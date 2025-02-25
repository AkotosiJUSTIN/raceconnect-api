<?php
require_once __DIR__ . '/../../db_connect.php';

header('Content-Type: application/json');

$action = $_GET['action'] ?? '';

switch ($action) {
    case 'fetch_dashboard_data':
        fetchDashboardData($conn);
        break;
    case 'fetch_notifications':
        fetchNotifications($conn);
        break;
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
    case 'ban_user':
        banUser($conn);
        break;
    case 'unban_user':
        unbanUser($conn);
        break;
    case 'suspend_user':
        suspendUser($conn);
        break;
    case 'archive_post':
        archivePost($conn);
        break;
    case 'hide_post':
        hidePost($conn);
        break;
    case 'post_announcement':
        postAnnouncement($conn);
        break;
    default:
        echo json_encode(['error' => 'Invalid action']);
        break;
}

$conn->close();

function fetchDashboardData($conn) {
    // Implement fetch dashboard data logic
}

function fetchNotifications($conn) {
    $query = "SELECT id, user_id, content, is_read, created_at FROM notifications";
    $result = $conn->query($query);
    $notifications = [];
    if ($result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $notifications[] = $row;
        }
    }
    echo json_encode($notifications);
}

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
    $query = "
    SELECT 
        p.id, 
        p.user_id, 
        p.title, 
        p.content, 
        p.like_count, 
        p.comment_count, 
        p.repost_count, 
        p.created_at,
        GROUP_CONCAT(DISTINCT pi.image_url) AS images
    FROM posts p
    LEFT JOIN post_images pi ON p.id = pi.post_id
    WHERE p.status NOT IN ('archived') OR p.status IS NULL
    GROUP BY p.id
    ";
    $result = $conn->query($query);
    $posts = [];
    if ($result === false) {
        echo json_encode(['success' => false, 'error' => $conn->error]);
        return;
    } else if ($result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $row['images'] = $row['images'] ? explode(',', $row['images']) : [];
            $posts[] = $row;
        }
    }
    echo json_encode(['success' => true, 'data' => $posts]);
}

function fetchUsers($conn) {
    $query = "SELECT username, status, created_at, suspension_end_date FROM users";
    $result = $conn->query($query);

    $users = [];
    while ($row = $result->fetch_assoc()) {
        if ($row['suspension_end_date'] && strtotime($row['suspension_end_date']) > time()) {
            $remainingDays = (strtotime($row['suspension_end_date']) - time()) / 86400;
            $row['suspension_days'] = ceil($remainingDays);
        } else {
            $row['suspension_days'] = null;
        }
        $users[] = $row;
    }

    echo json_encode($users);
}

function fetchMarketplaceItems($conn) {
    $queryItems = "SELECT id, seller_id, title, description, price, category, favorite_count, status, created_at, updated_at FROM `marketplace_items`";
    $resultItems = $conn->query($queryItems);

    $items = [];
    while ($row = $resultItems->fetch_assoc()) {
        $items[] = $row;
    }
    echo json_encode(['items' => $items]);
}

function banUser($conn) {
    $username = $_POST['username'] ?? null;
    $banDuration = $_POST['ban_duration'] ?? null;

    if (!$username || !$banDuration) {
        echo json_encode(["success" => false, "error" => "Missing parameters"]);
        return;
    }

    if ($banDuration === "permanent") {
        $suspensionEndDate = null;
    } else {
        $banDays = intval($banDuration);
        $suspensionEndDate = date('Y-m-d H:i:s', strtotime("+$banDays days"));
    }

    $query = "UPDATE users SET status = 'Banned', suspension_end_date = ? WHERE username = ?";
    $stmt = $conn->prepare($query);

    if (!$stmt) {
        echo json_encode(["success" => false, "error" => "Database error: " . $conn->error]);
        return;
    }

    $stmt->bind_param("ss", $suspensionEndDate, $username);
    $executeSuccess = $stmt->execute();

    $response = ["success" => $executeSuccess];
    if (!$executeSuccess) {
        $response["error"] = $stmt->error;
    }

    $stmt->close();
    echo json_encode($response);
}

