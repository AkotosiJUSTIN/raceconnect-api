<?php
//CHANGE THE PATHS TO YOUR OWN PATHS
require_once 'C:/xampp/htdocs/RaceConnect/models/User.php';

class UserController {
    private $user;

    public function __construct($db) {
        $this->user = new User($db);
    }

    public function processRequest($method, $id = null) {
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
                    echo json_encode($user ? ['success' => true, 'data' => $user] : ['success' => false, 'message' => 'User not found']);
                } else {
                    echo json_encode(['success' => true, 'data' => $this->user->getAllUsers()]);
                }
                break;

            case 'POST':
                if ($this->user->createUser($data)) {
                    echo json_encode(['success' => true, 'message' => 'User created successfully']);
                } else {
                    echo json_encode(['success' => false, 'message' => 'Failed to create user']);
                }
                break;

            case 'PUT':
                if ($id) {
                    if ($this->user->updateUser($id, $data)) {
                        echo json_encode(['success' => true, 'message' => 'User updated successfully']);
                    } else {
                        echo json_encode(['success' => false, 'message' => 'Failed to update user']);
                    }
                } else {
                    echo json_encode(['success' => false, 'message' => 'User ID is required']);
                }
                break;

            case 'DELETE':
                if ($id) {
                    if ($this->user->deleteUser($id)) {
                        echo json_encode(['success' => true, 'message' => 'User deleted successfully']);
                    } else {
                        echo json_encode(['success' => false, 'message' => 'Failed to delete user']);
                    }
                } else {
                    echo json_encode(['success' => false, 'message' => 'User ID is required']);
                }
                break;

            default:
                echo json_encode(['success' => false, 'message' => 'Unsupported HTTP method']);
        }
    }

    public function login($data) {
        if (!isset($data['username'], $data['password'])) {
            echo json_encode(['success' => false, 'message' => 'Username and password are required']);
            return;
        }

        $user = $this->user->loginUser($data['username'], $data['password']);
        if ($user) {
            // Generate a token
            $token = base64_encode(random_bytes(32));

            // Save the token in the database
            if ($this->user->storeToken($user['id'], $token)) {
                echo json_encode([
                    'success' => true,
                    'message' => 'Login successful',
                    'data' => [
                        'user' => [
                            'id' => $user['id'],
                            'username' => $user['username'],
                            'email' => $user['email']
                        ],
                        'token' => $token
                    ]
                ]);
            } else {
                echo json_encode(['success' => false, 'message' => 'Failed to store token']);
            }
        } else {
            echo json_encode(['success' => false, 'message' => 'Invalid username or password']);
        }
    }

    public function logout() {
        $headers = apache_request_headers();
        $authHeader = $headers['Authorization'] ?? null;

        if ($authHeader) {
            list($type, $token) = explode(" ", $authHeader, 2);
            if ($type === "Bearer" && $this->user->revokeToken($token)) {
                echo json_encode(['success' => true, 'message' => 'Logout successful']);
            } else {
                echo json_encode(['success' => false, 'message' => 'Invalid token']);
            }
        } else {
            echo json_encode(['success' => false, 'message' => 'Authorization header missing']);
        }
    }
}
?>
