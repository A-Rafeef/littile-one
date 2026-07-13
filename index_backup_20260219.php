<?php

/**
 * Homepage
 * Kids Store v5
 */

require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/functions.php';

// Page meta
$pageTitle = getSetting('meta_title', 'Kids Store');
$metaDescription = getSetting('meta_description', 'Shop the best kids products');
$canonicalUrl = SITE_URL;

// Get featured products
$featuredProducts = getProducts(['is_featured' => true, 'is_available' => true, 'limit' => 8]);
$featuredProducts = expandProductVariants($featuredProducts);

// Get latest products
$latestProducts = getProducts(['is_available' => true, 'limit' => 8]);
$latestProducts = expandProductVariants($latestProducts);

// Get categories
$categories = getCategories();

require_once __DIR__ . '/includes/header.php';
?>
<?php
// Get active banners
$banners = getBanners(true);
?>

<div class="container">
    <!-- Hero Banner Section -->
    <?php if (!empty($banners)): ?>
        <section class="hero-slider">
            <?php foreach ($banners as $banner): ?>
                <div class="hero-banner" style="background-image: linear-gradient(to right, rgba(0,0,0,0.1), rgba(0,0,0,0.05)), url('<?= imageUrl($banner['image_path']) ?>');">
                    <div class="hero-content">
                        <?php if ($banner['subtitle']): ?>
                            <span class="hero-subtitle"><?= e($banner['subtitle']) ?></span>
                        <?php endif; ?>
                        <h1 class="hero-title"><?= e($banner['title']) ?></h1>
                        <?php if ($banner['button_text']): ?>
                            <a href="<?= SITE_URL ?>/<?= e($banner['button_url']) ?>" class="btn btn-hero"><?= e($banner['button_text']) ?></a>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
            <div class="hero-dots"></div>
        </section>
    <?php endif; ?>

    <!-- Categories Circular Section -->
    <?php if (!empty($categories)): ?>
        <section class="mb-48">
            <div class="section-header">
                <h2 class="section-title">Shop by Category</h2>
                <a href="<?= SITE_URL ?>/categories.php" class="view-all">See All</a>
            </div>

            <div class="category-circles">
                <?php foreach ($categories as $cat): ?>
                    <a href="<?= SITE_URL ?>/category/<?= e($cat['slug']) ?>" class="category-circle-item">
                        <div class="category-circle-img">
                            <?php if ($cat['image']): ?>
                                <img src="<?= imageUrl($cat['image']) ?>" alt="<?= e($cat['name']) ?>" loading="lazy" width="120" height="120">
                            <?php else: ?>
                                <div class="category-placeholder"><?= strtoupper(substr($cat['name'], 0, 1)) ?></div>
                            <?php endif; ?>
                        </div>
                        <span class="category-circle-name"><?= e($cat['name']) ?></span>
                    </a>
                <?php endforeach; ?>
            </div>
        </section>
    <?php endif; ?>

    <!-- Featured Products -->
    <?php if (!empty($featuredProducts)): ?>
        <section class="mb-48">
            <div class="section-header">
                <h2 class="section-title">Featured</h2>
            </div>

            <div class="product-grid-premium">
                <?php foreach ($featuredProducts as $product): ?>
                    <a href="<?= SITE_URL ?>/product/<?= e($product['slug']) ?><?= $product['link_params'] ?? '' ?>" class="product-card-premium" data-product-id="<?= $product['id'] ?>" data-product-name="<?= e($product['name']) ?>" data-product-image="<?= !empty($product['display_image']) ? imageUrl($product['display_image']) : '' ?>" data-product-price="<?= $product['display_price'] ?>">
                        <div class="product-img">
                            <?php if (!empty($product['display_image'])): ?>
                                <img src="<?= imageUrl($product['display_image']) ?>" alt="<?= e($product['name']) ?>" loading="lazy" width="400" height="400">
                            <?php else: ?>
                                <img src="<?= SITE_URL ?>/assets/img/placeholder.png" alt="<?= e($product['name']) ?>" loading="lazy" width="400" height="400">
                            <?php endif; ?>
                        </div>
                        <div class="product-info-premium">
                            <h3 class="product-title"><?= e($product['name']) ?></h3>
                            <div class="product-meta">
                                <span class="product-cat"><?= e($product['category_name']) ?></span>
                            </div>
                            <div class="product-footer">
                                <span class="product-price"><?= formatPrice($product['display_price']) ?></span>
                                <span class="add-btn">ADD</span>
                            </div>
                        </div>
                    </a>
                <?php endforeach; ?>
            </div>
        </section>
    <?php elseif (!empty($latestProducts)): // Show placeholder if Latest exists but Featured is empty 
    ?>
        <section class="mb-48">
            <div class="section-header">
                <h2 class="section-title">Featured</h2>
            </div>
            <div class="text-center py-5 text-muted">
                <p>Check back soon for new featured items.</p>
            </div>
        </section>
    <?php endif; ?>

    <!-- New Arrivals -->

    <?php if (!empty($latestProducts)): ?>
        <section class="mb-48">
            <div class="section-header">
                <h2 class="section-title">New Arrivals</h2>
                <a href="<?= SITE_URL ?>/categories.php" class="view-all">View All</a>
            </div>

            <div class="product-grid-premium">
                <?php foreach ($latestProducts as $product): ?>
                    <a href="<?= SITE_URL ?>/product/<?= e($product['slug']) ?><?= $product['link_params'] ?? '' ?>" class="product-card-premium" data-product-id="<?= $product['id'] ?>" data-product-name="<?= e($product['name']) ?>" data-product-image="<?= !empty($product['display_image']) ? imageUrl($product['display_image']) : '' ?>" data-product-price="<?= $product['display_price'] ?>">
                        <div class="product-img">
                            <?php if (!empty($product['display_image'])): ?>
                                <img src="<?= imageUrl($product['display_image']) ?>" alt="<?= e($product['name']) ?>" loading="lazy" width="400" height="400">
                            <?php else: ?>
                                <img src="<?= SITE_URL ?>/assets/img/placeholder.png" alt="<?= e($product['name']) ?>" loading="lazy" width="400" height="400">
                            <?php endif; ?>
                        </div>
                        <div class="product-info-premium">
                            <h3 class="product-title"><?= e($product['name']) ?></h3>
                            <div class="product-meta">
                                <span class="product-cat"><?= e($product['category_name']) ?></span>
                            </div>
                            <div class="product-footer">
                                <span class="product-price"><?= formatPrice($product['display_price']) ?></span>
                                <span class="add-btn">ADD</span>
                            </div>
                        </div>
                    </a>
                <?php endforeach; ?>
            </div>
        </section>
    <?php elseif (!empty($featuredProducts)): // Show placeholder if Featured exists but Latest is empty 
    ?>
        <section class="mb-48">
            <div class="section-header">
                <h2 class="section-title">New Arrivals</h2>
            </div>
            <div class="text-center py-5 text-muted">
                <p>New arrivals coming soon!</p>
            </div>
        </section>
    <?php endif; ?>

    <!-- Recently Viewed (Added via JS) -->
    <section class="mb-48" id="recentlyViewedSection" style="display: none;">
        <div class="section-header">
            <h2 class="section-title">Recently Viewed</h2>
        </div>
        <div class="product-grid-premium" id="recentlyViewedGrid"></div>
    </section>



    <?php if (empty($featuredProducts) && empty($latestProducts)): ?>
        <div class="empty-state">
            <div class="empty-state-icon">🛍️</div>
            <h3>No Products Yet</h3>
            <p>Check back soon for amazing kids products!</p>
        </div>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>