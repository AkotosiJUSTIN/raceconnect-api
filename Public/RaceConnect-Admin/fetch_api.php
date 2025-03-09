<?php
require_once __DIR__ . '/../../db_connect.php';
require_once __DIR__ . '/cleanupArchived.php';

session_start();

// Check authentication
if (!isset($_SESSION['email'])) {
    echo json_encode([
        'success' => false,
        'error' => 'Unauthorized access'
    ]);
    exit;
}

header('Content-Type: application/json');
error_reporting(E_ALL);
ini_set('display_errors', 0);

$action = $_GET['action'] ?? '';

switch ($action) {
    case 'check_cleanup':
        try {
            if (rand(1, 10) === 1) {
                $cleanup = new CleanupService($conn);
                $result = $cleanup->cleanupArchivedData();
                echo json_encode(['success' => $result]);
            } else {
                echo json_encode(['success' => true, 'message' => 'Cleanup check skipped']);
            }
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
        break;
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
    case 'report_post':
        reportPost($conn);
        break;
    case 'report_marketplace_item':
        reportMarketplaceItem($conn);
        break;
    case 'hide_marketplace_item':
        hideMarketplaceItem($conn);
        break;
    case 'unhide_marketplace_item':
        unhideMarketplaceItem($conn);
        break;
    case 'archive_marketplace_item':
        archiveMarketplaceItem($conn);
        break;
    case 'archive_post':
        archivePost($conn);
        break;
    case 'hide_post':
        hidePost($conn);
        break;
    case 'unhide_post':
        unhidePost($conn);
        break;
    case 'archive_notification':
        archiveNotification($conn);
        break;
    case 'bulk_archive_notifications':
        bulkArchiveNotifications($conn);
    break;
    case 'post_announcement':
        postAnnouncement($conn);
        break;
    default:
        echo json_encode([
            'success' => false,
            'error' => 'Invalid action'
        ]);
        break;
}

$conn->close();

function fetchDashboardData($conn) {
    try {
        // Query to count posts by category
        $query = "SELECT 
            category,
            COUNT(*) as count
            FROM posts 
            WHERE status != 'Archived'
            GROUP BY category
            ORDER BY count DESC";
        
        $result = $conn->query($query);
        
        if (!$result) {
            throw new Exception($conn->error);
        }
        
        $categories = [];
        $counts = [];
        
        while ($row = $result->fetch_assoc()) {
            $categories[] = $row['category'];
            $counts[] = (int)$row['count'];
        }
        
        echo json_encode([
            'success' => true,
            'categories' => $categories,
            'counts' => $counts
        ]);
        
    } catch (Exception $e) {
        error_log("Error in fetchDashboardData: " . $e->getMessage());
        echo json_encode([
            'success' => false,
            'error' => $e->getMessage()
        ]);
    }
}

function createReportNotification($conn, $itemId, $reportId, $type, $reason) {
    try {
        // Get appropriate admin based on report type
        $roleCondition = match($type) {
            'post' => "'content_moderator'",
            'marketplace' => "'marketplace_manager'",
            default => "'community_manager'"
        };
        
        $adminQuery = "SELECT id FROM admins WHERE role = $roleCondition LIMIT 1";
        $adminResult = $conn->query($adminQuery);
        $adminId = $adminResult->fetch_assoc()['id'] ?? 1;

        $notifQuery = "INSERT INTO admin_notifications (
            admin_id,
            reporter_id,
            post_id,
            marketplace_item_id,
            type,
            content,
            severity,
            is_read,
            created_at,
            report_id,
            status
        ) VALUES (
            ?, -- admin_id
            ?, -- reporter_id
            CASE WHEN ? = 'post' THEN ? ELSE NULL END,
            CASE WHEN ? = 'marketplace' THEN ? ELSE NULL END,
            ?,
            ?,
            'medium',
            0,
            NOW(),
            ?,
            'pending'
        )";

        $stmt = $conn->prepare($notifQuery);
        $reportType = $type . '_report';
        $content = "New report: " . $reason;
        
        $stmt->bind_param("iiisissi", 
            $adminId,
            $_SESSION['user_id'],
            $type, $itemId,
            $type, $itemId,
            $reportType,
            $content,
            $reportId
        );

        if (!$stmt->execute()) {
            throw new Exception("Failed to create notification: " . $stmt->error);
        }

        return true;
    } catch (Exception $e) {
        error_log("Error creating notification: " . $e->getMessage());
        throw $e;
    }
}

