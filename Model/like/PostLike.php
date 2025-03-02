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
        // Check if required fields are present
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
    
        $owner_id = $post['owner_id']; // Set owner_id from the Posts table
    
        // Check if like already exists
        $stmt = $this->pdo->prepare("SELECT id FROM {$this->table} WHERE user_id = :user_id AND post_id = :post_id");
        $stmt->execute([
            ':user_id' => $data['user_id'],
            ':post_id' => $data['post_id']
        ]);
        if ($stmt->fetch()) {
            return ['message' => 'User already liked this post'];
        }
    
        // Insert new like
        $stmt = $this->pdo->prepare("
            INSERT INTO {$this->table} (user_id, post_id, owner_id) 
            VALUES (:user_id, :post_id, :owner_id)
        ");
        $result = $stmt->execute([
            ':user_id' => $data['user_id'],
            ':post_id' => $data['post_id'],
            ':owner_id' => $owner_id
        ]);
    
        if ($result) {
            // Fetch the username of the user who liked the post
            $stmt = $this->pdo->prepare("SELECT username FROM Users WHERE id = :user_id");
            $stmt->bindParam(':user_id', $data['user_id']);
            $stmt->execute();
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
            if ($user) {
                // Insert notification with the username
                $stmt = $this->pdo->prepare("
                    INSERT INTO Notifications (user_id, post_id, type, content, created_at)
                    VALUES (:owner_id, :post_id, 'like', CONCAT(:username, ' liked your post'), NOW())
                ");
                $stmt->execute([
                    ':owner_id' => $owner_id,
                    ':post_id' => $data['post_id'],
                    ':username' => $user['username']
                ]);
            }
        }
    
        return ['success' => true];
    }
    

    // Delete a like by ID
    public function deleteLike($id) {
        // Get the post_id and owner_id for the like to be deleted
        $stmt = $this->pdo->prepare("SELECT post_id, owner_id FROM {$this->table} WHERE id = :id");
        $stmt->bindParam(':id', $id);
        $stmt->execute();
        $like = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($like) {
            // Delete the like
            $stmt = $this->pdo->prepare("DELETE FROM {$this->table} WHERE id = :id");
            $stmt->bindParam(':id', $id);
            $result = $stmt->execute();

            if ($result) {
                // Delete the corresponding notification
                $stmt = $this->pdo->prepare("
                    DELETE FROM Notifications 
                    WHERE user_id = :owner_id AND post_id = :post_id AND type = 'like'
                ");
                $stmt->execute([
                    ':owner_id' => $like['owner_id'],
                    ':post_id' => $like['post_id']
                ]);
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
            $stmt = $this->pdo->prepare("
                DELETE FROM Notifications 
                WHERE post_id = :post_id AND type = 'like'
            ");
            $stmt->execute([
                ':post_id' => $post_id
            ]);
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
