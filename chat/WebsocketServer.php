<?php
require_once __DIR__ . '/../vendor/autoload.php';

use Ratchet\MessageComponentInterface;
use Ratchet\ConnectionInterface;

class WebsocketServer implements MessageComponentInterface {
    protected $clients;
    private $pdo;
    private $clientConversations;

    public function __construct() {
        $this->clients = new \SplObjectStorage;
        $this->clientConversations = [];

        // Database connection (Modify these credentials)
        $dsn = "mysql:host=localhost;dbname=raceconnect;charset=utf8mb4";
        $username = "root";
        $password = "";

        try {
            $this->pdo = new PDO($dsn, $username, $password, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
            ]);
        } catch (PDOException $e) {
            die("Database connection failed: " . $e->getMessage());
        }
    }

    public function onOpen(ConnectionInterface $conn) {
        $this->clients->attach($conn);
        echo "New connection ({$conn->resourceId})\n";
    }

    public function onMessage(ConnectionInterface $from, $msg) {
        $data = json_decode($msg, true);
        
        if (isset($data['type'])) {
            if ($data['type'] === 'fetch_messages' && isset($data['conversation_id'])) {
                echo "Fetching messages for conversation ID: " . $data['conversation_id'] . "\n"; // Debugging
                $this->fetchMessages($from, $data['conversation_id']);
            } elseif ($data['type'] === 'send_message' && isset($data['conversation_id'], $data['sender_id'], $data['receiver_id'], $data['message'])) {
                $this->storeAndBroadcastMessage($from, $data);
            }
        }
    }
    

    private function fetchMessages(ConnectionInterface $client, $conversation_id) {
        try {
            $stmt = $this->pdo->prepare("
                SELECT id, sender_id, receiver_id, message, status, created_at 
                FROM messages 
                WHERE conversation_id = :conversation_id 
                ORDER BY created_at ASC
            ");
            $stmt->execute([':conversation_id' => $conversation_id]);
            $messages = $stmt->fetchAll();
    
            echo "Fetched " . count($messages) . " messages for conversation ID: $conversation_id\n";
    
            $client->send(json_encode([
                "type" => "conversation_history",
                "conversation_id" => $conversation_id,
                "messages" => $messages ?: [], // Ensure it sends an empty array if no messages
            ]));
        } catch (PDOException $e) {
            echo "Database error: " . $e->getMessage() . "\n";
            $client->send(json_encode([
                "type" => "error",
                "message" => "An error occurred while fetching messages."
            ]));
        }
    }
    
    
    private function storeAndBroadcastMessage(ConnectionInterface $from, $data) {
        $conversation_id = $data['conversation_id'];
        $sender_id = $data['sender_id'];
        $receiver_id = $data['receiver_id'];
        $message = $data['message'];

        $stmt = $this->pdo->prepare("
            SELECT * FROM conversations 
            WHERE id = :conversation_id 
            AND (buyer_id = :sender_id OR seller_id = :sender_id)
        ");
        $stmt->execute([
            ':conversation_id' => $conversation_id,
            ':sender_id' => $sender_id
        ]);

        if ($stmt->rowCount() === 0) {
            $from->send(json_encode([
                "type" => "error",
                "message" => "You are not allowed to send messages in this conversation."
            ]));
            return;
        }

        $stmt = $this->pdo->prepare("
            INSERT INTO messages (conversation_id, sender_id, receiver_id, message, status)
            VALUES (:conversation_id, :sender_id, :receiver_id, :message, 'sent')
        ");
        $stmt->execute([
            ':conversation_id' => $conversation_id,
            ':sender_id' => $sender_id,
            ':receiver_id' => $receiver_id,
            ':message' => $message
        ]);

        $message_id = $this->pdo->lastInsertId();

        $updateStmt = $this->pdo->prepare("
            UPDATE conversations SET last_message = :message, last_message_time = NOW()
            WHERE id = :conversation_id
        ");
        $updateStmt->execute([
            ':message' => $message,
            ':conversation_id' => $conversation_id
        ]);

        foreach ($this->clients as $client) {
            if ($client !== $from) {
                $client->send(json_encode([
                    "type" => "new_message",
                    "message_id" => $message_id,
                    "conversation_id" => $conversation_id,
                    "sender_id" => $sender_id,
                    "receiver_id" => $receiver_id,
                    "message" => $message,
                    "status" => "sent",
                    "timestamp" => date('Y-m-d H:i:s')
                ]));
            }
        }
    }

    public function onClose(ConnectionInterface $conn) {
        $this->clients->detach($conn);
        unset($this->clientConversations[$conn->resourceId]);
        echo "Connection {$conn->resourceId} closed\n";
    }

    public function onError(ConnectionInterface $conn, \Exception $e) {
        echo "Error: {$e->getMessage()}\n";
        $conn->close();
    }
}

$server = \Ratchet\Server\IoServer::factory(
    new \Ratchet\Http\HttpServer(
        new \Ratchet\WebSocket\WsServer(
            new WebsocketServer()
        )
    ),
    8080
);

echo "WebSocket server running on port 8080...\n";
$server->run();