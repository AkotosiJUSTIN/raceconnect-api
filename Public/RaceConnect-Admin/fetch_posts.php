<?php
// Include the database connection script
require_once __DIR__ . '/../../db_connect.php';

// Enable error reporting for debugging
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Start session
session_start();

// Check if the user is logged in
if (!isset($_SESSION['email'])) {
    header("Location: index_login.html");
    exit();
}

// Fetch posts from the database
$query = "
    SELECT 
        p.id, 
        p.user_id, 
        p.title, 
        p.content, 
        p.like_count, 
        p.comment_count, 
        p.repost_count, 
        p.created_at,
        GROUP_CONCAT(DISTINCT pi.image_url) AS images,
        GROUP_CONCAT(DISTINCT pc.comment) AS comments,
        GROUP_CONCAT(DISTINCT pl.user_id) AS likes,
        GROUP_CONCAT(DISTINCT pr.user_id) AS reposts
    FROM posts p
    LEFT JOIN post_images pi ON p.id = pi.post_id
    LEFT JOIN post_comments pc ON p.id = pc.post_id
    LEFT JOIN post_likes pl ON p.id = pl.post_id
    LEFT JOIN post_reposts pr ON p.id = pr.post_id
    WHERE p.status NOT IN ('hidden', 'archived')
    GROUP BY p.id
    ORDER BY p.created_at DESC
";

// Initialize response array
$response = ['success' => false, 'data' => [], 'error' => null];

try {
    $result = $conn->query($query);
    if ($result === false) {
        throw new Exception("Database query failed: " . $conn->error);
    }

    $posts = [];
    if ($result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $posts[] = $row;
        }
    }

    $response['success'] = true;
    $response['data'] = $posts;
} catch (Exception $e) {
    $response['error'] = $e->getMessage();
}

header('Content-Type: application/json');
echo json_encode($response);

$conn->close();
?>