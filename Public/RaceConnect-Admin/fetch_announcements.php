<?php
// Include the database connection script
require_once __DIR__ . '/../../db_connect.php';

// Fetch announcements from the database
$query = "SELECT id, title, content, created_at FROM announcements ORDER BY created_at DESC";
$result = $conn->query($query);

$announcements = [];
if ($result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $announcements[] = $row;
    }
}

header('Content-Type: application/json');
echo json_encode($announcements);

$conn->close();
?>