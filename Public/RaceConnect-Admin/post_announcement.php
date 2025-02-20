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
    // Redirect to login page if not logged in
    header("Location: index_login.html");
    exit();
}

// Get the announcement data from the POST request
$title = isset($_POST['announcementTitle']) ? trim($_POST['announcementTitle']) : '';
$content = isset($_POST['announcementContent']) ? trim($_POST['announcementContent']) : '';

header('Content-Type: application/json'); // Ensure the response is JSON

if ($title && $content) {
    // Insert the announcement into the database
    $query = "INSERT INTO announcements (title, content) VALUES (?, ?)";
    $stmt = $conn->prepare($query);
    $stmt->bind_param('ss', $title, $content);
    if ($stmt->execute()) {
        echo json_encode(['success' => true, 'message' => 'Announcement posted successfully']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to post announcement']);
    }
    $stmt->close();
} else {
    echo json_encode(['success' => false, 'message' => 'Title and content are required']);
}

$conn->close();
?>