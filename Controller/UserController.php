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
                        if ($user) {
                            http_response_code(200);
                            echo json_encode($user);
                        } else {
                            http_response_code(404);
                            echo json_encode(['message' => 'User not found']);
                        }
                    } else {
                        http_response_code(200);
                        echo json_encode($this->user->getAllUsers());
                    }
                    break;

                case 'POST':
                    if (empty($data) || !isset($data['username'], $data['email'], $data['password'])) {
                        http_response_code(400);
                        echo json_encode(['message' => 'Missing required fields: username, email, password']);
                        return;
                    }

                    if (!filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
                        http_response_code(400);
                        echo json_encode(['message' => 'Invalid email format']);
                        return;
                    }

                    if (strlen($data['password']) < 6) {
                        http_response_code(400);
                        echo json_encode(['message' => 'Password must be at least 6 characters long']);
                        return;
                    }

                    if ($this->user->createUser($data)) {
                        http_response_code(201);
                        echo json_encode(['message' => 'User created successfully']);
                    } else {
                        http_response_code(500);
                        echo json_encode(['message' => 'Failed to create user']);
                    }
                    break;

                case 'PUT':
                    if (!$id) {
                        http_response_code(400);
                        echo json_encode(['message' => 'User ID is required']);
                        return;
                    }

                    if (empty($data)) {
                        http_response_code(400);
                        echo json_encode(['message' => 'No data provided for update']);
                        return;
                    }

                    if (isset($data['email']) && !filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
                        http_response_code(400);
                        echo json_encode(['message' => 'Invalid email format']);
                        return;
                    }

                    if (isset($data['password']) && strlen($data['password']) < 6) {
                        http_response_code(400);
                        echo json_encode(['message' => 'Password must be at least 6 characters long']);
                        return;
                    }

                    if ($this->user->updateUser($id, $data)) {
                        http_response_code(200);
                        echo json_encode(['message' => 'User updated successfully']);
                    } else {
                        http_response_code(500);
                        echo json_encode(['message' => 'Failed to update user']);
                    }
                    break;

                case 'DELETE':
                    if (!$id) {
                        http_response_code(400);
                        echo json_encode(['message' => 'User ID is required']);
                        return;
                    }

                    if ($this->user->deleteUser($id)) {
                        http_response_code(200);
                        echo json_encode(['message' => 'User deleted successfully']);
                    } else {
                        http_response_code(500);
                        echo json_encode(['message' => 'Failed to delete user']);
                    }
                    break;

                default:
                    http_response_code(405);
                    echo json_encode(['message' => 'Unsupported HTTP method']);
            }
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode(['message' => 'An error occurred', 'error' => $e->getMessage()]);
        }
    }
}
?>
