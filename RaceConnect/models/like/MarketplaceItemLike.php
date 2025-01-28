<?php
class MarketplaceItemLike {
    private $pdo;
    private $table = "Marketplace_Item_Likes";

    public function __construct($db) {
        $this->pdo = $db;
    }

    public function getAllLikes() {
        $stmt = $this->pdo->query("SELECT * FROM {$this->table}");
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getLikesByItemId($marketplace_item_id) {
        $stmt = $this->pdo->prepare("SELECT * FROM {$this->table} WHERE marketplace_item_id = :marketplace_item_id");
        $stmt->bindParam(':marketplace_item_id', $marketplace_item_id);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function createLike($data) {
        $stmt = $this->pdo->prepare("INSERT INTO {$this->table} (user_id, marketplace_item_id) VALUES (:user_id, :marketplace_item_id)");
        return $stmt->execute([
            ':user_id' => $data['user_id'],
            ':marketplace_item_id' => $data['marketplace_item_id']
        ]);
    }

    public function deleteLike($id) {
        $stmt = $this->pdo->prepare("DELETE FROM {$this->table} WHERE id = :id");
        $stmt->bindParam(':id', $id);
        return $stmt->execute();
    }

    public function deleteLikesByItemId($marketplace_item_id) {
        $stmt = $this->pdo->prepare("DELETE FROM {$this->table} WHERE marketplace_item_id = :marketplace_item_id");
        $stmt->bindParam(':marketplace_item_id', $marketplace_item_id);
        return $stmt->execute();
    }
}
?>
