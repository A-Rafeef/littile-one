<?php

/**
 * Ultimate Cache Clearing Utility
 * Upload this to your server root and visit: yourdomain.com/clear_cache.php
 */

// 1. Send Headers to Force Browser to Re-Check Content
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");
header("Expires: Sat, 26 Jul 1997 05:00:00 GMT"); // Date in the past

// 2. Clear Server-Side OPcache (The most common issue for code changes not showing)
$opcacheResult = false;
if (function_exists('opcache_reset')) {
    $opcacheResult = opcache_reset();
}

// 3. Clear Realpath Cache (File existence checks)
clearstatcache(true);

?>
<!DOCTYPE html>
<html>

<head>
    <title>Website Cache Cleaner</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <style>
        body {
            font-family: system-ui, -apple-system, sans-serif;
            padding: 20px;
            max-width: 600px;
            margin: 0 auto;
            background: #f3f4f6;
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 100vh;
        }

        .card {
            background: white;
            padding: 32px;
            border-radius: 16px;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
            width: 100%;
            text-align: center;
        }

        h1 {
            margin-top: 0;
            color: #111;
            font-size: 24px;
        }

        .status-box {
            margin: 24px 0;
            padding: 20px;
            background: #ECFDF5;
            border: 1px solid #D1FAE5;
            border-radius: 8px;
            color: #065F46;
        }

        .status-icon {
            font-size: 48px;
            display: block;
            margin-bottom: 8px;
        }

        .btn {
            display: inline-block;
            background: #2563EB;
            color: white;
            padding: 12px 24px;
            border-radius: 8px;
            text-decoration: none;
            font-weight: 600;
            margin-top: 16px;
            transition: background 0.2s;
        }

        .btn:hover {
            background: #1D4ED8;
        }

        .note {
            color: #6B7280;
            font-size: 14px;
            margin-top: 24px;
            border-top: 1px solid #eee;
            padding-top: 16px;
        }
    </style>
</head>

<body>
    <div class="card">
        <h1>🚀 Cache Cleared Successfully</h1>

        <div class="status-box">
            <span class="status-icon">✅</span>
            <div><strong>Server Code Cache (OPCache)</strong>: <?php echo $opcacheResult ? 'Reset' : 'Skipped (Not Active)'; ?></div>
            <div style="margin-top:4px"><strong>File Status Cache</strong>: Reset</div>
        </div>

        <p>The server has been told to drop all cached code files.</p>

        <a href="admin/category-edit.php?v=<?php echo time(); ?>" class="btn">
            Go to Category Edit Page (Fresh Load)
        </a>

        <div class="note">
            <strong>Still seeing old version?</strong><br>
            Your <em>browser</em> might still be holding the old file.<br>
            Press <strong>Ctrl + Shift + R</strong> (Windows) or <strong>Cmd + Shift + R</strong> (Mac) on the admin page.
        </div>
    </div>
</body>

</html>