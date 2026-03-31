<?php
/**
 * Discord OAuth Callback
 * 
 * Handles the response from Discord after user authorizes
 */
ob_start();

// Error handling
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

// Load config first (it handles session)
require_once '../config.php';
if (!defined('UM_FUNCTIONS_LOADED')) { require_once __DIR__ . '/../includes/functions.php'; }
if (!defined('TOAST_LOADED')) { require_once __DIR__ . '/../includes/toast.php'; define('TOAST_LOADED', true); }

// Check if Discord is configured before loading discord.php
if (!file_exists(__DIR__ . '/../includes/discord.php')) {
    toast('Discord integration is not available.', 'error');
    session_write_close();
    header('Location: /auth/login');
    exit;
}

require_once __DIR__ . '/../includes/discord.php';

// Try to restore login from token (handles session loss after OAuth redirect)
checkLoginToken();

// Ensure Discord columns exist in database
if (!ensureDiscordColumns()) {
    toast('Discord integration requires a database update. Please run the update patch.', 'error');
    session_write_close();
    header('Location: /auth/login');
    exit;
}

$returnUrl = $_SESSION['discord_return_url'] ?? '/user/';

// Handle direct access without OAuth parameters
if (!isset($_GET['code']) && !isset($_GET['error']) && !isset($_GET['state'])) {
    toast('Invalid access. Please use the Discord login button.', 'error');
    session_write_close();
    header('Location: /auth/login');
    exit;
}

// Check for errors from Discord
if (isset($_GET['error'])) {
    $error = $_GET['error'];
    $errorDesc = $_GET['error_description'] ?? 'Unknown error';

    if ($error === 'access_denied') {
        toast('Discord authorization was cancelled.', 'warning');
    } else {
        toast('Discord error: ' . htmlspecialchars($errorDesc), 'error');
    }

    session_write_close();
    header('Location: /auth/login');
    exit;
}

// Verify we have the required parameters
if (!isset($_GET['code']) || !isset($_GET['state'])) {
    toast('Invalid Discord response. Please try again.', 'error');
    session_write_close();
    header('Location: /auth/login');
    exit;
}

$code = $_GET['code'];
$state = $_GET['state'];

