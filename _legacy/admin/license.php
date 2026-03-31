<?php
/**
 * ============================================================
 *  Ultimate Mods – FiveM Community Manager
 * ============================================================
 *  Admin License Settings Page
 * ============================================================
 */
require_once '../config.php';
if (!defined('UM_FUNCTIONS_LOADED')) { require_once __DIR__ . '/../includes/functions.php'; }
if (!defined('TOAST_LOADED')) { require_once __DIR__ . '/../includes/toast.php'; define('TOAST_LOADED', true); }
require_once __DIR__ . '/../includes/license.php';
requireLogin();
requireAdmin();

$conn = getDBConnection();
$message = '';
$error = '';

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && verifyCSRFToken()) {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'update_license') {
        $newKey = strtoupper(trim($_POST['license_key'] ?? ''));
        
        if (empty($newKey)) {
            $error = "Please enter a license key.";
        } else {
            // Validate the new key
            $validation = validateLicenseKey($newKey);
            
            if ($validation['valid']) {
                // Store the license
                if (storeLicenseInfo($conn, $newKey, $validation['data'])) {
                    $message = "License key validated and saved successfully!";
                    logActivity($conn, $_SESSION['user_id'], 'license_updated', 'License key updated');
                } else {
                    $error = "Failed to save license information. Please try again.";
                }
            } else {
                $error = $validation['error'];
            }
        }
    } elseif ($action === 'revalidate') {
        $license = getStoredLicense($conn);
        if ($license && !empty($license['license_key'])) {
            $validation = validateLicenseKey($license['license_key']);
            
            if ($validation['valid']) {
                storeLicenseInfo($conn, $license['license_key'], $validation['data']);
                $message = "License revalidated successfully!";
            } else {
                // Still store the latest info even if invalid (to update domain info)
                if (isset($validation['data'])) {
                    storeLicenseInfo($conn, $license['license_key'], $validation['data']);
                }
                $error = $validation['error'];
            }
        } else {
            $error = "No license key configured.";
        }
    }
}

// Get current license info - always force revalidation on this page to show current status
$license = getStoredLicense($conn);
$licenseStatus = checkLicense($conn, true); // Always revalidate on license page to show current status

// Refresh stored license after revalidation
$license = getStoredLicense($conn);

$conn->close();
$theme = getThemeColors();

