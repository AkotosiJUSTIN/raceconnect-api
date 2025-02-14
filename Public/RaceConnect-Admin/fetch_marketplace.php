<?php
// Include the database connection script
require_once __DIR__ . '/../../db_connect.php';

// Start session
session_start();

// Check if the user is logged in
if (!isset($_SESSION['email'])) {
    // Redirect to login page if not logged in
    header("Location: index_login.html");
    exit();
}

try {
    // Fetch marketplace items
    $queryItems = "SELECT id, seller_id, title, description, price, category, image_url, favorite_count, status, created_at, updated_at FROM marketplace_items";
    $resultItems = $conn->query($queryItems);

    $items = [];
    if ($resultItems->num_rows > 0) {
        while ($row = $resultItems->fetch_assoc()) {
            $items[] = $row;
        }
    }

    // Fetch likes data
    $queryLikes = "SELECT id, user_id, marketplace_item_id, created_at FROM marketplace_item_likes";
    $resultLikes = $conn->query($queryLikes);

    $likes = [];
    if ($resultLikes->num_rows > 0) {
        while ($row = $resultLikes->fetch_assoc()) {
            $likes[] = $row;
        }
    }

    // Prepare the response
    $response = [
        'items' => $items,
        'likes' => $likes
    ];

    // Set the content type to JSON
    header('Content-Type: application/json');

    // Output the JSON response
    echo json_encode($response);
} catch (Exception $e) {
    // Handle errors
    http_response_code(500); // Internal Server Error
    echo json_encode(['error' => $e->getMessage()]);
}
?>