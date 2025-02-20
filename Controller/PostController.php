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
                        http_response_code(200);
                        echo json_encode($this->post->getAllPosts());
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
        error_log(print_r($_POST, true));
        error_log(print_r($_FILES, true));

        if (!isset($_POST['user_id'], $_POST['content'])) {
            http_response_code(400);
            echo json_encode(['message' => 'Missing required fields: user_id, content']);
            return;
        }

        $data = [
            'user_id' => $_POST['user_id'],
            'content' => $_POST['content'],
        ];

        $postId = $this->post->createPost($data);
        if (!$postId) {
            http_response_code(500);
            echo json_encode(['message' => 'Failed to create post']);
            return;
        }

        $imageUrls = [];
        if (isset($_FILES['image']) && !empty($_FILES['image']['name'][0])) {
            $imageUrls = $this->handleImageUpload($postId);
        }

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

        if (!isset($_FILES['image']) || empty($_FILES['image']['name'][0])) {
            return $imageUrls;
        }

        try {
            $fileCount = count($_FILES['image']['name']);

            for ($i = 0; $i < $fileCount; $i++) {
                if ($_FILES['image']['error'][$i] === UPLOAD_ERR_OK) {
                    $tmpName = $_FILES['image']['tmp_name'][$i];
                    $imageData = file_get_contents($tmpName);
                    $imageName = uniqid() . '-' . basename($_FILES['image']['name'][$i]);

                    $imageUrl = $this->post->uploadPostImageToS3($imageData, $imageName);
                    $imageUrls[] = $imageUrl;
                    $this->post->savePostImage($postId, $imageUrl);
                }
            }
        } catch (Exception $e) {
            error_log("Image upload failed: " . $e->getMessage());
        }

        return $imageUrls;
    }
}