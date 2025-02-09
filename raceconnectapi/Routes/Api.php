<?php
use Controller\UserController;
use Controller\PostController;
use Controller\ReelsController;
use Controller\MarketplaceItemController;
use Controller\NotificationController;
use Controller\Admin\AdminController;
use Controller\Admin\AdminAnalyticsController;
use Controller\Like\PostLikeController;
use Controller\Like\ReelsLikeController;
use Controller\Like\MarketplaceItemLikeController;
use Controller\Comment\PostCommentController;
use Controller\Comment\ReelsCommentController;
use Controller\Repost\PostRepostController;
use Controller\Repost\ReelsRepostController;
use Controller\AuthController;

class Api {
    private $conn;

    public function __construct($conn) {
        $this->conn = $conn;
    }

    public function route($path, $method) {
        $resource = $path[0] ?? null;
        $id = $path[1] ?? null;

        if (!$this->conn) {
            echo json_encode(['message' => 'Database connection failed']);
            exit;
        }

        switch ($resource) {
            case 'users':
                $controller = new UserController($this->conn);
                $controller->processRequest($method, $id);
                break;
            case 'login':
                if ($method === 'POST') {
                    $controller = new AuthController($this->conn);
                    $controller->login(json_decode(file_get_contents("php://input"), true));
                } else {
                    echo json_encode(['message' => 'Unsupported HTTP method for login']);
                }
                break;
            case 'logout':
                if ($method === 'POST') {
                    $controller = new AuthController($this->conn);
                    $controller->logout();
                } else {
                    echo json_encode(['message' => 'Unsupported HTTP method for logout']);
                }
                break;
            case 'posts':
                $controller = new PostController($this->conn);
                $controller->processRequest($method, $id);
                break;
            case 'reels':
                $controller = new ReelsController($this->conn);
                $controller->processRequest($method, $id);
                break;
            case 'marketplace-items':
                $controller = new MarketplaceItemController($this->conn);
                $controller->processRequest($method, $id);
                break;
            case 'notifications':
                $controller = new NotificationController($this->conn);
                $controller->processRequest($method, $id);
                break;
            case 'admins':
                $controller = new AdminController($this->conn);
                $controller->processRequest($method, $id);
                break;
            case 'admin_analytics':
                $controller = new AdminAnalyticsController($this->conn);
                $controller->processRequest($method, $id);
                break;
            case 'post-likes':
                $controller = new PostLikeController($this->conn);
                $controller->processRequest($method, $id);
                break;
            case 'reel-likes':
                $controller = new ReelsLikeController($this->conn);
                $controller->processRequest($method, $id);
                break;
            case 'marketplace-item-likes':
                $controller = new MarketplaceItemLikeController($this->conn);
                $controller->processRequest($method, $id);
                break;
            case 'post-comments':
                $controller = new PostCommentController($this->conn);
                $controller->processRequest($method, $id);
                break;
            case 'reel-comments':
                $controller = new ReelsCommentController($this->conn);
                $controller->processRequest($method, $id);
                break;
            case 'post-reposts':
                $controller = new PostRepostController($this->conn);
                $controller->processRequest($method, $id);
                break;
            case 'reel-reposts':
                $controller = new ReelsRepostController($this->conn);
                $controller->processRequest($method, $id);
                break;
            default:
                echo json_encode(['message' => 'Resource or user_id is missing']);
                break;
        }
    }
}
?>