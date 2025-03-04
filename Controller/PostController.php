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

    public function processRequest($method, $id = null, $action = null) {
        try {
            $data = json_decode(file_get_contents("php://input"), true) ?? [];
    
            switch ($method) {
                case 'POST':
                    $this->handlePostRequest();
                    break;
    
                case 'GET':
                    if ($action === 'images' && $id) {
                        $this->handleGetPostImagesRequest($id);
                    } elseif (isset($_GET['user_id'])) {
                        $this->handleGetPostsByUserIdRequest((int)$_GET['user_id']);
                    } else {
                        $this->handleGetRequest($id);
                    }
                    break;
    
                case 'PUT':
                    $this->handlePutRequest($id, $data);
                    break;
    
                case 'DELETE':
                    $this->handleDeleteRequest($id);
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
            $data = !empty($_POST) ? $_POST : json_decode(file_get_contents("php://input"), true) ?? [];

            if (empty($data['user_id']) || empty($data['content'])) {
                http_response_code(400);
                echo json_encode(['message' => 'Missing required fields: user_id, content']);
                return;
            }

            $postId = $this->post->createPost($data);
            if (!$postId || $postId == 0) {
                throw new Exception('Failed to create post.');
            }
            error_log("Generated Post ID: " . $postId); // Debugging: Check if the ID is valid

            // Add a short delay to ensure database consistency (if needed)
            usleep(500000); // 500ms delay

            $imageUrls = $this->handleImageUpload($postId);

            if (!empty($_FILES['image']['name']) && empty($imageUrls)) {
                // Rollback: Delete post if image upload fails
                $this->post->deletePost($postId);
                http_response_code(500);
                echo json_encode(['message' => 'Post creation failed due to image upload error']);
                return;
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

    private function handleGetRequest($id) {
        try {
            if ($id) {
                // Existing single post handling
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
                
                if (isset($_GET['category']) && isset($_GET['user_id'])) {
                    $rawCategories = urldecode($_GET['category']);
                    $categoryAbbreviations = explode(',', $rawCategories);
                    $categoryAbbreviations = array_map('strtoupper', $categoryAbbreviations);
                    $userId = (int)$_GET['user_id'];
                    
                    $posts = $this->post->getPostsByCategoryAndPrivacy(
                        $userId, 
                        $categoryAbbreviations,
                        $limit,
                        $offset
                    );
                    http_response_code(200);
                    echo json_encode($posts);
                } else {
                    // Default to all posts
                    $posts = $this->post->getAllPosts($limit, $offset);
                    http_response_code(200);
                    echo json_encode($posts);
                }
            }
        } catch (Exception $e) {
            http_response_code(400);
            echo json_encode(['message' => $e->getMessage()]);
        }
    }

    private function handlePutRequest($id, $data) {
        try {
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
                throw new Exception('Failed to update post.');
            }
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode(['message' => 'Update error', 'error' => $e->getMessage()]);
        }
    }

    private function handleDeleteRequest($id) {
        try {
            if (!$id) {
                http_response_code(400);
                echo json_encode(['message' => 'Post ID is required']);
                return;
            }

            if ($this->post->deletePost($id)) {
                http_response_code(200);
                echo json_encode(['message' => 'Post deleted successfully']);
            } else {
                throw new Exception('Failed to delete post.');
            }
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode(['message' => 'Deletion error', 'error' => $e->getMessage()]);
        }
    }

    private function handleImageUpload($postId) {
        $imageUrls = [];

        if (!isset($_FILES['image']) || empty($_FILES['image']['name'])) {
            return $imageUrls;
        }

        try {
            $files = is_array($_FILES['image']['name']) ? $_FILES['image'] : [
                'name' => [$_FILES['image']['name']],
                'type' => [$_FILES['image']['type']],
                'tmp_name' => [$_FILES['image']['tmp_name']],
                'error' => [$_FILES['image']['error']],
                'size' => [$_FILES['image']['size']]
            ];

            for ($i = 0; $i < count($files['name']); $i++) {
                if ($files['error'][$i] !== UPLOAD_ERR_OK) {
                    $this->handleFileUploadError($files['error'][$i]);
                    continue;
                }

                $tmpName = $files['tmp_name'][$i];
                $imageData = file_get_contents($tmpName);
                $imageName = uniqid() . '-' . basename($files['name'][$i]);

                $imageUrl = $this->post->uploadPostImageToS3($imageData, $imageName);
                if ($imageUrl) {
                    $imageUrls[] = $imageUrl;
                    $this->post->savePostImage($postId, $imageUrl);
                }
            }
        } catch (Exception $e) {
            error_log("Image upload failed: " . $e->getMessage());
        }

        return $imageUrls;
    }

    private function handleFileUploadError($errorCode) {
        $errorMessages = [
            UPLOAD_ERR_INI_SIZE => 'Uploaded file exceeds the upload_max_filesize directive in php.ini.',
            UPLOAD_ERR_FORM_SIZE => 'Uploaded file exceeds the MAX_FILE_SIZE directive specified in the form.',
            UPLOAD_ERR_PARTIAL => 'File was only partially uploaded.',
            UPLOAD_ERR_NO_FILE => 'No file was uploaded.',
            UPLOAD_ERR_NO_TMP_DIR => 'Missing a temporary folder.',
            UPLOAD_ERR_CANT_WRITE => 'Failed to write file to disk.',
            UPLOAD_ERR_EXTENSION => 'File upload stopped by an extension.'
        ];

        $message = $errorMessages[$errorCode] ?? 'Unknown upload error.';
        http_response_code(400);
        echo json_encode(['message' => 'File upload error', 'error' => $message]);
    }

    private function handleGetPostImagesRequest($postId) {
        try {
            $images = $this->post->getPostImages($postId);
            if ($images) {
                http_response_code(200);
                echo json_encode($images);
            } else {
                http_response_code(404);
                echo json_encode(['message' => 'No images found for this post']);
            }
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode(['message' => 'Failed to retrieve post images', 'error' => $e->getMessage()]);
        }
    }

    private function handleGetPostsByUserIdRequest($userId) {
        try {
            $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 10;
            $offset = isset($_GET['offset']) ? (int)$_GET['offset'] : 0;
            $posts = $this->post->getPostsByUserId($userId, $limit, $offset);
            http_response_code(200);
            echo json_encode($posts);
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode(['message' => 'Failed to retrieve posts', 'error' => $e->getMessage()]);
        }
    }
}
