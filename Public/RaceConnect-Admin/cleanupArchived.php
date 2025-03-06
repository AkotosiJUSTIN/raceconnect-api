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

            // Add archived_at timestamp when archiving
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
                AND archived_at <= DATE_SUB(NOW(), INTERVAL 30 DAY)
            ");
            $stmt->execute();
            $notificationsDeleted = $stmt->affected_rows;

            // Delete old posts
            $stmt = $this->conn->prepare("
                DELETE FROM posts 
                WHERE status = 'archived' 
                AND archived_at <= DATE_SUB(NOW(), INTERVAL 30 DAY)
            ");
            $stmt->execute();
            $postsDeleted = $stmt->affected_rows;

            // Delete old marketplace items
            $stmt = $this->conn->prepare("
                DELETE FROM marketplace_items 
                WHERE status = 'archived' 
                AND archived_at <= DATE_SUB(NOW(), INTERVAL 30 DAY)
            ");
            $stmt->execute();
            $itemsDeleted = $stmt->affected_rows;

            $this->conn->commit();

            // Log the cleanup results
            $this->logCleanup([
                'notifications' => $notificationsDeleted,
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

    private function logCleanup($counts) {
        $message = date('Y-m-d H:i:s') . " - Cleanup completed:\n";
        $message .= "Notifications deleted: {$counts['notifications']}\n";
        $message .= "Posts deleted: {$counts['posts']}\n";
        $message .= "Marketplace items deleted: {$counts['items']}\n";
        $message .= "Timestamp: {$counts['timestamp']}\n";
        file_put_contents($this->log_path . 'cleanup.log', $message, FILE_APPEND);
    }

    private function logError($error) {
        $message = date('Y-m-d H:i:s') . " - Error during cleanup: {$error}\n";
        file_put_contents($this->log_path . 'cleanup_errors.log', $message, FILE_APPEND);
    }
}