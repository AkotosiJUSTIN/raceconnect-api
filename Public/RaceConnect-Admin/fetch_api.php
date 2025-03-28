<?php
require_once __DIR__ . '/../../db_connect.php';
require_once __DIR__ . '/cleanupArchived.php';

require_once __DIR__ . '/../../vendor/autoload.php';
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/../../');
$dotenv->load();

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
    case 'mark_notification_read':
        markNotificationAsRead($conn);
        return;
    case 'update_appeal_status':
        updateAppealStatus($conn);
        return;
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
        $allCategories = [
            'Formula 1',
            'Formula Drift',
            '24 Hours of Lemans',
            'World Rally Championship',
            'NASCAR',
            'GT Championship'
        ];

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
        
        $categoryCounts = array_fill_keys($allCategories, 0);
        $mostPopularCategory = null;
        $maxCount = 0;
        
        while ($row = $result->fetch_assoc()) {
            if (isset($categoryCounts[$row['category']])) {
                $count = (int)$row['count'];
                $categoryCounts[$row['category']] = $count;
                
                if ($count > $maxCount) {
                    $maxCount = $count;
                    $mostPopularCategory = $row['category'];
                }
            }
        }

        arsort($categoryCounts);
        
        echo json_encode([
            'success' => true,
            'categories' => array_keys($categoryCounts),
            'counts' => array_values($categoryCounts),
            'mostPopular' => [
                'category' => $mostPopularCategory ?? 'No posts yet',
                'count' => $maxCount
            ]
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

function markNotificationAsRead($conn) {
    try {
        // Get POST data
        $input = file_get_contents('php://input');
        error_log("Received input: " . $input); // Debug log
        
        $data = json_decode($input, true);
        if (!$data || !isset($data['notification_id'])) {
            throw new Exception("Invalid or missing notification_id");
        }

        $notificationId = intval($data['notification_id']);
        
        // Prepare and execute update query
        $stmt = $conn->prepare("
            UPDATE admin_notifications 
            SET is_read = 1 
            WHERE id = ?
        ");

        if (!$stmt) {
            throw new Exception("Prepare failed: " . $conn->error);
        }

        $stmt->bind_param("i", $notificationId);
        
        if (!$stmt->execute()) {
            throw new Exception("Execute failed: " . $stmt->error);
        }

        $affected = $stmt->affected_rows;
        $stmt->close();

        echo json_encode([
            'success' => true,
            'affected_rows' => $affected,
            'message' => 'Notification updated successfully'
        ]);

    } catch (Exception $e) {
        error_log("Error in markNotificationAsRead: " . $e->getMessage());
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'error' => $e->getMessage()
        ]);
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
            COALESCE(an.is_read, 0) as is_read,
            an.created_at,
            an.report_id,
            an.status,
            an.action_taken,
            an.resolved_by,
            an.resolved_at,
            r.reason as report_reason,
            u.username as reporter_username,
            COALESCE(p.title, mi.title, 'Pending Appeal for Review') as title,
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
            // Ensure is_read is properly cast to integer
            $row['is_read'] = (int)$row['is_read'];
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

function archiveNotification($conn) {
    // Parse JSON input
    $data = json_decode(file_get_contents('php://input'), true);
    $notificationId = $data['notification_id'] ?? null;
    $password = $data['password'] ?? null;

    // Validate input
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
        // Start a transaction
        $conn->begin_transaction();

        // Optional cleanup (unchanged from original)
        if (rand(1, 10) === 1) {
            $cleanup = new CleanupService($conn);
            $cleanup->cleanupArchivedData();
        }

        // Archive the notification without updating appeals or action_taken
        $stmt = $conn->prepare("
            UPDATE admin_notifications 
            SET status = 'archived',
                archived_at = CURRENT_TIMESTAMP
            WHERE id = ?
        ");
        $stmt->bind_param("i", $notificationId);
        $stmt->execute();

        // Check if the update was successful
        if ($stmt->affected_rows === 0) {
            throw new Exception("Notification not found or already archived");
        }

        // Commit the transaction
        $conn->commit();
        echo json_encode(['success' => true]);
    } catch (Exception $e) {
        // Roll back on error
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
        // First ensure the reported_at column exists
        $checkColumn = "SHOW COLUMNS FROM posts LIKE 'reported_at'";
        $columnExists = $conn->query($checkColumn)->num_rows > 0;
        
        if (!$columnExists) {
            // Add the column if it doesn't exist
            $addColumn = "ALTER TABLE posts ADD COLUMN reported_at TIMESTAMP NULL DEFAULT NULL";
            if (!$conn->query($addColumn)) {
                throw new Exception("Failed to add reported_at column");
            }
        }

        // Sync post report status with pending reports
        $syncQuery = "UPDATE posts p 
            LEFT JOIN (
                SELECT post_id, MIN(created_at) as first_report
                FROM reports 
                WHERE status = 'pending'
                GROUP BY post_id
            ) r ON p.id = r.post_id 
            SET p.report = CASE
                WHEN r.post_id IS NOT NULL THEN 'reported'
                ELSE 'none'
            END,
            p.reported_at = COALESCE(p.reported_at, r.first_report)";
        
        if (!$conn->query($syncQuery)) {
            throw new Exception("Failed to sync report status: " . $conn->error);
        }

        // Modified query to count all reports correctly
        $query = "SELECT 
            p.id, 
            p.user_id, 
            COALESCE(p.title, 'Untitled Post') as title,
            p.content, 
            p.created_at,
            COALESCE(p.status, 'Active') as status,
            p.report,
            p.reported_at,
            r2.reason as report_reason,
            r2.created_at as report_created_at,
            r2.status as report_status,
            u.username as reporter_username,
            (SELECT COUNT(*) FROM reports WHERE post_id = p.id) as report_count,
            GROUP_CONCAT(DISTINCT pi.image_url) as images
            FROM posts p
            LEFT JOIN (
                SELECT post_id, reason, created_at, status, reporter_id
                FROM reports 
                WHERE created_at = (
                    SELECT MIN(created_at) 
                    FROM reports r3 
                    WHERE r3.post_id = reports.post_id
                )
            ) r2 ON p.id = r2.post_id
            LEFT JOIN users u ON r2.reporter_id = u.id
            LEFT JOIN post_images pi ON p.id = pi.post_id
            WHERE (p.report = 'reported' OR p.status = 'Hidden')
            AND p.status != 'Archived'
            GROUP BY p.id
            ORDER BY 
                CASE 
                    WHEN r2.status = 'pending' THEN 1
                    WHEN p.status = 'Hidden' THEN 2
                    ELSE 3
                END,
                COALESCE(p.reported_at, p.created_at) DESC";
        
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
            LEFT JOIN (
                SELECT marketplace_item_id, MIN(created_at) as first_report
                FROM reports 
                WHERE status = 'pending'
                GROUP BY marketplace_item_id
            ) r ON mi.id = r.marketplace_item_id 
            SET mi.report = CASE
                WHEN r.marketplace_item_id IS NOT NULL THEN 'reported'
                ELSE 'none'
            END,
            mi.reported_at = COALESCE(mi.reported_at, r.first_report)";
        
        $conn->query($syncQuery);

        // Modified query to show all reports for each item
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
            GROUP_CONCAT(DISTINCT mii.image_url) as image_urls,
            (SELECT COUNT(*) FROM reports WHERE marketplace_item_id = mi.id AND status IN ('pending', 'hidden')) as pending_report_count,
            (
                SELECT GROUP_CONCAT(
                    CONCAT(u2.username, ':', r2.reason)
                    ORDER BY r2.created_at DESC
                    SEPARATOR '||'
                )
                FROM reports r2 
                JOIN users u2 ON r2.reporter_id = u2.id 
                WHERE r2.marketplace_item_id = mi.id 
                AND r2.status IN ('pending', 'hidden')
            ) as report_details
        FROM marketplace_items mi
        LEFT JOIN marketplace_item_images mii ON mi.id = mii.marketplace_item_id
        WHERE (mi.report = 'reported' OR mi.status = 'Hidden')
        AND mi.status != 'Archived'
        GROUP BY mi.id
        ORDER BY 
            CASE 
                WHEN mi.status = 'Hidden' THEN 2
                WHEN mi.report = 'reported' THEN 1
                ELSE 3
            END,
            mi.reported_at DESC";

        $resultItems = $conn->query($queryItems);

        if (!$resultItems) {
            throw new Exception($conn->error);
        }

        $items = [];
        while ($row = $resultItems->fetch_assoc()) {
            $row['image_urls'] = $row['image_urls'] ? explode(',', $row['image_urls']) : [];
            
            // Process report details
            if ($row['report_details']) {
                $reports = explode('||', $row['report_details']);
                $latestReport = explode(':', $reports[0]); // Get the most recent report
                $row['reporter_username'] = $latestReport[0];
                $row['report_reason'] = $latestReport[1];
            } else {
                $row['reporter_username'] = 'Unknown';
                $row['report_reason'] = 'No reason provided';
            }
            
            unset($row['report_details']); // Remove the raw report details
            
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
            'error' => 'Failed to fetch items: ' . $e->getMessage()
        ]);
    }
}

function hideMarketplaceItem($conn) {
    $data = json_decode(file_get_contents('php://input'), true);
    $itemId = $data['item_id'] ?? null;

    if (!$itemId) {
        echo json_encode(["success" => false, "error" => "Missing item_id"]);
        return;
    }

    $conn->begin_transaction();

    try {

        $infoStmt = $conn->prepare("
            SELECT m.id, m.user_id, u.email, u.username
            FROM marketplace_items m
            JOIN users u ON m.user_id = u.id
            WHERE m.id = ?
        ");
        $infoStmt->bind_param("i", $itemId);
        $infoStmt->execute();
        $info = $infoStmt->get_result()->fetch_assoc();

        // Get report reasons
        $reportStmt = $conn->prepare("
            SELECT reason 
            FROM reports 
            WHERE post_id = ? AND status = 'pending'
        ");
        $reportStmt->bind_param("i", $itemId);
        $reportStmt->execute();
        $result = $reportStmt->get_result();
        
        $reasons = [];
        while ($row = $result->fetch_assoc()) {
            $reasons[] = $row['reason'];
        }
        // Update marketplace item status
        $updateItem = $conn->prepare("
            UPDATE marketplace_items 
            SET status = 'Hidden'
            WHERE id = ?
        ");
        $updateItem->bind_param("i", $itemId);
        if (!$updateItem->execute()) {
            throw new Exception("Failed to hide item");
        }

        // Update reports status to hidden
        $updateReports = $conn->prepare("
            UPDATE reports 
            SET status = 'hidden',
                resolved_at = CURRENT_TIMESTAMP,
                resolved_by = (SELECT id FROM admins WHERE email = ?)
            WHERE marketplace_item_id = ? AND status = 'pending'
        ");
        $updateReports->bind_param("si", $_SESSION['email'], $itemId);
        if (!$updateReports->execute()) {
            throw new Exception("Failed to update reports");
        }
        
        sendActionEmail(
            $info['email'],
            $info['username'],
            'hidden',
            'post',
            $postId,
            $reasons
        );

        $conn->commit();
        echo json_encode(["success" => true]);

    } catch (Exception $e) {
        $conn->rollback();
        echo json_encode(["success" => false, "error" => $e->getMessage()]);
    }
}

function unhideMarketplaceItem($conn) {
    $data = json_decode(file_get_contents('php://input'), true);
    $itemId = $data['item_id'] ?? null;

    if (!$itemId) {
        echo json_encode(["success" => false, "error" => "Missing item_id"]);
        return;
    }

    $conn->begin_transaction();

    try {
        // Update marketplace item status back to Active
        $updateItem = $conn->prepare("
            UPDATE marketplace_items 
            SET status = 'Active',
                report = 'none'
            WHERE id = ?
        ");
        $updateItem->bind_param("i", $itemId);
        if (!$updateItem->execute()) {
            throw new Exception("Failed to unhide item");
        }

        // Update reports status to resolved
        $updateReports = $conn->prepare("
            UPDATE reports 
            SET status = 'resolved',
                resolved_at = CURRENT_TIMESTAMP,
                resolved_by = (SELECT id FROM admins WHERE email = ?)
            WHERE marketplace_item_id = ? AND status = 'hidden'
        ");
        $updateReports->bind_param("si", $_SESSION['email'], $itemId);
        if (!$updateReports->execute()) {
            throw new Exception("Failed to update reports");
        }

        // Get pending appeals for this item
        $appealStmt = $conn->prepare("
            SELECT a.id, a.user_id, u.email, u.username
            FROM appeals a
            JOIN users u ON a.user_id = u.id
            WHERE a.item_id = ? 
            AND a.status = 'PENDING'
            AND a.concern_type = 'ITEM_POST_PENALTY'
        ");
        $appealStmt->bind_param("i", $itemId);
        $appealStmt->execute();
        $appeals = $appealStmt->get_result();

        while ($appeal = $appeals->fetch_assoc()) {
            // Update appeal status
            $updateAppeal = $conn->prepare("
                UPDATE appeals 
                SET status = 'APPROVED',
                    updated_at = CURRENT_TIMESTAMP
                WHERE id = ?
            ");
            $updateAppeal->bind_param("i", $appeal['id']);
            if (!$updateAppeal->execute()) {
                throw new Exception("Failed to update appeal status");
            }

            // Create notification for user
            $notifQuery = $conn->prepare("
                INSERT INTO notifications 
                (user_id, type, content, marketplace_item_id, trigger_admin_name) 
                VALUES (?, 'system', ?, ?, 
                    (SELECT admin_name FROM admins WHERE email = ?))
            ");
            $content = "Your appeal for the reported marketplace item has been approved. The content will be restored.";
            $notifQuery->bind_param("isis", 
                $appeal['user_id'], 
                $content, 
                $itemId,
                $_SESSION['email']
            );
            $notifQuery->execute();
        }
    
        // Send email notification
        sendAppealStatusEmail(
            $appeal['email'],
            $appeal['username'],
            'APPROVED', 'ITEM_POST_PENALTY'
        );

        $conn->commit();
        echo json_encode(["success" => true]);

    } catch (Exception $e) {
        $conn->rollback();
        error_log("Error in unhideMarketplaceItem: " . $e->getMessage());
        echo json_encode(["success" => false, "error" => $e->getMessage()]);
    }
}

function unhidePost($conn) {
    $data = json_decode(file_get_contents('php://input'), true);
    $postId = $data['post_id'] ?? null;

    if (!$postId) {
        echo json_encode(["success" => false, "error" => "Missing post_id"]);
        return;
    }

    $conn->begin_transaction();

    try {
        // Update post status back to Active
        $updatePost = $conn->prepare("
            UPDATE posts 
            SET status = 'Active',
                report = 'none'
            WHERE id = ?
        ");
        $updatePost->bind_param("i", $postId);
        if (!$updatePost->execute()) {
            throw new Exception("Failed to unhide post");
        }

        // Update reports status to resolved
        $updateReports = $conn->prepare("
            UPDATE reports 
            SET status = 'resolved',
                resolved_at = CURRENT_TIMESTAMP,
                resolved_by = (SELECT id FROM admins WHERE email = ?)
            WHERE post_id = ? AND status = 'hidden'
        ");
        $updateReports->bind_param("si", $_SESSION['email'], $postId);
        if (!$updateReports->execute()) {
            throw new Exception("Failed to update reports");
        }

        // Get pending appeals for this post
        $appealStmt = $conn->prepare("
            SELECT a.id, a.user_id, u.email, u.username
            FROM appeals a
            JOIN users u ON a.user_id = u.id
            WHERE a.post_id = ? 
            AND a.status = 'PENDING'
            AND a.concern_type = 'POST_PENALTY'
        ");
        $appealStmt->bind_param("i", $postId);
        $appealStmt->execute();
        $appeals = $appealStmt->get_result();

        while ($appeal = $appeals->fetch_assoc()) {
            // Update appeal status
            $updateAppeal = $conn->prepare("
                UPDATE appeals 
                SET status = 'APPROVED',
                    updated_at = CURRENT_TIMESTAMP
                WHERE id = ?
            ");
            $updateAppeal->bind_param("i", $appeal['id']);
            if (!$updateAppeal->execute()) {
                throw new Exception("Failed to update appeal status");
            }

            // Create notification for user
            $notifQuery = $conn->prepare("
                INSERT INTO notifications 
                (user_id, type, content, post_id, trigger_admin_name) 
                VALUES (?, 'system', ?, ?, 
                    (SELECT admin_name FROM admins WHERE email = ?))
            ");
            $content = "Your appeal for the reported post has been approved. The content will be restored.";
            $notifQuery->bind_param("isis", 
                $appeal['user_id'], 
                $content, 
                $postId,
                $_SESSION['email']
            );
            $notifQuery->execute();
        }
    
        // Send email notification
        sendAppealStatusEmail(
            $appeal['email'],
            $appeal['username'],
            'APPROVED', 'POST_PENALTY'
        );

        $conn->commit();
        echo json_encode(["success" => true]);

    } catch (Exception $e) {
        $conn->rollback();
        error_log("Error in unhidePost: " . $e->getMessage());
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

function getActionEmailTemplate($username, $action, $contentType, $contentId, $reasons = []) {
    $reasonsList = '';
    if (!empty($reasons)) {
        $reasonsList = "<ul style='margin-top: 10px;'>";
        foreach ($reasons as $reason) {
            $reasonsList .= "<li>{$reason}</li>";
        }
        $reasonsList .= "</ul>";
    }

    $appealText = '';
    if ($action === 'hidden') {
        $appealText = "<p>Feel free to submit an appeal if you have questions or concerns about your {$contentType}.</p>";
    }

    $contentIdText = $contentType === 'post' ? "Post ID: {$contentId}" : "Item ID: {$contentId}";

    return "
        <html>
        <body style='font-family: Arial, sans-serif; line-height: 1.6; color: #333;'>
            <div style='max-width: 600px; margin: 0 auto; padding: 20px;'>
                <h2 style='color: #B91C1C;'>Content {$action}</h2>
                <p>Hello {$username},</p>
                <p>Your {$contentType} has been {$action} for the following reason(s):</p>
                {$reasonsList}
                <p>{$contentIdText}</p>
                {$appealText}
                <p>If you have any questions, please contact our support team.</p>
                <br>
                <p>Best regards,</p>
                <p>RaceConnect Team</p>
            </div>
        </body>
        </html>
    ";
}

function getArchiveEmailTemplate($username, $contentType, $contentId) {
    return "
        <html>
        <body style='font-family: Arial, sans-serif; line-height: 1.6; color: #333;'>
            <div style='max-width: 600px; margin: 0 auto; padding: 20px;'>
                <h2 style='color: #B91C1C;'>Content Archived</h2>
                <p>Hello {$username},</p>
                <p>Your {$contentType} (ID: {$contentId}) has been archived by our moderation team.</p>
                <p>Archived content cannot be restored and this action cannot be appealed.</p>
                <p>If you have any questions, please contact our support team.</p>
                <br>
                <p>Best regards,</p>
                <p>RaceConnect Team</p>
            </div>
        </body>
        </html>
    ";
}

function sendActionEmail($email, $username, $action, $contentType, $contentId, $reasons = []) {
    $mail = new PHPMailer(true);
    try {
        $mail->isSMTP();
        $mail->Host = 'smtp.gmail.com';
        $mail->SMTPAuth = true;
        $mail->Username = $_ENV['SMTP_USER'];
        $mail->Password = $_ENV['SMTP_PASS'];
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port = 587;

        $mail->setFrom('RaceConnect@gmail.com', 'RaceConnect');
        $mail->addAddress($email, $username);

        $mail->isHTML(true);
        $mail->Subject = "Content {$action} Notification";
        $mail->Body = getActionEmailTemplate($username, $action, $contentType, $contentId, $reasons);

        $mail->send();
        return true;
    } catch (Exception $e) {
        error_log("Mail Error: " . $mail->ErrorInfo);
        return false;
    }
}

function sendAppealStatusEmail($email, $username, $status, $concernType) {
    $mail = new PHPMailer(true);
    try {
        // Server settings
        $mail->isSMTP();
        $mail->Host = 'smtp.gmail.com';
        $mail->SMTPAuth = true;
        $mail->Username = $_ENV['SMTP_USER'];
        $mail->Password = $_ENV['SMTP_PASS'];
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port = 587;

        // Recipients
        $mail->setFrom('RaceConnect@gmail.com', 'RaceConnect');
        $mail->addAddress($email, $username);

        // Content
        $mail->isHTML(true);
        $mail->Subject = 'Appeal Status Update';
        $mail->Body = getAppealEmailTemplate($username, $status, $concernType);

        $mail->send();
        return true;
    } catch (Exception $e) {
        error_log("Mail Error: " . $mail->ErrorInfo);
        return false;
    }
}

// Email template function (moved from AppealsController)
function getAppealEmailTemplate($username, $status, $concernType) {
    $statusText = $status === 'APPROVED' ? 'approved' : 'rejected';
    $actionText = $concernType === 'ACCOUNT_PENALTY' ?
        ($status === 'APPROVED' ? 'Your account has been unbanned.' : 'Your account will remain banned.') :
        ($status === 'APPROVED' ? 'The reported content will be restored.' : 'The reported content will remain hidden.');

    return "
        <html>
        <body style='font-family: Arial, sans-serif; line-height: 1.6; color: #333;'>
            <div style='max-width: 600px; margin: 0 auto; padding: 20px;'>
                <h2 style='color: #B91C1C;'>Appeal Status Update</h2>
                <p>Hello {$username},</p>
                <p>Your appeal has been {$statusText}.</p>
                <p>{$actionText}</p>
                <p>If you have any questions, please contact our support team.</p>
                <br>
                <p>Best regards,</p>
                <p>RaceConnect Team</p>
            </div>
        </body>
        </html>
    ";
}

function unbanUser($conn) {
    try {
        $conn->begin_transaction();
        
        $username = $_POST['username'] ?? null;
        if (!$username) {
            throw new Exception("Missing parameters");
        }

        // Get user info using prepared statement
        $query = "SELECT id, email FROM users WHERE username = ?";
        $stmt = $conn->prepare($query);
        $stmt->bind_param("s", $username);
        $stmt->execute();
        $result = $stmt->get_result();
        $user = $result->fetch_assoc();
        
        if (!$user) {
            throw new Exception("User not found");
        }

        // Update user status
        $updateQuery = "UPDATE users SET status = 'Active', suspension_end_date = NULL WHERE id = ?";
        $updateStmt = $conn->prepare($updateQuery);
        $updateStmt->bind_param("i", $user['id']);
        
        if (!$updateStmt->execute()) {
            throw new Exception("Failed to unban user");
        }

        // Check for pending appeals
        $appealQuery = "SELECT id FROM appeals WHERE user_id = ? AND concern_type = 'ACCOUNT_PENALTY' AND status = 'PENDING' LIMIT 1";
        $appealStmt = $conn->prepare($appealQuery);
        $appealStmt->bind_param("i", $user['id']);
        $appealStmt->execute();
        $appealResult = $appealStmt->get_result();
        
        if ($appealResult->num_rows > 0) {
            // Update appeal status
            $updateAppealQuery = "UPDATE appeals SET status = 'APPROVED' WHERE user_id = ? AND concern_type = 'ACCOUNT_PENALTY' AND status = 'PENDING'";
            $updateAppealStmt = $conn->prepare($updateAppealQuery);
            $updateAppealStmt->bind_param("i", $user['id']);
            $updateAppealStmt->execute();

            // Send email notification using the local function
            sendAppealStatusEmail($user['email'], $username, 'APPROVED', 'ACCOUNT_PENALTY');
        }

        $conn->commit();
        echo json_encode([
            "success" => true,
            "message" => "User unbanned successfully"
        ]);

    } catch (Exception $e) {
        if ($conn->inTransaction()) {
            $conn->rollback();
        }
        error_log("Unban error: " . $e->getMessage());
        echo json_encode([
            "success" => false,
            "error" => $e->getMessage()
        ]);
    } finally {
        if (isset($stmt)) $stmt->close();
        if (isset($updateStmt)) $updateStmt->close();
        if (isset($appealStmt)) $appealStmt->close();
        if (isset($updateAppealStmt)) $updateAppealStmt->close();
    }
}

function archivePost($conn) {
    $data = json_decode(file_get_contents('php://input'), true);
    $postId = $data['post_id'] ?? null;
    $password = $data['password'] ?? null;

    if (!$postId || !$password) {
        echo json_encode(["success" => false, "error" => "Missing parameters"]);
        return;
    }

    $conn->begin_transaction();

    try {
        // Verify admin password
        if (!verifyAdminPassword($conn, $_SESSION['email'], $password)) {
            throw new Exception("Invalid password");
        }

        // Get user info first
        $infoStmt = $conn->prepare("
            SELECT p.id, p.user_id, u.email, u.username
            FROM posts p
            JOIN users u ON p.user_id = u.id
            WHERE p.id = ?
        ");
        $infoStmt->bind_param("i", $postId);
        $infoStmt->execute();
        $info = $infoStmt->get_result()->fetch_assoc();

        if (!$info) {
            throw new Exception("Post not found");
        }

        // Update post status
        $updatePost = $conn->prepare("
            UPDATE posts 
            SET status = 'Archived', 
                report = 'none',
                archived_at = CURRENT_TIMESTAMP 
            WHERE id = ?
        ");
        $updatePost->bind_param("i", $postId);
        if (!$updatePost->execute()) {
            throw new Exception("Failed to archive post");
        }

        // Update reports status to resolved
        $updateReports = $conn->prepare("
            UPDATE reports 
            SET status = 'resolved',
                resolved_at = CURRENT_TIMESTAMP,
                resolved_by = (SELECT id FROM admins WHERE email = ?)
            WHERE post_id = ? AND status = 'pending'
        ");
        $updateReports->bind_param("si", $_SESSION['email'], $postId);
        if (!$updateReports->execute()) {
            throw new Exception("Failed to update reports");
        }

        // Send email notification
        $mail = new PHPMailer(true);
        try {
            $mail->isSMTP();
            $mail->Host = 'smtp.gmail.com';
            $mail->SMTPAuth = true;
            $mail->Username = $_ENV['SMTP_USER'];
            $mail->Password = $_ENV['SMTP_PASS'];
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
            $mail->Port = 587;

            $mail->setFrom('RaceConnect@gmail.com', 'RaceConnect');
            $mail->addAddress($info['email'], $info['username']);

            $mail->isHTML(true);
            $mail->Subject = 'Content Archived Notification';
            $mail->Body = getArchiveEmailTemplate($info['username'], 'post', $postId);

            $mail->send();
        } catch (Exception $e) {
            error_log("Mail Error: " . $mail->ErrorInfo);
            // Don't throw exception here as email failure shouldn't stop the archive process
        }

        $conn->commit();
        echo json_encode(["success" => true]);

    } catch (Exception $e) {
        $conn->rollback();
        echo json_encode(["success" => false, "error" => $e->getMessage()]);
    }
}

function archiveMarketplaceItem($conn) {
    $data = json_decode(file_get_contents('php://input'), true);
    $itemId = $data['item_id'] ?? null;
    $password = $data['password'] ?? null;

    if (!$itemId || !$password) {
        echo json_encode(["success" => false, "error" => "Missing parameters"]);
        return;
    }

    $conn->begin_transaction();

    try {
        // Verify admin password
        if (!verifyAdminPassword($conn, $_SESSION['email'], $password)) {
            throw new Exception("Invalid password");
        }

        // Get user info first
        $infoStmt = $conn->prepare("
            SELECT m.id, m.user_id, u.email, u.username
            FROM marketplace_items m
            JOIN users u ON m.user_id = u.id
            WHERE m.id = ?
        ");
        $infoStmt->bind_param("i", $itemId);
        $infoStmt->execute();
        $info = $infoStmt->get_result()->fetch_assoc();

        if (!$info) {
            throw new Exception("Item not found");
        }

        // Update marketplace item status
        $updateItem = $conn->prepare("
            UPDATE marketplace_items 
            SET status = 'Archived', 
                report = 'none',
                archived_at = CURRENT_TIMESTAMP 
            WHERE id = ?
        ");
        $updateItem->bind_param("i", $itemId);
        if (!$updateItem->execute()) {
            throw new Exception("Failed to archive item");
        }

        // Update reports status to resolved
        $updateReports = $conn->prepare("
            UPDATE reports 
            SET status = 'resolved',
                resolved_at = CURRENT_TIMESTAMP,
                resolved_by = (SELECT id FROM admins WHERE email = ?)
            WHERE marketplace_item_id = ? AND status = 'pending'
        ");
        $updateReports->bind_param("si", $_SESSION['email'], $itemId);
        if (!$updateReports->execute()) {
            throw new Exception("Failed to update reports");
        }

        // Send email notification
        $mail = new PHPMailer(true);
        try {
            $mail->isSMTP();
            $mail->Host = 'smtp.gmail.com';
            $mail->SMTPAuth = true;
            $mail->Username = $_ENV['SMTP_USER'];
            $mail->Password = $_ENV['SMTP_PASS'];
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
            $mail->Port = 587;

            $mail->setFrom('RaceConnect@gmail.com', 'RaceConnect');
            $mail->addAddress($info['email'], $info['username']);

            $mail->isHTML(true);
            $mail->Subject = 'Content Archived Notification';
            $mail->Body = getArchiveEmailTemplate($info['username'], 'marketplace item', $itemId);

            $mail->send();
        } catch (Exception $e) {
            error_log("Mail Error: " . $mail->ErrorInfo);
            // Don't throw exception here as email failure shouldn't stop the archive process
        }
        
        $conn->commit();
        echo json_encode(["success" => true]);

    } catch (Exception $e) {
        $conn->rollback();
        echo json_encode(["success" => false, "error" => $e->getMessage()]);
    }
}

function hidePost($conn) {
    $data = json_decode(file_get_contents('php://input'), true);
    $postId = $data['post_id'] ?? null;

    if (!$postId) {
        echo json_encode(["success" => false, "error" => "Missing post_id"]);
        return;
    }

    $conn->begin_transaction();

    try {

        $infoStmt = $conn->prepare("
            SELECT p.id, p.user_id, u.email, u.username
            FROM posts p
            JOIN users u ON p.user_id = u.id
            WHERE p.id = ?
        ");
        $infoStmt->bind_param("i", $postId);
        $infoStmt->execute();
        $info = $infoStmt->get_result()->fetch_assoc();

        // Get report reasons
        $reportStmt = $conn->prepare("
            SELECT reason 
            FROM reports 
            WHERE post_id = ? AND status = 'pending'
        ");
        $reportStmt->bind_param("i", $postId);
        $reportStmt->execute();
        $result = $reportStmt->get_result();
        
        $reasons = [];
        while ($row = $result->fetch_assoc()) {
            $reasons[] = $row['reason'];
        }

        // Update post status
        $updatePost = $conn->prepare("
            UPDATE posts 
            SET status = 'Hidden'
            WHERE id = ?
        ");
        $updatePost->bind_param("i", $postId);
        if (!$updatePost->execute()) {
            throw new Exception("Failed to hide post");
        }

        // Update reports status to hidden
        $updateReports = $conn->prepare("
            UPDATE reports 
            SET status = 'hidden',
                resolved_at = CURRENT_TIMESTAMP,
                resolved_by = (SELECT id FROM admins WHERE email = ?)
            WHERE post_id = ? AND status = 'pending'
        ");
        $updateReports->bind_param("si", $_SESSION['email'], $postId);
        if (!$updateReports->execute()) {
            throw new Exception("Failed to update reports");
        }

        sendActionEmail(
            $info['email'],
            $info['username'],
            'hidden',
            'post',
            $postId,
            $reasons
        );

        $conn->commit();
        echo json_encode(["success" => true]);

    } catch (Exception $e) {
        $conn->rollback();
        echo json_encode(["success" => false, "error" => $e->getMessage()]);
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
    header('Content-Type: application/json');
    ob_clean();
    
    try {
        // Basic validation with sanitization
        if (!isset($_POST['announcementTitle']) || !isset($_POST['announcementContent'])) {
            throw new Exception('Title and content are required');
        }

        $title = htmlspecialchars(trim($_POST['announcementTitle']), ENT_QUOTES, 'UTF-8');
        $content = htmlspecialchars(trim($_POST['announcementContent']), ENT_QUOTES, 'UTF-8');
        $imageUrl = null;
        $fileKey = null;

        // Handle image upload
        if (isset($_FILES['announcementImage']) && 
            $_FILES['announcementImage']['error'] === UPLOAD_ERR_OK && 
            $_FILES['announcementImage']['size'] > 0) {

            // Validate file size (5MB limit)
            if ($_FILES['announcementImage']['size'] > 5 * 1024 * 1024) {
                throw new Exception('File size exceeds 5MB limit');
            }

            // Validate file type
            $allowedTypes = ['image/jpeg', 'image/png', 'image/gif'];
            if (!in_array($_FILES['announcementImage']['type'], $allowedTypes)) {
                throw new Exception('Invalid file type. Only JPG, PNG and GIF are allowed');
            }

            require_once __DIR__ . '/../../vendor/autoload.php';
            $dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/../../');
            $dotenv->load();

            $s3 = new Aws\S3\S3Client([
                'version' => '2006-03-01',
                'region'  => $_ENV['AWS_REGION'],
                'credentials' => [
                    'key'    => $_ENV['AWS_ACCESS_KEY'],
                    'secret' => $_ENV['AWS_SECRET_KEY']
                ],
                'http' => [
                    'verify' => false
                ]
            ]);

            $file = $_FILES['announcementImage'];
            $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
            $filename = uniqid() . '_' . time() . '.' . $extension;
            $fileKey = 'announcements/' . $filename;

            try {
                $result = $s3->putObject([
                    'Bucket' => $_ENV['AWS_S3_BUCKET'],
                    'Key'    => $fileKey,
                    'Body'   => fopen($file['tmp_name'], 'rb'),
                    'ContentType' => $file['type']
                ]);

                $imageUrl = $result['ObjectURL'];
            } catch (Exception $e) {
                throw new Exception('Failed to upload image');
            }
        }

        // Prepared statement for SQL injection prevention
        $stmt = $conn->prepare("
            INSERT INTO announcements (
                title, 
                content, 
                image_url, 
                file_key
            ) VALUES (?, ?, ?, ?)
        ");

        if (!$stmt) {
            throw new Exception("Database error");
        }

        $stmt->bind_param("ssss", $title, $content, $imageUrl, $fileKey);
        
        if (!$stmt->execute()) {
            throw new Exception("Failed to save announcement");
        }

        echo json_encode([
            'success' => true,
            'message' => 'Announcement created successfully',
            'data' => [
                'id' => $stmt->insert_id,
                'image_url' => $imageUrl,
                'file_key' => $fileKey
            ]
        ]);

    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'error' => $e->getMessage()
        ]);
    }
}
?>