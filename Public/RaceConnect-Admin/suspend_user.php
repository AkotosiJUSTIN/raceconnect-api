<?php
// Include the database connection script
require_once __DIR__ . '/../../db_connect.php';

session_start();

if (!isset($_SESSION['email'])) {
    // Redirect to login page if not logged in
    header("Location: index_login.html");
    exit();
}

try {
    // Get POST data
    $username = $_POST['username'];
    $days = intval($_POST['days']);
    $suspensionEndDate = date('Y-m-d H:i:s', strtotime("+$days days"));

    // Prepare the SQL query
    $query = "UPDATE users SET status = 'suspended', suspension_days = ?, suspension_end_date = ? WHERE username = ?";
    $stmt = $conn->prepare($query);

    // Check if POST data is received
    if (!$username || !$days) {
        echo json_encode(['success' => false, 'error' => 'Missing data']);
        exit();
    }

    if ($stmt) {
        $stmt->bind_param("iss", $days, $suspensionEndDate, $username);
        $stmt->execute();
        $response = ['success' => $stmt->affected_rows > 0];
        $stmt->close();
    } else {
        $response = ['success' => false, 'error' => 'Failed to prepare the SQL statement.'];
    }
} catch (Exception $e) {
    $response = ['success' => false, 'error' => $e->getMessage()];
}

$conn->close();

header('Content-Type: application/json');

echo json_encode($response);
?>