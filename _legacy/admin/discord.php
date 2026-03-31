<?php
/**
 * ============================================================
 *  Ultimate Mods – FiveM Community Manager
 * ============================================================
 *  Admin Discord OAuth Settings Page
 * ============================================================
 */
require_once '../config.php';
if (!defined('UM_FUNCTIONS_LOADED')) { require_once __DIR__ . '/../includes/functions.php'; }
if (!defined('TOAST_LOADED')) { require_once __DIR__ . '/../includes/toast.php'; define('TOAST_LOADED', true); }
require_once __DIR__ . '/../includes/discord.php';
requireLogin();
requireAdmin();

$conn = getDBConnection();
$message = '';
$error = '';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && verifyCSRFToken()) {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'save_discord_settings') {
        $settings = [
            'discord_oauth_enabled' => isset($_POST['discord_enabled']) ? '1' : '0',
            'discord_client_id' => trim($_POST['client_id'] ?? ''),
            'discord_client_secret' => trim($_POST['client_secret'] ?? ''),
            'discord_redirect_uri' => trim($_POST['redirect_uri'] ?? ''),
            'discord_allow_registration' => isset($_POST['allow_registration']) ? '1' : '0',
            'discord_allow_login' => isset($_POST['allow_login']) ? '1' : '0',
            'discord_require_discord' => isset($_POST['require_discord']) ? '1' : '0'
        ];
        
        // Validate required fields if enabling
        if ($settings['discord_oauth_enabled'] === '1') {
            if (empty($settings['discord_client_id'])) {
                $error = 'Client ID is required when Discord OAuth is enabled.';
            } elseif (empty($settings['discord_client_secret'])) {
                $error = 'Client Secret is required when Discord OAuth is enabled.';
            }
        }
        
        if (empty($error)) {
            foreach ($settings as $key => $value) {
                $stmt = $conn->prepare("INSERT INTO system_settings (setting_key, setting_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE setting_value = ?");
                $stmt->bind_param("sss", $key, $value, $value);
                $stmt->execute();
                $stmt->close();
            }
            
            logActivity($conn, $_SESSION['user_id'], 'settings_updated', 'Discord OAuth settings updated');
            $message = 'Discord OAuth settings saved successfully!';
        }
    }
}

// Get current settings
$discordSettings = [
    'enabled' => getSetting('discord_oauth_enabled', '0') === '1',
    'client_id' => getSetting('discord_client_id', ''),
    'client_secret' => getSetting('discord_client_secret', ''),
    'redirect_uri' => getSetting('discord_redirect_uri', ''),
    'allow_registration' => getSetting('discord_allow_registration', '1') === '1',
    'allow_login' => getSetting('discord_allow_login', '1') === '1',
    'require_discord' => getSetting('discord_require_discord', '0') === '1'
];

// Generate redirect URI if not set (without .php extension for clean URLs)
if (empty($discordSettings['redirect_uri'])) {
    $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? $_SERVER['SERVER_NAME'] ?? 'localhost';
    $discordSettings['redirect_uri'] = $protocol . '://' . $host . '/auth/discord_callback';
}

// Count users with Discord linked (handle missing column gracefully)
$discordLinkedCount = 0;
try {
    $result = $conn->query("SELECT COUNT(*) as cnt FROM users WHERE discord_user_id IS NOT NULL");
    if ($result) {
        $discordLinkedCount = $result->fetch_assoc()['cnt'] ?? 0;
    }
} catch (Exception $e) {
    // Column may not exist yet
}

