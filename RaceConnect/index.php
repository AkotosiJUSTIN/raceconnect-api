<?php

//CHANGE THE PATHS TO YOUR OWN PATHS
require_once 'C:/xampp/htdocs/RaceConnect/config/database.php';
require_once 'C:/xampp/htdocs/RaceConnect/controllers/UserController.php';
require_once 'C:/xampp/htdocs/RaceConnect/controllers/PostController.php';
require_once 'C:/xampp/htdocs/RaceConnect/controllers/ReelsController.php';
require_once 'C:/xampp/htdocs/RaceConnect/controllers/MarketplaceItemController.php';
require_once 'C:/xampp/htdocs/RaceConnect/controllers/NotificationController.php';
require_once 'C:/xampp/htdocs/RaceConnect/controllers/admin/AdminController.php';
require_once 'C:/xampp/htdocs/RaceConnect/controllers/admin/AdminAnalyticsController.php';
require_once 'C:/xampp/htdocs/RaceConnect/controllers/like/PostLikeController.php';
require_once 'C:/xampp/htdocs/RaceConnect/controllers/like/ReelsLikeController.php';
require_once 'C:/xampp/htdocs/RaceConnect/controllers/like/MarketplaceItemLikeController.php';
require_once 'C:/xampp/htdocs/RaceConnect/controllers/comment/PostCommentController.php';
require_once 'C:/xampp/htdocs/RaceConnect/controllers/comment/ReelsCommentController.php';
require_once 'C:/xampp/htdocs/RaceConnect/controllers/repost/PostRepostController.php';
require_once 'C:/xampp/htdocs/RaceConnect/controllers/repost/ReelsRepostController.php';

$path = explode('/', trim($_SERVER['PATH_INFO'], '/'));
$resource = $path[0] ?? null;
$method = $_SERVER['REQUEST_METHOD'];
$id = $path[1] ?? null;

if (!$conn) {
    echo json_encode(['message' => 'Database connection failed']);
    exit;
}

if ($resource === 'users') {
    $controller = new UserController($conn);

    // Handle login and logout
    if (isset($_GET['action'])) {
        if ($_GET['action'] === 'login') {
            $controller->processRequest('POST');
        } elseif ($_GET['action'] === 'logout') {
            $controller->processRequest('POST');
        }
    } else {
        // Pass the ID (if available) to the controller's method
        $controller->processRequest($method, $id);
    }
} elseif ($resource === 'posts') {
    $controller = new PostController($conn);
    $controller->processRequest($method, $id);
} elseif ($resource === 'reels') {
    $controller = new ReelController($conn);
    $controller->processRequest($method, $id);
} elseif ($resource === 'marketplace-items') {
    $controller = new MarketplaceItemController($conn);
    $controller->processRequest($method, $id);
} elseif ($resource === 'notifications') {
    $controller = new NotificationController($conn);
    $controller->processRequest($method, $id);
} elseif ($resource === 'admins') {
    $controller = new AdminController($conn);
    $controller->processRequest($method, $id);
} elseif ($resource === 'admin_analytics') {
    $controller = new AdminAnalyticsController($conn);
    $controller->processRequest($method, $id);
}  elseif ($resource === 'post-likes') {
    $controller = new PostLikeController($conn);
    $controller->processRequest($method, $id);
} elseif ($resource === 'reel-likes') {
    $controller = new ReelLikeController($conn);
    $controller->processRequest($method, $id);
} elseif ($resource === 'marketplace-item-likes') {
    $controller = new MarketplaceItemLikeController($conn);
    $controller->processRequest($method, $id);
} elseif ($resource === 'post-comments') {
    $controller = new PostCommentController($conn);
    $controller->processRequest($method, $id);
} elseif ($resource === 'reel-comments') {
    $controller = new ReelCommentController($conn);
    $controller->processRequest($method, $id);
} elseif ($resource === 'post-reposts') {
    $controller = new PostRepostController($conn);
    $controller->processRequest($method, $id);
} elseif ($resource === 'reel-reposts') {
    $controller = new ReelRepostController($conn);
    $controller->processRequest($method, $id);
} else {
    echo json_encode(['message' => 'Resource or user_id is missing']);
}
?>
