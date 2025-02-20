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

// Get the post ID from the request
$postId = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($postId > 0) {
    // Update the post status to hidden in the database
    $query = "UPDATE posts SET status = 'hidden' WHERE id = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param('i', $postId);
    if ($stmt->execute()) {
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to hide post']);
    }
    $stmt->close();
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid post ID']);
}

$conn->close();
?>