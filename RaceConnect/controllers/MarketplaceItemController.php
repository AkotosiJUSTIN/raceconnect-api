<?php
//CHANGE THE PATHS TO YOUR OWN PATHS
require_once 'C:/xampp/htdocs/RaceConnect/models/MarketplaceItem.php';

class MarketplaceItemController {
    private $item;

    public function __construct($db) {
        $this->item = new MarketplaceItem($db);
    }

    public function processRequest($method, $id = null) {
        try {
            switch ($method) {
            case 'GET':
                if ($id) {
                $item = $this->item->getItemById($id);
                if ($item) {
                    echo json_encode($item);
                } else {
                    http_response_code(404);
                    echo json_encode(['message' => 'Item not found']);
                }
                } else {
                echo json_encode($this->item->getAllItems());
                }
                break;

            case 'POST':
                $data = json_decode(file_get_contents("php://input"), true);
                if (json_last_error() !== JSON_ERROR_NONE) {
                http_response_code(400);
                echo json_encode(['message' => 'Invalid JSON data']);
                break;
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
                if ($id) {
                $data = json_decode(file_get_contents("php://input"), true);
                if (json_last_error() !== JSON_ERROR_NONE) {
                    http_response_code(400);
                    echo json_encode(['message' => 'Invalid JSON data']);
                    break;
                }
                if ($this->item->updateItem($id, $data)) {
                    echo json_encode(['message' => 'Item updated successfully']);
                } else {
                    http_response_code(500);
                    echo json_encode(['message' => 'Failed to update item']);
                }
                } else {
                http_response_code(400);
                echo json_encode(['message' => 'Item ID is required']);
                }
                break;

            case 'DELETE':
                if ($id) {
                if ($this->item->deleteItem($id)) {
                    echo json_encode(['message' => 'Item deleted successfully']);
                } else {
                    http_response_code(500);
                    echo json_encode(['message' => 'Failed to delete item']);
                }
                } else {
                http_response_code(400);
                echo json_encode(['message' => 'Item ID is required']);
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
}
