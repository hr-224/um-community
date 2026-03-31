-- =====================================================
-- UM COMMUNITY MANAGER - COMPLETE DATABASE SETUP
-- =====================================================
-- This SQL file creates all required tables for the
-- UM Community Manager system. Run this on a fresh
-- database installation.
-- =====================================================


-- =====================================================
-- LICENSE TABLE (Must be first)
-- =====================================================

-- License Information
CREATE TABLE IF NOT EXISTS license_info (
    id INT AUTO_INCREMENT PRIMARY KEY,
    license_key VARCHAR(100) NOT NULL,
    license_id INT DEFAULT NULL,
    product_name VARCHAR(255) DEFAULT NULL,
    customer_name VARCHAR(255) DEFAULT NULL,
    customer_email VARCHAR(255) DEFAULT NULL,
    customer_id INT DEFAULT NULL,
    licensed_domain VARCHAR(255) DEFAULT NULL,
    purchased_at TIMESTAMP NULL,
    expires_at TIMESTAMP NULL,
    is_active BOOLEAN DEFAULT FALSE,
    is_canceled BOOLEAN DEFAULT FALSE,
    last_validated_at TIMESTAMP NULL,
    validation_response TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);


-- =====================================================
-- CORE TABLES
-- =====================================================

-- Users table
CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) UNIQUE NOT NULL,
    email VARCHAR(100) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    discord_id VARCHAR(50),
    discord_user_id VARCHAR(50) DEFAULT NULL,
    discord_username VARCHAR(100) DEFAULT NULL,
    discord_discriminator VARCHAR(10) DEFAULT NULL,
    discord_avatar VARCHAR(255) DEFAULT NULL,
    discord_email VARCHAR(255) DEFAULT NULL,
    discord_access_token VARCHAR(500) DEFAULT NULL,
    discord_refresh_token VARCHAR(500) DEFAULT NULL,
    discord_token_expires DATETIME DEFAULT NULL,
    discord_linked_at DATETIME DEFAULT NULL,
    is_admin BOOLEAN DEFAULT FALSE,
    is_approved BOOLEAN DEFAULT FALSE,
    is_suspended BOOLEAN DEFAULT FALSE,
    suspended_reason VARCHAR(255) DEFAULT NULL,
    suspended_at DATETIME DEFAULT NULL,
    must_change_password BOOLEAN DEFAULT FALSE,
    timezone VARCHAR(50) DEFAULT 'UTC',
    reset_token VARCHAR(64),
    reset_expires DATETIME,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Departments
CREATE TABLE IF NOT EXISTS departments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    abbreviation VARCHAR(10) NOT NULL,
    color VARCHAR(7) DEFAULT '#3B82F6',
    icon VARCHAR(50),
    logo_path VARCHAR(255) DEFAULT NULL,
    description TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Ranks
CREATE TABLE IF NOT EXISTS ranks (
    id INT AUTO_INCREMENT PRIMARY KEY,
    department_id INT NOT NULL,
    rank_name VARCHAR(100) NOT NULL,
    rank_order INT NOT NULL,
    FOREIGN KEY (department_id) REFERENCES departments(id) ON DELETE CASCADE
);

-- Roster entries
CREATE TABLE IF NOT EXISTS roster (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    department_id INT NOT NULL,
    rank_id INT NOT NULL,
    badge_number VARCHAR(20),
    callsign VARCHAR(20),
    status ENUM('active', 'loa', 'inactive') DEFAULT 'active',
    joined_date DATE,
    notes TEXT,
    is_primary BOOLEAN DEFAULT TRUE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (department_id) REFERENCES departments(id) ON DELETE CASCADE,
    FOREIGN KEY (rank_id) REFERENCES ranks(id) ON DELETE CASCADE
);

-- =====================================================
-- SYSTEM SETTINGS
-- =====================================================
CREATE TABLE IF NOT EXISTS system_settings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    setting_key VARCHAR(100) UNIQUE NOT NULL,
    setting_value TEXT,
    setting_type ENUM('text', 'color', 'boolean', 'number', 'json') DEFAULT 'text',
    description VARCHAR(255),
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Insert default system settings
INSERT INTO system_settings (setting_key, setting_value, setting_type, description) VALUES
('community_name', 'UM Community', 'text', 'Community name displayed across the site'),
('primary_color', '#667eea', 'color', 'Primary theme color'),
('secondary_color', '#764ba2', 'color', 'Secondary theme color'),
('accent_color', '#f093fb', 'color', 'Accent color for highlights'),
('background_color_start', '#0f0c29', 'color', 'Background gradient start color'),
('background_color_mid', '#302b63', 'color', 'Background gradient middle color'),
('background_color_end', '#24243e', 'color', 'Background gradient end color'),
('discord_webhook_url', '', 'text', 'Discord webhook URL for admin notifications'),
('discord_webhook_applications_url', '', 'text', 'Discord webhook URL for applications'),
('discord_webhook_enabled', '0', 'boolean', 'Enable Discord webhook notifications'),
('discord_oauth_enabled', '0', 'boolean', 'Enable Discord OAuth login'),
('discord_client_id', '', 'text', 'Discord OAuth client ID'),
('discord_client_secret', '', 'text', 'Discord OAuth client secret'),
('discord_redirect_uri', '', 'text', 'Discord OAuth redirect URI'),
('discord_allow_registration', '1', 'boolean', 'Allow registration via Discord'),
('discord_allow_login', '1', 'boolean', 'Allow login via Discord'),
('discord_require_discord', '0', 'boolean', 'Require Discord account to register'),
('email_notifications_enabled', '1', 'boolean', 'Enable email notifications'),
('auto_loa_return', '1', 'boolean', 'Automatically return users from LOA when end date passes'),
('motm_enabled', '1', 'boolean', 'Enable Member of the Month feature'),
('applications_enabled', '1', 'boolean', 'Enable public department applications')
ON DUPLICATE KEY UPDATE setting_key = setting_key;

