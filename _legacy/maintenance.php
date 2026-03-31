<?php
/**
 * Maintenance Mode Page
 * Shown to non-admin users when maintenance mode is enabled
 */

$maintenance_message = "We're currently performing scheduled maintenance.";
$maintenance_reason = "";
$maintenance_eta = "";
$contact_info = "";

// Try to get maintenance settings from database
if (file_exists(__DIR__ . '/config.php')) {
    @include_once __DIR__ . '/config.php';
    
    if (function_exists('getDBConnection')) {
        try {
            $conn = getDBConnection();
            
            // Get maintenance settings
            $stmt = $conn->prepare("SELECT setting_key, setting_value FROM system_settings WHERE setting_key IN ('maintenance_message', 'maintenance_reason', 'maintenance_eta', 'maintenance_contact')");
            if ($stmt) {
                $stmt->execute();
                $result = $stmt->get_result();
                while ($row = $result->fetch_assoc()) {
                    switch ($row['setting_key']) {
                        case 'maintenance_message':
                            if (!empty($row['setting_value'])) $maintenance_message = $row['setting_value'];
                            break;
                        case 'maintenance_reason':
                            $maintenance_reason = $row['setting_value'];
                            break;
                        case 'maintenance_eta':
                            $maintenance_eta = $row['setting_value'];
                            break;
                        case 'maintenance_contact':
                            $contact_info = $row['setting_value'];
                            break;
                    }
                }
                $stmt->close();
            }
            $conn->close();
        } catch (Exception $e) {
            // Silently fail - show default message
        }
    }
}

// Get community name
$community_name = defined('COMMUNITY_NAME') ? COMMUNITY_NAME : 'Community Manager';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Maintenance Mode - <?php echo htmlspecialchars($community_name); ?></title>
    <link rel="icon" type="image/x-icon" href="/favicon.ico">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        :root {
            --bg-base: #0a0a0a;
            --bg-card: #161616;
            --bg-elevated: #1c1c1c;
            --border: #2a2a2a;
            --accent: #5865F2;
            --accent-muted: rgba(88, 101, 242, 0.15);
            --warning: #f0b232;
            --text-primary: #f2f3f5;
            --text-secondary: #b5bac1;
            --text-muted: #80848e;
            --radius-md: 10px;
            --radius-lg: 14px;
        }
        
        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
            background: var(--bg-base);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--text-primary);
            padding: 24px;
            -webkit-font-smoothing: antialiased;
        }
        
        body::before {
            content: '';
            position: fixed;
            inset: 0;
            background-image: 
                linear-gradient(rgba(88, 101, 242, 0.03) 1px, transparent 1px),
                linear-gradient(90deg, rgba(88, 101, 242, 0.03) 1px, transparent 1px);
            background-size: 60px 60px;
            pointer-events: none;
        }
        
        .container {
            background: var(--bg-primary);
            
            border: 1px solid var(--bg-elevated);
            border-radius: var(--radius-lg);
            padding: 48px;
            max-width: 500px;
            text-align: center;
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.5);
        }
        
        .icon {
            width: 80px;
            height: 80px;
            background: linear-gradient(135deg, rgba(251, 191, 36, 0.2), rgba(245, 158, 11, 0.2));
            border-radius: var(--radius-lg);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 40px;
            margin: 0 auto 24px;
            border: 1px solid rgba(251, 191, 36, 0.3);
            animation: pulse 2s infinite;
        }
        
        @keyframes pulse {
            0%, 100% { transform: scale(1); }
            50% { transform: scale(1.05); }
        }
        
        h1 {
            font-size: 28px;
            font-weight: 700;
            margin-bottom: 12px;
            background: linear-gradient(135deg, #fff 0%, var(--bg-elevated) 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }
        
        .subtitle {
            font-size: 15px;
            color: var(--text-muted);
            margin-bottom: 32px;
            line-height: 1.6;
        }
        
        .message-box {
            background: rgba(251, 191, 36, 0.1);
            border: 1px solid rgba(251, 191, 36, 0.2);
            border-radius: var(--radius-md);
            padding: 20px;
            margin-bottom: 24px;
        }
        
        .message-box p {
            font-size: 15px;
            color: var(--text-primary);
            line-height: 1.6;
        }
        
        .info-grid {
            display: grid;
            gap: 16px;
            margin-bottom: 24px;
        }
        
        .info-item {
            background: var(--bg-primary);
            border-radius: var(--radius-md);
            padding: 16px;
            text-align: left;
        }
        
        .info-item h3 {
            font-size: 11px;
            color: var(--text-muted);
            text-transform: uppercase;
            letter-spacing: 1px;
            margin-bottom: 6px;
        }
        
        .info-item p {
            font-size: 14px;
            color: var(--text-secondary);
        }
        
        .progress-bar {
            height: 4px;
            background: var(--bg-elevated);
            border-radius: 2px;
            overflow: hidden;
            margin-top: 24px;
        }
        
        .progress-bar-inner {
            height: 100%;
            width: 30%;
            background: var(--accent);
            border-radius: 2px;
            animation: progress 2s ease-in-out infinite;
        }
        
        @keyframes progress {
            0% { transform: translateX(-100%); }
            100% { transform: translateX(400%); }
        }
        
        .footer {
            margin-top: 32px;
            font-size: 12px;
            color: var(--text-faint);
        }
        
        .admin-link {
            display: inline-block;
            margin-top: 16px;
            color: var(--text-muted);
            text-decoration: none;
            font-size: 13px;
            transition: color 0.2s;
        }
        
        .admin-link:hover {
            color: var(--text-secondary);
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="icon">🔧</div>
        <h1>Under Maintenance</h1>
        <p class="subtitle"><?php echo htmlspecialchars($community_name); ?></p>
        
        <div class="message-box">
            <p><?php echo htmlspecialchars($maintenance_message); ?></p>
        </div>
        
        <div class="info-grid">
            <?php if (!empty($maintenance_reason)): ?>
            <div class="info-item">
                <h3>Reason</h3>
                <p><?php echo htmlspecialchars($maintenance_reason); ?></p>
            </div>
            <?php endif; ?>
            
            <?php if (!empty($maintenance_eta)): ?>
            <div class="info-item">
                <h3>Estimated Completion</h3>
                <p><?php echo htmlspecialchars($maintenance_eta); ?></p>
            </div>
            <?php endif; ?>
            
            <?php if (!empty($contact_info)): ?>
            <div class="info-item">
                <h3>Contact</h3>
                <p><?php echo htmlspecialchars($contact_info); ?></p>
            </div>
            <?php endif; ?>
        </div>
        
        <div class="progress-bar">
            <div class="progress-bar-inner"></div>
        </div>
        
        <div class="footer">
            <p>We apologize for any inconvenience. Please check back soon.</p>
            <a href="/auth/login" class="admin-link">Administrator Login →</a>
        </div>
    </div>
</body>
</html>