$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Discord Settings - <?php echo htmlspecialchars(getCommunityName()); ?></title>
    <link rel="icon" type="image/x-icon" href="/favicon.ico">
    <?php include __DIR__ . '/../includes/styles.php'; ?>
    <style>
        .settings-card {
            background: var(--bg-card);
            border: 1px solid var(--border);
            border-radius: var(--radius-lg);
            padding: 24px;
            margin-bottom: 24px;
        }
        .settings-card h3 {
            font-size: 18px;
            color: var(--text-primary);
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .settings-card h3 .icon {
            font-size: 24px;
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        .form-group label {
            display: block;
            font-size: 13px;
            color: var(--text-secondary);
            margin-bottom: 8px;
            font-weight: 500;
        }
        .form-group input[type="text"],
        .form-group input[type="password"] {
            width: 100%;
            padding: 12px 14px;
            background: rgba(0,0,0,0.3);
            border: 1px solid var(--bg-hover);
            border-radius: var(--radius-sm);
            color: var(--text-primary);
            font-size: 14px;
        }
        .form-group input:focus {
            outline: none;
            border-color: var(--accent);
        }
        .form-group .help-text {
            font-size: 12px;
            color: var(--text-muted);
            margin-top: 6px;
        }
        
        .toggle-group {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 14px 0;
            border-bottom: 1px solid var(--bg-elevated);
        }
        .toggle-group:last-child {
            border-bottom: none;
        }
        .toggle-group .toggle-info h4 {
            font-size: 14px;
            color: var(--text-primary);
            margin-bottom: 4px;
        }
        .toggle-group .toggle-info p {
            font-size: 12px;
            color: var(--text-muted);
        }
        
        .toggle-switch {
            position: relative;
            width: 48px;
            height: 26px;
        }
        .toggle-switch input {
            opacity: 0;
            width: 0;
            height: 0;
        }
        .toggle-slider {
            position: absolute;
            cursor: pointer;
            inset: 0;
            background: var(--border);
            border-radius: 26px;
            transition: 0.3s;
        }
        .toggle-slider::before {
            content: '';
            position: absolute;
            width: 20px;
            height: 20px;
            left: 3px;
            bottom: 3px;
            background: white;
            border-radius: 50%;
            transition: 0.3s;
        }
        .toggle-switch input:checked + .toggle-slider {
            background: #5865F2;
        }
        .toggle-switch input:checked + .toggle-slider::before {
            transform: translateX(22px);
        }
        
        .info-box {
            background: rgba(88, 101, 242, 0.1);
            border: 1px solid rgba(88, 101, 242, 0.3);
            border-radius: var(--radius-md);
            padding: 20px;
            margin-bottom: 24px;
        }
        .info-box h4 {
            color: #5865F2;
            margin-bottom: 12px;
            font-size: 15px;
        }
        .info-box ol {
            margin: 0;
            padding-left: 20px;
        }
        .info-box li {
            font-size: 13px;
            color: var(--text-secondary);
            margin-bottom: 8px;
        }
        .info-box code {
            background: rgba(0,0,0,0.3);
            padding: 2px 6px;
            border-radius: 4px;
            font-family: 'SF Mono', Monaco, monospace;
            font-size: 12px;
            color: #5865F2;
        }
        .info-box a {
            color: #5865F2;
        }
        
        .stat-card {
            background: rgba(88, 101, 242, 0.1);
            border: 1px solid rgba(88, 101, 242, 0.2);
            border-radius: var(--radius-md);
            padding: 20px;
            text-align: center;
        }
        .stat-card .number {
            font-size: 32px;
            font-weight: 700;
            color: #5865F2;
        }
        .stat-card .label {
            font-size: 13px;
            color: var(--text-muted);
            margin-top: 4px;
        }
        
        .copy-btn {
            padding: 6px 12px;
            background: var(--bg-elevated);
            border: 1px solid var(--border);
            border-radius: 6px;
            color: var(--text-primary);
            font-size: 12px;
            cursor: pointer;
            transition: all 0.2s;
            margin-left: 8px;
        }
        .copy-btn:hover {
            background: var(--border);
        }
        
        .input-with-copy {
            display: flex;
            gap: 8px;
        }
        .input-with-copy input {
            flex: 1;
        }
        
        .discord-preview {
            background: #36393f;
            border-radius: var(--radius-md);
            padding: 20px;
            margin-top: 20px;
        }
        .discord-preview h4 {
            color: var(--text-primary);
            margin-bottom: 12px;
            font-size: 14px;
        }
        .discord-btn-preview {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 12px 24px;
            background: #5865F2;
            color: white;
            border-radius: var(--radius-sm);
            font-weight: 500;
        }
    </style>
</head>
<body>
<?php include __DIR__ . '/../includes/navbar.php'; ?>

<div class="container">
    <div class="page-header">
        <h1>
            <svg class="discord-icon" width="32" height="32" viewBox="0 0 71 55" fill="#5865F2" style="vertical-align: middle; margin-right: 12px;">
                <path d="M60.1045 4.8978C55.5792 2.8214 50.7265 1.2916 45.6527 0.41542C45.5603 0.39851 45.468 0.440769 45.4204 0.525289C44.7963 1.6353 44.105 3.0834 43.6209 4.2216C38.1637 3.4046 32.7345 3.4046 27.3892 4.2216C26.905 3.0581 26.1886 1.6353 25.5617 0.525289C25.5141 0.443589 25.4218 0.40133 25.3294 0.41542C20.2584 1.2888 15.4057 2.8186 10.8776 4.8978C10.8384 4.9147 10.8048 4.9429 10.7825 4.9795C1.57795 18.7309 -0.943561 32.1443 0.293408 45.3914C0.299005 45.4562 0.335386 45.5182 0.385761 45.5576C6.45866 50.0174 12.3413 52.7249 18.1147 54.5195C18.2071 54.5477 18.305 54.5139 18.3638 54.4378C19.7295 52.5728 20.9469 50.6063 21.9907 48.5383C22.0523 48.4172 21.9935 48.2735 21.8676 48.2256C19.9366 47.4931 18.0979 46.6 16.3292 45.5858C16.1893 45.5041 16.1781 45.304 16.3068 45.2082C16.679 44.9293 17.0513 44.6391 17.4067 44.3461C17.471 44.2926 17.5606 44.2813 17.6362 44.3151C29.2558 49.6202 41.8354 49.6202 53.3179 44.3151C53.3935 44.2785 53.4831 44.2898 53.5502 44.3433C53.9057 44.6363 54.2779 44.9293 54.6529 45.2082C54.7816 45.304 54.7732 45.5041 54.6333 45.5858C52.8646 46.6197 51.0259 47.4931 49.0921 48.2228C48.9662 48.2707 48.9102 48.4172 48.9718 48.5383C50.0383 50.6034 51.2557 52.5699 52.5959 54.435C52.6519 54.5139 52.7526 54.5477 52.845 54.5195C58.6464 52.7249 64.529 50.0174 70.6019 45.5576C70.6551 45.5182 70.6887 45.459 70.6943 45.3942C72.1747 30.0791 68.2147 16.7757 60.1968 4.9823C60.1772 4.9429 60.1437 4.9147 60.1045 4.8978ZM23.7259 37.3253C20.2276 37.3253 17.3451 34.1136 17.3451 30.1693C17.3451 26.225 20.1717 23.0133 23.7259 23.0133C27.308 23.0133 30.1626 26.2532 30.1099 30.1693C30.1099 34.1136 27.2680 37.3253 23.7259 37.3253ZM47.3178 37.3253C43.8196 37.3253 40.9371 34.1136 40.9371 30.1693C40.9371 26.225 43.7680 23.0133 47.3178 23.0133C50.9003 23.0133 53.7545 26.2532 53.7018 30.1693C53.7018 34.1136 50.9003 37.3253 47.3178 37.3253Z"/>
            </svg>
            Discord Integration
        </h1>
    </div>
    
    <?php if ($message) showToast($message, 'success'); ?>
    <?php if ($error) showToast($error, 'error'); ?>
    
    <div class="row" style="display: grid; grid-template-columns: 2fr 1fr; gap: 24px;">
        <div>
            <!-- Setup Instructions -->
            <div class="info-box">
                <h4>📋 Discord OAuth Setup Instructions</h4>
                <ol>
                    <li>Go to the <a href="https://discord.com/developers/applications" target="_blank" rel="noopener">Discord Developer Portal</a></li>
                    <li>Click "New Application" and give it a name (e.g., your community name)</li>
                    <li>Go to "OAuth2" → "General" in the sidebar</li>
                    <li>Copy the <strong>Client ID</strong> and paste it below</li>
                    <li>Click "Reset Secret" to generate a <strong>Client Secret</strong> and paste it below</li>
                    <li>Under "Redirects", add: <code><?php echo htmlspecialchars($discordSettings['redirect_uri']); ?></code></li>
                    <li>Save changes in Discord, then save settings here</li>
                </ol>
            </div>
            
            <!-- Settings Form -->
            <form method="POST">
                <?php echo csrfField(); ?>
                <input type="hidden" name="action" value="save_discord_settings">
                
                <div class="settings-card">
                    <h3><span class="icon">🔑</span> OAuth Credentials</h3>
                    
                    <div class="form-group">
                        <label>Client ID</label>
                        <input type="text" name="client_id" value="<?php echo htmlspecialchars($discordSettings['client_id']); ?>" placeholder="Enter your Discord Client ID">
                        <p class="help-text">Found in Discord Developer Portal → OAuth2 → General</p>
                    </div>
                    
                    <div class="form-group">
                        <label>Client Secret</label>
                        <input type="password" name="client_secret" value="<?php echo htmlspecialchars($discordSettings['client_secret']); ?>" placeholder="Enter your Discord Client Secret">
                        <p class="help-text">Keep this secret! Never share it publicly.</p>
                    </div>
                    
                    <div class="form-group">
                        <label>Redirect URI</label>
                        <div class="input-with-copy">
                            <input type="text" name="redirect_uri" value="<?php echo htmlspecialchars($discordSettings['redirect_uri']); ?>" placeholder="https://yourdomain.com/auth/discord_callback.php">
                            <button type="button" class="copy-btn" onclick="copyToClipboard(this.previousElementSibling.value)">Copy</button>
                        </div>
                        <p class="help-text">Add this exact URL to your Discord app's OAuth2 Redirects</p>
                    </div>
                </div>
                
                <div class="settings-card">
                    <h3><span class="icon">⚙️</span> Behavior Settings</h3>
                    
                    <div class="toggle-group">
                        <div class="toggle-info">
                            <h4>Enable Discord OAuth</h4>
                            <p>Allow users to login and register with Discord</p>
                        </div>
                        <label class="toggle-switch">
                            <input type="checkbox" name="discord_enabled" <?php echo $discordSettings['enabled'] ? 'checked' : ''; ?>>
                            <span class="toggle-slider"></span>
                        </label>
                    </div>
                    
                    <div class="toggle-group">
                        <div class="toggle-info">
                            <h4>Allow Login with Discord</h4>
                            <p>Existing users can login using their linked Discord</p>
                        </div>
                        <label class="toggle-switch">
                            <input type="checkbox" name="allow_login" <?php echo $discordSettings['allow_login'] ? 'checked' : ''; ?>>
                            <span class="toggle-slider"></span>
                        </label>
                    </div>
                    
                    <div class="toggle-group">
                        <div class="toggle-info">
                            <h4>Allow Registration with Discord</h4>
                            <p>New users can create accounts using Discord</p>
                        </div>
                        <label class="toggle-switch">
                            <input type="checkbox" name="allow_registration" <?php echo $discordSettings['allow_registration'] ? 'checked' : ''; ?>>
                            <span class="toggle-slider"></span>
                        </label>
                    </div>
                    
                    <div class="toggle-group">
                        <div class="toggle-info">
                            <h4>Require Discord for Registration</h4>
                            <p>Users must register with Discord (disables normal registration)</p>
                        </div>
                        <label class="toggle-switch">
                            <input type="checkbox" name="require_discord" <?php echo $discordSettings['require_discord'] ? 'checked' : ''; ?>>
                            <span class="toggle-slider"></span>
                        </label>
                    </div>
                </div>
                
                <button type="submit" class="btn btn-primary">💾 Save Settings</button>
            </form>
        </div>
        
        <div>
            <!-- Stats -->
            <div class="stat-card" style="margin-bottom: 24px;">
                <div class="number"><?php echo number_format($discordLinkedCount); ?></div>
                <div class="label">Users with Discord Linked</div>
            </div>
            
            <!-- Preview -->
            <div class="settings-card">
                <h3><span class="icon">👁️</span> Button Preview</h3>
                <div class="discord-preview">
                    <h4>Login Page</h4>
                    <div class="discord-btn-preview">
                        <svg width="20" height="20" viewBox="0 0 71 55" fill="currentColor">
                            <path d="M60.1045 4.8978C55.5792 2.8214 50.7265 1.2916 45.6527 0.41542C45.5603 0.39851 45.468 0.440769 45.4204 0.525289C44.7963 1.6353 44.105 3.0834 43.6209 4.2216C38.1637 3.4046 32.7345 3.4046 27.3892 4.2216C26.905 3.0581 26.1886 1.6353 25.5617 0.525289C25.5141 0.443589 25.4218 0.40133 25.3294 0.41542C20.2584 1.2888 15.4057 2.8186 10.8776 4.8978C10.8384 4.9147 10.8048 4.9429 10.7825 4.9795C1.57795 18.7309 -0.943561 32.1443 0.293408 45.3914C0.299005 45.4562 0.335386 45.5182 0.385761 45.5576C6.45866 50.0174 12.3413 52.7249 18.1147 54.5195C18.2071 54.5477 18.305 54.5139 18.3638 54.4378C19.7295 52.5728 20.9469 50.6063 21.9907 48.5383C22.0523 48.4172 21.9935 48.2735 21.8676 48.2256C19.9366 47.4931 18.0979 46.6 16.3292 45.5858C16.1893 45.5041 16.1781 45.304 16.3068 45.2082C16.679 44.9293 17.0513 44.6391 17.4067 44.3461C17.471 44.2926 17.5606 44.2813 17.6362 44.3151C29.2558 49.6202 41.8354 49.6202 53.3179 44.3151C53.3935 44.2785 53.4831 44.2898 53.5502 44.3433C53.9057 44.6363 54.2779 44.9293 54.6529 45.2082C54.7816 45.304 54.7732 45.5041 54.6333 45.5858C52.8646 46.6197 51.0259 47.4931 49.0921 48.2228C48.9662 48.2707 48.9102 48.4172 48.9718 48.5383C50.0383 50.6034 51.2557 52.5699 52.5959 54.435C52.6519 54.5139 52.7526 54.5477 52.845 54.5195C58.6464 52.7249 64.529 50.0174 70.6019 45.5576C70.6551 45.5182 70.6887 45.459 70.6943 45.3942C72.1747 30.0791 68.2147 16.7757 60.1968 4.9823C60.1772 4.9429 60.1437 4.9147 60.1045 4.8978ZM23.7259 37.3253C20.2276 37.3253 17.3451 34.1136 17.3451 30.1693C17.3451 26.225 20.1717 23.0133 23.7259 23.0133C27.308 23.0133 30.1626 26.2532 30.1099 30.1693C30.1099 34.1136 27.2680 37.3253 23.7259 37.3253ZM47.3178 37.3253C43.8196 37.3253 40.9371 34.1136 40.9371 30.1693C40.9371 26.225 43.7680 23.0133 47.3178 23.0133C50.9003 23.0133 53.7545 26.2532 53.7018 30.1693C53.7018 34.1136 50.9003 37.3253 47.3178 37.3253Z"/>
                        </svg>
                        Login with Discord
                    </div>
                </div>
            </div>
            
            <!-- Status -->
            <div class="settings-card">
                <h3><span class="icon">📊</span> Status</h3>
                
                <div style="display: flex; flex-direction: column; gap: 12px;">
                    <div style="display: flex; justify-content: space-between; align-items: center;">
                        <span style="color: var(--text-secondary); font-size: 13px;">OAuth Enabled</span>
                        <?php if ($discordSettings['enabled']): ?>
                            <span style="color: #22c55e; font-size: 13px;">✓ Active</span>
                        <?php else: ?>
                            <span style="color: var(--text-faint); font-size: 13px;">○ Disabled</span>
                        <?php endif; ?>
                    </div>
                    
                    <div style="display: flex; justify-content: space-between; align-items: center;">
                        <span style="color: var(--text-secondary); font-size: 13px;">Credentials Set</span>
                        <?php if (!empty($discordSettings['client_id']) && !empty($discordSettings['client_secret'])): ?>
                            <span style="color: #22c55e; font-size: 13px;">✓ Configured</span>
                        <?php else: ?>
                            <span style="color: #f59e0b; font-size: 13px;">○ Missing</span>
                        <?php endif; ?>
                    </div>
                    
                    <div style="display: flex; justify-content: space-between; align-items: center;">
                        <span style="color: var(--text-secondary); font-size: 13px;">Ready to Use</span>
                        <?php if (isDiscordConfigured()): ?>
                            <span style="color: #22c55e; font-size: 13px;">✓ Yes</span>
                        <?php else: ?>
                            <span style="color: var(--danger); font-size: 13px;">✗ No</span>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
function copyToClipboard(text) {
    navigator.clipboard.writeText(text).then(() => {
        Toast.success('Copied to clipboard!');
    }).catch(() => {
        // Fallback
        const textarea = document.createElement('textarea');
        textarea.value = text;
        document.body.appendChild(textarea);
        textarea.select();
        document.execCommand('copy');
        document.body.removeChild(textarea);
        Toast.success('Copied to clipboard!');
    });
}
</script>

</body>
</html>
