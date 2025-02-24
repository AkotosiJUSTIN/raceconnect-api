<?php
require_once __DIR__ . '/../../db_connect.php';

header('Content-Type: application/json');

$action = $_GET['action'] ?? '';

switch ($action) {
    case 'fetch_announcements':
        fetchAnnouncements($conn);
        break;
    case 'fetch_posts':
        fetchPosts($conn);
        break;
    case 'fetch_users':
        fetchUsers($conn);
        break;
    case 'fetch_marketplace':
        fetchMarketplaceItems($conn);
        break;
    default:
        echo json_encode(['error' => 'Invalid action']);
        break;
}

$conn->close();

function fetchAnnouncements($conn) {
    $query = "SELECT id, title, content, created_at FROM announcements ORDER BY created_at DESC";
    $result = $conn->query($query);
    $announcements = [];
    if ($result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $announcements[] = $row;
        }
    }
    echo json_encode($announcements);
}

function fetchPosts($conn) {
    // Implement fetch posts logic
}

function fetchUsers($conn) {
    // Implement fetch users logic
}

function fetchMarketplaceItems($conn) {
    // Implement fetch marketplace items logic
}
?>