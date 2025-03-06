<?php
// Include the database connection script
require_once __DIR__ . '/../../db_connect.php';

// Fetch all the admins from the table
$sql = "SELECT id, password FROM users";
$result = $conn->query($sql);

// Check if there are any admins
if ($result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $user_id = $row['id'];
        $password = $row['password'];

        // Hash the password using password_hash()
        $hashed_password = password_hash($password, PASSWORD_DEFAULT);

        // Update the password in the database with the hashed password
        $update_sql = "UPDATE users SET password = ? WHERE id = ?";
        $stmt = $conn->prepare($update_sql);
        $stmt->bind_param("si", $hashed_password, $user_id);

        // Execute the update
        if ($stmt->execute()) {
            echo "Password for users ID $user_id updated successfully.<br>";
        } else {
            echo "Error updating password for users ID $user_id.<br>";
        }
    }
} else {
    echo "No users found.<br>";
}

// Close the connection
$conn->close();
?>