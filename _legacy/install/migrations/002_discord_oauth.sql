-- Discord OAuth Migration
-- Run this on existing installations to add Discord OAuth support

-- Add Discord OAuth columns to users table
ALTER TABLE users 
ADD COLUMN IF NOT EXISTS discord_user_id VARCHAR(50) DEFAULT NULL,
ADD COLUMN IF NOT EXISTS discord_username VARCHAR(100) DEFAULT NULL,
ADD COLUMN IF NOT EXISTS discord_discriminator VARCHAR(10) DEFAULT NULL,
ADD COLUMN IF NOT EXISTS discord_avatar VARCHAR(255) DEFAULT NULL,
ADD COLUMN IF NOT EXISTS discord_email VARCHAR(255) DEFAULT NULL,
ADD COLUMN IF NOT EXISTS discord_access_token VARCHAR(500) DEFAULT NULL,
ADD COLUMN IF NOT EXISTS discord_refresh_token VARCHAR(500) DEFAULT NULL,
ADD COLUMN IF NOT EXISTS discord_token_expires DATETIME DEFAULT NULL,
ADD COLUMN IF NOT EXISTS discord_linked_at DATETIME DEFAULT NULL;

-- Add index for Discord user lookups
CREATE INDEX IF NOT EXISTS idx_discord_user_id ON users(discord_user_id);

-- Add Discord OAuth settings
INSERT INTO system_settings (setting_key, setting_value, setting_type, description) VALUES 
('discord_oauth_enabled', '0', 'boolean', 'Enable Discord OAuth login'),
('discord_client_id', '', 'text', 'Discord OAuth client ID'),
('discord_client_secret', '', 'text', 'Discord OAuth client secret'),
('discord_redirect_uri', '', 'text', 'Discord OAuth redirect URI'),
('discord_allow_registration', '1', 'boolean', 'Allow registration via Discord'),
('discord_allow_login', '1', 'boolean', 'Allow login via Discord'),
('discord_require_discord', '0', 'boolean', 'Require Discord account to register')
ON DUPLICATE KEY UPDATE setting_key = setting_key;
