<?php
//CHANGE THE PATHS TO YOUR OWN PATHS
require_once 'C:/xampp/htdocs/RaceConnect/models/Admin.php';

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
                    echo json_encode($admin ? ['data' => $admin] : ['message' => 'Admin not found']);
                } else {
                    echo json_encode(['data' => $this->admin->getAllAdmins()]);
                }
                break;

            case 'POST':
                $data = json_decode(file_get_contents("php://input"), true);
                if ($this->admin->createAdmin($data)) {
                    echo json_encode(['message' => 'Admin created successfully']);
                } else {
                    echo json_encode(['message' => 'Failed to create admin']);
                }
                break;

            case 'PUT':
                if ($id) {
                    $data = json_decode(file_get_contents("php://input"), true);
                    if ($this->admin->updateAdminRole($id, $data['role'])) {
                        echo json_encode(['message' => 'Admin role updated successfully']);
                    } else {
                        echo json_encode(['message' => 'Failed to update admin role']);
                    }
                } else {
                    echo json_encode(['message' => 'Admin ID is required']);
                }
                break;

            case 'DELETE':
                if ($id) {
                    if ($this->admin->deleteAdmin($id)) {
                        echo json_encode(['message' => 'Admin deleted successfully']);
                    } else {
                        echo json_encode(['message' => 'Failed to delete admin']);
                    }
                } else {
                    echo json_encode(['message' => 'Admin ID is required']);
                }
                break;

            default:
                echo json_encode(['message' => 'Unsupported HTTP method']);
        }
    }
}
?>
