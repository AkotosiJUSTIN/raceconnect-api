<?php

require_once __DIR__ . '/../vendor/autoload.php'; // Ensure this path is correct
require_once __DIR__ . '/../config/database.php'; // Ensure this path is correct

use Dotenv\Dotenv;

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
            $this->pdo = new PDO("mysql:host=$dbHost;dbname=$dbName", $dbUser, $dbPass);
            $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        } catch (PDOException $e) {
            $this->handleError(500, "Database connection failed: " . $e->getMessage());
        }
    }

    private function handleError($statusCode, $message) {
        http_response_code($statusCode);
        echo json_encode(['success' => false, 'message' => $message]);
        exit;
    }

    public function sendMessage($data) {
        if (!isset($data['buyer_id'], $data['seller_id'], $data['product_id'], $data['message'])) {
            $this->handleError(400, "Missing required fields");
        }

        $buyer_id = $data['buyer_id'];
        $seller_id = $data['seller_id'];
        $product_id = $data['product_id'];
        $sender_id = $buyer_id;
        $receiver_id = $seller_id;
        $message = $data['message'];

        try {
            // Fetch sender's username
            $sender_query = $this->pdo->prepare("SELECT username FROM users WHERE id = ?");
            $sender_query->execute([$sender_id]);
            $sender = $sender_query->fetch();
            $sender_username = $sender['username'];

            // Fetch product title
            $product_query = $this->pdo->prepare("SELECT title FROM marketplace_items WHERE id = ?");
            $product_query->execute([$product_id]);
            $product = $product_query->fetch();
            $product_title = $product['title'];

            $this->pdo->beginTransaction();

            // Check if conversation exists
            $query = $this->pdo->prepare("SELECT id FROM conversations WHERE buyer_id = ? AND seller_id = ? AND product_id = ?");
            $query->execute([$buyer_id, $seller_id, $product_id]);
            $conversation = $query->fetch();

            if (!$conversation) {
                // Create a new conversation
                $stmt = $this->pdo->prepare("INSERT INTO conversations (buyer_id, seller_id, product_id, last_message, last_message_time) VALUES (?, ?, ?, ?, NOW())");
                $stmt->execute([$buyer_id, $seller_id, $product_id, $message]);
                $conversation_id = $this->pdo->lastInsertId();

                // Insert notification into `notifications` table
                $notification_stmt = $this->pdo->prepare("INSERT INTO notifications (user_id, type, content, created_at) VALUES (?, 'new_conversation', ?, NOW())");
                $notification_message = "You have a new conversation with $sender_username regarding product '$product_title'.";
                $notification_stmt->execute([$receiver_id, $notification_message]);
            } else {
                $conversation_id = $conversation['id'];
                
                // Update last message in conversation
                $stmt = $this->pdo->prepare("UPDATE conversations SET last_message = ?, last_message_time = NOW() WHERE id = ?");
                $stmt->execute([$message, $conversation_id]);
            }

            // Insert message into `messages` table
            $stmt = $this->pdo->prepare("INSERT INTO messages (conversation_id, sender_id, receiver_id, message, status) VALUES (?, ?, ?, ?, 'sent')");
            $stmt->execute([$conversation_id, $sender_id, $receiver_id, $message]);

            $this->pdo->commit();

            // Notify WebSocket clients
            $websocketData = json_encode([
                "conversation_id" => $conversation_id,
                "sender_id" => $sender_id,
                "receiver_id" => $receiver_id,
                "message" => $message,
                "status" => "sent"
            ]);

            $websocketClient = @stream_socket_client("tcp://127.0.0.1:8080", $errno, $errstr, 30);
            if ($websocketClient) {
                fwrite($websocketClient, $websocketData);
                fclose($websocketClient);
            }

            echo json_encode([
                "success" => true,
                "conversation_id" => $conversation_id,
                "message" => [
                    "sender_id" => $sender_id,
                    "receiver_id" => $receiver_id,
                    "message" => $message,
                    "status" => "sent"
                ]
            ]);
        } catch (Exception $e) {
            $this->pdo->rollBack();
            $this->handleError(500, "Error: " . $e->getMessage());
        }
    }
}

// Instantiate the controller and call the sendMessage method
$controller = new SendMessageController();
$data = json_decode(file_get_contents("php://input"), true);
$controller->sendMessage($data);
?>
