<?php

/**
 * Category Page
 * Kids Store v5
 */

require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/functions.php';

// Get category slug
$slug = $_GET['slug'] ?? '';
if (empty($slug)) {
    redirect(SITE_URL);
}

// Get category
$category = getCategoryBySlug($slug);
if (!$category || !$category['is_active']) {
    http_response_code(404);
    $pageTitle = 'Category Not Found';
    require_once __DIR__ . '/includes/header.php';
    echo '<div class="container"><div class="empty-state"><div class="empty-state-icon">😕</div><h3>Category Not Found</h3><p>The category you\'re looking for doesn\'t exist.</p></div></div>';
    require_once __DIR__ . '/includes/footer.php';
    exit;
}

// Page meta
$pageTitle = $category['meta_title'] ?: $category['name'];
$metaDescription = $category['meta_description'] ?: $category['description'];
$canonicalUrl = SITE_URL . '/category/' . $category['slug'];

// Get products in this category
// Get products in this category
$filters = [
    'category_id' => $category['id'],
    'is_available' => true,
    'size' => $_GET['size'] ?? '',
    'age' => $_GET['age'] ?? '',
    'color' => $_GET['color'] ?? ''
];

$products = getProducts($filters);

$products = expandProductVariants($products);

require_once __DIR__ . '/includes/header.php';
?>

<div class="container">
    <!-- Breadcrumbs -->
    <nav class="breadcrumbs">
        <a href="<?= SITE_URL ?>">Home</a>
        <span>›</span>
        <span class="current"><?= e($category['name']) ?></span>
    </nav>

    <!-- Category Header -->
    <section class="mb-32">
        <div class="section-header">
            <h1 class="section-title"><?= e($category['name']) ?></h1>
        </div>

        <?php if ($category['description']): ?>
            <p class="text-light mb-24"><?= e($category['description']) ?></p>
        <?php endif; ?>
    </section>

    <!-- SMART FILTERS -->
    <?php
    $filters = getCategoryFilters($category['id']);
    $activeSize = $_GET['size'] ?? '';
    $activeAge = $_GET['age'] ?? '';
    $activeColor = $_GET['color'] ?? '';

    $hasActiveFilters = $activeSize || $activeAge || $activeColor;
    ?>

    <div class="filters-section mb-32">
        <form action="" method="GET" id="filterForm">
            <!-- Keep existing params if any, but base url is cleaner -->

            <?php if (!empty($filters['ages'])): ?>
                <div class="filter-group mb-16">
                    <h3 class="filter-title">Age</h3>
                    <div class="filter-pills">
                        <label class="filter-pill <?= $activeAge === '' ? 'active' : '' ?>">
                            <input type="radio" name="age" value="" onchange="this.form.submit()" <?= $activeAge === '' ? 'checked' : '' ?> class="hidden">
                            All
                        </label>
                        <?php foreach ($filters['ages'] as $age): ?>
                            <label class="filter-pill <?= $activeAge === $age ? 'active' : '' ?>">
                                <input type="radio" name="age" value="<?= e($age) ?>" onchange="this.form.submit()" <?= $activeAge === $age ? 'checked' : '' ?> class="hidden">
                                <?= e($age) ?>
                            </label>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endif; ?>

            <?php if (!empty($filters['sizes'])): ?>
                <div class="filter-group mb-16">
                    <h3 class="filter-title">Size</h3>
                    <div class="filter-pills">
                        <label class="filter-pill <?= $activeSize === '' ? 'active' : '' ?>">
                            <input type="radio" name="size" value="" onchange="this.form.submit()" <?= $activeSize === '' ? 'checked' : '' ?> class="hidden">
                            All
                        </label>
                        <?php foreach ($filters['sizes'] as $size): ?>
                            <label class="filter-pill <?= $activeSize === $size ? 'active' : '' ?>">
                                <input type="radio" name="size" value="<?= e($size) ?>" onchange="this.form.submit()" <?= $activeSize === $size ? 'checked' : '' ?> class="hidden">
                                <?= e($size) ?>
                            </label>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endif; ?>

            <?php if ($hasActiveFilters): ?>
                <div class="mt-8">
                    <a href="<?= SITE_URL ?>/category/<?= e($category['slug']) ?>" class="text-sm text-primary">Clear Filters</a>
                </div>
            <?php endif; ?>
        </form>
    </div>

    <style>
        /* Inline Styles for Filters (Move to CSS later) */
        .filter-title {
            font-size: 0.9rem;
            font-weight: 700;
            color: var(--secondary);
            margin-bottom: 8px;
        }

        .filter-pills {
            display: flex;
            gap: 8px;
            overflow-x: auto;
            padding-bottom: 4px;
        }

        .filter-pill {
            padding: 6px 14px;
            border: 1px solid var(--border);
            border-radius: 20px;
            font-size: 0.85rem;
            cursor: pointer;
            white-space: nowrap;
            transition: all 0.2s;
            background: white;
        }

        .filter-pill.active {
            background: var(--secondary);
            color: white;
            border-color: var(--secondary);
        }

        .filter-pill:hover:not(.active) {
            border-color: var(--secondary);
        }

        .hidden {
            display: none;
        }
    </style>


    <!-- Products Grid -->
    <?php if (!empty($products)): ?>
        <div class="product-grid">
            <?php foreach ($products as $product): ?>
                <a href="<?= SITE_URL ?>/product/<?= e($product['slug']) ?><?= $product['link_params'] ?? '' ?>" class="product-card">
                    <div class="product-card-image">
                        <?php if (!empty($product['display_image'])): ?>
                            <img src="<?= imageUrl($product['display_image']) ?>" alt="<?= e($product['name']) ?>" loading="lazy">
                        <?php else: ?>
                            <img src="<?= SITE_URL ?>/assets/img/placeholder.png" alt="<?= e($product['name']) ?>" loading="lazy">
                        <?php endif; ?>
                        <button type="button" class="wishlist-btn" data-id="<?= $product['id'] ?>" data-name="<?= e($product['name']) ?>" data-slug="<?= e($product['slug']) ?>" data-price="<?= $product['display_price'] ?>" data-image="<?= !empty($product['display_image']) ? imageUrl($product['display_image']) : '' ?>" aria-label="Toggle wishlist">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="18" height="18">
                                <path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z" />
                            </svg>
                        </button>
                    </div>
                    <div class="product-card-body">
                        <h3 class="product-card-title"><?= e($product['name']) ?></h3>
                        <div class="product-card-price"><?= formatPrice($product['display_price']) ?></div>
                    </div>
                </a>
            <?php endforeach; ?>
        </div>
    <?php else: ?>
        <div class="empty-state">
            <div class="empty-state-icon">📦</div>
            <h3>No Products Yet</h3>
            <p>This category doesn't have any products at the moment. Check back later!</p>
        </div>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>