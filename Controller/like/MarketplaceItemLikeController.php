<?php

namespace Controller\Like;

use Model\Like\MarketplaceItemLike;
use InvalidArgumentException;
use RuntimeException;
use Exception;

require_once __DIR__ . '/../../vendor/autoload.php';

class MarketplaceItemLikeController {
    private $marketplaceItemLike;

    public function __construct($db) {
        $this->marketplaceItemLike = new MarketplaceItemLike($db);
    }

    public function processRequest($method, $id = null) {
        try {
            switch ($method) {
                case 'GET':
                    if ($id) {
                        if (isset($_GET['count'])) {
                            $count = $this->marketplaceItemLike->getLikeCount($id);
                            $this->sendResponse(200, [
                                'status' => 'success',
                                'data' => $count,
                                'message' => null
                            ]);
                        } elseif (isset($_GET['user_ids'])) {
                            // New endpoint for multiple user IDs
                            $user_ids = array_map('intval', explode(',', $_GET['user_ids']));
                            $likedItems = $this->marketplaceItemLike->getLikedItemsByUserIds($user_ids);
                            $this->sendResponse(200, [
                                'status' => 'success',
                                'data' => $likedItems ?: new \stdClass(), // Empty object if no results
                                'message' => empty($likedItems) ? 'No liked items found for these users' : null
                            ]);
                        } elseif (isset($_GET['user_id']) && isset($_GET['liked_posts'])) {
                            $likedPosts = $this->marketplaceItemLike->getLikedItemsByUserIds($_GET['user_id']);
                            $this->sendResponse(200, [
                                'status' => 'success',
                                'data' => $likedPosts ?: [],
                                'message' => empty($likedPosts) ? 'No liked posts found' : null
                            ]);
                        } elseif (isset($_GET['user_id'])) {
                            $liked = $this->marketplaceItemLike->hasUserLiked($_GET['user_id'], $id);
                            $this->sendResponse(200, [
                                'status' => 'success',
                                'data' => ['liked' => (bool)$liked],
                                'message' => null
                            ]);
                        } else {
                            $likes = $this->marketplaceItemLike->getLikesByItemId($id);
                            $this->sendResponse(200, [
                                'status' => 'success',
                                'data' => $likes ?: [],
                                'message' => empty($likes) ? 'No likes found for this item' : null
                            ]);
                        }
                    } elseif (isset($_GET['user_id'])) {
                        $userLikes = $this->marketplaceItemLike->getUserLikedItems($_GET['user_id']);
                        $this->sendResponse(200, [
                            'status' => 'success',
                            'data' => $userLikes ?: [],
                            'message' => empty($userLikes) ? 'No liked items found' : null
                        ]);
                    } else {
                        $likes = $this->marketplaceItemLike->getAllLikes();
                        $this->sendResponse(200, [
                            'status' => 'success',
                            'data' => $likes ?: [],
                            'message' => empty($likes) ? 'No likes found' : null
                        ]);
                    }
                    break;

                case 'POST':
                    $data = json_decode(file_get_contents("php://input"), true);
                    if (!isset($data['user_id']) || !isset($data['marketplace_item_id']) || !isset($data['owner_id'])) {
                        $this->sendResponse(400, ['message' => 'Missing required fields: user_id, marketplace_item_id, owner_id']);
                        return;
                    }

                    $user_id = filter_var($data['user_id'], FILTER_VALIDATE_INT);
                    $marketplace_item_id = filter_var($data['marketplace_item_id'], FILTER_VALIDATE_INT);
                    $owner_id = filter_var($data['owner_id'], FILTER_VALIDATE_INT);

                    if ($user_id === false || $marketplace_item_id === false || $owner_id === false) {
                        $this->sendResponse(400, ['message' => 'Invalid field values: user_id, marketplace_item_id, and owner_id must be integers']);
                        return;
                    }

                    if ($user_id <= 0 || $marketplace_item_id <= 0) {
                        $this->sendResponse(400, ['message' => 'Invalid field values: user_id and marketplace_item_id must be positive integers']);
                        return;
                    }

                    $result = $this->marketplaceItemLike->toggleLike($user_id, $marketplace_item_id, $owner_id);
                    $this->sendResponse($result['status'], $result['data']);
                    break;

                case 'DELETE':
                    if ($id) {
                        $result = $this->marketplaceItemLike->deleteLike($id);
                        $this->sendResponse($result ? 200 : 500, $result ? ['message' => 'Like deleted successfully'] : ['message' => 'Failed to delete like']);
                    } else {
                        $this->sendResponse(400, ['message' => 'Like ID required']);
                    }
                    break;

                default:
                    $this->sendResponse(405, ['message' => 'Unsupported HTTP method']);
            }
        } catch (InvalidArgumentException $e) {
            $this->sendResponse(400, ['message' => 'Invalid argument', 'error' => $e->getMessage()]);
        } catch (RuntimeException $e) {
            $this->sendResponse(500, ['message' => 'Runtime error', 'error' => $e->getMessage()]);
        } catch (Exception $e) {
            $this->sendResponse(500, ['message' => 'An error occurred', 'error' => $e->getMessage()]);
        }
    }

    private function sendResponse($statusCode, $data) {
        http_response_code($statusCode);
        header('Content-Type: application/json');
        echo json_encode($data);
    }
}