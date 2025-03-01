<?php

namespace Controller;
use Model\MarketplaceItem;
use Exception;

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../Model/MarketplaceItem.php';

class MarketplaceItemController {
    private $item;

    public function __construct($db) {
        $this->item = new MarketplaceItem($db);
    }

    public function processRequest($method, $id = null, $action = null) {
        try {
            $data = json_decode(file_get_contents("php://input"), true) ?? [];
    
            switch ($method) {
                case 'GET':
                    if ($action === 'images' && $id) {
                        $this->handleGetItemImagesRequest($id);
                    } else {
                        $this->handleGetRequest($id);
                    }
                    break;
                case 'POST':
                    $this->handlePostRequest();
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
            error_log("Server error: " . $e->getMessage());
            http_response_code(500);
            echo json_encode(['message' => 'Internal Server Error', 'error' => $e->getMessage()]);
        }
    }

    private function handleGetRequest($id) {
        try {
            if ($id) {
                $item = $this->item->getItemById($id);
                if (!$item) {
                    http_response_code(404);
                    echo json_encode(['message' => 'Item not found']);
                    return;
                }
                http_response_code(200);
                echo json_encode($item);
            } else {
                $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 10;
                $offset = isset($_GET['offset']) ? (int)$_GET['offset'] : 0;
                
                if ($limit <= 0 || $offset < 0) {
                    http_response_code(400);
                    echo json_encode(['message' => 'Invalid limit or offset values']);
                    return;
                }
                
                $items = $this->item->getAllItems($limit, $offset);
                http_response_code(200);
                echo json_encode($items);
            }
        } catch (Exception $e) {
            error_log("GET request failed: " . $e->getMessage());
            http_response_code(500);
            echo json_encode(['message' => 'Failed to retrieve items', 'error' => $e->getMessage()]);
        }
    }

    private function handlePostRequest() {
        try {
            $data = $_POST ?: json_decode(file_get_contents("php://input"), true);
            
            if (!$this->validateItemData($data, true)) {
                http_response_code(400);
                echo json_encode(['message' => 'Invalid input data']);
                return;
            }
            
            $itemId = $this->item->createItem($data);
            if (!$itemId || $itemId == 0) {
                throw new Exception('Failed to create item.');
            }
            error_log("Generated Post ID: " . $itemId); // Debugging: Check if the ID is valid
            
            $imageUrls = $this->handleImageUpload($itemId);
            http_response_code(201);
            echo json_encode(['message' => 'Item created successfully', 'item_id' => $itemId, 'image_urls' => $imageUrls]);
        } catch (Exception $e) {
            error_log("POST request failed: " . $e->getMessage());
            http_response_code(500);
            echo json_encode(['message' => 'Failed to create item', 'error' => $e->getMessage()]);
        }
    }

    private function handlePutRequest($id, $data) {
        try {
            if (!$id) {
                http_response_code(400);
                echo json_encode(['message' => 'Item ID is required']);
                return;
            }
            
            if (!$this->validateItemData($data, false)) {
                http_response_code(400);
                echo json_encode(['message' => 'Invalid input data for update']);
                return;
            }
            
            if (!$this->item->updateItem($id, $data)) {
                throw new Exception("Failed to update item.");
            }
            
            http_response_code(200);
            echo json_encode(['message' => 'Item updated successfully']);
        } catch (Exception $e) {
            error_log("PUT request failed: " . $e->getMessage());
            http_response_code(500);
            echo json_encode(['message' => 'Failed to update item', 'error' => $e->getMessage()]);
        }
    }

    private function handleDeleteRequest($id) {
        try {
            if (!$id) {
                http_response_code(400);
                echo json_encode(['message' => 'Item ID is required']);
                return;
            }
            
            if (!$this->item->deleteItem($id)) {
                throw new Exception("Failed to delete item.");
            }
            
            http_response_code(200);
            echo json_encode(['message' => 'Item deleted successfully']);
        } catch (Exception $e) {
            error_log("DELETE request failed: " . $e->getMessage());
            http_response_code(500);
            echo json_encode(['message' => 'Failed to delete item', 'error' => $e->getMessage()]);
        }
    }

    private function validateItemData($data, $isNew = true) {
        if ($isNew && (!isset($data['seller_id'], $data['title'], $data['description'], $data['price']))) {
            return false;
        }
        return !empty($data['title']) && !empty($data['description']) && is_numeric($data['price']) && $data['price'] > 0;
    }

    private function handleImageUpload($itemId) {
        $imageUrls = [];

        if (empty($_FILES['image']['name'])) {
            return $imageUrls;
        }
        
        try {
            foreach ($_FILES['image']['tmp_name'] as $index => $tmpName) {
                if ($_FILES['image']['error'][$index] === UPLOAD_ERR_OK) {
                    $imageData = file_get_contents($tmpName);
                    $imageName = uniqid() . '-' . basename($_FILES['image']['name'][$index]);
                    $imageUrl = $this->item->uploadItemImageToS3($imageData, $imageName);
                    
                    if ($imageUrl) {
                        $imageUrls[] = $imageUrl;
                        $this->item->saveItemImage($itemId, $imageUrl);
                    }
                }
            }
        } catch (Exception $e) {
            error_log("Image upload failed: " . $e->getMessage());
        }

        return $imageUrls;
    }

    private function handleGetItemImagesRequest($itemId) {
        try {
            $images = $this->item->getItemImages($itemId);
            if ($images) {
                http_response_code(200);
                echo json_encode($images);
            } else {
                http_response_code(404);
                echo json_encode(['message' => 'No images found for this item']);
            }
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode(['message' => 'Failed to retrieve item images', 'error' => $e->getMessage()]);
        }
    }
}