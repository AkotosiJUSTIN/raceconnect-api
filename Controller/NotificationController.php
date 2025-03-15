<?php
namespace Controller;
use Model\Notification;
use Exception;

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../Model/Notification.php';

class NotificationController {
    private $notification;

    public function __construct($db) {
        $this->notification = new Notification($db);
    }

    public function processRequest($method, $id = null) {
        try {
            switch ($method) {
                case 'GET':
                    $this->handleGetRequest($id);
                    break;
                case 'POST':
                    $this->handlePostRequest();
                    break;
                case 'PUT':
                    $this->handlePutRequest($id);
                    break;
                case 'DELETE':
                    $this->handleDeleteRequest($id);
                    break;
                default:
                    http_response_code(405);
                    echo json_encode(['error' => 'Unsupported HTTP method']);
            }
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode(['error' => 'An unexpected error occurred', 'details' => $e->getMessage()]);
        }
    }

    private function handleGetRequest($id) {
        if ($id) {
            $notification = $this->notification->getNotificationById($id);
            if ($notification) {
                echo json_encode($notification);
            } else {
                http_response_code(404);
                echo json_encode(['error' => 'Notification not found']);
            }
        } else {
            $userId = $_GET['user_id'] ?? null;
            if ($userId) {
                echo json_encode($this->notification->getAllNotifications($userId));
            } else {
                http_response_code(400);
                echo json_encode(['error' => 'User ID is required']);
            }
        }
    }

    private function handlePostRequest() {
        $data = json_decode(file_get_contents("php://input"), true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            http_response_code(400);
            echo json_encode(['error' => 'Invalid JSON data']);
            return;
        }

        // Validate required fields
        if (!isset($data['user_id'], $data['type'], $data['content'], $data['trigger_user_id'])) {
            http_response_code(400);
            echo json_encode(['error' => 'Missing required fields: user_id, type, content, trigger_user_id']);
            return;
        }

        // Validate type
        $validTypes = ['like', 'comment', 'repost', 'post', 'marketplace', 'system', 'report'];
        if (!in_array($data['type'], $validTypes)) {
            http_response_code(400);
            echo json_encode(['error' => 'Invalid notification type']);
            return;
        }

        // Create notification
        $result = $this->notification->createNotification($data);
        if (isset($result['error'])) {
            http_response_code(500);
            echo json_encode(['error' => $result['error']]);
        } else {
            http_response_code(201);
            echo json_encode(['message' => 'Notification created successfully']);
        }
    }

    private function handlePutRequest($id) {
        if (!$id) {
            http_response_code(400);
            echo json_encode(['error' => 'Notification ID is required']);
            return;
        }

        if ($this->notification->markAsRead($id)) {
            echo json_encode(['message' => 'Notification marked as read']);
        } else {
            http_response_code(500);
            echo json_encode(['error' => 'Failed to mark notification as read']);
        }
    }

    private function handleDeleteRequest($id) {
        if (!$id) {
            http_response_code(400);
            echo json_encode(['error' => 'Notification ID is required']);
            return;
        }

        if ($this->notification->deleteNotification($id)) {
            echo json_encode(['message' => 'Notification deleted successfully']);
        } else {
            http_response_code(500);
            echo json_encode(['error' => 'Failed to delete notification']);
        }
    }
}
?>