<?php
namespace Controller\Comment;

use Model\Comment\CommentsReply;
use InvalidArgumentException;
use RuntimeException;
use Exception;

require_once __DIR__ . '/../../vendor/autoload.php';

class CommentsReplyController {
    private $commentReply;

    public function __construct($db) {
        $this->commentReply = new CommentsReply($db);
    }

    public function processRequest($method, $id = null, $action = null) {
        try {
            switch ($method) {
                case 'GET':
                    if ($id) {
                        $reply = $this->commentReply->getReplyById($id);
                        if ($reply) {
                            echo json_encode($reply);
                        } else {
                            http_response_code(404);
                            echo json_encode(['message' => 'Reply not found']);
                        }
                    } elseif (isset($_GET['parent_comment_id'])) {
                        $parentCommentId = (int)$_GET['parent_comment_id'];
                        $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
                        $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 10;

                        // Validate pagination parameters
                        if ($page < 1 || $limit < 1) {
                            http_response_code(400);
                            echo json_encode(['message' => 'Invalid pagination parameters: page and limit must be positive integers']);
                            return;
                        }

                        $replies = $this->commentReply->getRepliesByCommentId($parentCommentId, $page, $limit);
                        if (empty($replies)) {
                            http_response_code(200);
                            echo json_encode([]);
                        } else {
                            echo json_encode($replies);
                        }
                    } else {
                        $replies = $this->commentReply->getAllReplies();
                        echo json_encode($replies);
                    }
                    break;

                case 'POST':
                    $data = json_decode(file_get_contents("php://input"), true);
                    if (empty($data) || !isset($data['user_id'], $data['parent_comment_id'], $data['reply_text'])) {
                        http_response_code(400);
                        echo json_encode(['message' => 'Invalid input data. Required: user_id, parent_comment_id, reply_text']);
                        return;
                    }
                    $result = $this->commentReply->createReply($data);
                    if (is_array($result) && isset($result['success']) && $result['success']) {
                        http_response_code(201);
                        echo json_encode(['message' => 'Reply added successfully', 'reply_id' => $result['reply_id']]);
                    } elseif (is_array($result) && isset($result['message'])) {
                        http_response_code(400);
                        echo json_encode(['message' => $result['message']]);
                    } else {
                        http_response_code(500);
                        echo json_encode(['message' => 'Failed to add reply']);
                    }
                    break;

                case 'PUT':
                    if (!$id) {
                        http_response_code(400);
                        echo json_encode(['message' => 'Reply ID is required']);
                        return;
                    }
                    $data = json_decode(file_get_contents("php://input"), true);
                    if (empty($data) || !isset($data['reply_text'])) {
                        http_response_code(400);
                        echo json_encode(['message' => 'Invalid input data. Required: reply_text']);
                        return;
                    }
                    if ($this->commentReply->updateReply($id, $data['reply_text'])) {
                        echo json_encode(['message' => 'Reply updated successfully']);
                    } else {
                        http_response_code(500);
                        echo json_encode(['message' => 'Failed to update reply']);
                    }
                    break;

                case 'DELETE':
                    if (!$id) {
                        http_response_code(400);
                        echo json_encode(['message' => 'Reply ID is required']);
                        return;
                    }
                    if ($this->commentReply->deleteReply($id)) {
                        echo json_encode(['message' => 'Reply deleted successfully']);
                    } else {
                        http_response_code(500);
                        echo json_encode(['message' => 'Failed to delete reply']);
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