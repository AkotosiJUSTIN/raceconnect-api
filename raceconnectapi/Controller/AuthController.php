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
            if (empty($data['username']) || empty($data['password'])) {
                http_response_code(400);
                echo json_encode(['message' => 'Username and password are required']);
                return;
            }

            $user = $this->user->loginUser($data['username'], $data['password']);

            if (!$user) {
                http_response_code(401);
                echo json_encode(['message' => 'Invalid username or password']);
                return;
            }

            // Generate a secure token
            $token = bin2hex(random_bytes(32));

            // Save the token in the database
            if (!$this->authMiddleware->storeToken($user['id'], $token)) {
                http_response_code(500);
                echo json_encode(['message' => 'Failed to store token']);
                return;
            }

            http_response_code(200);
            echo json_encode([
                'message' => 'Login successful',
                'user' => [
                    'id' => $user['id'],
                    'username' => $user['username'],
                    'email' => $user['email']
                ],
                'token' => $token
            ]);

        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode(['message' => 'An error occurred during login', 'error' => $e->getMessage()]);
        }
    }

    public function logout() {
        try {
            $headers = apache_request_headers();
            $authHeader = $headers['Authorization'] ?? '';

            if (empty($authHeader)) {
                http_response_code(400);
                echo json_encode(['message' => 'Authorization header is missing']);
                return;
            }

            if (!preg_match('/^Bearer\s(\S+)$/', $authHeader, $matches)) {
                http_response_code(400);
                echo json_encode(['message' => 'Invalid token format']);
                return;
            }

            $token = $matches[1];

            // Validate token
            if (!$this->authMiddleware->validateToken($token)) {
                http_response_code(401);
                echo json_encode(['message' => 'Invalid or expired token']);
                return;
            }

            // Revoke token
            if (!$this->authMiddleware->revokeToken($token)) {
                http_response_code(500);
                echo json_encode(['message' => 'Failed to revoke token']);
                return;
            }

            http_response_code(200);
            echo json_encode(['message' => 'Logout successful']);

        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode(['message' => 'An error occurred during logout', 'error' => $e->getMessage()]);
        }
    }
}
?>
