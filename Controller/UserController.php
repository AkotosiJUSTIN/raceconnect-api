<?php

namespace Controller;
use Model\User;
use Exception;
use PDOException;

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../Model/User.php';

class UserController {
    private $user;

    public function __construct($db) {
        $this->user = new User($db);
    }

    public function processRequest($method, $id = null, $action = null) {
        try {
            $data = json_decode(file_get_contents("php://input"), true);
    
            switch ($method) {
                case 'GET':
                    if ($action === 'images' && $id) {
                        $this->handleGetUserProfileImagesRequest($id);
                    } else {
                        $this->handleGetRequest($id);
                    }
                    break;
    
                case 'POST':
                    $this->createUser($data);
                    break;
    
                case 'PUT':
                    if (!$id) {
                        $this->sendErrorResponse(400, 'User ID is required for updating.');
                        return;
                    }
                    $this->updateUser($id, $data);
                    break;
    
                case 'DELETE':
                    if (!$id) {
                        $this->sendErrorResponse(400, 'User ID is required for deletion.');
                        return;
                    }
                    $this->deleteUser($id);
                    break;
    
                default:
                    $this->sendErrorResponse(405, 'Unsupported HTTP method.');
            }
        } catch (PDOException $e) {
            $this->sendErrorResponse(500, 'Database error occurred.', $e);
        } catch (Exception $e) {
            $this->sendErrorResponse(500, 'An unexpected error occurred.', $e);
        }
    }

    private function handleGetRequest($id) {
        try {
            if ($id) {
                $user = $this->user->getUserById($id);
                if ($user) {
                    $this->sendSuccessResponse(200, $user);
                } else {
                    $this->sendErrorResponse(404, 'User not found.');
                }
            } else {
                $users = $this->user->getAllUsers();
                $this->sendSuccessResponse(200, $users);
            }
        } catch (Exception $e) {
            $this->sendErrorResponse(500, 'Failed to retrieve user data.', $e);
        }
    }

    private function createUser($data) {
        if (!$this->validateUserInput($data, true)) return;

        try {
            if ($this->user->createUser($data)) {
                $this->sendSuccessResponse(201, 'User created successfully.');
            } else {
                $this->sendErrorResponse(500, 'Failed to create user.');
            }
        } catch (PDOException $e) {
            $this->sendErrorResponse(500, 'Database error while creating user.', $e);
        }
    }

    private function updateUser($id, $data) {
        if (!$this->validateUserInput($data, false)) return;

        try {
            if ($this->user->updateUser($id, $data)) {
                $this->sendSuccessResponse(200, 'User updated successfully.');
            } else {
                $this->sendErrorResponse(500, 'Failed to update user.');
            }
        } catch (PDOException $e) {
            $this->sendErrorResponse(500, 'Database error while updating user.', $e);
        }
    }

    private function deleteUser($id) {
        try {
            if ($this->user->deleteUser($id)) {
                $this->sendSuccessResponse(200, 'User deleted successfully.');
            } else {
                $this->sendErrorResponse(500, 'Failed to delete user.');
            }
        } catch (PDOException $e) {
            $this->sendErrorResponse(500, 'Database error while deleting user.', $e);
        }
    }

    private function validateUserInput($data, $isNewUser) {
        $requiredFields = ['username', 'email', 'password'];

        if ($isNewUser) {
            foreach ($requiredFields as $field) {
                if (empty($data[$field])) {
                    $this->sendErrorResponse(400, "Missing required field: $field.");
                    return false;
                }
            }
        }

        if (!empty($data['email']) && !filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
            $this->sendErrorResponse(400, 'Invalid email format.');
            return false;
        }

        if (!empty($data['password']) && strlen($data['password']) < 6) {
            $this->sendErrorResponse(400, 'Password must be at least 6 characters long.');
            return false;
        }

        return true;
    }

    public function uploadProfilePicture($files) {
        if (empty($_POST['user_id']) || empty($_FILES['image'])) {
            $this->sendErrorResponse(400, 'User ID and image are required.');
            return;
        }

        try {
            $userId = $_POST['user_id'];
            $imageFile = $_FILES['image'];

            // Read image file
            $imageData = file_get_contents($imageFile['tmp_name']);
            $imageName = uniqid() . '-' . basename($imageFile['name']);

            // Upload to S3
            $imageUrl = $this->user->uploadProfilePictureToS3($imageData, $imageName);

            // Save to database
            if ($this->user->saveProfilePicture($userId, $imageUrl) && $this->user->updateUserProfilePicture($userId, $imageUrl)) {
                $this->sendSuccessResponse(200, ['message' => 'Profile picture uploaded successfully.', 'image_url' => $imageUrl]);
            } else {
                $this->sendErrorResponse(500, 'Failed to save profile picture.');
            }
        } catch (Exception $e) {
            $this->sendErrorResponse(500, 'Error during profile picture upload.', $e);
        }
    }

    private function sendSuccessResponse($statusCode, $data) {
        http_response_code($statusCode);
        echo json_encode(is_array($data) ? $data : ['message' => $data]);
    }

    private function sendErrorResponse($statusCode, $message, $exception = null) {
        http_response_code($statusCode);
        $errorResponse = ['message' => $message];

        if ($exception) {
            $errorResponse['error'] = $exception->getMessage();
            error_log('Error: ' . $exception->getMessage()); // Log error for debugging
        }

        echo json_encode($errorResponse);
    }

    private function handleGetUserProfileImagesRequest($userId) {
        try {
            $images = $this->user->getUserProfileImages($userId);
            if ($images) {
                http_response_code(200);
                echo json_encode($images);
            } else {
                http_response_code(404);
                echo json_encode(['message' => 'No images found for this user']);
            }
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode(['message' => 'Failed to retrieve user images', 'error' => $e->getMessage()]);
        }
    }
}

?>
