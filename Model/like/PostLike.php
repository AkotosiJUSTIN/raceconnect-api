<?php
namespace Model\Like;
use PDO;

require_once __DIR__ . '/../../vendor/autoload.php';
class PostLike {
    private $pdo;
    private $table = "Post_Likes";

    public function __construct($db) {
        $this->pdo = $db;
    }

    // Get all likes
    public function getAllLikes() {
        $stmt = $this->pdo->query("SELECT * FROM {$this->table}");
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // Get likes for a specific post
    public function getLikesByPostId($post_id) {
        $stmt = $this->pdo->prepare("SELECT * FROM {$this->table} WHERE post_id = :post_id");
        $stmt->bindParam(':post_id', $post_id);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // Create a new like
    public function createLike($data) {
        // Check if like already exists
        $stmt = $this->pdo->prepare("SELECT id FROM {$this->table} WHERE user_id = :user_id AND post_id = :post_id");
        $stmt->execute([
            ':user_id' => $data['user_id'],
            ':post_id' => $data['post_id']
        ]);
        if ($stmt->fetch()) {
            return ['error' => 'User already liked this post'];
        }

        // Insert new like
        $stmt = $this->pdo->prepare("
            INSERT INTO {$this->table} (user_id, post_id, owner_id) 
            VALUES (:user_id, :post_id, :owner_id)
        ");
        return $stmt->execute([
            ':user_id' => $data['user_id'],
            ':post_id' => $data['post_id'],
            ':owner_id' => $data['owner_id']
        ]);
    }

    // Delete a like by ID
    public function deleteLike($id) {
        $stmt = $this->pdo->prepare("DELETE FROM {$this->table} WHERE id = :id");
        $stmt->bindParam(':id', $id);
        return $stmt->execute();
    }

    // Delete all likes for a specific post
    public function deleteLikesByPostId($post_id) {
        $stmt = $this->pdo->prepare("DELETE FROM {$this->table} WHERE post_id = :post_id");
        $stmt->bindParam(':post_id', $post_id);
        return $stmt->execute();
    }

    // Get like count for a post
    public function getLikeCount($post_id) {
        $stmt = $this->pdo->prepare("SELECT COUNT(*) as like_count FROM {$this->table} WHERE post_id = :post_id");
        $stmt->bindParam(':post_id', $post_id);
        $stmt->execute();
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
}
?>