-- =====================================================
-- USER STATUS SYSTEM
-- =====================================================
CREATE TABLE IF NOT EXISTS user_status (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL UNIQUE,
    status ENUM('online', 'away', 'busy', 'offline') DEFAULT 'offline',
    custom_status VARCHAR(100),
    last_activity DATETIME DEFAULT CURRENT_TIMESTAMP,
    last_seen DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- =====================================================
-- SESSION MANAGEMENT
-- =====================================================
CREATE TABLE IF NOT EXISTS user_sessions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    session_token VARCHAR(128) UNIQUE NOT NULL,
    ip_address VARCHAR(45),
    user_agent TEXT,
    device_type VARCHAR(50),
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    last_activity TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    expires_at DATETIME,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- =====================================================
-- LOA REQUESTS
-- =====================================================
CREATE TABLE IF NOT EXISTS loa_requests (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    department_id INT,
    start_date DATE NOT NULL,
    end_date DATE NOT NULL,
    reason TEXT NOT NULL,
    status ENUM('pending', 'approved', 'denied') DEFAULT 'pending',
    approved_by INT,
    auto_returned BOOLEAN DEFAULT FALSE,
    return_date DATE,
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (department_id) REFERENCES departments(id) ON DELETE SET NULL,
    FOREIGN KEY (approved_by) REFERENCES users(id) ON DELETE SET NULL
);

-- =====================================================
-- AUDIT LOG
-- =====================================================
CREATE TABLE IF NOT EXISTS audit_log (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT,
    action VARCHAR(100) NOT NULL,
    target_type VARCHAR(50),
    target_id INT,
    details TEXT,
    ip_address VARCHAR(45),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
);

-- =====================================================
-- NOTIFICATIONS
-- =====================================================
CREATE TABLE IF NOT EXISTS notifications (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    title VARCHAR(255) NOT NULL,
    message TEXT NOT NULL,
    type VARCHAR(50) DEFAULT 'info',
    is_read BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- =====================================================
-- SMTP SETTINGS
-- =====================================================
CREATE TABLE IF NOT EXISTS smtp_settings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    smtp_host VARCHAR(255) NOT NULL,
    smtp_port INT NOT NULL DEFAULT 587,
    smtp_username VARCHAR(255) NOT NULL,
    smtp_password VARCHAR(255) NOT NULL,
    smtp_from_email VARCHAR(255) NOT NULL,
    smtp_from_name VARCHAR(255) NOT NULL,
    smtp_encryption ENUM('tls', 'ssl', 'none') DEFAULT 'tls',
    is_active BOOLEAN DEFAULT TRUE,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- =====================================================
-- ANNOUNCEMENTS SYSTEM
-- =====================================================
CREATE TABLE IF NOT EXISTS announcements (
    id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(255) NOT NULL,
    content TEXT NOT NULL,
    type ENUM('info', 'warning', 'urgent', 'maintenance') DEFAULT 'info',
    target_type ENUM('all', 'department', 'admins') DEFAULT 'all',
    target_department_id INT,
    author_id INT NOT NULL,
    is_pinned BOOLEAN DEFAULT FALSE,
    is_active BOOLEAN DEFAULT TRUE,
    starts_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    expires_at DATETIME,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (author_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (target_department_id) REFERENCES departments(id) ON DELETE SET NULL
);

CREATE TABLE IF NOT EXISTS announcement_reads (
    id INT AUTO_INCREMENT PRIMARY KEY,
    announcement_id INT NOT NULL,
    user_id INT NOT NULL,
    read_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (announcement_id) REFERENCES announcements(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    UNIQUE KEY unique_read (announcement_id, user_id)
);

-- =====================================================
-- INTERNAL MESSAGING SYSTEM
-- =====================================================
CREATE TABLE IF NOT EXISTS messages (
    id INT AUTO_INCREMENT PRIMARY KEY,
    sender_id INT NOT NULL,
    recipient_id INT NOT NULL,
    subject VARCHAR(255),
    content TEXT NOT NULL,
    is_read BOOLEAN DEFAULT FALSE,
    read_at DATETIME,
    is_deleted_sender BOOLEAN DEFAULT FALSE,
    is_deleted_recipient BOOLEAN DEFAULT FALSE,
    parent_id INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (sender_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (recipient_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (parent_id) REFERENCES messages(id) ON DELETE SET NULL
);

-- =====================================================
-- DEPARTMENT SOPs
-- =====================================================
CREATE TABLE IF NOT EXISTS department_sops (
    id INT AUTO_INCREMENT PRIMARY KEY,
    department_id INT NOT NULL,
    title VARCHAR(255) NOT NULL,
    content LONGTEXT NOT NULL,
    category VARCHAR(100),
    version VARCHAR(20) DEFAULT '1.0',
    is_active BOOLEAN DEFAULT TRUE,
    created_by INT NOT NULL,
    last_updated_by INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (department_id) REFERENCES departments(id) ON DELETE CASCADE,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (last_updated_by) REFERENCES users(id) ON DELETE SET NULL
);

CREATE TABLE IF NOT EXISTS sop_acknowledgments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    sop_id INT NOT NULL,
    user_id INT NOT NULL,
    acknowledged_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (sop_id) REFERENCES department_sops(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    UNIQUE KEY unique_ack (sop_id, user_id)
);

-- =====================================================
-- CONDUCT RECORDS (Commendations & Warnings)
-- =====================================================
CREATE TABLE IF NOT EXISTS conduct_records (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    type ENUM('commendation', 'warning', 'disciplinary', 'note') NOT NULL,
    title VARCHAR(255) NOT NULL,
    description TEXT NOT NULL,
    severity ENUM('minor', 'moderate', 'major', 'critical') DEFAULT 'minor',
    department_id INT,
    issued_by INT NOT NULL,
    is_active BOOLEAN DEFAULT TRUE,
    expires_at DATE,
    acknowledged BOOLEAN DEFAULT FALSE,
    acknowledged_at DATETIME,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (department_id) REFERENCES departments(id) ON DELETE SET NULL,
    FOREIGN KEY (issued_by) REFERENCES users(id) ON DELETE CASCADE
);

-- =====================================================
-- APPLICATION SYSTEM
-- =====================================================
CREATE TABLE IF NOT EXISTS application_templates (
    id INT AUTO_INCREMENT PRIMARY KEY,
    department_id INT NOT NULL,
    title VARCHAR(255) NOT NULL,
    description TEXT,
    questions JSON NOT NULL,
    requirements TEXT,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (department_id) REFERENCES departments(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS applications (
    id INT AUTO_INCREMENT PRIMARY KEY,
    template_id INT NOT NULL,
    applicant_name VARCHAR(100) NOT NULL,
    applicant_email VARCHAR(255) NOT NULL,
    applicant_discord VARCHAR(100),
    answers JSON NOT NULL,
    status ENUM('pending', 'under_review', 'interview', 'approved', 'denied') DEFAULT 'pending',
    reviewer_id INT,
    reviewer_notes TEXT,
    reviewed_at DATETIME,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (template_id) REFERENCES application_templates(id) ON DELETE CASCADE,
    FOREIGN KEY (reviewer_id) REFERENCES users(id) ON DELETE SET NULL
);

-- =====================================================
-- CUSTOM FIELDS
-- =====================================================
CREATE TABLE IF NOT EXISTS custom_fields (
    id INT AUTO_INCREMENT PRIMARY KEY,
    field_name VARCHAR(100) NOT NULL,
    field_label VARCHAR(255) NOT NULL,
    field_type ENUM('text', 'textarea', 'number', 'date', 'select', 'checkbox', 'url') DEFAULT 'text',
    field_options JSON,
    applies_to ENUM('user', 'roster', 'both') DEFAULT 'both',
    department_id INT,
    is_required BOOLEAN DEFAULT FALSE,
    is_active BOOLEAN DEFAULT TRUE,
    display_order INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (department_id) REFERENCES departments(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS custom_field_values (
    id INT AUTO_INCREMENT PRIMARY KEY,
    field_id INT NOT NULL,
    entity_type ENUM('user', 'roster') NOT NULL,
    entity_id INT NOT NULL,
    field_value TEXT,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (field_id) REFERENCES custom_fields(id) ON DELETE CASCADE,
    UNIQUE KEY unique_field_entity (field_id, entity_type, entity_id)
);

-- =====================================================
-- DEPARTMENT STATISTICS
-- =====================================================
CREATE TABLE IF NOT EXISTS department_statistics (
    id INT AUTO_INCREMENT PRIMARY KEY,
    department_id INT NOT NULL,
    stat_date DATE NOT NULL,
    total_members INT DEFAULT 0,
    active_members INT DEFAULT 0,
    on_loa INT DEFAULT 0,
    inactive_members INT DEFAULT 0,
    new_joins INT DEFAULT 0,
    departures INT DEFAULT 0,
    promotions INT DEFAULT 0,
    demotions INT DEFAULT 0,
    FOREIGN KEY (department_id) REFERENCES departments(id) ON DELETE CASCADE,
    UNIQUE KEY unique_dept_date (department_id, stat_date)
);

-- =====================================================
-- DISCORD WEBHOOK LOGS
-- =====================================================
CREATE TABLE IF NOT EXISTS discord_webhook_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    event_type VARCHAR(100) NOT NULL,
    payload JSON,
    response_code INT,
    response_body TEXT,
    success BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- =====================================================
-- RECOGNITION / MEMBER OF THE MONTH
-- =====================================================
CREATE TABLE IF NOT EXISTS recognition_awards (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    award_type ENUM('motm', 'excellence', 'dedication', 'teamwork', 'custom') NOT NULL,
    custom_award_name VARCHAR(255),
    description TEXT,
    month INT,
    year INT,
    department_id INT,
    awarded_by INT NOT NULL,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (department_id) REFERENCES departments(id) ON DELETE SET NULL,
    FOREIGN KEY (awarded_by) REFERENCES users(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS recognition_nominations (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nominee_id INT NOT NULL,
    nominator_id INT NOT NULL,
    award_type ENUM('motm', 'excellence', 'dedication', 'teamwork', 'custom') NOT NULL,
    reason TEXT NOT NULL,
    month INT NOT NULL,
    year INT NOT NULL,
    department_id INT,
    status ENUM('pending', 'approved', 'denied') DEFAULT 'pending',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (nominee_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (nominator_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (department_id) REFERENCES departments(id) ON DELETE SET NULL
);

-- =====================================================
-- ROLE-BASED PERMISSIONS SYSTEM
-- =====================================================

-- Available permissions
CREATE TABLE IF NOT EXISTS permissions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    permission_key VARCHAR(100) UNIQUE NOT NULL,
    permission_name VARCHAR(255) NOT NULL,
    description TEXT,
    category VARCHAR(50) DEFAULT 'general',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Roles (groups of permissions)
CREATE TABLE IF NOT EXISTS roles (
    id INT AUTO_INCREMENT PRIMARY KEY,
    role_name VARCHAR(100) NOT NULL,
    role_key VARCHAR(50) UNIQUE NOT NULL,
    description TEXT,
    color VARCHAR(7) DEFAULT '#6B7280',
    is_system BOOLEAN DEFAULT FALSE,
    department_id INT DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (department_id) REFERENCES departments(id) ON DELETE CASCADE
);

-- Role-Permission mapping
CREATE TABLE IF NOT EXISTS role_permissions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    role_id INT NOT NULL,
    permission_id INT NOT NULL,
    FOREIGN KEY (role_id) REFERENCES roles(id) ON DELETE CASCADE,
    FOREIGN KEY (permission_id) REFERENCES permissions(id) ON DELETE CASCADE,
    UNIQUE KEY unique_role_perm (role_id, permission_id)
);

-- User-Role assignments
CREATE TABLE IF NOT EXISTS user_roles (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    role_id INT NOT NULL,
    department_id INT DEFAULT NULL,
    assigned_by INT,
    assigned_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    expires_at DATETIME DEFAULT NULL,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (role_id) REFERENCES roles(id) ON DELETE CASCADE,
    FOREIGN KEY (department_id) REFERENCES departments(id) ON DELETE CASCADE,
    FOREIGN KEY (assigned_by) REFERENCES users(id) ON DELETE SET NULL,
    UNIQUE KEY unique_user_role_dept (user_id, role_id, department_id)
);

-- =====================================================
-- TRAINING & CERTIFICATION SYSTEM
-- =====================================================

-- Certification types (FTO, SWAT, K9, Detective, etc.)
CREATE TABLE IF NOT EXISTS certification_types (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    abbreviation VARCHAR(20),
    description TEXT,
    department_id INT DEFAULT NULL,
    icon VARCHAR(50) DEFAULT '📜',
    color VARCHAR(7) DEFAULT '#3B82F6',
    validity_days INT DEFAULT NULL,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (department_id) REFERENCES departments(id) ON DELETE CASCADE
);

-- User certifications
CREATE TABLE IF NOT EXISTS user_certifications (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    certification_type_id INT NOT NULL,
    status ENUM('pending', 'in_progress', 'completed', 'expired', 'revoked') DEFAULT 'pending',
    issued_date DATE,
    expiry_date DATE,
    issued_by INT,
    revoked_by INT,
    revoked_reason TEXT,
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (certification_type_id) REFERENCES certification_types(id) ON DELETE CASCADE,
    FOREIGN KEY (issued_by) REFERENCES users(id) ON DELETE SET NULL,
    FOREIGN KEY (revoked_by) REFERENCES users(id) ON DELETE SET NULL
);

-- Training programs
CREATE TABLE IF NOT EXISTS training_programs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    description TEXT,
    department_id INT DEFAULT NULL,
    certification_type_id INT DEFAULT NULL,
    required_hours DECIMAL(5,2) DEFAULT 0,
    max_trainees INT DEFAULT NULL,
    is_active BOOLEAN DEFAULT TRUE,
    created_by INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (department_id) REFERENCES departments(id) ON DELETE CASCADE,
    FOREIGN KEY (certification_type_id) REFERENCES certification_types(id) ON DELETE SET NULL,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE CASCADE
);

-- Training sessions/records
CREATE TABLE IF NOT EXISTS training_records (
    id INT AUTO_INCREMENT PRIMARY KEY,
    trainee_id INT NOT NULL,
    trainer_id INT NOT NULL,
    program_id INT DEFAULT NULL,
    certification_type_id INT DEFAULT NULL,
    session_date DATE NOT NULL,
    hours DECIMAL(5,2) NOT NULL,
    topic VARCHAR(255),
    notes TEXT,
    performance_rating ENUM('excellent', 'good', 'satisfactory', 'needs_improvement', 'unsatisfactory') DEFAULT NULL,
    status ENUM('scheduled', 'completed', 'cancelled', 'no_show') DEFAULT 'completed',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (trainee_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (trainer_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (program_id) REFERENCES training_programs(id) ON DELETE SET NULL,
    FOREIGN KEY (certification_type_id) REFERENCES certification_types(id) ON DELETE SET NULL
);

-- Rank certification requirements
CREATE TABLE IF NOT EXISTS rank_certification_requirements (
    id INT AUTO_INCREMENT PRIMARY KEY,
    rank_id INT NOT NULL,
    certification_type_id INT NOT NULL,
    is_required BOOLEAN DEFAULT TRUE,
    FOREIGN KEY (rank_id) REFERENCES ranks(id) ON DELETE CASCADE,
    FOREIGN KEY (certification_type_id) REFERENCES certification_types(id) ON DELETE CASCADE,
    UNIQUE KEY unique_rank_cert (rank_id, certification_type_id)
);

-- =====================================================
-- PATROL/ACTIVITY LOGGING SYSTEM
-- =====================================================

-- Activity types
CREATE TABLE IF NOT EXISTS activity_types (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    description TEXT,
    department_id INT DEFAULT NULL,
    icon VARCHAR(50) DEFAULT '📋',
    color VARCHAR(7) DEFAULT '#6B7280',
    points_value DECIMAL(5,2) DEFAULT 1.00,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (department_id) REFERENCES departments(id) ON DELETE CASCADE
);

-- Activity logs
CREATE TABLE IF NOT EXISTS activity_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    department_id INT NOT NULL,
    activity_type_id INT DEFAULT NULL,
    activity_date DATE NOT NULL,
    start_time TIME,
    end_time TIME,
    duration_minutes INT DEFAULT 0,
    description TEXT,
    notes TEXT,
    verified_by INT DEFAULT NULL,
    verified_at DATETIME DEFAULT NULL,
    status ENUM('pending', 'verified', 'rejected') DEFAULT 'pending',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (department_id) REFERENCES departments(id) ON DELETE CASCADE,
    FOREIGN KEY (activity_type_id) REFERENCES activity_types(id) ON DELETE SET NULL,
    FOREIGN KEY (verified_by) REFERENCES users(id) ON DELETE SET NULL
);

-- Activity requirements (minimum activity per period)
CREATE TABLE IF NOT EXISTS activity_requirements (
    id INT AUTO_INCREMENT PRIMARY KEY,
    department_id INT DEFAULT NULL,
    rank_id INT DEFAULT NULL,
    period_type ENUM('weekly', 'biweekly', 'monthly') DEFAULT 'weekly',
    min_hours DECIMAL(5,2) DEFAULT 0,
    min_activities INT DEFAULT 0,
    min_points DECIMAL(7,2) DEFAULT 0,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (department_id) REFERENCES departments(id) ON DELETE CASCADE,
    FOREIGN KEY (rank_id) REFERENCES ranks(id) ON DELETE CASCADE
);

-- Activity warnings (auto-generated for low activity)
CREATE TABLE IF NOT EXISTS activity_warnings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    department_id INT NOT NULL,
    period_start DATE NOT NULL,
    period_end DATE NOT NULL,
    required_hours DECIMAL(5,2),
    actual_hours DECIMAL(5,2),
    warning_type ENUM('low_activity', 'no_activity', 'missed_requirement') DEFAULT 'low_activity',
    is_excused BOOLEAN DEFAULT FALSE,
    excused_by INT DEFAULT NULL,
    excused_reason TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (department_id) REFERENCES departments(id) ON DELETE CASCADE,
    FOREIGN KEY (excused_by) REFERENCES users(id) ON DELETE SET NULL
);

-- =====================================================
-- PROMOTION/DEMOTION WORKFLOW
-- =====================================================

-- Promotion requests
CREATE TABLE IF NOT EXISTS promotion_requests (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    department_id INT NOT NULL,
    current_rank_id INT NOT NULL,
    requested_rank_id INT NOT NULL,
    request_type ENUM('promotion', 'demotion', 'lateral') DEFAULT 'promotion',
    reason TEXT NOT NULL,
    requested_by INT NOT NULL,
    status ENUM('pending', 'approved', 'denied', 'cancelled') DEFAULT 'pending',
    reviewed_by INT DEFAULT NULL,
    review_notes TEXT,
    reviewed_at DATETIME DEFAULT NULL,
    effective_date DATE DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (department_id) REFERENCES departments(id) ON DELETE CASCADE,
    FOREIGN KEY (current_rank_id) REFERENCES ranks(id) ON DELETE CASCADE,
    FOREIGN KEY (requested_rank_id) REFERENCES ranks(id) ON DELETE CASCADE,
    FOREIGN KEY (requested_by) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (reviewed_by) REFERENCES users(id) ON DELETE SET NULL
);

-- Promotion history
CREATE TABLE IF NOT EXISTS promotion_history (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    department_id INT NOT NULL,
    from_rank_id INT,
    to_rank_id INT NOT NULL,
    change_type ENUM('promotion', 'demotion', 'lateral', 'initial') DEFAULT 'promotion',
    reason TEXT,
    effective_date DATE NOT NULL,
    processed_by INT,
    promotion_request_id INT DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (department_id) REFERENCES departments(id) ON DELETE CASCADE,
    FOREIGN KEY (from_rank_id) REFERENCES ranks(id) ON DELETE SET NULL,
    FOREIGN KEY (to_rank_id) REFERENCES ranks(id) ON DELETE CASCADE,
    FOREIGN KEY (processed_by) REFERENCES users(id) ON DELETE SET NULL,
    FOREIGN KEY (promotion_request_id) REFERENCES promotion_requests(id) ON DELETE SET NULL
);

-- Time-in-rank requirements
CREATE TABLE IF NOT EXISTS rank_requirements (
    id INT AUTO_INCREMENT PRIMARY KEY,
    from_rank_id INT NOT NULL,
    to_rank_id INT NOT NULL,
    min_days_in_rank INT DEFAULT 0,
    min_training_hours DECIMAL(6,2) DEFAULT 0,
    min_activity_hours DECIMAL(6,2) DEFAULT 0,
    notes TEXT,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (from_rank_id) REFERENCES ranks(id) ON DELETE CASCADE,
    FOREIGN KEY (to_rank_id) REFERENCES ranks(id) ON DELETE CASCADE,
    UNIQUE KEY unique_rank_progression (from_rank_id, to_rank_id)
);

-- =====================================================
-- SECURITY: LOGIN ATTEMPTS / BRUTE FORCE PROTECTION
-- =====================================================
CREATE TABLE IF NOT EXISTS login_attempts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) NOT NULL,
    ip_address VARCHAR(45) NOT NULL,
    attempted_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_la_username (username),
    INDEX idx_la_ip (ip_address),
    INDEX idx_la_attempted (attempted_at)
);

-- =====================================================
-- INDEXES FOR PERFORMANCE
-- =====================================================
CREATE INDEX idx_user_status_user ON user_status(user_id);
CREATE INDEX idx_sessions_user ON user_sessions(user_id);
CREATE INDEX idx_sessions_token ON user_sessions(session_token);
CREATE INDEX idx_messages_recipient ON messages(recipient_id, is_read);
CREATE INDEX idx_messages_sender ON messages(sender_id);
CREATE INDEX idx_conduct_user ON conduct_records(user_id, type);
CREATE INDEX idx_applications_status ON applications(status);
CREATE INDEX idx_announcements_active ON announcements(is_active, starts_at, expires_at);
CREATE INDEX idx_loa_dates ON loa_requests(start_date, end_date, status);
CREATE INDEX idx_roster_user ON roster(user_id);
CREATE INDEX idx_roster_dept ON roster(department_id);
CREATE INDEX idx_audit_user ON audit_log(user_id);
CREATE INDEX idx_audit_action ON audit_log(action);
CREATE INDEX idx_notifications_user ON notifications(user_id, is_read);
CREATE INDEX idx_user_roles_user ON user_roles(user_id);
CREATE INDEX idx_user_certs_user ON user_certifications(user_id);
CREATE INDEX idx_user_certs_status ON user_certifications(status);
CREATE INDEX idx_training_trainee ON training_records(trainee_id);
CREATE INDEX idx_training_trainer ON training_records(trainer_id);
CREATE INDEX idx_activity_user ON activity_logs(user_id, activity_date);
CREATE INDEX idx_activity_dept ON activity_logs(department_id, activity_date);
CREATE INDEX idx_promotion_user ON promotion_requests(user_id);
CREATE INDEX idx_promotion_status ON promotion_requests(status);
CREATE INDEX idx_promo_history_user ON promotion_history(user_id);

-- =====================================================
-- DEFAULT DATA
-- =====================================================

-- Insert default departments
INSERT INTO departments (name, abbreviation, color, icon, description) VALUES
('Los Santos Police Department', 'LSPD', '#1E40AF', '🚔', 'Primary law enforcement for Los Santos'),
('Blaine County Sheriff Office', 'BCSO', '#92400E', '⭐', 'Law enforcement for Blaine County'),
('San Andreas State Police', 'SASP', '#1E3A8A', '🚨', 'State-wide law enforcement'),
('Fire Department', 'SAFD', '#DC2626', '🚒', 'Fire and rescue services'),
('Emergency Medical Services', 'EMS', '#059669', '🚑', 'Emergency medical response'),
('Department of Justice', 'DOJ', '#7C2D12', '⚖️', 'Judicial services'),
('Communications', 'COMM', '#6B21A8', '📡', 'Dispatch and communications');

-- Insert sample ranks for LSPD
INSERT INTO ranks (department_id, rank_name, rank_order) VALUES
(1, 'Chief of Police', 1),
(1, 'Assistant Chief', 2),
(1, 'Commander', 3),
(1, 'Captain', 4),
(1, 'Lieutenant', 5),
(1, 'Sergeant', 6),
(1, 'Corporal', 7),
(1, 'Senior Officer', 8),
(1, 'Officer', 9),
(1, 'Cadet', 10);



-- =====================================================
-- DEFAULT PERMISSIONS
-- =====================================================
INSERT INTO permissions (permission_key, permission_name, description, category) VALUES
-- Application permissions
('apps.view', 'View Applications', 'View submitted applications', 'applications'),
('apps.review', 'Review Applications', 'Approve or deny applications', 'applications'),
('apps.templates.view', 'View Application Templates', 'View application templates', 'applications'),
('apps.templates.manage', 'Manage Application Templates', 'Create, edit, and delete application templates', 'applications'),
-- Training permissions
('training.view', 'View Training Records', 'View training records', 'training'),
('training.manage', 'Manage Training', 'Create and manage training sessions', 'training'),
('training.certify', 'Issue Certifications', 'Issue and revoke certifications', 'training'),
('training.programs', 'Manage Training Programs', 'Create and manage training programs', 'training'),
-- Activity permissions
('activity.view', 'View Activity Logs', 'View activity logs', 'activity'),
('activity.log', 'Log Activity', 'Log own activity', 'activity'),
('activity.manage', 'Manage Activity', 'Verify and manage activity logs', 'activity'),
('activity.requirements', 'Manage Requirements', 'Set activity requirements', 'activity'),
-- Roster permissions
('roster.view', 'View Roster', 'View department roster', 'roster'),
('roster.manage', 'Manage Roster', 'Add, edit, remove roster entries', 'roster'),
('roster.promote', 'Process Promotions', 'Approve and process promotions', 'roster'),
-- Department permissions
('dept.view', 'View Department', 'View department information', 'department'),
('dept.manage', 'Manage Department', 'Edit department settings', 'department'),
('dept.ranks', 'Manage Ranks', 'Create and edit ranks', 'department'),
('dept.sops', 'Manage SOPs', 'Create and edit SOPs', 'department'),
-- Conduct permissions
('conduct.view', 'View Conduct Records', 'View conduct records', 'conduct'),
('conduct.manage', 'Manage Conduct Records', 'Create and manage conduct records', 'conduct'),
-- Admin permissions
('admin.users', 'Manage Users', 'Manage user accounts', 'admin'),
('admin.settings', 'System Settings', 'Access system settings', 'admin'),
('admin.audit', 'View Audit Log', 'View audit log', 'admin'),
('admin.roles', 'Manage Roles', 'Create and assign roles', 'admin')
ON DUPLICATE KEY UPDATE permission_key = permission_key;

-- =====================================================
-- DEFAULT ROLES
-- =====================================================
INSERT INTO roles (role_name, role_key, description, color, is_system) VALUES
('Application Reviewer', 'app_reviewer', 'Can review and process applications', '#10B981', TRUE),
('Field Training Officer', 'fto', 'Can conduct training and issue certifications', '#3B82F6', TRUE),
('Department Lead', 'dept_lead', 'Can manage department roster and settings', '#8B5CF6', TRUE),
('Human Resources', 'hr', 'Can manage conduct records and personnel matters', '#EC4899', TRUE),
('Activity Manager', 'activity_mgr', 'Can verify activity logs and manage requirements', '#F59E0B', TRUE)
ON DUPLICATE KEY UPDATE role_key = role_key;

-- Assign permissions to roles
-- App Reviewer: view and review apps only
INSERT INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id FROM roles r, permissions p 
WHERE r.role_key = 'app_reviewer' AND p.permission_key IN ('apps.view', 'apps.review', 'apps.templates.view')
ON DUPLICATE KEY UPDATE role_id = role_id;

-- FTO: training and certification permissions
INSERT INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id FROM roles r, permissions p 
WHERE r.role_key = 'fto' AND p.permission_key IN ('training.view', 'training.manage', 'training.certify', 'roster.view')
ON DUPLICATE KEY UPDATE role_id = role_id;

-- Department Lead: roster and department management
INSERT INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id FROM roles r, permissions p 
WHERE r.role_key = 'dept_lead' AND p.permission_key IN ('roster.view', 'roster.manage', 'roster.promote', 'dept.view', 'dept.manage', 'dept.ranks', 'dept.sops', 'training.view', 'activity.view', 'activity.manage')
ON DUPLICATE KEY UPDATE role_id = role_id;

-- HR: conduct and personnel
INSERT INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id FROM roles r, permissions p 
WHERE r.role_key = 'hr' AND p.permission_key IN ('conduct.view', 'conduct.manage', 'roster.view', 'activity.view')
ON DUPLICATE KEY UPDATE role_id = role_id;

-- Activity Manager: activity logging
INSERT INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id FROM roles r, permissions p 
WHERE r.role_key = 'activity_mgr' AND p.permission_key IN ('activity.view', 'activity.manage', 'activity.requirements', 'roster.view')
ON DUPLICATE KEY UPDATE role_id = role_id;

-- =====================================================
-- DEFAULT ACTIVITY TYPES
-- =====================================================
INSERT INTO activity_types (name, description, icon, color, points_value) VALUES
('Patrol', 'Regular patrol duty', '🚔', '#3B82F6', 1.00),
('Traffic Stop', 'Traffic enforcement', '🚦', '#F59E0B', 0.25),
('Arrest', 'Suspect arrest', '🚨', '#EF4444', 0.50),
('Investigation', 'Criminal investigation', '🔍', '#8B5CF6', 0.75),
('Training', 'Training session', '📚', '#10B981', 1.00),
('Meeting', 'Department meeting', '👥', '#6B7280', 0.50),
('Event', 'Community event', '🎉', '#EC4899', 1.00),
('Administrative', 'Administrative duties', '📋', '#6B7280', 0.50)
ON DUPLICATE KEY UPDATE name = name;

-- =====================================================
-- EVENT FOR AUTO LOA RETURN (Optional - requires EVENT privileges)
-- =====================================================
-- Note: This event is optional and requires MySQL EVENT privileges.
-- The system also handles auto-LOA-return via PHP on each page load.
-- If you want to enable the MySQL event, run this manually as MySQL admin:
--
-- DELIMITER //
-- CREATE EVENT IF NOT EXISTS auto_return_from_loa
-- ON SCHEDULE EVERY 1 DAY
-- STARTS CURRENT_TIMESTAMP
-- DO
-- BEGIN
--     UPDATE roster r
--     INNER JOIN loa_requests l ON r.user_id = l.user_id
--     SET r.status = 'active'
--     WHERE l.status = 'approved' 
--     AND l.end_date < CURDATE() 
--     AND r.status = 'loa'
--     AND l.auto_returned = FALSE;
--     
--     UPDATE loa_requests 
--     SET auto_returned = TRUE, return_date = CURDATE()
--     WHERE status = 'approved' 
--     AND end_date < CURDATE() 
--     AND auto_returned = FALSE;
-- END//
-- DELIMITER ;
-- SET GLOBAL event_scheduler = ON;
-- =====================================================

-- =====================================================
-- DASHBOARD QUICK LINKS
-- =====================================================
CREATE TABLE IF NOT EXISTS quick_links (
    id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(100) NOT NULL,
    url VARCHAR(255) NOT NULL,
    icon VARCHAR(50) DEFAULT '🔗',
    sort_order INT DEFAULT 0,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Insert default quick links
INSERT INTO quick_links (title, url, icon, sort_order) VALUES
('Request LOA', '/user/loa', '📅', 1),
('LOA Calendar', '/user/loa_calendar', '📆', 2),
('Messages', '/user/messages', '✉️', 3),
('Announcements', '/user/announcements', '📢', 4);
-- =====================================================

-- =====================================================
-- SHIFT CALENDAR
-- =====================================================
CREATE TABLE IF NOT EXISTS shifts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(255) NOT NULL,
    description TEXT,
    shift_date DATE NOT NULL,
    start_time TIME NOT NULL,
    end_time TIME NOT NULL,
    max_slots INT DEFAULT 0,
    department_id INT,
    created_by INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_shift_date (shift_date),
    FOREIGN KEY (department_id) REFERENCES departments(id) ON DELETE SET NULL,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS shift_signups (
    id INT AUTO_INCREMENT PRIMARY KEY,
    shift_id INT NOT NULL,
    user_id INT NOT NULL,
    signed_up_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY unique_signup (shift_id, user_id),
    FOREIGN KEY (shift_id) REFERENCES shifts(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- =====================================================
-- EVENT CALENDAR
-- =====================================================
CREATE TABLE IF NOT EXISTS events (
    id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(255) NOT NULL,
    description TEXT,
    event_date DATE NOT NULL,
    start_time TIME,
    end_time TIME,
    location VARCHAR(255),
    is_mandatory BOOLEAN DEFAULT FALSE,
    created_by INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_event_date (event_date),
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS event_rsvps (
    id INT AUTO_INCREMENT PRIMARY KEY,
    event_id INT NOT NULL,
    user_id INT NOT NULL,
    status ENUM('attending', 'maybe', 'not_attending') DEFAULT 'attending',
    responded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY unique_rsvp (event_id, user_id),
    FOREIGN KEY (event_id) REFERENCES events(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- =====================================================
-- DOCUMENT LIBRARY
-- =====================================================
CREATE TABLE IF NOT EXISTS documents (
    id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(255) NOT NULL,
    description TEXT,
    file_path VARCHAR(500) NOT NULL,
    file_name VARCHAR(255) NOT NULL,
    file_type VARCHAR(100),
    file_size INT,
    category VARCHAR(100) DEFAULT 'General',
    department_id INT,
    is_public BOOLEAN DEFAULT FALSE,
    uploaded_by INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_doc_category (category),
    FOREIGN KEY (department_id) REFERENCES departments(id) ON DELETE SET NULL,
    FOREIGN KEY (uploaded_by) REFERENCES users(id) ON DELETE CASCADE
);

-- =====================================================
-- READ RECEIPTS
-- =====================================================
CREATE TABLE IF NOT EXISTS read_receipts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    content_type ENUM('announcement', 'sop', 'document') NOT NULL,
    content_id INT NOT NULL,
    user_id INT NOT NULL,
    read_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY unique_read (content_type, content_id, user_id),
    INDEX idx_content (content_type, content_id),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- =====================================================
-- PATROL/ACTIVITY LOGS
-- =====================================================
CREATE TABLE IF NOT EXISTS patrol_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    log_type VARCHAR(100) NOT NULL DEFAULT 'Patrol',
    description TEXT,
    started_at DATETIME NOT NULL,
    ended_at DATETIME,
    duration_minutes INT,
    department_id INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_patrol_user (user_id),
    INDEX idx_patrol_date (started_at),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (department_id) REFERENCES departments(id) ON DELETE SET NULL
);

-- =====================================================
-- MEDALS/BADGES
-- =====================================================
CREATE TABLE IF NOT EXISTS badges (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    description TEXT,
    icon VARCHAR(50) DEFAULT '🏅',
    color VARCHAR(20) DEFAULT '#fbbf24',
    rarity ENUM('common', 'uncommon', 'rare', 'epic', 'legendary') DEFAULT 'common',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS user_badges (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    badge_id INT NOT NULL,
    awarded_by INT,
    awarded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    reason TEXT,
    UNIQUE KEY unique_user_badge (user_id, badge_id),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (badge_id) REFERENCES badges(id) ON DELETE CASCADE,
    FOREIGN KEY (awarded_by) REFERENCES users(id) ON DELETE SET NULL
);

-- =====================================================
-- MENTORSHIP/FTO TRACKING
-- =====================================================
CREATE TABLE IF NOT EXISTS mentorships (
    id INT AUTO_INCREMENT PRIMARY KEY,
    trainee_id INT NOT NULL,
    mentor_id INT NOT NULL,
    department_id INT,
    status ENUM('active', 'completed', 'cancelled') DEFAULT 'active',
    started_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    completed_at DATETIME,
    notes TEXT,
    FOREIGN KEY (trainee_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (mentor_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (department_id) REFERENCES departments(id) ON DELETE SET NULL
);

CREATE TABLE IF NOT EXISTS mentorship_notes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    mentorship_id INT NOT NULL,
    note TEXT NOT NULL,
    created_by INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (mentorship_id) REFERENCES mentorships(id) ON DELETE CASCADE,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE CASCADE
);

-- =====================================================
-- ADMIN NOTES
-- =====================================================
CREATE TABLE IF NOT EXISTS admin_notes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    note TEXT NOT NULL,
    created_by INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE CASCADE
);

-- =====================================================
-- SESSION MANAGEMENT (enhanced)
-- =====================================================
CREATE TABLE IF NOT EXISTS active_sessions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    session_token VARCHAR(64) NOT NULL UNIQUE,
    ip_address VARCHAR(45),
    user_agent VARCHAR(500),
    last_activity TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_session_user (user_id),
    INDEX idx_session_token (session_token),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- =====================================================
-- WEBHOOK LOGS
-- =====================================================
CREATE TABLE IF NOT EXISTS webhook_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    webhook_type VARCHAR(50) NOT NULL,
    target_url VARCHAR(500),
    payload TEXT,
    response_code INT,
    response_body TEXT,
    success BOOLEAN DEFAULT FALSE,
    error_message VARCHAR(500),
    sent_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_webhook_type (webhook_type),
    INDEX idx_webhook_date (sent_at)
);

-- =====================================================
-- LOGIN HISTORY
-- =====================================================
CREATE TABLE IF NOT EXISTS login_history (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    ip_address VARCHAR(45),
    user_agent VARCHAR(500),
    location VARCHAR(255),
    success BOOLEAN DEFAULT TRUE,
    failure_reason VARCHAR(255),
    login_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_login_user (user_id),
    INDEX idx_login_date (login_at),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- =====================================================
-- TWO-FACTOR AUTHENTICATION
-- =====================================================
CREATE TABLE IF NOT EXISTS two_factor_codes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL UNIQUE,
    secret VARCHAR(32) NOT NULL,
    is_enabled BOOLEAN DEFAULT FALSE,
    backup_codes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- =====================================================
-- CALLSIGN MANAGEMENT
-- =====================================================
CREATE TABLE IF NOT EXISTS callsigns (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL UNIQUE,
    callsign VARCHAR(20) NOT NULL UNIQUE,
    department_id INT,
    assigned_by INT,
    assigned_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (department_id) REFERENCES departments(id) ON DELETE SET NULL,
    FOREIGN KEY (assigned_by) REFERENCES users(id) ON DELETE SET NULL
);

-- =====================================================
-- DEPARTMENT TRANSFER REQUESTS
-- =====================================================
CREATE TABLE IF NOT EXISTS transfer_requests (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    from_department_id INT NOT NULL,
    to_department_id INT NOT NULL,
    reason TEXT,
    status ENUM('pending', 'approved', 'denied') DEFAULT 'pending',
    reviewed_by INT,
    reviewed_at DATETIME,
    review_notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_transfer_status (status),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (from_department_id) REFERENCES departments(id) ON DELETE CASCADE,
    FOREIGN KEY (to_department_id) REFERENCES departments(id) ON DELETE CASCADE,
    FOREIGN KEY (reviewed_by) REFERENCES users(id) ON DELETE SET NULL
);

-- =====================================================
-- CHAIN OF COMMAND
-- =====================================================
CREATE TABLE IF NOT EXISTS chain_of_command (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    department_id INT,
    position_title VARCHAR(100) NOT NULL,
    reports_to INT,
    display_order INT DEFAULT 0,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (department_id) REFERENCES departments(id) ON DELETE SET NULL,
    FOREIGN KEY (reports_to) REFERENCES users(id) ON DELETE SET NULL
);

-- =====================================================
-- LOGIN ANOMALY DETECTION
-- =====================================================
CREATE TABLE IF NOT EXISTS trusted_devices (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    device_hash VARCHAR(64) NOT NULL,
    device_name VARCHAR(255),
    ip_address VARCHAR(45),
    last_ip VARCHAR(45),
    location VARCHAR(255),
    first_seen TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    last_seen TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    is_trusted BOOLEAN DEFAULT FALSE,
    trust_expires DATETIME,
    INDEX idx_trusted_user (user_id),
    INDEX idx_trusted_hash (device_hash),
    UNIQUE KEY unique_user_device (user_id, device_hash),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS security_alerts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    alert_type ENUM('new_device', 'new_ip', 'new_location', 'suspicious_time', 'failed_attempts', 'impossible_travel') NOT NULL,
    severity ENUM('low', 'medium', 'high', 'critical') DEFAULT 'medium',
    ip_address VARCHAR(45),
    user_agent VARCHAR(500),
    location VARCHAR(255),
    details TEXT,
    is_resolved BOOLEAN DEFAULT FALSE,
    resolved_by INT,
    resolved_at DATETIME,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_alert_user (user_id),
    INDEX idx_alert_type (alert_type),
    INDEX idx_alert_resolved (is_resolved),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (resolved_by) REFERENCES users(id) ON DELETE SET NULL
);

CREATE TABLE IF NOT EXISTS user_security_settings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL UNIQUE,
    email_on_new_device BOOLEAN DEFAULT TRUE,
    email_on_new_ip BOOLEAN DEFAULT TRUE,
    email_on_new_location BOOLEAN DEFAULT TRUE,
    email_on_failed_attempts BOOLEAN DEFAULT TRUE,
    require_2fa_new_device BOOLEAN DEFAULT FALSE,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- =====================================================
-- TRAINING QUIZ SYSTEM
-- =====================================================

CREATE TABLE IF NOT EXISTS quizzes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(255) NOT NULL,
    description TEXT,
    department_id INT DEFAULT NULL,
    certification_type_id INT DEFAULT NULL,
    training_program_id INT DEFAULT NULL,
    pass_score INT DEFAULT 70,
    time_limit_minutes INT DEFAULT NULL,
    max_attempts INT DEFAULT NULL,
    shuffle_questions BOOLEAN DEFAULT FALSE,
    show_correct_answers BOOLEAN DEFAULT TRUE,
    is_active BOOLEAN DEFAULT TRUE,
    created_by INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (department_id) REFERENCES departments(id) ON DELETE SET NULL,
    FOREIGN KEY (certification_type_id) REFERENCES certification_types(id) ON DELETE SET NULL,
    FOREIGN KEY (training_program_id) REFERENCES training_programs(id) ON DELETE SET NULL,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS quiz_questions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    quiz_id INT NOT NULL,
    question_text TEXT NOT NULL,
    question_type ENUM('multiple_choice', 'true_false', 'multi_select') DEFAULT 'multiple_choice',
    points INT DEFAULT 1,
    explanation TEXT,
    display_order INT DEFAULT 0,
    FOREIGN KEY (quiz_id) REFERENCES quizzes(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS quiz_answers (
    id INT AUTO_INCREMENT PRIMARY KEY,
    question_id INT NOT NULL,
    answer_text TEXT NOT NULL,
    is_correct BOOLEAN DEFAULT FALSE,
    display_order INT DEFAULT 0,
    FOREIGN KEY (question_id) REFERENCES quiz_questions(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS quiz_attempts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    quiz_id INT NOT NULL,
    user_id INT NOT NULL,
    score INT DEFAULT 0,
    max_score INT DEFAULT 0,
    percentage DECIMAL(5,2) DEFAULT 0,
    passed BOOLEAN DEFAULT FALSE,
    started_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    completed_at TIMESTAMP NULL,
    time_spent_seconds INT DEFAULT 0,
    FOREIGN KEY (quiz_id) REFERENCES quizzes(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS quiz_attempt_answers (
    id INT AUTO_INCREMENT PRIMARY KEY,
    attempt_id INT NOT NULL,
    question_id INT NOT NULL,
    selected_answers JSON,
    is_correct BOOLEAN DEFAULT FALSE,
    points_earned INT DEFAULT 0,
    FOREIGN KEY (attempt_id) REFERENCES quiz_attempts(id) ON DELETE CASCADE,
    FOREIGN KEY (question_id) REFERENCES quiz_questions(id) ON DELETE CASCADE
);

-- =====================================================
-- MESSAGE ATTACHMENTS
-- =====================================================

CREATE TABLE IF NOT EXISTS message_attachments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    message_id INT NOT NULL,
    filename VARCHAR(255) NOT NULL,
    original_filename VARCHAR(255) NOT NULL,
    file_path VARCHAR(500) NOT NULL,
    file_size INT NOT NULL,
    mime_type VARCHAR(100),
    uploaded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (message_id) REFERENCES messages(id) ON DELETE CASCADE
);

-- =====================================================
-- USER EMAIL PREFERENCES
-- =====================================================

CREATE TABLE IF NOT EXISTS user_email_preferences (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL UNIQUE,
    weekly_activity_report BOOLEAN DEFAULT TRUE,
    monthly_activity_report BOOLEAN DEFAULT TRUE,
    certification_expiry_alerts BOOLEAN DEFAULT TRUE,
    shift_reminders BOOLEAN DEFAULT TRUE,
    event_reminders BOOLEAN DEFAULT TRUE,
    announcement_notifications BOOLEAN DEFAULT TRUE,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- =====================================================
-- API KEYS
-- =====================================================

CREATE TABLE IF NOT EXISTS api_keys (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    api_key VARCHAR(64) NOT NULL UNIQUE,
    secret_hash VARCHAR(255) NOT NULL,
    permissions JSON,
    rate_limit INT DEFAULT 100,
    is_active BOOLEAN DEFAULT TRUE,
    last_used_at TIMESTAMP NULL,
    last_used_ip VARCHAR(45),
    created_by INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    expires_at TIMESTAMP NULL,
    INDEX idx_api_key (api_key),
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS api_request_log (
    id INT AUTO_INCREMENT PRIMARY KEY,
    api_key_id INT NOT NULL,
    endpoint VARCHAR(255) NOT NULL,
    method VARCHAR(10) NOT NULL,
    ip_address VARCHAR(45),
    response_code INT,
    request_time TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_api_log_key (api_key_id),
    INDEX idx_api_log_time (request_time),
    FOREIGN KEY (api_key_id) REFERENCES api_keys(id) ON DELETE CASCADE
);

-- =====================================================
-- SCHEDULED REPORTS
-- =====================================================

CREATE TABLE IF NOT EXISTS scheduled_reports (
    id INT AUTO_INCREMENT PRIMARY KEY,
    report_type ENUM('weekly_activity', 'monthly_activity', 'cert_expiry') NOT NULL,
    last_run_at TIMESTAMP NULL,
    next_run_at TIMESTAMP NULL,
    is_enabled BOOLEAN DEFAULT TRUE
);

