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
            if ($method === 'POST') {
                $this->handlePostRequest();
                return;
            }

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
                        $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 10;
                        $offset = isset($_GET['offset']) ? (int)$_GET['offset'] : 0;
                        $posts = $this->post->getAllPosts($limit, $offset);
                        http_response_code(200);
                        echo json_encode($posts);
                    }
                    break;

                case 'PUT':
                    if (!$id) {
                        http_response_code(400);
                        echo json_encode(['message' => 'Post ID is required']);
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

    private function handlePostRequest() {
        try {
            // Detect if the request is multipart/form-data or JSON
            if (!empty($_POST)) {
                $data = $_POST; // Form data request
            } else {
                $jsonInput = file_get_contents("php://input");
                $data = json_decode($jsonInput, true);
            }
    
            // Validate required fields
            if (!isset($data['user_id'], $data['content'])) {
                http_response_code(400);
                echo json_encode(['message' => 'Missing required fields: user_id, content']);
                return;
            }
    
            // ✅ First, create the post before handling images
            $postId = $this->post->createPost($data);
            if (!$postId) {
                http_response_code(500);
                echo json_encode(['message' => 'Failed to create post']);
                return;
            }
    
            // ✅ Then, handle image upload (ensuring postId exists)
            $imageUrls = $this->handleImageUpload($postId);
    
            http_response_code(201);
            echo json_encode([
                'message' => 'Post created successfully',
                'post_id' => $postId,
                'image_urls' => $imageUrls
            ]);
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode(['message' => 'Failed to create post', 'error' => $e->getMessage()]);
        }
    }
    
    

    private function handleImageUpload($postId) {
        $imageUrls = [];
    
        if (!isset($_FILES['image']) || empty($_FILES['image']['name'])) {
            return $imageUrls; // No images uploaded
        }
    
        try {
            // Normalize single file to array format
            $files = is_array($_FILES['image']['name']) ? $_FILES['image'] : [
                'name' => [$_FILES['image']['name']],
                'type' => [$_FILES['image']['type']],
                'tmp_name' => [$_FILES['image']['tmp_name']],
                'error' => [$_FILES['image']['error']],
                'size' => [$_FILES['image']['size']]
            ];
    
            $fileCount = count($files['name']);
    
            for ($i = 0; $i < $fileCount; $i++) {
                if ($files['error'][$i] === UPLOAD_ERR_OK) {
                    $tmpName = $files['tmp_name'][$i];
                    $imageData = file_get_contents($tmpName);
                    $imageName = uniqid() . '-' . basename($files['name'][$i]);
    
                    // ✅ Upload to S3 and get image URL
                    $imageUrl = $this->post->uploadPostImageToS3($imageData, $imageName);
                    if ($imageUrl) {
                        $imageUrls[] = $imageUrl;
    
                        // ✅ Save the image URL in the database (with correct postId)
                        $this->post->savePostImage($postId, $imageUrl);
                    }
                }
            }
        } catch (Exception $e) {
            error_log("Image upload failed: " . $e->getMessage());
        }
    
        return $imageUrls;
    }
    
    
}