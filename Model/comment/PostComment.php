<?php

namespace Model\Comment;

use PDO;
use PDOException;

require_once 'C:/xampp/htdocs/raceconnectapi/vendor/autoload.php';

class PostComment {
    private $pdo;
    private $table = "Post_Comments";

    public function __construct($db) {
        $this->pdo = $db;
    }

    // Get all comments
    public function getAllComments() {
        try {
            $stmt = $this->pdo->query("SELECT * FROM {$this->table} ORDER BY created_at DESC");
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            return [];
        }
    }

    // Get comments by post ID
    public function getCommentsByPostId($post_id) {
        try {
            $stmt = $this->pdo->prepare("SELECT * FROM {$this->table} WHERE post_id = :post_id ORDER BY created_at DESC");
            $stmt->bindParam(':post_id', $post_id, PDO::PARAM_INT);
            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            return [];
        }
    }

    // Get comments by user ID
    public function getCommentsByUserId($user_id) {
        try {
            $stmt = $this->pdo->prepare("SELECT * FROM {$this->table} WHERE user_id = :user_id ORDER BY created_at DESC");
            $stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            return [];
        }
    }

    // Get comment count for a post
    public function getCommentCount($post_id) {
        try {
            $stmt = $this->pdo->prepare("SELECT COUNT(*) AS total_comments FROM {$this->table} WHERE post_id = :post_id");
            $stmt->bindParam(':post_id', $post_id, PDO::PARAM_INT);
            $stmt->execute();
            return $stmt->fetch(PDO::FETCH_ASSOC)['total_comments'] ?? 0;
        } catch (PDOException $e) {
            return 0;
        }
    }

    // Create a new comment
    public function createComment($data) {
        try {
            $stmt = $this->pdo->prepare("INSERT INTO {$this->table} (user_id, post_id, owner_id, comment) VALUES (:user_id, :post_id, :owner_id, :comment)");
            return $stmt->execute([
                ':user_id' => $data['user_id'],
                ':post_id' => $data['post_id'],
                ':owner_id' => $data['owner_id'],
                ':comment' => $data['comment']
            ]);
        } catch (PDOException $e) {
            return false;
        }
    }

    // Update a comment
    public function updateComment($id, $newComment) {
        try {
            $stmt = $this->pdo->prepare("UPDATE {$this->table} SET comment = :comment WHERE id = :id");
            return $stmt->execute([
                ':id' => $id,
                ':comment' => $newComment
            ]);
        } catch (PDOException $e) {
            return false;
        }
    }

    // Delete a comment
    public function deleteComment($id) {
        try {
            $stmt = $this->pdo->prepare("DELETE FROM {$this->table} WHERE id = :id");
            $stmt->bindParam(':id', $id, PDO::PARAM_INT);
            return $stmt->execute();
        } catch (PDOException $e) {
            return false;
        }
    }
}

?>
