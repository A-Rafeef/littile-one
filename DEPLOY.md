# Little One Kids Store - Deployment Guide

## 📋 Pre-Deployment Checklist

### 1. Database Setup

```sql
-- Create database on your hosting
CREATE DATABASE your_database_name CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

-- Import schema
-- Upload schema.sql and run it via phpMyAdmin or command line
```

### 2. Configuration Updates

Edit `includes/config.php`:

```php
// Change to production mode
define('ENVIRONMENT', 'production');

// Update database credentials
define('DB_HOST', 'localhost');      // Usually 'localhost' on shared hosting
define('DB_NAME', 'your_db_name');   // Your actual database name
define('DB_USER', 'your_db_user');   // Your database username
define('DB_PASS', 'your_db_pass');   // Your database password
```

### 3. File Upload

Upload these folders/files to your hosting:

```
/admin/          → Admin panel
/assets/         → CSS, JS, images
/includes/       → PHP includes
/uploads/        → Product images (set permissions to 755)
/logs/           → Error logs (set permissions to 755)
.htaccess        → Apache config
*.php            → All PHP files in root
```

### 4. Folder Permissions

```bash
chmod 755 uploads/
chmod 755 logs/
chmod 644 includes/config.php
```

### 5. Admin Login

- URL: `https://yourdomain.com/admin`
- Default username: `admin`
- Default password: `admin123`

⚠️ **IMPORTANT**: Change the admin password immediately after first login!

---

## 🔒 Security Checklist

- [ ] Changed admin password
- [ ] Set `ENVIRONMENT` to `'production'`
- [ ] Database credentials are secure
- [ ] `.htaccess` is uploaded and working
- [ ] SSL certificate is installed (HTTPS)
- [ ] Removed any test/sample data

---

## 🔧 Troubleshooting

### "500 Internal Server Error"

- Check if `.htaccess` is compatible with your hosting
- Verify PHP version is 8.0+
- Check `logs/error.log` for details

### "Database connection failed"

- Verify database credentials in `config.php`
- Ensure database exists and user has permissions

### Images not loading

- Check `uploads/` folder permissions (755)
- Verify `UPLOAD_URL` is correct in config

### Clean URLs not working

- Ensure `mod_rewrite` is enabled on server
- Check `.htaccess` file is uploaded

---

## 📱 WhatsApp Integration

Update your WhatsApp number in Admin Panel → Settings:

- Format: Country code + number (e.g., `919876543210`)
- No spaces or special characters

---

## 🚀 You're Live!

Your Little One Kids Store should now be accessible at your domain!
