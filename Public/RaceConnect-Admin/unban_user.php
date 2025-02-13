<?php
// Include the database connection script
require_once __DIR__ . '/../../db_connect.php';

session_start();

if (!isset($_SESSION['email'])) {
    header("Location: index_login.html");
    exit();
}

$username = $_POST['username'];

$query = "UPDATE users SET status = 'active' WHERE username = ?";
$stmt = $conn->prepare($query);
$stmt->bind_param('s', $username);
$stmt->execute();

$response = ['success' => $stmt->affected_rows > 0];
echo json_encode($response);

$stmt->close();
$conn->close();
?>