// Wrap everything in try-catch to catch any errors
try {
    // Validate state token
    if (!validateDiscordState($state)) {
        toast('Invalid or expired session. Please try again.', 'error');
        session_write_close();
        header('Location: /auth/login');
        exit;
    }
    
    // Get the action from state
    $action = getDiscordActionFromState($state);
    
    // Exchange code for tokens
    $tokenData = exchangeDiscordCode($code);
    
    if (!$tokenData) {
        toast('Failed to authenticate with Discord. Please try again.', 'error');
        session_write_close();
        header('Location: /auth/login');
        exit;
    }

    // Get Discord user info
    $discordUser = getDiscordUser($tokenData['access_token']);

    if (!$discordUser) {
        toast('Failed to get Discord user info. Please try again.', 'error');
        session_write_close();
        header('Location: /auth/login');
        exit;
    }
    
    $conn = getDBConnection();

// Handle based on action
switch ($action) {
    case 'link':
        // User is logged in and wants to link Discord
        if (!isLoggedIn()) {
            toast('You must be logged in to link Discord.', 'error');
            header('Location: /auth/login');
            exit;
        }
        
        $userId = $_SESSION['user_id'];
        
        // Check if this Discord is already linked to another account
        $existingUser = findUserByDiscordId($conn, $discordUser['id']);
        if ($existingUser && $existingUser['id'] != $userId) {
            toast('This Discord account is already linked to another user.', 'error');
            $conn->close();
            session_write_close();
            header('Location: /user/security');
            exit;
        }

        // Link the account
        if (linkDiscordAccount($conn, $userId, $discordUser, $tokenData)) {
            toast('Discord account linked successfully!', 'success');
        } else {
            toast('Failed to link Discord account. Please try again.', 'error');
        }

        $conn->close();
        session_write_close();
        header('Location: /user/security');
        exit;
        break;
        
    case 'register':
        // Check if registration is allowed
        $settings = getDiscordSettings();
        if (!$settings['allow_registration']) {
            toast('Discord registration is currently disabled.', 'error');
            $conn->close();
            header('Location: /auth/register');
            exit;
        }
        
        // Check if this Discord is already linked
        $existingUser = findUserByDiscordId($conn, $discordUser['id']);
        if ($existingUser) {
            // Update tokens
            updateDiscordTokens($conn, $existingUser['id'], $tokenData);

            // Check if approved
            if (!$existingUser['is_approved']) {
                toast('Your account is pending approval. You will be notified when an administrator approves your account.', 'warning', ['persistent' => true]);
                $conn->close();
                session_write_close();
                header('Location: /auth/login');
                exit;
            }

            if ($existingUser['is_suspended']) {
                toast('Your account has been suspended. Please contact an administrator.', 'error', ['persistent' => true]);
                $conn->close();
                session_write_close();
                header('Location: /auth/login');
                exit;
            }
            
            // Already registered and approved - log them in
            $_SESSION['user_id'] = $existingUser['id'];
            $_SESSION['username'] = $existingUser['username'];
            $_SESSION['is_admin'] = $existingUser['is_admin'];
            $_SESSION['is_approved'] = true;
            
            // Generate login token cookie
            createLoginToken($existingUser['id'], $existingUser['username'], $existingUser['is_admin']);
            
            logAudit('user_login', 'user', $existingUser['id'], 'Logged in via Discord OAuth');
            recordLoginHistory($existingUser['id'], true);
            
            toast('Welcome back! You have been logged in.', 'success');
            session_write_close();
            $conn->close();
            header('Location: ' . $returnUrl);
            exit;
        }
        
        // Check if email already exists - auto-link if so
        if (!empty($discordUser['email'])) {
            $stmt = $conn->prepare("SELECT id, username, is_approved, is_suspended, is_admin FROM users WHERE email = ?");
            $stmt->bind_param("s", $discordUser['email']);
            $stmt->execute();
            $emailUser = $stmt->get_result()->fetch_assoc();
            $stmt->close();
            
            if ($emailUser) {
                // Link the Discord to this existing account
                if (linkDiscordAccount($conn, $emailUser['id'], $discordUser, $tokenData)) {
                    // Check if approved
                    if (!$emailUser['is_approved']) {
                        toast('Discord linked to your existing account. Your account is pending approval.', 'warning', ['persistent' => true]);
                        $conn->close();
                        session_write_close();
                        header('Location: /auth/login');
                        exit;
                    }

                    if ($emailUser['is_suspended']) {
                        toast('Your account has been suspended. Please contact an administrator.', 'error', ['persistent' => true]);
                        $conn->close();
                        session_write_close();
                        header('Location: /auth/login');
                        exit;
                    }

                    // Log them in
                    $_SESSION['user_id'] = $emailUser['id'];
                    $_SESSION['username'] = $emailUser['username'];
                    $_SESSION['is_admin'] = $emailUser['is_admin'];
                    $_SESSION['is_approved'] = true;

                    // Generate login token cookie
                    createLoginToken($emailUser['id'], $emailUser['username'], $emailUser['is_admin']);

                    logAudit('user_login', 'user', $emailUser['id'], 'Logged in via Discord OAuth (auto-linked by email)');
                    recordLoginHistory($emailUser['id'], true);

                    toast('Discord linked to your account automatically. Welcome!', 'success');
                    $conn->close();
                    session_write_close();
                    header('Location: ' . $returnUrl);
                    exit;
                } else {
                    toast('An account with this email already exists. Please log in and link your Discord from your profile.', 'warning', ['persistent' => true]);
                    $conn->close();
                    session_write_close();
                    header('Location: /auth/login');
                    exit;
                }
            }
        }
        
        // Create new user
        $newUserId = createUserFromDiscord($conn, $discordUser, $tokenData);
        
        if (!$newUserId) {
            toast('Failed to create account. Please try again or register manually.', 'error');
            $conn->close();
            session_write_close();
            header('Location: /auth/register');
            exit;
        }

        // Check if auto-approve is enabled (use same setting as regular registration)
        $requireApproval = getSetting('registration_require_approval', '1');
        $autoApprove = ($requireApproval === '0');

        if ($autoApprove) {
            // Log them in
            $stmt = $conn->prepare("SELECT * FROM users WHERE id = ?");
            $stmt->bind_param("i", $newUserId);
            $stmt->execute();
            $newUser = $stmt->get_result()->fetch_assoc();
            $stmt->close();

            $_SESSION['user_id'] = $newUser['id'];
            $_SESSION['username'] = $newUser['username'];
            $_SESSION['is_admin'] = $newUser['is_admin'];
            $_SESSION['is_approved'] = true;

            // Generate login token cookie
            createLoginToken($newUser['id'], $newUser['username'], $newUser['is_admin']);

            logAudit('user_login', 'user', $newUserId, 'First login via Discord OAuth');
            recordLoginHistory($newUserId, true);

            toast('Account created successfully! Welcome!', 'success');
            $conn->close();
            session_write_close();
            header('Location: ' . $returnUrl);
            exit;
        } else {
            toast('Account created successfully! Your account is pending approval. You will be notified when an administrator approves your account.', 'warning', ['persistent' => true]);
            $conn->close();
            session_write_close();
            header('Location: /auth/login');
            exit;
        }
        break;
        
    case 'login':
    default:
        // Check if registration is required but this Discord isn't linked
        $existingUser = findUserByDiscordId($conn, $discordUser['id']);
        
        if (!$existingUser) {
            // Not registered via Discord
            $settings = getDiscordSettings();
            
            // Check if email matches an existing account
            if (!empty($discordUser['email'])) {
                $stmt = $conn->prepare("SELECT * FROM users WHERE email = ?");
                $stmt->bind_param("s", $discordUser['email']);
                $stmt->execute();
                $emailUser = $stmt->get_result()->fetch_assoc();
                $stmt->close();
                
                if ($emailUser) {
                    // Automatically link Discord to this account
                    if (linkDiscordAccount($conn, $emailUser['id'], $discordUser, $tokenData)) {
                        // Check if approved
                        if (!$emailUser['is_approved']) {
                            toast('Discord linked to your account. Your account is pending approval. You will be notified when approved.', 'warning', ['persistent' => true]);
                            $conn->close();
                            session_write_close();
                            header('Location: /auth/login');
                            exit;
                        }

                        if ($emailUser['is_suspended']) {
                            toast('Your account has been suspended. Please contact an administrator.', 'error', ['persistent' => true]);
                            $conn->close();
                            session_write_close();
                            header('Location: /auth/login');
                            exit;
                        }

                        // Log them in
                        $_SESSION['user_id'] = $emailUser['id'];
                        $_SESSION['username'] = $emailUser['username'];
                        $_SESSION['is_admin'] = $emailUser['is_admin'];
                        $_SESSION['is_approved'] = true;

                        // Generate login token cookie
                        createLoginToken($emailUser['id'], $emailUser['username'], $emailUser['is_admin']);

                        logAudit('user_login', 'user', $emailUser['id'], 'Logged in via Discord OAuth (auto-linked)');
                        recordLoginHistory($emailUser['id'], true);

                        toast('Discord linked to your account automatically!', 'success');
                        $conn->close();
                        session_write_close();
                        header('Location: ' . $returnUrl);
                        exit;
                    }
                }
            }
            
            // No matching account - create one if registration is allowed
            if ($settings['allow_registration']) {
                // Create new user
                $newUserId = createUserFromDiscord($conn, $discordUser, $tokenData);

                if ($newUserId) {
                    $requireApproval = getSetting('registration_require_approval', '1');
                    $autoApprove = ($requireApproval === '0');

                    if ($autoApprove) {
                        $stmt = $conn->prepare("SELECT * FROM users WHERE id = ?");
                        $stmt->bind_param("i", $newUserId);
                        $stmt->execute();
                        $newUser = $stmt->get_result()->fetch_assoc();
                        $stmt->close();

                        $_SESSION['user_id'] = $newUser['id'];
                        $_SESSION['username'] = $newUser['username'];
                        $_SESSION['is_admin'] = $newUser['is_admin'];
                        $_SESSION['is_approved'] = true;

                        // Generate login token cookie
                        createLoginToken($newUser['id'], $newUser['username'], $newUser['is_admin']);

                        logAudit('user_login', 'user', $newUserId, 'First login via Discord OAuth');
                        recordLoginHistory($newUserId, true);

                        toast('Account created successfully! Welcome!', 'success');
                        $conn->close();
                        session_write_close();
                        header('Location: ' . $returnUrl);
                        exit;
                    } else {
                        toast('Account created successfully! Your account is pending approval. You will be notified when an administrator approves your account.', 'warning', ['persistent' => true]);
                        $conn->close();
                        session_write_close();
                        header('Location: /auth/login');
                        exit;
                    }
                } else {
                    toast('Failed to create account. Please register manually.', 'error');
                    $conn->close();
                    session_write_close();
                    header('Location: /auth/register');
                    exit;
                }
            } else {
                toast('No account found with this Discord. Please register first or link Discord from your profile.', 'warning', ['persistent' => true]);
                $conn->close();
                session_write_close();
                header('Location: /auth/login');
                exit;
            }
        }
        
        // User exists, log them in
        // Update tokens
        updateDiscordTokens($conn, $existingUser['id'], $tokenData);
        
        // Check if approved
        if (!$existingUser['is_approved']) {
            toast('Your account is pending approval. You will be notified when an administrator approves your account.', 'warning', ['persistent' => true]);
            $conn->close();
            session_write_close();
            header('Location: /auth/login');
            exit;
        }

        if ($existingUser['is_suspended']) {
            toast('Your account has been suspended: ' . ($existingUser['suspended_reason'] ?? 'No reason provided.'), 'error', ['persistent' => true]);
            $conn->close();
            session_write_close();
            header('Location: /auth/login');
            exit;
        }
        
        // Log them in
        $_SESSION['user_id'] = $existingUser['id'];
        $_SESSION['username'] = $existingUser['username'];
        $_SESSION['is_admin'] = $existingUser['is_admin'];
        $_SESSION['is_approved'] = true;
        
        // Generate a login token cookie (bypasses session issues after cross-site redirect)
        createLoginToken($existingUser['id'], $existingUser['username'], $existingUser['is_admin']);
        
        logAudit('user_login', 'user', $existingUser['id'], 'Logged in via Discord OAuth');
        recordLoginHistory($existingUser['id'], true);
        
        // Check 2FA
        if (!empty($existingUser['two_factor_secret'])) {
            $_SESSION['pending_2fa_user_id'] = $existingUser['id'];
            $_SESSION['pending_2fa_username'] = $existingUser['username'];
            $_SESSION['pending_2fa_is_admin'] = $existingUser['is_admin'];
            $_SESSION['pending_2fa_is_approved'] = $existingUser['is_approved'];
            $_SESSION['pending_2fa_must_change'] = !empty($existingUser['must_change_password']);
            unset($_SESSION['user_id'], $_SESSION['username'], $_SESSION['is_admin'], $_SESSION['is_approved']);
            // Clear login token for 2FA
            setcookie('discord_login_token', '', time() - 3600, '/');
            $conn->close();
            session_write_close();
            header('Location: /auth/verify_2fa');
            exit;
        }
        
        toast('Welcome back!', 'success');
        
        // Clean up and redirect
        $conn->close();
        ob_end_clean();
        session_write_close();
        
        header('Location: ' . $returnUrl);
        exit;
        break;
}

} catch (Exception $e) {
    error_log("Discord OAuth error: " . $e->getMessage());
    toast('An error occurred during Discord authentication. Please try again.', 'error');
    session_write_close();
    header('Location: /auth/login');
    exit;
}
