# Changelog

All notable changes to the UM Community Manager will be documented in this file.

## [1.4.0-beta] - 2026-02-23

### Major UI Redesign - Discord/Stripe Inspired
- **Complete visual overhaul** - Moved from gradient glassmorphism to modern flat dark design
- **New color palette** - Deep blacks (#0a0a0a base) with Discord blurple accent (#5865F2)
- **Updated typography** - Inter font family with improved weight hierarchy
- **Modern components** - Flat cards with subtle shadows, top-accent bars on stat cards
- **Consistent design system** - CSS variables for colors, radii, and spacing

### Updated Pages
- **Dashboard (index.php)** - New stat cards, department grid, and activity sections
- **Login page** - Modern card design with grid background pattern
- **Register page** - Matching design with improved form layout
- **Apply page** - Updated public-facing application form
- **Installer** - Completely redesigned installation wizard with step progress
- **Navbar** - Streamlined flat design with modern dropdowns
- **All admin/user pages** - Via centralized styles.php

### Design System Changes
- **Backgrounds**: #0a0a0a (base), #0f0f0f (primary), #141414 (secondary), #181818 (card)
- **Borders**: #2a2a2a (default), #333333 (light)
- **Accent colors**: #5865F2 (primary), #4752c4 (hover), #7c3aed (purple)
- **Semantic colors**: #23a559 (success), #f0b232 (warning), #da373c (danger)
- **Text hierarchy**: #f2f3f5 (primary), #b5bac1 (secondary), #80848e (muted)
- **Border radius**: 4-12px (tighter than before)
- **Shadows**: Layered depth instead of gradient borders

### Technical Changes
- **styles.php** - Complete rewrite with new CSS variable system
- **navbar.php** - Modernized navigation with improved mobile support
- **Removed gradient backgrounds** - Replaced with solid flat colors
- **Removed backdrop blur** - Cleaner, faster rendering
- **Grid background pattern** - Subtle technical aesthetic

---

## [1.3.9-beta] - 2026-02-22

### Fixed - SMTP Settings Not Saving
- **Added missing CSRF verification** - The save handler was missing CSRF token validation
- **Fixed hardcoded ID in UPDATE** - Now properly updates the correct record
- **Added is_active flag on insert/update** - Ensures settings are loaded after saving
- **Improved error handling** - Now shows actual database errors if save fails

### Fixed - Discord Registration Not Appearing in Admin
- **Fixed auto-approve setting mismatch** - Discord registration was using a different setting (`auto_approve_users`) than regular registration (`registration_require_approval`). Now both use the same setting, so Discord users properly appear in the admin User Management table when auto-approve is enabled.

### Added - Registration Emails
- **Welcome email** - Sent to new users when auto-approve is enabled (both Discord and regular registration)
- **Pending approval email** - Sent to new users when admin approval is required
- **Account approved email** - Sent when an admin approves a user (single or bulk approval)

### Added - Discord Avatar Sync
- **Auto-download Discord avatar** - When users register with Discord, their Discord profile picture is automatically downloaded and set as their site profile picture
- **Avatar on link** - When existing users link their Discord account, their Discord avatar is set as their profile picture (only if they don't already have one)

### Fixed - Discord OAuth Login
- **Fixed Discord login not persisting** - Implemented a persistent login token cookie that bypasses PHP session issues after cross-site OAuth redirects. The token:
  - Lasts 24 hours or until logout
  - Is checked on every page load to restore login state
  - Is cleared on logout
  - Solves the issue where sessions were lost after redirecting back from Discord
- **Fixed missing `$_SESSION['is_approved']`** - Added required session variable for `isLoggedIn()` function
- **Fixed session state validation** - State token now stored in both session AND a secure cookie as backup
- **Fixed 500 error on Discord callback** - Replaced undefined `logActivity()` calls with `logAudit()`
- **Fixed session conflict** - Removed premature `session_start()` that conflicted with config.php session settings

### Improved - Discord OAuth User Experience  
- **Better toast notifications** - Users now see persistent notifications for approval/suspension states
- **Automatic email linking** - When registering/logging in with Discord, if the email matches an existing account, Discord is automatically linked
- **Consistent feedback** - All registration and login states now provide clear feedback to users

### Changed
- **Version footer** - Now visible to all users, not just admins

---

## [1.3.8-beta] - 2026-02-19

### Fixed - API Keys Page (Root Cause)
- **Added missing `getSiteUrl()` function** to `includes/functions.php` - this was the true root cause; the page was crashing with a fatal PHP error on render because `getSiteUrl()` was called but never defined, breaking the entire page before the button could work
- **Fixed `admin/api_docs.php`** - same `getSiteUrl()` crash + added missing `toast.php` include

---

## [1.3.7-beta] - 2026-02-19

### Fixed - Missing Toast Includes (Comprehensive Fix)
- **Fixed API Keys page** - Create button now works (was crashing due to missing `toast.php`)
- **Fixed 22 admin pages** missing `toast.php` include:
  - admin_notes, backup, badges, callsigns, chain_of_command, discord, documents, events, license, maintenance, manage_departments, mentorships, password_policies, quiz_edit, quizzes, recognition, sessions, shifts, smtp_settings, system_settings, transfers, webhook_logs
- **Fixed 10 user pages** missing `toast.php` include:
  - email_preferences, events, messages, patrol_logs, profile, sessions, shifts, sops, transfer_request, two_factor
- All pages using `showToast()` now properly include the toast system

---

## [1.3.6-beta] - 2026-02-19

### Fixed - Discord OAuth Callback Crash
- **Fixed crash on Discord callback** - Added missing `toast.php` include to `auth/discord_callback.php`
  - The `toast()` function was being called but the file was never included
  - This caused a fatal error (undefined function) when Discord redirected users back

---

## [1.3.5-beta] - 2026-02-19

### Fixed - License Page 500 Error & Domain Display
- **Fixed 500 error on license validation** - Corrected `bind_param` type strings in `storeLicenseInfo()`:
  - INSERT statement: Fixed type string (was "sisssississs", now "sisssisssiis")
  - UPDATE statement: Fixed type string (was "sisssississsi" with 14 chars, now "sisssisssiisi" with 13 chars)
  - Types for `expires_at`, `is_active`, `is_canceled` were incorrectly specified
- **Fixed "Licensed Domain" showing "Not set"** - Improved domain extraction from API response:
  - Now checks multiple possible field keys: '1', 1, 'domain', 'url', 'website', etc.
  - Iterates through all customFields looking for URL-like values
  - Also checks top-level fields for domain info
  - Added fallback extraction from stored `validation_response` JSON
- **Improved `extractDomainFromUrl()` function** - Now handles:
  - Plain domain names without http:// prefix
  - URLs with port numbers
  - Various domain formats
- **Added `extractDomainFromStoredResponse()` helper** - Extracts domain from stored validation JSON

---

## [1.3.4-beta] - 2026-02-19

### Fixed - Clean URL Compatibility
- **Installer redirect issue** - Changed all redirects to use `.php` extensions for servers without mod_rewrite enabled
- Fixed redirects in:
  - `install/index.php` - Post-install redirect now uses `/auth/login.php`
  - `auth/login.php` - 2FA and setup_account redirects
  - `auth/register.php` - Registration success redirects
  - `auth/discord_callback.php` - OAuth callback redirects
  - `auth/forgot_password.php` - Password reset redirects
  - `auth/verify_2fa.php` - 2FA verification redirects
  - `includes/functions.php` - requireLogin, requireAdmin, license check redirects
- This ensures compatibility with shared hosting environments that may not have URL rewriting enabled

---

## [1.3.3-beta] - 2026-02-18

### Fixed - Comprehensive SQL Audit
- **Null safety for all query results** - Added null coalescing operators to 40+ query fetch patterns across the codebase to prevent 500 errors when queries return unexpected results
- **Safe query helper functions** - Added `safeQueryCount()`, `safeQueryValue()`, and `tableExists()` helper functions for safer database operations
- **Fixed files:**
  - `admin/training.php` - Stats queries now use safe functions
  - `admin/promotions.php` - Stats and rank lookups now null-safe
  - `admin/activity.php` - Count query fixed
  - `admin/audit_log.php` - Pagination count fixed
  - `admin/applications.php` - Department lookup fixed
  - `admin/system_settings.php` - Quick links sort order fixed
  - `admin/manage_ranks.php` - Max order lookup fixed
  - `admin/conduct.php` - Count query fixed
  - `admin/index.php` - User count and roster check fixed
  - `admin/quizzes.php` - Added table existence safety for certification_types and training_programs
  - `cron/scheduled_tasks.php` - All stat queries now null-safe
  - `api/notifications.php` - Count queries fixed
  - `api/notifications_stream.php` - All count queries fixed
  - `api/v1/index.php` - Rate limit check fixed
  - `user/take_quiz.php` - Attempt count and started_at fixed
  - `user/messages.php` - Unread count fixed
  - `user/directory.php` - Total count fixed
  - `user/quizzes.php` - Added safe queries and proper prepared statements
  - `includes/functions.php` - Rate limiting queries fixed

### Security
- Converted raw SQL queries to prepared statements in user/quizzes.php
- Added input sanitization with intval() for user IDs in dynamic queries

---

## [1.3.2-beta] - 2026-02-18

### Fixed
- Notifications page now accessible from navbar (was showing toasts instead of linking to page)

---

## [1.3.1-beta] - 2026-02-18

### Added
- **Discord OAuth Integration** - Login, register, and link accounts with Discord
  - Auto-creates database columns on first use
  - Supports Discord avatar display
  - Account linking from security settings
- **API System** - RESTful API with key-based authentication
  - Comprehensive API documentation page
  - Configurable permissions per key
  - Rate limiting support
- **Real-time Notifications** - Server-Sent Events (SSE) for instant updates
  - Live badge counts for messages and notifications
  - Automatic fallback to polling if SSE unavailable
- **Standardized Modal System** - Consistent glassmorphism design across all popups
- **Loading States** - Visual feedback during form submissions
- **Keyboard Accessibility** - Escape key closes modals, focus states for all interactive elements
- **Version Display** - Shows app version in admin footer
- **Cloudflare IP Detection** - Proper client IP logging behind Cloudflare proxy

### Changed
- **UI Improvements**
  - Removed green/red validation borders per user feedback
  - Consistent button text colors (white on colored buttons)
  - Improved table layouts with proper width handling
  - Better empty state messaging
  - Smoother page load transitions (no white flash)
- **Maintenance Page** - Complete redesign with modern card-based layout
- **Security Alerts** - Proper JSON formatting instead of raw display
- **Scheduled Tasks** - Better error handling and minimal output for cron services

### Fixed
- Discord callback 500 errors
- Scheduled tasks crashing when optional tables don't exist
- Login page Discord SVG rendering
- Various modal styling inconsistencies
- Nested form issues in admin user management

### Security
- CSRF protection on all forms
- Rate limiting on authentication endpoints
- Secure session handling
- Password policy enforcement

---

## [1.3.0] - 2026-02-14

### Added
- **Role-Based Permissions** - Granular permission system with custom roles
- **Leave of Absence (LOA) System** - Request, approve, and track LOA
- **Training & Certification Tracking**
- **Quiz System** - Create and administer training quizzes
- **Patrol Logging** - Track patrol hours and activities
- **Department Statistics Dashboard**
- **User Status System** - Online/Away/Busy/Offline with custom messages
- **Email Notification Preferences**
- **Two-Factor Authentication (2FA)**
- **Login History Tracking**
- **Session Management** - View and revoke active sessions
- **Scheduled Tasks** - Automated reports and cleanup

### Changed
- Complete UI overhaul with dark glassmorphism theme
- Responsive design improvements
- Enhanced navbar with notification badges

---

## [1.2.0] - 2026-02-01

### Added
- **Department Management** - Create and configure departments
- **Rank System** - Hierarchical ranks per department
- **Roster Management** - Assign users to departments
- **Application System** - Custom application forms
- **Announcement System** - Community-wide announcements
- **Internal Messaging** - Private messages between users
- **Document Management** - Upload and share documents
- **Chain of Command** - Visual hierarchy display
- **Event Calendar** - Schedule and manage events

---

## [1.1.0] - 2026-01-20

### Added
- **User Management** - Registration, approval workflow
- **Admin Dashboard** - Overview and quick actions
- **Profile System** - User profiles with customization
- **Activity Logging** - Track user actions
- **Audit Trail** - Administrative action logging

---

## [1.0.0] - 2026-01-15

### Added
- Initial release
- Basic authentication system
- Installation wizard
- Database schema
- Core file structure

---

## Upgrade Notes

### From 1.2.x to 1.3.x
- Run the installer to apply database migrations
- Discord OAuth columns are auto-created on first use
- API keys table is auto-created when accessing API Keys page

### Fresh Installation
1. Upload all files to your web server
2. Navigate to `/install/` in your browser
3. Follow the installation wizard
4. Delete the `/install/` directory after setup

---

## Support

- Website: https://ultimate-mods.com/
- Documentation: https://docs.ultimate-mods.com/
- Support: support@ultimate-mods.com
