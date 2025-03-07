<?php
namespace Controller\Comment;

use Model\Comment\PostComment;
use InvalidArgumentException;
use RuntimeException;
use Exception;

require_once __DIR__ . '/../../vendor/autoload.php';

class PostCommentController {
    private $postComment;

    public function __construct($db) {
        $this->postComment = new PostComment($db);
    }

    public function processRequest($method, $id = null, $action = null) {
        try {
            switch ($method) {
                case 'GET':
                    if ($id) {
                        // Get a specific comment by ID
                        $comment = $this->postComment->getCommentById($id);
                        if ($comment) {
                            echo json_encode($comment);
                        } else {
                            http_response_code(404);
                            echo json_encode(['message' => 'Comment not found']);
                        }
                    } elseif (isset($_GET['post_id'])) {
                        // Get comments by post_id
                        $postId = (int)$_GET['post_id'];
                        $comments = $this->postComment->getCommentsByPostId($postId);
                        if (empty($comments)) {
                            http_response_code(200); // Return 200 with empty array
                            echo json_encode([]);
                        } else {
                            echo json_encode($comments);
                        }
                    } else {
                        // Get all comments
                        $comments = $this->postComment->getAllComments();
                        echo json_encode($comments);
                    }
                    break;

                case 'POST':
                    $data = json_decode(file_get_contents("php://input"), true);
                    if (empty($data) || !isset($data['user_id'], $data['post_id'], $data['comment'])) {
                        http_response_code(400);
                        echo json_encode(['message' => 'Invalid input data. Required: user_id, post_id, comment']);
                        return;
                    }
                    $result = $this->postComment->createComment($data);
                    if (is_array($result) && isset($result['success']) && $result['success']) {
                        http_response_code(201);
                        echo json_encode(['message' => 'Comment added successfully', 'comment_id' => $result['comment_id']]);
                    } elseif (is_array($result) && isset($result['message'])) {
                        http_response_code(400);
                        echo json_encode(['message' => $result['message']]);
                    } else {
                        http_response_code(500);
                        echo json_encode(['message' => 'Failed to add comment']);
                    }
                    break;

                case 'PUT':
                    if (!$id) {
                        http_response_code(400);
                        echo json_encode(['message' => 'Comment ID is required']);
                        return;
                    }
                    $data = json_decode(file_get_contents("php://input"), true);
                    if (empty($data) || !isset($data['comment'])) {
                        http_response_code(400);
                        echo json_encode(['message' => 'Invalid input data. Required: comment']);
                        return;
                    }
                    if ($this->postComment->updateComment($id, $data['comment'])) {
                        echo json_encode(['message' => 'Comment updated successfully']);
                    } else {
                        http_response_code(500);
                        echo json_encode(['message' => 'Failed to update comment']);
                    }
                    break;

                case 'DELETE':
                    if (!$id) {
                        http_response_code(400);
                        echo json_encode(['message' => 'Comment ID is required']);
                        return;
                    }
                    if ($this->postComment->deleteComment($id)) {
                        echo json_encode(['message' => 'Comment deleted successfully']);
                    } else {
                        http_response_code(500);
                        echo json_encode(['message' => 'Failed to delete comment']);
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