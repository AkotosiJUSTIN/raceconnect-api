<?php
include '../raceconnect-api-with-composer/db_connect.php';

// Start session
session_start();

// Check if the user is logged in
if (!isset($_SESSION['email'])) {
    // Redirect to login page if not logged in
    header("Location: index_login.html");
    exit();
}

// Fetch posts from the database
$query = "SELECT id, user_id, title, content, img_url, like_count, comment_count, repost_count, created_at FROM posts";
$result = $conn->query($query);

$posts = [];
if ($result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $posts[] = $row;
    }
}

header('Content-Type: application/json');
echo json_encode($posts);
?>