<?php
class Notification {
    private $pdo;
    private $table = "Notifications";

    public function __construct($db) {
        $this->pdo = $db;
    }

    // Get all notifications for a user
    public function getAllNotifications($userId) {
        $stmt = $this->pdo->prepare("SELECT * FROM {$this->table} WHERE user_id = :user_id");
        $stmt->bindParam(':user_id', $userId);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // Get a single notification by ID
    public function getNotificationById($id) {
        $stmt = $this->pdo->prepare("SELECT * FROM {$this->table} WHERE id = :id");
        $stmt->bindParam(':id', $id);
        $stmt->execute();
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    // Create a new notification
    public function createNotification($data) {
        $stmt = $this->pdo->prepare("INSERT INTO {$this->table} (user_id, content) VALUES (:user_id, :content)");
        return $stmt->execute([
            ':user_id' => $data['user_id'],
            ':content' => $data['content']
        ]);
    }

    // Update a notification's read status
    public function markAsRead($id) {
        $stmt = $this->pdo->prepare("UPDATE {$this->table} SET is_read = 1 WHERE id = :id");
        $stmt->bindParam(':id', $id);
        return $stmt->execute();
    }

    // Delete a notification
    public function deleteNotification($id) {
        $stmt = $this->pdo->prepare("DELETE FROM {$this->table} WHERE id = :id");
        $stmt->bindParam(':id', $id);
        return $stmt->execute();
    }
}
?>
