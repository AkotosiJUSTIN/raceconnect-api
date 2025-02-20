<?php
require_once __DIR__ . '/../../db_connect.php';

$query = "SELECT username, status, created_at, suspension_end_date FROM users";
$result = $conn->query($query);

$users = [];
while ($row = $result->fetch_assoc()) {
    // Calculate remaining suspension days
    if ($row['suspension_end_date'] && strtotime($row['suspension_end_date']) > time()) {
        $remainingDays = (strtotime($row['suspension_end_date']) - time()) / 86400;
        $row['suspension_days'] = ceil($remainingDays); // Round up to whole days
    } else {
        $row['suspension_days'] = null;
    }
    $users[] = $row;
}

echo json_encode($users);

$conn->close();
?>
