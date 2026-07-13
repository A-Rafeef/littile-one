<?php

/**
 * Admin Login Page
 * Kids Store v5
 */

require_once __DIR__ . '/includes/auth.php';

// Redirect if already logged in
if (isLoggedIn()) {
    redirect(ADMIN_URL . '/dashboard.php');
}

$error = '';

// Handle login form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrf($_POST['csrf_token'] ?? '')) {
        $error = 'Invalid security token';
    } else {
        $username = trim($_POST['username'] ?? '');
        $password = $_POST['password'] ?? '';

        if (empty($username) || empty($password)) {
            $error = 'Please enter username and password';
        } else {
            $stmt = getDB()->prepare("SELECT id, password_hash FROM admins WHERE username = ?");
            $stmt->execute([$username]);
            $admin = $stmt->fetch();

            if ($admin && password_verify($password, $admin['password_hash'])) {
                $_SESSION['admin_id'] = $admin['id'];
                redirect(ADMIN_URL . '/dashboard.php');
            } else {
                $error = 'Invalid username or password';
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Login | Kids Store</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?= ADMIN_URL ?>/assets/css/admin.css">
</head>

<body class="login-page">
    <div class="login-container">
        <div class="login-card">
            <div class="login-header">
                <img src="<?= SITE_URL ?>/assets/img/logo.png" alt="Kids Store" class="login-logo-img">
                <h1>Kids Store</h1>
                <p>Admin Panel</p>
            </div>

            <?php if ($error): ?>
                <div class="alert alert-danger"><?= e($error) ?></div>
            <?php endif; ?>

            <form method="POST" class="login-form">
                <?= csrfField() ?>
                <div class="form-group">
                    <label for="username">Username</label>
                    <input type="text" id="username" name="username"
                        value="<?= e($_POST['username'] ?? '') ?>"
                        placeholder="Enter username" required autofocus>
                </div>

                <div class="form-group">
                    <label for="password">Password</label>
                    <input type="password" id="password" name="password"
                        placeholder="Enter password" required>
                </div>

                <button type="submit" class="btn btn-primary btn-block">
                    Login
                </button>
            </form>

            <div class="login-footer">
                <a href="<?= SITE_URL ?>">← Back to Store</a>
            </div>
        </div>
    </div>
</body>

</html>