function reportMarketplaceItem($conn) {
    try {
        $itemId = $_POST['item_id'] ?? null;
        $reporterId = $_POST['reporter_id'] ?? null;
        $reason = $_POST['reason'] ?? null;

        if (!$itemId || !$reporterId || !$reason) {
            throw new Exception("Missing required fields");
        }

        $conn->begin_transaction();

        // Insert report with NULL post_id
        $reportQuery = "INSERT INTO reports (
            post_id,
            marketplace_item_id, 
            reporter_id, 
            reason, 
            created_at, 
            status
        ) VALUES (
            NULL,
            ?, 
            ?, 
            ?, 
            NOW(), 
            'pending'
        )";
        
        $reportStmt = $conn->prepare($reportQuery);
        $reportStmt->bind_param("iis", $itemId, $reporterId, $reason);
        
        if (!$reportStmt->execute()) {
            throw new Exception("Failed to create report: " . $reportStmt->error);
        }
        
        $reportId = $conn->insert_id;

        // Update marketplace item status
        $updateItem = "UPDATE marketplace_items SET report = 'reported' WHERE id = ?";
        $itemStmt = $conn->prepare($updateItem);
        $itemStmt->bind_param("i", $itemId);
        
        if (!$itemStmt->execute()) {
            throw new Exception("Failed to update item status");
        }

        $conn->commit();
        echo json_encode(["success" => true]);

    } catch (Exception $e) {
        $conn->rollback();
        error_log("Error in reportMarketplaceItem: " . $e->getMessage());
        echo json_encode([
            "success" => false,
            "error" => $e->getMessage()
        ]);
    }
}

function reportPost($conn) {
    try {
        $postId = $_POST['post_id'] ?? null;
        $reporterId = $_POST['reporter_id'] ?? null;
        $reason = $_POST['reason'] ?? null;

        if (!$postId || !$reporterId || !$reason) {
            throw new Exception("Missing required fields");
        }

        error_log("Processing report for post ID: $postId"); // Debug log

        $conn->begin_transaction();

        try {
            // Insert report with explicit status
            $reportQuery = "INSERT INTO reports (
                post_id,
                marketplace_item_id, 
                reporter_id, 
                reason, 
                created_at, 
                status
            ) VALUES (?, ?, ?, NOW(), 'pending')";  // Explicitly set status to 'pending'
            
            $reportStmt = $conn->prepare($reportQuery);
            $reportStmt->bind_param("iis", $postId, $reporterId, $reason);
            
            if (!$reportStmt->execute()) {
                throw new Exception("Failed to create report: " . $reportStmt->error);
            }
            
            $reportId = $conn->insert_id;
            error_log("Created report with ID: $reportId"); // Debug log

            // Update post status
            $updatePost = "UPDATE posts SET report = 'reported' WHERE id = ?";
            $postStmt = $conn->prepare($updatePost);
            $postStmt->bind_param("i", $postId);
            
            if (!$postStmt->execute()) {
                throw new Exception("Failed to update post status");
            }

            // Create notification using the helper function
            try {
                createReportNotification($conn, $postId, $reportId, 'post', $reason);
            } catch (Exception $e) {
                throw new Exception("Failed to create notification: " . $e->getMessage());
            }

            $conn->commit();
            echo json_encode([
                "success" => true,
                "message" => "Report created successfully"
            ]);

        } catch (Exception $e) {
            $conn->rollback();
            throw $e;
        }

    } catch (Exception $e) {
        error_log("Error in reportPost: " . $e->getMessage());
        echo json_encode([
            "success" => false,
            "error" => $e->getMessage()
        ]);
    }
}

