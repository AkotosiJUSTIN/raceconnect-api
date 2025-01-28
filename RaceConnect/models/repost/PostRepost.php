<?php
class PostRepost {
    private $pdo;
    private $table = "Post_Reposts";

    public function __construct($db) {
        $this->pdo = $db;
    }

    public function getAllReposts() {
        $stmt = $this->pdo->query("SELECT * FROM {$this->table}");
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getRepostsByPostId($post_id) {
        $stmt = $this->pdo->prepare("SELECT * FROM {$this->table} WHERE post_id = :post_id");
        $stmt->bindParam(':post_id', $post_id);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function createRepost($data) {
        $stmt = $this->pdo->prepare("INSERT INTO {$this->table} (user_id, post_id) VALUES (:user_id, :post_id)");
        return $stmt->execute([
            ':user_id' => $data['user_id'],
            ':post_id' => $data['post_id']
        ]);
    }

    public function deleteRepost($id) {
        $stmt = $this->pdo->prepare("DELETE FROM {$this->table} WHERE id = :id");
        $stmt->bindParam(':id', $id);
        return $stmt->execute();
    }
}
?>
