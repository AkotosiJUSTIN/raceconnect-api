<?php

namespace Controller;
use Model\Post;
use Exception;

require_once 'C:/xampp/htdocs/raceconnectapi/Model/Post.php';

class PostController {
    private $post;

    public function __construct($db) {
        $this->post = new Post($db);
    }

    public function processRequest($method, $id = null) {
        try {
            $data = json_decode(file_get_contents("php://input"), true);

            switch ($method) {
                case 'GET':
                    if ($id) {
                        $post = $this->post->getPostById($id);
                        if ($post) {
                            http_response_code(200);
                            echo json_encode($post);
                        } else {
                            http_response_code(404);
                            echo json_encode(['message' => 'Post not found']);
                        }
                    } else {
                        http_response_code(200);
                        echo json_encode($this->post->getAllPosts());
                    }
                    break;

                case 'POST':
                    if (!$this->validatePostData($data)) {
                        http_response_code(400);
                        echo json_encode(['message' => 'Invalid input data. Required: title, content, author_id']);
                        return;
                    }

                    if ($this->post->createPost($data)) {
                        http_response_code(201);
                        echo json_encode(['message' => 'Post created successfully']);
                    } else {
                        http_response_code(500);
                        echo json_encode(['message' => 'Failed to create post']);
                    }
                    break;

                case 'PUT':
                    if (!$id) {
                        http_response_code(400);
                        echo json_encode(['message' => 'Post ID is required']);
                        return;
                    }

                    if (empty($data)) {
                        http_response_code(400);
                        echo json_encode(['message' => 'No data provided for update']);
                        return;
                    }

                    if (isset($data['title']) && strlen($data['title']) < 3) {
                        http_response_code(400);
                        echo json_encode(['message' => 'Title must be at least 3 characters long']);
                        return;
                    }

                    if (isset($data['content']) && strlen($data['content']) < 10) {
                        http_response_code(400);
                        echo json_encode(['message' => 'Content must be at least 10 characters long']);
                        return;
                    }

                    if ($this->post->updatePost($id, $data)) {
                        http_response_code(200);
                        echo json_encode(['message' => 'Post updated successfully']);
                    } else {
                        http_response_code(500);
                        echo json_encode(['message' => 'Failed to update post']);
                    }
                    break;

                case 'DELETE':
                    if (!$id) {
                        http_response_code(400);
                        echo json_encode(['message' => 'Post ID is required']);
                        return;
                    }

                    if ($this->post->deletePost($id)) {
                        http_response_code(200);
                        echo json_encode(['message' => 'Post deleted successfully']);
                    } else {
                        http_response_code(500);
                        echo json_encode(['message' => 'Failed to delete post']);
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

    private function validatePostData($data) {
        return isset($data['title'], $data['content'], $data['author_id'])
            && !empty($data['title']) 
            && !empty($data['content'])
            && !empty($data['author_id'])
            && strlen($data['title']) >= 3
            && strlen($data['content']) >= 10;
    }
}
