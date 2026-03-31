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
ob_start();
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

require_once '../config.php';
if (!defined('UM_FUNCTIONS_LOADED')) { require_once __DIR__ . '/../includes/functions.php'; }
if (!defined('UM_EMAIL_LOADED')) { require_once __DIR__ . '/../includes/email.php'; }
require_once '../includes/permissions_ui.php';
requireLogin();

// Check permission
if (!isAdmin() && !hasAnyPermission(['roster.promote', 'roster.manage'])) {
    header('Location: /index?error=unauthorized');
    exit();
}

$conn = getDBConnection();
$message = '';
$error = '';
$is_admin = isAdmin();
$can_process = $is_admin || hasPermission('roster.promote');

// Handle creating promotion request (anyone with roster.promote or roster.manage can submit)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && verifyCSRFToken() && isset($_POST['create_request']) && $can_process) {
    $user_id = intval($_POST['user_id']);
    $dept_id = intval($_POST['department_id']);
    $current_rank_id = intval($_POST['current_rank_id']);
    $requested_rank_id = intval($_POST['requested_rank_id']);
    $request_type = $_POST['request_type'];
    $reason = trim($_POST['reason']);
    
    $stmt = $conn->prepare("INSERT INTO promotion_requests (user_id, department_id, current_rank_id, requested_rank_id, request_type, reason, requested_by) 
                           VALUES (?, ?, ?, ?, ?, ?, ?)");
    $requested_by = $_SESSION['user_id'];
    $stmt->bind_param("iiiissi", $user_id, $dept_id, $current_rank_id, $requested_rank_id, $request_type, $reason, $requested_by);
    
    if ($stmt->execute()) {
        logAudit('create_promo_request', 'promotion_request', $stmt->insert_id, "Created $request_type request for user #$user_id");
        $message = ucfirst($request_type) . ' request submitted!';
        
        // Notify admins
        $admins = $conn->query("SELECT id FROM users WHERE is_admin = TRUE");
        $stmt_u = $conn->prepare("SELECT username FROM users WHERE id = ?");
        $stmt_u->bind_param("i", $user_id);
        $stmt_u->execute();
        $user_row = $stmt_u->get_result()->fetch_assoc();
        $username = $user_row['username'] ?? 'Unknown';
        $stmt_u->close();
        while ($admin = $admins->fetch_assoc()) {
            createNotification($admin['id'], ucfirst($request_type) . ' Request', "A $request_type request has been submitted for $username", 'info');
        }
    } else {
        $error = 'Failed to submit request.';
    }
    $stmt->close();
}

