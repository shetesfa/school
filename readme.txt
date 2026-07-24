============================================
SCHOOL MANAGEMENT SYSTEM - COMPLETE SETUP GUIDE
============================================

📁 FILE STRUCTURE:
/school/
├── index.php              # Homepage
├── config.php             # Database config (auto-created)
├── login.php              # Login page
├── register.php           # Registration page
├── forgot_password.php    # Forgot password
├── reset_password.php     # Password reset
├── teacher_dashboard.php  # Teacher/Admin dashboard
├── parent_dashboard.php   # Parent dashboard
├── logout.php             # Logout handler
├── setup.php              # Initial setup (RUN FIRST)
├── email_log.txt          # Email log (auto-created)
└── .htaccess              # Security config (optional)

🔧 SETUP INSTRUCTIONS:

STEP 1: INSTALL XAMPP
1. Download and install XAMPP from https://www.apachefriends.org/
2. Start Apache and MySQL from XAMPP Control Panel
3. Open browser and go to http://localhost/phpmyadmin

STEP 2: DEPLOY FILES
1. Create a folder called "school" in C:\xampp\htdocs\
2. Copy ALL provided files into C:\xampp\htdocs\school\

STEP 3: RUN SETUP
1. Open browser and go to: http://localhost/school/setup.php
2. Click "Run Setup" button
3. Wait for "Setup Complete!" message
4. DELETE setup.php file for security!

STEP 4: ACCESS SYSTEM
1. Go to: http://localhost/school/
2. Login with:
   - Admin: admin@school.com / admin123
   - OR Register as Teacher/Parent

🔐 FORGOT PASSWORD TESTING:

LOCALHOST TEST:
1. On login page, click "Forgot Password?"
2. Enter registered email
3. Check email_log.txt file in the /school/ folder
4. Copy the reset link from the log file
5. Paste in browser to reset password

EMAIL LOG FILE LOCATION:
C:\xampp\htdocs\school\email_log.txt

📊 DEFAULT DATA:
- Admin: admin@school.com / admin123
- 6 Subjects: Mathematics, English, Science, History, Geography, Computer Science
- 5 Sample Students

👨‍🏫 TEACHER/ADMIN FEATURES:
- Manage students (add/edit/delete)
- Manage subjects
- Enter student marks
- View all marks
- Automatic percentage calculation

👨‍👩‍👧 PARENT FEATURES:
- Link children to account
- View only your children's marks
- See performance colors
- View overall averages
- Performance indicators

⚠️ SECURITY NOTES:
- All passwords hashed with password_hash()
- Password reset tokens expire in 15 minutes
- Prepared statements prevent SQL injection
- Sessions protected
- Role-based access control
- No plain-text passwords stored

🛠️ TROUBLESHOOTING:

1. "Connection failed" error:
   - Check if MySQL is running in XAMPP
   - Check if database name is "school_management"

2. "Cannot modify header information" error:
   - Remove any whitespace before <?php in files
   - Check for blank lines at beginning/end of files

3. Forgot password not working:
   - Check email_log.txt file exists and is writable
   - Check token expiration (15 minutes)

4. Parent cannot see students:
   - Teacher must add students first
   - Parent must link students to their account

📞 SUPPORT:
- Check XAMPP logs: C:\xampp\apache\logs\
- Check MySQL logs: C:\xampp\mysql\data\
- PHP errors are displayed for debugging

🔒 IMPORTANT:
- Delete setup.php after installation
- Change default admin password
- Secure your XAMPP installation
- Regular backups recommended

✅ TEST ALL FEATURES:
1. Register as Teacher and Parent
2. Login with different roles
3. Test forgot password system
4. Add students and marks as Teacher
5. Link students and view as Parent
6. Test logout functionality

The system is now ready! 🎉