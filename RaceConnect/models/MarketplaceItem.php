<?php
class MarketplaceItem {
    private $pdo;
    private $table = "Marketplace_Items";

    public function __construct($db) {
        $this->pdo = $db;
    }

    public function getAllItems() {
        $stmt = $this->pdo->query("SELECT * FROM {$this->table}");
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getItemById($id) {
        $stmt = $this->pdo->prepare("SELECT * FROM {$this->table} WHERE id = :id");
        $stmt->bindParam(':id', $id);
        $stmt->execute();
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function createItem($data) {
        $stmt = $this->pdo->prepare("INSERT INTO {$this->table} (seller_id, title, description, price, category, image_url, favorite_count, status) 
                                    VALUES (:seller_id, :title, :description, :price, :category, :image_url, :favorite_count, :status)");
        return $stmt->execute([
            ':seller_id' => $data['seller_id'],
            ':title' => $data['title'],
            ':description' => $data['description'],
            ':price' => $data['price'],
            ':category' => $data['category'],
            ':image_url' => $data['image_url'],
            ':favorite_count' => $data['favorite_count'],
            ':status' => $data['status']
        ]);
    }

    public function updateItem($id, $data) {
        $fields = [];
        $params = [':id' => $id];

        if (isset($data['title'])) {
            $fields[] = "title = :title";
            $params[':title'] = $data['title'];
        }
        if (isset($data['description'])) {
            $fields[] = "description = :description";
            $params[':description'] = $data['description'];
        }
        if (isset($data['price'])) {
            $fields[] = "price = :price";
            $params[':price'] = $data['price'];
        }
        if (isset($data['category'])) {
            $fields[] = "category = :category";
            $params[':category'] = $data['category'];
        }
        if (isset($data['image_url'])) {
            $fields[] = "image_url = :image_url";
            $params[':image_url'] = $data['image_url'];
        }
        if (isset($data['favorite_count'])) {
            $fields[] = "favorite_count = :favorite_count";
            $params[':favorite_count'] = $data['favorite_count'];
        }
        if (isset($data['status'])) {
            $fields[] = "status = :status";
            $params[':status'] = $data['status'];
        }

        $stmt = $this->pdo->prepare("UPDATE {$this->table} SET " . implode(", ", $fields) . " WHERE id = :id");
        return $stmt->execute($params);
    }

    public function deleteItem($id) {
        $stmt = $this->pdo->prepare("DELETE FROM {$this->table} WHERE id = :id");
        $stmt->bindParam(':id', $id);
        return $stmt->execute();
    }
}
?>
