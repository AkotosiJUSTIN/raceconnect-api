<?php

namespace Model;
use PDO;

class Announcement {
    private $pdo;
    private $table = 'announcements';

    public function __construct($db) {
        $this->pdo = $db;
    }

    public function getAllAnnouncements($limit = 10, $offset = 0) {
        $stmt = $this->pdo->prepare("SELECT * FROM {$this->table} LIMIT :limit OFFSET :offset");
        $stmt->bindParam(':limit', $limit, PDO::PARAM_INT);
        $stmt->bindParam(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getAnnouncementById($id) {
        $stmt = $this->pdo->prepare("SELECT * FROM {$this->table} WHERE id = :id");
        $stmt->bindParam(':id', $id, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function createAnnouncement($data) {
        $stmt = $this->pdo->prepare("INSERT INTO {$this->table} (title, content, image_url, status) 
                                    VALUES (:title, :content, :image_url, :status)");
        $result = $stmt->execute([
            ':title' => htmlspecialchars($data['title'], ENT_QUOTES, 'UTF-8'),
            ':content' => htmlspecialchars($data['content'], ENT_QUOTES, 'UTF-8'),
            ':image_url' => htmlspecialchars($data['image_url'], ENT_QUOTES, 'UTF-8'),
            ':status' => htmlspecialchars($data['status'], ENT_QUOTES, 'UTF-8')
        ]);

        if ($result) {
            return $this->pdo->lastInsertId(); // Return inserted ID
        }
        return false;
    }

    public function updateAnnouncement($id, $data) {
        $fields = [];
        $params = [':id' => (int) $id];

        if (!empty($data['title'])) {
            $fields[] = "title = :title";
            $params[':title'] = htmlspecialchars($data['title'], ENT_QUOTES, 'UTF-8');
        }
        if (!empty($data['content'])) {
            $fields[] = "content = :content";
            $params[':content'] = htmlspecialchars($data['content'], ENT_QUOTES, 'UTF-8');
        }
        if (!empty($data['image_url'])) {
            $fields[] = "image_url = :image_url";
            $params[':image_url'] = htmlspecialchars($data['image_url'], ENT_QUOTES, 'UTF-8');
        }
        if (!empty($data['status'])) {
            $fields[] = "status = :status";
            $params[':status'] = htmlspecialchars($data['status'], ENT_QUOTES, 'UTF-8');
        }
        if (empty($fields)) {
            return false; // No valid fields to update
        }

        $stmt = $this->pdo->prepare("UPDATE {$this->table} SET " . implode(", ", $fields) . " WHERE id = :id");
        return $stmt->execute($params);
    }

    public function deleteAnnouncement($id) {
        $stmt = $this->pdo->prepare("DELETE FROM {$this->table} WHERE id = :id");
        $stmt->bindParam(':id', $id, PDO::PARAM_INT);
        return $stmt->execute();
    }
}
?>