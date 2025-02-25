<?php

namespace Model\Like;
use PDO;

require_once __DIR__ . '/../../vendor/autoload.php';

class MarketplaceItemLike {
    private $pdo;
    private $table = "Marketplace_Item_Likes";

    public function __construct($db) {
        $this->pdo = $db;
    }

    // Get all likes
    public function getAllLikes() {
        $stmt = $this->pdo->query("SELECT * FROM {$this->table}");
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // Get likes for a specific marketplace item
    public function getLikesByItemId($marketplace_item_id) {
        $stmt = $this->pdo->prepare("SELECT * FROM {$this->table} WHERE marketplace_item_id = :marketplace_item_id");
        $stmt->bindParam(':marketplace_item_id', $marketplace_item_id);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // Get total number of likes for a specific marketplace item
    public function getLikeCount($marketplace_item_id) {
        $stmt = $this->pdo->prepare("SELECT COUNT(*) as like_count FROM {$this->table} WHERE marketplace_item_id = :marketplace_item_id");
        $stmt->bindParam(':marketplace_item_id', $marketplace_item_id);
        $stmt->execute();
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    // Check if a user already liked the item
    public function hasUserLiked($user_id, $marketplace_item_id) {
        $stmt = $this->pdo->prepare("SELECT id FROM {$this->table} WHERE user_id = :user_id AND marketplace_item_id = :marketplace_item_id");
        $stmt->execute([
            ':user_id' => $user_id,
            ':marketplace_item_id' => $marketplace_item_id
        ]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ? true : false;
    }

    // Add a like to an item
    public function createLike($data) {
        if ($this->hasUserLiked($data['user_id'], $data['marketplace_item_id'])) {
            return ['error' => 'User has already liked this item'];
        }

        $stmt = $this->pdo->prepare("INSERT INTO {$this->table} (user_id, marketplace_item_id, owner_id) VALUES (:user_id, :marketplace_item_id, :owner_id)");
        return $stmt->execute([
            ':user_id' => $data['user_id'],
            ':marketplace_item_id' => $data['marketplace_item_id'],
            ':owner_id' => $data['owner_id']
        ]);
    }

    // Delete a like by its ID
    public function deleteLike($id) {
        $stmt = $this->pdo->prepare("DELETE FROM {$this->table} WHERE id = :id");
        $stmt->bindParam(':id', $id);
        return $stmt->execute();
    }

    // Delete all likes associated with a specific item
    public function deleteLikesByItemId($marketplace_item_id) {
        $stmt = $this->pdo->prepare("DELETE FROM {$this->table} WHERE marketplace_item_id = :marketplace_item_id");
        $stmt->bindParam(':marketplace_item_id', $marketplace_item_id);
        return $stmt->execute();
    }
}
?>
