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
$message = '';

// Default policy settings
$defaults = [
    'password_min_length' => '8',
    'password_require_uppercase' => '1',
    'password_require_lowercase' => '1',
    'password_require_number' => '1',
    'password_require_special' => '0',
    'password_expiry_days' => '0',
    'password_history_count' => '0'
];

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && verifyCSRFToken()) {
    $settings = [
        'password_min_length' => max(6, min(32, intval($_POST['password_min_length'] ?? 8))),
        'password_require_uppercase' => isset($_POST['password_require_uppercase']) ? '1' : '0',
        'password_require_lowercase' => isset($_POST['password_require_lowercase']) ? '1' : '0',
        'password_require_number' => isset($_POST['password_require_number']) ? '1' : '0',
        'password_require_special' => isset($_POST['password_require_special']) ? '1' : '0',
        'password_expiry_days' => max(0, intval($_POST['password_expiry_days'] ?? 0)),
        'password_history_count' => max(0, min(24, intval($_POST['password_history_count'] ?? 0)))
    ];
    
    foreach ($settings as $key => $value) {
        $stmt = $conn->prepare("INSERT INTO system_settings (setting_key, setting_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE setting_value = ?");
        $val = (string)$value;
        $stmt->bind_param("sss", $key, $val, $val);
        $stmt->execute();
        $stmt->close();
    }
    
    $message = 'Password policies updated!';
    logAudit('password_policy_update', 'system', 0, 'Updated password policies');
}

// Get current settings
$current = $defaults;
$result = $conn->query("SELECT setting_key, setting_value FROM system_settings WHERE setting_key LIKE 'password_%'");
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $current[$row['setting_key']] = $row['setting_value'];
    }
}

$conn->close();

