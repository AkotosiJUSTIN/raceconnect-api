<?php

namespace Controller;

use Model\Friend;
use Exception;
use PDOException;

require_once __DIR__ . '/../vendor/autoload.php';

class FriendsController {
    private $friend;

    public function __construct($db) {
        $this->friend = new Friend($db);
    }

    public function processRequest($method, $action = null) {
        try {
            $data = json_decode(file_get_contents("php://input"), true);
            
            $userId = null;
            if ($method === 'GET') {
                $userId = $_GET['user_id'] ?? null;
            } elseif ($method === 'DELETE') {
                // For DELETE requests, get user_id from query parameters
                $userId = $_GET['user_id'] ?? null;
            } elseif (!isset($data['user_id']) || empty($data['user_id'])) {
                $this->sendErrorResponse(400, 'User ID is required.');
                return;
            } else {
                $userId = $data['user_id'];
            }

            if ($userId === null || empty($userId)) {
                $this->sendErrorResponse(400, 'User ID is required.');
                return;
            }

            switch ($method) {
                case 'GET':
                    if ($action === 'list') {
                        $this->getFriendsList($userId);
                    } elseif ($action === 'pending') {
                        $this->getPendingRequests($userId);
                    } elseif ($action === 'nonfriends') {
                        $this->getNonFriends($userId);
                    } else {
                        $this->sendErrorResponse(400, 'Invalid action.');
                    }
                    break;

                case 'POST':
                    ($action === 'add') ? $this->addFriend($userId, $data) : $this->sendErrorResponse(400, 'Invalid action.');
                    break;

                case 'PUT':
                    ($action === 'update') ? $this->updateFriendStatus($userId, $data) : $this->sendErrorResponse(400, 'Invalid action.');
                    break;

                case 'DELETE':
                    ($action === 'remove') ? $this->removeFriend($userId, $data) : $this->sendErrorResponse(400, 'Invalid action.');
                    break;

                default:
                    $this->sendErrorResponse(405, 'Unsupported HTTP method.');
            }
        } catch (PDOException $e) {
            $this->sendErrorResponse(500, 'Database error occurred.', $e);
        } catch (Exception $e) {
            $this->sendErrorResponse(500, 'An unexpected error occurred.', $e);
        }
    }

    private function getFriendsList($userId) {
        try {
            $friendsList = $this->friend->getFriendsList($userId);
            $this->sendSuccessResponse(200, $friendsList ?: []);
        } catch (Exception $e) {
            $this->sendErrorResponse(500, 'Failed to retrieve friends list.', $e);
        }
    }

    private function addFriend($userId, $data) {
        if (!isset($data['friend_id']) || empty($data['friend_id'])) {
            $this->sendErrorResponse(400, 'Friend ID is required.');
            return;
        }

        if ($userId === $data['friend_id']) {
            $this->sendErrorResponse(400, 'You cannot add yourself as a friend.');
            return;
        }

        $existingFriendship = $this->friend->getFriendship($userId, $data['friend_id']);
        if ($existingFriendship) {
            $this->sendErrorResponse(409, 'Friend request already exists.');
            return;
        }

        if ($this->friend->sendRequest($userId, $data['friend_id'])) {
            $this->sendSuccessResponse(200, 'Friend request sent successfully.');
        } else {
            $this->sendErrorResponse(500, 'Failed to send friend request.');
        }
    }

    private function removeFriend($userId, $data) {
        error_log("Remove friend request: user_id=$userId, data=" . print_r($data, true));
        $friendId = $data['friend_id'] ?? $_GET['friend_id'] ?? null;

        if (empty($friendId)) {
            $this->sendErrorResponse(400, 'Friend ID is required.');
            return;
        }

        $friendship = $this->friend->getFriendship($userId, $friendId);
        if (!$friendship) {
            $this->sendErrorResponse(404, 'Friend request not found.');
            return;
        }

        if ($this->friend->removeFriend($userId, $friendId)) {
            $this->sendSuccessResponse(200, 'Friend request removed successfully.');
        } else {
            $this->sendErrorResponse(500, 'Failed to remove friend request.');
        }
    }

