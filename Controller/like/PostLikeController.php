<?php
namespace Controller\Like;
use Model\Like\PostLike;
use Exception;

require_once __DIR__ . '/../../vendor/autoload.php';
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
                        // If "count" parameter exists, return like count instead of likes
                        if (isset($_GET['count'])) {
                            $count = $this->postLike->getLikeCount($id);
                            echo json_encode($count);
                        } else {
                            $likes = $this->postLike->getLikesByPostId($id);
                            if ($likes) {
                                echo json_encode($likes);
                            } else {
                                http_response_code(404);
                                echo json_encode(['message' => 'No likes found for the given post ID']);
                            }
                        }
                    } else {
                        $likes = $this->postLike->getAllLikes();
                        echo json_encode($likes);
                    }
                    break;

                case 'POST':
                    $data = json_decode(file_get_contents("php://input"), true);
                    
                    if (empty($data['user_id']) || empty($data['post_id'])) {
                        http_response_code(400);
                        echo json_encode(['message' => 'Missing required fields (user_id, post_id)']);
                        return;
                    }

                    $result = $this->postLike->createLike($data);
                    if (is_array($result) && isset($result['error'])) {
                        http_response_code(409);
                        echo json_encode($result);
                    } elseif ($result) {
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
                    } elseif (isset($_GET['post_id'])) {
                        $postId = $_GET['post_id'];
                        if ($this->postLike->deleteLikesByPostId($postId)) {
                            echo json_encode(['message' => 'All likes for the post deleted successfully']);
                        } else {
                            http_response_code(500);
                            echo json_encode(['message' => 'Failed to delete likes for the post']);
                        }
                    } else {
                        http_response_code(400);
                        echo json_encode(['message' => 'Like ID or post_id is required']);
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
