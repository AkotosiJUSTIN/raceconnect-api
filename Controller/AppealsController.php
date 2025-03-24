<?php

namespace Controller;

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../Config/database.php';
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

class AppealsController {
    private $conn;
    private $table = 'appeals';

    public function __construct($db) {
        $this->conn = $db;
    }

    public function submitAppeal($data) {
        try {
            // Validate required fields
            if (empty($data['username']) || empty($data['email']) || empty($data['description'])) {
                return [
                    'success' => false,
                    'message' => 'Missing required fields'
                ];
            }
    
            // Check if user is actually banned
            $userQuery = "SELECT id, status FROM Users WHERE email = :email AND status = 'Banned'";
            $userStmt = $this->conn->prepare($userQuery);
            $userStmt->execute([':email' => $data['email']]);
            $user = $userStmt->fetch(\PDO::FETCH_ASSOC);
    
            if (!$user) {
                return [
                    'success' => false,
                    'message' => 'Only banned accounts can submit appeals'
                ];
            }
    
            $this->conn->beginTransaction();
    
            try {
                // 1. Insert the appeal
                $query = "INSERT INTO appeals 
                        (user_id, username, email, suspension_date, 
                         suspension_reason, description)
                        VALUES (:user_id, :username, :email, :suspensionDate, 
                         :suspensionReason, :description)";
    
                $stmt = $this->conn->prepare($query);
                
                $params = [
                    ':user_id' => $user['id'],
                    ':username' => htmlspecialchars(strip_tags($data['username'])),
                    ':email' => htmlspecialchars(strip_tags($data['email'])),
                    ':suspensionDate' => !empty($data['suspensionDate']) ? $data['suspensionDate'] : null,
                    ':suspensionReason' => !empty($data['suspensionReason']) ? 
                                         htmlspecialchars(strip_tags($data['suspensionReason'])) : null,
                    ':description' => htmlspecialchars(strip_tags($data['description']))
                ];
    
                if (!$stmt->execute($params)) {
                    throw new \Exception("Failed to create appeal");
                }
    
                $appealId = $this->conn->lastInsertId();
    
                // 2. Create admin notification
                $notifQuery = "INSERT INTO admin_notifications 
                             (admin_id, reporter_id, type, content, reporter_username, appeal_id, 
                              severity, status) 
                             SELECT 
                                 a.id,
                                 :reporter_id,
                                 'ban_appeal',
                                 :content,
                                 :username,
                                 :appeal_id,
                                 'high',
                                 'pending'
                             FROM admins a 
                             WHERE a.active = 1 
                             AND a.role = 'community_manager' 
                             LIMIT 1";
    
                $notifStmt = $this->conn->prepare($notifQuery);
                $notifParams = [
                    ':reporter_id' => $user['id'],
                    ':content' => "New ban appeal from {$params[':username']}",
                    ':username' => $params[':username'],
                    ':appeal_id' => $appealId
                ];
    
                if (!$notifStmt->execute($notifParams)) {
                    throw new \Exception("Failed to create admin notification");
                }
    
                // 3. Send confirmation emails
                $this->sendAppealConfirmation(
                    $params[':email'],
                    $params[':username'],
                    $appealId
                );
    
                $this->conn->commit();
                
                return [
                    'success' => true,
                    'message' => 'Appeal submitted successfully',
                    'appeal_id' => $appealId
                ];
    
            } catch (\Exception $e) {
                $this->conn->rollBack();
                throw $e;
            }
    
        } catch (\Exception $e) {
            error_log("Appeal submission error: " . $e->getMessage());
            return [
                'success' => false,
                'message' => 'An error occurred while submitting the appeal'
            ];
        }
    }

    public function updateAppealStatus($appealId, $status) {
        try {
            $this->conn->beginTransaction();
    
            try {
                // 1. Update appeal status
                $appealQuery = "UPDATE appeals 
                              SET status = :status 
                              WHERE id = :appeal_id";
                
                $appealStmt = $this->conn->prepare($appealQuery);
                $appealStmt->execute([
                    ':status' => $status === 'archived' ? 'REJECTED' : strtoupper($status),
                    ':appeal_id' => $appealId
                ]);
    
                // 2. Update related notification
                $notifQuery = "UPDATE admin_notifications 
                             SET status = :status, 
                                 action_taken = CASE 
                                     WHEN :status = 'archived' THEN 'Denied'
                                     ELSE NULL 
                                 END,
                                 archived_at = CASE 
                                     WHEN :status = 'archived' THEN CURRENT_TIMESTAMP
                                     ELSE NULL 
                                 END
                             WHERE appeal_id = :appeal_id";
    
                $notifStmt = $this->conn->prepare($notifQuery);
                $notifStmt->execute([
                    ':status' => $status,
                    ':appeal_id' => $appealId
                ]);
    
                $this->conn->commit();
                
                return [
                    'success' => true,
                    'message' => 'Appeal status updated successfully'
                ];
    
            } catch (\Exception $e) {
                $this->conn->rollBack();
                throw $e;
            }
    
        } catch (\Exception $e) {
            error_log("Appeal status update error: " . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Failed to update appeal status'
            ];
        }
    }