function fetchNotifications($conn) {
    try {
        $query = "SELECT 
            an.id,
            an.admin_id,
            an.reporter_id,
            an.post_id,
            an.marketplace_item_id,
            an.type,
            an.content,
            an.severity,
            an.is_read,
            an.created_at,
            an.report_id,
            an.status,
            an.action_taken,
            an.resolved_by,
            an.resolved_at,
            r.reason as report_reason,
            u.username as reporter_username,
            COALESCE(p.title, mi.title, 'Untitled') as title,
            p.title as post_title,
            mi.title as marketplace_title
            FROM admin_notifications an
            LEFT JOIN reports r ON an.report_id = r.id
            LEFT JOIN users u ON an.reporter_id = u.id
            LEFT JOIN posts p ON an.post_id = p.id
            LEFT JOIN marketplace_items mi ON an.marketplace_item_id = mi.id
            WHERE an.status != 'archived'
            ORDER BY 
                CASE an.severity
                    WHEN 'high' THEN 1
                    WHEN 'medium' THEN 2
                    WHEN 'low' THEN 3
                END,
                an.created_at DESC";
        
        $result = $conn->query($query);
        
        if (!$result) {
            throw new Exception("Query failed: " . $conn->error);
        }
        
        $notifications = [];
        while ($row = $result->fetch_assoc()) {
            $notifications[] = $row;
        }
        
        echo json_encode([
            'success' => true,
            'data' => $notifications,
            'count' => count($notifications)
        ]);
        
    } catch (Exception $e) {
        error_log("Error in fetchNotifications: " . $e->getMessage());
        echo json_encode([
            'success' => false,
            'error' => $e->getMessage()
        ]);
    }
}



// Add this function to verify admin password
function verifyAdminPassword($conn, $email, $password) {
    try {
        // Add error logging
        error_log("Verifying password for email: " . $email);
        
        $stmt = $conn->prepare("SELECT password FROM admins WHERE email = ?");
        if (!$stmt) {
            error_log("Prepare failed: " . $conn->error);
            return false;
        }
        
        $stmt->bind_param("s", $email);
        if (!$stmt->execute()) {
            error_log("Execute failed: " . $stmt->error);
            return false;
        }
        
        $result = $stmt->get_result();
        if ($row = $result->fetch_assoc()) {
            $verified = password_verify($password, $row['password']);
            error_log("Password verification result: " . ($verified ? 'true' : 'false'));
            return $verified;
        }
        
        error_log("No admin found with email: " . $email);
        return false;
    } catch (Exception $e) {
        error_log("Error in verifyAdminPassword: " . $e->getMessage());
        return false;
    }
}

