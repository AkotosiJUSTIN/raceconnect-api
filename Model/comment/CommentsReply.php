<?php
namespace Model\Comment;

use PDO;

require_once __DIR__ . '/../../vendor/autoload.php';

class CommentsReply {
    private $pdo;
    private $table = "Comments_Reply";

    public function __construct($db) {
        $this->pdo = $db;
    }

    // Get all replies
    public function getAllReplies() {
        $query = "SELECT cr.id, cr.parent_comment_id, cr.user_id, cr.post_id, cr.reply_text AS text, cr.created_at, cr.likes, u.username 
                  FROM {$this->table} cr 
                  LEFT JOIN Users u ON cr.user_id = u.id 
                  ORDER BY cr.created_at DESC";
        $stmt = $this->pdo->query($query);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // Get replies by parent comment ID with pagination (no metadata)
    public function getRepliesByCommentId($parent_comment_id, $page = 1, $limit = 10) {
        $offset = ($page - 1) * $limit;

        $query = "SELECT cr.id, cr.parent_comment_id, cr.user_id, cr.post_id, cr.reply_text AS reply, cr.created_at, cr.likes, u.username 
                  FROM {$this->table} cr 
                  LEFT JOIN Users u ON cr.user_id = u.id 
                  WHERE cr.parent_comment_id = :parent_comment_id 
                  ORDER BY cr.created_at DESC 
                  LIMIT :limit OFFSET :offset";
        $stmt = $this->pdo->prepare($query);
        $stmt->bindParam(':parent_comment_id', $parent_comment_id, PDO::PARAM_INT);
        $stmt->bindParam(':limit', $limit, PDO::PARAM_INT);
        $stmt->bindParam(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // Get reply count for a comment
    public function getReplyCount($parent_comment_id) {
        $stmt = $this->pdo->prepare("SELECT COUNT(*) AS total_replies FROM {$this->table} WHERE parent_comment_id = :parent_comment_id");
        $stmt->bindParam(':parent_comment_id', $parent_comment_id, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetch(PDO::FETCH_ASSOC)['total_replies'] ?? 0;
    }

    // Create a new reply
    public function createReply($data) {
        if (!isset($data['user_id']) || !isset($data['parent_comment_id']) || !isset($data['reply_text'])) {
            return ['message' => 'Missing required fields (user_id, parent_comment_id, reply_text)'];
        }

        // Fetch the post_id and owner_id from the parent comment
        $stmt = $this->pdo->prepare("SELECT post_id, owner_id FROM Post_Comments WHERE id = :parent_comment_id");
        $stmt->bindParam(':parent_comment_id', $data['parent_comment_id'], PDO::PARAM_INT);
        $stmt->execute();
        $comment = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$comment) {
            return ['message' => 'Parent comment not found'];
        }

        $post_id = $comment['post_id'];
        $owner_id = $comment['owner_id'];

        // Insert the reply
        $stmt = $this->pdo->prepare("INSERT INTO {$this->table} (parent_comment_id, user_id, post_id, owner_id, reply_text, created_at, likes) 
                                     VALUES (:parent_comment_id, :user_id, :post_id, :owner_id, :reply_text, NOW(), :likes)");
        $stmt->execute([
            ':parent_comment_id' => $data['parent_comment_id'],
            ':user_id' => $data['user_id'],
            ':post_id' => $post_id,
            ':owner_id' => $owner_id,
            ':reply_text' => $data['reply_text'],
            ':likes' => 0
        ]);

        $reply_id = $this->pdo->lastInsertId();

        // Fetch the username of the replier
        $stmt = $this->pdo->prepare("SELECT username FROM Users WHERE id = :user_id");
        $stmt->bindParam(':user_id', $data['user_id'], PDO::PARAM_INT);
        $stmt->execute();
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        // Insert notification for the post owner (if not the replier)
        if ($user && $data['user_id'] != $owner_id) {
            $stmt = $this->pdo->prepare("INSERT INTO Notifications (user_id, post_id, comment_id, type, content, trigger_user_id, created_at) 
                                         VALUES (:owner_id, :post_id, :parent_comment_id, 'system', CONCAT(:username, ' replied to a comment on your post'), :trigger_user_id, NOW())");
            $stmt->execute([
                ':owner_id' => $owner_id,
                ':post_id' => $post_id,
                ':parent_comment_id' => $data['parent_comment_id'],
                ':username' => $user['username'] ?? 'Unknown',
                ':trigger_user_id' => $data['user_id']
            ]);
        }

        return ['success' => true, 'reply_id' => $reply_id];
    }

    // Update a reply
    public function updateReply($id, $newReplyText) {
        $stmt = $this->pdo->prepare("UPDATE {$this->table} SET reply_text = :reply_text, updated_at = NOW() WHERE id = :id");
        return $stmt->execute([
            ':id' => $id,
            ':reply_text' => $newReplyText
        ]);
    }

    // Delete a reply
    public function deleteReply($id) {
        $stmt = $this->pdo->prepare("DELETE FROM {$this->table} WHERE id = :id");
        $stmt->bindParam(':id', $id, PDO::PARAM_INT);
        return $stmt->execute();
    }

    // Get a reply by ID
    public function getReplyById($id) {
        $query = "SELECT cr.id, cr.parent_comment_id, cr.user_id, cr.post_id, cr.reply_text AS text, cr.created_at, cr.likes, u.username 
                  FROM {$this->table} cr 
                  LEFT JOIN Users u ON cr.user_id = u.id 
                  WHERE cr.id = :id";
        $stmt = $this->pdo->prepare($query);
        $stmt->bindParam(':id', $id, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
}