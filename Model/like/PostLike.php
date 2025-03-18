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

    public function createLike($data) {
        if (!isset($data['user_id']) || !isset($data['post_id'])) {
            return ['message' => 'Missing required fields (user_id, post_id)'];
        }
    
        // Fetch the owner_id of the post
        $stmt = $this->pdo->prepare("SELECT user_id AS owner_id FROM Posts WHERE id = :post_id");
        $stmt->bindParam(':post_id', $data['post_id']);
        $stmt->execute();
        $post = $stmt->fetch(PDO::FETCH_ASSOC);
    
        if (!$post) {
            return ['message' => 'Post not found'];
        }
    
        $owner_id = $post['owner_id'];
    
        // Check if like already exists
        $stmt = $this->pdo->prepare("SELECT id FROM {$this->table} WHERE user_id = :user_id AND post_id = :post_id");
        $stmt->execute([
            ':user_id' => $data['user_id'],
            ':post_id' => $data['post_id']
        ]);
        $existing_like = $stmt->fetch(PDO::FETCH_ASSOC);
    
        if ($existing_like) {
            return ['message' => 'User already liked this post'];
        }
    
        // Insert new like
        $stmt = $this->pdo->prepare("INSERT INTO {$this->table} (user_id, post_id, owner_id) VALUES (:user_id, :post_id, :owner_id)");
        $stmt->execute([
            ':user_id' => $data['user_id'],
            ':post_id' => $data['post_id'],
            ':owner_id' => $owner_id
        ]);
    
        $like_id = $this->pdo->lastInsertId(); // Get the newly inserted like ID
    
        // Fetch the username of the user who liked the post
        $stmt = $this->pdo->prepare("SELECT username FROM Users WHERE id = :user_id");
        $stmt->bindParam(':user_id', $data['user_id']);
        $stmt->execute();
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
        // Only insert notification if the user is not the owner
        if ($user && $data['user_id'] != $owner_id) {
            $stmt = $this->pdo->prepare("INSERT INTO Notifications (user_id, post_id, like_id, type, content, trigger_user_id, created_at) 
                                         VALUES (:owner_id, :post_id, :like_id, 'system', CONCAT(:username, ' liked your post'), :trigger_user_id, NOW())");
            $stmt->execute([
                ':owner_id' => $owner_id,
                ':post_id' => $data['post_id'],
                ':like_id' => $like_id,
                ':username' => $user['username'],
                ':trigger_user_id' => $data['user_id'] // Set the trigger_user_id to the user who liked
            ]);
        }
    
        return ['success' => true];
    }

    // Delete a like by ID
    public function deleteLike($id) {
        // Get the like details before deleting
        $stmt = $this->pdo->prepare("SELECT post_id, owner_id FROM {$this->table} WHERE id = :id");
        $stmt->bindParam(':id', $id);
        $stmt->execute();
        $like = $stmt->fetch(PDO::FETCH_ASSOC);
    
        if ($like) {
            // Delete the like entry
            $stmt = $this->pdo->prepare("DELETE FROM {$this->table} WHERE id = :id");
            $stmt->bindParam(':id', $id);
            $result = $stmt->execute();
    
            if ($result) {
                // Delete the corresponding notification using like_id
                $stmt = $this->pdo->prepare("DELETE FROM Notifications WHERE like_id = :like_id");
                $stmt->execute([':like_id' => $id]);
            }
    
            return $result;
        }
    
        return false;
    }

    // Delete all likes for a specific post
    public function deleteLikesByPostId($post_id) {
        // Delete the likes
        $stmt = $this->pdo->prepare("DELETE FROM {$this->table} WHERE post_id = :post_id");
        $stmt->bindParam(':post_id', $post_id);
        $result = $stmt->execute();

        if ($result) {
            // Delete the corresponding notifications
            $stmt = $this->pdo->prepare("DELETE FROM Notifications WHERE post_id = :post_id AND type = 'like'");
            $stmt->execute([':post_id' => $post_id]);
        }

        return $result;
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