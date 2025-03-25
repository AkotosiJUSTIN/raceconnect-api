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
            if (empty($data['username']) || empty($data['email']) || 
                empty($data['concernType']) || empty($data['description'])) {
                return [
                    'success' => false,
                    'message' => 'Missing required fields'
                ];
            }
    
            // Validate that concernType matches one of the allowed values
            $validConcernTypes = ['ACCOUNT_PENALTY', 'POST_PENALTY', 'ITEM_POST_PENALTY'];
            if (!in_array($data['concernType'], $validConcernTypes)) {
                return [
                    'success' => false,
                    'message' => 'Invalid concern type'
                ];
            }
    
            // Check if user exists and get their info
            $userStmt = $this->conn->prepare("SELECT id, status FROM Users WHERE email = :email");
            $userStmt->execute([':email' => $data['email']]);
            $user = $userStmt->fetch(\PDO::FETCH_ASSOC);

            if (!$user) {
                return [
                    'success' => false,
                    'message' => 'User not found'
                ];
            }

            // For account appeals, user must be banned
            if ($data['concernType'] === 'ACCOUNT_PENALTY' && $user['status'] !== 'Banned') {
                return [
                    'success' => false,
                    'message' => 'Only banned accounts can submit account appeals'
                ];
            }
    
            $this->conn->beginTransaction();
    
            try {
                // Insert the appeal
                $query = "INSERT INTO appeals 
                        (user_id, username, email, concern_type, post_id, item_id, description, status)
                        VALUES (:user_id, :username, :email, :concern_type, :post_id, :item_id, 
                                :description, 'PENDING')";
    
                $stmt = $this->conn->prepare($query);
                
                // Convert post_id and item_id based on concernType
                $postId = ($data['concernType'] === 'POST_PENALTY' && isset($data['postId'])) ? 
                    intval($data['postId']) : null;
                $itemId = ($data['concernType'] === 'ITEM_POST_PENALTY' && isset($data['itemId'])) ? 
                    intval($data['itemId']) : null;
                
                $stmt->execute([
                    ':user_id' => $user['id'],
                    ':username' => $data['username'],
                    ':email' => $data['email'],
                    ':concern_type' => $data['concernType'],
                    ':post_id' => $postId,
                    ':item_id' => $itemId,
                    ':description' => $data['description']
                ]);
    
                $appealId = $this->conn->lastInsertId();
    
                // Create admin notification
                $notifQuery = "INSERT INTO admin_notifications 
                             (admin_id, reporter_id, type, content, reporter_username, 
                              appeal_id, severity, status) 
                             SELECT 
                                 a.id,
                                 :reporter_id,
                                 'appeal_submission',
                                 :content,
                                 :username,
                                 :appeal_id,
                                 'high',
                                 'pending'
                             FROM admins a 
                             WHERE a.active = 1 
                             AND a.role = 'community_manager' 
                             ORDER BY a.id ASC
                             LIMIT 1";
    
                $content = "New appeal from {$data['username']}";
                $notifStmt = $this->conn->prepare($notifQuery);
                $notifStmt->execute([
                    ':reporter_id' => $user['id'],
                    ':content' => $content,
                    ':username' => $data['username'],
                    ':appeal_id' => $appealId
                ]);
    
                // Send confirmation emails
                $this->sendAppealConfirmation(
                    $data['email'],
                    $data['username'],
                    $appealId
                );
    
                $this->conn->commit();
                
                return [
                    'success' => true,
                    'message' => 'Appeal submitted successfully',
                    'data' => [
                        'id' => $appealId,
                        'username' => $data['username'],
                        'email' => $data['email'],
                        'concernType' => $data['concernType'],
                        'postId' => $postId,
                        'itemId' => $itemId,
                        'description' => $data['description'],
                        'status' => 'PENDING',
                        'createdAt' => date('Y-m-d H:i:s'),
                        'updatedAt' => date('Y-m-d H:i:s')
                    ]
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

            // Validate status matches AppealStatus enum
            $validStatuses = ['PENDING', 'APPROVED', 'REJECTED'];
            if (!in_array(strtoupper($newStatus), $validStatuses)) {
                return [
                    'success' => false,
                    'message' => 'Invalid appeal status'
                ];
            }
    
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
            $mail->Body = $this->getAdminNotificationTemplate($username, $appealId, $email);
            $mail->send();

            // removed because it creates redundant notifications
            //$this->createAdminNotification($username, $email, $appealId);
            return true;
        } catch (Exception $e) {
            error_log("Mail Error: " . $mail->ErrorInfo);
            return false;
        }
    }

    private function getAdminNotificationTemplate($username, $appealId, $userEmail) {
        // First, fetch all appeal data from database
        $query = "SELECT a.*, u.status as user_status 
                 FROM appeals a 
                 LEFT JOIN Users u ON a.user_id = u.id 
                 WHERE a.id = :appeal_id";
                 
        $stmt = $this->conn->prepare($query);
        $stmt->execute([':appeal_id' => $appealId]);
        $appealData = $stmt->fetch(\PDO::FETCH_ASSOC);
    
        // Format dates for better readability
        $createdAt = new \DateTime($appealData['created_at']);
        $updatedAt = new \DateTime($appealData['updated_at']);
        $suspensionDate = $appealData['suspension_date'] ? new \DateTime($appealData['suspension_date']) : null;
    
        return "
            <html>
            <body style='font-family: Inter, sans-serif; line-height: 1.6; color: #333;'>
                <div style='max-width: 600px; margin: 0 auto; padding: 20px;'>
                    <h2 style='color: #B91C1C;'>New Appeal Submission</h2>
                    
                    <div style='margin: 20px 0; padding: 15px; background-color: #f5f5f5; border-radius: 5px;'>
                        <h3 style='color: #1F2937; margin-bottom: 15px;'>Appeal Information</h3>
                        <table style='width: 100%; border-collapse: collapse;'>
                            <tr>
                                <td style='padding: 8px; border-bottom: 1px solid #ddd;'><strong>Appeal ID:</strong></td>
                                <td style='padding: 8px; border-bottom: 1px solid #ddd;'>{$appealData['id']}</td>
                            </tr>
                            <tr>
                                <td style='padding: 8px; border-bottom: 1px solid #ddd;'><strong>User ID:</strong></td>
                                <td style='padding: 8px; border-bottom: 1px solid #ddd;'>{$appealData['user_id']}</td>
                            </tr>
                            <tr>
                                <td style='padding: 8px; border-bottom: 1px solid #ddd;'><strong>Username:</strong></td>
                                <td style='padding: 8px; border-bottom: 1px solid #ddd;'>{$appealData['username']}</td>
                            </tr>
                            <tr>
                                <td style='padding: 8px; border-bottom: 1px solid #ddd;'><strong>Email:</strong></td>
                                <td style='padding: 8px; border-bottom: 1px solid #ddd;'>{$appealData['email']}</td>
                            </tr>
                            <tr>
                                <td style='padding: 8px; border-bottom: 1px solid #ddd;'><strong>User Status:</strong></td>
                                <td style='padding: 8px; border-bottom: 1px solid #ddd;'>{$appealData['user_status']}</td>
                            </tr>
                        </table>
                    </div>
    
                    <div style='margin: 20px 0; padding: 15px; background-color: #f5f5f5; border-radius: 5px;'>
                        <h3 style='color: #1F2937; margin-bottom: 15px;'>Suspension Details</h3>
                        <table style='width: 100%; border-collapse: collapse;'>
                            <tr>
                                <td style='padding: 8px; border-bottom: 1px solid #ddd;'><strong>Suspension Date:</strong></td>
                                <td style='padding: 8px; border-bottom: 1px solid #ddd;'>" . 
                                ($suspensionDate ? $suspensionDate->format('Y-m-d H:i:s') : 'Not specified') . 
                                "</td>
                            </tr>
                            <tr>
                                <td style='padding: 8px; border-bottom: 1px solid #ddd;'><strong>Suspension Reason:</strong></td>
                                <td style='padding: 8px; border-bottom: 1px solid #ddd;'>" . 
                                ($appealData['suspension_reason'] ?: 'Not specified') . 
                                "</td>
                            </tr>
                        </table>
                    </div>
    
                    <div style='margin: 20px 0; padding: 15px; background-color: #f5f5f5; border-radius: 5px;'>
                        <h3 style='color: #1F2937; margin-bottom: 15px;'>Appeal Details</h3>
                        <table style='width: 100%; border-collapse: collapse;'>
                            <tr>
                                <td style='padding: 8px; border-bottom: 1px solid #ddd;'><strong>Description:</strong></td>
                                <td style='padding: 8px; border-bottom: 1px solid #ddd;'>{$appealData['description']}</td>
                            </tr>
                            <tr>
                                <td style='padding: 8px; border-bottom: 1px solid #ddd;'><strong>Status:</strong></td>
                                <td style='padding: 8px; border-bottom: 1px solid #ddd;'>{$appealData['status']}</td>
                            </tr>
                            <tr>
                                <td style='padding: 8px; border-bottom: 1px solid #ddd;'><strong>Created At:</strong></td>
                                <td style='padding: 8px; border-bottom: 1px solid #ddd;'>" . 
                                $createdAt->format('Y-m-d H:i:s') . 
                                "</td>
                            </tr>
                            <tr>
                                <td style='padding: 8px; border-bottom: 1px solid #ddd;'><strong>Updated At:</strong></td>
                                <td style='padding: 8px; border-bottom: 1px solid #ddd;'>" . 
                                $updatedAt->format('Y-m-d H:i:s') . 
                                "</td>
                            </tr>
                        </table>
                    </div>
    
                    <p>Please review this appeal in the admin dashboard.</p>
    
                    <p style='margin-top: 20px;'>Best regards,<br>RaceConnect System</p>
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