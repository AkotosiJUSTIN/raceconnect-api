<?php
//CHANGE THE PATHS TO YOUR OWN PATHS
require_once 'C:/xampp/htdocs/RaceConnect/models/AdminAnalytics.php';

class AdminAnalyticsController {
    private $analytics;

    public function __construct($db) {
        $this->analytics = new AdminAnalytics($db);
    }

    public function processRequest($method, $id = null) {
        switch ($method) {
            case 'GET':
                if ($id) {
                    $analytics = $this->analytics->getAnalyticsByDate($id); // Using date as id
                    echo json_encode($analytics ? ['success' => true, 'data' => $analytics] : ['success' => false, 'message' => 'Analytics not found']);
                } else {
                    echo json_encode(['success' => true, 'data' => $this->analytics->getAllAnalytics()]);
                }
                break;

            case 'POST':
                $data = json_decode(file_get_contents("php://input"), true);
                if ($this->analytics->createAnalytics($data)) {
                    echo json_encode(['success' => true, 'message' => 'Analytics created successfully']);
                } else {
                    echo json_encode(['success' => false, 'message' => 'Failed to create analytics']);
                }
                break;

            case 'PUT':
                if ($id) {
                    $data = json_decode(file_get_contents("php://input"), true);
                    if ($this->analytics->updateAnalytics($id, $data)) {
                        echo json_encode(['success' => true, 'message' => 'Analytics updated successfully']);
                    } else {
                        echo json_encode(['success' => false, 'message' => 'Failed to update analytics']);
                    }
                } else {
                    echo json_encode(['success' => false, 'message' => 'Analytics ID is required']);
                }
                break;

            case 'DELETE':
                if ($id) {
                    if ($this->analytics->deleteAnalytics($id)) {
                        echo json_encode(['success' => true, 'message' => 'Analytics deleted successfully']);
                    } else {
                        echo json_encode(['success' => false, 'message' => 'Failed to delete analytics']);
                    }
                } else {
                    echo json_encode(['success' => false, 'message' => 'Analytics ID is required']);
                }
                break;

            default:
                echo json_encode(['success' => false, 'message' => 'Unsupported HTTP method']);
        }
    }
}
?>
