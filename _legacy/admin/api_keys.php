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
$error = '';
$new_key = null;
$new_secret = null;

// Ensure api_keys tables exist
$tableExists = $conn->query("SHOW TABLES LIKE 'api_keys'");
if (!$tableExists || $tableExists->num_rows === 0) {
    $conn->query("CREATE TABLE IF NOT EXISTS api_keys (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(100) NOT NULL,
        api_key VARCHAR(64) NOT NULL UNIQUE,
        secret_hash VARCHAR(255) NOT NULL,
        permissions JSON,
        rate_limit INT DEFAULT 100,
        is_active BOOLEAN DEFAULT TRUE,
        created_by INT,
        expires_at DATETIME DEFAULT NULL,
        last_used_at DATETIME DEFAULT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_api_key (api_key)
    )");
    $conn->query("CREATE TABLE IF NOT EXISTS api_request_log (
        id INT AUTO_INCREMENT PRIMARY KEY,
        api_key_id INT NOT NULL,
        endpoint VARCHAR(255) NOT NULL,
        method VARCHAR(10) NOT NULL,
        ip_address VARCHAR(45),
        response_code INT,
        request_time TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_api_log_key (api_key_id),
        INDEX idx_api_log_time (request_time)
    )");
}

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && verifyCSRFToken()) {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'create') {
        $name = trim($_POST['name'] ?? '');
        $rate_limit = intval($_POST['rate_limit'] ?? 100);
        $permissions = $_POST['permissions'] ?? [];
        $expires_days = intval($_POST['expires_days'] ?? 0);
        
        if ($name) {
            // Generate API key and secret
            $api_key = 'umcm_' . bin2hex(random_bytes(16));
            $secret = bin2hex(random_bytes(32));
            $secret_hash = password_hash($secret, PASSWORD_DEFAULT);
            $permissions_json = json_encode($permissions);
            $expires_at = $expires_days > 0 ? date('Y-m-d H:i:s', strtotime("+$expires_days days")) : null;
            
            $stmt = $conn->prepare("INSERT INTO api_keys (name, api_key, secret_hash, permissions, rate_limit, created_by, expires_at) VALUES (?, ?, ?, ?, ?, ?, ?)");
            $stmt->bind_param("ssssiis", $name, $api_key, $secret_hash, $permissions_json, $rate_limit, $user_id, $expires_at);
            
            if ($stmt->execute()) {
                $new_key = $api_key;
                $new_secret = $secret;
                $message = "API key created! Save the secret now - it won't be shown again.";
                logAudit('api_key_create', 'api_keys', $stmt->insert_id, "Created API key: $name");
            }
            $stmt->close();
        }
    } elseif ($action === 'toggle') {
        $key_id = intval($_POST['key_id'] ?? 0);
        $stmt = $conn->prepare("UPDATE api_keys SET is_active = NOT is_active WHERE id = ?");
        $stmt->bind_param("i", $key_id);
        $stmt->execute();
        $stmt->close();
        $message = "API key status updated.";
    } elseif ($action === 'delete') {
        $key_id = intval($_POST['key_id'] ?? 0);
        $stmt = $conn->prepare("DELETE FROM api_keys WHERE id = ?");
        $stmt->bind_param("i", $key_id);
        $stmt->execute();
        $stmt->close();
        $message = "API key deleted.";
    }
}

