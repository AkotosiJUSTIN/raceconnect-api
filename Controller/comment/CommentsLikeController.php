<?php
namespace Controller\Comment;

use Model\Comment\CommentsLike;
use InvalidArgumentException;
use RuntimeException;
use Exception;

require_once __DIR__ . '/../../vendor/autoload.php';

class CommentsLikeController {
    private $commentLike;

    public function __construct($db) {
        $this->commentLike = new CommentsLike($db);
    }

    public function processRequest($method, $id = null, $action = null) {
        try {
            switch ($method) {
                case 'GET':
                    if (isset($_GET['comment_id'])) {
                        $commentId = (int)$_GET['comment_id'];
                        $likes = $this->commentLike->getLikesByCommentId($commentId);
                        if (empty($likes)) {
                            http_response_code(200);
                            echo json_encode([]);
                        } else {
                            echo json_encode($likes);
                        }
                    } else {
                        http_response_code(400);
                        echo json_encode(['message' => 'Comment ID is required']);
                    }
                    break;

                case 'POST':
                    $data = json_decode(file_get_contents("php://input"), true);
                    if (empty($data) || !isset($data['user_id'], $data['comment_id'])) {
                        http_response_code(400);
                        echo json_encode(['message' => 'Invalid input data. Required: user_id, comment_id']);
                        return;
                    }
                    $result = $this->commentLike->addLike($data);
                    if (is_array($result) && isset($result['success']) && $result['success']) {
                        http_response_code(201);
                        echo json_encode(['message' => 'Like added successfully', 'like_id' => $result['like_id']]);
                    } elseif (is_array($result) && isset($result['message'])) {
                        http_response_code(400);
                        echo json_encode(['message' => $result['message']]);
                    } else {
                        http_response_code(500);
                        echo json_encode(['message' => 'Failed to add like']);
                    }
                    break;

                case 'DELETE':
                    $data = json_decode(file_get_contents("php://input"), true);
                    if (empty($data) || !isset($data['comment_id'], $data['user_id'])) {
                        http_response_code(400);
                        echo json_encode(['message' => 'Invalid input data. Required: comment_id, user_id']);
                        return;
                    }
                    if ($this->commentLike->removeLike($data['comment_id'], $data['user_id'])) {
                        echo json_encode(['message' => 'Like removed successfully']);
                    } else {
                        http_response_code(500);
                        echo json_encode(['message' => 'Failed to remove like or like not found']);
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