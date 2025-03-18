<?php
require_once __DIR__ . '/../vendor/autoload.php';

use Ratchet\MessageComponentInterface;
use Ratchet\ConnectionInterface;
use Dotenv\Dotenv;
use Ratchet\Server\IoServer;
use Ratchet\Http\HttpServer;
use Ratchet\WebSocket\WsServer;
use React\Socket\Server as ReactServer;
use React\EventLoop\Factory as LoopFactory;
use Aws\S3\S3Client;
use Aws\Exception\AwsException;
use Exception;

class WebsocketServer implements MessageComponentInterface {
    protected $clients;
    private $pdo;
    private $userConnections;
    private $offlineMessages;
    private $s3;

    public function __construct() {
        $dotenv = Dotenv::createImmutable(__DIR__ . '/../');
        $dotenv->load();

        date_default_timezone_set('UTC');
        echo "Server timezone set to: " . date_default_timezone_get() . "\n";

        $this->clients = new \SplObjectStorage;
        $this->userConnections = [];
        $this->offlineMessages = [];

        $dbHost = $_ENV['DB_HOST'];
        $dbName = $_ENV['DB_NAME'];
        $dbUser = $_ENV['DB_USER'];
        $dbPass = $_ENV['DB_PASS'];

        $dsn = "mysql:host={$dbHost};dbname={$dbName};charset=utf8mb4";

        try {
            $this->pdo = new PDO($dsn, $dbUser, $dbPass, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
            ]);
            $this->pdo->exec("SET time_zone = '+00:00';");
            echo "Database connected successfully\n";
        } catch (PDOException $e) {
            die("Database connection failed: " . $e->getMessage());
        }

