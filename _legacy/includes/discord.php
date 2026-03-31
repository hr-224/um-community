<?php
/**
 * ============================================================
 *  Ultimate Mods – FiveM Community Manager
 * ============================================================
 *  Discord OAuth2 Integration
 * ============================================================
 */

// Prevent multiple includes
if (defined('UM_DISCORD_LOADED')) return;
define('UM_DISCORD_LOADED', true);

// Discord API endpoints
define('DISCORD_API_URL', 'https://discord.com/api/v10');
define('DISCORD_AUTHORIZE_URL', 'https://discord.com/api/oauth2/authorize');
define('DISCORD_TOKEN_URL', 'https://discord.com/api/oauth2/token');

/**
 * Generate a login token for Discord OAuth
 * This bypasses session issues after cross-site redirects
 * Token persists until logout or expiry (24 hours)
 */
function createLoginToken($userId, $username, $isAdmin) {
    $token = bin2hex(random_bytes(32));
    $expiry = time() + 86400; // 24 hours
    $data = [
        'token' => $token,
        'user_id' => $userId,
        'username' => $username,
        'is_admin' => $isAdmin,
        'expires' => $expiry,
        'created' => time()
    ];
    
    // Store in cookie - this will persist through page loads
    $cookieValue = base64_encode(json_encode($data));
    $isSecure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || 
                ($_SERVER['SERVER_PORT'] ?? 80) == 443;
    
    setcookie('discord_login_token', $cookieValue, [
        'expires' => $expiry,
        'path' => '/',
        'secure' => $isSecure,
        'httponly' => true,
        'samesite' => 'Lax'
    ]);
    
    return $token;
}

/**
 * Check for and process a login token
 * Call this early in pages that require login
 * Returns true if login was restored from token
 */
function checkLoginToken() {
    if (!isset($_COOKIE['discord_login_token'])) {
        return false;
    }
    
    $cookieValue = $_COOKIE['discord_login_token'];
    $data = json_decode(base64_decode($cookieValue), true);
    
    if (!$data || !isset($data['token']) || !isset($data['expires'])) {
        // Invalid cookie, clear it
        clearLoginToken();
        return false;
    }
    
    // Check expiry
    if (time() > $data['expires']) {
        clearLoginToken();
        return false;
    }
    
    // Restore session
    $_SESSION['user_id'] = $data['user_id'];
    $_SESSION['username'] = $data['username'];
    $_SESSION['is_admin'] = $data['is_admin'];
    $_SESSION['is_approved'] = true;
    
    // Keep the token - only clear on logout
    return true;
}

/**
 * Clear the login token (call on logout)
 */
function clearLoginToken() {
    if (isset($_COOKIE['discord_login_token'])) {
        $isSecure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || 
                    ($_SERVER['SERVER_PORT'] ?? 80) == 443;
        
        setcookie('discord_login_token', '', [
            'expires' => time() - 3600,
            'path' => '/',
            'secure' => $isSecure,
            'httponly' => true,
            'samesite' => 'Lax'
        ]);
    }
}

/**
 * Check if Discord OAuth columns exist in the users table
 */
function discordColumnsExist() {
    static $exists = null;
    if ($exists !== null) return $exists;
    
    try {
        $conn = getDBConnection();
        $result = $conn->query("SHOW COLUMNS FROM users LIKE 'discord_user_id'");
        $exists = ($result && $result->num_rows > 0);
        $conn->close();
    } catch (Exception $e) {
        $exists = false;
    }
    return $exists;
}

/**
 * Create Discord columns if they don't exist
 */
