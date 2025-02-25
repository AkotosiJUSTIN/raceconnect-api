<?php
require_once __DIR__ . '/../../vendor/autoload.php';
require_once __DIR__ . '/../../db_connect.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception as PHPMailerException;
use Dotenv\Dotenv;

// Load environment variables
$dotenv = Dotenv::createImmutable(__DIR__ . '/../../');
$dotenv->load();

session_start();

// Set JSON header
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = $_POST['email'] ?? '';

    try {
        // Check if email exists in admins table
        $stmt = $conn->prepare("SELECT email FROM admins WHERE email = ?");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows > 0) {
            $otp = rand(100000, 999999);
            
            // Store OTP in password_resets table
            $stmt = $conn->prepare("INSERT INTO password_resets (email, otp, created_at) 
                                  VALUES (?, ?, NOW()) 
                                  ON DUPLICATE KEY UPDATE otp = ?, created_at = NOW()");
            $stmt->bind_param("sss", $email, $otp, $otp);
            
            if ($stmt->execute()) {
                try {
                    $mail = new PHPMailer(true);
                    
                    // Server settings
                    $mail->isSMTP();
                    $mail->Host = 'smtp.gmail.com';
                    $mail->SMTPAuth = true;
                    $mail->Username = $_ENV['SMTP_USER'];
                    $mail->Password = $_ENV['SMTP_PASS'];
                    $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
                    $mail->Port = 587;

                    // Recipients
                    $mail->setFrom('RaceConnect@gmail.com', 'RaceConnect Admin');
                    $mail->addAddress($email);

                    // Content
                    $mail->isHTML(true);
                    $mail->Subject = 'Admin Password Reset OTP';
                    $mail->Body = "
                        <h2>Password Reset Request</h2>
                        <p>Your OTP for password reset is: <strong>{$otp}</strong></p>
                        <p>This OTP will expire in 15 minutes.</p>
                        <p>If you didn't request this password reset, please ignore this email.</p>
                    ";

                    $mail->send();
                    
                    // Store email in session
                    $_SESSION['reset_email'] = $email;

                    echo json_encode([
                        'success' => true,
                        'message' => 'OTP has been sent to your email.'
                    ]);
                } catch (PHPMailerException $e) {
                    error_log("Mailer Error: " . $e->getMessage());
                    echo json_encode([
                        'success' => false,
                        'message' => 'Failed to send OTP email. Please try again.'
                    ]);
                }
                exit;
            }
        } else {
            // For security, don't reveal if email exists or not
            echo json_encode([
                'success' => true,
                'message' => 'If the email exists, you will receive an OTP shortly.'
            ]);
            exit;
        }
    } catch (Exception $e) {
        error_log("Error in forgot password: " . $e->getMessage());
        echo json_encode([
            'success' => false,
            'message' => 'An error occurred. Please try again.'
        ]);
        exit;
    }
} else {
    echo json_encode([
        'success' => false,
        'message' => 'Invalid request method.'
    ]);
    exit;
}