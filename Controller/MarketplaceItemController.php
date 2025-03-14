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
        error_log("Method: $method, ID: $id, Action: $action");
        try {
            $data = json_decode(file_get_contents("php://input"), true) ?? [];
    
            switch ($method) {
                case 'GET':
                    if ($action === 'images' && $id) {
                        $this->handleGetItemImagesRequest($id);
                    } elseif ($action === 'user' && $id) {
                        $this->handleGetItemsByUserRequest($id);
                    } else {
                        $this->handleGetRequest($id);
                    }
                    break;
                case 'POST':
                    if ($action === 'images' && $id) {
                        $this->handleUploadItemImagesRequest($id);
                    } else {
                        $this->handlePostRequest();
                    }
                    break;
                case 'PUT':
                    $this->handlePutRequest($id, $data);
                    break;
                case 'DELETE':
                    $this->handleDeleteRequest($id);
                    break;
                default:
                    $this->respond(405, ['message' => 'Method Not Allowed']);
            }
        } catch (Exception $e) {
            error_log("Server error: " . $e->getMessage() . "\n" . $e->getTraceAsString());
            $this->respond(500, ['message' => 'Internal Server Error', 'error' => $e->getMessage()]);
        }
    }

    private function handleUploadItemImagesRequest($id) {
        try {
            if (!$id || !is_numeric($id)) {
                $this->respond(400, ['message' => 'Valid Item ID is required']);
                return;
            }
    
            $imageUrls = $this->handleImageUpload($id);
            if (empty($imageUrls)) {
                $this->respond(400, ['message' => 'No valid images uploaded']);
                return;
            }
    
            $this->respond(200, [
                'message' => 'Images uploaded successfully',
                'image_urls' => $imageUrls
            ]);
        } catch (Exception $e) {
            error_log("Image upload failed: " . $e->getMessage());
            $this->respond(500, ['message' => 'Internal Server Error', 'error' => $e->getMessage()]);
        }
    }

    private function handleGetRequest($id) {
        try {
            if ($id) {
                $item = $this->item->getItemById($id);
                if (!$item) {
                    $this->respond(404, ['message' => 'Item not found']);
                    return;
                }
                $this->respond(200, $item);
            } else {
                $limit = max(1, min(100, (int)($_GET['limit'] ?? 10)));
                $offset = max(0, (int)($_GET['offset'] ?? 0));
                $excludeSellerId = isset($_GET['exclude_seller_id']) && is_numeric($_GET['exclude_seller_id']) 
                    ? (int)$_GET['exclude_seller_id'] 
                    : null;
    
                $items = $this->item->getAllItems($limit, $offset, $excludeSellerId);
                $this->respond(200, $items);
            }
        } catch (Exception $e) {
            throw new Exception("GET request failed: " . $e->getMessage());
        }
    }

    private function handleGetItemsByUserRequest($userId) {
        try {
            $limit = max(1, min(100, (int)($_GET['limit'] ?? 10)));
            $offset = max(0, (int)($_GET['offset'] ?? 0));
            
            if (!is_numeric($userId)) {
                $this->respond(400, ['message' => 'Invalid user ID']);
                return;
            }
    
            error_log("Fetching items for user ID: $userId with limit $limit and offset $offset");
            $items = $this->item->getItemByUserId($userId, $limit, $offset);
            error_log("Fetched items count: " . count($items));
            if (empty($items)) {
                $this->respond(200, []); // Return empty list instead of 404
                return;
            }
            $this->respond(200, $items);
        } catch (Exception $e) {
            error_log("Get items by user failed: " . $e->getMessage());
            $this->respond(500, ['message' => 'Internal Server Error', 'error' => $e->getMessage()]);
        }
    }

    private function handlePostRequest() {
        try {
            $data = $_POST ?: json_decode(file_get_contents("php://input"), true);
            
            if (!$this->validateItemData($data, true)) {
                $this->respond(400, ['message' => 'Invalid or missing required fields']);
                return;
            }
            
            $itemId = $this->item->createItem($data);
            if (!$itemId) {
                throw new Exception('Failed to create item');
            }
            
            $imageUrls = $this->handleImageUpload($itemId);
            $this->respond(201, [
                'message' => 'Item created successfully',
                'item_id' => $itemId,
                'image_urls' => $imageUrls
            ]);
        } catch (Exception $e) {
            throw new Exception("POST request failed: " . $e->getMessage());
        }
    }

    private function handlePutRequest($id, $data) {
        try {
            if (!$id || !is_numeric($id)) {
                $this->respond(400, ['message' => 'Valid Item ID is required']);
                return;
            }
            
            if (!$this->validateItemData($data, false)) {
                $this->respond(400, ['message' => 'Invalid update data']);
                return;
            }
            
            // Handle image uploads if present in PUT request
            $imageUrls = [];
            if (!empty($_FILES['image']) && is_array($_FILES['image']['name'])) {
                $imageUrls = $this->handleImageUpload($id);
            }

            if (!$this->item->updateItem($id, $data)) {
                $this->respond(404, ['message' => 'Item not found or no changes made']);
                return;
            }
            
            $this->respond(200, [
                'message' => 'Item updated successfully',
                'image_urls' => $imageUrls
            ]);
        } catch (Exception $e) {
            throw new Exception("PUT request failed: " . $e->getMessage());
        }
    }

    private function handleDeleteRequest($id) {
        try {
            if (!$id || !is_numeric($id)) {
                $this->respond(400, ['message' => 'Valid Item ID is required']);
                return;
            }
            
            if (!$this->item->deleteItem($id)) {
                $this->respond(404, ['message' => 'Item not found']);
                return;
            }
            
            $this->respond(200, ['message' => 'Item deleted successfully']);
        } catch (Exception $e) {
            throw new Exception("DELETE request failed: " . $e->getMessage());
        }
    }

    private function validateItemData($data, $isNew = true) {
        $requiredFields = ['seller_id', 'title', 'description', 'price', 'category'];
        
        if ($isNew) {
            foreach ($requiredFields as $field) {
                if (!isset($data[$field]) || empty(trim($data[$field]))) {
                    return false;
                }
            }
        }
        
        $validStatuses = ['Active', 'Hidden', 'Archived'];
        return (!empty($data['title']) || !$isNew) &&
               (!empty($data['description']) || !$isNew) &&
               (!isset($data['price']) || (is_numeric($data['price']) && $data['price'] >= 0)) &&
               (!isset($data['status']) || in_array($data['status'], $validStatuses));
    }

    private function handleImageUpload($itemId) {
        $imageUrls = [];
    
        error_log("Files received: " . json_encode($_FILES));
    
        if (empty($_FILES['image']) || !is_array($_FILES['image']['name'])) {
            error_log("No valid image files found in $_FILES[image]");
            return $imageUrls;
        }
        
        try {
            $fileCount = count($_FILES['image']['name']);
            error_log("Processing $fileCount image files");
            for ($i = 0; $i < $fileCount; $i++) {
                if ($_FILES['image']['error'][$i] === UPLOAD_ERR_OK) {
                    $imageData = file_get_contents($_FILES['image']['tmp_name'][$i]);
                    $imageName = $_FILES['image']['name'][$i];
                    
                    error_log("Uploading image: $imageName");
                    $imageUrl = $this->item->uploadItemImageToS3($imageData, $imageName);
                    if ($imageUrl && $this->item->saveItemImage($itemId, $imageUrl)) {
                        $imageUrls[] = $imageUrl;
                        error_log("Image uploaded successfully: $imageUrl");
                    } else {
                        error_log("Failed to save image to database or S3: $imageName");
                    }
                } else {
                    error_log("Upload error for image[$i]: " . $_FILES['image']['error'][$i]);
                }
            }
        } catch (Exception $e) {
            error_log("Image upload failed: " . $e->getMessage());
        }
    
        return $imageUrls;
    }

    private function handleGetItemImagesRequest($itemId) {
        try {
            if (!is_numeric($itemId)) {
                $this->respond(400, ['message' => 'Valid Item ID is required']);
                return;
            }
            
            $images = $this->item->getItemImages($itemId);
            $this->respond($images ? 200 : 404, 
                $images ?: ['message' => 'No images found for this item']);
        } catch (Exception $e) {
            throw new Exception("Get item images failed: " . $e->getMessage());
        }
    }

    private function respond($code, $data) {
        http_response_code($code);
        header('Content-Type: application/json');
        echo json_encode($data);
        exit;
    }
}