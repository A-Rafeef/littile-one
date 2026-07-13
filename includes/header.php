<?php

/**
 * Public Header
 * Kids Store v5
 */

if (session_status() === PHP_SESSION_NONE) {
    session_name(SESSION_NAME);
    session_start();
}

$storeName = getSetting('store_name', DEFAULT_STORE_NAME);
$categories = getCategories();
$currentSlug = $_GET['slug'] ?? '';
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <?php
    // Fix 10: Avoid "Store | Store" duplication
    $titleTag = isset($pageTitle) && trim($pageTitle) !== trim($storeName)
        ? e($pageTitle) . ' | ' . e($storeName)
        : e($storeName);
    ?>
    <title><?= $titleTag ?></title>
    <?php if (isset($metaDescription)): ?>
        <meta name="description" content="<?= e($metaDescription) ?>">
    <?php endif; ?>
    <?php if (isset($canonicalUrl)): ?>
        <link rel="canonical" href="<?= e($canonicalUrl) ?>">
    <?php endif; ?>

    <!-- Fix 11: Open Graph / Social Meta -->
    <meta property="og:type" content="website">
    <meta property="og:title" content="<?= $titleTag ?>">
    <?php if (isset($metaDescription)): ?>
        <meta property="og:description" content="<?= e($metaDescription) ?>">
    <?php endif; ?>
    <?php if (isset($canonicalUrl)): ?>
        <meta property="og:url" content="<?= e($canonicalUrl) ?>">
    <?php endif; ?>
    <meta property="og:image" content="<?= SITE_URL ?>/assets/img/logo.png">
    <meta property="og:site_name" content="<?= e($storeName) ?>">
    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:title" content="<?= $titleTag ?>">
    <?php if (isset($metaDescription)): ?>
        <meta name="twitter:description" content="<?= e($metaDescription) ?>">
    <?php endif; ?>
    <meta name="twitter:image" content="<?= SITE_URL ?>/assets/img/logo.png">

    <!-- Fix 12: Favicon -->
    <link rel="icon" href="<?= SITE_URL ?>/assets/img/logo.png" type="image/png">
    <link rel="apple-touch-icon" href="<?= SITE_URL ?>/assets/img/logo.png">

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?= SITE_URL ?>/assets/css/style.css?v=<?= filemtime(__DIR__ . '/../assets/css/style.css') ?>">
    <script>
        window.siteUrl = <?= json_encode(SITE_URL) ?>;
    </script>
</head>

