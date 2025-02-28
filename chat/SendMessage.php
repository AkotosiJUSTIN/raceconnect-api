<?php
require_once __DIR__ . '/../config/database.php'; // Ensure this path is correct

header('Content-Type: application/json');

$data = json_decode(file_get_contents("php://input"), true);

if (!isset($data['buyer_id'], $data['seller_id'], $data['product_id'], $data['sender_id'], $data['receiver_id'], $data['message'])) {
    echo json_encode(["success" => false, "message" => "Missing required fields"]);
    exit;
}

$buyer_id = $data['buyer_id'];
$seller_id = $data['seller_id'];
$product_id = $data['product_id'];
$sender_id = $data['sender_id'];
$receiver_id = $data['receiver_id'];
$message = $data['message'];

try {
    // Ensure $pdo is defined and connected to the database
    $pdo = new PDO('mysql:host=localhost;dbname=raceconnect', 'root', ''); // Update with your DB credentials
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    $pdo->beginTransaction();

    // Check if conversation exists
    $query = $pdo->prepare("SELECT id FROM conversations WHERE buyer_id = ? AND seller_id = ? AND product_id = ?");
    $query->execute([$buyer_id, $seller_id, $product_id]);
    $conversation = $query->fetch();

    if (!$conversation) {
        // Create a new conversation
        $stmt = $pdo->prepare("INSERT INTO conversations (buyer_id, seller_id, product_id, last_message, last_message_time) VALUES (?, ?, ?, ?, NOW())");
        $stmt->execute([$buyer_id, $seller_id, $product_id, $message]);
        $conversation_id = $pdo->lastInsertId();
    } else {
        $conversation_id = $conversation['id'];
        
        // Update last message in conversation
        $stmt = $pdo->prepare("UPDATE conversations SET last_message = ?, last_message_time = NOW() WHERE id = ?");
        $stmt->execute([$message, $conversation_id]);
    }

    // Insert message into `messages` table
    $stmt = $pdo->prepare("INSERT INTO messages (conversation_id, sender_id, receiver_id, message, status) VALUES (?, ?, ?, ?, 'sent')");
    $stmt->execute([$conversation_id, $sender_id, $receiver_id, $message]);

    $pdo->commit();

    // Notify WebSocket clients
    $websocketData = json_encode([
        "conversation_id" => $conversation_id,
        "sender_id" => $sender_id,
        "receiver_id" => $receiver_id,
        "message" => $message,
        "status" => "sent"
    ]);

    $websocketClient = stream_socket_client("tcp://127.0.0.1:8080", $errno, $errstr, 30);
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
    $pdo->rollBack();
    echo json_encode(["success" => false, "message" => "Error: " . $e->getMessage()]);
}
?>
