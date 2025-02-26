<?php
require __DIR__ . '/vendor/autoload.php';  // Composer dependencies
use Ratchet\MessageComponentInterface;
use Ratchet\ConnectionInterface;
use PDO;

class ChatServer implements MessageComponentInterface {
    protected $clients;
    private $pdo;
    private $users = [];

    public function __construct() {
        $this->clients = new \SplObjectStorage;
        $this->pdo = new PDO("mysql:host=localhost;dbname=raceconnect", "root", "");  // Update DB credentials
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    }

    public function onOpen(ConnectionInterface $conn) {
        $this->clients->attach($conn);
        echo "New connection ({$conn->resourceId})\n";
    }

    public function onMessage(ConnectionInterface $from, $msg) {
        $data = json_decode($msg, true);

        switch ($data['action']) {
            case 'connect':
                $this->handleConnect($from, $data);
                break;
            case 'send_message':
                $this->handleSendMessage($from, $data);
                break;
            case 'mark_read':
                $this->handleReadReceipts($data);
                break;
            case 'typing':
                $this->handleTypingIndicator($data);
                break;
        }
    }

    public function onClose(ConnectionInterface $conn) {
        $userId = $this->users[$conn->resourceId] ?? null;
        if ($userId) {
            $stmt = $this->pdo->prepare("UPDATE users SET online_status = 'offline' WHERE id = ?");
            $stmt->execute([$userId]);
            $this->broadcastUserStatus($userId, 'offline');
        }

        $this->clients->detach($conn);
        unset($this->users[$conn->resourceId]);
        echo "Connection {$conn->resourceId} has disconnected\n";
    }

    public function onError(ConnectionInterface $conn, \Exception $e) {
        echo "Error: {$e->getMessage()}\n";
        $conn->close();
    }

    private function handleConnect($conn, $data) {
        $userId = $data['user_id'];
        $this->users[$conn->resourceId] = $userId;

        $stmt = $this->pdo->prepare("UPDATE users SET online_status = 'online' WHERE id = ?");
        $stmt->execute([$userId]);

        $this->broadcastUserStatus($userId, 'online');
    }

    private function handleSendMessage(ConnectionInterface $from, $data) {
        $conversationId = $data['conversation_id'];
        $senderId = $data['sender_id'];
        $message = $data['message'];

        // Store message in DB
        $stmt = $this->pdo->prepare("INSERT INTO messages (conversation_id, sender_id, message, status) VALUES (?, ?, ?, 'sent')");
        $stmt->execute([$conversationId, $senderId, $message]);
        $messageId = $this->pdo->lastInsertId();

        // Send message to receiver
        foreach ($this->clients as $client) {
            if ($client !== $from) {
                $client->send(json_encode([
                    'action' => 'new_message',
                    'message_id' => $messageId,
                    'conversation_id' => $conversationId,
                    'sender_id' => $senderId,
                    'message' => $message,
                    'status' => 'sent'
                ]));
            }
        }
    }

    private function handleReadReceipts($data) {
        $conversationId = $data['conversation_id'];
        $receiverId = $data['receiver_id'];

        $stmt = $this->pdo->prepare("UPDATE messages SET status = 'read' WHERE conversation_id = ? AND sender_id != ?");
        $stmt->execute([$conversationId, $receiverId]);

        foreach ($this->clients as $client) {
            $client->send(json_encode([
                'action' => 'read_receipt',
                'conversation_id' => $conversationId
            ]));
        }
    }

    private function handleTypingIndicator($data) {
        $senderId = $data['sender_id'];
        $receiverId = $data['receiver_id'];

        foreach ($this->clients as $client) {
            if (isset($this->users[$client->resourceId]) && $this->users[$client->resourceId] == $receiverId) {
                $client->send(json_encode([
                    'action' => 'typing',
                    'sender_id' => $senderId
                ]));
            }
        }
    }

    private function broadcastUserStatus($userId, $status) {
        foreach ($this->clients as $client) {
            $client->send(json_encode([
                'action' => 'user_status',
                'user_id' => $userId,
                'status' => $status
            ]));
        }
    }
}

// Start the WebSocket Server
$server = \Ratchet\Server\IoServer::factory(
    new \Ratchet\Http\HttpServer(
        new \Ratchet\WebSocket\WsServer(
            new ChatServer()
        )
    ),
    8080
);
$server->run();
