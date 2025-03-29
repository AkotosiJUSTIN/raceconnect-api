<?php
class CleanupService {
    private $conn;
    private $log_path;

    public function __construct($conn) {
        $this->conn = $conn;
        $this->log_path = __DIR__ . '/../logs/cleanup/';
        
        // Create logs directory if it doesn't exist
        if (!file_exists($this->log_path)) {
            mkdir($this->log_path, 0777, true);
        }
    }

    public function cleanupArchivedData() {
        try {
            $this->conn->begin_transaction();
    
            // Existing cleanup for admin notifications
            $stmt = $this->conn->prepare("
                UPDATE admin_notifications 
                SET archived_at = CURRENT_TIMESTAMP 
                WHERE status = 'archived' 
                AND archived_at IS NULL
            ");
            $stmt->execute();
    
            // Delete old notifications
            $stmt = $this->conn->prepare("
                DELETE FROM admin_notifications 
                WHERE status = 'archived' 
                AND archived_at <= DATE_SUB(NOW(), INTERVAL 15 DAY)
            ");
            $stmt->execute();
            $notificationsDeleted = $stmt->affected_rows;
    
            // Clean up resolved appeals older than 15 days
            $stmt = $this->conn->prepare("
                DELETE FROM appeals 
                WHERE status IN ('APPROVED', 'REJECTED')
                AND updated_at <= DATE_SUB(NOW(), INTERVAL 15 DAY)
            ");
            $stmt->execute();
            $appealsDeleted = $stmt->affected_rows;
    
            // Clean up resolved reports older than 15 days
            $stmt = $this->conn->prepare("
                DELETE FROM reports 
                WHERE status = 'resolved'
                AND resolved_at <= DATE_SUB(NOW(), INTERVAL 15 DAY)
                AND (
                    post_id IS NULL 
                    OR post_id IN (SELECT id FROM posts WHERE status = 'Archived')
                    OR marketplace_item_id IN (SELECT id FROM marketplace_items WHERE status = 'Archived')
                )
            ");
            $stmt->execute();
            $reportsDeleted = $stmt->affected_rows;
    
            // Delete old archived posts
            $stmt = $this->conn->prepare("
                DELETE FROM posts 
                WHERE status = 'Archived' 
                AND archived_at <= DATE_SUB(NOW(), INTERVAL 15 DAY)
            ");
            $stmt->execute();
            $postsDeleted = $stmt->affected_rows;
    
            // Delete old archived marketplace items
            $stmt = $this->conn->prepare("
                DELETE FROM marketplace_items 
                WHERE status = 'Archived'
                AND archived_at <= DATE_SUB(NOW(), INTERVAL 15 DAY)
            ");
            $stmt->execute();
            $itemsDeleted = $stmt->affected_rows;
    
            $this->conn->commit();
    
            // Log the cleanup results
            $this->logCleanup([
                'notifications' => $notificationsDeleted,
                'appeals' => $appealsDeleted,
                'reports' => $reportsDeleted,
                'posts' => $postsDeleted,
                'items' => $itemsDeleted,
                'timestamp' => date('Y-m-d H:i:s')
            ]);
    
            return true;
    
        } catch (Exception $e) {
            $this->conn->rollback();
            $this->logError($e->getMessage());
            return false;
        }
    }
    
    public function checkAndCleanup() {
        try {
            // Check if cleanup is needed
            $stmt = $this->conn->prepare("
                SELECT * FROM cleanup_timestamp 
                WHERE next_cleanup <= CURRENT_TIMESTAMP 
                LIMIT 1
            ");
            $stmt->execute();
            $needsCleanup = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($needsCleanup) {
                // Perform cleanup
                $success = $this->cleanupArchivedData();
                
                if ($success) {
                    // Update timestamps
                    $stmt = $this->conn->prepare("
                        UPDATE cleanup_timestamp 
                        SET last_cleanup = CURRENT_TIMESTAMP,
                            next_cleanup = DATE_ADD(CURRENT_TIMESTAMP, INTERVAL 1 DAY)
                        WHERE id = :id
                    ");
                    $stmt->execute([':id' => $needsCleanup['id']]);
                }
                return $success;
            }
            return true;
        } catch (Exception $e) {
            $this->logError($e->getMessage());
            return false;
        }
    }

    private function logCleanup($counts) {
        $message = date('Y-m-d H:i:s') . " - Cleanup completed:\n";
        $message .= "Notifications deleted: {$counts['notifications']}\n";
        $message .= "Appeals deleted: {$counts['appeals']}\n";
        $message .= "Reports deleted: {$counts['reports']}\n";
        $message .= "Posts deleted: {$counts['posts']}\n";
        $message .= "Marketplace items deleted: {$counts['items']}\n";
        $message .= "Timestamp: {$counts['timestamp']}\n";
        file_put_contents($this->log_path . 'cleanup.log', $message, FILE_APPEND);
    }
}