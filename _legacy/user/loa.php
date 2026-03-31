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
if (!defined('UM_EMAIL_LOADED')) { require_once __DIR__ . '/../includes/email.php'; }
requireLogin();

$conn = getDBConnection();
$message = '';
$error = '';
$is_admin = isAdmin();

// Handle LOA submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && verifyCSRFToken() && isset($_POST['submit_loa'])) {
    $user_id = $_SESSION['user_id'];
    $start_date = $_POST['start_date'];
    $end_date = $_POST['end_date'];
    $reason = trim($_POST['reason']);
    
    $stmt = $conn->prepare("INSERT INTO loa_requests (user_id, start_date, end_date, reason) VALUES (?, ?, ?, ?)");
    $stmt->bind_param("isss", $user_id, $start_date, $end_date, $reason);
    $stmt->execute();
    
    logAudit('submit_loa', 'loa', $stmt->insert_id, "Submitted LOA request");
    $message = 'LOA request submitted successfully!';
    $stmt->close();
}

// Handle LOA cancellation (user cancels their own LOA)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && verifyCSRFToken() && isset($_POST['cancel_loa'])) {
    $loa_id = intval($_POST['loa_id']);
    $user_id = $_SESSION['user_id'];
    
    // Only allow cancelling own LOAs that are pending or approved and haven't fully passed
    $stmt = $conn->prepare("SELECT * FROM loa_requests WHERE id = ? AND user_id = ?");
    $stmt->bind_param("ii", $loa_id, $user_id);
    $stmt->execute();
    $loa = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    
    if ($loa && in_array($loa['status'], ['pending', 'approved'])) {
        // If approved, reset roster status back to active
        if ($loa['status'] === 'approved') {
            $stmt = $conn->prepare("UPDATE roster SET status = 'active' WHERE user_id = ? AND status = 'loa'");
            $stmt->bind_param("i", $user_id);
            $stmt->execute();
            $stmt->close();
        }
        
        // Delete the LOA record entirely
        $stmt = $conn->prepare("DELETE FROM loa_requests WHERE id = ? AND user_id = ?");
        $stmt->bind_param("ii", $loa_id, $user_id);
        $stmt->execute();
        $stmt->close();
        
        logAudit('cancel_loa', 'loa', $loa_id, "Cancelled LOA: " . $loa['start_date'] . " to " . $loa['end_date']);
        $message = 'LOA has been cancelled successfully.';
    } else {
        $error = 'Unable to cancel this LOA request.';
    }
}

// Handle LOA approval/denial (admin only)
if ($is_admin && $_SERVER['REQUEST_METHOD'] === 'POST' && verifyCSRFToken()) {
    if (isset($_POST['approve_loa'])) {
        $loa_id = intval($_POST['loa_id']);
        $stmt = $conn->prepare("UPDATE loa_requests SET status = 'approved', approved_by = ? WHERE id = ?");
        $stmt->bind_param("ii", $_SESSION['user_id'], $loa_id);
        $stmt->execute();
        
        $loa_stmt = $conn->prepare("SELECT user_id FROM loa_requests WHERE id = ?");
        $loa_stmt->bind_param("i", $loa_id);
        $loa_stmt->execute();
        $loa = $loa_stmt->get_result()->fetch_assoc();
        $loa_stmt->close();
        $stmt_roster = $conn->prepare("UPDATE roster SET status = 'loa' WHERE user_id = ?");
        $stmt_roster->bind_param("i", $loa['user_id']);
        $stmt_roster->execute();
        $stmt_roster->close();
        
        createNotification($loa['user_id'], 'LOA Approved', 'Your leave of absence request has been approved.', 'success');
        
        logAudit('approve_loa', 'loa', $loa_id, 'Approved LOA request');
        $message = 'LOA request approved!';
        $stmt->close();
        
    } elseif (isset($_POST['deny_loa'])) {
        $loa_id = intval($_POST['loa_id']);
        $stmt = $conn->prepare("UPDATE loa_requests SET status = 'denied', approved_by = ? WHERE id = ?");
        $stmt->bind_param("ii", $_SESSION['user_id'], $loa_id);
        $stmt->execute();
        
        $loa_stmt = $conn->prepare("SELECT user_id FROM loa_requests WHERE id = ?");
        $loa_stmt->bind_param("i", $loa_id);
        $loa_stmt->execute();
        $loa = $loa_stmt->get_result()->fetch_assoc();
        $loa_stmt->close();
        createNotification($loa['user_id'], 'LOA Denied', 'Your leave of absence request has been denied.', 'warning');
        
        logAudit('deny_loa', 'loa', $loa_id, 'Denied LOA request');
        $message = 'LOA request denied.';
        $stmt->close();
    }
}

