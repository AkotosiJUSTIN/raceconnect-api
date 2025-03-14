<?php

namespace Model\Like;
use PDO;

require_once __DIR__ . '/../../vendor/autoload.php';

class MarketplaceItemLike {
    public $pdo;
    public $table = "Marketplace_Item_Likes";

    public function __construct($db) {
        $this->pdo = $db;
    }

    public function getAllLikes() {
        $stmt = $this->pdo->query("SELECT * FROM {$this->table}");
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getLikesByItemId($marketplace_item_id) {
        $stmt = $this->pdo->prepare("SELECT * FROM {$this->table} WHERE marketplace_item_id = :marketplace_item_id");
        $stmt->execute([':marketplace_item_id' => $marketplace_item_id]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getLikeCount($marketplace_item_id) {
        $stmt = $this->pdo->prepare("SELECT COUNT(*) as like_count FROM {$this->table} WHERE marketplace_item_id = :marketplace_item_id");
        $stmt->execute([':marketplace_item_id' => $marketplace_item_id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function hasUserLiked($user_id, $marketplace_item_id) {
        $stmt = $this->pdo->prepare("SELECT * FROM {$this->table} WHERE user_id = :user_id AND marketplace_item_id = :marketplace_item_id");
        $stmt->execute([
            ':user_id' => $user_id,
            ':marketplace_item_id' => $marketplace_item_id
        ]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function getUserLikedItems($user_id) {
        $stmt = $this->pdo->prepare("
            SELECT mi.* 
            FROM Marketplace_Items mi
            INNER JOIN {$this->table} mil ON mi.id = mil.marketplace_item_id
            WHERE mil.user_id = :user_id
            ORDER BY mil.created_at DESC
        ");
        $stmt->execute([':user_id' => $user_id]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function toggleLike($user_id, $marketplace_item_id, $owner_id) {
        $existingLike = $this->hasUserLiked($user_id, $marketplace_item_id);

        if ($existingLike) {
            $stmt = $this->pdo->prepare("
                DELETE FROM {$this->table} 
                WHERE user_id = :user_id AND marketplace_item_id = :marketplace_item_id
            ");
            $result = $stmt->execute([
                ':user_id' => $user_id,
                ':marketplace_item_id' => $marketplace_item_id
            ]);
            return [
                'status' => $result ? 200 : 500,
                'data' => $result ? ['message' => 'Like removed successfully', 'liked' => false] : ['message' => 'Failed to remove like']
            ];
        } else {
            $stmt = $this->pdo->prepare("
                INSERT INTO {$this->table} (user_id, marketplace_item_id, owner_id) 
                VALUES (:user_id, :marketplace_item_id, :owner_id)
            ");
            $result = $stmt->execute([
                ':user_id' => $user_id,
                ':marketplace_item_id' => $marketplace_item_id,
                ':owner_id' => $owner_id
            ]);
            return [
                'status' => $result ? 201 : 500,
                'data' => $result ? ['message' => 'Like added successfully', 'liked' => true] : ['message' => 'Failed to add like']
            ];
        }
    }

    public function deleteLike($id) {
        $stmt = $this->pdo->prepare("DELETE FROM {$this->table} WHERE id = :id");
        return $stmt->execute([':id' => $id]);
    }

    public function deleteLikesByItemId($marketplace_item_id) {
        $stmt = $this->pdo->prepare("DELETE FROM {$this->table} WHERE marketplace_item_id = :marketplace_item_id");
        return $stmt->execute([':marketplace_item_id' => $marketplace_item_id]);
    }

    // Add this to MarketplaceItemLike class
    public function getLikedItemsByUserIds(array $user_ids) {
        if (empty($user_ids)) {
            return [];
        }
        
        // Prepare placeholders for the IN clause
        $placeholders = implode(',', array_fill(0, count($user_ids), '?'));
        
        $stmt = $this->pdo->prepare("
            SELECT mil.user_id, mi.*, mil.created_at as liked_at
            FROM Marketplace_Items mi
            INNER JOIN {$this->table} mil ON mi.id = mil.marketplace_item_id
            WHERE mil.user_id IN ($placeholders)
            ORDER BY mil.user_id, mil.created_at DESC
        ");
        
        $stmt->execute($user_ids);
        $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Group results by user_id
        $grouped = [];
        foreach ($results as $row) {
            $user_id = $row['user_id'];
            unset($row['user_id']); // Remove user_id from item data
            $grouped[$user_id][] = $row;
        }
        
        return $grouped;
    }
}