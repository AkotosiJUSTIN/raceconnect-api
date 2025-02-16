<?php

namespace Controller;
use Model\User;
use Middleware\AuthMiddleware;
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception as PHPMailerException;
use Exception;

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../Model/User.php';
require_once __DIR__ . '/../Middleware/AuthMiddleware.php';

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

            $otp = rand(100000, 999999); // Generate a 6-digit OTP
            if (!$this->user->storeOtp($data['email'], $otp)) {
                http_response_code(500);
                echo json_encode(['message' => 'Failed to generate OTP']);
                return;
            }

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
                $mail->Subject = 'Password Reset OTP';
                $mail->Body = "Your OTP for password reset is: <strong>$otp</strong>";

                $mail->send();
                echo json_encode(['message' => 'OTP sent to email']);
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
            if (empty($data['email']) || empty($data['otp']) || empty($data['new_password']) || empty($data['confirm_password'])) {
                http_response_code(400);
                echo json_encode(['message' => 'Email, OTP, new password, and confirmation password are required']);
                return;
            }

            if ($data['new_password'] !== $data['confirm_password']) {
                http_response_code(400);
                echo json_encode(['message' => 'New password and confirmation password do not match']);
                return;
            }

            // Verify OTP
            if (!$this->user->verifyOtp($data['email'], $data['otp'])) {
                http_response_code(400);
                echo json_encode(['message' => 'Invalid or expired OTP']);
                return;
            }

            if ($this->user->resetPassword($data['email'], $data['new_password'])) {
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
}
?>
