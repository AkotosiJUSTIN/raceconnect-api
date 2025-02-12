<?php
namespace Controller\Repost;

use Model\Repost\PostRepost;
use InvalidArgumentException;
use RuntimeException;
use Exception;

class PostRepostController {
    private $postRepost;

    public function __construct($db) {
        $this->postRepost = new PostRepost($db);
    }

    public function processRequest($method, $id = null, $queryParams = []) {
        try {
            switch ($method) {
                case 'GET':
                    if ($id) {
                        // Get reposts for a specific post
                        $reposts = $this->postRepost->getRepostsByPostId($id);
                        if (!empty($reposts)) {
                            echo json_encode($reposts);
                        } else {
                            http_response_code(404);
                            echo json_encode(['message' => 'Reposts not found for this post']);
                        }
                    } elseif (!empty($queryParams['user_id'])) {
                        // Get reposts by a specific user
                        $user_id = $queryParams['user_id'];
                        $reposts = $this->postRepost->getRepostsByUserId($user_id);
                        echo json_encode($reposts);
                    } elseif (!empty($queryParams['count']) && !empty($queryParams['post_id'])) {
                        // Get repost count for a post
                        $post_id = $queryParams['post_id'];
                        $count = $this->postRepost->getRepostCount($post_id);
                        echo json_encode(['post_id' => $post_id, 'repost_count' => $count['total_reposts']]);
                    } else {
                        // Get all reposts
                        $reposts = $this->postRepost->getAllReposts();
                        echo json_encode($reposts);
                    }
                    break;

                case 'POST':
                    $data = json_decode(file_get_contents("php://input"), true);
                    
                    if (empty($data) || !isset($data['user_id'], $data['post_id'], $data['owner_id'])) {
                        http_response_code(400);
                        echo json_encode(['message' => 'Invalid input data. Required: user_id, post_id, owner_id']);
                        return;
                    }

                    if ($this->postRepost->createRepost($data)) {
                        http_response_code(201);
                        echo json_encode(['message' => 'Repost added successfully']);
                    } else {
                        http_response_code(500);
                        echo json_encode(['message' => 'Failed to add repost']);
                    }
                    break;

                case 'DELETE':
                    if (!$id) {
                        http_response_code(400);
                        echo json_encode(['message' => 'Repost ID is required']);
                        return;
                    }

                    if ($this->postRepost->deleteRepost($id)) {
                        echo json_encode(['message' => 'Repost deleted successfully']);
                    } else {
                        http_response_code(500);
                        echo json_encode(['message' => 'Failed to delete repost']);
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