function ensureDiscordColumns() {
    if (discordColumnsExist()) return true;
    
    try {
        $conn = getDBConnection();
        
        // Check each column and add if missing (MySQL 5.7 compatible)
        $columns = [
            'discord_user_id' => "VARCHAR(50) DEFAULT NULL",
            'discord_username' => "VARCHAR(100) DEFAULT NULL",
            'discord_discriminator' => "VARCHAR(10) DEFAULT NULL",
            'discord_avatar' => "VARCHAR(255) DEFAULT NULL",
            'discord_email' => "VARCHAR(255) DEFAULT NULL",
            'discord_access_token' => "VARCHAR(500) DEFAULT NULL",
            'discord_refresh_token' => "VARCHAR(500) DEFAULT NULL",
            'discord_token_expires' => "DATETIME DEFAULT NULL",
            'discord_linked_at' => "DATETIME DEFAULT NULL"
        ];
        
        foreach ($columns as $col => $def) {
            $check = $conn->query("SHOW COLUMNS FROM users LIKE '$col'");
            if ($check && $check->num_rows === 0) {
                $conn->query("ALTER TABLE users ADD COLUMN $col $def");
            }
        }
        
        $conn->close();
        return true;
    } catch (Exception $e) {
        error_log("Discord column creation failed: " . $e->getMessage());
        return false;
    }
}

/**
 * Get Discord OAuth settings from database
 */
function getDiscordSettings() {
    static $settings = null;
    
    if ($settings !== null) {
        return $settings;
    }
    
    $settings = [
        'enabled' => false,
        'client_id' => '',
        'client_secret' => '',
        'redirect_uri' => '',
        'allow_registration' => true,
        'allow_login' => true,
        'require_discord' => false
    ];
    
    try {
        $conn = getDBConnection();
        $result = $conn->query("SELECT setting_key, setting_value FROM system_settings WHERE setting_key LIKE 'discord_%'");
        
        if ($result) {
            while ($row = $result->fetch_assoc()) {
                $key = str_replace('discord_', '', $row['setting_key']);
                // Handle oauth_enabled -> enabled mapping
                if ($key === 'oauth_enabled') {
                    $key = 'enabled';
                }
                if ($key === 'enabled' || $key === 'allow_registration' || $key === 'allow_login' || $key === 'require_discord') {
                    $settings[$key] = ($row['setting_value'] === '1' || $row['setting_value'] === 'true');
                } else {
                    $settings[$key] = $row['setting_value'];
                }
            }
        }
        $conn->close();
    } catch (Exception $e) {
        // Return defaults
    }
    
    return $settings;
}

/**
 * Check if Discord OAuth is properly configured
 */
function isDiscordConfigured() {
    $settings = getDiscordSettings();
    return $settings['enabled'] && 
           !empty($settings['client_id']) && 
           !empty($settings['client_secret']);
}

/**
 * Get the Discord OAuth redirect URI
 */
function getDiscordRedirectUri() {
    $settings = getDiscordSettings();
    
    if (!empty($settings['redirect_uri'])) {
        return $settings['redirect_uri'];
    }
    
    // Auto-generate based on current domain (without .php for clean URLs)
    $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? $_SERVER['SERVER_NAME'] ?? 'localhost';
    
    return $protocol . '://' . $host . '/auth/discord_callback';
}

/**
 * Generate Discord OAuth authorization URL
 * 
 * @param string $state CSRF state token
 * @param string $action 'login', 'register', or 'link'
 * @return string The authorization URL
 */
function getDiscordAuthUrl($state, $action = 'login') {
    $settings = getDiscordSettings();
    
    $params = [
        'client_id' => $settings['client_id'],
        'redirect_uri' => getDiscordRedirectUri(),
        'response_type' => 'code',
        'scope' => 'identify email guilds',
        'state' => $state . ':' . $action,
        'prompt' => 'consent'
    ];
    
    return DISCORD_AUTHORIZE_URL . '?' . http_build_query($params);
}

/**
 * Exchange authorization code for access token
 * 
 * @param string $code The authorization code
 * @return array|false Token data or false on failure
 */
function exchangeDiscordCode($code) {
    $settings = getDiscordSettings();
    
    $data = [
        'client_id' => $settings['client_id'],
        'client_secret' => $settings['client_secret'],
        'grant_type' => 'authorization_code',
        'code' => $code,
        'redirect_uri' => getDiscordRedirectUri()
    ];
    
    $ch = curl_init(DISCORD_TOKEN_URL);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => http_build_query($data),
        CURLOPT_HTTPHEADER => ['Content-Type: application/x-www-form-urlencoded']
    ]);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($httpCode !== 200) {
        error_log("Discord token exchange failed: HTTP $httpCode - $response");
        return false;
    }
    
    $tokenData = json_decode($response, true);
    
    if (!isset($tokenData['access_token'])) {
        error_log("Discord token exchange failed: No access token in response");
        return false;
    }
    
    return $tokenData;
}

