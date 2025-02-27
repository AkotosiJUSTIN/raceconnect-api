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
        if (!$stmt) {
            throw new Exception("Failed to prepare statement: " . $conn->error);
        }

        $stmt->bind_param("s", $email);
        if (!$stmt->execute()) {
            throw new Exception("Failed to execute statement: " . $stmt->error);
        }

        $result = $stmt->get_result();

        if ($result->num_rows > 0) {
            $otp = rand(100000, 999999);
            
            // Begin transaction
            $conn->begin_transaction();
            
            try {
                // Store OTP in password_resets_admin table
                $insertStmt = $conn->prepare("INSERT INTO password_resets_admin (email, otp, created_at) 
                                      VALUES (?, ?, NOW()) 
                                      ON DUPLICATE KEY UPDATE otp = ?, created_at = NOW()");
                if (!$insertStmt) {
                    throw new Exception("Failed to prepare insert statement: " . $conn->error);
                }

                $insertStmt->bind_param("sss", $email, $otp, $otp);
                
                if (!$insertStmt->execute()) {
                    throw new Exception("Failed to store OTP: " . $insertStmt->error);
                }

                $mail = new PHPMailer(true);
                
                // Server settings
                $mail->isSMTP();
                $mail->Host = 'smtp.gmail.com';
                $mail->SMTPAuth = true;
                $mail->Username = $_ENV['SMTP_USER'] ?? '';
                $mail->Password = $_ENV['SMTP_PASS'] ?? '';
                
                if (empty($mail->Username) || empty($mail->Password)) {
                    throw new Exception("SMTP credentials not found in environment variables");
                }

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
                
                // Commit transaction
                $conn->commit();
                
                $_SESSION['reset_email'] = $email;
                echo json_encode([
                    'success' => true,
                    'message' => 'OTP has been sent to your email.'
                ]);
            } catch (Exception $e) {
                $conn->rollback();
                error_log("Inner try block error: " . $e->getMessage());
                throw $e;
            }
        } else {
            echo json_encode([
                'success' => true,
                'message' => 'If the email exists, you will receive an OTP shortly.'
            ]);
        }
    } catch (Exception $e) {
        error_log("Caught exception: " . $e->getMessage());
        echo json_encode([
            'success' => false,
            'message' => 'An error occurred. Please try again.',
            'debug' => $e->getMessage() // Remove this in production
        ]);
    } finally {
        if (isset($stmt)) $stmt->close();
        if (isset($insertStmt)) $insertStmt->close();
    }
} else {
    echo json_encode([
        'success' => false,
        'message' => 'Invalid request method.'
    ]);
    exit;
}