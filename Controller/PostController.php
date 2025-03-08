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
                    } elseif (isset($_GET['category']) && isset($_GET['user_id'])) {
                        $categories = explode(',', $_GET['category']);
                        $userId = (int) $_GET['user_id'];
                        $limit = isset($_GET['limit']) ? (int) $_GET['limit'] : 10;
                        $offset = isset($_GET['offset']) ? (int) $_GET['offset'] : 0;

                        $this->handleGetPostsByCategoryAndPrivacyRequest($userId, $categories, $limit, $offset);
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
                    echo json_encode(["error" => "Method not allowed"]);
            }
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode(["error" => $e->getMessage()]);
        }
    }

    private function handlePostRequest() {
        $data = json_decode(file_get_contents("php://input"), true);
        if ($data && isset($data['user_id'], $data['title'], $data['content'])) {
            $postId = $this->post->createPost($data);
            if ($postId) {
                http_response_code(201);
                echo json_encode(["message" => "Post created successfully", "post_id" => $postId]);
            } else {
                http_response_code(500);
                echo json_encode(["error" => "Failed to create post"]);
            }
        } else {
            http_response_code(400);
            echo json_encode(["error" => "Invalid post data"]);
        }
    }

    private function handleGetRequest($id) {
        if ($id) {
            $post = $this->post->getPostById($id);
            echo json_encode($post);
        } else {
            $limit = isset($_GET['limit']) ? (int) $_GET['limit'] : 10;
            $offset = isset($_GET['offset']) ? (int) $_GET['offset'] : 0;
            $posts = $this->post->getAllPosts($limit, $offset);
            echo json_encode($posts);
        }
    }

    private function handleGetPostsByUserIdRequest($userId) {
        $limit = isset($_GET['limit']) ? (int) $_GET['limit'] : 10;
        $offset = isset($_GET['offset']) ? (int) $_GET['offset'] : 0;
        $posts = $this->post->getPostById($userId, $limit, $offset);
        echo json_encode($posts);
    }

    private function handleGetPostsByCategoryAndPrivacyRequest($userId, $categories, $limit, $offset) {
        $posts = $this->post->getPostsByCategoryAndPrivacy($userId, $categories, $limit, $offset);
        echo json_encode($posts);
    }

    private function handleGetPostImagesRequest($postId) {
        $images = $this->post->getPostImages($postId);
        echo json_encode($images);
    }

    private function handlePutRequest($id, $data) {
        if (!$id || empty($data)) {
            http_response_code(400);
            echo json_encode(["error" => "Invalid request"]);
            return;
        }

        $updated = $this->post->updatePost($id, $data);
        if ($updated) {
            echo json_encode(["message" => "Post updated successfully"]);
        } else {
            http_response_code(500);
            echo json_encode(["error" => "Failed to update post"]);
        }
    }

    private function handleDeleteRequest($id) {
        if (!$id) {
            http_response_code(400);
            echo json_encode(["error" => "Invalid request"]);
            return;
        }

        $deleted = $this->post->deletePost($id);
        if ($deleted) {
            echo json_encode(["message" => "Post deleted successfully"]);
        } else {
            http_response_code(500);
            echo json_encode(["error" => "Failed to delete post"]);
        }
    }
}
?>
