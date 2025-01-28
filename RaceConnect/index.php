<?php
require_once 'C:/xampp/htdocs/RaceConnect/config/database.php';
require_once 'C:/xampp/htdocs/RaceConnect/controllers/UserController.php';
require_once 'C:/xampp/htdocs/RaceConnect/controllers/PostController.php';
require_once 'C:/xampp/htdocs/RaceConnect/controllers/ReelsController.php';
require_once 'C:/xampp/htdocs/RaceConnect/controllers/MarketplaceItemController.php';
require_once 'C:/xampp/htdocs/RaceConnect/controllers/NotificationController.php';
require_once 'C:/xampp/htdocs/RaceConnect/controllers/AdminController.php';
require_once 'C:/xampp/htdocs/RaceConnect/controllers/AdminAnalyticsController.php';

$path = explode('/', trim($_SERVER['PATH_INFO'], '/'));
$resource = $path[0] ?? null;
$id = $path[1] ?? null;
$method = $_SERVER['REQUEST_METHOD'];

if (!$conn) {
    echo json_encode(['success' => false, 'message' => 'Database connection failed']);
    exit;
}

if ($resource === 'users') {
    $controller = new UserController($conn);

    if (isset($_GET['action'])) {
        if ($_GET['action'] === 'login') {
            $controller->processRequest('POST');
        } elseif ($_GET['action'] === 'logout') {
            $controller->processRequest('POST');
        }
    } else {
        $controller->processRequest($method, $user_id);
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
}  else {
    echo json_encode(['success' => false, 'message' => 'Resource or user_id is missing']);
}
?>
