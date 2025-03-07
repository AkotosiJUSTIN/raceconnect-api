<?php
namespace Controller\Repost;

use Model\Repost\PostRepost;
use InvalidArgumentException;
use RuntimeException;
use Exception;

require_once __DIR__ . '/../../vendor/autoload.php';

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
                        $reposts = $this->postRepost->getRepostsByPostId($id);
                        if (!empty($reposts)) {
                            echo json_encode($reposts);
                        } else {
                            http_response_code(404);
                            echo json_encode(['message' => 'No reposts found for this post']);
                        }
                    } elseif (!empty($queryParams['user_id'])) {
                        $reposts = $this->postRepost->getRepostsByUserId($queryParams['user_id']);
                        echo json_encode($reposts);
                    } else {
                        $reposts = $this->postRepost->getAllReposts();
                        echo json_encode($reposts);
                    }
                    break;

                case 'POST':
                    $data = json_decode(file_get_contents("php://input"), true);
                    
                    if (empty($data) || !isset($data['user_id'], $data['post_id'])) {
                        throw new InvalidArgumentException('Invalid input data. Required: user_id, post_id');
                    }

                    $data['quote'] = $data['quote'] ?? null;
                    $result = $this->postRepost->createRepost($data);

                    if (isset($result['success']) && $result['success']) {
                        http_response_code(201);
                        echo json_encode([
                            'message' => 'Repost created successfully',
                            'repost_id' => $result['repost_id'] ?? null
                        ]);
                    } else {
                        http_response_code(400);
                        echo json_encode(['message' => $result['message'] ?? 'Failed to create repost']);
                    }
                    break;

                case 'DELETE':
                    if (!$id) {
                        throw new InvalidArgumentException('Repost ID is required');
                    }

                    if ($this->postRepost->deleteRepost($id)) {
                        echo json_encode(['message' => 'Repost deleted successfully']);
                    } else {
                        http_response_code(404);
                        echo json_encode(['message' => 'Repost not found or failed to delete']);
                    }
                    break;

                default:
                    http_response_code(405);
                    echo json_encode(['message' => 'Method not allowed']);
                    break;
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