// Check for session-stored license error (from redirect)
if (isset($_SESSION['license_error']) && empty($error)) {
    $error = $_SESSION['license_error'];
    unset($_SESSION['license_error']);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>License Settings - <?php echo htmlspecialchars(getCommunityName()); ?></title>
    <link rel="icon" type="image/x-icon" href="/favicon.ico">
    <?php include __DIR__ . '/../includes/styles.php'; ?>
    <style>
        .license-card {
            background: var(--bg-card);
            border: 1px solid var(--border);
            border-radius: var(--radius-lg);
            padding: 24px;
            margin-bottom: 24px;
        }
        .license-status {
            display: flex;
            align-items: center;
            gap: 16px;
            margin-bottom: 24px;
            padding-bottom: 24px;
            border-bottom: 1px solid var(--bg-elevated);
        }
        .status-icon {
            width: 56px;
            height: 56px;
            border-radius: var(--radius-lg);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 28px;
        }
        .status-icon.valid { background: rgba(34, 197, 94, 0.2); }
        .status-icon.invalid { background: rgba(239, 68, 68, 0.2); }
        .status-icon.warning { background: rgba(251, 191, 36, 0.2); }
        .status-text h3 {
            font-size: 20px;
            color: var(--text-primary);
            margin-bottom: 4px;
        }
        .status-text p {
            font-size: 14px;
            color: var(--text-muted);
        }
        
        .license-key-display {
            font-family: 'SF Mono', Monaco, monospace;
            background: rgba(0,0,0,0.3);
            padding: 14px 18px;
            border-radius: var(--radius-md);
            font-size: 15px;
            color: var(--accent);
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            letter-spacing: 0.5px;
        }
        
        .license-details {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 16px;
            margin-bottom: 20px;
        }
        .detail-item {
            background: rgba(0,0,0,0.2);
            border-radius: var(--radius-md);
            padding: 14px 16px;
        }
        .detail-item label {
            display: block;
            font-size: 11px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            color: var(--text-muted);
            margin-bottom: 6px;
        }
        .detail-item span {
            font-size: 14px;
            color: var(--text-primary);
            font-weight: 500;
        }
        
        .badge {
            display: inline-block;
            padding: 4px 12px;
            border-radius: var(--radius-lg);
            font-size: 11px;
            font-weight: 600;
            text-transform: uppercase;
        }
        .badge-success { background: rgba(34, 197, 94, 0.2); color: #22c55e; }
        .badge-danger { background: rgba(239, 68, 68, 0.2); color: var(--danger); }
        .badge-warning { background: rgba(251, 191, 36, 0.2); color: #fbbf24; }
        
        .form-section {
            margin-top: 24px;
            padding-top: 24px;
            border-top: 1px solid var(--bg-elevated);
        }
        .form-section h4 {
            margin-bottom: 16px;
            color: var(--text-primary);
            font-size: 16px;
        }
        .input-group {
            display: flex;
            gap: 12px;
        }
        .input-group input {
            flex: 1;
            font-family: 'SF Mono', Monaco, monospace;
        }
        
        .actions-row {
            display: flex;
            gap: 12px;
            margin-top: 20px;
            flex-wrap: wrap;
        }
        
        .warning-box {
            background: rgba(251, 191, 36, 0.1);
            border: 1px solid rgba(251, 191, 36, 0.3);
            border-radius: var(--radius-md);
            padding: 16px 20px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 12px;
        }
        .warning-box .icon { font-size: 20px; }
        .warning-box p { font-size: 14px; color: #fbbf24; }
        
        .info-card {
            background: rgba(59, 130, 246, 0.1);
            border: 1px solid rgba(59, 130, 246, 0.2);
            border-radius: var(--radius-md);
            padding: 20px;
        }
        .info-card h4 {
            color: var(--accent);
            margin-bottom: 12px;
            font-size: 15px;
        }
        .info-card ul {
            list-style: none;
            padding: 0;
        }
        .info-card li {
            padding: 8px 0;
            font-size: 13px;
            color: var(--text-secondary);
            display: flex;
            align-items: flex-start;
            gap: 8px;
        }
        .info-card li::before {
            content: "•";
            color: var(--accent);
        }
    </style>
</head>
<body>
<?php include __DIR__ . '/../includes/navbar.php'; ?>

<div class="container">
    <div class="page-header">
        <h1>🔐 License Settings</h1>
    </div>
    
    <?php if ($message) showToast($message, 'success'); ?>
    <?php if ($error) showToast($error, 'error'); ?>
    
    <?php if (isset($licenseStatus['error_type']) && $licenseStatus['error_type'] === UM_LICENSE_ERROR_DOMAIN_MISMATCH): ?>
        <div class="warning-box" style="background: rgba(239, 68, 68, 0.1); border-color: rgba(239, 68, 68, 0.3);">
            <span class="icon">🚫</span>
            <div>
                <p style="color: var(--danger); font-weight: 600; margin-bottom: 4px;">Domain Mismatch Detected</p>
                <p style="color: var(--text-secondary); font-size: 13px;">
                    This license is registered for <strong><?php echo htmlspecialchars($licenseStatus['licensed_domain'] ?? 'unknown'); ?></strong> 
                    but you're accessing from <strong><?php echo htmlspecialchars($licenseStatus['current_domain'] ?? getCurrentSiteDomain()); ?></strong>.
                    Contact support to transfer your license.
                </p>
            </div>
        </div>
    <?php elseif (!empty($licenseStatus['warning'])): ?>
        <div class="warning-box">
            <span class="icon">⚠️</span>
            <p><?php echo htmlspecialchars($licenseStatus['warning']); ?></p>
        </div>
    <?php endif; ?>
    
    <div class="license-card">
        <div class="license-status">
            <?php if ($licenseStatus['valid']): ?>
                <div class="status-icon valid">✓</div>
                <div class="status-text">
                    <h3>License Active</h3>
                    <p>Your license is valid and active</p>
                </div>
            <?php elseif ($license): ?>
                <div class="status-icon invalid">✕</div>
                <div class="status-text">
                    <h3>License Invalid</h3>
                    <p><?php echo htmlspecialchars($licenseStatus['error'] ?? 'Unknown error'); ?></p>
                </div>
            <?php else: ?>
                <div class="status-icon warning">!</div>
                <div class="status-text">
                    <h3>No License Configured</h3>
                    <p>Please enter your license key below</p>
                </div>
            <?php endif; ?>
        </div>
        
        <?php if ($license): ?>
            <div class="license-key-display">
                <span><?php echo maskLicenseKey($license['license_key']); ?></span>
                <span class="badge <?php echo $licenseStatus['valid'] ? 'badge-success' : 'badge-danger'; ?>">
                    <?php echo $licenseStatus['valid'] ? 'Valid' : 'Invalid'; ?>
                </span>
            </div>
            
            <div class="license-details">
                <div class="detail-item">
                    <label>Product</label>
                    <span><?php echo htmlspecialchars($license['product_name'] ?? 'N/A'); ?></span>
                </div>
                <div class="detail-item">
                    <label>Registered To</label>
                    <span><?php echo htmlspecialchars($license['customer_name'] ?? 'N/A'); ?></span>
                </div>
                <div class="detail-item">
                    <label>Email</label>
                    <span><?php echo htmlspecialchars($license['customer_email'] ?? 'N/A'); ?></span>
                </div>
                <div class="detail-item">
                    <label>Licensed Domain</label>
                    <span>
                        <?php 
                        $licensedDomain = $license['licensed_domain'] ?? null;
                        
                        // Try to extract from validation_response if not set
                        if (empty($licensedDomain) && !empty($license['validation_response'])) {
                            $licensedDomain = extractDomainFromStoredResponse($license['validation_response']);
                        }
                        
                        $currentDomain = getCurrentSiteDomain();
                        if ($licensedDomain): 
                            $domainMatch = (strtolower($licensedDomain) === strtolower($currentDomain));
                        ?>
                            <?php echo htmlspecialchars($licensedDomain); ?>
                            <?php if ($domainMatch): ?>
                                <span class="badge badge-success">✓ Match</span>
                            <?php else: ?>
                                <span class="badge badge-danger">✗ Mismatch</span>
                            <?php endif; ?>
                        <?php else: ?>
                            <span style="color: var(--text-muted);">Not set in license</span>
                        <?php endif; ?>
                    </span>
                </div>
                <div class="detail-item">
                    <label>Current Domain</label>
                    <span><?php echo htmlspecialchars($currentDomain); ?></span>
                </div>
                <div class="detail-item">
                    <label>Purchased</label>
                    <span><?php echo $license['purchased_at'] ? date('M j, Y', strtotime($license['purchased_at'])) : 'N/A'; ?></span>
                </div>
                <div class="detail-item">
                    <label>Expires</label>
                    <span>
                        <?php if ($license['expires_at']): ?>
                            <?php 
                            $expires = strtotime($license['expires_at']);
                            $daysLeft = floor(($expires - time()) / 86400);
                            echo date('M j, Y', $expires);
                            if ($daysLeft > 0 && $daysLeft <= 30) {
                                echo " <span class='badge badge-warning'>$daysLeft days left</span>";
                            }
                            ?>
                        <?php else: ?>
                            <span class="badge badge-success">Lifetime</span>
                        <?php endif; ?>
                    </span>
                </div>
                <div class="detail-item">
                    <label>Last Verified</label>
                    <span><?php echo $license['last_validated_at'] ? date('M j, Y g:i A', strtotime($license['last_validated_at'])) : 'Never'; ?></span>
                </div>
            </div>
            
            <div class="actions-row">
                <form method="POST" style="display: inline;">
                    <?php echo csrfField(); ?>
                    <input type="hidden" name="action" value="revalidate">
                    <button type="submit" class="btn">🔄 Revalidate Now</button>
                </form>
                <a href="https://ultimate-mods.com/clients/purchases/" target="_blank" rel="noopener noreferrer" class="btn btn-secondary">
                    📦 Manage License
                </a>
            </div>
        <?php endif; ?>
        
        <div class="form-section">
            <h4><?php echo $license ? 'Update License Key' : 'Enter License Key'; ?></h4>
            <form method="POST">
                <?php echo csrfField(); ?>
                <input type="hidden" name="action" value="update_license">
                <div class="input-group">
                    <input type="text" name="license_key" placeholder="XXXX-XXXX-XXXX-XXXX-XXXX" 
                           pattern="[A-Za-z0-9\-]+" required
                           style="text-transform: uppercase;">
                    <button type="submit" class="btn btn-primary">Validate & Save</button>
                </div>
            </form>
        </div>
    </div>
    
    <div class="info-card">
        <h4>ℹ️ License Information</h4>
        <ul>
            <li>Your license key can be found in your <a href="https://ultimate-mods.com/clients/purchases/" target="_blank" rel="noopener noreferrer" style="color: var(--accent);">Ultimate Mods account</a></li>
            <li>License is validated every 24 hours automatically</li>
            <li>If you need to transfer your license to a new domain, please contact support</li>
            <li>License violations may result in service termination</li>
        </ul>
    </div>
</div>

</body>
</html>
