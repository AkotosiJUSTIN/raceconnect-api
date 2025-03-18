<?php
namespace Model\Repost;

use PDO;

require_once __DIR__ . '/../../vendor/autoload.php';

class PostRepost {
    private $pdo;
    private $table = "Post_Reposts";

    public function __construct($db) {
        $this->pdo = $db;
    }

    // Get all reposts
    public function getAllReposts() {
        $stmt = $this->pdo->query("SELECT id, user_id, post_id, owner_id, quote, created_at FROM {$this->table}");
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // Get reposts for a specific post
    public function getRepostsByPostId($post_id) {
        $stmt = $this->pdo->prepare("SELECT id, user_id, post_id, owner_id, quote, created_at FROM {$this->table} WHERE post_id = :post_id");
        $stmt->bindParam(':post_id', $post_id, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function createRepost($data) {
        if (!isset($data['user_id']) || !isset($data['post_id'])) {
            return ['message' => 'Missing required fields (user_id, post_id)'];
        }
    
        // Fetch the owner_id of the post
        $stmt = $this->pdo->prepare("SELECT user_id AS owner_id FROM Posts WHERE id = :post_id");
        $stmt->bindParam(':post_id', $data['post_id'], PDO::PARAM_INT);
        $stmt->execute();
        $post = $stmt->fetch(PDO::FETCH_ASSOC);
    
        if (!$post) {
            return ['message' => 'Post not found'];
        }
    
        $owner_id = $post['owner_id'];
    
        // Check if user_id and owner_id are the same
        if ($data['user_id'] == $owner_id) {
            return ['message' => 'You cannot repost your own post'];
        }
    
        // Check if repost already exists
        $stmt = $this->pdo->prepare("SELECT id FROM {$this->table} WHERE user_id = :user_id AND post_id = :post_id");
        $stmt->execute([
            ':user_id' => $data['user_id'],
            ':post_id' => $data['post_id']
        ]);
        if ($stmt->fetch()) {
            return ['message' => 'You have already reposted this post'];
        }
    
        // Insert new repost with quote
        $stmt = $this->pdo->prepare("INSERT INTO {$this->table} (user_id, post_id, owner_id, quote) VALUES (:user_id, :post_id, :owner_id, :quote)");
        $result = $stmt->execute([
            ':user_id' => $data['user_id'],
            ':post_id' => $data['post_id'],
            ':owner_id' => $owner_id,
            ':quote' => $data['quote'] ?? null
        ]);
    
        if ($result) {
            $repost_id = $this->pdo->lastInsertId();
    
            // Fetch the username of the user who reposted
            $stmt = $this->pdo->prepare("SELECT username FROM Users WHERE id = :user_id");
            $stmt->bindParam(':user_id', $data['user_id'], PDO::PARAM_INT);
            $stmt->execute();
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
            if ($user && $data['user_id'] != $owner_id) {
                $stmt = $this->pdo->prepare("INSERT INTO Notifications (user_id, post_id, repost_id, type, content, trigger_user_id, created_at) VALUES (:owner_id, :post_id, :repost_id, 'repost', CONCAT(:username, ' reposted your post'), :trigger_user_id, NOW())");
                $stmt->execute([
                    ':owner_id' => $owner_id,
                    ':post_id' => $data['post_id'],
                    ':repost_id' => $repost_id,
                    ':username' => $user['username'],
                    ':trigger_user_id' => $data['user_id'] // Set the trigger_user_id to the user who reposted
                ]);
            }
            return ['success' => true, 'repost_id' => $repost_id];
        }
    
        return ['success' => false, 'message' => 'Failed to create repost'];
    }

    // Delete a repost by ID
    public function deleteRepost($id) {
        $stmt = $this->pdo->prepare("SELECT post_id, owner_id FROM {$this->table} WHERE id = :id");
        $stmt->bindParam(':id', $id, PDO::PARAM_INT);
        $stmt->execute();
        $repost = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$repost) {
            return false;
        }

        $stmt = $this->pdo->prepare("DELETE FROM {$this->table} WHERE id = :id");
        $stmt->bindParam(':id', $id, PDO::PARAM_INT);
        $result = $stmt->execute();

        if ($result) {
            $stmt = $this->pdo->prepare("DELETE FROM Notifications WHERE repost_id = :repost_id");
            $stmt->execute([':repost_id' => $id]);
            return true;
        }

        return false;
    }

    // Delete all reposts for a specific post
    public function deleteRepostsByPostId($post_id) {
        $stmt = $this->pdo->prepare("SELECT id FROM {$this->table} WHERE post_id = :post_id");
        $stmt->bindParam(':post_id', $post_id, PDO::PARAM_INT);
        $stmt->execute();
        $repost_ids = $stmt->fetchAll(PDO::FETCH_COLUMN);

        if (empty($repost_ids)) {
            return false;
        }

        $stmt = $this->pdo->prepare("DELETE FROM {$this->table} WHERE post_id = :post_id");
        $stmt->bindParam(':post_id', $post_id, PDO::PARAM_INT);
        $result = $stmt->execute();

        if ($result) {
            $stmt = $this->pdo->prepare("DELETE FROM Notifications WHERE repost_id IN (" . implode(',', array_fill(0, count($repost_ids), '?')) . ")");
            $stmt->execute($repost_ids);
            return true;
        }

        return false;
    }

    // Get reposts by user ID
    public function getRepostsByUserId($user_id) {
        $stmt = $this->pdo->prepare("SELECT id, user_id, post_id, owner_id, quote, created_at FROM {$this->table} WHERE user_id = :user_id ORDER BY created_at DESC");
        $stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}