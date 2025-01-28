<?php
class ReelRepost {
    private $pdo;
    private $table = "Reel_Reposts";

    public function __construct($db) {
        $this->pdo = $db;
    }

    public function getAllReposts() {
        $stmt = $this->pdo->query("SELECT * FROM {$this->table}");
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getRepostsByReelId($reel_id) {
        $stmt = $this->pdo->prepare("SELECT * FROM {$this->table} WHERE reel_id = :reel_id");
        $stmt->bindParam(':reel_id', $reel_id);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function createRepost($data) {
        $stmt = $this->pdo->prepare("INSERT INTO {$this->table} (user_id, reel_id) VALUES (:user_id, :reel_id)");
        return $stmt->execute([
            ':user_id' => $data['user_id'],
            ':reel_id' => $data['reel_id']
        ]);
    }

    public function deleteRepost($id) {
        $stmt = $this->pdo->prepare("DELETE FROM {$this->table} WHERE id = :id");
        $stmt->bindParam(':id', $id);
        return $stmt->execute();
    }
}
?>
