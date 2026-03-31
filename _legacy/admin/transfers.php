<?php
/**
 * ============================================================
 *  Ultimate Mods – FiveM Community Manager
 * ============================================================
 *
 *  Description:
 *  This file is part of the Ultimate Mods FiveM Community Manager,
 *  a commercial web-based management system designed for FiveM
 *  roleplay communities. The system provides tools for department
 *  management, user administration, applications, announcements,
 *  internal messaging, scheduling, and other community operations.
 *
 *  Copyright:
 *  Copyright © 2026 Ultimate Mods LLC.
 *  All Rights Reserved.
 *
 *  License & Usage:
 *  This software is licensed, not sold. Unauthorized copying,
 *  modification, redistribution, resale, sublicensing, or
 *  reverse engineering of this file or any portion of the
 *  Ultimate Mods FiveM Community Manager is strictly prohibited
 *  without prior written permission from Ultimate Mods LLC.
 *
 *  This file may only be used as part of a valid, purchased
 *  Ultimate Mods license and in accordance with the applicable
 *  license agreement.
 *
 *  Website:
 *  https://ultimate-mods.com/
 *
 * ============================================================
 */
require_once '../config.php';
if (!defined('UM_FUNCTIONS_LOADED')) { require_once __DIR__ . '/../includes/functions.php'; }
if (!defined('TOAST_LOADED')) { require_once __DIR__ . '/../includes/toast.php'; define('TOAST_LOADED', true); }
requireLogin();
requireAdmin();

$conn = getDBConnection();
$user_id = $_SESSION['user_id'];
$message = '';

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && verifyCSRFToken()) {
    $action = $_POST['action'] ?? '';
    $request_id = intval($_POST['request_id'] ?? 0);
    $review_notes = trim($_POST['review_notes'] ?? '');
    
    if ($request_id && in_array($action, ['approve', 'deny'])) {
        $status = $action === 'approve' ? 'approved' : 'denied';
        
        // Get request details first
        $stmt = $conn->prepare("SELECT * FROM transfer_requests WHERE id = ? AND status = 'pending'");
        $stmt->bind_param("i", $request_id);
        $stmt->execute();
        $request = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        
        if ($request) {
            // Update request status
            $stmt = $conn->prepare("UPDATE transfer_requests SET status = ?, reviewed_by = ?, reviewed_at = NOW(), review_notes = ? WHERE id = ?");
            $stmt->bind_param("sisi", $status, $user_id, $review_notes, $request_id);
            $stmt->execute();
            $stmt->close();
            
            if ($action === 'approve') {
                // Get lowest rank in the new department for the transfer
                $stmt = $conn->prepare("SELECT id FROM ranks WHERE department_id = ? ORDER BY rank_order DESC LIMIT 1");
                $stmt->bind_param("i", $request['to_department_id']);
                $stmt->execute();
                $new_rank = $stmt->get_result()->fetch_assoc();
                $stmt->close();
                
                if ($new_rank) {
                    // Remove from old department
                    $stmt = $conn->prepare("DELETE FROM roster WHERE user_id = ? AND department_id = ?");
                    $stmt->bind_param("ii", $request['user_id'], $request['from_department_id']);
                    $stmt->execute();
                    $stmt->close();
                    
                    // Add to new department with lowest rank
                    $stmt = $conn->prepare("INSERT INTO roster (user_id, department_id, rank_id, joined_date) VALUES (?, ?, ?, CURDATE())");
                    $stmt->bind_param("iii", $request['user_id'], $request['to_department_id'], $new_rank['id']);
                    $stmt->execute();
                    $stmt->close();
                }
                
                createNotification($request['user_id'], 'Transfer Approved', 'Your department transfer request has been approved!', 'success');
                $message = 'Transfer approved and processed!';
            } else {
                createNotification($request['user_id'], 'Transfer Denied', 'Your department transfer request has been denied.', 'warning');
                $message = 'Transfer denied.';
            }
            
            logAudit('transfer_' . $action, 'transfer_request', $request_id, "Transfer $status for user " . $request['user_id']);
        }
    }
}