/**
 * Refresh Discord access token
 * 
 * @param string $refreshToken The refresh token
 * @return array|false New token data or false on failure
 */
function refreshDiscordToken($refreshToken) {
    $settings = getDiscordSettings();
    
    $data = [
        'client_id' => $settings['client_id'],
        'client_secret' => $settings['client_secret'],
        'grant_type' => 'refresh_token',
        'refresh_token' => $refreshToken
    ];
    
    $ch = curl_init(DISCORD_TOKEN_URL);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => http_build_query($data),
        CURLOPT_HTTPHEADER => ['Content-Type: application/x-www-form-urlencoded']
    ]);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($httpCode !== 200) {
        return false;
    }
    
    return json_decode($response, true);
}

/**
 * Get Discord user info from access token
 * 
 * @param string $accessToken The access token
 * @return array|false User data or false on failure
 */
function getDiscordUser($accessToken) {
    $ch = curl_init(DISCORD_API_URL . '/users/@me');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => [
            'Authorization: Bearer ' . $accessToken,
            'Content-Type: application/json'
        ]
    ]);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($httpCode !== 200) {
        error_log("Discord user fetch failed: HTTP $httpCode - $response");
        return false;
    }
    
    $userData = json_decode($response, true);
    
    if (!isset($userData['id'])) {
        return false;
    }
    
    return $userData;
}

/**
 * Get user's Discord guilds
 * 
 * @param string $accessToken The access token
 * @return array List of guilds
 */
function getDiscordGuilds($accessToken) {
    $ch = curl_init(DISCORD_API_URL . '/users/@me/guilds');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => [
            'Authorization: Bearer ' . $accessToken,
            'Content-Type: application/json'
        ]
    ]);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($httpCode !== 200) {
        return [];
    }
    
    return json_decode($response, true) ?? [];
}

/**
 * Get Discord avatar URL
 * 
 * @param string $userId Discord user ID
 * @param string|null $avatarHash Avatar hash
 * @param string $discriminator User discriminator (for default avatar)
 * @return string Avatar URL
 */
function getDiscordAvatarUrl($userId, $avatarHash, $discriminator = '0') {
    if ($avatarHash) {
        $extension = strpos($avatarHash, 'a_') === 0 ? 'gif' : 'png';
        return "https://cdn.discordapp.com/avatars/{$userId}/{$avatarHash}.{$extension}?size=256";
    }
    
    // Default avatar
    $defaultIndex = intval($discriminator) % 5;
    return "https://cdn.discordapp.com/embed/avatars/{$defaultIndex}.png";
}

/**
 * Find user by Discord ID
 * 
 * @param mysqli $conn Database connection
 * @param string $discordId Discord user ID
 * @return array|null User data or null if not found
 */
function findUserByDiscordId($conn, $discordId) {
    $stmt = $conn->prepare("SELECT * FROM users WHERE discord_user_id = ? LIMIT 1");
    if (!$stmt) return null;
    
    $stmt->bind_param("s", $discordId);
    $stmt->execute();
    $result = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    
    return $result;
}

/**
 * Link Discord account to existing user
 * 
 * @param mysqli $conn Database connection
 * @param int $userId User ID
 * @param array $discordUser Discord user data
 * @param array $tokenData Token data (access_token, refresh_token)
 * @return bool Success
 */