// Get all API keys
$result = $conn->query("
    SELECT ak.*, u.username as created_by_name,
           (SELECT COUNT(*) FROM api_request_log WHERE api_key_id = ak.id) as request_count
    FROM api_keys ak
    LEFT JOIN users u ON ak.created_by = u.id
    ORDER BY ak.created_at DESC
");
$keys = $result ? $result->fetch_all(MYSQLI_ASSOC) : [];

$conn->close();
$theme = getThemeColors();

// Available permissions
$available_permissions = [
    'users.read' => 'Read user info',
    'users.list' => 'List all users',
    'roster.read' => 'Read roster data',
    'departments.read' => 'Read departments',
    'announcements.read' => 'Read announcements',
    'events.read' => 'Read events',
    'certifications.read' => 'Read certifications',
    'certifications.write' => 'Update certifications',
    'training.read' => 'Read training records',
    'training.write' => 'Create training records',
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>API Keys - <?php echo htmlspecialchars(getCommunityName()); ?></title>
    <link rel="icon" type="image/x-icon" href="/favicon.ico">
    <?php include __DIR__ . '/../includes/styles.php'; ?>
    <style>
        .key-card { background: var(--bg-card); border: 1px solid var(--border); border-radius: var(--radius-md); padding: 20px; margin-bottom: 16px; }
        .key-card.inactive { opacity: 0.6; }
        .key-header { display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 12px; }
        .key-name { font-size: 16px; font-weight: 600; color: var(--text-primary); }
        .key-value { font-family: monospace; font-size: 13px; background: rgba(0,0,0,0.3); padding: 8px 12px; border-radius: 6px; margin: 8px 0; word-break: break-all; }
        .key-meta { font-size: 12px; color: var(--text-muted); }
        .key-permissions { display: flex; flex-wrap: wrap; gap: 6px; margin: 12px 0; }
        .key-perm { padding: 4px 8px; background: rgba(59, 130, 246, 0.2); color: var(--accent); border-radius: 4px; font-size: 11px; }
        .key-actions { display: flex; gap: 8px; margin-top: 12px; }
        .new-key-alert { background: rgba(34, 197, 94, 0.15); border: 1px solid rgba(34, 197, 94, 0.3); border-radius: var(--radius-md); padding: 20px; margin-bottom: 24px; }
        .new-key-alert h4 { color: #22c55e; margin-bottom: 12px; }
        .secret-warning { color: #fbbf24; font-size: 13px; margin-top: 12px; }
        .permissions-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(200px, 1fr)); gap: 8px; }
        .perm-checkbox { display: flex; align-items: center; gap: 8px; padding: 8px; background: var(--bg-primary); border-radius: 6px; cursor: pointer; }
        .perm-checkbox:hover { background: var(--bg-elevated); }
        .badge { padding: 4px 10px; border-radius: 6px; font-size: 11px; font-weight: 600; }
        .badge-active { background: rgba(34, 197, 94, 0.2); color: #22c55e; }
        .badge-inactive { background: rgba(239, 68, 68, 0.2); color: var(--danger); }
        .form-group { margin-bottom: 16px; }
        .form-group label { display: block; margin-bottom: 8px; font-weight: 600; font-size: 14px; color: var(--text-primary); }
        .form-group input, .form-group select { width: 100%; padding: 12px; border: 1px solid var(--bg-elevated); border-radius: var(--radius-sm); background: var(--bg-primary); color: var(--text-primary); font-size: 14px; }
        .form-group input:focus { outline: none; border-color: var(--accent); }
    </style>
</head>
<body>
<?php include __DIR__ . '/../includes/navbar.php'; ?>

<div class="container">
    <div class="page-header">
        <h1>🔑 API Keys</h1>
        <button class="btn btn-primary" onclick="document.getElementById('createModal').classList.add('active')">+ Create API Key</button>
    </div>
    
    <?php if ($message) showToast($message, 'success'); ?>
    
    <?php if ($new_key && $new_secret): ?>
    <div class="new-key-alert">
        <h4>🔐 New API Key Created</h4>
        <p>API Key:</p>
        <div class="key-value"><?php echo htmlspecialchars($new_key); ?></div>
        <p>Secret:</p>
        <div class="key-value"><?php echo htmlspecialchars($new_secret); ?></div>
        <p class="secret-warning">⚠️ Save the secret now! It will not be shown again.</p>
    </div>
    <?php endif; ?>
    
    <div style="background: rgba(59, 130, 246, 0.1); border-radius: var(--radius-md); padding: 20px; margin-bottom: 24px;">
        <h4 style="margin-bottom: 12px;">📖 API Documentation</h4>
        <p style="font-size: 14px; color: var(--text-secondary); margin-bottom: 12px;">
            Base URL: <code style="background: rgba(0,0,0,0.3); padding: 2px 6px; border-radius: 4px;"><?php echo rtrim(getSiteUrl(), '/'); ?>/api/v1/</code>
        </p>
        <p style="font-size: 14px; color: var(--text-secondary);">
            Authentication: Include headers <code>X-API-Key</code> and <code>X-API-Secret</code> with each request.
        </p>
        <a href="api_docs.php" class="btn btn-sm" style="margin-top: 12px;">View Full Documentation</a>
    </div>
    
    <h3 style="margin-bottom: 16px;">Your API Keys</h3>
    
    <?php if (empty($keys)): ?>
        <div class="empty-state">
            <p>No API keys created yet.</p>
        </div>
    <?php else: ?>
        <?php foreach ($keys as $key): ?>
        <div class="key-card <?php echo $key['is_active'] ? '' : 'inactive'; ?>">
            <div class="key-header">
                <div>
                    <div class="key-name"><?php echo htmlspecialchars($key['name']); ?></div>
                    <div class="key-value"><?php echo htmlspecialchars($key['api_key']); ?></div>
                </div>
                <span class="badge <?php echo $key['is_active'] ? 'badge-active' : 'badge-inactive'; ?>">
                    <?php echo $key['is_active'] ? 'Active' : 'Inactive'; ?>
                </span>
            </div>
            <div class="key-permissions">
                <?php 
                $perms = json_decode($key['permissions'], true) ?: [];
                foreach ($perms as $p): ?>
                    <span class="key-perm"><?php echo htmlspecialchars($p); ?></span>
                <?php endforeach; ?>
                <?php if (empty($perms)): ?>
                    <span style="font-size: 12px; color: var(--text-muted);">No permissions set</span>
                <?php endif; ?>
            </div>
            <div class="key-meta">
                Created by <?php echo htmlspecialchars($key['created_by_name']); ?> on <?php echo date('M j, Y', strtotime($key['created_at'])); ?>
                • Rate limit: <?php echo $key['rate_limit']; ?>/hour
                • <?php echo $key['request_count']; ?> requests
                <?php if ($key['expires_at']): ?>
                    • Expires: <?php echo date('M j, Y', strtotime($key['expires_at'])); ?>
                <?php endif; ?>
                <?php if ($key['last_used_at']): ?>
                    • Last used: <?php echo date('M j g:i A', strtotime($key['last_used_at'])); ?>
                <?php endif; ?>
            </div>
            <div class="key-actions">
                <form method="POST" style="display: inline;">
                    <?php echo csrfField(); ?>
                    <input type="hidden" name="action" value="toggle">
                    <input type="hidden" name="key_id" value="<?php echo $key['id']; ?>">
                    <button type="submit" class="btn btn-sm"><?php echo $key['is_active'] ? 'Disable' : 'Enable'; ?></button>
                </form>
                <form method="POST" style="display: inline;" onsubmit="return confirm('Delete this API key?');">
                    <?php echo csrfField(); ?>
                    <input type="hidden" name="action" value="delete">
                    <input type="hidden" name="key_id" value="<?php echo $key['id']; ?>">
                    <button type="submit" class="btn btn-sm btn-danger">Delete</button>
                </form>
            </div>
        </div>
        <?php endforeach; ?>
    <?php endif; ?>
</div>

<!-- Create Modal -->
<div class="modal" id="createModal">
    <div class="modal-content" style="max-width: 600px;">
        <h3 style="margin-bottom: 20px;">Create API Key</h3>
        <form method="POST">
            <?php echo csrfField(); ?>
            <input type="hidden" name="action" value="create">
            
            <div class="form-group">
                <label>Key Name *</label>
                <input type="text" name="name" required placeholder="e.g., Discord Bot, FiveM Server">
            </div>
            
            <div class="form-group">
                <label>Permissions</label>
                <div class="permissions-grid">
                    <?php foreach ($available_permissions as $perm => $desc): ?>
                    <label class="perm-checkbox">
                        <input type="checkbox" name="permissions[]" value="<?php echo $perm; ?>">
                        <?php echo htmlspecialchars($desc); ?>
                    </label>
                    <?php endforeach; ?>
                </div>
            </div>
            
            <div class="form-row" style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px;">
                <div class="form-group">
                    <label>Rate Limit (requests/hour)</label>
                    <input type="number" name="rate_limit" value="100" min="1">
                </div>
                <div class="form-group">
                    <label>Expires In (days, 0 = never)</label>
                    <input type="number" name="expires_days" value="0" min="0">
                </div>
            </div>
            
            <div class="modal-footer">
                <button type="button" class="btn" onclick="document.getElementById('createModal').classList.remove('active')">Cancel</button>
                <button type="submit" class="btn btn-primary">Create Key</button>
            </div>
        </form>
    </div>
</div>

<script>
document.getElementById('createModal').addEventListener('click', function(e) {
    if (e.target === this) this.classList.remove('active');
});
</script>
</body>
</html>
