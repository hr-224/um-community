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

$theme = getThemeColors();
$base_url = rtrim(getSiteUrl(), '/') . '/api/v1';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>API Documentation - <?php echo htmlspecialchars(getCommunityName()); ?></title>
    <link rel="icon" type="image/x-icon" href="/favicon.ico">
    <?php include __DIR__ . '/../includes/styles.php'; ?>
    <style>
        .doc-section { background: var(--bg-card); border: 1px solid var(--border); border-radius: var(--radius-md); padding: 24px; margin-bottom: 24px; }
        .endpoint { background: rgba(0,0,0,0.2); border-radius: var(--radius-sm); padding: 16px; margin: 12px 0; }
        .endpoint-header { display: flex; align-items: center; gap: 12px; margin-bottom: 8px; }
        .method { padding: 4px 10px; border-radius: 4px; font-size: 11px; font-weight: 700; font-family: monospace; }
        .method.get { background: rgba(34, 197, 94, 0.2); color: #22c55e; }
        .method.post { background: rgba(59, 130, 246, 0.2); color: #3b82f6; }
        .method.put { background: rgba(251, 191, 36, 0.2); color: #fbbf24; }
        .method.delete { background: rgba(239, 68, 68, 0.2); color: var(--danger); }
        .endpoint-path { font-family: monospace; font-size: 14px; color: var(--text-primary); }
        .endpoint-desc { font-size: 13px; color: var(--text-secondary); }
        .param-table { width: 100%; font-size: 13px; margin-top: 12px; }
        .param-table th { text-align: left; padding: 8px; background: var(--bg-primary); }
        .param-table td { padding: 8px; border-bottom: 1px solid var(--border); }
        .code-block { background: #1a1a2e; border-radius: var(--radius-sm); padding: 16px; font-family: monospace; font-size: 13px; overflow-x: auto; margin-top: 12px; }
        .perm-badge { padding: 2px 8px; background: rgba(59, 130, 246, 0.2); color: var(--accent); border-radius: 4px; font-size: 11px; }
    </style>
</head>
<body>
<?php include __DIR__ . '/../includes/navbar.php'; ?>

<div class="container">
    <div class="page-header">
        <h1>📚 API Documentation</h1>
        <a href="api_keys.php" class="btn btn-primary">Manage API Keys</a>
    </div>
    
    <div class="doc-section">
        <h3>Authentication</h3>
        <p style="color: var(--text-secondary); margin: 12px 0;">All API requests require authentication via headers:</p>
        <div class="code-block">
X-API-Key: your_api_key_here
X-API-Secret: your_secret_here
        </div>
        <p style="color: var(--text-muted); font-size: 13px; margin-top: 12px;">
            Create API keys in the <a href="api_keys.php" style="color: var(--accent);">API Keys</a> section.
        </p>
    </div>
    
    <div class="doc-section">
        <h3>Base URL</h3>
        <div class="code-block"><?php echo htmlspecialchars($base_url); ?></div>
    </div>
    
    <div class="doc-section">
        <h3>Rate Limiting</h3>
        <p style="color: var(--text-secondary);">API requests are rate-limited per key. Default: 100 requests/hour. When exceeded, you'll receive a 429 status code.</p>
    </div>
    
    <div class="doc-section">
        <h3>Endpoints</h3>
        
        <h4 style="margin-top: 20px; color: var(--text-secondary);">Health Check</h4>
        <div class="endpoint">
            <div class="endpoint-header">
                <span class="method get">GET</span>
                <span class="endpoint-path">/status</span>
            </div>
            <div class="endpoint-desc">Check API status. No authentication required.</div>
        </div>
        
        <h4 style="margin-top: 20px; color: var(--text-secondary);">Users</h4>
        <div class="endpoint">
            <div class="endpoint-header">
                <span class="method get">GET</span>
                <span class="endpoint-path">/users</span>
                <span class="perm-badge">users.list</span>
            </div>
            <div class="endpoint-desc">List all approved users.</div>
        </div>
        <div class="endpoint">
            <div class="endpoint-header">
                <span class="method get">GET</span>
                <span class="endpoint-path">/users/{id}</span>
                <span class="perm-badge">users.read</span>
            </div>
            <div class="endpoint-desc">Get specific user details.</div>
        </div>
        
        <h4 style="margin-top: 20px; color: var(--text-secondary);">Departments</h4>
        <div class="endpoint">
            <div class="endpoint-header">
                <span class="method get">GET</span>
                <span class="endpoint-path">/departments</span>
                <span class="perm-badge">departments.read</span>
            </div>
            <div class="endpoint-desc">List all departments.</div>
        </div>
        <div class="endpoint">
            <div class="endpoint-header">
                <span class="method get">GET</span>
                <span class="endpoint-path">/departments/{id}</span>
                <span class="perm-badge">departments.read</span>
            </div>
            <div class="endpoint-desc">Get specific department.</div>
        </div>
        
        <h4 style="margin-top: 20px; color: var(--text-secondary);">Roster</h4>
        <div class="endpoint">
            <div class="endpoint-header">
                <span class="method get">GET</span>
                <span class="endpoint-path">/roster</span>
                <span class="perm-badge">roster.read</span>
            </div>
            <div class="endpoint-desc">Get full roster. Optional: ?department_id=X to filter.</div>
        </div>
        
        <h4 style="margin-top: 20px; color: var(--text-secondary);">Announcements</h4>
        <div class="endpoint">
            <div class="endpoint-header">
                <span class="method get">GET</span>
                <span class="endpoint-path">/announcements</span>
                <span class="perm-badge">announcements.read</span>
            </div>
            <div class="endpoint-desc">Get active announcements. Optional: ?limit=X (max 50).</div>
        </div>
        
        <h4 style="margin-top: 20px; color: var(--text-secondary);">Events</h4>
        <div class="endpoint">
            <div class="endpoint-header">
                <span class="method get">GET</span>
                <span class="endpoint-path">/events</span>
                <span class="perm-badge">events.read</span>
            </div>
            <div class="endpoint-desc">Get events. Optional: ?upcoming=1 for future events only.</div>
        </div>
        
        <h4 style="margin-top: 20px; color: var(--text-secondary);">Certifications</h4>
        <div class="endpoint">
            <div class="endpoint-header">
                <span class="method get">GET</span>
                <span class="endpoint-path">/certifications</span>
                <span class="perm-badge">certifications.read</span>
            </div>
            <div class="endpoint-desc">List certification types. Optional: ?user_id=X for user's certs.</div>
        </div>
        <div class="endpoint">
            <div class="endpoint-header">
                <span class="method post">POST</span>
                <span class="endpoint-path">/certifications</span>
                <span class="perm-badge">certifications.write</span>
            </div>
            <div class="endpoint-desc">Create/update user certification.</div>
            <table class="param-table">
                <tr><th>Field</th><th>Type</th><th>Description</th></tr>
                <tr><td>user_id</td><td>int</td><td>Required. User ID</td></tr>
                <tr><td>certification_type_id</td><td>int</td><td>Required. Cert type ID</td></tr>
                <tr><td>status</td><td>string</td><td>Optional. pending, in_progress, completed, expired, revoked</td></tr>
            </table>
        </div>
        
        <h4 style="margin-top: 20px; color: var(--text-secondary);">Training Records</h4>
        <div class="endpoint">
            <div class="endpoint-header">
                <span class="method get">GET</span>
                <span class="endpoint-path">/training</span>
                <span class="perm-badge">training.read</span>
            </div>
            <div class="endpoint-desc">Get training records. Optional: ?user_id=X to filter.</div>
        </div>
        <div class="endpoint">
            <div class="endpoint-header">
                <span class="method post">POST</span>
                <span class="endpoint-path">/training</span>
                <span class="perm-badge">training.write</span>
            </div>
            <div class="endpoint-desc">Create training record.</div>
            <table class="param-table">
                <tr><th>Field</th><th>Type</th><th>Description</th></tr>
                <tr><td>trainee_id</td><td>int</td><td>Required. Trainee user ID</td></tr>
                <tr><td>trainer_id</td><td>int</td><td>Required. Trainer user ID</td></tr>
                <tr><td>hours</td><td>float</td><td>Required. Training hours</td></tr>
                <tr><td>session_date</td><td>string</td><td>Optional. YYYY-MM-DD format</td></tr>
                <tr><td>topic</td><td>string</td><td>Optional. Training topic</td></tr>
                <tr><td>notes</td><td>string</td><td>Optional. Additional notes</td></tr>
            </table>
        </div>
    </div>
    
    <div class="doc-section">
        <h3>Response Format</h3>
        <p style="color: var(--text-secondary); margin-bottom: 12px;">All responses are JSON:</p>
        <div class="code-block">
// Success
{
    "success": true,
    "data": { ... }
}

// Error
{
    "success": false,
    "error": "Error message"
}
        </div>
    </div>
    
    <div class="doc-section">
        <h3>Example: cURL</h3>
        <div class="code-block">
curl -X GET "<?php echo $base_url; ?>/users" \
    -H "X-API-Key: umcm_your_key_here" \
    -H "X-API-Secret: your_secret_here"
        </div>
    </div>
    
    <div class="doc-section">
        <h3>Example: JavaScript</h3>
        <div class="code-block">
fetch('<?php echo $base_url; ?>/users', {
    headers: {
        'X-API-Key': 'umcm_your_key_here',
        'X-API-Secret': 'your_secret_here'
    }
})
.then(res => res.json())
.then(data => console.log(data));
        </div>
    </div>
</div>
</body>
</html>
