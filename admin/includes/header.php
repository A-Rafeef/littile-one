<?php

/**
 * Admin Header
 * Kids Store v5
 */

$currentAdmin = getCurrentAdmin();
$currentPage = basename($_SERVER['PHP_SELF'], '.php');
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= isset($pageTitle) ? e($pageTitle) . ' - Admin' : 'Admin Panel' ?> | Kids Store</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?= ADMIN_URL ?>/assets/css/admin.css?v=<?= filemtime(__DIR__ . '/../assets/css/admin.css') ?>">
</head>

<body>
    <div class="admin-layout">
        <!-- Sidebar -->
        <aside class="sidebar" id="sidebar">
            <div class="sidebar-header">
                <img src="<?= SITE_URL ?>/assets/img/logo.png" alt="Kids Store" class="sidebar-logo-img">
                <span class="sidebar-title">Kids Store</span>
            </div>

            <nav class="sidebar-nav">
                <a href="<?= ADMIN_URL ?>/dashboard.php" class="nav-item <?= $currentPage === 'dashboard' ? 'active' : '' ?>">
                    <span class="nav-icon">📊</span>
                    Dashboard
                </a>
                <a href="<?= ADMIN_URL ?>/categories.php" class="nav-item <?= in_array($currentPage, ['categories', 'category-edit']) ? 'active' : '' ?>">
                    <span class="nav-icon">📁</span>
                    Categories
                </a>
                <a href="<?= ADMIN_URL ?>/banners.php" class="nav-item <?= in_array($currentPage, ['banners', 'banner-edit']) ? 'active' : '' ?>">
                    <span class="nav-icon">🖼️</span>
                    Banners
                </a>
                <a href="<?= ADMIN_URL ?>/products.php" class="nav-item <?= in_array($currentPage, ['products', 'product-edit']) ? 'active' : '' ?>">
                    <span class="nav-icon">🛍️</span>
                    Products
                </a>
                <a href="<?= ADMIN_URL ?>/settings.php" class="nav-item <?= $currentPage === 'settings' ? 'active' : '' ?>">
                    <span class="nav-icon">⚙️</span>
                    Settings
                </a>
            </nav>

            <div class="sidebar-footer">
                <a href="<?= SITE_URL ?>" class="nav-item" target="_blank">
                    <span class="nav-icon">🌐</span>
                    View Store
                </a>
                <a href="<?= ADMIN_URL ?>/logout.php" class="nav-item">
                    <span class="nav-icon">🚪</span>
                    Logout
                </a>
            </div>
        </aside>

        <!-- Sidebar Backdrop (Mobile) -->
        <div class="sidebar-backdrop" id="sidebarBackdrop"></div>

        <!-- Main Content -->
        <div class="main-content">
            <header class="topbar">
                <button class="sidebar-toggle" id="sidebarToggle">☰</button>
                <div class="topbar-right">
                    <span class="admin-name">👤 <?= e($currentAdmin['username'] ?? 'Admin') ?></span>
                </div>
            </header>

            <main class="content">
                <?php if ($flash = getFlash()): ?>
                    <div class="alert alert-<?= e($flash['type']) ?>">
                        <?= e($flash['message']) ?>
                    </div>
                <?php endif; ?>