function linkDiscordAccount($conn, $userId, $discordUser, $tokenData) {
    $discordId = $discordUser['id'];
    $discordUsername = $discordUser['username'];
    $discordDiscriminator = $discordUser['discriminator'] ?? '0';
    $discordAvatar = $discordUser['avatar'] ?? null;
    $discordEmail = $discordUser['email'] ?? null;
    $accessToken = $tokenData['access_token'];
    $refreshToken = $tokenData['refresh_token'] ?? null;
    $expiresIn = $tokenData['expires_in'] ?? 604800;
    $expiresAt = date('Y-m-d H:i:s', time() + $expiresIn);
    
    // Check if this Discord account is already linked to another user
    $stmt = $conn->prepare("SELECT id, username FROM users WHERE discord_user_id = ? AND id != ?");
    $stmt->bind_param("si", $discordId, $userId);
    $stmt->execute();
    $existing = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    
    if ($existing) {
        return false; // Already linked to another account
    }
    
    $sql = "UPDATE users SET 
        discord_user_id = ?,
        discord_username = ?,
        discord_discriminator = ?,
        discord_avatar = ?,
        discord_email = ?,
        discord_access_token = ?,
        discord_refresh_token = ?,
        discord_token_expires = ?,
        discord_linked_at = NOW()
        WHERE id = ?";
    
    $stmt = $conn->prepare($sql);
    if (!$stmt) return false;
    
    $stmt->bind_param("ssssssssi", 
        $discordId, $discordUsername, $discordDiscriminator, $discordAvatar,
        $discordEmail, $accessToken, $refreshToken, $expiresAt, $userId
    );
    
    $result = $stmt->execute();
    $stmt->close();
    
    if ($result) {
        logAudit('discord_linked', 'user', $userId, 'Discord account linked: ' . $discordUsername);
        
        // Download Discord avatar if user doesn't have a profile picture
        if (!empty($discordAvatar)) {
            $existingPic = getSetting('user_' . $userId . '_profile_pic', '');
            if (empty($existingPic)) {
                try {
                    downloadDiscordAvatar($userId, $discordId, $discordAvatar);
                } catch (Exception $e) {
                    error_log("Failed to download Discord avatar on link: " . $e->getMessage());
                }
            }
        }
    }
    
    return $result;
}

/**
 * Unlink Discord account from user
 * 
 * @param mysqli $conn Database connection
 * @param int $userId User ID
 * @return bool Success
 */
function unlinkDiscordAccount($conn, $userId) {
    $sql = "UPDATE users SET 
        discord_user_id = NULL,
        discord_username = NULL,
        discord_discriminator = NULL,
        discord_avatar = NULL,
        discord_email = NULL,
        discord_access_token = NULL,
        discord_refresh_token = NULL,
        discord_token_expires = NULL,
        discord_linked_at = NULL
        WHERE id = ?";
    
    $stmt = $conn->prepare($sql);
    if (!$stmt) return false;
    
    $stmt->bind_param("i", $userId);
    $result = $stmt->execute();
    $stmt->close();
    
    if ($result) {
        logAudit('discord_unlinked', 'user', $userId, 'Discord account unlinked');
    }
    
    return $result;
}

/**
 * Create new user from Discord data
 * 
 * @param mysqli $conn Database connection
 * @param array $discordUser Discord user data
 * @param array $tokenData Token data
 * @return int|false New user ID or false on failure
 */
/**
 * Download Discord avatar and save as user's profile picture
 * Returns the saved file path or false on failure
 */
function downloadDiscordAvatar($userId, $discordUserId, $discordAvatarHash) {
    if (empty($discordAvatarHash)) {
        return false;
    }
    
    // Determine file extension (animated avatars start with a_)
    $isAnimated = strpos($discordAvatarHash, 'a_') === 0;
    $ext = $isAnimated ? 'gif' : 'png';
    
    // Discord CDN URL
    $avatarUrl = "https://cdn.discordapp.com/avatars/{$discordUserId}/{$discordAvatarHash}.{$ext}?size=256";
    
    // Create upload directory if needed
    $uploadDir = $_SERVER['DOCUMENT_ROOT'] . '/uploads/profiles/';
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0755, true);
    }
    
    // Download the image
    $context = stream_context_create([
        'http' => [
            'timeout' => 10,
            'user_agent' => 'UM Community Manager/1.0'
        ]
    ]);
    
    $imageData = @file_get_contents($avatarUrl, false, $context);
    if ($imageData === false) {
        error_log("Failed to download Discord avatar from: $avatarUrl");
        return false;
    }
    
    // Delete old profile picture if exists
    $oldPic = getSetting('user_' . $userId . '_profile_pic', '');
    if ($oldPic && file_exists($_SERVER['DOCUMENT_ROOT'] . $oldPic)) {
        @unlink($_SERVER['DOCUMENT_ROOT'] . $oldPic);
    }
    
    // Save the new image
    $filename = 'user_' . $userId . '_discord_' . time() . '.' . $ext;
    $filepath = $uploadDir . $filename;
    
    if (file_put_contents($filepath, $imageData) === false) {
        error_log("Failed to save Discord avatar to: $filepath");
        return false;
    }
    
    // Update the database
    $picPath = '/uploads/profiles/' . $filename;
    $conn = getDBConnection();
    $key = 'user_' . $userId . '_profile_pic';
    $stmt = $conn->prepare("INSERT INTO system_settings (setting_key, setting_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE setting_value = ?");
    $stmt->bind_param("sss", $key, $picPath, $picPath);
    $stmt->execute();
    $stmt->close();
    $conn->close();
    
    return $picPath;
}

