<?php
/**
 * Real-Time Notifications Stream (Server-Sent Events)
 * 
 * This endpoint keeps a connection open and pushes new notifications
 * to the browser in real-time as they're created.
 */

// Prevent buffering
@ini_set('output_buffering', 'off');
@ini_set('zlib.output_compression', false);
@ini_set('implicit_flush', true);
ob_implicit_flush(true);
while (ob_get_level()) ob_end_clean();

// Set SSE headers
header('Content-Type: text/event-stream');
header('Cache-Control: no-cache, no-store, must-revalidate');
header('Pragma: no-cache');
header('Expires: 0');
header('X-Accel-Buffering: no'); // Disable nginx buffering
header('Connection: keep-alive');

// Disable time limit for long-running connection
set_time_limit(0);
ignore_user_abort(false);

// Load config without session interference
define('CRON_CONTEXT', true);
require_once __DIR__ . '/../config.php';

// Start session to check login
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Check if logged in
if (!isset($_SESSION['user_id'])) {
    echo "event: error\n";
    echo "data: {\"error\": \"Not authenticated\"}\n\n";
    flush();
    exit;
}

$user_id = $_SESSION['user_id'];
$is_admin = $_SESSION['is_admin'] ?? false;

// Close session to prevent blocking other requests
session_write_close();

// Send initial connection message
echo "event: connected\n";
echo "data: {\"status\": \"connected\", \"user_id\": $user_id}\n\n";
flush();

// Track last check time and last notification ID
$lastCheckTime = date('Y-m-d H:i:s');
$lastNotificationId = 0;
$lastMessageCount = 0;
$lastPendingCount = 0;

// Get initial counts
try {
    $conn = getDBConnection();
    
    // Get last notification ID
    $stmt = $conn->prepare("SELECT MAX(id) as max_id FROM notifications WHERE user_id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result()->fetch_assoc();
    $lastNotificationId = intval($result['max_id'] ?? 0);
    $stmt->close();
    
    // Get initial message count
    $stmt = $conn->prepare("SELECT COUNT(*) as cnt FROM messages WHERE recipient_id = ? AND is_read = FALSE");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $msg_row = $stmt->get_result()->fetch_assoc();
    $lastMessageCount = intval($msg_row['cnt'] ?? 0);
    $stmt->close();
    
    // Get initial pending users count (admins only)
    if ($is_admin) {
        $result = $conn->query("SELECT COUNT(*) as cnt FROM users WHERE is_approved = FALSE");
        if ($result) {
            $pending_row = $result->fetch_assoc();
            $lastPendingCount = intval($pending_row['cnt'] ?? 0);
        }
    }
    
    $conn->close();
} catch (Exception $e) {
    // Continue with defaults
}

// Main event loop
$loopCount = 0;
$maxLoops = 600; // ~10 minutes max, then client reconnects

while ($loopCount < $maxLoops) {
    // Check if client disconnected
    if (connection_aborted()) {
        break;
    }
    
    try {
        $conn = getDBConnection();
        $hasUpdates = false;
        $eventData = [];
        
        // Check for new notifications
        $stmt = $conn->prepare("
            SELECT id, title, message, type, link, created_at 
            FROM notifications 
            WHERE user_id = ? AND id > ? 
            ORDER BY id ASC
        ");
        $stmt->bind_param("ii", $user_id, $lastNotificationId);
        $stmt->execute();
        $result = $stmt->get_result();
        
        $newNotifications = [];
        while ($row = $result->fetch_assoc()) {
            $newNotifications[] = [
                'id' => intval($row['id']),
                'title' => $row['title'],
                'message' => $row['message'],
                'type' => $row['type'] ?? 'info',
                'link' => $row['link'],
                'created_at' => $row['created_at']
            ];
            $lastNotificationId = max($lastNotificationId, intval($row['id']));
        }
        $stmt->close();
        
        if (!empty($newNotifications)) {
            $hasUpdates = true;
            $eventData['notifications'] = $newNotifications;
        }
        
        // Check for new messages
        $stmt = $conn->prepare("SELECT COUNT(*) as cnt FROM messages WHERE recipient_id = ? AND is_read = FALSE");
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $msg_row = $stmt->get_result()->fetch_assoc();
        $newMessageCount = intval($msg_row['cnt'] ?? 0);
        $stmt->close();
        
        if ($newMessageCount !== $lastMessageCount) {
            $hasUpdates = true;
            $eventData['message_count'] = $newMessageCount;
            
            // If count increased, there's a new message
            if ($newMessageCount > $lastMessageCount) {
                $eventData['new_messages'] = $newMessageCount - $lastMessageCount;
            }
            $lastMessageCount = $newMessageCount;
        }
        
        // Check for pending users (admins only)
        if ($is_admin) {
            $result = $conn->query("SELECT COUNT(*) as cnt FROM users WHERE is_approved = FALSE");
            $pending_row = $result ? $result->fetch_assoc() : null;
            $newPendingCount = intval($pending_row['cnt'] ?? 0);
            
            if ($newPendingCount !== $lastPendingCount) {
                $hasUpdates = true;
                $eventData['pending_users_count'] = $newPendingCount;
                
                if ($newPendingCount > $lastPendingCount) {
                    $eventData['new_pending_users'] = $newPendingCount - $lastPendingCount;
                }
                $lastPendingCount = $newPendingCount;
            }
        }
        
        // Get unread notification count
        $stmt = $conn->prepare("SELECT COUNT(*) as cnt FROM notifications WHERE user_id = ? AND is_read = FALSE");
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $notif_row = $stmt->get_result()->fetch_assoc();
        $eventData['unread_count'] = intval($notif_row['cnt'] ?? 0);
        $stmt->close();
        
        $conn->close();
        
        // Send update if there's new data
        if ($hasUpdates) {
            $eventData['timestamp'] = date('Y-m-d H:i:s');
            echo "event: update\n";
            echo "data: " . json_encode($eventData) . "\n\n";
            flush();
        }
        
        // Send heartbeat every 30 seconds to keep connection alive
        if ($loopCount % 15 === 0) {
            echo "event: heartbeat\n";
            echo "data: {\"time\": \"" . date('Y-m-d H:i:s') . "\"}\n\n";
            flush();
        }
        
    } catch (Exception $e) {
        // Send error but continue
        echo "event: error\n";
        echo "data: {\"error\": \"" . addslashes($e->getMessage()) . "\"}\n\n";
        flush();
    }
    
    $loopCount++;
    
    // Sleep 1 second between checks for near-instant notifications
    sleep(1);
}

// Connection ended, client will reconnect
echo "event: reconnect\n";
echo "data: {\"reason\": \"timeout\"}\n\n";
flush();
