<?php
namespace Model\Comment;

use PDO;

require_once __DIR__ . '/../../vendor/autoload.php';

class CommentsLike {
    private $pdo;
    private $table = "Comments_Likes";

    public function __construct($db) {
        $this->pdo = $db;
    }

    // Get all likes for a comment
    public function getLikesByCommentId($comment_id) {
        $query = "SELECT cl.id, cl.comment_id, cl.user_id, cl.owner_id, cl.created_at, u.username 
                  FROM {$this->table} cl 
                  LEFT JOIN Users u ON cl.user_id = u.id 
                  WHERE cl.comment_id = :comment_id 
                  ORDER BY cl.created_at DESC";
        $stmt = $this->pdo->prepare($query);
        $stmt->bindParam(':comment_id', $comment_id, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // Check if a user has liked a comment
    public function hasUserLiked($comment_id, $user_id) {
        $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM {$this->table} WHERE comment_id = :comment_id AND user_id = :user_id");
        $stmt->execute([
            ':comment_id' => $comment_id,
            ':user_id' => $user_id
        ]);
        return $stmt->fetchColumn() > 0;
    }

    // Add a like to a comment
    public function addLike($data) {
        if (!isset($data['user_id']) || !isset($data['comment_id'])) {
            return ['message' => 'Missing required fields (user_id, comment_id)'];
        }

        // Fetch owner_id from Post_Comments
        $stmt = $this->pdo->prepare("SELECT owner_id FROM Post_Comments WHERE id = :comment_id");
        $stmt->bindParam(':comment_id', $data['comment_id'], PDO::PARAM_INT);
        $stmt->execute();
        $comment = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$comment) {
            return ['message' => 'Comment not found'];
        }

        if ($this->hasUserLiked($data['comment_id'], $data['user_id'])) {
            return ['message' => 'User has already liked this comment'];
        }

        $owner_id = $comment['owner_id'];

        $stmt = $this->pdo->prepare("INSERT INTO {$this->table} (comment_id, user_id, owner_id, created_at) 
                                     VALUES (:comment_id, :user_id, :owner_id, NOW())");
        $stmt->execute([
            ':comment_id' => $data['comment_id'],
            ':user_id' => $data['user_id'],
            ':owner_id' => $owner_id
        ]);

        $like_id = $this->pdo->lastInsertId();

        // Fetch username of the liker
        $stmt = $this->pdo->prepare("SELECT username FROM Users WHERE id = :user_id");
        $stmt->bindParam(':user_id', $data['user_id'], PDO::PARAM_INT);
        $stmt->execute();
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        // Insert notification if user is not the owner
        if ($user && $data['user_id'] != $owner_id) {
            $stmt = $this->pdo->prepare("INSERT INTO Notifications (user_id, comment_id, like_id, type, content, trigger_user_id, created_at) 
                                         VALUES (:owner_id, :comment_id, :like_id, 'system', CONCAT(:username, ' liked your comment'), :trigger_user_id, NOW())");
            $stmt->execute([
                ':owner_id' => $owner_id,
                ':comment_id' => $data['comment_id'],
                ':like_id' => $like_id,
                ':username' => $user['username'] ?? 'Unknown',
                ':trigger_user_id' => $data['user_id']
            ]);
        }

        return ['success' => true, 'like_id' => $like_id];
    }

    // Remove a like from a comment
    public function removeLike($comment_id, $user_id) {
        $stmt = $this->pdo->prepare("DELETE FROM {$this->table} WHERE comment_id = :comment_id AND user_id = :user_id");
        $stmt->execute([
            ':comment_id' => $comment_id,
            ':user_id' => $user_id
        ]);

        if ($stmt->rowCount() > 0) {
            $stmt = $this->pdo->prepare("DELETE FROM Notifications WHERE comment_id = :comment_id AND trigger_user_id = :user_id AND type = 'system'");
            $stmt->execute([
                ':comment_id' => $comment_id,
                ':user_id' => $user_id
            ]);
            return true;
        }
        return false;
    }
}