// Modify the existing archive_notification function
function archiveNotification($conn) {
    // Parse JSON input
    $data = json_decode(file_get_contents('php://input'), true);
    $notificationId = $data['notification_id'] ?? null;
    $password = $data['password'] ?? null;

    if (!$notificationId || !$password) {
        echo json_encode(['success' => false, 'error' => 'Invalid input']);
        return;
    }

    // Verify admin password
    if (!verifyAdminPassword($conn, $_SESSION['email'], $password)) {
        echo json_encode(['success' => false, 'error' => 'Invalid password']);
        return;
    }

    try {
        // Start transaction
        $conn->begin_transaction();

        if (rand(1, 10) === 1) {
            $cleanup = new CleanupService($conn);
            $cleanup->cleanupArchivedData();
        }

        // Update notification status and set archived_at timestamp
        $stmt = $conn->prepare("
            UPDATE admin_notifications 
            SET status = 'archived',
                archived_at = CURRENT_TIMESTAMP
            WHERE id = ?
        ");
        $stmt->bind_param("i", $notificationId);
        $stmt->execute();

        if ($stmt->affected_rows === 0) {
            throw new Exception("Notification not found or already archived");
        }

        // Commit transaction
        $conn->commit();
        
        echo json_encode(['success' => true]);
    } catch (Exception $e) {
        // Rollback on error
        $conn->rollback();
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
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
    try {
        // First, sync post report status with pending reports
        $syncQuery = "UPDATE posts p 
            LEFT JOIN (
                SELECT post_id, COUNT(*) as pending_count 
                FROM reports 
                WHERE status = 'pending'
                GROUP BY post_id
            ) r ON p.id = r.post_id 
            SET p.report = CASE
                WHEN r.pending_count > 0 THEN 'reported'
                WHEN r.pending_count IS NULL OR r.pending_count = 0 THEN 'none'
                ELSE p.report
            END
            WHERE p.status != 'Hidden'";
        
        if (!$conn->query($syncQuery)) {
            throw new Exception("Failed to sync report status: " . $conn->error);
        }

        // Then fetch posts that are either reported or hidden
        $query = "SELECT 
            p.id, 
            p.user_id, 
            COALESCE(p.title, 'Untitled Post') as title,
            p.content, 
            p.created_at,
            COALESCE(p.status, 'Active') as status,
            p.report,
            r.reason as report_reason,
            r.created_at as reported_at,
            r.status as report_status,
            u.username as reporter_username,
            GROUP_CONCAT(pi.image_url) as images
            FROM posts p
            LEFT JOIN reports r ON p.id = r.post_id AND r.status = 'pending'
            LEFT JOIN users u ON r.reporter_id = u.id
            LEFT JOIN post_images pi ON p.id = pi.post_id
            WHERE (p.report = 'reported' OR p.status = 'Hidden')
            AND p.status != 'Archived'
            AND EXISTS (
                SELECT 1 FROM reports 
                WHERE post_id = p.id 
                AND status = 'pending'
            )
            GROUP BY p.id
            ORDER BY r.created_at DESC";
        
        $result = $conn->query($query);
        
        if (!$result) {
            throw new Exception($conn->error);
        }
        
        $posts = [];
        while ($row = $result->fetch_assoc()) {
            $row['images'] = $row['images'] ? explode(',', $row['images']) : [];
            $posts[] = $row;
        }
        
        echo json_encode([
            'success' => true, 
            'data' => $posts
        ]);
    } catch (Exception $e) {
        error_log("Error in fetchPosts: " . $e->getMessage());
        echo json_encode([
            'success' => false, 
            'error' => 'Failed to fetch posts: ' . $e->getMessage()
        ]);
    }
}

function fetchUsers($conn) {
    // First, update any expired bans
    $updateQuery = "UPDATE users 
                   SET status = 'Active', suspension_end_date = NULL 
                   WHERE status = 'Banned' 
                   AND suspension_end_date IS NOT NULL 
                   AND suspension_end_date <= NOW()";
    $conn->query($updateQuery);

    // Then fetch all users
    $query = "SELECT username, status, created_at, suspension_end_date,
              CASE 
                WHEN status = 'Banned' AND suspension_end_date IS NOT NULL 
                THEN TIMESTAMPDIFF(SECOND, NOW(), suspension_end_date) / 86400
                ELSE NULL 
              END as suspension_days
              FROM users";
    
    $result = $conn->query($query);
    $users = [];
    
    while ($row = $result->fetch_assoc()) {
        if ($row['suspension_days'] !== null) {
            $row['suspension_days'] = ceil($row['suspension_days']);
            // If suspension has expired but hasn't been caught by the update
            if ($row['suspension_days'] <= 0) {
                $row['status'] = 'Active';
                $row['suspension_days'] = null;
                $row['suspension_end_date'] = null;
            }
        }
        $users[] = $row;
    }

    echo json_encode($users);
}

function fetchMarketplaceItems($conn) {
    try {
        // Update reported_at timestamp when items are reported
        $syncQuery = "UPDATE marketplace_items mi 
            INNER JOIN (
                SELECT marketplace_item_id, MIN(created_at) as first_report
                FROM reports 
                WHERE status = 'pending'
                GROUP BY marketplace_item_id
            ) r ON mi.id = r.marketplace_item_id 
            SET mi.report = 'reported',
                mi.reported_at = COALESCE(mi.reported_at, r.first_report)
            WHERE r.marketplace_item_id IS NOT NULL";
        
        $conn->query($syncQuery);

        // Then fetch items that are either reported or hidden
        $queryItems = "SELECT 
            mi.id, 
            mi.seller_id, 
            mi.title, 
            mi.description, 
            mi.price, 
            mi.category,
            mi.status,
            mi.report,
            mi.created_at,
            mi.reported_at,
            r.reason as report_reason,
            r.created_at as report_created_at,
            r.status as report_status,
            u.username as reporter_username,
            GROUP_CONCAT(mii.image_url) as image_urls
            FROM marketplace_items mi
            LEFT JOIN reports r ON mi.id = r.marketplace_item_id AND r.status = 'pending'
            LEFT JOIN users u ON r.reporter_id = u.id
            LEFT JOIN marketplace_item_images mii ON mi.id = mii.marketplace_item_id
            WHERE (mi.report = 'reported' OR mi.status = 'Hidden')
            AND mi.status != 'Archived'
            AND EXISTS (
                SELECT 1 FROM reports 
                WHERE marketplace_item_id = mi.id 
                AND status = 'pending'
            )
            GROUP BY mi.id
            ORDER BY 
                COALESCE(mi.reported_at, mi.created_at) DESC";

        $resultItems = $conn->query($queryItems);

        if (!$resultItems) {
            throw new Exception($conn->error);
        }

        $items = [];
        while ($row = $resultItems->fetch_assoc()) {
            $row['image_urls'] = $row['image_urls'] ? explode(',', $row['image_urls']) : [];
            $items[] = $row;
        }
        
        echo json_encode([
            'success' => true,
            'items' => $items
        ]);
    } catch (Exception $e) {
        error_log("Error in fetchMarketplaceItems: " . $e->getMessage());
        echo json_encode([
            'success' => false,
            'error' => 'Failed to fetch items'
        ]);
    }
}

function hideMarketplaceItem($conn) {
    $itemId = $_POST['item_id'] ?? null;

    if (!$itemId) {
        echo json_encode(["success" => false, "error" => "Missing parameters"]);
        return;
    }

    $conn->begin_transaction();

    try {
        // First get the current status
        $getCurrentStatus = "SELECT status FROM marketplace_items WHERE id = ?";
        $statusStmt = $conn->prepare($getCurrentStatus);
        $statusStmt->bind_param("i", $itemId);
        $statusStmt->execute();
        $result = $statusStmt->get_result();
        $currentStatus = $result->fetch_assoc()['status'];

        // Update item status and store previous status
        $query = "UPDATE marketplace_items 
                 SET status = 'Hidden', 
                     previous_status = ? 
                 WHERE id = ?";
        $stmt = $conn->prepare($query);

        if (!$stmt) {
            throw new Exception("Database error: " . $conn->error);
        }

        $stmt->bind_param("si", $currentStatus, $itemId);
        if (!$stmt->execute()) {
            throw new Exception("Failed to update item");
        }

        // Update only the report status to Hidden while preserving the reason
        $updateReport = $conn->prepare("UPDATE reports SET status = 'Hidden' WHERE marketplace_item_id = ? AND status = 'pending'");
        $updateReport->bind_param("i", $postId);
        if (!$updateReport->execute()) {
            throw new Exception("Failed to update report");
        }

        // Check if there are any pending reports for this item
        $checkReports = $conn->prepare("
            SELECT COUNT(*) as count 
            FROM reports 
            WHERE marketplace_item_id = ? AND status = 'pending'
        ");
        $checkReports->bind_param("i", $itemId);
        $checkReports->execute();
        $result = $checkReports->get_result();
        $row = $result->fetch_assoc();

        // If no pending reports, update the item's report column to 'none'
        if ($row['count'] == 0) {
            $updateItemReport = $conn->prepare("
                UPDATE marketplace_items 
                SET report = 'none' 
                WHERE id = ?
            ");
            $updateItemReport->bind_param("i", $itemId);
            if (!$updateItemReport->execute()) {
                throw new Exception("Failed to update item report status");
            }
        }

        $conn->commit();
        echo json_encode(["success" => true]);

    } catch (Exception $e) {
        $conn->rollback();
        echo json_encode(["success" => false, "error" => $e->getMessage()]);
    }
}

function unhideMarketplaceItem($conn) {
    $itemId = $_POST['item_id'] ?? null;

    if (!$itemId) {
        echo json_encode(["success" => false, "error" => "Missing parameters"]);
        return;
    }

    $conn->begin_transaction();

    try {
        // Get the previous status or default to Available
        $getStatusQuery = "SELECT previous_status FROM marketplace_items WHERE id = ?";
        $statusStmt = $conn->prepare($getStatusQuery);
        $statusStmt->bind_param("i", $itemId);
        $statusStmt->execute();
        $result = $statusStmt->get_result();
        $previousStatus = $result->fetch_assoc()['previous_status'] ?? 'Available';

        // Update item status but maintain the report information
        $query = "UPDATE marketplace_items 
                 SET status = ?, 
                     previous_status = NULL
                 WHERE id = ?";
        $stmt = $conn->prepare($query);

        if (!$stmt) {
            throw new Exception("Database error: " . $conn->error);
        }

        $stmt->bind_param("si", $previousStatus, $itemId);
        if (!$stmt->execute()) {
            throw new Exception("Failed to update item");
        }

        // Update report status to pending instead of changing it
        $updateReport = $conn->prepare("
            UPDATE reports 
            SET status = 'pending'
            WHERE marketplace_item_id = ? 
            AND status = 'Hidden'
        ");
        $updateReport->bind_param("i", $itemId);
        if (!$updateReport->execute()) {
            throw new Exception("Failed to update report");
        }

        $conn->commit();
        echo json_encode(["success" => true]);

    } catch (Exception $e) {
        $conn->rollback();
        echo json_encode(["success" => false, "error" => $e->getMessage()]);
    }
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

function archivePost($conn) {
    // Parse JSON input
    $data = json_decode(file_get_contents('php://input'), true);
    $postId = $data['post_id'] ?? null;
    $password = $data['password'] ?? null;

    if (!$postId || !$password) {
        echo json_encode(['success' => false, 'error' => 'Invalid input']);
        return;
    }

    // Verify admin password
    if (!verifyAdminPassword($conn, $_SESSION['email'], $password)) {
        echo json_encode(['success' => false, 'error' => 'Invalid password']);
        return;
    }

    try {
        // Start transaction
        $conn->begin_transaction();

        if (rand(1, 10) === 1) {
            $cleanup = new CleanupService($conn);
            $cleanup->cleanupArchivedData();
        }

        // Update post status and set archived_at timestamp
        $stmt = $conn->prepare("
            UPDATE posts 
            SET status = 'Archived',
                archived_at = CURRENT_TIMESTAMP,
                report = 'none'
            WHERE id = ?
        ");
        $stmt->bind_param("i", $postId);
        $stmt->execute();

        if ($stmt->affected_rows === 0) {
            throw new Exception("Post not found or already archived");
        }

        // Update reports to resolved status
        $updateReports = $conn->prepare("
            UPDATE reports 
            SET status = 'resolved',
                resolved_at = CURRENT_TIMESTAMP,
                resolved_by = ?
            WHERE post_id = ? AND status = 'pending'
        ");
        $updateReports->bind_param("ii", $_SESSION['admin_id'], $postId);
        $updateReports->execute();

        // Commit transaction
        $conn->commit();
        
        echo json_encode(['success' => true]);
    } catch (Exception $e) {
        $conn->rollback();
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
}

function archiveMarketplaceItem($conn) {
    try {
        // Parse JSON input
        $inputData = file_get_contents('php://input');
        error_log("Received data: " . $inputData); // Debug log
        
        $data = json_decode($inputData, true);
        $itemId = $data['item_id'] ?? null;
        $password = $data['password'] ?? null;

        // Validate input
        if (!$itemId || !$password) {
            echo json_encode(['success' => false, 'error' => 'Missing required fields']);
            return;
        }

        // Verify admin password
        if (!verifyAdminPassword($conn, $_SESSION['email'], $password)) {
            echo json_encode(['success' => false, 'error' => 'Invalid password']);
            return;
        }

        // Start transaction
        $conn->begin_transaction();

        // Archive the item
        $stmt = $conn->prepare("
            UPDATE marketplace_items 
            SET status = 'Archived',
                archived_at = CURRENT_TIMESTAMP,
                report = 'none'
            WHERE id = ?
        ");
        
        $stmt->bind_param("i", $itemId);
        $stmt->execute();

        if ($stmt->affected_rows === 0) {
            throw new Exception("Item not found or already archived");
        }

        // Update reports to resolved status
        $updateReports = $conn->prepare("
            UPDATE reports 
            SET status = 'resolved',
                resolved_at = CURRENT_TIMESTAMP,
                resolved_by = ?
            WHERE marketplace_item_id = ? AND status = 'pending'
        ");
        $updateReports->bind_param("ii", $_SESSION['admin_id'], $itemId);
        $updateReports->execute();

        // Commit the transaction
        $conn->commit();
        
        echo json_encode(['success' => true]);
        
    } catch (Exception $e) {
        if ($conn->inTransaction()) {
            $conn->rollback();
        }
        error_log("Error in archiveMarketplaceItem: " . $e->getMessage());
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
}

function hidePost($conn) {
    $postId = $_POST['post_id'] ?? null;

    if (!$postId) {
        echo json_encode(["success" => false, "error" => "Missing parameters"]);
        return;
    }

    $conn->begin_transaction();

    try {
        // Update post status
        $query = "UPDATE posts SET status = 'Hidden' WHERE id = ?";
        $stmt = $conn->prepare($query);

        if (!$stmt) {
            throw new Exception("Database error: " . $conn->error);
        }

        $stmt->bind_param("i", $postId);
        if (!$stmt->execute()) {
            throw new Exception("Failed to update post");
        }

        // Update report status
        $updateReport = $conn->prepare("UPDATE reports SET status = 'Hidden' WHERE post_id = ?");
        $updateReport->bind_param("i", $postId);
        if (!$updateReport->execute()) {
            throw new Exception("Failed to update report");
        }

        // Check if there are any pending reports for this post
        $checkReports = $conn->prepare("SELECT COUNT(*) as count FROM reports WHERE post_id = ? AND status = 'pending'");
        $checkReports->bind_param("i", $postId);
        $checkReports->execute();
        $result = $checkReports->get_result();
        $row = $result->fetch_assoc();

        // If no pending reports, update the post's report column to 'none'
        if ($row['count'] == 0) {
            $updatePostReport = $conn->prepare("UPDATE posts SET report = 'none' WHERE id = ?");
            $updatePostReport->bind_param("i", $postId);
            if (!$updatePostReport->execute()) {
                throw new Exception("Failed to update post report status");
            }
        }

        $conn->commit();
        echo json_encode(["success" => true]);

    } catch (Exception $e) {
        $conn->rollback();
        echo json_encode(["success" => false, "error" => $e->getMessage()]);
    } finally {
        if (isset($stmt)) {
            $stmt->close();
        }
    }
}

function unhidePost($conn) {
    $postId = $_POST['post_id'] ?? null;

    if (!$postId) {
        echo json_encode(["success" => false, "error" => "Missing parameters"]);
        return;
    }

    $conn->begin_transaction();

    try {
        // Update post status
        $query = "UPDATE posts SET status = 'Active' WHERE id = ?";
        $stmt = $conn->prepare($query);

        if (!$stmt) {
            throw new Exception("Database error: " . $conn->error);
        }

        $stmt->bind_param("i", $postId);
        if (!$stmt->execute()) {
            throw new Exception("Failed to update post");
        }

        // Update report status
        $updateReport = $conn->prepare("UPDATE reports SET status = 'pending' WHERE post_id = ?");
        $updateReport->bind_param("i", $postId);
        if (!$updateReport->execute()) {
            throw new Exception("Failed to update report");
        }

        // Check if there are any pending reports for this post
        $checkReports = $conn->prepare("SELECT COUNT(*) as count FROM reports WHERE post_id = ? AND status = 'pending'");
        $checkReports->bind_param("i", $postId);
        $checkReports->execute();
        $result = $checkReports->get_result();
        $row = $result->fetch_assoc();

        // If no pending reports, update the post's report column to 'none'
        if ($row['count'] == 0) {
            $updatePostReport = $conn->prepare("UPDATE posts SET report = 'none' WHERE id = ?");
            $updatePostReport->bind_param("i", $postId);
            if (!$updatePostReport->execute()) {
                throw new Exception("Failed to update post report status");
            }
        }

        $conn->commit();
        echo json_encode(["success" => true]);

    } catch (Exception $e) {
        $conn->rollback();
        echo json_encode(["success" => false, "error" => $e->getMessage()]);
    } finally {
        if (isset($stmt)) {
            $stmt->close();
        }
    }
}

function bulkArchiveNotifications($conn) {
    try {
        $data = json_decode(file_get_contents('php://input'), true);
        $notificationIds = $data['notification_ids'] ?? [];

        if (empty($notificationIds)) {
            throw new Exception("No notifications selected");
        }

        $ids = array_map('intval', $notificationIds);
        $placeholders = str_repeat('?,', count($ids) - 1) . '?';
        
        $query = "UPDATE admin_notifications 
                 SET status = 'archived',
                     resolved_at = NOW(),
                     resolved_by = ?
                 WHERE id IN ($placeholders)";
        
        $stmt = $conn->prepare($query);
        
        if (!$stmt) {
            throw new Exception("Database error: " . $conn->error);
        }

        $adminId = $_SESSION['admin_id'] ?? 1; // Get the current admin's ID
        $types = "i" . str_repeat('i', count($ids));
        $params = array_merge([$adminId], $ids);
        
        $stmt->bind_param($types, ...$params);
        
        if (!$stmt->execute()) {
            throw new Exception("Failed to archive notifications");
        }

        echo json_encode(["success" => true]);

    } catch (Exception $e) {
        echo json_encode([
            "success" => false,
            "error" => $e->getMessage()
        ]);
    }
}

function postAnnouncement($conn) {
    try {
        require_once __DIR__ . '/../../vendor/autoload.php';
        $dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/../../');
        $dotenv->load();

        // Initialize S3 client
        $s3 = new Aws\S3\S3Client([
            'version' => 'latest',
            'region'  => $_ENV['AWS_REGION'],
            'credentials' => [
                'key'    => $_ENV['AWS_ACCESS_KEY'],
                'secret' => $_ENV['AWS_SECRET_KEY'],
            ],
            'http'    => [
                'verify' => false
            ]
        ]);

        // Validate input
        $title = $_POST['announcementTitle'] ?? null;
        $content = $_POST['announcementContent'] ?? null;
        $imageUrl = null;

        if (!$title || !$content) {
            throw new Exception('Title and content are required');
        }

        // Handle image upload if present
        if (isset($_FILES['announcementImage']) && $_FILES['announcementImage']['error'] === UPLOAD_ERR_OK) {
            $file = $_FILES['announcementImage'];
            $imageData = file_get_contents($file['tmp_name']);
            
            if (!isValidImage($imageData)) {
                throw new Exception('Invalid image file or size exceeds 5MB limit.');
            }

            // Generate unique filename
            $uniqueId = uniqid();
            $filename = $uniqueId . '-' . basename($file['name']); // Use $file['name'] instead of $imageName

            try {
                // Upload to S3 with public-read ACL
                $result = $s3->putObject([
                    'Bucket' => $_ENV['AWS_S3_BUCKET'],
                    'Key'    => 'announcement-images/' . $filename,
                    'Body'   => $imageData,
                    'ContentType' => $file['type'],
                ]);
            
                // Construct the URL manually to ensure proper format
                $imageUrl = 'https://' . $_ENV['AWS_S3_BUCKET'] . '.s3.' . $_ENV['AWS_REGION'] . '.amazonaws.com/announcement-images/' . $filename;
            } catch (Aws\Exception\AwsException $e) {
                error_log('S3 Upload Error: ' . $e->getMessage());
                throw new Exception('Failed to upload image to S3: ' . $e->getMessage());
            }
        }

        // Insert announcement into database
        $query = "INSERT INTO announcements (title, content, image_url) VALUES (?, ?, ?)";
        $stmt = $conn->prepare($query);
        
        if (!$stmt) {
            throw new Exception('Database prepare failed: ' . $conn->error);
        }

        $stmt->bind_param("sss", $title, $content, $imageUrl);
        
        if (!$stmt->execute()) {
            throw new Exception('Failed to insert announcement: ' . $stmt->error);
        }

        echo json_encode([
            'success' => true,
            'message' => 'Announcement created successfully',
            'image_url' => $imageUrl
        ]);

    } catch (Exception $e) {
        error_log('Announcement Error: ' . $e->getMessage());
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'error' => $e->getMessage()
        ]);
    }
}

// Add the same validation function as Post.php
function isValidImage($imageData) {
    return (strlen($imageData) > 0 && strlen($imageData) <= 5000000); // 5MB limit
}
?>