<?php
//CHANGE THE PATHS TO YOUR OWN PATHS
require_once 'C:/xampp/htdocs/RaceConnect/models/Reels.php';

class ReelController {
    private $reel;

    public function __construct($db) {
        $this->reel = new Reel($db);
    }

    public function processRequest($method, $id = null) {
        try {
            switch ($method) {
            case 'GET':
                if ($id) {
                $reel = $this->reel->getReelById($id);
                if ($reel) {
                    echo json_encode($reel);
                } else {
                    http_response_code(404);
                    echo json_encode(['message' => 'Reel not found']);
                }
                } else {
                echo json_encode($this->reel->getAllReels());
                }
                break;

            case 'POST':
                $data = json_decode(file_get_contents("php://input"), true);
                if (json_last_error() !== JSON_ERROR_NONE) {
                http_response_code(400);
                echo json_encode(['message' => 'Invalid JSON']);
                break;
                }
                if ($this->reel->createReel($data)) {
                http_response_code(201);
                echo json_encode(['message' => 'Reel created successfully']);
                } else {
                http_response_code(500);
                echo json_encode(['message' => 'Failed to create reel']);
                }
                break;

            case 'PUT':
                if ($id) {
                $data = json_decode(file_get_contents("php://input"), true);
                if (json_last_error() !== JSON_ERROR_NONE) {
                    http_response_code(400);
                    echo json_encode(['message' => 'Invalid JSON']);
                    break;
                }
                if ($this->reel->updateReel($id, $data)) {
                    echo json_encode(['message' => 'Reel updated successfully']);
                } else {
                    http_response_code(500);
                    echo json_encode(['message' => 'Failed to update reel']);
                }
                } else {
                http_response_code(400);
                echo json_encode(['message' => 'Reel ID is required']);
                }
                break;

            case 'DELETE':
                if ($id) {
                if ($this->reel->deleteReel($id)) {
                    echo json_encode(['message' => 'Reel deleted successfully']);
                } else {
                    http_response_code(500);
                    echo json_encode(['message' => 'Failed to delete reel']);
                }
                } else {
                http_response_code(400);
                echo json_encode(['message' => 'Reel ID is required']);
                }
                break;

            default:
                http_response_code(405);
                echo json_encode(['message' => 'Unsupported HTTP method']);
            }
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode(['message' => 'An error occurred', 'error' => $e->getMessage()]);
        }
    }
}