    private function sendAppealConfirmation($email, $username, $appealId) {
        $mail = new PHPMailer(true);

        try {
            // Server settings
            $mail->SMTPDebug = 2; // 2 for detailed debug output
            $mail->Debugoutput = 'error_log'; // Log to error_log

            $mail->isSMTP();
            $mail->Host = 'smtp.gmail.com';
            $mail->SMTPAuth = true;
            $mail->Username = $_ENV['SMTP_USER'];
            $mail->Password = $_ENV['SMTP_PASS'];
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
            $mail->Port = 587;

            //Email for User Confirmation
            $mail->setFrom('RaceConnect@gmail.com', 'RaceConnect');
            $mail->addAddress($email, $username);
            $mail->isHTML(true);
            $mail->Subject = 'Appeal Submission Confirmation';
            $mail->Body = $this->getAppealConfirmationTemplate($username, $appealId);
            $mail->send();

            //Email for Admin Notification
            $mail->clearAddresses();
            $mail->addAddress('raceconnect.team@gmail.com', 'RaceConnect Team');
            $mail->Subject = 'New Appeal Submission';
            $mail->Body = $this->getAdminNotificationTemplate($username, $email, $appealId);
            $mail->send();

            $this->createAdminNotification($username, $email, $appealId);
            return true;
        } catch (Exception $e) {
            error_log("Mail Error: " . $mail->ErrorInfo);
            return false;
        }
    }

    private function getAdminNotificationTemplate($username, $appealId, $userEmail) {
        return "
            <html>
            <body style='font-family: Inter, sans-serif; line-height: 1.6; color: #333;'>
                <div style='max-width: 600px; margin: 0 auto; padding: 20px;'>
                    <h2 style='color: #B91C1C;'>New Appeal Submission</h2>
                    <div style='margin: 20px 0; padding: 15px; background-color: #f5f5f5; border-radius: 5px;'>
                        <p><strong>Appeal ID:</strong> $appealId</p>
                        <p><strong>Username:</strong> $username</p>
                        <p><strong>User Email:</strong> $userEmail</p>
                    </div>
                    <p>A new appeal has been submitted. Please review this in the admin dashboard.</p>
                    <p>Best regards,<br>RaceConnect System</p>
                </div>
            </body>
            </html>
        ";
    }

    private function createAdminNotification($username, $appealId, $concernType) {
        try {
            // Get active admin with community_manager role
            $adminQuery = "SELECT id FROM admins WHERE active = 1 AND role = 'community_manager' LIMIT 1";
            $adminStmt = $this->conn->prepare($adminQuery);
            $adminStmt->execute();
            $admin = $adminStmt->fetch(\PDO::FETCH_ASSOC);
            
            if (!$admin) {
                throw new \Exception("No active community manager found");
            }
    
            // Get user ID if exists (but make it optional)
            $userQuery = "SELECT id FROM users WHERE username = :username LIMIT 1";
            $userStmt = $this->conn->prepare($userQuery);
            $userStmt->execute([':username' => $username]);
            $user = $userStmt->fetch(\PDO::FETCH_ASSOC);
    
            // Insert notification
            $query = "INSERT INTO admin_notifications 
                     (admin_id, type, content, reporter_username, appeal_id, reporter_id, 
                      concern_type, severity, status) 
                     VALUES 
                     (:admin_id, 'appeal_submission', :content, :username, :appeal_id, 
                      :reporter_id, :concern_type, 'high', 'pending')";
            
            $stmt = $this->conn->prepare($query);
            $content = "New appeal submission from {$username} - {$concernType}";
            
            $params = [
                ':admin_id' => $admin['id'],
                ':content' => $content,
                ':username' => $username,
                ':appeal_id' => $appealId,
                ':reporter_id' => $user ? $user['id'] : null,
                ':concern_type' => $concernType
            ];
            
            if (!$stmt->execute($params)) {
                throw new \Exception("Failed to create admin notification");
            }
    
            return true;
    
        } catch (\Exception $e) {
            error_log("Failed to create admin notification: " . $e->getMessage());
            throw $e;
        }
    }

    private function getAppealConfirmationTemplate($username, $appealId) {
        return "
            <html>
            <body style='font-family: Inter, sans-serif; line-height: 1.6; color: #333;'>
                <div style='max-width: 600px; margin: 0 auto; padding: 20px;'>
                    <h2 style='color: #B191C;'>Appeal Submission Confirmation</h2>
                    <p>Dear $username,</p>
                    <p>We have received your appeal (ID: $appealId). Our team will review your case carefully and respond within 24-48 hours.</p>
                    <p>Please save your appeal ID for future reference.</p>
                    <div style='margin: 20px 0; padding: 15px; background-color: #f5f5f5; border-radius: 5px;'>
                        <strong>Appeal ID:</strong> $appealId
                    </div>
                    <p>If you have any additional information to provide, please reply to this email.</p>
                    <p>Best regards,<br>RaceConnect Team</p>
                </div>
            </body>
            </html>
        ";
    }
}