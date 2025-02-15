<?php
require_once __DIR__ . '/../../db_connect.php';

if ($conn->connect_error) {
    die(json_encode(["error" => "Connection failed: " . $conn->connect_error]));
}

session_start();
if (!isset($_SESSION['email'])) {
    header("Location: index_login.html");
    exit();
}

try {
    // Fetch marketplace items including `favorite_count`
    $queryItems = "SELECT id, seller_id, title, description, price, category, image_url, favorite_count, status, created_at, updated_at FROM `marketplace_items`";
    $resultItems = $conn->query($queryItems);

    $items = [];
    while ($row = $resultItems->fetch_assoc()) {
        $items[] = $row;
    }

    // Send JSON response
    header('Content-Type: application/json; charset=UTF-8');
    echo json_encode(['items' => $items]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
?>
