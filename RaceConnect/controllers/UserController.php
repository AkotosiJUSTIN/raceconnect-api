<?php
//CHANGE THE PATHS TO YOUR OWN PATHS
require_once 'C:/xampp/htdocs/RaceConnect/models/User.php';

class UserController {
    private $user;

    public function __construct($db) {
        $this->user = new User($db);
    }

    public function processRequest($method, $id = null) {
        try {
            $data = json_decode(file_get_contents("php://input"), true);

            if ($method === 'POST' && isset($_GET['action'])) {
                if ($_GET['action'] === 'login') {
                    $this->login($data);
                    return;
                } elseif ($_GET['action'] === 'logout') {
                    $this->logout();
                    return;
                }
            }

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

    public function login($data) {
        try {
            if (!isset($data['username'], $data['password'])) {
                echo json_encode(['message' => 'Username and password are required']);
                return;
            }

            $user = $this->user->loginUser($data['username'], $data['password']);
            if ($user) {
                // Generate a token
                $token = base64_encode(random_bytes(32));

                // Save the token in the database
                if ($this->user->storeToken($user['id'], $token)) {
                    echo json_encode([
                        'message' => 'Login successful',
                        'user' => [
                            'id' => $user['id'],
                            'username' => $user['username'],
                            'email' => $user['email']
                        ],
                        'token' => $token
                    ]);
                } else {
                    echo json_encode(['message' => 'Failed to store token']);
                }
            } else {
                echo json_encode(['message' => 'Invalid username or password']);
            }
        } catch (Exception $e) {
            echo json_encode(['message' => 'An error occurred during login', 'error' => $e->getMessage()]);
        }
    }

    public function logout() {
        try {
            $headers = apache_request_headers();
            $authHeader = $headers['Authorization'] ?? null;

            if ($authHeader) {
                list($type, $token) = explode(" ", $authHeader, 2);

                if ($type === "Bearer") {
                    $tokenData = $this->user->validateToken($token);

                    if ($tokenData) {
                        if ($this->user->revokeToken($token)) {
                            echo json_encode(['message' => 'Logout successful']);
                        } else {
                            echo json_encode(['message' => 'Failed to revoke token']);
                        }
                    } else {
                        echo json_encode(['message' => 'Invalid token']);
                    }
                } else {
                    echo json_encode(['message' => 'Invalid token type']);
                }
            } else {
                echo json_encode(['message' => 'Authorization header missing']);
            }
        } catch (Exception $e) {
            echo json_encode(['message' => 'An error occurred during logout', 'error' => $e->getMessage()]);
        }
    }
}
?>