function createUserFromDiscord($conn, $discordUser, $tokenData) {
    $discordId = $discordUser['id'];
    $discordUsername = $discordUser['username'];
    $discordDiscriminator = $discordUser['discriminator'] ?? '0';
    $discordAvatar = $discordUser['avatar'] ?? null;
    $discordEmail = $discordUser['email'] ?? null;
    $accessToken = $tokenData['access_token'];
    $refreshToken = $tokenData['refresh_token'] ?? null;
    $expiresIn = $tokenData['expires_in'] ?? 604800;
    $expiresAt = date('Y-m-d H:i:s', time() + $expiresIn);
    
    // Generate unique username
    $baseUsername = preg_replace('/[^a-zA-Z0-9_]/', '', $discordUsername);
    if (strlen($baseUsername) < 3) {
        $baseUsername = 'user' . substr($discordId, -6);
    }
    
    $username = $baseUsername;
    $counter = 1;
    
    // Check if username exists
    while (true) {
        $stmt = $conn->prepare("SELECT id FROM users WHERE username = ?");
        $stmt->bind_param("s", $username);
        $stmt->execute();
        $exists = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        
        if (!$exists) break;
        
        $username = $baseUsername . $counter;
        $counter++;
    }
    
    // Check if email exists
    if ($discordEmail) {
        $stmt = $conn->prepare("SELECT id FROM users WHERE email = ?");
        $stmt->bind_param("s", $discordEmail);
        $stmt->execute();
        $emailExists = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        
        if ($emailExists) {
            // Email already registered - they should link instead
            return false;
        }
    }
    
    // Generate random password (user can set one later if they want)
    $randomPassword = bin2hex(random_bytes(16));
    $hashedPassword = password_hash($randomPassword, PASSWORD_DEFAULT);
    
    // Check if auto-approve is enabled (use same setting as regular registration)
    $requireApproval = getSetting('registration_require_approval', '1');
    $autoApprove = ($requireApproval === '0');
    
    $sql = "INSERT INTO users (
        username, email, password, 
        discord_user_id, discord_username, discord_discriminator, discord_avatar, discord_email,
        discord_access_token, discord_refresh_token, discord_token_expires, discord_linked_at,
        is_approved, created_at
    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), ?, NOW())";
    
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        error_log("Failed to prepare Discord user insert: " . $conn->error);
        return false;
    }
    
    $isApproved = $autoApprove ? 1 : 0;
    
    $stmt->bind_param("sssssssssssi",
        $username, $discordEmail, $hashedPassword,
        $discordId, $discordUsername, $discordDiscriminator, $discordAvatar, $discordEmail,
        $accessToken, $refreshToken, $expiresAt,
        $isApproved
    );
    
    if (!$stmt->execute()) {
        error_log("Failed to create Discord user: " . $stmt->error);
        $stmt->close();
        return false;
    }
    
    $newUserId = $stmt->insert_id;
    $stmt->close();
    
    logAudit('user_registered', 'user', $newUserId, 'User registered via Discord OAuth');
    
    // Download and set Discord avatar as profile picture
    if (!empty($discordAvatar)) {
        try {
            downloadDiscordAvatar($newUserId, $discordId, $discordAvatar);
        } catch (Exception $e) {
            error_log("Failed to download Discord avatar: " . $e->getMessage());
        }
    }
    
    // Send appropriate welcome email
    if (!empty($discordEmail)) {
        try {
            if ($autoApprove) {
                sendWelcomeEmail($discordEmail, $username);
            } else {
                sendPendingApprovalEmail($discordEmail, $username);
            }
        } catch (Exception $e) {
            error_log("Failed to send Discord registration email: " . $e->getMessage());
        }
    }
    
    // Send notification to admins about new registration
    if (!$autoApprove) {
        $admins = $conn->query("SELECT id FROM users WHERE is_admin = 1");
        while ($admin = $admins->fetch_assoc()) {
            createNotification($admin['id'], 'New Registration', 
                "New user '$username' registered via Discord and is awaiting approval.", 
                'info', '/admin/');
        }
    }
    
    return $newUserId;
}

