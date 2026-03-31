<?php
/**
 * Notifications API
 * 
 * Endpoints:
 * GET /api/notifications.php - Get unread notifications
 * POST /api/notifications.php?action=mark_read&id=X - Mark notification as read
 * POST /api/notifications.php?action=mark_all_read - Mark all as read
 * POST /api/notifications.php?action=dismiss&id=X - Dismiss notification
 */

// Disable maintenance and license enforcement for API
define('CRON_CONTEXT', true);

header('Content-Type: application/json');

// Load config without triggering redirects
require_once __DIR__ . '/../config.php';
if (!defined('UM_FUNCTIONS_LOADED')) { 
    require_once __DIR__ . '/../includes/functions.php'; 
}

// Check if user is logged in
if (!isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized', 'message' => 'Login required']);
    exit;
}

$user_id = $_SESSION['user_id'];
$conn = getDBConnection();

$action = $_REQUEST['action'] ?? 'get';

try {
    switch ($action) {
        case 'get':
            // Get unread notifications
            $limit = min(intval($_GET['limit'] ?? 10), 50);
            $since = $_GET['since'] ?? null;
            
            $sql = "SELECT id, title, message, type, is_read, created_at, link FROM notifications WHERE user_id = ?";
            $params = [$user_id];
            $types = "i";
            
            if ($since) {
                $sql .= " AND created_at > ?";
                $params[] = $since;
                $types .= "s";
            }
            
            $sql .= " ORDER BY created_at DESC LIMIT ?";
            $params[] = $limit;
            $types .= "i";
            
            $stmt = $conn->prepare($sql);
            $stmt->bind_param($types, ...$params);
            $stmt->execute();
            $result = $stmt->get_result();
            
            $notifications = [];
            while ($row = $result->fetch_assoc()) {
                $notifications[] = [
                    'id' => intval($row['id']),
                    'title' => $row['title'],
                    'message' => $row['message'],
                    'type' => $row['type'] ?? 'info',
                    'is_read' => (bool)$row['is_read'],
                    'created_at' => $row['created_at'],
                    'link' => $row['link']
                ];
            }
            $stmt->close();
            
            // Get unread count
            $stmt = $conn->prepare("SELECT COUNT(*) as cnt FROM notifications WHERE user_id = ? AND is_read = FALSE");
            $stmt->bind_param("i", $user_id);
            $stmt->execute();
            $count_row = $stmt->get_result()->fetch_assoc();
            $unread_count = $count_row['cnt'] ?? 0;
            $stmt->close();
            
            // Get message count
            $msg_count = getUnreadMessageCount();
            
            // Get pending users count (admins only)
            $pending_count = 0;
            if (isAdmin() || hasPermission('admin.users')) {
                $result = $conn->query("SELECT COUNT(*) as cnt FROM users WHERE is_approved = FALSE");
                if ($result) {
                    $row = $result->fetch_assoc();
                    $pending_count = intval($row['cnt'] ?? 0);
                }
            }
            
            echo json_encode([
                'success' => true,
                'notifications' => $notifications,
                'unread_count' => intval($unread_count),
                'message_count' => intval($msg_count),
                'pending_users_count' => $pending_count,
                'timestamp' => date('Y-m-d H:i:s')
            ]);
            break;
            
        case 'mark_read':
            $notif_id = intval($_REQUEST['id'] ?? 0);
            if ($notif_id > 0) {
                $stmt = $conn->prepare("UPDATE notifications SET is_read = TRUE WHERE id = ? AND user_id = ?");
                $stmt->bind_param("ii", $notif_id, $user_id);
                $stmt->execute();
                $stmt->close();
            }
            echo json_encode(['success' => true]);
            break;
            
        case 'mark_all_read':
            $stmt = $conn->prepare("UPDATE notifications SET is_read = TRUE WHERE user_id = ?");
            $stmt->bind_param("i", $user_id);
            $stmt->execute();
            $stmt->close();
            echo json_encode(['success' => true]);
            break;
            
        case 'dismiss':
            $notif_id = intval($_REQUEST['id'] ?? 0);
            if ($notif_id > 0) {
                $stmt = $conn->prepare("DELETE FROM notifications WHERE id = ? AND user_id = ?");
                $stmt->bind_param("ii", $notif_id, $user_id);
                $stmt->execute();
                $stmt->close();
            }
            echo json_encode(['success' => true]);
            break;
            
        default:
            http_response_code(400);
            echo json_encode(['error' => 'Invalid action']);
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Server error', 'message' => $e->getMessage()]);
}

$conn->close();
