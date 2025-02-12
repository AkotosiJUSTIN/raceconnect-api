<?php
namespace Model;
use PDO;
use Exception;

class Notification {
    private $pdo;
    private $table = "Notifications";

    public function __construct($db) {
        $this->pdo = $db;
    }

    // Get all notifications for a user
    public function getAllNotifications($userId) {
        $stmt = $this->pdo->prepare("SELECT * FROM {$this->table} WHERE user_id = :user_id ORDER BY created_at DESC");
        $stmt->bindParam(':user_id', $userId, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // Get a single notification by ID
    public function getNotificationById($id) {
        $stmt = $this->pdo->prepare("SELECT * FROM {$this->table} WHERE id = :id");
        $stmt->bindParam(':id', $id, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    // Create a new notification
    public function createNotification($data) {
        try {
            $this->pdo->beginTransaction();

            // Validate required fields
            if (!isset($data['user_id'], $data['type'], $data['content'])) {
                throw new Exception("Missing required fields: user_id, type, content");
            }

            // Validate type
            $validTypes = ['like', 'comment', 'repost'];
            if (!in_array($data['type'], $validTypes)) {
                throw new Exception("Invalid notification type. Allowed: like, comment, repost");
            }

            // Ensure either post_id or marketplace_item_id is provided
            if (!isset($data['post_id']) && !isset($data['marketplace_item_id'])) {
                throw new Exception("Either post_id or marketplace_item_id must be provided.");
            }

            // Prepare insert query
            $stmt = $this->pdo->prepare("INSERT INTO {$this->table} (user_id, post_id, marketplace_item_id, type, content, is_read) 
                                         VALUES (:user_id, :post_id, :marketplace_item_id, :type, :content, 0)");
            $stmt->execute([
                ':user_id' => $data['user_id'],
                ':post_id' => $data['post_id'] ?? null,
                ':marketplace_item_id' => $data['marketplace_item_id'] ?? null,
                ':type' => $data['type'],
                ':content' => $data['content']
            ]);

            $this->pdo->commit();
            return true;
        } catch (Exception $e) {
            $this->pdo->rollBack();
            return ['error' => $e->getMessage()];
        }
    }

    // Mark a notification as read
    public function markAsRead($id) {
        $stmt = $this->pdo->prepare("UPDATE {$this->table} SET is_read = 1 WHERE id = :id");
        $stmt->bindParam(':id', $id, PDO::PARAM_INT);
        return $stmt->execute();
    }

    // Delete a notification
    public function deleteNotification($id) {
        $stmt = $this->pdo->prepare("DELETE FROM {$this->table} WHERE id = :id");
        $stmt->bindParam(':id', $id, PDO::PARAM_INT);
        return $stmt->execute();
    }
}
?>
