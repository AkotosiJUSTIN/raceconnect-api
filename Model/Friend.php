<?php

namespace Model;

use PDO;
use Exception;

require_once __DIR__ . '/../vendor/autoload.php';

class Friend {
    private $pdo;
    private $table = "Friends";

    public function __construct($db) {
        $this->pdo = $db;
    }

    public function sendRequest($userId, $friendId) {
        try {
            $stmt = $this->pdo->prepare("INSERT INTO {$this->table} (user_id, friend_id, status) VALUES (:user_id, :friend_id, 'Pending')");
            return $stmt->execute([':user_id' => $userId, ':friend_id' => $friendId]);
        } catch (Exception $e) {
            error_log("Error sending friend request: " . $e->getMessage());
            return false;
        }
    }

    public function acceptRequest($userId, $friendId) {
        try {
            $stmt = $this->pdo->prepare("UPDATE {$this->table} SET status = 'Accepted' WHERE (user_id = :friend_id AND friend_id = :user_id) AND status = 'Pending'");
            $stmt->execute([':user_id' => $userId, ':friend_id' => $friendId]);
            return $stmt->rowCount() > 0;
        } catch (Exception $e) {
            error_log("Error accepting friend request: " . $e->getMessage());
            return false;
        }
    }

    public function blockFriend($userId, $friendId) {
        try {
            $stmt = $this->pdo->prepare("UPDATE {$this->table} SET status = 'Blocked' WHERE user_id = :user_id AND friend_id = :friend_id");
            return $stmt->execute([':user_id' => $userId, ':friend_id' => $friendId]);
        } catch (Exception $e) {
            error_log("Error blocking friend: " . $e->getMessage());
            return false;
        }
    }

    public function removeFriend($userId, $friendId) {
        try {
            $stmt = $this->pdo->prepare("DELETE FROM {$this->table} WHERE (user_id = :user_id AND friend_id = :friend_id) OR (user_id = :friend_id AND friend_id = :user_id)");
            return $stmt->execute([':user_id' => $userId, ':friend_id' => $friendId]);
        } catch (Exception $e) {
            error_log("Error removing friend: " . $e->getMessage());
            return false;
        }
    }

    public function getFriends($userId) {
        try {
            $stmt = $this->pdo->prepare("SELECT u.id, u.username, f.status FROM {$this->table} f JOIN Users u ON (f.friend_id = u.id OR f.user_id = u.id) WHERE (f.user_id = :user_id OR f.friend_id = :user_id) AND f.status = 'Accepted'");
            $stmt->execute([':user_id' => $userId]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            error_log("Error fetching friends list: " . $e->getMessage());
            return [];
        }
    }

    public function getFriendship($userId, $friendId) {
        try {
            $stmt = $this->pdo->prepare("SELECT * FROM {$this->table} WHERE (user_id = :user_id AND friend_id = :friend_id) OR (user_id = :friend_id AND friend_id = :user_id)");
            $stmt->execute([':user_id' => $userId, ':friend_id' => $friendId]);
            return $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            error_log("Error fetching friendship: " . $e->getMessage());
            return null;
        }
    }

    public function updateFriendStatus($userId, $friendId, $status) {
        try {
            $stmt = $this->pdo->prepare("UPDATE {$this->table} SET status = :status WHERE (user_id = :friend_id AND friend_id = :user_id) AND status = 'Pending'");
            return $stmt->execute([':status' => $status, ':user_id' => $userId, ':friend_id' => $friendId]);
        } catch (Exception $e) {
            error_log("Error updating friend status: " . $e->getMessage());
            return false;
        }
    }
}
?>