        $this->s3 = new S3Client([
            'version' => 'latest',
            'region'  => 'ap-southeast-2',
            'credentials' => [
                'key'    => $_ENV['AWS_ACCESS_KEY'],
                'secret' => $_ENV['AWS_SECRET_KEY'],
            ],
            'http'    => [
                'verify' => false
            ]
        ]);
    }

    private function isValidImage($imageData) {
        // Check if the image data is not empty and within size limits (25MB)
        return (strlen($imageData) > 0 && strlen($imageData) <= 25000000);
    }

    private function uploadImageToS3($imageData, $imageName) {
        if (!$this->isValidImage($imageData)) {
            throw new Exception("Invalid image file: size must be between 0 and 25MB.");
        }

        $uniqueId = uniqid();
        $uniqueImageName = $uniqueId . '-' . basename($imageName);

        try {
            $result = $this->s3->putObject([
                'Bucket' => 'raceconnect-images',
                'Key'    => 'chat-images/' . $uniqueImageName,
                'Body'   => $imageData, // Use the raw binary data directly
                'ContentType' => 'image/jpeg', // Ensure correct content type
                //'ACL'    => 'public-read' // Make the image publicly accessible
            ]);
            $objectUrl = $result['ObjectURL'] ?? $this->s3->getObjectUrl('raceconnect-images', 'chat-images/' . $uniqueImageName);
            echo "Image uploaded to S3: $objectUrl\n";
            return $objectUrl;
        } catch (AwsException $e) {
            echo "S3 upload error: " . $e->getMessage() . "\n";
            throw new Exception('Failed to upload image to S3: ' . $e->getMessage());
        }
    }

    private function saveMessageImage($messageId, $imageUrl) {
        try {
            $stmt = $this->pdo->prepare("INSERT INTO Message_Images (message_id, image_url) VALUES (:message_id, :image_url)");
            $success = $stmt->execute([
                ':message_id' => (int) $messageId,
                ':image_url' => filter_var($imageUrl, FILTER_SANITIZE_URL)
            ]);
            if ($success) {
                echo "Image URL saved to Message_Images: $imageUrl\n";
            } else {
                echo "Failed to save image URL to Message_Images\n";
            }
            return $success;
        } catch (PDOException $e) {
            echo "Database error saving image URL: " . $e->getMessage() . "\n";
            throw new Exception('Failed to save image URL to database: ' . $e->getMessage());
        }
    }

    public function onOpen(ConnectionInterface $conn) {
        $this->clients->attach($conn);
        $query = $conn->httpRequest->getUri()->getQuery();
        parse_str($query, $params);

        if (isset($params['user_id'])) {
            $userId = (string)$params['user_id'];
            $this->registerUser($conn, $userId);
            echo "New connection for user $userId ({$conn->resourceId})\n";
        } else {
            $conn->send(json_encode(['error' => 'No user_id provided']));
            echo "No user_id provided for connection ({$conn->resourceId})\n";
        }
    }

    private function registerUser(ConnectionInterface $conn, string $userId) {
        if (isset($this->userConnections[$userId])) {
            $oldConn = $this->userConnections[$userId];
            try {
                $oldConn->close();
                $this->clients->detach($oldConn);
                echo "Closed old connection for user $userId (resource {$oldConn->resourceId})\n";
            } catch (Exception $e) {
                echo "Failed to close old connection for user $userId: {$e->getMessage()}\n";
            }
        }

        $this->userConnections[$userId] = $conn;

        $stmt = $this->pdo->prepare("
            INSERT INTO websocket_clients (user_id, connection_id)
            VALUES (:user_id, :connection_id)
            ON DUPLICATE KEY UPDATE connection_id = :connection_id_update
        ");
        $stmt->execute([
            ':user_id' => $userId,
            ':connection_id' => $conn->resourceId,
            ':connection_id_update' => $conn->resourceId
        ]);

        if (isset($this->offlineMessages[$userId])) {
            foreach ($this->offlineMessages[$userId] as $queuedMessage) {
                $conn->send($queuedMessage);
            }
            unset($this->offlineMessages[$userId]);
        }

        $conn->send(json_encode(['status' => 'connected', 'user_id' => $userId]));
    }

    public function onMessage(ConnectionInterface $from, $msg) {
        echo "Received message: $msg\n";
        $data = json_decode($msg, true);

        if (!isset($data['type'])) {
            $from->send(json_encode(['error' => 'Invalid message format']));
            return;
        }

        switch ($data['type']) {
            case 'fetch_messages':
                if (isset($data['conversation_id'])) {
                    $limit = isset($data['limit']) ? (int)$data['limit'] : 20;
                    $offset = isset($data['offset']) ? (int)$data['offset'] : 0;
                    $this->fetchMessages($from, $data['conversation_id'], $limit, $offset);
                }
                break;

            case 'send_message':
                if (isset($data['conversation_id'], $data['sender_id'], $data['receiver_id'])) {
                    $this->storeAndBroadcastMessage($from, $data);
                }
                break;

            case 'message_read':
                if (isset($data['message_id'], $data['user_id'])) {
                    $this->markMessageAsRead($from, $data);
                }
                break;

            case 'typing':
                if (isset($data['conversation_id'], $data['user_id'])) {
                    $this->broadcastTyping($from, $data);
                }
                break;
        }
    }

    private function fetchMessages(ConnectionInterface $client, $conversation_id, $limit = 20, $offset = 0) {
        try {
            $stmt = $this->pdo->prepare("
                SELECT
                    m.id, m.sender_id, m.receiver_id,
                    m.message_type, m.message, m.media_url,
                    m.status, m.created_at, m.delivered_at, m.read_at
                FROM messages m
                WHERE m.conversation_id = :conversation_id
                AND m.is_deleted = 0
                ORDER BY m.created_at ASC
                LIMIT :limit OFFSET :offset
            ");
            $stmt->bindParam(':conversation_id', $conversation_id, PDO::PARAM_INT);
            $stmt->bindParam(':limit', $limit, PDO::PARAM_INT);
            $stmt->bindParam(':offset', $offset, PDO::PARAM_INT);
            $stmt->execute();
            $messages = $stmt->fetchAll();

            // Fetch images for each message
            foreach ($messages as &$message) {
                $imageStmt = $this->pdo->prepare("SELECT image_url FROM Message_Images WHERE message_id = :message_id");
                $imageStmt->bindValue(':message_id', $message['id'], PDO::PARAM_INT);
                $imageStmt->execute();
                $message['images'] = $imageStmt->fetchAll(PDO::FETCH_COLUMN);
                // Ensure created_at is in the correct format
                $message['created_at'] = date('Y-m-d H:i:s', strtotime($message['created_at']));
            }

            $response = json_encode([
                "type" => "conversation_history",
                "conversation_id" => $conversation_id,
                "messages" => $messages ?: [],
                "limit" => $limit,
                "offset" => $offset
            ]);
            $client->send($response);
        } catch (PDOException $e) {
            echo "Error fetching messages: " . $e->getMessage() . "\n";
            $client->send(json_encode([
                "type" => "error",
                "message" => "Failed to fetch messages: " . $e->getMessage()
            ]));
        }
    }

    private function storeAndBroadcastMessage(ConnectionInterface $from, $data) {
        $conversation_id = $data['conversation_id'];
        $sender_id = (string)$data['sender_id'];
        $receiver_id = (string)$data['receiver_id'];
        $message = $data['message'] ?? '';
        $message_type = $data['message_type'] ?? 'text';
        $image_data = $data['image_data'] ?? null;
        $media_url = null;
        $images = [];

        // Handle image upload to S3 if it's an image message
        if ($message_type === 'image' && $image_data) {
            try {
                // Decode the Base64 image data
                $binaryData = base64_decode($image_data);
                if ($binaryData === false) {
                    throw new Exception("Failed to decode Base64 image data");
                }
                $media_url = $this->uploadImageToS3($binaryData, 'chat-image.jpg');
                $images[] = $media_url;
            } catch (Exception $e) {
                echo "Image upload error: " . $e->getMessage() . "\n";
                $from->send(json_encode([
                    "type" => "error",
                    "message" => "Failed to upload image to S3: " . $e->getMessage()
                ]));
                return;
            }
        }

        try {
            $this->pdo->beginTransaction();

            // Verify conversation
            $stmt = $this->pdo->prepare("
                SELECT status
                FROM conversations
                WHERE id = :conversation_id
                AND (buyer_id = :sender_id OR seller_id = :sender_id)
                AND status = 'active'
            ");
            $stmt->execute([
                ':conversation_id' => $conversation_id,
                ':sender_id' => $sender_id
            ]);

            if ($stmt->rowCount() === 0) {
                throw new Exception("Invalid conversation or permission denied");
            }

            // Insert message into database
            $stmt = $this->pdo->prepare("
                INSERT INTO messages (
                    conversation_id, sender_id, receiver_id,
                    message_type, message, media_url, status, created_at
                ) VALUES (
                    :conversation_id, :sender_id, :receiver_id,
                    :message_type, :message, :media_url, 'sent', NOW()
                )
            ");
            $stmt->execute([
                ':conversation_id' => $conversation_id,
                ':sender_id' => $sender_id,
                ':receiver_id' => $receiver_id,
                ':message_type' => $message_type,
                ':message' => $message,
                ':media_url' => $media_url
            ]);

            $message_id = $this->pdo->lastInsertId();

            // Save image to Message_Images table if applicable
            if ($media_url) {
                $this->saveMessageImage($message_id, $media_url);
            }

            // Update conversation
            $this->updateConversation($conversation_id, $message);

            $this->pdo->commit();

            // Fetch the created_at timestamp from the database to ensure consistency
            $stmt = $this->pdo->prepare("SELECT created_at FROM messages WHERE id = :message_id");
            $stmt->execute([':message_id' => $message_id]);
            $created_at = $stmt->fetchColumn();
            $created_at = date('Y-m-d H:i:s', strtotime($created_at));

            $messageData = [
                "type" => "new_message",
                "message_id" => $message_id,
                "conversation_id" => $conversation_id,
                "sender_id" => $sender_id,
                "receiver_id" => $receiver_id,
                "message_type" => $message_type,
                "message" => $message,
                "media_url" => $media_url,
                "images" => $images,
                "status" => "sent",
                "timestamp" => $created_at
            ];

            $confirmationData = [
                "type" => "message_sent",
                "message_id" => $message_id,
                "conversation_id" => $conversation_id,
                "sender_id" => $sender_id,
                "receiver_id" => $receiver_id,
                "message_type" => $message_type,
                "message" => $message,
                "media_url" => $media_url,
                "images" => $images,
                "status" => "sent",
                "timestamp" => $created_at
            ];

            // Send confirmation to sender
            $from->send(json_encode($confirmationData));

            // Broadcast to receiver
            $this->sendMessage($receiver_id, $messageData);

        } catch (Exception $e) {
            $this->pdo->rollBack();
            echo "Error storing message: " . $e->getMessage() . "\n";
            $from->send(json_encode([
                "type" => "error",
                "message" => "Failed to send message: " . $e->getMessage()
            ]));
        }
    }

    private function updateConversation($conversation_id, $message): void {
        $stmt = $this->pdo->prepare("
            UPDATE conversations
            SET
                last_message = :message,
                last_message_time = NOW(),
                last_activity_at = NOW()
            WHERE id = :conversation_id
        ");
        $stmt->execute([
            ':message' => $message,
            ':conversation_id' => $conversation_id
        ]);
    }

    private function sendMessage(string $userId, array $data): bool {
        $messageJson = json_encode($data);

        if (!isset($this->userConnections[$userId])) {
            echo "User $userId is offline; queuing message\n";
            $this->offlineMessages[$userId][] = $messageJson;
            return false;
        }

        $conn = $this->userConnections[$userId];
        try {
            $conn->send($messageJson);
            echo "Message sent to user $userId (resource {$conn->resourceId})\n";
            return true;
        } catch (\Exception $e) {
            echo "Failed to send to user $userId: {$e->getMessage()}\n";
            return false;
        }
    }

    private function markMessageAsRead(ConnectionInterface $from, $data) {
        try {
            $stmt = $this->pdo->prepare("
                UPDATE messages
                SET
                    status = 'read',
                    read_at = NOW()
                WHERE id = :message_id
                AND receiver_id = :user_id
                AND status != 'read'
            ");
            $stmt->execute([
                ':message_id' => $data['message_id'],
                ':user_id' => $data['user_id']
            ]);

            if ($stmt->rowCount() > 0) {
                $broadcastData = [
                    "type" => "message_status",
                    "message_id" => $data['message_id'],
                    "status" => "read",
                    "read_at" => date('Y-m-d H:i:s')
                ];

                $stmt = $this->pdo->prepare("
                    SELECT sender_id, receiver_id
                    FROM messages
                    WHERE id = :message_id
                ");
                $stmt->execute([':message_id' => $data['message_id']]);
                $message = $stmt->fetch();

                $this->sendMessage($message['sender_id'], $broadcastData);
                $this->sendMessage($message['receiver_id'], $broadcastData);
            }
        } catch (PDOException $e) {
            echo "Error marking message as read: " . $e->getMessage() . "\n";
            $from->send(json_encode([
                "type" => "error",
                "message" => "Failed to update message status: " . $e->getMessage()
            ]));
        }
    }

    private function broadcastTyping(ConnectionInterface $from, $data) {
        $broadcastData = [
            "type" => "typing",
            "conversation_id" => $data['conversation_id'],
            "user_id" => $data['user_id']
        ];

        $stmt = $this->pdo->prepare("
            SELECT buyer_id, seller_id
            FROM conversations
            WHERE id = :conversation_id
        ");
        $stmt->execute([':conversation_id' => $data['conversation_id']]);
        $conv = $stmt->fetch();

        if ($conv) {
            $otherUserId = ($conv['buyer_id'] == $data['user_id']) ? $conv['seller_id'] : $conv['buyer_id'];
            $this->sendMessage($otherUserId, $broadcastData);
        }
    }

    public function onClose(ConnectionInterface $conn) {
        $this->clients->detach($conn);

        $stmt = $this->pdo->prepare("DELETE FROM websocket_clients WHERE connection_id = :connection_id");
        $stmt->execute([':connection_id' => $conn->resourceId]);

        foreach ($this->userConnections as $userId => $connection) {
            if ($connection === $conn) {
                unset($this->userConnections[$userId]);
                echo "User $userId disconnected (resource {$conn->resourceId})\n";
                break;
            }
        }
        echo "Connection {$conn->resourceId} closed\n";
    }

    public function onError(ConnectionInterface $conn, \Exception $e) {
        echo "Error: {$e->getMessage()}\n";
        $conn->send(json_encode([
            "type" => "error",
            "message" => "Server error occurred"
        ]));
        $conn->close();
    }
}

$dotenv = Dotenv::createImmutable(__DIR__ . '/../');
$dotenv->load();
$host = $_ENV['WS_HOST'] ?? '0.0.0.0';
$port = $_ENV['WS_PORT'] ?? 8080;

$loop = LoopFactory::create();
$socket = new ReactServer("{$host}:{$port}", $loop);

$server = new IoServer(
    new HttpServer(
        new WsServer(
            new WebsocketServer()
        )
    ),
    $socket,
    $loop
);

echo "WebSocket server running on ws://{$host}:{$port}...\n";
$server->run();