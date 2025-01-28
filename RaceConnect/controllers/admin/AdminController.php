<?php
//CHANGE THE PATHS TO YOUR OWN PATHS
require_once 'C:/xampp/htdocs/RaceConnect/models/admin/Admin.php';

class AdminController {
    private $admin;

    public function __construct($db) {
        $this->admin = new Admin($db);
    }

    public function processRequest($method, $id = null) {
        switch ($method) {
            case 'GET':
                if ($id) {
                    $admin = $this->admin->getAdminById($id);
                    if ($admin) {
                        echo json_encode($admin);
                    } else {
                        http_response_code(404);
                        echo json_encode(['message' => 'Admin not found']);
                    }
                } else {
                    echo json_encode($this->admin->getAllAdmins());
                }
                break;

            case 'POST':
                $data = json_decode(file_get_contents("php://input"), true);
                if (json_last_error() !== JSON_ERROR_NONE) {
                    http_response_code(400);
                    echo json_encode(['message' => 'Invalid JSON']);
                    break;
                }
                if ($this->validateAdminData($data)) {
                    if ($this->admin->createAdmin($data)) {
                        http_response_code(201);
                        echo json_encode(['message' => 'Admin created successfully']);
                    } else {
                        http_response_code(500);
                        echo json_encode(['message' => 'Failed to create admin']);
                    }
                } else {
                    http_response_code(400);
                    echo json_encode(['message' => 'Invalid admin data']);
                }
                break;

            case 'PUT':
                if ($id) {
                    $data = json_decode(file_get_contents("php://input"), true);
                    if (json_last_error() !== JSON_ERROR_NONE) {
                        http_response_code(400);
                        echo json_encode(['message' => 'Invalid JSON']);
                        break;
                    }
                    if (isset($data['role']) && !empty($data['role'])) {
                        if ($this->admin->updateAdminRole($id, $data['role'])) {
                            echo json_encode(['message' => 'Admin role updated successfully']);
                        } else {
                            http_response_code(500);
                            echo json_encode(['message' => 'Failed to update admin role']);
                        }
                    } else {
                        http_response_code(400);
                        echo json_encode(['message' => 'Invalid role data']);
                    }
                } else {
                    http_response_code(400);
                    echo json_encode(['message' => 'Admin ID is required']);
                }
                break;

            case 'DELETE':
                if ($id) {
                    if ($this->admin->deleteAdmin($id)) {
                        echo json_encode(['message' => 'Admin deleted successfully']);
                    } else {
                        http_response_code(500);
                        echo json_encode(['message' => 'Failed to delete admin']);
                    }
                } else {
                    http_response_code(400);
                    echo json_encode(['message' => 'Admin ID is required']);
                }
                break;

            default:
                http_response_code(405);
                echo json_encode(['message' => 'Unsupported HTTP method']);
        }
    }

    private function validateAdminData($data) {
        return isset($data['name']) && !empty($data['name']) &&
               isset($data['email']) && filter_var($data['email'], FILTER_VALIDATE_EMAIL) &&
               isset($data['role']) && !empty($data['role']);
    }
}
