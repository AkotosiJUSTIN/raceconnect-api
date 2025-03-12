<?php

namespace Model;
use PDO;

class Report {
    private $pdo;
    private $table = 'Reports';

    public function __construct($db) {
        $this->pdo = $db;
    }

    public function getAllReports($limit = 10, $offset = 0) {
        $stmt = $this->pdo->prepare("SELECT * FROM {$this->table} LIMIT :limit OFFSET :offset");
        $stmt->bindParam(':limit', $limit, PDO::PARAM_INT);
        $stmt->bindParam(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getReportById($id) {
        $stmt = $this->pdo->prepare("SELECT * FROM {$this->table} WHERE id = :id");
        $stmt->bindParam(':id', $id, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function createReport($data) {
        $stmt = $this->pdo->prepare("INSERT INTO {$this->table} (post_id, marketplace_item_id, reporter_id, reason, status) 
                                    VALUES (:post_id, :marketplace_item_id, :reporter_id, :reason, :status)");
        $result = $stmt->execute([
            ':post_id' => isset($data['post_id']) ? (int)$data['post_id'] : null,
            ':marketplace_item_id' => isset($data['marketplace_item_id']) ? (int)$data['marketplace_item_id'] : null,
            ':reporter_id' => (int)$data['reporter_id'],
            ':reason' => htmlspecialchars($data['reason'], ENT_QUOTES, 'UTF-8'),
            ':status' => isset($data['status']) ? htmlspecialchars($data['status'], ENT_QUOTES, 'UTF-8') : 'pending'
        ]);

        if ($result) {
            return $this->pdo->lastInsertId();
        }
        return false;
    }

    public function updateReport($id, $data) {
        $fields = [];
        $params = [':id' => (int)$id];

        if (isset($data['post_id'])) {
            $fields[] = "post_id = :post_id";
            $params[':post_id'] = (int)$data['post_id'];
        }
        if (isset($data['marketplace_item_id'])) {
            $fields[] = "marketplace_item_id = :marketplace_item_id";
            $params[':marketplace_item_id'] = (int)$data['marketplace_item_id'];
        }
        if (!empty($data['reason'])) {
            $fields[] = "reason = :reason";
            $params[':reason'] = htmlspecialchars($data['reason'], ENT_QUOTES, 'UTF-8');
        }
        if (!empty($data['status'])) {
            $fields[] = "status = :status";
            $params[':status'] = htmlspecialchars($data['status'], ENT_QUOTES, 'UTF-8');
        }
        if (isset($data['resolved_by'])) {
            $fields[] = "resolved_by = :resolved_by";
            $params[':resolved_by'] = (int)$data['resolved_by'];
        }
        if (isset($data['resolved_at'])) {
            $fields[] = "resolved_at = :resolved_at";
            $params[':resolved_at'] = $data['resolved_at'];
        }

        if (empty($fields)) {
            return false;
        }

        $stmt = $this->pdo->prepare("UPDATE {$this->table} SET " . implode(", ", $fields) . " WHERE id = :id");
        return $stmt->execute($params);
    }

    public function updatePostReportStatus($postId) {
        $stmt = $this->pdo->prepare("UPDATE posts SET report = 'reported' WHERE id = :post_id");
        $stmt->bindParam(':post_id', $postId, PDO::PARAM_INT);
        return $stmt->execute();
    }

    public function deleteReport($id) {
        $stmt = $this->pdo->prepare("DELETE FROM {$this->table} WHERE id = :id");
        $stmt->bindParam(':id', $id, PDO::PARAM_INT);
        return $stmt->execute();
    }
}
?>