<?php
//CHANGE THE PATHS TO YOUR OWN PATHS
require_once 'C:/xampp/htdocs/RaceConnect/models/Notification.php';

class NotificationController {
    private $notification;

    public function __construct($db) {
        $this->notification = new Notification($db);
    }

    public function processRequest($method, $id = null) {
        try {
            switch ($method) {
                case 'GET':
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
                    break;

                case 'POST':
                    $data = json_decode(file_get_contents("php://input"), true);
                    if (json_last_error() !== JSON_ERROR_NONE) {
                        http_response_code(400);
                        echo json_encode(['error' => 'Invalid JSON data']);
                        break;
                    }
                    if ($this->notification->createNotification($data)) {
                        http_response_code(201);
                        echo json_encode(['message' => 'Notification created successfully']);
                    } else {
                        http_response_code(500);
                        echo json_encode(['error' => 'Failed to create notification']);
                    }
                    break;

                case 'PUT':
                    if ($id) {
                        if ($this->notification->markAsRead($id)) {
                            echo json_encode(['message' => 'Notification marked as read']);
                        } else {
                            http_response_code(500);
                            echo json_encode(['error' => 'Failed to mark notification as read']);
                        }
                    } else {
                        http_response_code(400);
                        echo json_encode(['error' => 'Notification ID is required']);
                    }
                    break;

                case 'DELETE':
                    if ($id) {
                        if ($this->notification->deleteNotification($id)) {
                            echo json_encode(['message' => 'Notification deleted successfully']);
                        } else {
                            http_response_code(500);
                            echo json_encode(['error' => 'Failed to delete notification']);
                        }
                    } else {
                        http_response_code(400);
                        echo json_encode(['error' => 'Notification ID is required']);
                    }
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
}
?>
