<?php
require_once 'C:/xampp/htdocs/RaceConnect/models/repost/ReelsRepost.php';

class ReelRepostController {
    private $reelRepost;

    public function __construct($db) {
        $this->reelRepost = new ReelRepost($db);
    }

    public function processRequest($method, $id = null) {
        try {
            switch ($method) {
                case 'GET':
                    if ($id) {
                        $reposts = $this->reelRepost->getRepostsByReelId($id);
                        if ($reposts) {
                            echo json_encode($reposts);
                        } else {
                            http_response_code(404);
                            echo json_encode(['message' => 'Reposts not found']);
                        }
                    } else {
                        $reposts = $this->reelRepost->getAllReposts();
                        if ($reposts) {
                            echo json_encode($reposts);
                        } else {
                            http_response_code(404);
                            echo json_encode(['message' => 'No reposts found']);
                        }
                    }
                    break;

                case 'POST':
                    $data = json_decode(file_get_contents("php://input"), true);
                    if (empty($data)) {
                        http_response_code(400);
                        echo json_encode(['message' => 'Invalid input data']);
                        return;
                    }
                    if ($this->reelRepost->createRepost($data)) {
                        http_response_code(201);
                        echo json_encode(['message' => 'Repost added successfully']);
                    } else {
                        http_response_code(500);
                        echo json_encode(['message' => 'Failed to add repost']);
                    }
                    break;

                case 'DELETE':
                    if ($id) {
                        if ($this->reelRepost->deleteRepost($id)) {
                            echo json_encode(['message' => 'Repost deleted successfully']);
                        } else {
                            http_response_code(500);
                            echo json_encode(['message' => 'Failed to delete repost']);
                        }
                    } else {
                        http_response_code(400);
                        echo json_encode(['message' => 'Repost ID is required']);
                    }
                    break;

                default:
                    http_response_code(405);
                    echo json_encode(['message' => 'Unsupported HTTP method']);
            }
        } catch (InvalidArgumentException $e) {
            http_response_code(400);
            echo json_encode(['message' => 'Invalid argument', 'error' => $e->getMessage()]);
        } catch (RuntimeException $e) {
            http_response_code(500);
            echo json_encode(['message' => 'Runtime error', 'error' => $e->getMessage()]);
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode(['message' => 'An error occurred', 'error' => $e->getMessage()]);
        }
    }
}
?>
