<?php

namespace Controller;
use Model\Report;
use Exception;

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../Model/Report.php';

class ReportController {
    private $report;

    public function __construct($db) {
        $this->report = new Report($db);
    }

    public function processRequest($method, $id = null, $action = null) {
        try {
            $data = json_decode(file_get_contents("php://input"), true) ?? [];
    
            switch ($method) {
                case 'GET':
                    $this->handleGetRequest($id);
                    break;
                case 'POST':
                    $this->handlePostRequest($data);
                    break;
                case 'PUT':
                    $this->handlePutRequest($id, $data);
                    break;
                case 'DELETE':
                    $this->handleDeleteRequest($id);
                    break;
                default:
                    http_response_code(405);
                    echo json_encode(['message' => 'Unsupported HTTP method']);
            }
        } catch (Exception $e) {
            error_log("Server error: " . $e->getMessage());
            http_response_code(500);
            echo json_encode(['message' => 'Internal Server Error', 'error' => $e->getMessage()]);
        }
    }

    private function handleGetRequest($id) {
        try {
            if ($id) {
                $report = $this->report->getReportById($id);
                if (!$report) {
                    http_response_code(404);
                    echo json_encode(['message' => 'Report not found']);
                    return;
                }
                http_response_code(200);
                echo json_encode($report);
            } else {
                $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 10;
                $offset = isset($_GET['offset']) ? (int)$_GET['offset'] : 0;
                
                if ($limit <= 0 || $offset < 0) {
                    http_response_code(400);
                    echo json_encode(['message' => 'Invalid limit or offset values']);
                    return;
                }
                
                $reports = $this->report->getAllReports($limit, $offset);
                http_response_code(200);
                echo json_encode($reports);
            }
        } catch (Exception $e) {
            error_log("GET request failed: " . $e->getMessage());
            http_response_code(500);
            echo json_encode(['message' => 'Failed to retrieve reports', 'error' => $e->getMessage()]);
        }
    }

    private function handlePostRequest($data) {
        try {
            if (!$this->validateReportData($data, true)) {
                http_response_code(400);
                echo json_encode(['message' => 'Invalid input data']);
                return;
            }
            
            $reportId = $this->report->createReport($data);
            if (!$reportId || $reportId == 0) {
                throw new Exception('Failed to create report.');
            }
            
            http_response_code(201);
            echo json_encode(['message' => 'Report created successfully', 'report_id' => $reportId]);
        } catch (Exception $e) {
            error_log("POST request failed: " . $e->getMessage());
            http_response_code(500);
            echo json_encode(['message' => 'Failed to create report', 'error' => $e->getMessage()]);
        }
    }

    private function handlePutRequest($id, $data) {
        try {
            if (!$id) {
                http_response_code(400);
                echo json_encode(['message' => 'Report ID is required']);
                return;
            }
            
            if (!$this->validateReportData($data, false)) {
                http_response_code(400);
                echo json_encode(['message' => 'Invalid input data for update']);
                return;
            }
            
            if (!$this->report->updateReport($id, $data)) {
                throw new Exception("Failed to update report.");
            }
            
            http_response_code(200);
            echo json_encode(['message' => 'Report updated successfully']);
        } catch (Exception $e) {
            error_log("PUT request failed: " . $e->getMessage());
            http_response_code(500);
            echo json_encode(['message' => 'Failed to update report', 'error' => $e->getMessage()]);
        }
    }

    private function handleDeleteRequest($id) {
        try {
            if (!$id) {
                http_response_code(400);
                echo json_encode(['message' => 'Report ID is required']);
                return;
            }
            
            if (!$this->report->deleteReport($id)) {
                throw new Exception("Failed to delete report.");
            }
            
            http_response_code(200);
            echo json_encode(['message' => 'Report deleted successfully']);
        } catch (Exception $e) {
            error_log("DELETE request failed: " . $e->getMessage());
            http_response_code(500);
            echo json_encode(['message' => 'Failed to delete report', 'error' => $e->getMessage()]);
        }
    }

    private function validateReportData($data, $isNew = true) {
        if ($isNew) {
            if (!isset($data['reporter_id']) || !isset($data['reason'])) {
                return false;
            }
            if (empty($data['reporter_id']) || empty($data['reason'])) {
                return false;
            }
            // At least one of post_id or marketplace_item_id should be present
            if (!isset($data['post_id']) && !isset($data['marketplace_item_id'])) {
                return false;
            }
        }
        // For updates, allow partial updates but validate status if present
        if (isset($data['status']) && !in_array($data['status'], ['pending', 'resolved', 'hidden'])) {
            return false;
        }
        return true;
    }
}
?>