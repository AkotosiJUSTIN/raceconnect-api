<?php

namespace Controller;
use Model\User;
use Exception;

require_once 'C:/xampp/htdocs/raceconnectapi/Model/User.php';

class UserController {
    private $user;

    public function __construct($db) {
        $this->user = new User($db);
    }

    public function processRequest($method, $id = null) {
        try {
            $data = json_decode(file_get_contents("php://input"), true);

            switch ($method) {
                case 'GET':
                    if ($id) {
                        $user = $this->user->getUserById($id);
                        echo json_encode($user ? $user : ['message' => 'User not found']);
                    } else {
                        echo json_encode($this->user->getAllUsers());
                    }
                    break;

                case 'POST':
                    if ($this->user->createUser($data)) {
                        echo json_encode(['message' => 'User created successfully']);
                    } else {
                        echo json_encode(['message' => 'Failed to create user']);
                    }
                    break;

                case 'PUT':
                    if ($id) {
                        if ($this->user->updateUser($id, $data)) {
                            echo json_encode(['message' => 'User updated successfully']);
                        } else {
                            echo json_encode(['message' => 'Failed to update user']);
                        }
                    } else {
                        echo json_encode(['message' => 'User ID is required']);
                    }
                    break;

                case 'DELETE':
                    if ($id) {
                        if ($this->user->deleteUser($id)) {
                            echo json_encode(['message' => 'User deleted successfully']);
                        } else {
                            echo json_encode(['message' => 'Failed to delete user']);
                        }
                    } else {
                        echo json_encode(['message' => 'User ID is required']);
                    }
                    break;

                default:
                    echo json_encode(['message' => 'Unsupported HTTP method']);
            }
        } catch (Exception $e) {
            echo json_encode(['message' => 'An error occurred', 'error' => $e->getMessage()]);
        }
    }
}
?>
```