// Get pending requests
$pending = $conn->query("
    SELECT tr.*, u.username, fd.name as from_dept_name, td.name as to_dept_name
    FROM transfer_requests tr
    JOIN users u ON tr.user_id = u.id
    JOIN departments fd ON tr.from_department_id = fd.id
    JOIN departments td ON tr.to_department_id = td.id
    WHERE tr.status = 'pending'
    ORDER BY tr.created_at ASC
")->fetch_all(MYSQLI_ASSOC);

// Get recent processed
$processed = $conn->query("
    SELECT tr.*, u.username, fd.name as from_dept_name, td.name as to_dept_name, r.username as reviewed_by_name
    FROM transfer_requests tr
    JOIN users u ON tr.user_id = u.id
    JOIN departments fd ON tr.from_department_id = fd.id
    JOIN departments td ON tr.to_department_id = td.id
    LEFT JOIN users r ON tr.reviewed_by = r.id
    WHERE tr.status != 'pending'
    ORDER BY tr.reviewed_at DESC
    LIMIT 50
")->fetch_all(MYSQLI_ASSOC);

$conn->close();

$theme = getThemeColors();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Transfer Requests - <?php echo htmlspecialchars(getCommunityName()); ?></title>
    <link rel="icon" type="image/x-icon" href="/favicon.ico">
    <?php include __DIR__ . '/../includes/styles.php'; ?>
    <style>
        .request-card { background: var(--bg-card); border: 1px solid var(--border); border-radius: var(--radius-md); padding: 20px; margin-bottom: 16px; }
        .request-header { display: flex; justify-content: space-between; align-items: start; margin-bottom: 12px; }
        .request-user { font-size: 18px; font-weight: 600; color: var(--text-primary); }
        .request-date { font-size: 12px; color: var(--text-muted); }
        .request-transfer { display: flex; align-items: center; gap: 12px; margin-bottom: 12px; }
        .dept-badge { padding: 6px 14px; background: var(--bg-elevated); border-radius: var(--radius-lg); font-size: 13px; }
        .request-reason { color: var(--text-secondary); font-size: 14px; margin-bottom: 16px; padding: 12px; background: var(--bg-primary); border-radius: var(--radius-sm); }
        .request-actions { display: flex; gap: 10px; }
        .status-approved { color: var(--success); }
        .status-denied { color: var(--danger); }
    </style>
</head>
<body>
<?php include __DIR__ . '/../includes/navbar.php'; ?>

<div class="container">
    <div class="page-header">
        <h1>🔄 Transfer Requests</h1>
    </div>
    
    <?php if ($message) showToast($message, 'success'); ?>
    
    <h2 style="font-size:18px;color:#fff;margin-bottom:16px;">Pending Requests (<?php echo count($pending); ?>)</h2>
    
    <?php if (empty($pending)): ?>
        <div class="empty-state" style="margin-bottom:30px;"><p>No pending transfer requests.</p></div>
    <?php else: ?>
        <?php foreach ($pending as $req): ?>
        <div class="request-card">
            <div class="request-header">
                <div>
                    <div class="request-user"><?php echo htmlspecialchars($req['username']); ?></div>
                    <div class="request-date">Submitted: <?php echo date('M j, Y g:i A', strtotime($req['created_at'])); ?></div>
                </div>
            </div>
            <div class="request-transfer">
                <span class="dept-badge"><?php echo htmlspecialchars($req['from_dept_name']); ?></span>
                <span style="color:var(--text-muted);">→</span>
                <span class="dept-badge"><?php echo htmlspecialchars($req['to_dept_name']); ?></span>
            </div>
            <div class="request-reason"><strong>Reason:</strong> <?php echo htmlspecialchars($req['reason']); ?></div>
            <form method="POST" class="request-actions">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(generateCSRFToken()); ?>">
                <input type="hidden" name="request_id" value="<?php echo $req['id']; ?>">
                <input type="text" name="review_notes" class="form-control" placeholder="Notes (optional)" style="flex:1;">
                <button type="submit" name="action" value="approve" class="btn btn-success">✓ Approve</button>
                <button type="submit" name="action" value="deny" class="btn btn-danger">✗ Deny</button>
            </form>
        </div>
        <?php endforeach; ?>
    <?php endif; ?>
    
    <h2 style="font-size:18px;color:#fff;margin:30px 0 16px;">Recent Decisions</h2>
    
    <div class="table-container">
        <table>
            <thead>
                <tr>
                    <th>User</th>
                    <th>Transfer</th>
                    <th>Status</th>
                    <th>Reviewed By</th>
                    <th>Date</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($processed as $req): ?>
                <tr>
                    <td><?php echo htmlspecialchars($req['username']); ?></td>
                    <td><?php echo htmlspecialchars($req['from_dept_name']); ?> → <?php echo htmlspecialchars($req['to_dept_name']); ?></td>
                    <td class="status-<?php echo $req['status']; ?>"><?php echo ucfirst($req['status']); ?></td>
                    <td><?php echo htmlspecialchars($req['reviewed_by_name'] ?: '—'); ?></td>
                    <td><?php echo date('M j, Y', strtotime($req['reviewed_at'])); ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

</body>
</html>
