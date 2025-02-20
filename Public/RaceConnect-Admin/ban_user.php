<?php
// Include the database connection script
require_once __DIR__ . '/../../db_connect.php';

session_start();

if (!isset($_SESSION['email'])) {
    header("Location: index_login.html");
    exit();
}

// Ensure request is POST and required data is received
if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    echo json_encode(["success" => false, "error" => "Invalid request method"]);
    exit();
}

// Validate POST parameters
if (!isset($_POST['username']) || !isset($_POST['ban_duration'])) {
    echo json_encode(["success" => false, "error" => "Missing parameters"]);
    exit();
}

$username = $_POST['username'];
$banDuration = $_POST['ban_duration'];

// Determine suspension end date
if ($banDuration === "permanent") {
    $suspensionEndDate = null; // Permanent ban (no expiry)
} else {
    $banDays = intval($banDuration);
    $suspensionEndDate = date('Y-m-d H:i:s', strtotime("+$banDays days"));
}

// Prepare the SQL query
$query = "UPDATE users SET status = 'Banned', suspension_end_date = ? WHERE username = ?";
$stmt = $conn->prepare($query);

// Check if the statement was prepared correctly
if (!$stmt) {
    echo json_encode(["success" => false, "error" => "Database error: " . $conn->error]);
    exit();
}

// Bind parameters
$stmt->bind_param("ss", $suspensionEndDate, $username);
$executeSuccess = $stmt->execute();

// Check if execution was successful
$response = ["success" => $executeSuccess];
if (!$executeSuccess) {
    $response["error"] = $stmt->error;
}

// Close connections
$stmt->close();
$conn->close();

// Send JSON response
header('Content-Type: application/json');
echo json_encode($response);
?>
