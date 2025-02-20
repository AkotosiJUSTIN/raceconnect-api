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

// Fetch posts from the database, excluding hidden and archived posts
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
        GROUP_CONCAT(DISTINCT pi.image_url) AS images
    FROM posts p
    LEFT JOIN post_images pi ON p.id = pi.post_id
    WHERE p.status NOT IN ('hidden', 'archived') OR p.status IS NULL
    GROUP BY p.id
";
$result = $conn->query($query);

$posts = [];
if ($result === false) {
    // Query failed
    $response = ['success' => false, 'error' => $conn->error];
} else if ($result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        // Convert the comma-separated images string into an array
        $row['images'] = $row['images'] ? explode(',', $row['images']) : [];
        $posts[] = $row;
    }
}

header('Content-Type: application/json');
echo json_encode(['success' => true, 'data' => $posts]);
?>