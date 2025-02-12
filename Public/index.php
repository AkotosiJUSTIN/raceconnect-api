<?php

require_once 'C:/xampp/htdocs/raceconnectapi/vendor/autoload.php';
require_once '../Config/database.php';
require_once '../Controller/UserController.php';
require_once '../Controller/PostController.php';
require_once '../Controller/MarketplaceItemController.php';
require_once '../Controller/NotificationController.php';
require_once '../Controller/admin/AdminController.php';
require_once '../Controller/admin/AdminAnalyticsController.php';
require_once '../Controller/like/PostLikeController.php';
require_once '../Controller/like/MarketplaceItemLikeController.php';
require_once '../Controller/comment/PostCommentController.php';
require_once '../Controller/repost/PostRepostController.php';
require_once '../Controller/AuthController.php';
require_once '../Middleware/AuthMiddleware.php';
require_once '../Routes/Api.php';

$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$path = trim($path, '/');
$path = explode('/', $path);
$method = $_SERVER['REQUEST_METHOD'];

$router = new Api($conn);
$router->route($path, $method);

?>
