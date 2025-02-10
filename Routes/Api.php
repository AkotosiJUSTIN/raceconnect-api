<?php

use Controller\UserController;
use Controller\PostController;
use Controller\MarketplaceItemController;
use Controller\NotificationController;
use Controller\Admin\AdminController;
use Controller\Admin\AdminAnalyticsController;
use Controller\Like\PostLikeController;
use Controller\Like\MarketplaceItemLikeController;
use Controller\Comment\PostCommentController;
use Controller\Repost\PostRepostController;
use Controller\AuthController;

class Api {
    private $conn;

    public function __construct($conn) {
        $this->conn = $conn;
    }

    public function route($path, $method) {
        header('Content-Type: application/json');

        // Validate database connection
        if (!$this->conn) {
            http_response_code(500);
            echo json_encode(['message' => 'Internal Server Error: Database connection failed']);
            exit;
        }

        $resource = $path[0] ?? null;
        $id = $path[1] ?? null;

        // Handle invalid routes
        if (!$resource) {
            http_response_code(400);
            echo json_encode(['message' => 'Bad Request: Resource is missing']);
            exit;
        }

        try {
            switch ($resource) {
                case 'users':
                    $this->handleRequest(new UserController($this->conn), $method, $id);
                    break;

                case 'login':
                    if ($method !== 'POST') {
                        http_response_code(405);
                        echo json_encode(['message' => 'Method Not Allowed: Use POST for login']);
                        exit;
                    }
                    $controller = new AuthController($this->conn);
                    $data = $this->getJsonInput();
                    $controller->login($data);
                    break;

                case 'logout':
                    if ($method !== 'POST') {
                        http_response_code(405);
                        echo json_encode(['message' => 'Method Not Allowed: Use POST for logout']);
                        exit;
                    }
                    $controller = new AuthController($this->conn);
                    $controller->logout();
                    break;

                case 'posts':
                    $this->handleRequest(new PostController($this->conn), $method, $id);
                    break;

                case 'marketplace-items':
                    $this->handleRequest(new MarketplaceItemController($this->conn), $method, $id);
                    break;

                case 'notifications':
                    $this->handleRequest(new NotificationController($this->conn), $method, $id);
                    break;

                case 'admins':
                    $this->handleRequest(new AdminController($this->conn), $method, $id);
                    break;

                case 'admin_analytics':
                    $this->handleRequest(new AdminAnalyticsController($this->conn), $method, $id);
                    break;

                case 'post-likes':
                    $this->handleRequest(new PostLikeController($this->conn), $method, $id);
                    break;

                case 'marketplace-item-likes':
                    $this->handleRequest(new MarketplaceItemLikeController($this->conn), $method, $id);
                    break;

                case 'post-comments':
                    $this->handleRequest(new PostCommentController($this->conn), $method, $id);
                    break;

                case 'post-reposts':
                    $this->handleRequest(new PostRepostController($this->conn), $method, $id);
                    break;

                default:
                    http_response_code(404);
                    echo json_encode(['message' => 'Not Found: Invalid resource']);
                    break;
            }
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode([
                'message' => 'Internal Server Error',
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Validates and retrieves JSON input data
     */
    private function getJsonInput() {
        $input = file_get_contents("php://input");
        $data = json_decode($input, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            http_response_code(400);
            echo json_encode(['message' => 'Bad Request: Invalid JSON format']);
            exit;
        }

        return $data;
    }

    /**
     * Handles request for controllers with CRUD operations
     */
    private function handleRequest($controller, $method, $id) {
        if (!method_exists($controller, 'processRequest')) {
            http_response_code(500);
            echo json_encode(['message' => 'Internal Server Error: Controller method not found']);
            exit;
        }

        $controller->processRequest($method, $id);
    }
}
