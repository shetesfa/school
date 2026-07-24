# EduTrack International School Management System v2.0

## 🚀 Installation

### Requirements
- PHP 7.4+ (PHP 8.x recommended)
- MySQL 5.7+ or MariaDB 10.3+
- Apache (with mod_rewrite) or Nginx
- Web server with mod_rewrite support

---

### Step 1 – Import Database
1. Open phpMyAdmin or MySQL CLI
2. Run the SQL file:
```
mysql -u root -p < school_management.sql
```
Or import `school_management.sql` via phpMyAdmin.

---

### Step 2 – Configure Database
Edit `config.php` and update:
```php
define('DB_HOST', 'localhost');
define('DB_NAME', 'school_management');
define('DB_USER', 'root');       // your MySQL username
define('DB_PASS', '');            // your MySQL password
```

---

### Step 3 – Upload Files
Upload the entire `school/` folder to your web server:
- **Local**: Place in `htdocs/school/` (XAMPP) or `www/school/` (WAMP)
- **Remote**: Upload to `public_html/` or your domain root

---

### Step 4 – Set Permissions
```bash
chmod 755 uploads/
chmod 755 uploads/chat/
chmod 755 uploads/students/
chmod 755 uploads/teachers/
```

---

### Step 5 – Access the System
Open in browser: `http://your-domain.com/school/`

---

## 🔐 Demo Login Accounts

| Role              | Email                    | Password   |
|-------------------|--------------------------|------------|
| Admin (Super)     | admin@school.com         | password   |
| Director/Admin    | director@school.com      | password   |
| Teacher (Math)    | james@school.com         | password   |
| Teacher (English) | sarah@school.com         | password   |
| Teacher (Science) | robert@school.com        | password   |
| Parent            | parent.smith@mail.com    | password   |
| Parent            | parent.johnson@mail.com  | password   |

> **Note**: The demo passwords above are hashed as `password` in the SQL.
> In production, change all passwords via the settings.

---

## 📱 Mobile & Desktop
The system is fully responsive and works on:
- Desktop browsers (Chrome, Firefox, Safari, Edge)
- Mobile browsers (iOS Safari, Android Chrome)
- Tablet browsers

---

## 🌙 Dark Mode
Click the moon icon (🌙) in the top bar to toggle dark mode. Preference is saved per user.

---

## 🏫 School Name
To change the school name:
1. Log in as Admin
2. Go to **Settings** tab
3. Update "School Name"
4. Click **Save Settings**

---

## 📊 Features by Role

### Admin
- Full control over all data
- Add/edit/delete students, teachers, classes
- Lock/unlock marks by class
- Control result visibility (4 modes)
- Calendar management
- Announcements
- Reports & certificates
- Chat with everyone

### Teacher
- Enter marks with flexible criteria
- Mark attendance
- View own students only
- Chat with parents
- Homeroom teachers see full class results

### Parent
- View child's attendance
- View results (based on admin visibility mode)
- View school calendar
- Chat with teachers

---

## 📋 Result Visibility Modes
| Mode | What Parents See |
|------|-----------------|
| 1    | Nothing |
| 2    | Attendance only |
| 3    | Average + Rank + Attendance |
| 4    | Full report (marks + comments) |

---

## 🔧 Production Tips
- Set `display_errors = Off` in PHP config
- Use HTTPS (SSL certificate)
- Change default passwords immediately
- Enable email notifications (add SMTP config to config.php)
- Set up regular database backups

---

## 📁 File Structure
```
school/
├── config.php          # Database & settings
├── index.php           # Entry point (redirects)
├── login.php           # Login page
├── logout.php          # Logout handler
├── admin.php           # Admin dashboard
├── teacher.php         # Teacher dashboard
├── parent.php          # Parent portal
├── chat.php            # Messaging system
├── api.php             # AJAX API handler
├── report_card.php     # Printable report cards
├── report_class.php    # Class report redirect
├── report_ranking.php  # Ranking reports
├── report_attendance.php # Attendance reports
├── notifications.php   # Notifications page
├── layout.php          # Shared header/nav
├── layout_end.php      # Shared footer
├── school_management.sql # Full database schema
├── images/             # Logo & images
├── includes/           # Shared components
│   └── calendar_view.php
└── uploads/            # User file uploads
    ├── chat/
    ├── students/
    └── teachers/
```

---

## 💬 Support
For issues or customization, check the config.php file for all system settings.

**Version**: 2.0 International Edition  
**Compatible**: PHP 7.4+, MySQL 5.7+
