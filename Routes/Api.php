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
use Controller\FriendsController;
use Controller\AnnouncementController;
use Controller\ReportController;

require_once __DIR__ . '/../vendor/autoload.php';

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
        $action = $path[2] ?? null;

        // Debug log for routing
        error_log("Routing: Resource=$resource, ID=$id, Action=$action, Query=" . http_build_query($_GET));

        // Handle invalid routes
        if (!$resource) {
            http_response_code(400);
            echo json_encode(['message' => 'Bad Request: Resource is missing']);
            exit;
        }

        try {
            switch ($resource) {
                case 'users':
                    if ($action === 'images' && $id) {
                        $this->handleRequest(new UserController($this->conn), $method, $id, 'images');
                    } else {
                        $this->handleRequest(new UserController($this->conn), $method, $id);
                    }
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

                case 'forgot-password':
                    if ($method !== 'POST') {
                        http_response_code(405);
                        echo json_encode(['message' => 'Method Not Allowed: Use POST for forgot password']);
                        exit;
                    }
                    $controller = new AuthController($this->conn);
                    $data = $this->getJsonInput();
                    $controller->forgotPassword($data);
                    break;

                case 'verify-otp':
                    if ($method !== 'POST') {
                        http_response_code(405);
                        echo json_encode(['message' => 'Method Not Allowed: Use POST for verify OTP']);
                        exit;
                    }
                    $controller = new AuthController($this->conn);
                    $data = $this->getJsonInput();
                    $controller->verifyOtp($data);
                    break;

                case 'reset-password':
                    if ($method !== 'PUT') {
                        http_response_code(405);
                        echo json_encode(['message' => 'Method Not Allowed: Use PUT for reset password']);
                        exit;
                    }
                    $controller = new AuthController($this->conn);
                    $data = $this->getJsonInput();
                    $controller->resetPassword($data);
                    break;

                case 'friends':
                    $this->handleRequest(new FriendsController($this->conn), $method, $id);
                    break;

                case 'posts':
                    if ($action) {
                        if ($action === 'images') {
                            $this->handleRequest(new PostController($this->conn), $method, $id, 'images');
                        } elseif ($action === 'category' && isset($path[3])) {
                            $this->handleRequest(new PostController($this->conn), $method, $path[3], 'category');
                        } else {
                            $this->handleRequest(new PostController($this->conn), $method, $id);
                        }
                    } else {
                        $this->handleRequest(new PostController($this->conn), $method, $id);
                    }
                    break;

                case 'marketplace-items':
                    if ($id === 'user' && isset($path[2]) && is_numeric($path[2])) {
                        // For /marketplace-items/user/{userId}
                        $userId = $path[2]; // The user ID is in $path[2] (e.g., '4')
                        error_log("Routing to handleGetItemsByUserRequest for user ID: $userId");
                        $this->handleRequest(new MarketplaceItemController($this->conn), $method, $userId, 'user');
                    } elseif ($id && isset($path[2]) && $path[2] === 'images' && is_numeric($id)) {
                        // For /marketplace-items/{id}/images
                        error_log("Routing to handleGetItemImagesRequest for item ID: $id");
                        $this->handleRequest(new MarketplaceItemController($this->conn), $method, $id, 'images');
                    } else {
                        // For /marketplace-items (with query params) or /marketplace-items/{id}
                        error_log("Routing to handleGetRequest for resource with ID: $id");
                        $this->handleRequest(new MarketplaceItemController($this->conn), $method, $id);
                    }
                    break;

                case 'notifications':
                    $this->handleRequest(new NotificationController($this->conn), $method, $id);
                    break;

                case 'admins':
                    $this->handleRequest(new AdminController($this->conn), $method, $id);
                    break;

                case 'admin-analytics':
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

                case 'upload-profile-picture':
                    if ($method !== 'POST') {
                        http_response_code(405);
                        echo json_encode(['message' => 'Method Not Allowed: Use POST for uploading profile picture']);
                        exit;
                    }
                    
                    if (empty($_FILES)) {
                        http_response_code(400);
                        echo json_encode(['message' => 'No file uploaded']);
                        exit;
                    }
                
                    $controller = new UserController($this->conn);
                    $controller->uploadProfilePicture($_FILES);
                    break;

                case 'announcements':
                    $this->handleRequest(new AnnouncementController($this->conn), $method, $id);
                    break;

                case 'reports':
                    $this->handleRequest(new ReportController($this->conn), $method, $id);
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

    private function handleRequest($controller, $method, $id, $action = null) {
        if (!method_exists($controller, 'processRequest')) {
            http_response_code(500);
            echo json_encode(['message' => 'Internal Server Error: Controller method not found']);
            exit;
        }

        $controller->processRequest($method, $id, $action);
    }
}
?>