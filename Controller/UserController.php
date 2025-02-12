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
                    if (isset($_GET['action']) && $_GET['action'] === 'login') {
                        $this->loginUser($data);
                    } else {
                        $this->createUser($data);
                    }
                    break;

                case 'PUT':
                    if (!$id) {
                        http_response_code(400);
                        echo json_encode(['message' => 'User ID is required']);
                        return;
                    }
                    $this->updateUser($id, $data);
                    break;

                case 'DELETE':
                    if (!$id) {
                        http_response_code(400);
                        echo json_encode(['message' => 'User ID is required']);
                        return;
                    }
                    $this->deleteUser($id);
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

    private function createUser($data) {
        if (!$this->validateUserInput($data, true)) return;

        if ($this->user->createUser($data)) {
            http_response_code(201);
            echo json_encode(['message' => 'User created successfully']);
        } else {
            http_response_code(500);
            echo json_encode(['message' => 'Failed to create user']);
        }
    }

    private function updateUser($id, $data) {
        if (!$this->validateUserInput($data, false)) return;

        if ($this->user->updateUser($id, $data)) {
            http_response_code(200);
            echo json_encode(['message' => 'User updated successfully']);
        } else {
            http_response_code(500);
            echo json_encode(['message' => 'Failed to update user']);
        }
    }

    private function deleteUser($id) {
        if ($this->user->deleteUser($id)) {
            http_response_code(200);
            echo json_encode(['message' => 'User deleted successfully']);
        } else {
            http_response_code(500);
            echo json_encode(['message' => 'Failed to delete user']);
        }
    }

    private function loginUser($data) {
        if (empty($data['username']) || empty($data['password'])) {
            http_response_code(400);
            echo json_encode(['message' => 'Username and password are required']);
            return;
        }

        $user = $this->user->loginUser($data['username'], $data['password']);
        if ($user) {
            $token = bin2hex(random_bytes(32));
            $this->user->storeToken($user['id'], $token);
            http_response_code(200);
            echo json_encode(['message' => 'Login successful', 'token' => $token, 'user' => $user]);
        } else {
            http_response_code(401);
            echo json_encode(['message' => 'Invalid credentials']);
        }
    }

    private function validateUserInput($data, $isNewUser) {
        $requiredFields = ['username', 'email', 'password'];

        if ($isNewUser) {
            foreach ($requiredFields as $field) {
                if (empty($data[$field])) {
                    http_response_code(400);
                    echo json_encode(['message' => "Missing required field: $field"]);
                    return false;
                }
            }
        }

        if (!empty($data['email']) && !filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
            http_response_code(400);
            echo json_encode(['message' => 'Invalid email format']);
            return false;
        }

        if (!empty($data['password']) && strlen($data['password']) < 6) {
            http_response_code(400);
            echo json_encode(['message' => 'Password must be at least 6 characters long']);
            return false;
        }

        return true;
    }
}

?>
