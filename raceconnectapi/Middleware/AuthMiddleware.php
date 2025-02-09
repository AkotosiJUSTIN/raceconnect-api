<?php

namespace Middleware;
use PDO;

class AuthMiddleware {
    private $pdo;

    public function __construct($db) {
        $this->pdo = $db;
    }

    public function storeToken($user_id, $token) {
        $stmt = $this->pdo->prepare("INSERT INTO user_tokens (user_id, token) VALUES (:user_id, :token)");
        return $stmt->execute([
            ':user_id' => $user_id,
            ':token' => $token
        ]);
    }

    public function validateToken($token) {
        $stmt = $this->pdo->prepare("SELECT * FROM user_tokens WHERE token = :token");
        $stmt->bindParam(':token', $token);
        $stmt->execute();
        return $stmt->fetch(PDO::FETCH_ASSOC); // Return token data if valid, else null
    }

    public function revokeToken($token) {
        $stmt = $this->pdo->prepare("DELETE FROM user_tokens WHERE token = :token");
        $stmt->bindParam(':token', $token);
        return $stmt->execute();
    }
}
?>