<?php
namespace Controller; // Add the Controller namespace

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../config/database.php';

use Dotenv\Dotenv;
use PDO;
use PDOException;
use Exception;

header('Content-Type: application/json');


class SendMessageController {
    private $pdo;

    public function __construct() {
        $dotenv = Dotenv::createImmutable(__DIR__ . '/../');
        $dotenv->load();

        $dbHost = $_ENV['DB_HOST'];
        $dbName = $_ENV['DB_NAME'];
        $dbUser = $_ENV['DB_USER'];
        $dbPass = $_ENV['DB_PASS'];

        try {
            $this->pdo = new PDO("mysql:host=$dbHost;dbname=$dbName;charset=utf8mb4", $dbUser, $dbPass, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
            ]);
            error_log("API: Database connected successfully\n");
        } catch (PDOException $e) {
            $this->handleError(500, "Database connection failed: " . $e->getMessage());
        }
    }

    private function handleError($statusCode, $message) {
        http_response_code($statusCode);
        echo json_encode(['success' => false, 'message' => $message]);
        exit;
    }
    public function processRequest($method, $id = null, $data = null) {
        if ($method === 'POST') {
            if ($data === null) {
                $this->handleError(400, 'Bad Request: No JSON data provided');
            }
            $this->sendMessage($data);
        } elseif ($method === 'GET') {
            if ($id === null) {
                $this->handleError(400, 'Bad Request: User ID is required');
            }
            $this->getUserConversations($id);
        } else {
            $this->handleError(405, 'Method Not Allowed: Use POST for sending messages or GET for fetching conversations');
        }
    }

    private function sendMessage($data) {
        // Define integer fields that should be positive integers
        $integerFields = ['buyer_id', 'seller_id', 'product_id', 'sender_id'];
        foreach ($integerFields as $field) {
            if (!isset($data[$field]) || !is_int($data[$field]) || $data[$field] <= 0) {
                $this->handleError(400, "Invalid or missing required field: $field");
            }
        }

        // Validate the message field separately as a string
        if (!isset($data['message']) || !is_string($data['message']) || empty($data['message'])) {
            $this->handleError(400, "Invalid or missing required field: message");
        }

        $buyer_id = (int)$data['buyer_id']; // Cast to int for security
        $seller_id = (int)$data['seller_id'];
        $product_id = (int)$data['product_id'];
        $sender_id = (int)$data['sender_id'];
        $receiver_id = ($sender_id == $buyer_id) ? $seller_id : $buyer_id;
        $message = $data['message'];
        $message_type = $data['message_type'] ?? 'text';
        $media_url = $data['media_url'] ?? null;

        if ($sender_id !== $buyer_id && $sender_id !== $seller_id) {
            $this->handleError(403, "Sender must be either buyer or seller");
        }

        try {
            $this->pdo->beginTransaction();

            $query = $this->pdo->prepare("
                SELECT id, status 
                FROM conversations 
                WHERE buyer_id = ? 
                AND seller_id = ? 
                AND product_id = ?
            ");
            $query->execute([$buyer_id, $seller_id, $product_id]);
            $conversation = $query->fetch();

            if (!$conversation) {
                $stmt = $this->pdo->prepare("
                    INSERT INTO conversations (
                        buyer_id, 
                        seller_id, 
                        product_id, 
                        last_message, 
                        last_message_time,
                        last_activity_at
                    ) VALUES (?, ?, ?, ?, NOW(), NOW())
                ");
                $stmt->execute([$buyer_id, $seller_id, $product_id, $message]);
                $conversation_id = $this->pdo->lastInsertId();

                $sender_query = $this->pdo->prepare("SELECT username FROM users WHERE id = ?");
                $sender_query->execute([$sender_id]);
                $sender = $sender_query->fetch();

                $product_query = $this->pdo->prepare("SELECT title FROM marketplace_items WHERE id = ?");
                $product_query->execute([$product_id]);
                $product = $product_query->fetch();

                if ($sender && $product) {
                    $notification_stmt = $this->pdo->prepare("
                        INSERT INTO notifications (
                            user_id, 
                            type, 
                            content, 
                            created_at
                        ) VALUES (?, 'new_conversation', ?, NOW())
                    ");
                    $notification_message = "New conversation from {$sender['username']} about '{$product['title']}'";
                    $notification_stmt->execute([$receiver_id, $notification_message]);
                } else {
                    error_log("API: Failed to fetch sender or product details for notification");
                }
            } else {
                $conversation_id = $conversation['id'];

                if ($conversation['status'] !== 'active') {
                    $this->handleError(403, "Cannot send message to inactive conversation");
                }

                $stmt = $this->pdo->prepare("
                    UPDATE conversations 
                    SET 
                        last_message = ?,
                        last_message_time = NOW(),
                        last_activity_at = NOW()
                    WHERE id = ?
                ");
                $stmt->execute([$message, $conversation_id]);
            }

            $stmt = $this->pdo->prepare("
                INSERT INTO messages (
                    conversation_id,
                    sender_id,
                    receiver_id,
                    message_type,
                    message,
                    media_url,
                    status,
                    created_at
                ) VALUES (?, ?, ?, ?, ?, ?, 'sent', NOW())
            ");
            $stmt->execute([
                $conversation_id,
                $sender_id,
                $receiver_id,
                $message_type,
                $message,
                $media_url
            ]);
            $message_id = $this->pdo->lastInsertId();

            $this->pdo->commit();

            $websocketData = json_encode([
                "type" => "new_message",
                "message_id" => $message_id,
                "conversation_id" => $conversation_id,
                "sender_id" => $sender_id,
                "receiver_id" => $receiver_id,
                "message_type" => $message_type,
                "message" => $message,
                "media_url" => $media_url,
                "status" => "sent",
                "timestamp" => date('Y-m-d H:i:s')
            ]);

            // Use textalk/websocket
            try {
                $client = new \WebSocket\Client("ws://127.0.0.1:8080");
                $client->text($websocketData);
                $client->close();
                error_log("API: WebSocket message sent: $websocketData");
            } catch (\Exception $e) {
                error_log("API: WebSocket connection failed: {$e->getMessage()}");
            }

            // Return response matching SendMessageResponse
            http_response_code(200);
            echo json_encode([
                "success" => true,
                "conversation_id" => $conversation_id,
                "message" => [
                    "id" => $message_id,
                    "sender_id" => $sender_id,
                    "receiver_id" => $receiver_id,
                    "message_type" => $message_type,
                    "message" => $message,
                    "media_url" => $media_url,
                    "status" => "sent",
                    "created_at" => date('Y-m-d H:i:s')
                ],
                "error" => null
            ]);
        } catch (Exception $e) {
            $this->pdo->rollBack();
            $this->handleError(500, "Error sending message: " . $e->getMessage());
        }
    }

    private function getUserConversations($userId) {
        try {
            $stmt = $this->pdo->prepare("
                SELECT 
                    c.id AS conversation_id,
                    c.buyer_id,
                    c.seller_id,
                    c.product_id,
                    c.last_message,
                    c.last_message_time,
                    c.last_activity_at,
                    u1.username AS buyer_username,
                    u2.username AS seller_username,
                    m.title AS product_title
                FROM 
                    conversations c
                JOIN 
                    users u1 ON c.buyer_id = u1.id
                JOIN 
                    users u2 ON c.seller_id = u2.id
                JOIN 
                    marketplace_items m ON c.product_id = m.id
                WHERE 
                    c.buyer_id = ? OR c.seller_id = ?
                ORDER BY 
                    c.last_activity_at DESC
            ");
            $stmt->execute([$userId, $userId]);
            $conversations = $stmt->fetchAll();

            http_response_code(200);
            echo json_encode([
                "success" => true,
                "conversations" => $conversations,
                "error" => null
            ]);
        } catch (Exception $e) {
            $this->handleError(500, "Error fetching conversations: " . $e->getMessage());
        }
    }
}