// Handle approving/denying request
if ($_SERVER['REQUEST_METHOD'] === 'POST' && verifyCSRFToken() && isset($_POST['review_request']) && $can_process) {
    $request_id = intval($_POST['request_id']);
    $status = $_POST['status'];
    $notes = trim($_POST['review_notes'] ?? '');
    $effective_date = $_POST['effective_date'] ?? date('Y-m-d');
    
    // Get request details
    $stmt_r = $conn->prepare("SELECT * FROM promotion_requests WHERE id = ?");
    $stmt_r->bind_param("i", $request_id);
    $stmt_r->execute();
    $request = $stmt_r->get_result()->fetch_assoc();
    $stmt_r->close();
    
    if ($request) {
        $stmt = $conn->prepare("UPDATE promotion_requests SET status = ?, reviewed_by = ?, review_notes = ?, reviewed_at = NOW(), effective_date = ? WHERE id = ?");
        $reviewed_by = $_SESSION['user_id'];
        $stmt->bind_param("sissi", $status, $reviewed_by, $notes, $effective_date, $request_id);
        $stmt->execute();
        $stmt->close();
        
        if ($status === 'approved') {
            // Update roster with new rank
            $stmt = $conn->prepare("UPDATE roster SET rank_id = ? WHERE user_id = ? AND department_id = ?");
            $stmt->bind_param("iii", $request['requested_rank_id'], $request['user_id'], $request['department_id']);
            $stmt->execute();
            $stmt->close();
            
            // Record in promotion history
            $stmt = $conn->prepare("INSERT INTO promotion_history (user_id, department_id, from_rank_id, to_rank_id, change_type, reason, effective_date, processed_by, promotion_request_id) 
                                   VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->bind_param("iiiisssii", $request['user_id'], $request['department_id'], $request['current_rank_id'], $request['requested_rank_id'], $request['request_type'], $request['reason'], $effective_date, $reviewed_by, $request_id);
            $stmt->execute();
            $stmt->close();
            
            // Get rank names for notification
            $stmt_fr = $conn->prepare("SELECT rank_name FROM ranks WHERE id = ?");
            $stmt_fr->bind_param("i", $request['current_rank_id']);
            $stmt_fr->execute();
            $from_rank_row = $stmt_fr->get_result()->fetch_assoc();
            $from_rank = $from_rank_row['rank_name'] ?? 'Unknown';
            $stmt_fr->close();
            $stmt_tr = $conn->prepare("SELECT rank_name FROM ranks WHERE id = ?");
            $stmt_tr->bind_param("i", $request['requested_rank_id']);
            $stmt_tr->execute();
            $to_rank_row = $stmt_tr->get_result()->fetch_assoc();
            $to_rank = $to_rank_row['rank_name'] ?? 'Unknown';
            $stmt_tr->close();
            
            // Notify user
            $type_text = $request['request_type'] === 'promotion' ? 'promoted' : ($request['request_type'] === 'demotion' ? 'demoted' : 'transferred');
            createNotification($request['user_id'], ucfirst($request['request_type']) . ' Approved', "You have been $type_text from $from_rank to $to_rank!", $request['request_type'] === 'promotion' ? 'success' : 'warning');
            
            $message = ucfirst($request['request_type']) . ' approved and processed!';
        } else {
            // Notify user of denial
            createNotification($request['user_id'], ucfirst($request['request_type']) . ' Request Denied', "Your " . $request['request_type'] . " request has been denied." . ($notes ? " Reason: $notes" : ''), 'warning');
            $message = 'Request denied.';
        }
        
        logAudit('review_promo_request', 'promotion_request', $request_id, "Reviewed request: $status");
    }
}

// Handle cancelling request
if ($_SERVER['REQUEST_METHOD'] === 'POST' && verifyCSRFToken() && isset($_POST['cancel_request'])) {
    $request_id = intval($_POST['request_id']);
    
    // Only allow cancelling own requests or admin
    $stmt_r2 = $conn->prepare("SELECT requested_by FROM promotion_requests WHERE id = ? AND status = 'pending'");
    $stmt_r2->bind_param("i", $request_id);
    $stmt_r2->execute();
    $request = $stmt_r2->get_result()->fetch_assoc();
    $stmt_r2->close();
    if ($request && ($is_admin || $request['requested_by'] == $_SESSION['user_id'])) {
        $stmt_c = $conn->prepare("UPDATE promotion_requests SET status = 'cancelled' WHERE id = ?");
        $stmt_c->bind_param("i", $request_id);
        $stmt_c->execute();
        $stmt_c->close();
        logAudit('cancel_promo_request', 'promotion_request', $request_id, "Cancelled request");
        $message = 'Request cancelled.';
    }
}

// Get pending requests
$pending_requests = $conn->query("SELECT pr.*, 
                                   u.username, 
                                   d.name as dept_name, d.abbreviation,
                                   cr.rank_name as current_rank_name,
                                   rr.rank_name as requested_rank_name,
                                   rb.username as requested_by_name
                                   FROM promotion_requests pr
                                   JOIN users u ON pr.user_id = u.id
                                   JOIN departments d ON pr.department_id = d.id
                                   JOIN ranks cr ON pr.current_rank_id = cr.id
                                   JOIN ranks rr ON pr.requested_rank_id = rr.id
                                   JOIN users rb ON pr.requested_by = rb.id
                                   WHERE pr.status = 'pending'
                                   ORDER BY pr.created_at DESC");

// Get recent history
$recent_history = $conn->query("SELECT ph.*, 
                                 u.username,
                                 d.name as dept_name, d.abbreviation,
                                 fr.rank_name as from_rank_name,
                                 tr.rank_name as to_rank_name,
                                 pb.username as processed_by_name
                                 FROM promotion_history ph
                                 JOIN users u ON ph.user_id = u.id
                                 JOIN departments d ON ph.department_id = d.id
                                 LEFT JOIN ranks fr ON ph.from_rank_id = fr.id
                                 JOIN ranks tr ON ph.to_rank_id = tr.id
                                 LEFT JOIN users pb ON ph.processed_by = pb.id
                                 ORDER BY ph.effective_date DESC, ph.created_at DESC
                                 LIMIT 30");

// Get all requests (for history tab)
$all_requests = $conn->query("SELECT pr.*, 
                               u.username, 
                               d.abbreviation,
                               cr.rank_name as current_rank_name,
                               rr.rank_name as requested_rank_name,
                               rb.username as requested_by_name,
                               rv.username as reviewed_by_name
                               FROM promotion_requests pr
                               JOIN users u ON pr.user_id = u.id
                               JOIN departments d ON pr.department_id = d.id
                               JOIN ranks cr ON pr.current_rank_id = cr.id
                               JOIN ranks rr ON pr.requested_rank_id = rr.id
                               JOIN users rb ON pr.requested_by = rb.id
                               LEFT JOIN users rv ON pr.reviewed_by = rv.id
                               ORDER BY pr.created_at DESC
                               LIMIT 50");

// Get roster entries for creating requests
$roster = $conn->query("SELECT r.*, u.username, d.name as dept_name, d.abbreviation, rk.rank_name, rk.rank_order
                        FROM roster r
                        JOIN users u ON r.user_id = u.id
                        JOIN departments d ON r.department_id = d.id
                        JOIN ranks rk ON r.rank_id = rk.id
                        WHERE r.status = 'active'
                        ORDER BY d.name, rk.rank_order, u.username");

// Get departments for rank lookup
$departments = $conn->query("SELECT id, name FROM departments ORDER BY name");

// Stats - use safe queries
$stats = [
    'pending' => safeQueryCount($conn, "SELECT COUNT(*) as cnt FROM promotion_requests WHERE status = 'pending'"),
    'this_month' => safeQueryCount($conn, "SELECT COUNT(*) as cnt FROM promotion_history WHERE MONTH(effective_date) = MONTH(CURDATE()) AND YEAR(effective_date) = YEAR(CURDATE())"),
    'promotions' => safeQueryCount($conn, "SELECT COUNT(*) as cnt FROM promotion_history WHERE change_type = 'promotion' AND YEAR(effective_date) = YEAR(CURDATE())"),
    'demotions' => safeQueryCount($conn, "SELECT COUNT(*) as cnt FROM promotion_history WHERE change_type = 'demotion' AND YEAR(effective_date) = YEAR(CURDATE())")
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Promotions & Demotions - <?php echo COMMUNITY_NAME; ?></title>
    <link rel="icon" type="image/x-icon" href="/favicon.ico">
    <link rel="icon" type="image/x-icon" href="<?php echo FAVICON_PATH; ?>">
    <?php include '../includes/styles.php'; ?>
    <style>
        .stats-row { display: grid; grid-template-columns: repeat(4, 1fr); gap: 16px; margin-bottom: 24px; }
        @media (max-width: 768px) { .stats-row { grid-template-columns: repeat(2, 1fr); } }
        .stat-card { background: var(--bg-card); border-radius: var(--radius-lg); padding: 20px; text-align: center; }
        .stat-value { font-size: 32px; font-weight: 800; color: var(--text-primary); }
        .stat-label { font-size: 12px; color: var(--text-muted); margin-top: 4px; }
        
        .request-card {
            background: var(--bg-elevated);
            border: 1px solid var(--bg-elevated);
            border-radius: var(--radius-lg);
            padding: 24px;
            margin-bottom: 16px;
        }
        .request-card.promotion { border-left: 4px solid var(--success); }
        .request-card.demotion { border-left: 4px solid var(--danger); }
        .request-card.lateral { border-left: 4px solid #3b82f6; }
        
        .request-header { display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 16px; }
        .request-user { font-size: 20px; font-weight: 700; }
        .request-dept { font-size: 12px; color: var(--text-muted); }
        
        .rank-change { display: flex; align-items: center; gap: 16px; margin: 16px 0; padding: 16px; background: var(--bg-card); border-radius: var(--radius-md); }
        .rank-box { flex: 1; text-align: center; padding: 12px; background: var(--bg-elevated); border-radius: var(--radius-sm); }
        .rank-label { font-size: 11px; color: var(--text-muted); margin-bottom: 4px; }
        .rank-name { font-weight: 700; font-size: 16px; }
        .rank-arrow { font-size: 24px; color: var(--text-primary); }
        
        .request-meta { font-size: 12px; color: var(--text-muted); margin-top: 12px; }
        .request-reason { background: var(--bg-elevated); padding: 12px; border-radius: var(--radius-sm); margin-top: 12px; font-size: 13px; }
        
        .history-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 14px 16px;
            background: var(--bg-elevated);
            border-radius: var(--radius-md);
            margin-bottom: 8px;
        }
        .history-info { display: flex; align-items: center; gap: 12px; }
        
        .badge { padding: 4px 10px; border-radius: var(--radius-lg); font-size: 11px; font-weight: 600; }
        .badge-pending { background: rgba(251, 191, 36, 0.2); color: #f0b232; }
        .badge-approved { background: rgba(16, 185, 129, 0.2); color: #4ade80; }
        .badge-denied { background: rgba(239, 68, 68, 0.2); color: #f87171; }
        .badge-cancelled { background: rgba(107, 114, 128, 0.2); color: #9ca3af; }
        .badge-promotion { background: rgba(16, 185, 129, 0.2); color: #4ade80; }
        .badge-demotion { background: rgba(239, 68, 68, 0.2); color: #f87171; }
        .badge-lateral { background: rgba(59, 130, 246, 0.2); color: #93c5fd; }
        
        .btn { padding: 10px 20px; border: none; border-radius: var(--radius-md); cursor: pointer; font-weight: 600; transition: all 0.3s; }
        .btn-primary { background: var(--accent); color: white; }
        .btn-sm { padding: 8px 14px; font-size: 12px; }
        .btn-success { background: linear-gradient(135deg, var(--success), #059669); color: white; }
        .btn-danger { background: linear-gradient(135deg, var(--danger), #dc2626); color: white; }
        
        .tabs { display: flex; gap: 8px; margin-bottom: 24px; }
        .tab { padding: 12px 24px; border-radius: var(--radius-md); background: var(--bg-card); color: var(--text-secondary); cursor: pointer; font-weight: 600; }
        .tab:hover { background: var(--bg-elevated); }
        .tab.active { background: var(--accent); color: white; }
        .tab-content { display: none; }
        .tab-content.active { display: block; }
        
        
        .form-group { margin-bottom: 16px; }
        .form-group label { display: block; margin-bottom: 6px; font-weight: 600; font-size: 13px; }
        .form-group input, .form-group select, .form-group textarea {
            width: 100%; padding: 10px 14px; border: 2px solid var(--bg-elevated);
            border-radius: var(--radius-md); background: var(--bg-card); color: var(--text-primary);
        }
        .form-group input:focus, .form-group select:focus { outline: none; border-color: var(--accent); }
        
        .message { background: linear-gradient(135deg, rgba(16, 185, 129, 0.2), rgba(5, 150, 105, 0.2)); border: 1px solid rgba(16, 185, 129, 0.3); color: #4ade80; padding: 16px; border-radius: var(--radius-md); margin-bottom: 20px; }
    </style>
</head>
<body>
    <?php $current_page = 'admin_promotions'; include '../includes/navbar.php'; ?>
    
    <div class="container">
        <?php showPageToasts(); ?>
        
        <div class="stats-row">
            <div class="stat-card">
                <div class="stat-value" style="<?php echo $stats['pending'] > 0 ? 'color: #f0b232; -webkit-text-fill-color: #f0b232;' : ''; ?>"><?php echo $stats['pending']; ?></div>
                <div class="stat-label">Pending Requests</div>
            </div>
            <div class="stat-card">
                <div class="stat-value"><?php echo $stats['this_month']; ?></div>
                <div class="stat-label">This Month</div>
            </div>
            <div class="stat-card">
                <div class="stat-value" style="color: #4ade80; -webkit-text-fill-color: #4ade80;"><?php echo $stats['promotions']; ?></div>
                <div class="stat-label">Promotions (YTD)</div>
            </div>
            <div class="stat-card">
                <div class="stat-value" style="color: #f87171; -webkit-text-fill-color: #f87171;"><?php echo $stats['demotions']; ?></div>
                <div class="stat-label">Demotions (YTD)</div>
            </div>
        </div>
        
        <div class="tabs">
            <div class="tab active" onclick="showTab('pending', this)">📋 Pending (<?php echo $stats['pending']; ?>)</div>
            <div class="tab" onclick="showTab('history', this)">📜 History</div>
            <div class="tab" onclick="showTab('requests', this)">📝 All Requests</div>
        </div>
        
        <div style="display: flex; justify-content: flex-end; margin-bottom: 20px;">
            <?php if ($can_process): ?>
            <button class="btn btn-primary" onclick="openRequestModal()">+ New Request</button>
            <?php else: ?>
            <?php lockedButton('+ New Request', 'Promote/Demote permission required'); ?>
            <?php endif; ?>
        </div>
        
        <!-- Pending Tab -->
        <div class="tab-content active" id="tab-pending">
            <?php if ($pending_requests && $pending_requests->num_rows > 0): ?>
                <?php while ($req = $pending_requests->fetch_assoc()): ?>
                <div class="request-card <?php echo $req['request_type']; ?>">
                    <div class="request-header">
                        <div>
                            <div class="request-user"><?php echo htmlspecialchars($req['username']); ?></div>
                            <div class="request-dept"><?php echo htmlspecialchars($req['dept_name']); ?> (<?php echo htmlspecialchars($req['abbreviation']); ?>)</div>
                        </div>
                        <span class="badge badge-<?php echo $req['request_type']; ?>"><?php echo strtoupper($req['request_type']); ?></span>
                    </div>
                    
                    <div class="rank-change">
                        <div class="rank-box">
                            <div class="rank-label">Current Rank</div>
                            <div class="rank-name"><?php echo htmlspecialchars($req['current_rank_name']); ?></div>
                        </div>
                        <div class="rank-arrow">→</div>
                        <div class="rank-box">
                            <div class="rank-label">Requested Rank</div>
                            <div class="rank-name"><?php echo htmlspecialchars($req['requested_rank_name']); ?></div>
                        </div>
                    </div>
                    
                    <div class="request-reason">
                        <strong>Reason:</strong> <?php echo nl2br(htmlspecialchars($req['reason'])); ?>
                    </div>
                    
                    <div class="request-meta">
                        Requested by <?php echo htmlspecialchars($req['requested_by_name']); ?> on <?php echo date('M j, Y g:i A', strtotime($req['created_at'])); ?>
                    </div>
                    
                    <div style="display: flex; gap: 12px; margin-top: 16px;">
                        <?php if ($can_process): ?>
                        <button class="btn btn-success" onclick="openReviewModal(<?php echo $req['id']; ?>, 'approved', '<?php echo htmlspecialchars(addslashes($req['username'])); ?>')">✓ Approve</button>
                        <button class="btn btn-danger" onclick="openReviewModal(<?php echo $req['id']; ?>, 'denied', '<?php echo htmlspecialchars(addslashes($req['username'])); ?>')">✕ Deny</button>
                        <form method="POST" style="margin-left: auto;" onsubmit="return confirm('Cancel this request?')">
                    <?php echo csrfField(); ?>
                            <input type="hidden" name="request_id" value="<?php echo $req['id']; ?>">
                            <button type="submit" name="cancel_request" class="btn btn-sm" style="background: var(--bg-elevated);">Cancel</button>
                        </form>
                        <?php else: ?>
                        <?php lockedButton('Approve', 'Promote/Demote permission required'); ?>
                        <?php lockedButton('Deny', 'Promote/Demote permission required'); ?>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endwhile; ?>
            <?php else: ?>
                <div class="section" style="text-align: center; padding: 60px; color: var(--text-muted);">
                    No pending requests
                </div>
            <?php endif; ?>
        </div>
        
        <!-- History Tab -->
        <div class="tab-content" id="tab-history">
            <div class="section">
                <?php if ($recent_history && $recent_history->num_rows > 0): ?>
                    <?php while ($h = $recent_history->fetch_assoc()): ?>
                    <div class="history-row">
                        <div class="history-info">
                            <span class="badge badge-<?php echo $h['change_type']; ?>"><?php echo strtoupper($h['change_type']); ?></span>
                            <div>
                                <strong><?php echo htmlspecialchars($h['username']); ?></strong> (<?php echo htmlspecialchars($h['abbreviation']); ?>)
                                <div style="font-size: 12px; color: var(--text-muted);">
                                    <?php echo $h['from_rank_name'] ? htmlspecialchars($h['from_rank_name']) . ' → ' : ''; ?><?php echo htmlspecialchars($h['to_rank_name']); ?>
                                </div>
                            </div>
                        </div>
                        <div style="text-align: right; font-size: 12px; color: var(--text-muted);">
                            <?php echo date('M j, Y', strtotime($h['effective_date'])); ?>
                            <?php if ($h['processed_by_name']): ?><br>by <?php echo htmlspecialchars($h['processed_by_name']); ?><?php endif; ?>
                        </div>
                    </div>
                    <?php endwhile; ?>
                <?php else: ?>
                    <div style="text-align: center; padding: 40px; color: var(--text-muted);">No promotion history yet</div>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- All Requests Tab -->
        <div class="tab-content" id="tab-requests">
            <div class="section">
                <?php if ($all_requests && $all_requests->num_rows > 0): ?>
                    <?php while ($r = $all_requests->fetch_assoc()): ?>
                    <div class="history-row">
                        <div class="history-info">
                            <span class="badge badge-<?php echo $r['status']; ?>"><?php echo strtoupper($r['status']); ?></span>
                            <div>
                                <strong><?php echo htmlspecialchars($r['username']); ?></strong> (<?php echo htmlspecialchars($r['abbreviation']); ?>)
                                <div style="font-size: 12px; color: var(--text-muted);">
                                    <?php echo htmlspecialchars($r['current_rank_name']); ?> → <?php echo htmlspecialchars($r['requested_rank_name']); ?>
                                    (<?php echo $r['request_type']; ?>)
                                </div>
                            </div>
                        </div>
                        <div style="text-align: right; font-size: 12px; color: var(--text-muted);">
                            <?php echo date('M j, Y', strtotime($r['created_at'])); ?>
                            <br>by <?php echo htmlspecialchars($r['requested_by_name']); ?>
                        </div>
                    </div>
                    <?php endwhile; ?>
                <?php else: ?>
                    <div style="text-align: center; padding: 40px; color: var(--text-muted);">No requests yet</div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <!-- New Request Modal -->
    <div class="modal" id="requestModal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>New Promotion/Demotion Request</h3>
                <button class="modal-close" onclick="closeModal('requestModal')">&times;</button>
            </div>
            <form method="POST">
                    <?php echo csrfField(); ?>
                <div class="form-group">
                    <label>Member *</label>
                    <select name="user_id" id="request_user" required onchange="loadUserInfo()">
                        <option value="">Select Member</option>
                        <?php while ($roster && $r = $roster->fetch_assoc()): ?>
                        <option value="<?php echo $r['user_id']; ?>" 
                                data-dept="<?php echo $r['department_id']; ?>"
                                data-rank="<?php echo $r['rank_id']; ?>"
                                data-rank-name="<?php echo htmlspecialchars($r['rank_name']); ?>">
                            <?php echo htmlspecialchars($r['username']); ?> - <?php echo htmlspecialchars($r['abbreviation']); ?> (<?php echo htmlspecialchars($r['rank_name']); ?>)
                        </option>
                        <?php endwhile; ?>
                    </select>
                </div>
                
                <input type="hidden" name="department_id" id="request_dept">
                <input type="hidden" name="current_rank_id" id="request_current_rank">
                
                <div class="form-group">
                    <label>Current Rank</label>
                    <input type="text" id="current_rank_display" disabled style="opacity: 0.7;">
                </div>
                
                <div class="form-group">
                    <label>Request Type *</label>
                    <select name="request_type" required>
                        <option value="promotion">Promotion</option>
                        <option value="demotion">Demotion</option>
                        <option value="lateral">Lateral Transfer</option>
                    </select>
                </div>
                
                <div class="form-group">
                    <label>New Rank *</label>
                    <select name="requested_rank_id" id="request_new_rank" required>
                        <option value="">Select new rank</option>
                    </select>
                </div>
                
                <div class="form-group">
                    <label>Reason/Justification *</label>
                    <textarea name="reason" rows="3" required placeholder="Provide justification for this request..."></textarea>
                </div>
                
                <button type="submit" name="create_request" class="btn btn-primary" style="width: 100%;">Submit Request</button>
            </form>
        </div>
    </div>
    
    <!-- Review Modal -->
    <div class="modal" id="reviewModal">
        <div class="modal-content" style="max-width: 400px;">
            <div class="modal-header">
                <h3 id="review_title">Review Request</h3>
                <button class="modal-close" onclick="closeModal('reviewModal')">&times;</button>
            </div>
            <form method="POST">
                    <?php echo csrfField(); ?>
                <input type="hidden" name="request_id" id="review_request_id">
                <input type="hidden" name="status" id="review_status">
                
                <p style="margin-bottom: 16px;">Processing request for <strong id="review_username"></strong></p>
                
                <div class="form-group">
                    <label>Effective Date</label>
                    <input type="date" name="effective_date" value="<?php echo date('Y-m-d'); ?>">
                </div>
                
                <div class="form-group">
                    <label>Notes (optional)</label>
                    <textarea name="review_notes" rows="3"></textarea>
                </div>
                
                <button type="submit" name="review_request" id="review_submit" class="btn btn-primary" style="width: 100%;">Confirm</button>
            </form>
        </div>
    </div>
    
    <!-- Rank data: isolated block so any PHP error here can't break the functions below -->
    <script>
        var ranksByDept = {};
        <?php
        if ($departments && $departments instanceof mysqli_result) {
            $departments->data_seek(0);
            while ($d = $departments->fetch_assoc()) {
                $stmt_rk = $conn->prepare("SELECT id, rank_name, rank_order FROM ranks WHERE department_id = ? ORDER BY rank_order");
                if ($stmt_rk) {
                    $stmt_rk->bind_param("i", $d['id']);
                    $stmt_rk->execute();
                    $ranks = $stmt_rk->get_result();
                    $rankJson = json_encode($ranks->fetch_all(MYSQLI_ASSOC));
                    $stmt_rk->close();
                    echo "ranksByDept[" . intval($d['id']) . "] = " . ($rankJson !== false ? $rankJson : '[]') . ";\n";
                }
            }
        }
        ?>
    </script>

    <script>
        function showTab(tab, el) {
            document.querySelectorAll('.tab').forEach(function(t) { t.classList.remove('active'); });
            document.querySelectorAll('.tab-content').forEach(function(c) {
                c.classList.remove('active');
                c.style.display = 'none';
            });
            el.classList.add('active');
            var target = document.getElementById('tab-' + tab);
            if (target) {
                target.classList.add('active');
                target.style.display = 'block';
            }
        }

        // Initialise display state on load
        document.addEventListener('DOMContentLoaded', function() {
            document.querySelectorAll('.tab-content').forEach(function(c) {
                c.style.display = c.classList.contains('active') ? 'block' : 'none';
            });
        });

        function openRequestModal() {
            openModal('requestModal');
        }

        function loadUserInfo() {
            var select = document.getElementById('request_user');
            var option = select.options[select.selectedIndex];

            if (option.value) {
                var deptId = option.dataset.dept;
                var rankId = option.dataset.rank;
                var rankName = option.dataset.rankName;

                document.getElementById('request_dept').value = deptId;
                document.getElementById('request_current_rank').value = rankId;
                document.getElementById('current_rank_display').value = rankName;

                var newRankSelect = document.getElementById('request_new_rank');
                newRankSelect.innerHTML = '<option value="">Select new rank</option>';

                if (ranksByDept[deptId]) {
                    ranksByDept[deptId].forEach(function(rank) {
                        if (rank.id != rankId) {
                            var opt = document.createElement('option');
                            opt.value = rank.id;
                            opt.textContent = rank.rank_name;
                            newRankSelect.appendChild(opt);
                        }
                    });
                }
            }
        }

        function openReviewModal(requestId, status, username) {
            document.getElementById('review_request_id').value = requestId;
            document.getElementById('review_status').value = status;
            document.getElementById('review_username').textContent = username;
            document.getElementById('review_title').textContent = status === 'approved' ? 'Approve Request' : 'Deny Request';
            document.getElementById('review_submit').textContent = status === 'approved' ? 'Approve' : 'Deny';
            document.getElementById('review_submit').className = status === 'approved' ? 'btn btn-success' : 'btn btn-danger';
            document.getElementById('review_submit').style.width = '100%';
            openModal('reviewModal');
        }
    </script>
</body>
</html>
<?php $conn->close(); ?>
