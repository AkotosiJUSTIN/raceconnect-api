<?php

namespace Controller\Like;

use Model\Like\MarketplaceItemLike;
use InvalidArgumentException;
use RuntimeException;
use Exception;

require_once 'C:/xampp/htdocs/raceconnectapi/vendor/autoload.php';

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
                        // Get like count if requested
                        if (isset($_GET['count'])) {
                            $count = $this->marketplaceItemLike->getLikeCount($id);
                            echo json_encode($count);
                        } else {
                            $likes = $this->marketplaceItemLike->getLikesByItemId($id);
                            if ($likes) {
                                echo json_encode($likes);
                            } else {
                                http_response_code(404);
                                echo json_encode(['message' => 'No likes found for this item']);
                            }
                        }
                    } else {
                        $likes = $this->marketplaceItemLike->getAllLikes();
                        if ($likes) {
                            echo json_encode($likes);
                        } else {
                            http_response_code(404);
                            echo json_encode(['message' => 'No likes found']);
                        }
                    }
                    break;

                case 'POST':
                    $data = json_decode(file_get_contents("php://input"), true);
                    
                    // Validate input data
                    if (empty($data['user_id']) || empty($data['marketplace_item_id']) || empty($data['owner_id'])) {
                        http_response_code(400);
                        echo json_encode(['message' => 'Missing required fields: user_id, marketplace_item_id, owner_id']);
                        return;
                    }

                    // Prevent duplicate likes
                    if ($this->marketplaceItemLike->hasUserLiked($data['user_id'], $data['marketplace_item_id'])) {
                        http_response_code(409);
                        echo json_encode(['message' => 'User has already liked this item']);
                        return;
                    }

                    // Add like
                    if ($this->marketplaceItemLike->createLike($data)) {
                        http_response_code(201);
                        echo json_encode(['message' => 'Like added successfully']);
                    } else {
                        http_response_code(500);
                        echo json_encode(['message' => 'Failed to add like']);
                    }
                    break;

                case 'DELETE':
                    if (!$id) {
                        http_response_code(400);
                        echo json_encode(['message' => 'Like ID is required']);
                        return;
                    }

                    if ($this->marketplaceItemLike->deleteLike($id)) {
                        echo json_encode(['message' => 'Like deleted successfully']);
                    } else {
                        http_response_code(500);
                        echo json_encode(['message' => 'Failed to delete like']);
                    }
                    break;

                default:
                    http_response_code(405);
                    echo json_encode(['message' => 'Unsupported HTTP method']);
            }
        } catch (InvalidArgumentException $e) {
            http_response_code(400);
            echo json_encode(['message' => 'Invalid argument', 'error' => $e->getMessage()]);
        } catch (RuntimeException $e) {
            http_response_code(500);
            echo json_encode(['message' => 'Runtime error', 'error' => $e->getMessage()]);
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode(['message' => 'An error occurred', 'error' => $e->getMessage()]);
        }
    }
}
?>