    private function updateFriendStatus($userId, $data) {
        if (!isset($data['friend_id'], $data['status']) || empty($data['friend_id']) || empty($data['status'])) {
            $this->sendErrorResponse(400, 'Friend ID and status are required.');
            return;
        }
    
        $validStatuses = ['Accepted', 'Rejected', 'Blocked'];
        if (!in_array($data['status'], $validStatuses)) {
            $this->sendErrorResponse(400, 'Invalid status value.');
            return;
        }
    
        try {
            // Clean up friend_id and user_id
            $friendId = preg_replace('/\.0$/', '', (string)$data['friend_id']);
            $userId = preg_replace('/\.0$/', '', (string)$userId);
    
            error_log("updateFriendStatus: user_id=$userId (type=" . gettype($userId) . "), friend_id=$friendId (type=" . gettype($friendId) . "), status=" . $data['status']);
    
            // $userId is the receiver, $friendId is the sender
            $friendship = $this->friend->getFriendship($userId, $friendId);
    
            if (!$friendship) {
                $this->sendErrorResponse(404, 'No pending friend request found.');
                return;
            }
    
            error_log("Friendship data: " . print_r($friendship, true));
            error_log("friend_id from DB: " . $friendship['friend_id'] . " (type=" . gettype($friendship['friend_id']) . ")");
    
            if ($friendship['status'] !== 'Pending') {
                $this->sendErrorResponse(400, 'This request has already been processed.');
                return;
            }
    
            // Normalize both values for comparison
            $friendshipFriendId = (string)$friendship['friend_id'];
            $normalizedUserId = (string)$userId;
    
            error_log("Comparing: friend_id='$friendshipFriendId' (type=" . gettype($friendshipFriendId) . "), user_id='$normalizedUserId' (type=" . gettype($normalizedUserId) . ")");
            error_log("Hex comparison: friend_id=" . bin2hex($friendshipFriendId) . ", user_id=" . bin2hex($normalizedUserId));
    
            if ($friendshipFriendId !== $normalizedUserId) {
                error_log("User $normalizedUserId is not the receiver. Expected friend_id=$friendshipFriendId");
                $this->sendErrorResponse(403, 'Only the receiver can update this request.');
                return;
            }
    
            if ($this->friend->updateFriendStatus($userId, $friendId, $data['status'])) {
                $this->sendSuccessResponse(200, 'Friend status updated successfully.');
            } else {
                $this->sendErrorResponse(500, 'Failed to update friend status.');
            }
        } catch (PDOException $e) {
            $this->sendErrorResponse(500, 'Database error while updating friend status.', $e);
        }
    }

    private function sendSuccessResponse($statusCode, $data) {
        http_response_code($statusCode);
        echo json_encode(is_array($data) ? $data : ['message' => $data], JSON_UNESCAPED_UNICODE);
    }

    private function sendErrorResponse($statusCode, $message, $exception = null) {
        http_response_code($statusCode);
        $errorResponse = ['message' => $message];

        if ($exception) {
            $errorResponse['error'] = $exception->getMessage();
            error_log("Error: " . $exception->getMessage());
        }

        echo json_encode($errorResponse, JSON_UNESCAPED_UNICODE);
    }

    private function getPendingRequests($userId) {
        try {
            $pendingRequests = $this->friend->getPendingRequests($userId);
            $this->sendSuccessResponse(200, $pendingRequests ?: []);
        } catch (Exception $e) {
            $this->sendErrorResponse(500, 'Failed to retrieve pending friend requests.', $e);
        }
    }

    private function getNonFriends($userId) {
        try {
            $nonFriends = $this->friend->getNonFriends($userId);
            $this->sendSuccessResponse(200, $nonFriends ?: []);
        } catch (Exception $e) {
            $this->sendErrorResponse(500, 'Failed to retrieve non-friends.', $e);
        }
    }
}
?>