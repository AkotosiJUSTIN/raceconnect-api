<?php
namespace Model\Repost;

use PDO;
use PDOException;

class PostRepost {
    private $pdo;
    private $table = "Post_Reposts";

    public function __construct($db) {
        $this->pdo = $db;
    }

    // Get all reposts
    public function getAllReposts() {
        $stmt = $this->pdo->query("SELECT * FROM {$this->table}");
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // Get reposts by post ID
    public function getRepostsByPostId($post_id) {
        $stmt = $this->pdo->prepare("SELECT * FROM {$this->table} WHERE post_id = :post_id ORDER BY created_at DESC");
        $stmt->bindParam(':post_id', $post_id, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // Get reposts by user ID
    public function getRepostsByUserId($user_id) {
        $stmt = $this->pdo->prepare("SELECT * FROM {$this->table} WHERE user_id = :user_id ORDER BY created_at DESC");
        $stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // Get repost count for a post
    public function getRepostCount($post_id) {
        $stmt = $this->pdo->prepare("SELECT COUNT(*) AS total_reposts FROM {$this->table} WHERE post_id = :post_id");
        $stmt->bindParam(':post_id', $post_id, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    // Create a new repost
    public function createRepost($data) {
        try {
            $stmt = $this->pdo->prepare("INSERT INTO {$this->table} (user_id, post_id, owner_id) VALUES (:user_id, :post_id, :owner_id)");
            return $stmt->execute([
                ':user_id' => $data['user_id'],
                ':post_id' => $data['post_id'],
                ':owner_id' => $data['owner_id']
            ]);
        } catch (PDOException $e) {
            return false;
        }
    }

    // Delete a repost
    public function deleteRepost($id) {
        $stmt = $this->pdo->prepare("DELETE FROM {$this->table} WHERE id = :id");
        $stmt->bindParam(':id', $id, PDO::PARAM_INT);
        return $stmt->execute();
    }
}
?>
