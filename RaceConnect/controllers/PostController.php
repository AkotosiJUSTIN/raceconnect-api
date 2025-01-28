<?php
//CHANGE THE PATHS TO YOUR OWN PATHS
require_once 'C:/xampp/htdocs/RaceConnect/models/Post.php';

class PostController {
    private $post;

    public function __construct($db) {
        $this->post = new Post($db);
    }

    public function processRequest($method, $id = null) {
        try {
            switch ($method) {
            case 'GET':
                if ($id) {
                $post = $this->post->getPostById($id);
                if ($post) {
                    echo json_encode($post);
                } else {
                    http_response_code(404);
                    echo json_encode(['message' => 'Post not found']);
                }
                } else {
                echo json_encode($this->post->getAllPosts());
                }
                break;

            case 'POST':
                $data = json_decode(file_get_contents("php://input"), true);
                if (empty($data)) {
                http_response_code(400);
                echo json_encode(['message' => 'Invalid input data']);
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
                if ($id) {
                $data = json_decode(file_get_contents("php://input"), true);
                if (empty($data)) {
                    http_response_code(400);
                    echo json_encode(['message' => 'Invalid input data']);
                    return;
                }
                if ($this->post->updatePost($id, $data)) {
                    echo json_encode(['message' => 'Post updated successfully']);
                } else {
                    http_response_code(500);
                    echo json_encode(['message' => 'Failed to update post']);
                }
                } else {
                http_response_code(400);
                echo json_encode(['message' => 'Post ID is required']);
                }
                break;

            case 'DELETE':
                if ($id) {
                if ($this->post->deletePost($id)) {
                    echo json_encode(['message' => 'Post deleted successfully']);
                } else {
                    http_response_code(500);
                    echo json_encode(['message' => 'Failed to delete post']);
                }
                } else {
                http_response_code(400);
                echo json_encode(['message' => 'Post ID is required']);
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
