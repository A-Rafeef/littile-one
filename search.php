<?php

/**
 * Search Results
 * Kids Store v5
 */

require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/functions.php';

$query = trim($_GET['q'] ?? '');
$pageTitle = $query ? 'Search results for "' . $query . '"' : 'Search Products';

// Fetch products based on search query
$db = getDB();
$products = [];

if ($query) {
    // Search in product name and category name
    $sql = "SELECT p.*, c.name as category_name, c.slug as category_slug 
            FROM products p 
            JOIN categories c ON p.category_id = c.id 
            WHERE (p.name LIKE ? OR c.name LIKE ?) 
            AND p.is_available = 1 
            ORDER BY p.created_at DESC";
    $stmt = $db->prepare($sql);
    $stmt->execute(['%' . $query . '%', '%' . $query . '%']);
    $products = $stmt->fetchAll();

    // Expand variants for results
    $products = expandProductVariants($products);
}

require_once __DIR__ . '/includes/header.php';
?>

<div class="container h-padding-top h-padding-bottom">
    <div class="section-header">
        <h1 class="section-title">
            <?php if ($query): ?>
                Search results for "<?= e($query) ?>"
            <?php else: ?>
                Browse Products
            <?php endif; ?>
        </h1>
        <span class="results-count"><?= count($products) ?> products found</span>
    </div>

    <?php if (empty($products)): ?>
        <div class="empty-state">
            <div class="empty-state-icon">🔍</div>
            <h3>No results found</h3>
            <p>Try searching for something else or browse our categories.</p>
            <a href="<?= SITE_URL ?>/categories.php" class="btn btn-primary mt-24">View Categories</a>
        </div>
    <?php else: ?>
        <div class="product-grid-premium">
            <?php foreach ($products as $product): ?>
                <a href="<?= SITE_URL ?>/product/<?= e($product['slug']) ?><?= $product['link_params'] ?? '' ?>" class="product-card-premium">
                    <div class="product-img">
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
                    <div class="product-info-premium">
                        <h3 class="product-title"><?= e($product['name']) ?></h3>
                        <span class="product-price"><?= formatPrice($product['display_price']) ?></span>
                    </div>
                </a>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<style>
    .h-padding-top {
        padding-top: 48px;
    }

    .h-padding-bottom {
        padding-bottom: 48px;
    }

    .results-count {
        display: block;
        color: var(--text-muted);
        font-size: 0.9rem;
        margin-top: 8px;
    }

    .mt-24 {
        margin-top: 24px;
    }
</style>

<?php require_once __DIR__ . '/includes/footer.php'; ?>