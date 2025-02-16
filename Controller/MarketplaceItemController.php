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

    public function processRequest($method, $id = null) {
        try {
            $data = json_decode(file_get_contents("php://input"), true);

            switch ($method) {
                case 'GET':
                    if ($id) {
                        $item = $this->item->getItemById($id);
                        if ($item) {
                            http_response_code(200);
                            echo json_encode($item);
                        } else {
                            http_response_code(404);
                            echo json_encode(['message' => 'Item not found']);
                        }
                    } else {
                        http_response_code(200);
                        echo json_encode($this->item->getAllItems());
                    }
                    break;

                case 'POST':
                    if (!$this->validateItemData($data, true)) {
                        http_response_code(400);
                        echo json_encode(['message' => 'Invalid input data. Required: seller_id, title, description, price, category (optional), image_url (optional), status (optional)']);
                        return;
                    }

                    // Set default values for optional fields
                    $data['category'] = $data['category'] ?? 'Formula 1';
                    $data['image_url'] = $data['image_url'] ?? null;
                    $data['favorite_count'] = 0;
                    $data['status'] = $data['status'] ?? 'available';

                    if ($this->item->createItem($data)) {
                        http_response_code(201);
                        echo json_encode(['message' => 'Item created successfully']);
                    } else {
                        http_response_code(500);
                        echo json_encode(['message' => 'Failed to create item']);
                    }
                    break;

                case 'PUT':
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

                    if ($this->item->updateItem($id, $data)) {
                        http_response_code(200);
                        echo json_encode(['message' => 'Item updated successfully']);
                    } else {
                        http_response_code(500);
                        echo json_encode(['message' => 'Failed to update item']);
                    }
                    break;

                case 'DELETE':
                    if (!$id) {
                        http_response_code(400);
                        echo json_encode(['message' => 'Item ID is required']);
                        return;
                    }

                    if ($this->item->deleteItem($id)) {
                        http_response_code(200);
                        echo json_encode(['message' => 'Item deleted successfully']);
                    } else {
                        http_response_code(500);
                        echo json_encode(['message' => 'Failed to delete item']);
                    }
                    break;

                default:
                    http_response_code(405);
                    echo json_encode(['message' => 'Unsupported HTTP method']);
            }
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode(['message' => 'Internal Server Error', 'error' => $e->getMessage()]);
        }
    }

    private function validateItemData($data, $isNew = true) {
        $validCategories = ['Formula 1', '24 Hours of Lemans', 'World Rally Championship', 'NASCAR', 'Formula Drift', 'GT Championship'];
        $validStatuses = ['available', 'sold', 'reserved'];

        if ($isNew) {
            // Required fields for creating an item
            if (!isset($data['seller_id'], $data['title'], $data['description'], $data['price'])) {
                return false;
            }
        }

        if (isset($data['title']) && strlen($data['title']) < 1) {
            return false;
        }

        if (isset($data['description']) && empty($data['description'])) {
            return false;
        }

        if (isset($data['price']) && (!is_numeric($data['price']) || $data['price'] <= 0)) {
            return false;
        }

        if (isset($data['favorite_count']) && (!is_numeric($data['favorite_count']) || $data['favorite_count'] < 0)) {
            return false;
        }

        if (isset($data['category']) && !in_array($data['category'], $validCategories)) {
            return false;
        }

        if (isset($data['status']) && !in_array($data['status'], $validStatuses)) {
            return false;
        }

        return true;
    }

    public function uploadItemImage() {
        // Debugging - Log request data
        error_log(print_r($_POST, true));
        error_log(print_r($_FILES, true));
    
        // Validate input
        if (!isset($_POST['item_id']) || empty($_POST['item_id']) || !isset($_FILES['image']) || empty($_FILES['image']['name'][0])) {
            http_response_code(400);
            echo json_encode(['message' => 'Item ID and at least one image are required']);
            return;
        }
    
        try {
            $itemId = $_POST['item_id'];
            $imageUrls = [];
    
            // Ensure images are uploaded correctly
            if (is_array($_FILES['image']['name'])) {
                $fileCount = count($_FILES['image']['name']); // Count number of uploaded files
    
                for ($i = 0; $i < $fileCount; $i++) {
                    if ($_FILES['image']['error'][$i] === UPLOAD_ERR_OK) {
                        $tmpName = $_FILES['image']['tmp_name'][$i];
                        $imageData = file_get_contents($tmpName);
                        $imageName = uniqid() . '-' . basename($_FILES['image']['name'][$i]);
    
                        // Upload to S3
                        $imageUrl = $this->item->uploadItemImageToS3($imageData, $imageName);
                        $imageUrls[] = $imageUrl;
    
                        // Save to database
                        $this->item->saveItemImage($itemId, $imageUrl);
                    }
                }
            } else {
                // Handle single image upload
                if ($_FILES['image']['error'] === UPLOAD_ERR_OK) {
                    $imageData = file_get_contents($_FILES['image']['tmp_name']);
                    $imageName = uniqid() . '-' . basename($_FILES['image']['name']);
    
                    // Upload to S3
                    $imageUrl = $this->item->uploadItemImageToS3($imageData, $imageName);
                    $imageUrls[] = $imageUrl;
    
                    // Save to database
                    $this->item->saveItemImage($itemId, $imageUrl);
                }
            }
    
            http_response_code(200);
            echo json_encode([
                'message' => 'Item images uploaded successfully',
                'image_urls' => $imageUrls
            ]);
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode(['message' => 'Failed to upload item images', 'error' => $e->getMessage()]);
        }
    }
    
    
    
}
