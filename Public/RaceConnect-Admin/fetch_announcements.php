<?php
// Include the database connection script
require_once __DIR__ . '/../../db_connect.php';

// Start session
session_start();

// Check if the user is logged in
if (!isset($_SESSION['email'])) {
    http_response_code(401); // Unauthorized
    echo json_encode(['error' => 'User not logged in']);
    exit();
}

try {
    // Fetch announcements from the database
    $query = "SELECT id, title, content, created_at FROM announcements ORDER BY created_at DESC";
    $result = $conn->query($query);

    $announcements = [];
    if ($result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $announcements[] = $row;
        }
    }

    // Return announcements as JSON
    header('Content-Type: application/json');
    echo json_encode(['announcements' => $announcements]);
} catch (Exception $e) {
    http_response_code(500); // Internal Server Error
    echo json_encode(['error' => $e->getMessage()]);
}
?>