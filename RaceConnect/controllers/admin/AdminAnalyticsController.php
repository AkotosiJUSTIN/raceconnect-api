<?php
//CHANGE THE PATHS TO YOUR OWN PATHS
require_once 'C:/xampp/htdocs/RaceConnect/models/admin/AdminAnalytics.php';

class AdminAnalyticsController {
    private $analytics;

    public function __construct($db) {
        $this->analytics = new AdminAnalytics($db);
    }

    public function processRequest($method, $id = null) {
        try {
            switch ($method) {
            case 'GET':
                if ($id) {
                $analytics = $this->analytics->getAnalyticsByDate($id); // Using date as id
                if ($analytics) {
                    echo json_encode($analytics);
                } else {
                    http_response_code(404);
                    echo json_encode(['message' => 'Analytics not found']);
                }
                } else {
                echo json_encode($this->analytics->getAllAnalytics());
                }
                break;

            case 'POST':
                $data = json_decode(file_get_contents("php://input"), true);
                if (json_last_error() !== JSON_ERROR_NONE) {
                http_response_code(400);
                echo json_encode(['message' => 'Invalid JSON data']);
                break;
                }
                if ($this->validateAnalyticsData($data)) {
                if ($this->analytics->createAnalytics($data)) {
                    http_response_code(201);
                    echo json_encode(['message' => 'Analytics created successfully']);
                } else {
                    http_response_code(500);
                    echo json_encode(['message' => 'Failed to create analytics']);
                }
                } else {
                http_response_code(400);
                echo json_encode(['message' => 'Invalid analytics data']);
                }
                break;

            case 'PUT':
                if ($id) {
                $data = json_decode(file_get_contents("php://input"), true);
                if (json_last_error() !== JSON_ERROR_NONE) {
                    http_response_code(400);
                    echo json_encode(['message' => 'Invalid JSON data']);
                    break;
                }
                if ($this->validateAnalyticsData($data)) {
                    if ($this->analytics->updateAnalytics($id, $data)) {
                    echo json_encode(['message' => 'Analytics updated successfully']);
                    } else {
                    http_response_code(500);
                    echo json_encode(['message' => 'Failed to update analytics']);
                    }
                } else {
                    http_response_code(400);
                    echo json_encode(['message' => 'Invalid analytics data']);
                }
                } else {
                http_response_code(400);
                echo json_encode(['message' => 'Analytics ID is required']);
                }
                break;

            case 'DELETE':
                if ($id) {
                if ($this->analytics->deleteAnalytics($id)) {
                    echo json_encode(['message' => 'Analytics deleted successfully']);
                } else {
                    http_response_code(500);
                    echo json_encode(['message' => 'Failed to delete analytics']);
                }
                } else {
                http_response_code(400);
                echo json_encode(['message' => 'Analytics ID is required']);
                }
                break;

            default:
                http_response_code(405);
                echo json_encode(['message' => 'Unsupported HTTP method']);
            }
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode(['message' => 'Internal Server Error', 'error' => $e->getMessage()]);
        }
    }

    public function validateAnalyticsData($data) {
        // Add your validation logic here
        return isset($data['requiredField']); // Example validation
    }
}
