<?php
//CHANGE THE PATHS TO YOUR OWN PATHSS
require_once 'C:/xampp/htdocs/RaceConnect/models/Post.php';

class PostController {
    private $post;

    public function __construct($db) {
        $this->post = new Post($db);
    }

    public function processRequest($method, $id = null) {
        switch ($method) {
            case 'GET':
                if ($id) {
                    $post = $this->post->getPostById($id);
                    echo json_encode($post ? ['success' => true, 'data' => $post] : ['success' => false, 'message' => 'Post not found']);
                } else {
                    echo json_encode(['success' => true, 'data' => $this->post->getAllPosts()]);
                }
                break;

            case 'POST':
                $data = json_decode(file_get_contents("php://input"), true);
                if ($this->post->createPost($data)) {
                    echo json_encode(['success' => true, 'message' => 'Post created successfully']);
                } else {
                    echo json_encode(['success' => false, 'message' => 'Failed to create post']);
                }
                break;

            case 'PUT':
                if ($id) {
                    $data = json_decode(file_get_contents("php://input"), true);
                    if ($this->post->updatePost($id, $data)) {
                        echo json_encode(['success' => true, 'message' => 'Post updated successfully']);
                    } else {
                        echo json_encode(['success' => false, 'message' => 'Failed to update post']);
                    }
                } else {
                    echo json_encode(['success' => false, 'message' => 'Post ID is required']);
                }
                break;

            case 'DELETE':
                if ($id) {
                    if ($this->post->deletePost($id)) {
                        echo json_encode(['success' => true, 'message' => 'Post deleted successfully']);
                    } else {
                        echo json_encode(['success' => false, 'message' => 'Failed to delete post']);
                    }
                } else {
                    echo json_encode(['success' => false, 'message' => 'Post ID is required']);
                }
                break;

            default:
                echo json_encode(['success' => false, 'message' => 'Unsupported HTTP method']);
        }
    }
}
?>