function unbanUser($conn) {
    $username = $_POST['username'] ?? null;

    if (!$username) {
        echo json_encode(["success" => false, "error" => "Missing parameters"]);
        return;
    }

    $query = "UPDATE users SET status = 'Active', suspension_end_date = NULL WHERE username = ?";
    $stmt = $conn->prepare($query);

    if (!$stmt) {
        echo json_encode(["success" => false, "error" => "Database error: " . $conn->error]);
        return;
    }

    $stmt->bind_param("s", $username);
    $executeSuccess = $stmt->execute();

    $response = ["success" => $executeSuccess];
    if (!$executeSuccess) {
        $response["error"] = $stmt->error;
    }

    $stmt->close();
    echo json_encode($response);
}

function suspendUser($conn) {
    $username = $_POST['username'] ?? null;
    $suspensionDays = $_POST['suspension_days'] ?? null;

    if (!$username || !$suspensionDays) {
        echo json_encode(["success" => false, "error" => "Missing parameters"]);
        return;
    }

    $suspensionEndDate = date('Y-m-d H:i:s', strtotime("+$suspensionDays days"));

    $query = "UPDATE users SET status = 'Suspended', suspension_end_date = ? WHERE username = ?";
    $stmt = $conn->prepare($query);

    if (!$stmt) {
        echo json_encode(["success" => false, "error" => "Database error: " . $conn->error]);
        return;
    }

    $stmt->bind_param("ss", $suspensionEndDate, $username);
    $executeSuccess = $stmt->execute();

    $response = ["success" => $executeSuccess];
    if (!$executeSuccess) {
        $response["error"] = $stmt->error;
    }

    $stmt->close();
    echo json_encode($response);
}

function archivePost($conn) {
    $postId = $_POST['post_id'] ?? null;

    if (!$postId) {
        echo json_encode(["success" => false, "error" => "Missing parameters"]);
        return;
    }

    $query = "UPDATE posts SET status = 'Archived' WHERE id = ?";
    $stmt = $conn->prepare($query);

    if (!$stmt) {
        echo json_encode(["success" => false, "error" => "Database error: " . $conn->error]);
        return;
    }

    $stmt->bind_param("i", $postId);
    $executeSuccess = $stmt->execute();

    $response = ["success" => $executeSuccess];
    if (!$executeSuccess) {
        $response["error"] = $stmt->error;
    }

    $stmt->close();
    echo json_encode($response);
}

function hidePost($conn) {
    $postId = $_POST['post_id'] ?? null;

    if (!$postId) {
        echo json_encode(["success" => false, "error" => "Missing parameters"]);
        return;
    }

    $query = "UPDATE posts SET status = 'Hidden' WHERE id = ?";
    $stmt = $conn->prepare($query);

    if (!$stmt) {
        echo json_encode(["success" => false, "error" => "Database error: " . $conn->error]);
        return;
    }

    $stmt->bind_param("i", $postId);
    $executeSuccess = $stmt->execute();

    $response = ["success" => $executeSuccess];
    if (!$executeSuccess) {
        $response["error"] = $stmt->error;
    }

    $stmt->close();
    echo json_encode($response);
}

function postAnnouncement($conn) {
    $title = $_POST['announcementTitle'] ?? null;
    $content = $_POST['announcementContent'] ?? null;

    if (!$title || !$content) {
        echo json_encode(["success" => false, "error" => "Missing parameters"]);
        return;
    }

    $query = "INSERT INTO announcements (title, content, created_at) VALUES (?, ?, NOW())";
    $stmt = $conn->prepare($query);

    if (!$stmt) {
        echo json_encode(["success" => false, "error" => "Database error: " . $conn->error]);
        return;
    }

    $stmt->bind_param("ss", $title, $content);
    $executeSuccess = $stmt->execute();

    $response = ["success" => $executeSuccess];
    if (!$executeSuccess) {
        $response["error"] = $stmt->error;
    }

    $stmt->close();
    echo json_encode($response);
}
?>