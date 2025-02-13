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

// Fetch users from the database
$query = "SELECT username, created_at, status FROM users";
$result = $conn->query($query);

$users = [];
if ($result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $users[] = $row;
    }
}

header('Content-Type: application/json');
echo json_encode($users);
?>