<?php

/**
 * Store Settings
 * Kids Store v5 Admin
 */

require_once __DIR__ . '/includes/auth.php';
requireLogin();

$pageTitle = 'Settings';
$db = getDB();

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (verifyCsrf($_POST['csrf_token'] ?? '')) {
        $settings = [
            'store_name' => trim($_POST['store_name'] ?? ''),
            'whatsapp_number' => preg_replace('/[^0-9]/', '', $_POST['whatsapp_number'] ?? ''),
            'currency_symbol' => trim($_POST['currency_symbol'] ?? '₹'),
            'meta_title' => trim($_POST['meta_title'] ?? ''),
            'meta_description' => trim($_POST['meta_description'] ?? ''),
            'instagram_url' => trim($_POST['instagram_url'] ?? '')
        ];

        foreach ($settings as $key => $value) {
            if (DB_DRIVER === 'pgsql') {
                $stmt = $db->prepare("INSERT INTO settings (setting_key, setting_value) VALUES (?, ?) 
                                      ON CONFLICT (setting_key) DO UPDATE SET setting_value = EXCLUDED.setting_value");
                $stmt->execute([$key, $value]);
            } else {
                $stmt = $db->prepare("INSERT INTO settings (setting_key, setting_value) VALUES (?, ?) 
                                      ON DUPLICATE KEY UPDATE setting_value = ?");
                $stmt->execute([$key, $value, $value]);
            }
        }

        setFlash('success', 'Settings saved successfully');
        redirect(ADMIN_URL . '/settings.php');
    }
}

// Load current settings
$settings = [];
$stmt = $db->query("SELECT setting_key, setting_value FROM settings");
while ($row = $stmt->fetch()) {
    $settings[$row['setting_key']] = $row['setting_value'];
}

require_once __DIR__ . '/includes/header.php';
?>

<div class="page-header">
    <h1>Store Settings</h1>
    <p>Configure your store settings</p>
</div>

<form method="POST">
    <?= csrfField() ?>

    <div class="form-grid">
        <!-- General Settings -->
        <div class="card">
            <div class="card-header">
                <h2>General Settings</h2>
            </div>
            <div class="card-body">
                <div class="form-group">
                    <label for="store_name">Store Name</label>
                    <input type="text" id="store_name" name="store_name"
                        value="<?= e($settings['store_name'] ?? 'Kids Store') ?>">
                </div>

                <div class="form-group">
                    <label for="whatsapp_number">WhatsApp Number</label>
                    <input type="text" id="whatsapp_number" name="whatsapp_number"
                        value="<?= e($settings['whatsapp_number'] ?? '') ?>"
                        placeholder="e.g., 919876543210 (with country code, no +)">
                    <small>Enter the full number with country code but without + symbol</small>
                </div>

                <div class="form-group">
                    <label for="instagram_url">Instagram Username</label>
                    <input type="text" id="instagram_url" name="instagram_url"
                        value="<?= e($settings['instagram_url'] ?? '') ?>"
                        placeholder="e.g., little__one_baby_shop_">
                    <small>Username only (without @), used in header & footer links</small>
                </div>

                <div class="form-group">
                    <label for="currency_symbol">Currency Symbol</label>
                    <input type="text" id="currency_symbol" name="currency_symbol"
                        value="<?= e($settings['currency_symbol'] ?? '₹') ?>" maxlength="5">
                </div>
            </div>
        </div>

        <!-- SEO Settings -->
        <div class="card">
            <div class="card-header">
                <h2>Default SEO Settings</h2>
            </div>
            <div class="card-body">
                <div class="form-group">
                    <label for="meta_title">Default Meta Title</label>
                    <input type="text" id="meta_title" name="meta_title"
                        value="<?= e($settings['meta_title'] ?? '') ?>" maxlength="160">
                    <small>Used on homepage and pages without custom meta title</small>
                </div>

                <div class="form-group">
                    <label for="meta_description">Default Meta Description</label>
                    <textarea id="meta_description" name="meta_description"
                        rows="3" maxlength="320"><?= e($settings['meta_description'] ?? '') ?></textarea>
                </div>
            </div>
        </div>
    </div>

    <!-- Admin Account -->
    <div class="card mt-24">
        <div class="card-header">
            <h2>Admin Account</h2>
        </div>
        <div class="card-body">
            <p class="mb-16">Currently logged in as: <strong><?= e(getCurrentAdmin()['username'] ?? 'admin') ?></strong></p>
            <a href="<?= ADMIN_URL ?>/change-password.php" class="btn">Change Password</a>
        </div>
    </div>

    <div class="form-actions mt-24">
        <button type="submit" class="btn btn-primary btn-lg">Save Settings</button>
    </div>
</form>

<?php require_once __DIR__ . '/includes/footer.php'; ?>