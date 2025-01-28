<?php
require_once 'C:/xampp/htdocs/RaceConnect/models/comment/ReelsComment.php';

class ReelCommentController {
    private $reelComment;

    public function __construct($db) {
        $this->reelComment = new ReelComment($db);
    }

    public function processRequest($method, $id = null) {
        try {
            switch ($method) {
                case 'GET':
                    if ($id) {
                        $comments = $this->reelComment->getCommentsByReelId($id);
                        if ($comments) {
                            echo json_encode($comments);
                        } else {
                            http_response_code(404);
                            echo json_encode(['message' => 'Comments not found']);
                        }
                    } else {
                        $comments = $this->reelComment->getAllComments();
                        if ($comments) {
                            echo json_encode($comments);
                        } else {
                            http_response_code(404);
                            echo json_encode(['message' => 'No comments found']);
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
                    if ($this->reelComment->createComment($data)) {
                        http_response_code(201);
                        echo json_encode(['message' => 'Comment added successfully']);
                    } else {
                        http_response_code(500);
                        echo json_encode(['message' => 'Failed to add comment']);
                    }
                    break;

                case 'DELETE':
                    if ($id) {
                        if ($this->reelComment->deleteComment($id)) {
                            echo json_encode(['message' => 'Comment deleted successfully']);
                        } else {
                            http_response_code(500);
                            echo json_encode(['message' => 'Failed to delete comment']);
                        }
                    } else {
                        http_response_code(400);
                        echo json_encode(['message' => 'Comment ID is required']);
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
