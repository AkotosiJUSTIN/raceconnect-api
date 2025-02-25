<!-- filepath: /c:/xampp/htdocs/raceconnect-api/Public/RaceConnect-Admin/reset_password.php -->
<?php
require_once __DIR__ . '/../../vendor/autoload.php';
require_once __DIR__ . '/../../db_connect.php';

session_start();

$error_message = '';
$success_message = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $email = $_POST['email'];
    $otp = $_POST['otp'];
    $new_password = $_POST['new_password'];
    $confirm_password = $_POST['confirm_password'];

    if ($new_password !== $confirm_password) {
        $error_message = 'Passwords do not match.';
    } else {
        $authController = new AuthController();
        $data = [
            'email' => $email,
            'otp' => $otp,
            'new_password' => $new_password,
            'confirm_password' => $confirm_password
        ];
        $authController->resetPassword($data);
        $success_message = 'Password has been reset successfully.';
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reset Password</title>
    <link rel="stylesheet" href="assets/css/admin-login.css">
</head>
<body>
    <div class="login-container">
        <div class="login-form">
            <h2 class="form-title">Reset Password</h2>
            <?php if ($error_message): ?>
                <div class="error-message"><?php echo $error_message; ?></div>
            <?php endif; ?>
            <?php if ($success_message): ?>
                <div class="success-message"><?php echo $success_message; ?></div>
            <?php endif; ?>
            <form action="reset_password.php" method="POST">
                <div class="form-group">
                    <label for="email" class="input-label">Email</label>
                    <div class="input-wrapper">
                        <box-icon type='solid' name='envelope' color="#dc2626" class="input-icon"></box-icon>
                        <input type="email" id="email" name="email" required class="text-input" autofocus>
                    </div>
                </div>
                <div class="form-group">
                    <label for="otp" class="input-label">OTP</label>
                    <div class="input-wrapper">
                        <box-icon type='solid' name='key' color="#dc2626" class="input-icon"></box-icon>
                        <input type="text" id="otp" name="otp" required class="text-input">
                    </div>
                </div>
                <div class="form-group">
                    <label for="new_password" class="input-label">New Password</label>
                    <div class="input-wrapper">
                        <box-icon type='solid' name='lock' color="#dc2626" class="input-icon"></box-icon>
                        <input type="password" id="new_password" name="new_password" required class="text-input">
                    </div>
                </div>
                <div class="form-group">
                    <label for="confirm_password" class="input-label">Confirm Password</label>
                    <div class="input-wrapper">
                        <box-icon type='solid' name='lock' color="#dc2626" class="input-icon"></box-icon>
                        <input type="password" id="confirm_password" name="confirm_password" required class="text-input">
                    </div>
                </div>
                <button type="submit" class="submit-button">Reset Password</button>
            </form>
        </div>
    </div>
</body>
</html>