<body>
    <!-- Creative Page Loader -->
    <div id="pageLoader" class="page-loader">
        <div class="loader-content">
            <div class="loader-dots">
                <div class="dot dot-1"></div>
                <div class="dot dot-2"></div>
                <div class="dot dot-3"></div>
            </div>
            <div class="loader-text">Loading Fun...</div>
        </div>
    </div>
    <script>
        window.addEventListener('load', function() {
            const loader = document.getElementById('pageLoader');
            if (loader) {
                // Add a small delay for smoother transition
                setTimeout(() => {
                    loader.classList.add('hidden');
                    // Remove from DOM after transition
                    setTimeout(() => loader.remove(), 500);
                }, 300);
            }
        });
    </script>

    <header class="header">
        <div class="container header-grid">
            <a href="<?= SITE_URL ?>" class="logo">
                <img src="<?= SITE_URL ?>/assets/img/logo.png" alt="<?= e($storeName) ?>" class="logo-img">
                <span class="logo-text"><?= e($storeName) ?></span>
            </a>

            <!-- Desktop Navigation -->
            <nav class="desktop-nav">
                <a href="<?= SITE_URL ?>" class="nav-link <?= empty($currentSlug) && basename($_SERVER['PHP_SELF']) === 'index.php' ? 'active' : '' ?>">Home</a>
                <?php foreach (array_slice($categories, 0, 5) as $cat): ?>
                    <a href="<?= SITE_URL ?>/category/<?= e($cat['slug']) ?>" class="nav-link <?= $currentSlug === $cat['slug'] ? 'active' : '' ?>"><?= e($cat['name']) ?></a>
                <?php endforeach; ?>
                <a href="<?= SITE_URL ?>/categories.php" class="nav-link">More</a>
            </nav>

            <div class="header-actions">

                <a href="<?= SITE_URL ?>/wishlist" class="header-action-btn wishlist-header-btn" id="wishlistHeaderBtn" aria-label="Wishlist">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="svg-icon">
                        <path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z" />
                    </svg>
                    <span class="wishlist-badge" id="wishlistBadge" style="display: none;">0</span>
                </a>
                <button class="header-action-btn search-toggle" id="searchToggle" aria-label="Toggle Search">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="svg-icon">
                        <circle cx="11" cy="11" r="8"></circle>
                        <line x1="21" y1="21" x2="16.65" y2="16.65"></line>
                    </svg>
                </button>
                <button class="nav-toggle" id="navToggle" aria-label="Toggle navigation">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="svg-icon">
                        <line x1="3" y1="12" x2="21" y2="12"></line>
                        <line x1="3" y1="6" x2="21" y2="6"></line>
                        <line x1="3" y1="18" x2="21" y2="18"></line>
                    </svg>
                </button>
            </div>
        </div>

        <!-- Classic Search Bar (Drawer Effect) -->
        <div class="header-search-bar" id="searchBar">
            <div class="container">
                <form action="<?= SITE_URL ?>/search.php" method="GET" class="search-form-refined">
                    <input type="text" name="q" placeholder="Search products..." value="<?= e($_GET['q'] ?? '') ?>" id="searchInput">
                    <button type="submit" class="search-submit">Search</button>
                </form>
            </div>
        </div>

        <!-- Mobile Navigation Overlay -->
        <div class="mobile-nav-overlay" id="mobileNav">
            <div class="mobile-nav-content">
                <div class="mobile-nav-header">
                    <span class="logo-text">Menu</span>
                    <button class="close-nav" id="closeNav">&times;</button>
                </div>
                <div class="mobile-nav-links">
                    <!-- Fix 16: active state on mobile nav -->
                    <a href="<?= SITE_URL ?>" class="mobile-nav-link <?= empty($currentSlug) && basename($_SERVER['PHP_SELF']) === 'index.php' ? 'mobile-nav-active' : '' ?>"><?= e($storeName) ?> Home</a>
                    <?php foreach ($categories as $cat): ?>
                        <a href="<?= SITE_URL ?>/category/<?= e($cat['slug']) ?>" class="mobile-nav-link <?= $currentSlug === $cat['slug'] ? 'mobile-nav-active' : '' ?>"><?= e($cat['name']) ?></a>
                    <?php endforeach; ?>
                    <a href="<?= SITE_URL ?>/categories.php" class="mobile-nav-link">All Categories</a>
                    <a href="<?= SITE_URL ?>/wishlist" class="mobile-nav-link">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="svg-icon-sm">
                            <path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z" />
                        </svg>
                        My Wishlist
                    </a>
                    <div class="mobile-nav-divider"></div>
                    <a href="https://wa.me/<?= e(getSetting('whatsapp_number')) ?>" target="_blank" class="mobile-nav-link action-link">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="svg-icon-sm">
                            <path d="M21 11.5a8.38 8.38 0 0 1-.9 3.8 8.5 8.5 0 0 1-7.6 4.7 8.38 8.38 0 0 1-3.8-.9L3 21l1.9-5.7a8.38 8.38 0 0 1-.9-3.8 8.5 8.5 0 0 1 4.7-7.6 8.38 8.38 0 0 1 3.8-.9h.5a8.48 8.48 0 0 1 8 8v.5z"></path>
                        </svg>
                        WhatsApp
                    </a>
                    <a href="https://www.instagram.com/<?= e(getSetting('instagram_url', 'little__one_baby_shop_')) ?>" target="_blank" class="mobile-nav-link action-link">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="svg-icon-sm">
                            <rect x="2" y="2" width="20" height="20" rx="5" ry="5"></rect>
                            <path d="M16 11.37A4 4 0 1 1 12.63 8 4 4 0 0 1 16 11.37z"></path>
                            <line x1="17.5" y1="6.5" x2="17.51" y2="6.5"></line>
                        </svg>
                        Instagram
                    </a>
                </div>
            </div>
        </div>
    </header>

    <main class="main">