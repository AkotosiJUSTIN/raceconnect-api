<?php
class AdminAnalytics {
    private $pdo;
    private $table = "Admin_Analytics";

    public function __construct($db) {
        $this->pdo = $db;
    }

    // Get all admin analytics
    public function getAllAnalytics() {
        $stmt = $this->pdo->query("SELECT * FROM {$this->table}");
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // Get analytics by date
    public function getAnalyticsByDate($report_date) {
        $stmt = $this->pdo->prepare("SELECT * FROM {$this->table} WHERE report_date = :report_date");
        $stmt->bindParam(':report_date', $report_date);
        $stmt->execute();
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    // Create new analytics entry
    public function createAnalytics($data) {
        $stmt = $this->pdo->prepare("INSERT INTO {$this->table} (total_users, total_posts, total_reels, total_marketplace_items, report_date) VALUES (:total_users, :total_posts, :total_reels, :total_marketplace_items, :report_date)");
        return $stmt->execute([
            ':total_users' => $data['total_users'],
            ':total_posts' => $data['total_posts'],
            ':total_reels' => $data['total_reels'],
            ':total_marketplace_items' => $data['total_marketplace_items'],
            ':report_date' => $data['report_date']
        ]);
    }

    // Update an existing analytics entry
    public function updateAnalytics($id, $data) {
        $stmt = $this->pdo->prepare("UPDATE {$this->table} SET total_users = :total_users, total_posts = :total_posts, total_reels = :total_reels, total_marketplace_items = :total_marketplace_items, report_date = :report_date WHERE id = :id");
        $stmt->bindParam(':id', $id);
        return $stmt->execute([
            ':total_users' => $data['total_users'],
            ':total_posts' => $data['total_posts'],
            ':total_reels' => $data['total_reels'],
            ':total_marketplace_items' => $data['total_marketplace_items'],
            ':report_date' => $data['report_date']
        ]);
    }

    // Delete analytics by ID
    public function deleteAnalytics($id) {
        $stmt = $this->pdo->prepare("DELETE FROM {$this->table} WHERE id = :id");
        $stmt->bindParam(':id', $id);
        return $stmt->execute();
    }
}
?>
