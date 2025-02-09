<?php

namespace Controller;
use Model\User;
use Middleware\AuthMiddleware;
use Exception;

require_once 'C:/xampp/htdocs/raceconnectapi/Model/User.php';
require_once 'C:/xampp/htdocs/raceconnectapi/Middleware/AuthMiddleware.php';

class AuthController {
    private $user;
    private $authMiddleware;

    public function __construct($db) {
        $this->user = new User($db);
        $this->authMiddleware = new AuthMiddleware($db);
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
                if ($this->authMiddleware->storeToken($user['id'], $token)) {
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
                    $tokenData = $this->authMiddleware->validateToken($token);

                    if ($tokenData) {
                        if ($this->authMiddleware->revokeToken($token)) {
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