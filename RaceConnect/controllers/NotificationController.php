<?php
//CHANGE THE PATHS TO YOUR OWN PATHS
require_once 'C:/xampp/htdocs/RaceConnect/models/Notification.php';

class NotificationController {
    private $notification;

    public function __construct($db) {
        $this->notification = new Notification($db);
    }

    public function processRequest($method, $id = null) {
        switch ($method) {
            case 'GET':
                if ($id) {
                    $notification = $this->notification->getNotificationById($id);
                    echo json_encode($notification ? ['data' => $notification] : ['message' => 'Notification not found']);
                } else {
                    $userId = $_GET['user_id'] ?? null;
                    if ($userId) {
                        echo json_encode(['data' => $this->notification->getAllNotifications($userId)]);
                    } else {
                        echo json_encode(['message' => 'User ID is required']);
                    }
                }
                break;

            case 'POST':
                $data = json_decode(file_get_contents("php://input"), true);
                if ($this->notification->createNotification($data)) {
                    echo json_encode(['message' => 'Notification created successfully']);
                } else {
                    echo json_encode(['message' => 'Failed to create notification']);
                }
                break;

            case 'PUT':
                if ($id) {
                    if ($this->notification->markAsRead($id)) {
                        echo json_encode(['message' => 'Notification marked as read']);
                    } else {
                        echo json_encode(['message' => 'Failed to mark notification as read']);
                    }
                } else {
                    echo json_encode(['message' => 'Notification ID is required']);
                }
                break;

            case 'DELETE':
                if ($id) {
                    if ($this->notification->deleteNotification($id)) {
                        echo json_encode(['message' => 'Notification deleted successfully']);
                    } else {
                        echo json_encode(['message' => 'Failed to delete notification']);
                    }
                } else {
                    echo json_encode(['message' => 'Notification ID is required']);
                }
                break;

            default:
                echo json_encode(['message' => 'Unsupported HTTP method']);
        }
    }
}
?>
