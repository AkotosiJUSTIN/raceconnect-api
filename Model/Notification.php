<?php
namespace Model;
use PDO;
use Exception;

require_once __DIR__ . '/../vendor/autoload.php';

class Notification {
    private $pdo;
    private $table = "Notifications";

    public function __construct($db) {
        $this->pdo = $db;
    }

    // Get all notifications for a user with trigger user profile
    public function getAllNotifications($userId) {
        $query = "SELECT 
            n.*,
            u.username AS trigger_username,
            u.profile_picture AS trigger_profile_picture
        FROM {$this->table} n
        LEFT JOIN Users u ON n.trigger_user_id = u.id
        WHERE n.user_id = :user_id 
        ORDER BY n.created_at DESC";
        
        $stmt = $this->pdo->prepare($query);
        $stmt->bindParam(':user_id', $userId, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // Get a single notification by ID with trigger user profile
    public function getNotificationById($id) {
        $query = "SELECT 
            n.*,
            u.username AS trigger_username,
            u.profile_picture AS trigger_profile_picture
        FROM {$this->table} n
        LEFT JOIN Users u ON n.trigger_user_id = u.id
        WHERE n.id = :id";
        
        $stmt = $this->pdo->prepare($query);
        $stmt->bindParam(':id', $id, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    // Updated createNotification to include trigger_user_id
    public function createNotification($data) {
        try {
            $this->pdo->beginTransaction();

            // Validate required fields
            if (!isset($data['user_id'], $data['type'], $data['content'], $data['trigger_user_id'])) {
                throw new Exception("Missing required fields: user_id, type, content, trigger_user_id");
            }

            // Prepare insert query
            $stmt = $this->pdo->prepare("INSERT INTO {$this->table} 
                (user_id, post_id, marketplace_item_id, type, content, is_read, trigger_user_id, like_id, comment_id, repost_id) 
                VALUES (:user_id, :post_id, :marketplace_item_id, :type, :content, 0, :trigger_user_id, :like_id, :comment_id, :repost_id)");
            
            $stmt->execute([
                ':user_id' => $data['user_id'],
                ':post_id' => $data['post_id'] ?? null,
                ':marketplace_item_id' => $data['marketplace_item_id'] ?? null,
                ':type' => $data['type'],
                ':content' => $data['content'],
                ':trigger_user_id' => $data['trigger_user_id'],
                ':like_id' => $data['like_id'] ?? null,
                ':comment_id' => $data['comment_id'] ?? null,
                ':repost_id' => $data['repost_id'] ?? null
            ]);

            $this->pdo->commit();
            return true;
        } catch (Exception $e) {
            $this->pdo->rollBack();
            return ['error' => $e->getMessage()];
        }
    }

    // markAsRead and deleteNotification remain unchanged...
    public function markAsRead($id) {
        $stmt = $this->pdo->prepare("UPDATE {$this->table} SET is_read = 1 WHERE id = :id");
        $stmt->bindParam(':id', $id, PDO::PARAM_INT);
        return $stmt->execute();
    }

    public function deleteNotification($id) {
        $stmt = $this->pdo->prepare("DELETE FROM {$this->table} WHERE id = :id");
        $stmt->bindParam(':id', $id, PDO::PARAM_INT);
        return $stmt->execute();
    }
}
?>