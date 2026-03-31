<?php
/**
 * License Error Page
 * Shown to non-admin users when the license is invalid
 */

// No authentication required - this page must be accessible
$error_message = "This community management system is currently unavailable due to a licensing issue.";
$contact_message = "Please contact your community administrator for assistance.";

// Get community name if available (from session or default)
$community_name = 'Community Manager';
if (file_exists(__DIR__ . '/config.php')) {
    @include_once __DIR__ . '/config.php';
    if (defined('COMMUNITY_NAME')) {
        $community_name = COMMUNITY_NAME;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>System Unavailable - <?php echo htmlspecialchars($community_name); ?></title>
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
            --danger: #da373c;
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
                linear-gradient(rgba(218, 55, 60, 0.02) 1px, transparent 1px),
                linear-gradient(90deg, rgba(218, 55, 60, 0.02) 1px, transparent 1px);
            background-size: 60px 60px;
            pointer-events: none;
        }
        
        .container {
            background: var(--bg-card);
            border: 1px solid var(--border);
            border-radius: var(--radius-lg);
            padding: 40px;
            max-width: 460px;
            text-align: center;
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.5);
            position: relative;
        }
        
        .container::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 3px;
            background: var(--danger);
            border-radius: var(--radius-lg) var(--radius-lg) 0 0;
        }
        
        .icon {
            width: 64px;
            height: 64px;
            background: rgba(218, 55, 60, 0.15);
            border-radius: var(--radius-md);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 32px;
            margin: 0 auto 24px;
            border: 1px solid rgba(218, 55, 60, 0.3);
        }
        
        h1 {
            font-size: 22px;
            font-weight: 700;
            margin-bottom: 12px;
            color: var(--text-primary);
        }
        
        .subtitle {
            font-size: 15px;
            color: var(--text-muted);
            margin-bottom: 32px;
            line-height: 1.6;
        }
        
        .message-box {
            background: rgba(239, 68, 68, 0.1);
            border: 1px solid rgba(239, 68, 68, 0.2);
            border-radius: var(--radius-md);
            padding: 20px;
            margin-bottom: 24px;
        }
        
        .message-box p {
            font-size: 14px;
            color: var(--text-secondary);
            line-height: 1.6;
        }
        
        .contact-info {
            background: var(--bg-primary);
            border-radius: var(--radius-md);
            padding: 20px;
            margin-bottom: 24px;
        }
        
        .contact-info h3 {
            font-size: 14px;
            color: var(--text-muted);
            text-transform: uppercase;
            letter-spacing: 1px;
            margin-bottom: 12px;
        }
        
        .contact-info p {
            font-size: 14px;
            color: var(--text-secondary);
        }
        
        .btn {
            display: inline-block;
            padding: 14px 28px;
            background: var(--accent);
            color: var(--text-primary);
            text-decoration: none;
            border-radius: var(--radius-md);
            font-weight: 600;
            font-size: 14px;
            transition: all 0.3s ease;
            border: none;
            cursor: pointer;
        }
        
        .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 20px rgba(88, 101, 242, 0.3);
        }
        
        .footer {
            margin-top: 32px;
            font-size: 12px;
            color: var(--text-faint);
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="icon">⚠️</div>
        <h1>System Unavailable</h1>
        <p class="subtitle"><?php echo htmlspecialchars($community_name); ?></p>
        
        <div class="message-box">
            <p><?php echo htmlspecialchars($error_message); ?></p>
        </div>
        
        <div class="contact-info">
            <h3>What to do</h3>
            <p><?php echo htmlspecialchars($contact_message); ?></p>
        </div>
        
        <a href="/auth/login" class="btn">Return to Login</a>
        
        <div class="footer">
            <p>If you are an administrator, please log in to resolve this issue.</p>
        </div>
    </div>
</body>
</html>