$theme = getThemeColors();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Password Policies - <?php echo htmlspecialchars(getCommunityName()); ?></title>
    <link rel="icon" type="image/x-icon" href="/favicon.ico">
    <?php include __DIR__ . '/../includes/styles.php'; ?>
    <style>
        .policy-section { background: var(--bg-card); border: 1px solid var(--border); border-radius: var(--radius-md); padding: 24px; margin-bottom: 20px; }
        .policy-section h3 { color: var(--text-primary); margin: 0 0 16px; font-size: 16px; }
        .policy-row { display: flex; justify-content: space-between; align-items: center; padding: 12px 0; border-bottom: 1px solid var(--border); }
        .policy-row:last-child { border-bottom: none; }
        .policy-label { color: var(--text-secondary); }
        .policy-desc { font-size: 12px; color: var(--text-muted); margin-top: 4px; }
        .toggle-switch { position: relative; width: 50px; height: 26px; }
        .toggle-switch input { opacity: 0; width: 0; height: 0; }
        .toggle-slider { position: absolute; cursor: pointer; top: 0; left: 0; right: 0; bottom: 0; background: var(--bg-elevated); border-radius: 26px; transition: 0.3s; }
        .toggle-slider:before { position: absolute; content: ""; height: 20px; width: 20px; left: 3px; bottom: 3px; background: #fff; border-radius: 50%; transition: 0.3s; }
        .toggle-switch input:checked + .toggle-slider { background: var(--accent); }
        .toggle-switch input:checked + .toggle-slider:before { transform: translateX(24px); }
        .preview-box { background: rgba(0,0,0,0.2); border-radius: var(--radius-sm); padding: 16px; margin-top: 20px; }
        .preview-box h4 { color: var(--text-primary); margin: 0 0 12px; font-size: 14px; }
        .preview-list { list-style: none; padding: 0; margin: 0; }
        .preview-list li { color: var(--text-muted); font-size: 13px; padding: 4px 0; }
        .preview-list li:before { content: "✓"; color: var(--success); margin-right: 8px; }
    </style>
</head>
<body>
<?php include __DIR__ . '/../includes/navbar.php'; ?>

<div class="container">
    <div class="page-header">
        <h1>🔑 Password Policies</h1>
    </div>
    
    <?php if ($message) showToast($message, 'success'); ?>
    
    <form method="POST">
        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(generateCSRFToken()); ?>">
        
        <div class="policy-section">
            <h3>Password Requirements</h3>
            
            <div class="policy-row">
                <div>
                    <div class="policy-label">Minimum Length</div>
                    <div class="policy-desc">Minimum number of characters required</div>
                </div>
                <input type="number" name="password_min_length" class="form-control" style="width:80px;" value="<?php echo intval($current['password_min_length']); ?>" min="6" max="32">
            </div>
            
            <div class="policy-row">
                <div>
                    <div class="policy-label">Require Uppercase Letter</div>
                    <div class="policy-desc">Password must contain at least one uppercase letter (A-Z)</div>
                </div>
                <label class="toggle-switch">
                    <input type="checkbox" name="password_require_uppercase" <?php echo $current['password_require_uppercase'] === '1' ? 'checked' : ''; ?>>
                    <span class="toggle-slider"></span>
                </label>
            </div>
            
            <div class="policy-row">
                <div>
                    <div class="policy-label">Require Lowercase Letter</div>
                    <div class="policy-desc">Password must contain at least one lowercase letter (a-z)</div>
                </div>
                <label class="toggle-switch">
                    <input type="checkbox" name="password_require_lowercase" <?php echo $current['password_require_lowercase'] === '1' ? 'checked' : ''; ?>>
                    <span class="toggle-slider"></span>
                </label>
            </div>
            
            <div class="policy-row">
                <div>
                    <div class="policy-label">Require Number</div>
                    <div class="policy-desc">Password must contain at least one number (0-9)</div>
                </div>
                <label class="toggle-switch">
                    <input type="checkbox" name="password_require_number" <?php echo $current['password_require_number'] === '1' ? 'checked' : ''; ?>>
                    <span class="toggle-slider"></span>
                </label>
            </div>
            
            <div class="policy-row">
                <div>
                    <div class="policy-label">Require Special Character</div>
                    <div class="policy-desc">Password must contain at least one special character (!@#$%^&*)</div>
                </div>
                <label class="toggle-switch">
                    <input type="checkbox" name="password_require_special" <?php echo $current['password_require_special'] === '1' ? 'checked' : ''; ?>>
                    <span class="toggle-slider"></span>
                </label>
            </div>
        </div>
        
        <div class="policy-section">
            <h3>Password Expiration</h3>
            
            <div class="policy-row">
                <div>
                    <div class="policy-label">Password Expiry (Days)</div>
                    <div class="policy-desc">Force password change after this many days (0 = never expires)</div>
                </div>
                <input type="number" name="password_expiry_days" class="form-control" style="width:80px;" value="<?php echo intval($current['password_expiry_days']); ?>" min="0" max="365">
            </div>
            
            <div class="policy-row">
                <div>
                    <div class="policy-label">Password History</div>
                    <div class="policy-desc">Prevent reuse of last N passwords (0 = no restriction)</div>
                </div>
                <input type="number" name="password_history_count" class="form-control" style="width:80px;" value="<?php echo intval($current['password_history_count']); ?>" min="0" max="24">
            </div>
        </div>
        
        <div class="preview-box">
            <h4>Current Password Requirements</h4>
            <ul class="preview-list" id="previewList">
                <li>At least <?php echo intval($current['password_min_length']); ?> characters</li>
                <?php if ($current['password_require_uppercase'] === '1'): ?><li>At least one uppercase letter</li><?php endif; ?>
                <?php if ($current['password_require_lowercase'] === '1'): ?><li>At least one lowercase letter</li><?php endif; ?>
                <?php if ($current['password_require_number'] === '1'): ?><li>At least one number</li><?php endif; ?>
                <?php if ($current['password_require_special'] === '1'): ?><li>At least one special character</li><?php endif; ?>
            </ul>
        </div>
        
        <div style="display:flex;align-items:center;gap:12px;margin-top:20px;">
            <button type="submit" class="btn btn-primary">Save Policies</button>
            <span id="saveStatus" style="color:var(--success);font-size:13px;display:none;">✓ Saved</span>
        </div>
    </form>
</div>

<script>
// Auto-save on any input change
document.querySelectorAll('input[type="checkbox"], input[type="number"]').forEach(function(input) {
    input.addEventListener('change', function() {
        // Show saving indicator
        var status = document.getElementById('saveStatus');
        status.textContent = 'Saving...';
        status.style.color = '#f0b232';
        status.style.display = 'inline';
        
        // Submit form via fetch
        var form = document.querySelector('form');
        var formData = new FormData(form);
        
        fetch(window.location.href, {
            method: 'POST',
            body: formData
        })
        .then(function(response) {
            if (response.ok) {
                status.textContent = '✓ Saved';
                status.style.color = 'var(--success)';
                updatePreview();
            } else {
                status.textContent = '✗ Error';
                status.style.color = 'var(--danger)';
            }
        })
        .catch(function() {
            status.textContent = '✗ Error';
            status.style.color = 'var(--danger)';
        });
    });
});

// Update preview list based on current form values
function updatePreview() {
    var minLength = document.querySelector('input[name="password_min_length"]').value;
    var uppercase = document.querySelector('input[name="password_require_uppercase"]').checked;
    var lowercase = document.querySelector('input[name="password_require_lowercase"]').checked;
    var number = document.querySelector('input[name="password_require_number"]').checked;
    var special = document.querySelector('input[name="password_require_special"]').checked;
    
    var html = '<li>At least ' + minLength + ' characters</li>';
    if (uppercase) html += '<li>At least one uppercase letter</li>';
    if (lowercase) html += '<li>At least one lowercase letter</li>';
    if (number) html += '<li>At least one number</li>';
    if (special) html += '<li>At least one special character</li>';
    
    document.getElementById('previewList').innerHTML = html;
}
</script>

</body>
</html>
