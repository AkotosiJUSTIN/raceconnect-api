<?php
namespace Controller\Comment;

use Model\Comment\PostComment;
use InvalidArgumentException;
use RuntimeException;
use Exception;

class PostCommentController {
    private $postComment;

    public function __construct($db) {
        $this->postComment = new PostComment($db);
    }

    public function processRequest($method, $id = null) {
        try {
            switch ($method) {
                case 'GET':
                    $this->handleGetRequest($id);
                    break;
                case 'POST':
                    $this->handlePostRequest();
                    break;
                case 'PATCH':
                    $this->handlePatchRequest($id);
                    break;
                case 'DELETE':
                    $this->handleDeleteRequest($id);
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

    // Handle GET requests
    private function handleGetRequest($id) {
        if (isset($_GET['post_id'])) {
            $comments = $this->postComment->getCommentsByPostId($_GET['post_id']);
        } elseif (isset($_GET['user_id'])) {
            $comments = $this->postComment->getCommentsByUserId($_GET['user_id']);
        } else {
            $comments = $this->postComment->getAllComments();
        }

        if (!empty($comments)) {
            echo json_encode($comments);
        } else {
            http_response_code(404);
            echo json_encode(['message' => 'No comments found']);
        }
    }

    // Handle POST requests
    private function handlePostRequest() {
        $data = json_decode(file_get_contents("php://input"), true);
        if (!$this->isValidCommentData($data)) {
            http_response_code(400);
            echo json_encode(['message' => 'Invalid input data']);
            return;
        }

        if ($this->postComment->createComment($data)) {
            http_response_code(201);
            echo json_encode(['message' => 'Comment added successfully']);
        } else {
            http_response_code(500);
            echo json_encode(['message' => 'Failed to add comment']);
        }
    }

    // Handle PATCH requests (update comment)
    private function handlePatchRequest($id) {
        if (!$id) {
            http_response_code(400);
            echo json_encode(['message' => 'Comment ID is required']);
            return;
        }

        $data = json_decode(file_get_contents("php://input"), true);
        if (empty($data['comment'])) {
            http_response_code(400);
            echo json_encode(['message' => 'New comment text is required']);
            return;
        }

        if ($this->postComment->updateComment($id, $data['comment'])) {
            echo json_encode(['message' => 'Comment updated successfully']);
        } else {
            http_response_code(500);
            echo json_encode(['message' => 'Failed to update comment']);
        }
    }

    // Handle DELETE requests
    private function handleDeleteRequest($id) {
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
    }

    // Validate input data
    private function isValidCommentData($data) {
        return isset($data['user_id'], $data['post_id'], $data['owner_id'], $data['comment']) && 
               !empty($data['user_id']) && !empty($data['post_id']) && 
               !empty($data['owner_id']) && !empty($data['comment']);
    }
}

?>
