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

            if (!isset($data['user_id']) || empty($data['user_id'])) {
                $this->sendErrorResponse(400, 'User ID is required.');
                return;
            }

            $userId = $data['user_id'];

            switch ($method) {
                case 'GET':
                    ($action === 'list') ? $this->getFriendsList($userId) : $this->sendErrorResponse(400, 'Invalid action.');
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
            $friendsList = $this->friend->getFriends($userId);
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

        // Check if a friend request already exists in either direction
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
        if (!isset($data['friend_id']) || empty($data['friend_id'])) {
            $this->sendErrorResponse(400, 'Friend ID is required.');
            return;
        }

        // Check if the user is either the sender or the receiver of the friend request
        $friendship = $this->friend->getFriendship($userId, $data['friend_id']);
        if (!$friendship) {
            $this->sendErrorResponse(404, 'Friend request not found.');
            return;
        }

        if ($this->friend->removeFriend($userId, $data['friend_id'])) {
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
            // Ensure the request exists and the current user is the receiver
            $friendship = $this->friend->getFriendship($data['friend_id'], $userId); // Check if `friend_id` (sender) sent a request to `userId` (receiver)

            if (!$friendship) {
                $this->sendErrorResponse(404, 'No pending friend request found.');
                return;
            }

            if ($friendship['status'] !== 'Pending') {
                $this->sendErrorResponse(400, 'This request has already been processed.');
                return;
            }

            // Ensure only the receiver (user_id) can update the request
            if ($friendship['friend_id'] !== $userId) {
                $this->sendErrorResponse(403, 'Only the receiver can update this request.');
                return;
            }

            // Update friend request status
            if ($this->friend->updateFriendStatus($userId, $data['friend_id'], $data['status'])) {
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
}
?>