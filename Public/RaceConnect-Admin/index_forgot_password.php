<?php
require_once __DIR__ . '/../../vendor/autoload.php';
require_once __DIR__ . '/../../db_connect.php';

use Controller\AuthController;

session_start();

$error_message = '';
$success_message = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $email = $_POST['email'];

    $stmt = $conn->prepare("SELECT email FROM admins WHERE email = ?");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        $otp = rand(100000, 999999);
        $stmt = $conn->prepare("INSERT INTO password_resets (email, otp, created_at) VALUES (?, ?, NOW()) ON DUPLICATE KEY UPDATE otp = ?, created_at = NOW()");
        $stmt->bind_param("sss", $email, $otp, $otp);
        $stmt->execute();

        // Send OTP via AWS SES
        $authController = new AuthController($conn);
        $authController->sendOtpEmail($email, $otp);
        $success_message = 'OTP has been sent to your email.';
    } else {
        $error_message = 'Email not found.';
    }

    $stmt->close();
    $conn->close();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Forgot Password</title>
    <link rel="stylesheet" href="assets/css/admin-login.css">
</head>
<body>
    <div class="login-container">
        <div class="login-form">
            <h2 class="form-title">Forgot Password</h2>
            <?php if ($error_message): ?>
                <div class="error-message"><?php echo $error_message; ?></div>
            <?php endif; ?>
            <?php if ($success_message): ?>
                <div class="success-message"><?php echo $success_message; ?></div>
            <?php endif; ?>
            <form action="index_forgot_password.php" method="POST">
                <div class="form-group">
                    <label for="email" class="input-label">Email</label>
                    <div class="input-wrapper">
                        <box-icon type='solid' name='envelope' color="#dc2626" class="input-icon"></box-icon>
                        <input type="email" id="email" name="email" required class="text-input" autofocus>
                    </div>
                </div>
                <button type="submit" class="submit-button">Send OTP</button>
            </form>
        </div>
    </div>
</body>
</html>