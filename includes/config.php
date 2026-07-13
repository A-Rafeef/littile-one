<?php

/**
 * Database Configuration
 * Little One Kids Store
 * 
 * DEPLOYMENT MODE: Set ENVIRONMENT to 'production' before going live
 */

// ============================================
// ENVIRONMENT CONFIGURATION
// ============================================
define('ENVIRONMENT', 'development'); // Change to 'production' for live site

// Error reporting based on environment
if (ENVIRONMENT === 'production') {
    error_reporting(0);
    ini_set('display_errors', '0');
    ini_set('log_errors', '1');
    ini_set('error_log', __DIR__ . '/../logs/error.log');
} else {
    error_reporting(E_ALL);
    ini_set('display_errors', '1');
}

// ============================================
// DATABASE CONFIGURATION
// ============================================

define('DB_HOST', 'localhost');
define('DB_NAME', 'ziteo_littile');  // Change this to your production database name
define('DB_USER', 'root');          // Change this to your production database user
define('DB_PASS', '');              // Change this to your production database password
define('DB_CHARSET', 'utf8mb4');


// ============================================
// SITE URL CONFIGURATION
// Automatically detects the correct URL
// ============================================
$protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ||
    (!empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https')
    ? 'https' : 'http';
$host = $_SERVER['HTTP_HOST'] ?? 'localhost';

// Detect if running in subdirectory or root
$scriptDir = dirname($_SERVER['SCRIPT_NAME']);
$basePath = '';

// Check if we're in a subdirectory (development) or root (production)
if (strpos($scriptDir, '/one1/v5') !== false) {
    $basePath = '/one1/v5';
} elseif (strpos($scriptDir, '/') === 0 && strlen($scriptDir) > 1) {
    // Might be in some other subdirectory
    $parts = explode('/', trim($scriptDir, '/'));
    if (count($parts) > 0 && !in_array($parts[0], ['admin', 'category', 'product'])) {
        $basePath = '/' . $parts[0];
        if (isset($parts[1]) && !in_array($parts[1], ['admin', 'category', 'product'])) {
            $basePath .= '/' . $parts[1];
        }
    }
}

define('SITE_URL', $protocol . '://' . $host . $basePath);
define('ADMIN_URL', SITE_URL . '/admin');

// Default Store Name
define('DEFAULT_STORE_NAME', 'Kids Store');
define('UPLOAD_PATH', __DIR__ . '/../uploads/');
define('UPLOAD_URL', SITE_URL . '/uploads/');

// ============================================
// SESSION CONFIGURATION
// ============================================
define('SESSION_NAME', 'littleone_session');
define('SESSION_LIFETIME', 86400); // 24 hours

// ============================================
// IMAGE SETTINGS
// ============================================
define('MAX_IMAGE_SIZE', 10 * 1024 * 1024); // 10MB upload limit
define('MAX_IMAGES_PER_PRODUCT', 7);
define('ALLOWED_IMAGE_TYPES', ['image/jpeg', 'image/png', 'image/webp']);

// Image compression settings
define('IMAGE_MAX_WIDTH', 1200);       // Max width after compression
define('IMAGE_MAX_HEIGHT', 1200);      // Max height after compression  
define('IMAGE_QUALITY', 85);           // JPEG/WebP quality (1-100)
define('IMAGE_COMPRESS_THRESHOLD', 2 * 1024 * 1024); // Compress if > 2MB

// ============================================
// SECURITY SETTINGS
// ============================================
if (ENVIRONMENT === 'production') {
    // Force HTTPS in production
    if ($protocol === 'http' && !headers_sent()) {
        header('Location: https://' . $host . $_SERVER['REQUEST_URI'], true, 301);
        exit;
    }

    // Security headers
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: SAMEORIGIN');
    header('X-XSS-Protection: 1; mode=block');
}

// ============================================
// DATABASE CONNECTION
// ============================================
function getDB(): PDO
{
    static $pdo = null;

    if ($pdo === null) {
        $dsn = sprintf(
            'mysql:host=%s;dbname=%s;charset=%s',
            DB_HOST,
            DB_NAME,
            DB_CHARSET
        );

        $options = [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ];

        try {
            $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
        } catch (PDOException $e) {
            if (ENVIRONMENT === 'production') {
                die('Database connection error. Please try again later.');
            } else {
                die('Database connection failed: ' . $e->getMessage());
            }
        }
    }

    return $pdo;
}

// ============================================
// SETTINGS HELPER
// ============================================
function getSetting(string $key, string $default = ''): string
{
    static $settings = null;

    if ($settings === null) {
        try {
            $stmt = getDB()->query("SELECT setting_key, setting_value FROM settings");
            $settings = [];
            while ($row = $stmt->fetch()) {
                $settings[$row['setting_key']] = $row['setting_value'];
            }
        } catch (Exception $e) {
            $settings = [];
        }
    }

    return $settings[$key] ?? $default;
}
