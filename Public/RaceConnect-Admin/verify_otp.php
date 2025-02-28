<?php
session_start();
require_once __DIR__ . '/../../db_connect.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $otp = $_POST['otp'] ?? '';
    $email = $_SESSION['reset_email'] ?? null;

    if (!$email) {
        echo json_encode(['success' => false, 'message' => 'Session expired']);
        exit;
    }

    try {
        $stmt = $conn->prepare("SELECT * FROM password_resets_admin 
                              WHERE email = ? AND otp = ? 
                              AND created_at >= NOW() - INTERVAL 15 MINUTE");
        $stmt->bind_param("ss", $email, $otp);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows > 0) {
            // Set the session flag to indicate OTP is verified
            $_SESSION['otp_verified'] = true;
            echo json_encode(['success' => true, 'message' => 'OTP verified successfully']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Invalid or expired OTP']);
        }
    } catch (Exception $e) {
        error_log("Error in verify OTP: " . $e->getMessage());
        echo json_encode(['success' => false, 'message' => 'An error occurred']);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid request']);
}
?>