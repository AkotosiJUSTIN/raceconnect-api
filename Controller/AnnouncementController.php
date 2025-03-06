<?php

namespace Controller;
use Model\Announcement;
use Exception;

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../Model/Announcement.php';

class AnnouncementController {
    private $announcement;

    public function __construct($db) {
        $this->announcement = new Announcement($db);
    }

    public function processRequest($method, $id = null, $action = null) {
        try {
            $data = json_decode(file_get_contents("php://input"), true) ?? [];
    
            switch ($method) {
                case 'GET':
                    $this->handleGetRequest($id);
                    break;
                case 'POST':
                    $this->handlePostRequest($data);
                    break;
                case 'PUT':
                    $this->handlePutRequest($id, $data);
                    break;
                case 'DELETE':
                    $this->handleDeleteRequest($id);
                    break;
                default:
                    http_response_code(405);
                    echo json_encode(['message' => 'Unsupported HTTP method']);
            }
        } catch (Exception $e) {
            error_log("Server error: " . $e->getMessage());
            http_response_code(500);
            echo json_encode(['message' => 'Internal Server Error', 'error' => $e->getMessage()]);
        }
    }

    private function handleGetRequest($id) {
        try {
            if ($id) {
                $announcement = $this->announcement->getAnnouncementById($id);
                if (!$announcement) {
                    http_response_code(404);
                    echo json_encode(['message' => 'Announcement not found']);
                    return;
                }
                http_response_code(200);
                echo json_encode($announcement);
            } else {
                $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 10;
                $offset = isset($_GET['offset']) ? (int)$_GET['offset'] : 0;
                
                if ($limit <= 0 || $offset < 0) {
                    http_response_code(400);
                    echo json_encode(['message' => 'Invalid limit or offset values']);
                    return;
                }
                
                $announcements = $this->announcement->getAllAnnouncements($limit, $offset);
                http_response_code(200);
                echo json_encode($announcements);
            }
        } catch (Exception $e) {
            error_log("GET request failed: " . $e->getMessage());
            http_response_code(500);
            echo json_encode(['message' => 'Failed to retrieve announcements', 'error' => $e->getMessage()]);
        }
    }

    private function handlePostRequest($data) {
        try {
            if (!$this->validateAnnouncementData($data, true)) {
                http_response_code(400);
                echo json_encode(['message' => 'Invalid input data']);
                return;
            }
            
            $announcementId = $this->announcement->createAnnouncement($data);
            if (!$announcementId || $announcementId == 0) {
                throw new Exception('Failed to create announcement.');
            }
            
            http_response_code(201);
            echo json_encode(['message' => 'Announcement created successfully', 'announcement_id' => $announcementId]);
        } catch (Exception $e) {
            error_log("POST request failed: " . $e->getMessage());
            http_response_code(500);
            echo json_encode(['message' => 'Failed to create announcement', 'error' => $e->getMessage()]);
        }
    }

    private function handlePutRequest($id, $data) {
        try {
            if (!$id) {
                http_response_code(400);
                echo json_encode(['message' => 'Announcement ID is required']);
                return;
            }
            
            if (!$this->validateAnnouncementData($data, false)) {
                http_response_code(400);
                echo json_encode(['message' => 'Invalid input data for update']);
                return;
            }
            
            if (!$this->announcement->updateAnnouncement($id, $data)) {
                throw new Exception("Failed to update announcement.");
            }
            
            http_response_code(200);
            echo json_encode(['message' => 'Announcement updated successfully']);
        } catch (Exception $e) {
            error_log("PUT request failed: " . $e->getMessage());
            http_response_code(500);
            echo json_encode(['message' => 'Failed to update announcement', 'error' => $e->getMessage()]);
        }
    }

    private function handleDeleteRequest($id) {
        try {
            if (!$id) {
                http_response_code(400);
                echo json_encode(['message' => 'Announcement ID is required']);
                return;
            }
            
            if (!$this->announcement->deleteAnnouncement($id)) {
                throw new Exception("Failed to delete announcement.");
            }
            
            http_response_code(200);
            echo json_encode(['message' => 'Announcement deleted successfully']);
        } catch (Exception $e) {
            error_log("DELETE request failed: " . $e->getMessage());
            http_response_code(500);
            echo json_encode(['message' => 'Failed to delete announcement', 'error' => $e->getMessage()]);
        }
    }

    private function validateAnnouncementData($data, $isNew = true) {
        if ($isNew && (!isset($data['title'], $data['content'], $data['status']))) {
            return false;
        }
        return !empty($data['title']) && !empty($data['content']) && !empty($data['status']);
    }
}
?>