<?php
class Reel {
    private $pdo;
    private $table = "Reels";

    public function __construct($db) {
        $this->pdo = $db;
    }

    public function getAllReels() {
        $stmt = $this->pdo->query("SELECT * FROM {$this->table}");
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getReelById($id) {
        $stmt = $this->pdo->prepare("SELECT * FROM {$this->table} WHERE id = :id");
        $stmt->bindParam(':id', $id);
        $stmt->execute();
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function createReel($data) {
        $stmt = $this->pdo->prepare("INSERT INTO {$this->table} (user_id, title, video_url, description) VALUES (:user_id, :title, :video_url, :description)");
        return $stmt->execute([
            ':user_id' => $data['user_id'],
            ':title' => $data['title'],
            ':video_url' => $data['video_url'],
            ':description' => $data['description']
        ]);
    }

    public function updateReel($id, $data) {
        $fields = [];
        $params = [':id' => $id];

        if (isset($data['title'])) {
            $fields[] = "title = :title";
            $params[':title'] = $data['title'];
        }
        if (isset($data['video_url'])) {
            $fields[] = "video_url = :video_url";
            $params[':video_url'] = $data['video_url'];
        }
        if (isset($data['description'])) {
            $fields[] = "description = :description";
            $params[':description'] = $data['description'];
        }

        $stmt = $this->pdo->prepare("UPDATE {$this->table} SET " . implode(", ", $fields) . " WHERE id = :id");
        return $stmt->execute($params);
    }

    public function deleteReel($id) {
        $stmt = $this->pdo->prepare("DELETE FROM {$this->table} WHERE id = :id");
        $stmt->bindParam(':id', $id);
        return $stmt->execute();
    }
}
?>
