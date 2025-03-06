<?php
require_once __DIR__ . '/../../vendor/autoload.php';
require_once __DIR__ . '/../../db_connect.php';

header('Content-Type: application/json');
session_start();

// Password validation function
$new_password = $_POST['new_password'];
function validatePassword($password) {
    $errors = [];

    if (strlen($password) < 8) {
        $errors[] = "Password must be at least 8 characters long.";
    }
    if (!preg_match("/[A-Z]/", $password)) {
        $errors[] = "Password must contain at least one uppercase letter.";
    }
    if (!preg_match("/[a-z]/", $password)) {
        $errors[] = "Password must contain at least one lowercase letter.";
    }
    if (!preg_match("/[0-9]/", $password)) {
        $errors[] = "Password must contain at least one number.";
    }
    if (!preg_match("/[^A-Za-z0-9]/", $password)) {
        $errors[] = "Password must contain at least one special character.";
    }

    return $errors;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Check if OTP was previously verified
    if (!isset($_SESSION['otp_verified']) || $_SESSION['otp_verified'] !== true) {
        echo json_encode(['success' => false, 'message' => 'OTP verification required']);
        exit;
    }

    $email = $_SESSION['reset_email'] ?? null;
    $new_password = $_POST['new_password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';

    if (!$email) {
        echo json_encode(['success' => false, 'message' => 'Email not found']);
        exit;
    }

    // Validate the new password
    $passwordErrors = validatePassword($new_password);
    $error_message = '';

    if (!empty($passwordErrors)) {
        $error_message = "Password does not meet the requirements:<ul>";
        foreach ($passwordErrors as $error) {
            $error_message .= "<li>$error</li>";
        }
        $error_message .= "</ul>";
        echo json_encode(['success' => false, 'message' => $error_message]);
        exit;
    }

    // Check if passwords match
    if ($new_password !== $confirm_password) {
        echo json_encode(['success' => false, 'message' => 'Passwords do not match']);
        exit;
    }

    try {
        // Retrieve the current password hash from the database
        $select_stmt = $conn->prepare("SELECT password FROM admins WHERE email = ?");
        $select_stmt->bind_param("s", $email);
        $select_stmt->execute();
        $result = $select_stmt->get_result();

        if ($result->num_rows === 0) {
            throw new Exception("Admin not found");
        }

        $row = $result->fetch_assoc();
        $current_hash = $row['password'];

        // Check if the new password matches the current password
        if (password_verify($new_password, $current_hash)) {
            echo json_encode(['success' => false, 'message' => 'You cannot reuse your previous password']);
            exit;
        }

        // If all checks pass, proceed with the update
        $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
        $update_stmt = $conn->prepare("UPDATE admins SET password = ? WHERE email = ?");
        $update_stmt->bind_param("ss", $hashed_password, $email);

        if ($update_stmt->execute()) {
            // Delete the used OTP record
            $delete_stmt = $conn->prepare("DELETE FROM password_resets_admin WHERE email = ?");
            $delete_stmt->bind_param("s", $email);
            $delete_stmt->execute();

            // Clean up session variables
            unset($_SESSION['otp_verified']);
            unset($_SESSION['reset_email']);
            echo json_encode([
                'success' => true,
                'message' => 'Password has been reset successfully.'
            ]);
        } else {
            throw new Exception("Failed to update password");
        }
    } catch (Exception $e) {
        error_log("Error in reset password: " . $e->getMessage());
        echo json_encode([
            'success' => false,
            'message' => 'An error occurred. Please try again.'
        ]);
    }
} else {
    echo json_encode([
        'success' => false,
        'message' => 'Invalid request method.'
    ]);
}
?>