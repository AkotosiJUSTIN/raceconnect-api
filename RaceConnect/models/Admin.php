<?php
class Admin {
    private $pdo;
    private $table = "Admins";

    public function __construct($db) {
        $this->pdo = $db;
    }

    // Get all admins
    public function getAllAdmins() {
        $stmt = $this->pdo->query("SELECT * FROM {$this->table}");
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // Get an admin by ID
    public function getAdminById($id) {
        $stmt = $this->pdo->prepare("SELECT * FROM {$this->table} WHERE id = :id");
        $stmt->bindParam(':id', $id);
        $stmt->execute();
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    // Create a new admin
    public function createAdmin($data) {
        $stmt = $this->pdo->prepare("INSERT INTO {$this->table} (user_id, role) VALUES (:user_id, :role)");
        return $stmt->execute([
            ':user_id' => $data['user_id'],
            ':role' => $data['role']
        ]);
    }

    // Update an admin's role
    public function updateAdminRole($id, $role) {
        $stmt = $this->pdo->prepare("UPDATE {$this->table} SET role = :role WHERE id = :id");
        $stmt->bindParam(':id', $id);
        $stmt->bindParam(':role', $role);
        return $stmt->execute();
    }

    // Delete an admin
    public function deleteAdmin($id) {
        $stmt = $this->pdo->prepare("DELETE FROM {$this->table} WHERE id = :id");
        $stmt->bindParam(':id', $id);
        return $stmt->execute();
    }
}
?>
