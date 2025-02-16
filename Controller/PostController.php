<?php

namespace Controller;
use Model\Post;
use Exception;

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../Model/Post.php';

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
                        echo json_encode(['message' => 'Invalid input data. Required: user_id, content']);
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
        return isset($data['user_id'], $data['content'])
            && !empty($data['user_id'])
            && !empty($data['content']);
    }

    public function uploadPostImage() {
        if (!isset($_POST['post_id']) || empty($_POST['post_id']) || !isset($_FILES['image']) || empty($_FILES['image']['name'])) {
            http_response_code(400);
            echo json_encode(['message' => 'Post ID and at least one image are required']);
            return;
        }
    
        try {
            $postId = $_POST['post_id'];
            $imageUrls = [];
    
            // Check if multiple files were uploaded
            if (is_array($_FILES['image']['name'])) {
                // Handle multiple files
                foreach ($_FILES['image']['tmp_name'] as $key => $tmpName) {
                    if ($_FILES['image']['error'][$key] === UPLOAD_ERR_OK) {
                        $imageData = file_get_contents($tmpName);
                        $imageName = uniqid() . '-' . basename($_FILES['image']['name'][$key]);
    
                        // Upload to S3
                        $imageUrl = $this->post->uploadPostImageToS3($imageData, $imageName);
                        $imageUrls[] = $imageUrl;
    
                        // Save to database
                        $this->post->savePostImage($postId, $imageUrl);
                    }
                }
            } else {
                // Handle single file
                if ($_FILES['image']['error'] === UPLOAD_ERR_OK) {
                    $imageData = file_get_contents($_FILES['image']['tmp_name']);
                    $imageName = uniqid() . '-' . basename($_FILES['image']['name']);
    
                    // Upload to S3
                    $imageUrl = $this->post->uploadPostImageToS3($imageData, $imageName);
                    $imageUrls[] = $imageUrl;
    
                    // Save to database
                    $this->post->savePostImage($postId, $imageUrl);
                }
            }
    
            http_response_code(200);
            echo json_encode([
                'message' => 'Post images uploaded successfully',
                'image_urls' => $imageUrls
            ]);
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode(['message' => 'Failed to upload post images', 'error' => $e->getMessage()]);
        }
    }
    
}
