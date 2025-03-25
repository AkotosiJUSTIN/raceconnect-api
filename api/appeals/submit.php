<?php
header('Access-Control-Allow-Origin: *');
header('Content-Type: application/json');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Access-Control-Allow-Headers, Content-Type, Access-Control-Allow-Methods, Authorization, X-Requested-With');

require_once '../../Config/Database.php';
require_once '../../Controller/AppealsController.php';

// Initialize database connection
$database = new Database();
$db = $database->connect();

// Initialize appeals controller
$appealsController = new AppealsController($db);

// Get posted data
$data = json_decode(file_get_contents("php://input"), true);

// Process the appeal
$result = $appealsController->submitAppeal($data);

// Return response
echo json_encode($result);
?>