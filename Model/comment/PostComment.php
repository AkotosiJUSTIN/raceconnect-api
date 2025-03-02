<?php

namespace Model\Comment;

use PDO;

require_once __DIR__ . '/../../vendor/autoload.php';

class PostComment {
    private $pdo;
    private $table = "Post_Comments";

    public function __construct($db) {
        $this->pdo = $db;
    }

    // Get all comments
    public function getAllComments() {
        $stmt = $this->pdo->query("SELECT * FROM {$this->table} ORDER BY created_at DESC");
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // Get comments by post ID
    public function getCommentsByPostId($post_id) {
        $stmt = $this->pdo->prepare("SELECT * FROM {$this->table} WHERE post_id = :post_id ORDER BY created_at DESC");
        $stmt->bindParam(':post_id', $post_id);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // Get comments by user ID
    public function getCommentsByUserId($user_id) {
        $stmt = $this->pdo->prepare("SELECT * FROM {$this->table} WHERE user_id = :user_id ORDER BY created_at DESC");
        $stmt->bindParam(':user_id', $user_id);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // Get comment count for a post
    public function getCommentCount($post_id) {
        $stmt = $this->pdo->prepare("SELECT COUNT(*) AS total_comments FROM {$this->table} WHERE post_id = :post_id");
        $stmt->bindParam(':post_id', $post_id);
        $stmt->execute();
        return $stmt->fetch(PDO::FETCH_ASSOC)['total_comments'] ?? 0;
    }

    // Create a new comment
    public function createComment($data) {
        if (!isset($data['user_id']) || !isset($data['post_id']) || !isset($data['comment'])) {
            return ['message' => 'Missing required fields (user_id, post_id, comment)'];
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

        // Insert the comment
        $stmt = $this->pdo->prepare("INSERT INTO {$this->table} (user_id, post_id, owner_id, comment, created_at) VALUES (:user_id, :post_id, :owner_id, :comment, NOW())");
        $stmt->execute([
            ':user_id' => $data['user_id'],
            ':post_id' => $data['post_id'],
            ':owner_id' => $owner_id,
            ':comment' => $data['comment']
        ]);

        $comment_id = $this->pdo->lastInsertId(); // Get the inserted comment ID

        // Fetch the username of the commenter
        $stmt = $this->pdo->prepare("SELECT username FROM Users WHERE id = :user_id");
        $stmt->bindParam(':user_id', $data['user_id']);
        $stmt->execute();
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($user) {
            // Insert notification with comment_id
            $stmt = $this->pdo->prepare("INSERT INTO Notifications (user_id, post_id, comment_id, content, created_at) VALUES (:owner_id, :post_id, :comment_id, CONCAT(:username, ' commented on your post'), NOW())");
            $stmt->execute([
                ':owner_id' => $owner_id,
                ':post_id' => $data['post_id'],
                ':comment_id' => $comment_id,
                ':username' => $user['username']
            ]);
        }

        return ['success' => true, 'comment_id' => $comment_id];
    }


    // Update a comment
    public function updateComment($id, $newComment) {
        $stmt = $this->pdo->prepare("UPDATE {$this->table} SET comment = :comment WHERE id = :id");
        return $stmt->execute([
            ':id' => $id,
            ':comment' => $newComment
        ]);
    }

   // Delete a comment
    public function deleteComment($id) {
        // Get the post_id, owner_id, and comment_id for the comment
        $stmt = $this->pdo->prepare("SELECT post_id, owner_id FROM {$this->table} WHERE id = :id");
        $stmt->bindParam(':id', $id);
        $stmt->execute();
        $comment = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($comment) {
            // Delete the comment
            $stmt = $this->pdo->prepare("DELETE FROM {$this->table} WHERE id = :id");
            $stmt->bindParam(':id', $id);
            $result = $stmt->execute();

            if ($result) {
                // Delete the corresponding notification using comment_id
                $stmt = $this->pdo->prepare("DELETE FROM Notifications WHERE comment_id = :id");
                $stmt->execute([':id' => $id]);
            }
            return $result;
        }
        return false;
    }


    // Delete all comments for a specific post
    public function deleteCommentsByPostId($post_id) {
        // Delete the comments
        $stmt = $this->pdo->prepare("DELETE FROM {$this->table} WHERE post_id = :post_id");
        $stmt->bindParam(':post_id', $post_id);
        $result = $stmt->execute();

        if ($result) {
            // Delete the corresponding notifications
            $stmt = $this->pdo->prepare("DELETE FROM Notifications WHERE post_id = :post_id");
            $stmt->execute([':post_id' => $post_id]);
        }
        return $result;
    }
}
?>