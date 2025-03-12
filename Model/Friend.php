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
            $stmt = $this->pdo->prepare("
                SELECT u.id, u.username 
                FROM {$this->table} f 
                JOIN Users u ON 
                    (f.friend_id = u.id AND f.user_id = :user_id) 
                    OR (f.user_id = u.id AND f.friend_id = :user_id)
                WHERE f.status = 'Accepted' AND u.id != :user_id
            ");
            $stmt->execute([':user_id' => $userId]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            error_log("Error fetching friends list: " . $e->getMessage());
            return [];
        }
    }

    public function getFriendship($userId, $friendId) {
        try {
            $userId = preg_replace('/\.0$/', '', (string)$userId);
            $friendId = preg_replace('/\.0$/', '', (string)$friendId);
    
            $stmt = $this->pdo->prepare("SELECT * FROM {$this->table} WHERE (user_id = :user_id AND friend_id = :friend_id) OR (user_id = :friend_id AND friend_id = :user_id)");
            $stmt->execute([':user_id' => $userId, ':friend_id' => $friendId]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            error_log("getFriendship result for user_id=$userId, friend_id=$friendId: " . print_r($result, true));
            return $result;
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

    public function getPendingRequests($userId) {
        try {
            // Fetch incoming pending requests (where userId is the receiver)
            $stmt = $this->pdo->prepare("
                SELECT u.id, u.username 
                FROM {$this->table} f 
                JOIN Users u ON f.user_id = u.id 
                WHERE f.friend_id = :user_id AND f.status = 'Pending'
            ");
            $stmt->execute([':user_id' => $userId]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            error_log("Error fetching pending friend requests: " . $e->getMessage());
            return [];
        }
    }

    public function getNonFriends($userId) {
        try {
            $stmt = $this->pdo->prepare("
                SELECT u.id, u.username 
                FROM Users u
                WHERE u.id != :user_id
                AND u.id NOT IN (
                    SELECT friend_id FROM {$this->table} WHERE user_id = :user_id
                    UNION
                    SELECT user_id FROM {$this->table} WHERE friend_id = :user_id
                )
            ");
            $stmt->execute([':user_id' => $userId]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            error_log("Error fetching non-friends: " . $e->getMessage());
            return [];
        }
    }

    public function getFriendsList($userId) {
        try {
            // Fetch incoming pending requests (where userId is the receiver)
            $pendingStmt = $this->pdo->prepare("
                SELECT 
                    u.id AS friend_id, 
                    u.username, 
                    'Pending' AS status, 
                    u.profile_picture
                FROM {$this->table} f
                JOIN Users u ON f.user_id = u.id
                WHERE f.friend_id = :user_id AND f.status = 'Pending'
            ");
            $pendingStmt->execute([':user_id' => $userId]);
            $pendingRequests = $pendingStmt->fetchAll(PDO::FETCH_ASSOC);
    
            // Fetch non-friends and outgoing pending requests (suggestions)
            $suggestionsStmt = $this->pdo->prepare("
                SELECT 
                    u.id AS friend_id, 
                    u.username, 
                    CASE 
                        WHEN f.status = 'Pending' AND f.user_id = :user_id THEN 'PendingSent'
                        ELSE 'NonFriends'
                    END AS status,
                    u.profile_picture
                FROM Users u
                LEFT JOIN {$this->table} f 
                    ON (u.id = f.friend_id AND f.user_id = :user_id) 
                    OR (u.id = f.user_id AND f.friend_id = :user_id)
                WHERE u.id != :user_id
                AND (f.status IS NULL OR f.status = 'Pending')
                AND u.id NOT IN (
                    SELECT friend_id FROM {$this->table} WHERE user_id = :user_id AND status = 'Accepted'
                    UNION
                    SELECT user_id FROM {$this->table} WHERE friend_id = :user_id AND status = 'Accepted'
                )
            ");
            $suggestionsStmt->execute([':user_id' => $userId]);
            $suggestions = $suggestionsStmt->fetchAll(PDO::FETCH_ASSOC);
    
            // Combine results
            return array_merge($pendingRequests, $suggestions);
        } catch (Exception $e) {
            error_log("Error fetching friends list: " . $e->getMessage());
            return [];
        }
    }
}
?>