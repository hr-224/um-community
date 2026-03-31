================================================================================
              UM COMMUNITY MANAGER v1.3.1-beta - INSTALLATION GUIDE
================================================================================

Thank you for choosing UM Community Manager! This guide will help you get
your FiveM community management system up and running.

SYSTEM REQUIREMENTS:
--------------------
- PHP 8.0 or higher (7.4 minimum)
- MySQL 5.7 or higher / MariaDB 10.3+
- PHP Extensions: mysqli, json, session, mbstring, curl, openssl
- Apache with mod_rewrite OR Nginx
- SSL Certificate (recommended for production)
- Writable permissions on the root directory (for config.php)

INSTALLATION STEPS:
-------------------
1. Upload all files to your web server document root
2. Navigate to your domain (e.g., https://yourdomain.com)
3. You will be automatically redirected to the installer
4. Enter your database credentials when prompted
5. Create your admin account
6. Complete the installation wizard

SHARED HOSTING NOTES:
---------------------
This system is designed to work on shared hosting without composer or
external dependencies. All required libraries are included.

If using cPanel/Plesk:
- Create a MySQL database and user via the control panel
- Note the database name, username, password, and host (usually localhost)
- Upload files via File Manager or FTP

POST-INSTALLATION SECURITY:
---------------------------
After installation is complete, you MUST:

1. DELETE this entire /install folder
   - On Linux: rm -rf /path/to/your/site/install
   - On cPanel: Use File Manager to delete the folder
   - On FTP: Delete the folder and all contents

2. Set config.php to read-only (recommended)
   - On Linux: chmod 444 config.php
   - This prevents unauthorized modifications

3. Ensure your .htaccess file is properly configured

OPTIONAL CONFIGURATION:
-----------------------
After installation, visit Admin > System Settings to configure:
- Community name and branding
- Theme colors and background image
- SMTP email settings
- Discord OAuth integration
- Discord webhook notifications
- Scheduled task settings

DISCORD OAUTH SETUP:
--------------------
1. Go to https://discord.com/developers/applications
2. Create a new application
3. Go to OAuth2 > General
4. Add redirect URL: https://yourdomain.com/auth/discord_callback
5. Copy Client ID and Client Secret
6. Enter in Admin > Discord Settings

CRON JOBS (Optional):
---------------------
For automated tasks (reports, cleanup), add this cron job:
*/15 * * * * curl -s "https://yourdomain.com/cron/scheduled_tasks.php?token=YOUR_CRON_TOKEN" > /dev/null

TROUBLESHOOTING:
----------------
"Connection failed" errors:
  - Verify your database credentials
  - Ensure your database server is running
  - Check that the database user has CREATE, ALTER, INSERT privileges

"Permission denied" errors:
  - Make sure the web server can write to the root directory
  - On Linux: chmod 755 /path/to/your/site
  - After install: chmod 444 config.php

Blank pages or 500 errors:
  - Check PHP error logs
  - Ensure all required PHP extensions are installed
  - Verify PHP version is 7.4+

Discord login not working:
  - Verify redirect URL matches exactly
  - Check Client ID and Secret are correct
  - Ensure OAuth is enabled in Discord settings

SUPPORT:
--------
Website: https://ultimate-mods.com/
Documentation: https://docs.ultimate-mods.com/
Email: support@ultimate-mods.com

================================================================================
        ⚠️  DELETE THIS FOLDER AFTER INSTALLATION FOR SECURITY!  ⚠️
================================================================================
