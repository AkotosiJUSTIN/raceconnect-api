<?php
//CHANGE THE PATHS TO YOUR OWN PATHS
require_once 'C:/xampp/htdocs/RaceConnect/models/Reels.php';

class ReelController {
    private $reel;

    public function __construct($db) {
        $this->reel = new Reel($db);
    }

    public function processRequest($method, $id = null) {
        switch ($method) {
            case 'GET':
                if ($id) {
                    $reel = $this->reel->getReelById($id);
                    echo json_encode($reel ? ['data' => $reel] : ['message' => 'Reel not found']);
                } else {
                    echo json_encode(['data' => $this->reel->getAllReels()]);
                }
                break;

            case 'POST':
                $data = json_decode(file_get_contents("php://input"), true);
                if ($this->reel->createReel($data)) {
                    echo json_encode(['message' => 'Reel created successfully']);
                } else {
                    echo json_encode(['message' => 'Failed to create reel']);
                }
                break;

            case 'PUT':
                if ($id) {
                    $data = json_decode(file_get_contents("php://input"), true);
                    if ($this->reel->updateReel($id, $data)) {
                        echo json_encode(['message' => 'Reel updated successfully']);
                    } else {
                        echo json_encode(['message' => 'Failed to update reel']);
                    }
                } else {
                    echo json_encode(['message' => 'Reel ID is required']);
                }
                break;

            case 'DELETE':
                if ($id) {
                    if ($this->reel->deleteReel($id)) {
                        echo json_encode(['message' => 'Reel deleted successfully']);
                    } else {
                        echo json_encode(['message' => 'Failed to delete reel']);
                    }
                } else {
                    echo json_encode(['message' => 'Reel ID is required']);
                }
                break;

            default:
                echo json_encode(['message' => 'Unsupported HTTP method']);
        }
    }
}
?>