/**
 * Update user's Discord tokens
 */
function updateDiscordTokens($conn, $userId, $tokenData) {
    $accessToken = $tokenData['access_token'];
    $refreshToken = $tokenData['refresh_token'] ?? null;
    $expiresIn = $tokenData['expires_in'] ?? 604800;
    $expiresAt = date('Y-m-d H:i:s', time() + $expiresIn);
    
    $sql = "UPDATE users SET 
        discord_access_token = ?,
        discord_refresh_token = COALESCE(?, discord_refresh_token),
        discord_token_expires = ?
        WHERE id = ?";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("sssi", $accessToken, $refreshToken, $expiresAt, $userId);
    $stmt->execute();
    $stmt->close();
}

/**
 * Generate and store OAuth state token
 */
function generateDiscordState() {
    $state = bin2hex(random_bytes(16));
    $_SESSION['discord_oauth_state'] = $state;
    $_SESSION['discord_oauth_time'] = time();
    
    // Also store in a cookie as backup (sessions can be lost during OAuth redirects)
    $cookieData = json_encode([
        'state' => $state,
        'time' => time()
    ]);
    $isSecure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ||
                ($_SERVER['SERVER_PORT'] ?? 80) == 443;
    setcookie('discord_oauth_state', $cookieData, [
        'expires' => time() + 600, // 10 minutes
        'path' => '/',
        'secure' => $isSecure,
        'httponly' => true,
        'samesite' => 'Lax'
    ]);
    
    return $state;
}

/**
 * Validate OAuth state token
 */
function validateDiscordState($state) {
    $expectedState = null;
    $stateTime = 0;
    
    // Try session first
    if (isset($_SESSION['discord_oauth_state'])) {
        $expectedState = $_SESSION['discord_oauth_state'];
        $stateTime = $_SESSION['discord_oauth_time'] ?? 0;
    }
    
    // Fall back to cookie if session doesn't have it
    if ($expectedState === null && isset($_COOKIE['discord_oauth_state'])) {
        $cookieData = json_decode($_COOKIE['discord_oauth_state'], true);
        if ($cookieData && isset($cookieData['state'])) {
            $expectedState = $cookieData['state'];
            $stateTime = $cookieData['time'] ?? 0;
        }
    }
    
    if ($expectedState === null) {
        return false;
    }
    
    // Extract the state part (before the action)
    $parts = explode(':', $state);
    $receivedState = $parts[0];
    
    // Check expiration (10 minutes)
    if (time() - $stateTime > 600) {
        // Clear both
        unset($_SESSION['discord_oauth_state'], $_SESSION['discord_oauth_time']);
        setcookie('discord_oauth_state', '', time() - 3600, '/');
        return false;
    }
    
    if (!hash_equals($expectedState, $receivedState)) {
        return false;
    }
    
    // Clear state after successful validation
    unset($_SESSION['discord_oauth_state'], $_SESSION['discord_oauth_time']);
    setcookie('discord_oauth_state', '', time() - 3600, '/');
    
    return true;
}

/**
 * Get action from state string
 */
function getDiscordActionFromState($state) {
    $parts = explode(':', $state);
    return $parts[1] ?? 'login';
}
