<?php

namespace Controller;

use Model\User;
use Middleware\AuthMiddleware;
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception as PHPMailerException;
use Exception;
use Dotenv\Dotenv;

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../Model/User.php';
require_once __DIR__ . '/../Middleware/AuthMiddleware.php';

$dotenv = Dotenv::createImmutable(__DIR__ . '/../');
$dotenv->load();

class AuthController {
    private $user;
    private $authMiddleware;

    public function __construct($db) {
        $this->user = new User($db);
        $this->authMiddleware = new AuthMiddleware($db);
    }

    private function handleError($statusCode, $message) {
        http_response_code($statusCode);
        echo json_encode(['message' => $message]);
        exit;
    }

    public function login($data) {
        try {
            if (empty($data['username']) || empty($data['password'])) {
                $this->handleError(400, 'Username and password are required');
            }

            $user = $this->user->loginUser($data['username'], $data['password']);
            if (!$user) {
                $this->handleError(401, 'Invalid username or password');
            }

            $token = bin2hex(random_bytes(32));
            if (!$this->authMiddleware->storeToken($user['id'], $token)) {
                $this->handleError(500, 'Failed to store authentication token');
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
            $this->handleError(500, 'An error occurred during login');
        }
    }

    public function forgotPassword($data) {
        try {
            if (empty($data['email'])) {
                $this->handleError(400, 'Email is required');
            }

            $user = $this->user->getUserByEmail($data['email']);
            if (!$user) {
                $this->handleError(404, 'Email not found');
            }

            $otp = rand(100000, 999999);
            if (!$this->user->storeOtp($data['email'], $otp)) {
                $this->handleError(500, 'Failed to generate OTP');
            }

            $mail = new PHPMailer(true);
            try {
                $mail->isSMTP();
                $mail->Host = 'smtp.gmail.com';
                $mail->SMTPAuth = true;
                $mail->Username = $_ENV['SMTP_USER'];
                $mail->Password = $_ENV['SMTP_PASS'];
                $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
                $mail->Port = 587;

                $mail->setFrom('RaceConnect@gmail.com', 'RaceConnect');
                $mail->addAddress($data['email']);
                $mail->isHTML(true);
                $mail->Subject = 'Password Reset OTP';
                $mail->Body = "Your OTP for password reset is: <strong>$otp</strong>";

                $mail->send();
                http_response_code(200);
                echo json_encode(['message' => 'OTP sent to email']);
            } catch (PHPMailerException $e) {
                $this->handleError(500, 'Failed to send email');
            }
        } catch (Exception $e) {
            $this->handleError(500, 'An error occurred while processing your request');
        }
    }

    public function resetPassword($data) {
        try {
            if (empty($data['email']) || empty($data['otp']) || empty($data['new_password']) || empty($data['confirm_password'])) {
                $this->handleError(400, 'All fields are required');
            }

            if ($data['new_password'] !== $data['confirm_password']) {
                $this->handleError(400, 'Passwords do not match');
            }

            if (!$this->user->verifyOtp($data['email'], $data['otp'])) {
                $this->handleError(400, 'Invalid or expired OTP');
            }

            if ($this->user->resetPassword($data['email'], $data['new_password'])) {
                http_response_code(200);
                echo json_encode(['message' => 'Password reset successful']);
            } else {
                $this->handleError(500, 'Failed to reset password');
            }
        } catch (Exception $e) {
            $this->handleError(500, 'An error occurred while resetting password');
        }
    }
}
?>
