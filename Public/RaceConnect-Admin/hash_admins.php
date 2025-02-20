<?php
// Include the database connection script
require_once __DIR__ . '/../../db_connect.php';

// Fetch all the admins from the table
$sql = "SELECT id, password FROM admins";
$result = $conn->query($sql);

// Check if there are any admins
if ($result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $admin_id = $row['id'];
        $password = $row['password'];

        // Hash the password using password_hash()
        $hashed_password = password_hash($password, PASSWORD_DEFAULT);

        // Update the password in the database with the hashed password
        $update_sql = "UPDATE admins SET password = ? WHERE id = ?";
        $stmt = $conn->prepare($update_sql);
        $stmt->bind_param("si", $hashed_password, $admin_id);

        // Execute the update
        if ($stmt->execute()) {
            echo "Password for admin ID $admin_id updated successfully.<br>";
        } else {
            echo "Error updating password for admin ID $admin_id.<br>";
        }
    }
} else {
    echo "No admins found.<br>";
}

// Close the connection
$conn->close();
?>