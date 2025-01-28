<?php
require_once 'C:/xampp/htdocs/RaceConnect/models/like/PostLike.php';

class PostLikeController {
    private $postLike;

    public function __construct($db) {
        $this->postLike = new PostLike($db);
    }

    public function processRequest($method, $id = null) {
        try {
            switch ($method) {
                case 'GET':
                    if ($id) {
                        $likes = $this->postLike->getLikesByPostId($id);
                        if ($likes !== false) {
                            echo json_encode($likes);
                        } else {
                            http_response_code(404);
                            echo json_encode(['message' => 'Likes not found for the given post ID']);
                        }
                    } else {
                        $likes = $this->postLike->getAllLikes();
                        if ($likes !== false) {
                            echo json_encode($likes);
                        } else {
                            http_response_code(500);
                            echo json_encode(['message' => 'Failed to retrieve likes']);
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
                    if ($this->postLike->createLike($data)) {
                        http_response_code(201);
                        echo json_encode(['message' => 'Like added successfully']);
                    } else {
                        http_response_code(500);
                        echo json_encode(['message' => 'Failed to add like']);
                    }
                    break;

                case 'DELETE':
                    if ($id) {
                        if ($this->postLike->deleteLike($id)) {
                            echo json_encode(['message' => 'Like deleted successfully']);
                        } else {
                            http_response_code(500);
                            echo json_encode(['message' => 'Failed to delete like']);
                        }
                    } else {
                        http_response_code(400);
                        echo json_encode(['message' => 'Like ID is required']);
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
?>
