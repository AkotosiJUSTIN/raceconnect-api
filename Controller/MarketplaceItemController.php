<?php

namespace Controller;
use Model\MarketplaceItem;
use Exception;

require_once 'C:/xampp/htdocs/raceconnectapi/Model/MarketplaceItem.php';

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
                    if (!$this->validateItemData($data)) {
                        http_response_code(400);
                        echo json_encode(['message' => 'Invalid input data. Required: title, price, description, seller_id']);
                        return;
                    }

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

                    if (empty($data)) {
                        http_response_code(400);
                        echo json_encode(['message' => 'No data provided for update']);
                        return;
                    }

                    if (isset($data['title']) && strlen($data['title']) < 3) {
                        http_response_code(400);
                        echo json_encode(['message' => 'Item title must be at least 3 characters long']);
                        return;
                    }

                    if (isset($data['price']) && (!is_numeric($data['price']) || $data['price'] <= 0)) {
                        http_response_code(400);
                        echo json_encode(['message' => 'Price must be a positive number']);
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

    private function validateItemData($data) {
        return isset($data['title'], $data['price'], $data['description'], $data['seller_id'])
            && !empty($data['title']) 
            && !empty($data['description'])
            && !empty($data['seller_id'])
            && is_numeric($data['price']) && $data['price'] > 0
            && strlen($data['title']) >= 1;
    }
}
