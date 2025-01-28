<?php
//CHANGE THE PATHS TO YOUR OWN PATHS
require_once 'C:/xampp/htdocs/RaceConnect/models/MarketplaceItem.php';

class MarketplaceItemController {
    private $item;

    public function __construct($db) {
        $this->item = new MarketplaceItem($db);
    }

    public function processRequest($method, $id = null) {
        switch ($method) {
            case 'GET':
                if ($id) {
                    $item = $this->item->getItemById($id);
                    echo json_encode($item ? ['data' => $item] : ['message' => 'Item not found']);
                } else {
                    echo json_encode(['data' => $this->item->getAllItems()]);
                }
                break;

            case 'POST':
                $data = json_decode(file_get_contents("php://input"), true);
                if ($this->item->createItem($data)) {
                    echo json_encode(['message' => 'Item created successfully']);
                } else {
                    echo json_encode(['message' => 'Failed to create item']);
                }
                break;

            case 'PUT':
                if ($id) {
                    $data = json_decode(file_get_contents("php://input"), true);
                    if ($this->item->updateItem($id, $data)) {
                        echo json_encode(['message' => 'Item updated successfully']);
                    } else {
                        echo json_encode(['message' => 'Failed to update item']);
                    }
                } else {
                    echo json_encode(['message' => 'Item ID is required']);
                }
                break;

            case 'DELETE':
                if ($id) {
                    if ($this->item->deleteItem($id)) {
                        echo json_encode(['message' => 'Item deleted successfully']);
                    } else {
                        echo json_encode(['message' => 'Failed to delete item']);
                    }
                } else {
                    echo json_encode(['message' => 'Item ID is required']);
                }
                break;

            default:
                echo json_encode(['message' => 'Unsupported HTTP method']);
        }
    }
}
?>
