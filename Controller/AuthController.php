<?php

namespace Controller;
use Model\User;
use Middleware\AuthMiddleware;
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception as PHPMailerException;
use Exception;

require_once 'C:/xampp/htdocs/raceconnectapi/vendor/autoload.php';
require_once 'C:/xampp/htdocs/raceconnectapi/Model/User.php';
require_once 'C:/xampp/htdocs/raceconnectapi/Middleware/AuthMiddleware.php';

class AuthController {
    private $user;
    private $authMiddleware;

    public function __construct($db) {
        $this->user = new User($db);
        $this->authMiddleware = new AuthMiddleware($db);
    }

    public function login($data) {
        try {
            if (empty($data['username']) || empty($data['password'])) {
                http_response_code(400);
                echo json_encode(['message' => 'Username and password are required']);
                return;
            }

            $user = $this->user->loginUser($data['username'], $data['password']);

            if (!$user) {
                http_response_code(401);
                echo json_encode(['message' => 'Invalid username or password']);
                return;
            }

            // Generate a secure token
            $token = bin2hex(random_bytes(32));

            // Save the token in the database
            if (!$this->authMiddleware->storeToken($user['id'], $token)) {
                http_response_code(500);
                echo json_encode(['message' => 'Failed to store token']);
                return;
            }

            http_response_code(200);
            echo json_encode([
                'message' => 'Login successful',
                'user' => [
                    'id' => $user['id'],
                    'username' => $user['username'],
                    'email' => $user['email']
                ],
                'token' => $token
            ]);

        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode(['message' => 'An error occurred during login', 'error' => $e->getMessage()]);
        }
    }

    public function logout() {
        try {
            $headers = apache_request_headers();
            $authHeader = $headers['Authorization'] ?? '';

            if (empty($authHeader)) {
                http_response_code(400);
                echo json_encode(['message' => 'Authorization header is missing']);
                return;
            }

            if (!preg_match('/^Bearer\s(\S+)$/', $authHeader, $matches)) {
                http_response_code(400);
                echo json_encode(['message' => 'Invalid token format']);
                return;
            }

            $token = $matches[1];

            // Validate token
            if (!$this->authMiddleware->validateToken($token)) {
                http_response_code(401);
                echo json_encode(['message' => 'Invalid or expired token']);
                return;
            }

            // Revoke token
            if (!$this->authMiddleware->revokeToken($token)) {
                http_response_code(500);
                echo json_encode(['message' => 'Failed to revoke token']);
                return;
            }

            http_response_code(200);
            echo json_encode(['message' => 'Logout successful']);

        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode(['message' => 'An error occurred during logout', 'error' => $e->getMessage()]);
        }
    }

    public function forgotPassword($data) {
        try {
            if (empty($data['email'])) {
                http_response_code(400);
                echo json_encode(['message' => 'Email is required']);
                return;
            }

            $token = $this->user->generatePasswordResetToken($data['email']);
            if (!$token) {
                http_response_code(500);
                echo json_encode(['message' => 'Failed to generate password reset token']);
                return;
            }

            $resetLink = "http://localhost:8000/reset-password.html?token=$token";

            // Send email with PHPMailer
            $mail = new PHPMailer(true);
            try {
                //Server settings
                $mail->isSMTP();
                $mail->Host = 'smtp.gmail.com'; // Set the SMTP server to send through
                $mail->SMTPAuth = true;
                $mail->Username = 'cuagdannitsuj@gmail.com'; // SMTP username
                $mail->Password = 'uftt nusi rxuk frky'; // SMTP password
                $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
                $mail->Port = 587;

                //Recipients
                $mail->setFrom('RaceConnect@gmail.com', 'RaceConnect');
                $mail->addAddress($data['email']);

                // Content
                $mail->isHTML(true);
                $mail->Subject = 'Password Reset Request';
                $mail->Body = "Click the button below to reset your password:<br><br>
                    <a href='$resetLink' style='display: inline-block; padding: 10px 20px; background-color: #007bff; color: white; text-decoration: none; border-radius: 5px;'>Reset Password</a>";
                
                $mail->send();
                echo json_encode(['message' => 'Password reset email sent']);
            } catch (PHPMailerException $e) {
                http_response_code(500);
                echo json_encode(['message' => 'Failed to send email', 'error' => $e->getMessage()]);
            }
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode(['message' => 'An error occurred', 'error' => $e->getMessage()]);
        }
    }

    public function resetPassword($data) {
        try {
            if (empty($data['token']) || empty($data['new_password'])) {
                http_response_code(400);
                echo json_encode(['message' => 'Token and new password are required']);
                return;
            }

            // Debugging information
            error_log("Token: " . $data['token']);
            error_log("New Password: " . $data['new_password']);

            if ($this->user->resetPassword($data['token'], $data['new_password'])) {
                echo json_encode(['message' => 'Password reset successful']);
            } else {
                http_response_code(500);
                echo json_encode(['message' => 'Failed to reset password']);
            }
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode(['message' => 'An error occurred', 'error' => $e->getMessage()]);
        }
    }

    public function changePassword($data) {
        try {
            if (empty($data['current_password']) || empty($data['new_password'])) {
                http_response_code(400);
                echo json_encode(['message' => 'Current password and new password are required']);
                return;
            }

            $headers = apache_request_headers();
            $authHeader = $headers['Authorization'] ?? '';

            if (empty($authHeader)) {
                http_response_code(400);
                echo json_encode(['message' => 'Authorization header is missing']);
                return;
            }

            if (!preg_match('/^Bearer\s(\S+)$/', $authHeader, $matches)) {
                http_response_code(400);
                echo json_encode(['message' => 'Invalid token format']);
                return;
            }

            $token = $matches[1];

            // Validate token
            error_log("Validating token: " . $token);
            $tokenData = $this->authMiddleware->validateToken($token);
            if (!$tokenData) {
                http_response_code(401);
                echo json_encode(['message' => 'Invalid or expired token']);
                return;
            }

            $userId = $tokenData['user_id'];
            error_log("User ID from token: " . $userId);
            $user = $this->user->getUserById($userId);

            if (!$user) {
                error_log("User not found");
                http_response_code(401);
                echo json_encode(['message' => 'User not found']);
                return;
            }

            if (!password_verify($data['current_password'], $user['password'])) {
                error_log("Current password is incorrect");
                http_response_code(401);
                echo json_encode(['message' => 'Current password is incorrect']);
                return;
            }

            if ($this->user->updatePassword($userId, $data['new_password'])) {
                echo json_encode(['message' => 'Password changed successfully']);
            } else {
                error_log("Failed to change password");
                http_response_code(500);
                echo json_encode(['message' => 'Failed to change password']);
            }
        } catch (Exception $e) {
            error_log("Exception: " . $e->getMessage());
            http_response_code(500);
            echo json_encode(['message' => 'An error occurred', 'error' => $e->getMessage()]);
        }
    }
}
?>
