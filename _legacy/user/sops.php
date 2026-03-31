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
if (!defined('UM_EMAIL_LOADED')) { require_once __DIR__ . '/../includes/email.php'; }
requireLogin();

$conn = getDBConnection();
$user_id = $_SESSION['user_id'];
$message = '';

// Handle SOP acknowledgment
if ($_SERVER['REQUEST_METHOD'] === 'POST' && verifyCSRFToken() && isset($_POST['acknowledge_sop'])) {
    $sop_id = intval($_POST['sop_id']);
    
    // Verify user belongs to the SOP's department
    $stmt = $conn->prepare("SELECT s.id, s.title, s.department_id FROM department_sops s 
        JOIN roster r ON r.department_id = s.department_id 
        WHERE s.id = ? AND r.user_id = ? AND s.is_active = TRUE");
    $stmt->bind_param("ii", $sop_id, $user_id);
    $stmt->execute();
    $sop = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    
    if ($sop) {
        $stmt = $conn->prepare("INSERT IGNORE INTO sop_acknowledgments (sop_id, user_id) VALUES (?, ?)");
        $stmt->bind_param("ii", $sop_id, $user_id);
        $stmt->execute();
        $stmt->close();
        logAudit('acknowledge_sop', 'sop', $sop_id, 'Acknowledged SOP: ' . $sop['title']);
        $message = 'SOP acknowledged successfully!';
    }
}

// Get user's department IDs
$my_depts = [];
$stmt = $conn->prepare("SELECT r.department_id, d.name as dept_name, d.abbreviation, d.color 
    FROM roster r JOIN departments d ON r.department_id = d.id 
    WHERE r.user_id = ? ORDER BY r.is_primary DESC, d.name");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$dept_result = $stmt->get_result();
while ($row = $dept_result->fetch_assoc()) $my_depts[] = $row;
$stmt->close();

$dept_ids = array_column($my_depts, 'department_id');

// Get SOPs for user's departments with acknowledgment status
$sops = [];
if (!empty($dept_ids)) {
    $placeholders = implode(',', array_fill(0, count($dept_ids), '?'));
    $sql = "SELECT s.*, d.name as dept_name, d.abbreviation as dept_abbr, d.color as dept_color,
                u.username as author_name,
                (SELECT COUNT(*) FROM sop_acknowledgments WHERE sop_id = s.id AND user_id = ?) as is_acknowledged
            FROM department_sops s
            JOIN departments d ON s.department_id = d.id
            JOIN users u ON s.created_by = u.id
            WHERE s.department_id IN ($placeholders) AND s.is_active = TRUE
            ORDER BY (SELECT COUNT(*) FROM sop_acknowledgments WHERE sop_id = s.id AND user_id = ?) ASC, s.updated_at DESC";
    $types = 'i' . str_repeat('i', count($dept_ids)) . 'i';
    $params = array_merge([$user_id], $dept_ids, [$user_id]);
    $stmt = $conn->prepare($sql);
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $sop_result = $stmt->get_result();
    while ($row = $sop_result->fetch_assoc()) $sops[] = $row;
    $stmt->close();
}

// Count stats
$total_sops = count($sops);
$acked_count = count(array_filter($sops, fn($s) => $s['is_acknowledged'] > 0));
$pending_count = $total_sops - $acked_count;

$current_page = 'sops';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SOPs - <?php echo COMMUNITY_NAME; ?></title>
    <link rel="icon" type="image/x-icon" href="/favicon.ico">
    <link rel="icon" type="image/x-icon" href="<?php echo FAVICON_PATH; ?>">
    <?php include '../includes/styles.php'; ?>
    <style>
        .container { max-width: 1000px; }
        
        .sop-stats {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 16px;
            margin-bottom: 24px;
        }
        
        .sop-stat {
            background: var(--bg-card);
            border: 1px solid var(--bg-elevated);
            border-radius: var(--radius-lg);
            padding: 20px;
            text-align: center;
        }
        
        .sop-stat-value {
            font-size: 28px;
            font-weight: 800;
        }
        
        .sop-stat-label {
            font-size: 12px;
            color: var(--text-muted);
            margin-top: 4px;
        }
        
        .sop-card {
            background: var(--bg-card);
            border: 1px solid var(--bg-elevated);
            border-radius: var(--radius-lg);
            margin-bottom: 16px;
            overflow: hidden;
            transition: all 0.2s;
        }
        
        .sop-card.unacked {
            border-color: rgba(251, 191, 36, 0.3);
        }
        
        .sop-header {
            padding: 20px 24px;
            cursor: pointer;
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 16px;
        }
        
        .sop-header:hover {
            background: var(--bg-elevated);
        }
        
        .sop-title {
            font-size: 16px;
            font-weight: 700;
            margin-bottom: 4px;
        }
        
        .sop-meta {
            font-size: 12px;
            color: var(--text-muted);
            display: flex;
            gap: 12px;
            flex-wrap: wrap;
        }
        
        .sop-body {
            display: none;
            padding: 0 24px 24px;
            border-top: 1px solid var(--bg-card);
        }
        
        .sop-body.open {
            display: block;
        }
        
        .sop-content {
            line-height: 1.7;
            color: var(--bg-elevated);
            white-space: pre-wrap;
            padding: 20px 0;
        }
        
        .sop-footer {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding-top: 16px;
            border-top: 1px solid var(--bg-card);
        }
        
        .badge-dept {
            padding: 4px 12px;
            border-radius: var(--radius-lg);
            font-size: 11px;
            font-weight: 700;
        }
        
        .badge-acked {
            background: rgba(110, 231, 183, 0.15);
            color: #4ade80;
            padding: 4px 12px;
            border-radius: var(--radius-lg);
            font-size: 11px;
            font-weight: 700;
        }
        
        .badge-pending {
            background: rgba(251, 191, 36, 0.15);
            color: #f0b232;
            padding: 4px 12px;
            border-radius: var(--radius-lg);
            font-size: 11px;
            font-weight: 700;
        }
        
        .expand-icon {
            font-size: 18px;
            color: var(--text-muted);
            transition: transform 0.2s;
            flex-shrink: 0;
        }
        
        .sop-card.expanded .expand-icon {
            transform: rotate(180deg);
        }
        
        .empty-state {
            text-align: center;
            padding: 80px;
            color: var(--text-muted);
        }
        
        .empty-state h3 {
            font-size: 24px;
            margin-bottom: 12px;
        }
        
        @media (max-width: 768px) {
            .sop-stats { grid-template-columns: repeat(3, 1fr); gap: 10px; }
            .sop-stat { padding: 14px; }
            .sop-stat-value { font-size: 22px; }
            .sop-header { padding: 16px; }
            .sop-body { padding: 0 16px 16px; }
        }
    </style>
</head>
<body>
    <?php include '../includes/navbar.php'; ?>

    <div class="container">
        <div class="section" style="margin-bottom: 24px;">
            <h2>📘 Standard Operating Procedures</h2>
            <p style="color: var(--text-muted); margin-top: 4px;">Review and acknowledge SOPs for your departments.</p>
        </div>
        
        <?php if ($message) showToast($message, 'success'); ?>
        
        <?php if (!empty($sops)): ?>
            <div class="sop-stats">
                <div class="sop-stat">
                    <div class="sop-stat-value" style="color: #93c5fd;"><?php echo $total_sops; ?></div>
                    <div class="sop-stat-label">Total SOPs</div>
                </div>
                <div class="sop-stat">
                    <div class="sop-stat-value" style="color: #4ade80;"><?php echo $acked_count; ?></div>
                    <div class="sop-stat-label">Acknowledged</div>
                </div>
                <div class="sop-stat">
                    <div class="sop-stat-value" style="color: <?php echo $pending_count > 0 ? '#f0b232' : 'var(--bg-elevated)'; ?>;"><?php echo $pending_count; ?></div>
                    <div class="sop-stat-label">Pending</div>
                </div>
            </div>
            
            <?php foreach ($sops as $sop): ?>
                <div class="sop-card <?php echo !$sop['is_acknowledged'] ? 'unacked' : ''; ?>" id="sop-<?php echo $sop['id']; ?>">
                    <div class="sop-header" onclick="toggleSOP(<?php echo $sop['id']; ?>)">
                        <div>
                            <div class="sop-title">
                                <?php echo htmlspecialchars($sop['title']); ?>
                                <?php if (!$sop['is_acknowledged']): ?>
                                    <span class="badge-pending">NEEDS ACKNOWLEDGMENT</span>
                                <?php else: ?>
                                    <span class="badge-acked">✓ ACKNOWLEDGED</span>
                                <?php endif; ?>
                            </div>
                            <div class="sop-meta">
                                <span class="badge-dept" style="background: <?php echo htmlspecialchars($sop['dept_color']); ?>20; color: <?php echo htmlspecialchars($sop['dept_color']); ?>;"><?php echo htmlspecialchars($sop['dept_abbr']); ?></span>
                                <?php if ($sop['category']): ?><span>📁 <?php echo htmlspecialchars($sop['category']); ?></span><?php endif; ?>
                                <span>v<?php echo htmlspecialchars($sop['version']); ?></span>
                                <span>Updated <?php echo date('M j, Y', strtotime($sop['updated_at'])); ?></span>
                            </div>
                        </div>
                        <span class="expand-icon">▼</span>
                    </div>
                    <div class="sop-body" id="sop-body-<?php echo $sop['id']; ?>">
                        <div class="sop-content"><?php echo nl2br(htmlspecialchars($sop['content'])); ?></div>
                        <div class="sop-footer">
                            <span style="font-size: 12px; color: var(--text-muted);">Created by <?php echo htmlspecialchars($sop['author_name']); ?></span>
                            <?php if (!$sop['is_acknowledged']): ?>
                                <form method="POST" style="margin: 0;">
                                    <?php echo csrfField(); ?>
                                    <input type="hidden" name="sop_id" value="<?php echo $sop['id']; ?>">
                                    <button type="submit" name="acknowledge_sop" class="btn btn-primary" onclick="return confirm('By acknowledging, you confirm you have read and understood this SOP.')">✓ I Acknowledge This SOP</button>
                                </form>
                            <?php else: ?>
                                <span style="color: #4ade80; font-size: 13px; font-weight: 600;">✓ Acknowledged</span>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php elseif (empty($my_depts)): ?>
            <div class="empty-state">
                <h3>No Department Assigned</h3>
                <p>You are not currently assigned to any departments. SOPs will appear here once you are added to a department roster.</p>
            </div>
        <?php else: ?>
            <div class="empty-state">
                <h3>No SOPs Available</h3>
                <p>There are no active SOPs for your departments at this time.</p>
            </div>
        <?php endif; ?>
    </div>
    
    <script>
    function toggleSOP(id) {
        const card = document.getElementById('sop-' + id);
        const body = document.getElementById('sop-body-' + id);
        
        if (card.classList.contains('expanded')) {
            card.classList.remove('expanded');
            body.classList.remove('open');
        } else {
            card.classList.add('expanded');
            body.classList.add('open');
        }
    }
    </script>
</body>
</html>
<?php $conn->close(); ?>
