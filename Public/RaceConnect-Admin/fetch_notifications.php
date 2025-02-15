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

// Fetch notifications from the database
$query = "SELECT id, user_id, content, is_read, created_at FROM notifications";
$result = $conn->query($query);

$notifications = [];
if ($result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $notifications[] = $row;
    }
}

header('Content-Type: application/json');
echo json_encode($notifications);
?>