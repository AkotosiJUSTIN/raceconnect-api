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

            // Validate postId for POST_PENALTY
            if ($data['concernType'] === 'POST_PENALTY') {
                if (!isset($data['postId']) || !is_numeric($data['postId'])) {
                    return [
                        'success' => false,
                        'message' => 'Post ID is required for post penalty appeals'
                    ];
                }
            }
    
            // Validate itemId for ITEM_POST_PENALTY
            if ($data['concernType'] === 'ITEM_POST_PENALTY') {
                if (!isset($data['itemId']) || !is_numeric($data['itemId'])) {
                    return [
                        'success' => false,
                        'message' => 'Item ID is required for item post penalty appeals'
                    ];
                }
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

                // Fetch title based on concern type
                $title = null;
                if ($data['concernType'] === 'POST_PENALTY') {
                    $postStmt = $this->conn->prepare("SELECT title FROM posts WHERE id = :postId");
                    $postStmt->execute([':postId' => $postId]);
                    $post = $postStmt->fetch(\PDO::FETCH_ASSOC);
                    $title = $post ? $post['title'] : 'Unknown Post';
                } elseif ($data['concernType'] === 'ITEM_POST_PENALTY') {
                    $itemStmt = $this->conn->prepare("SELECT title FROM marketplace_items WHERE id = :itemId");
                    $itemStmt->execute([':itemId' => $itemId]);
                    $item = $itemStmt->fetch(\PDO::FETCH_ASSOC);
                    $title = $item ? $item['title'] : 'Unknown Item';
                }

                // Set notification content based on appeal type
                if ($data['concernType'] === 'ACCOUNT_PENALTY') {
                    $content = "{$data['username']} has submitted an account appeal.";
                } elseif ($data['concernType'] === 'POST_PENALTY') {
                    $content = "{$data['username']} has submitted an appeal for their post: {$title}";
                } elseif ($data['concernType'] === 'ITEM_POST_PENALTY') {
                    $content = "{$data['username']} has submitted an appeal for their item: {$title}";
                }
    
                // Create admin notification
                $notifQuery = "INSERT INTO admin_notifications 
                            (admin_id, reporter_id, type, content, reporter_username, 
                                appeal_id, severity, status, post_id, marketplace_item_id) 
                            SELECT 
                                a.id,
                                :reporter_id,
                                'appeal_submission',
                                :content,
                                :username,
                                :appeal_id,
                                'high',
                                'pending',
                                :post_id,
                                :item_id
                            FROM admins a 
                            WHERE a.active = 1 
                            AND a.role = 'community_manager' 
                            ORDER BY a.id ASC
                            LIMIT 1";

                $notifStmt = $this->conn->prepare($notifQuery);
                $notifStmt->execute([
                    ':reporter_id' => $user['id'],
                    ':content' => $content,
                    ':username' => $data['username'],
                    ':appeal_id' => $appealId,
                    ':post_id' => $postId,
                    ':item_id' => $itemId
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

    public function updateAppealForUser($userId, $status) {
        try {
            $this->conn->beginTransaction();
    
            // Find any pending account appeals for this user
            $appealQuery = "SELECT a.*, u.email, u.username 
                        FROM appeals a 
                        JOIN Users u ON a.user_id = u.id 
                        WHERE a.user_id = :user_id 
                        AND a.concern_type = 'ACCOUNT_PENALTY'
                        AND a.status = 'PENDING'
                        ORDER BY a.created_at DESC
                        LIMIT 1";
                        
            $appealStmt = $this->conn->prepare($appealQuery);
            $appealStmt->execute([':user_id' => $userId]);
            $appeal = $appealStmt->fetch(\PDO::FETCH_ASSOC);
    
            if ($appeal) {
                // Update appeal status
                $updateQuery = "UPDATE appeals 
                            SET status = :status 
                            WHERE id = :appeal_id";
                
                $updateStmt = $this->conn->prepare($updateQuery);
                $updateStmt->execute([
                    ':status' => strtoupper($status),
                    ':appeal_id' => $appeal['id']
                ]);
    
                // Send email notification
                $emailSent = $this->sendAppealStatusEmail(
                    $appeal['email'],
                    $appeal['username'],
                    $status,
                    'ACCOUNT_PENALTY'
                );
    
                if (!$emailSent) {
                    throw new \Exception('Failed to send email notification');
                }
    
                $this->conn->commit();
                return true;
            }
    
            $this->conn->commit();
            return false;
    
        } catch (\Exception $e) {
            if ($this->conn->inTransaction()) {
                $this->conn->rollBack();
            }
            error_log("Update appeal for user error: " . $e->getMessage());
            return false;
        }
    }

    public function updateAppealStatus($appealId, $status) {
        try {
            $this->conn->beginTransaction();

            // Validate status
            $validStatuses = ['PENDING', 'APPROVED', 'REJECTED'];
            if (!in_array(strtoupper($status), $validStatuses)) {
                return [
                    'success' => false,
                    'message' => 'Invalid appeal status'
                ];
            }

            // Get appeal details first
            $appealQuery = "SELECT a.*, u.email, u.username 
                           FROM appeals a 
                           JOIN Users u ON a.user_id = u.id 
                           WHERE a.id = :appeal_id";
            $appealStmt = $this->conn->prepare($appealQuery);
            $appealStmt->execute([':appeal_id' => $appealId]);
            $appeal = $appealStmt->fetch(\PDO::FETCH_ASSOC);

            if (!$appeal) {
                throw new \Exception('Appeal not found');
            }

            // Update appeal status
            $updateQuery = "UPDATE appeals 
                           SET status = :status 
                           WHERE id = :appeal_id";
            
            $updateStmt = $this->conn->prepare($updateQuery);
            $updateStmt->execute([
                ':status' => strtoupper($status),
                ':appeal_id' => $appealId
            ]);

            // Handle notifications based on appeal type and status
            if ($appeal['concern_type'] === 'ACCOUNT_PENALTY') {
                // Send email for account-related appeals
                $this->sendAppealStatusEmail(
                    $appeal['email'],
                    $appeal['username'],
                    $status,
                    $appeal['concern_type']
                );
            } else {
                // Create in-app notification for post/item appeals
                $notifContent = $this->getNotificationContent(
                    $status, 
                    $appeal['concern_type']
                );

                $notifQuery = "INSERT INTO notifications 
                              (user_id, type, content, post_id, marketplace_item_id) 
                              VALUES (:user_id, 'system', :content, :post_id, :item_id)";
                
                $notifStmt = $this->conn->prepare($notifQuery);
                $notifStmt->execute([
                    ':user_id' => $appeal['user_id'],
                    ':content' => $notifContent,
                    ':post_id' => $appeal['post_id'],
                    ':item_id' => $appeal['item_id']
                ]);
            }

            $this->conn->commit();
            
            return [
                'success' => true,
                'message' => 'Appeal status updated successfully'
            ];

        } catch (\Exception $e) {
            $this->conn->rollBack();
            error_log("Appeal status update error: " . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Failed to update appeal status'
            ];
        }
    }

    public function handleAppealStatusUpdate($userEmail, $username, $status, $concernType) {
        return $this->sendAppealStatusEmail($userEmail, $username, $status, $concernType);
    }

    private function sendAppealStatusEmail($email, $username, $status, $concernType) {
        $mail = new PHPMailer(true);

        try {
            // Server settings
            $mail->isSMTP();
            $mail->Host = 'smtp.gmail.com';
            $mail->SMTPAuth = true;
            $mail->Username = $_ENV['SMTP_USER'];
            $mail->Password = $_ENV['SMTP_PASS'];
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
            $mail->Port = 587;

            // Recipients
            $mail->setFrom('RaceConnect@gmail.com', 'RaceConnect');
            $mail->addAddress($email, $username);

            // Content
            $mail->isHTML(true);
            $mail->Subject = 'Appeal Status Update';
            $mail->Body = $this->getAppealEmailTemplate($username, $status, $concernType);

            $mail->send();
            return true;
        } catch (Exception $e) {
            error_log("Mail Error: " . $mail->ErrorInfo);
            return false;
        }
    }

    private function getAppealEmailTemplate($username, $status, $concernType) {
        $statusText = $status === 'APPROVED' ? 'approved' : 'rejected';
        $actionText = $concernType === 'ACCOUNT_PENALTY' ? 
            ($status === 'APPROVED' ? 'Your account has been unbanned.' : 'Your account will remain banned.') :
            ($status === 'APPROVED' ? 'The reported content will be restored.' : 'The reported content will remain hidden.');

        return "
            <html>
            <body style='font-family: Arial, sans-serif; line-height: 1.6; color: #333;'>
                <div style='max-width: 600px; margin: 0 auto; padding: 20px;'>
                    <h2 style='color: #B91C1C;'>Appeal Status Update</h2>
                    <p>Hello {$username},</p>
                    <p>Your appeal has been {$statusText}.</p>
                    <p>{$actionText}</p>
                    <p>If you have any questions, please contact our support team.</p>
                    <br>
                    <p>Best regards,</p>
                    <p>RaceConnect Team</p>
                </div>
            </body>
            </html>
        ";
    }

    private function getNotificationContent($status, $concernType) {
        $statusText = $status === 'APPROVED' ? 'approved' : 'rejected';
        $typeText = $concernType === 'POST_PENALTY' ? 'post' : 'marketplace item';
        
        return "Your appeal for the reported {$typeText} has been {$statusText}. " . 
               ($status === 'APPROVED' ? 'The content will be restored.' : 'The content will remain hidden.');
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