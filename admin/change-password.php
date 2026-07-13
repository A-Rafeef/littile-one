<?php

/**
 * Change Password
 * Kids Store v5 Admin
 */

require_once __DIR__ . '/includes/auth.php';
requireLogin();

$pageTitle = 'Change Password';
$errors = [];
$success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrf($_POST['csrf_token'] ?? '')) {
        $errors[] = 'Invalid security token';
    } else {
        $currentPassword = $_POST['current_password'] ?? '';
        $newPassword = $_POST['new_password'] ?? '';
        $confirmPassword = $_POST['confirm_password'] ?? '';

        // Get current admin
        $stmt = getDB()->prepare("SELECT password_hash FROM admins WHERE id = ?");
        $stmt->execute([$_SESSION['admin_id']]);
        $admin = $stmt->fetch();

        if (!$admin || !password_verify($currentPassword, $admin['password_hash'])) {
            $errors[] = 'Current password is incorrect';
        }

        if (strlen($newPassword) < 6) {
            $errors[] = 'New password must be at least 6 characters';
        }

        if ($newPassword !== $confirmPassword) {
            $errors[] = 'New passwords do not match';
        }

        if (empty($errors)) {
            $newHash = password_hash($newPassword, PASSWORD_DEFAULT);
            $stmt = getDB()->prepare("UPDATE admins SET password_hash = ? WHERE id = ?");
            $stmt->execute([$newHash, $_SESSION['admin_id']]);

            setFlash('success', 'Password changed successfully');
            redirect(ADMIN_URL . '/settings.php');
        }
    }
}

require_once __DIR__ . '/includes/header.php';
?>

<div class="page-header">
    <div>
        <h1>Change Password</h1>
        <p>Update your admin password</p>
    </div>
    <a href="<?= ADMIN_URL ?>/settings.php" class="btn">← Back to Settings</a>
</div>

<?php if (!empty($errors)): ?>
    <div class="alert alert-danger">
        <ul class="error-list">
            <?php foreach ($errors as $error): ?>
                <li><?= e($error) ?></li>
            <?php endforeach; ?>
        </ul>
    </div>
<?php endif; ?>

<div class="card" style="max-width: 500px;">
    <div class="card-body">
        <form method="POST">
            <?= csrfField() ?>

            <div class="form-group">
                <label for="current_password">Current Password</label>
                <input type="password" id="current_password" name="current_password" required>
            </div>

            <div class="form-group">
                <label for="new_password">New Password</label>
                <input type="password" id="new_password" name="new_password" required minlength="6">
                <small>Minimum 6 characters</small>
            </div>

            <div class="form-group">
                <label for="confirm_password">Confirm New Password</label>
                <input type="password" id="confirm_password" name="confirm_password" required>
            </div>

            <button type="submit" class="btn btn-primary">Change Password</button>
        </form>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>