$user_id = $_SESSION['user_id'];
$stmt = $conn->prepare("SELECT * FROM loa_requests WHERE user_id = ? ORDER BY created_at DESC");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$user_loas = $stmt->get_result();

if ($is_admin) {
    $pending_loas = $conn->query("
        SELECT l.*, u.username 
        FROM loa_requests l 
        JOIN users u ON l.user_id = u.id 
        WHERE l.status = 'pending' 
        ORDER BY l.created_at ASC
    ");
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Leave of Absence - <?php echo COMMUNITY_NAME; ?></title>
    <link rel="icon" type="image/x-icon" href="/favicon.ico">
    <link rel="icon" type="image/x-icon" href="<?php echo FAVICON_PATH; ?>">
    <?php include '../includes/styles.php'; ?>
    <style>
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        @keyframes slideIn {
            from { opacity: 0; transform: translateX(-20px); }
            to { opacity: 1; transform: translateX(0); }
        }
        
        .container {
            max-width: 1400px;
        }
        
        .message {
            background: linear-gradient(135deg, rgba(16, 185, 129, 0.2) 0%, rgba(5, 150, 105, 0.2) 100%);
            
            border: 1px solid rgba(16, 185, 129, 0.3);
            color: #d1fae5;
            padding: 18px 24px;
            border-radius: var(--radius-lg);
            margin-bottom: 24px;
            animation: slideIn 0.5s ease;
            box-shadow: 0 8px 32px rgba(16, 185, 129, 0.2);
        }
        
        .section {
            animation: fadeIn 0.6s ease;
        }
        
        .section h2 {
            color: var(--text-primary);
            margin-bottom: 28px;
            padding-bottom: 16px;
            border-bottom: 2px solid rgba(88, 101, 242, 0.3);
            font-size: 24px;
            font-weight: 700;
        }
        
        .form-group {
            margin-bottom: 24px;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 10px;
            color: var(--text-primary);
            font-weight: 600;
            font-size: 14px;
        }
        
        .form-group input,
        .form-group textarea {
            width: 100%;
            padding: 14px 18px;
            border: 2px solid var(--bg-elevated);
            border-radius: var(--radius-md);
            font-size: 15px;
            background: var(--bg-card);
            color: var(--text-primary);
            transition: all 0.3s ease;
        }
        
        .form-group input:focus,
        .form-group textarea:focus {
            outline: none;
            border-color: rgba(88, 101, 242, 0.5);
            background: var(--bg-elevated);
            box-shadow: 0 0 0 4px rgba(88, 101, 242, 0.1);
        }
        
        .form-group textarea {
            resize: vertical;
            min-height: 120px;
        }
        
        .btn {
            padding: 14px 28px;
            border: none;
            border-radius: var(--radius-md);
            cursor: pointer;
            font-size: 15px;
            font-weight: 600;
            transition: all 0.3s ease;
        }
        
        .btn:hover { transform: translateY(-2px); }
        
        .btn-primary { 
            background: var(--accent);
            color: white;
            box-shadow: 0 8px 24px var(--shadow-color, rgba(88, 101, 242, 0.4));
        }
        
        .btn-primary:hover {
            box-shadow: 0 12px 32px var(--shadow-color, rgba(88, 101, 242, 0.6));
        }
        
        .btn-approve { 
            background: linear-gradient(135deg, var(--success) 0%, #059669 100%);
            color: white;
            padding: 10px 18px;
            margin-right: 8px;
            box-shadow: 0 4px 12px rgba(16, 185, 129, 0.4);
        }
        
        .btn-deny { 
            background: linear-gradient(135deg, var(--danger) 0%, #dc2626 100%);
            color: white;
            padding: 10px 18px;
            box-shadow: 0 4px 12px rgba(239, 68, 68, 0.4);
        }
        
        table {
            width: 100%;
            border-collapse: separate;
            border-spacing: 0 8px;
        }
        
        th {
            background: var(--bg-elevated);
            padding: 16px;
            text-align: left;
            font-weight: 600;
            color: var(--text-primary);
            font-size: 13px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        th:first-child {
            border-radius: var(--radius-md) 0 0 12px;
        }
        
        th:last-child {
            border-radius: 0 12px 12px 0;
        }
        
        td {
            padding: 18px 16px;
            background: var(--bg-elevated);
            border-top: 1px solid var(--bg-card);
            border-bottom: 1px solid var(--bg-card);
        }
        
        td:first-child {
            border-left: 1px solid var(--bg-card);
            border-radius: var(--radius-md) 0 0 12px;
        }
        
        td:last-child {
            border-right: 1px solid var(--bg-card);
            border-radius: 0 12px 12px 0;
        }
        
        tr:hover td { 
            background: var(--bg-elevated);
        }
        
        .status-badge {
            display: inline-block;
            padding: 6px 14px;
            border-radius: var(--radius-lg);
            font-size: 12px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .status-pending { 
            background: linear-gradient(135deg, rgba(251, 191, 36, 0.2) 0%, rgba(245, 158, 11, 0.2) 100%);
            color: #f0b232;
            border: 1px solid rgba(251, 191, 36, 0.3);
        }
        
        .status-approved { 
            background: linear-gradient(135deg, rgba(16, 185, 129, 0.2) 0%, rgba(5, 150, 105, 0.2) 100%);
            color: #4ade80;
            border: 1px solid rgba(16, 185, 129, 0.3);
        }
        
        .status-denied { 
            background: linear-gradient(135deg, rgba(239, 68, 68, 0.2) 0%, rgba(220, 38, 38, 0.2) 100%);
            color: #f87171;
            border: 1px solid rgba(239, 68, 68, 0.3);
        }
        
        .btn-cancel-loa {
            padding: 6px 14px;
            border: 1px solid rgba(239, 68, 68, 0.4);
            border-radius: var(--radius-sm);
            background: rgba(239, 68, 68, 0.1);
            color: #f87171;
            font-size: 12px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
        }
        
        .btn-cancel-loa:hover {
            background: rgba(239, 68, 68, 0.25);
            border-color: rgba(239, 68, 68, 0.6);
            transform: translateY(-1px);
        }
        
        .error-msg {
            background: linear-gradient(135deg, rgba(239, 68, 68, 0.2) 0%, rgba(220, 38, 38, 0.2) 100%);
            
            border: 1px solid rgba(239, 68, 68, 0.3);
            color: #f87171;
            padding: 18px 24px;
            border-radius: var(--radius-lg);
            margin-bottom: 24px;
            animation: slideIn 0.5s ease;
        }
        
        .empty-state {
            text-align: center;
            padding: 60px 20px;
            color: var(--text-muted);
            font-size: 16px;
        }
        
        @media (max-width: 768px) {
            .container { padding: 0 16px; }
            table { font-size: 13px; }
            .section { padding: 24px; }
        }
    </style>
</head>
<body>
    <?php $current_page = 'loa'; include '../includes/navbar.php'; ?>

    <div class="container">
        <?php showPageToasts(); ?>

        <?php if ($is_admin && $pending_loas->num_rows > 0): ?>
            <div class="section">
                <h2>Pending LOA Requests</h2>
                <table>
                    <thead>
                        <tr>
                            <th>User</th>
                            <th>Start Date</th>
                            <th>End Date</th>
                            <th>Reason</th>
                            <th>Submitted</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while ($loa = $pending_loas->fetch_assoc()): ?>
                            <tr>
                                <td><strong><?php echo htmlspecialchars($loa['username']); ?></strong></td>
                                <td><?php echo date('M j, Y', strtotime($loa['start_date'])); ?></td>
                                <td><?php echo date('M j, Y', strtotime($loa['end_date'])); ?></td>
                                <td><?php echo htmlspecialchars($loa['reason']); ?></td>
                                <td><?php echo date('M j, Y', strtotime($loa['created_at'])); ?></td>
                                <td>
                                    <form method="POST" style="display: inline;">
                    <?php echo csrfField(); ?>
                                        <input type="hidden" name="loa_id" value="<?php echo $loa['id']; ?>">
                                        <button type="submit" name="approve_loa" class="btn btn-approve">✓ Approve</button>
                                        <button type="submit" name="deny_loa" class="btn btn-deny" onclick="return confirm('Deny this LOA request?')">✗ Deny</button>
                                    </form>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>

        <div class="section">
            <h2>Submit LOA Request</h2>
            <form method="POST">
                    <?php echo csrfField(); ?>
                <div class="form-group">
                    <label>Start Date *</label>
                    <input type="date" name="start_date" required>
                </div>
                <div class="form-group">
                    <label>End Date *</label>
                    <input type="date" name="end_date" required>
                </div>
                <div class="form-group">
                    <label>Reason *</label>
                    <textarea name="reason" required placeholder="Please provide a reason for your leave..."></textarea>
                </div>
                <button type="submit" name="submit_loa" class="btn btn-primary">Submit LOA Request</button>
            </form>
        </div>

        <div class="section">
            <h2>My LOA History</h2>
            <?php if ($user_loas->num_rows > 0): ?>
                <table>
                    <thead>
                        <tr>
                            <th>Start Date</th>
                            <th>End Date</th>
                            <th>Reason</th>
                            <th>Status</th>
                            <th>Submitted</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while ($loa = $user_loas->fetch_assoc()): ?>
                            <?php 
                            $can_cancel = in_array($loa['status'], ['pending', 'approved']) && $loa['end_date'] >= date('Y-m-d');
                            ?>
                            <tr>
                                <td><?php echo date('M j, Y', strtotime($loa['start_date'])); ?></td>
                                <td><?php echo date('M j, Y', strtotime($loa['end_date'])); ?></td>
                                <td><?php echo htmlspecialchars($loa['reason']); ?></td>
                                <td>
                                    <span class="status-badge status-<?php echo $loa['status']; ?>">
                                        <?php echo strtoupper($loa['status']); ?>
                                    </span>
                                </td>
                                <td><?php echo date('M j, Y', strtotime($loa['created_at'])); ?></td>
                                <td>
                                    <?php if ($can_cancel): ?>
                                    <form method="POST" style="display: inline;" onsubmit="return confirm('Are you sure you want to cancel this LOA? This cannot be undone.')">
                    <?php echo csrfField(); ?>
                                        <input type="hidden" name="loa_id" value="<?php echo $loa['id']; ?>">
                                        <button type="submit" name="cancel_loa" class="btn-cancel-loa">✕ Cancel</button>
                                    </form>
                                    <?php else: ?>
                                    <span style="color: var(--bg-elevated); font-size: 12px;">—</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <div class="empty-state">No LOA requests yet.</div>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>
<?php $conn->close(); ?>