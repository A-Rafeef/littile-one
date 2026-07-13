<?php

/**
 * All Categories
 * Kids Store v5
 */

require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/functions.php';

$pageTitle = 'All Categories';
$categories = getCategories();

require_once __DIR__ . '/includes/header.php';
?>

<div class="container h-padding-top h-padding-bottom">
    <div class="section-header">
        <h1 class="section-title">Shop by Category</h1>
    </div>

    <div class="category-grid-full">
        <?php foreach ($categories as $cat): ?>
            <a href="<?= SITE_URL ?>/category/<?= e($cat['slug']) ?>" class="category-card-full">
                <div class="category-img">
                    <?php if ($cat['image']): ?>
                        <img src="<?= imageUrl($cat['image']) ?>" alt="<?= e($cat['name']) ?>">
                    <?php else: ?>
                        <div class="category-placeholder">🧸</div>
                    <?php endif; ?>
                </div>
                <div class="category-info">
                    <h3><?= e($cat['name']) ?></h3>
                    <?php
                    // Count products in this category (optional, keeping it simple for now)
                    ?>
                </div>
            </a>
        <?php endforeach; ?>
    </div>
</div>

<style>
    .category-grid-full {
        display: grid;
        grid-template-columns: repeat(2, 1fr);
        gap: 16px;
    }

    @media (min-width: 768px) {
        .category-grid-full {
            grid-template-columns: repeat(3, 1fr);
        }
    }

    @media (min-width: 992px) {
        .category-grid-full {
            grid-template-columns: repeat(4, 1fr);
        }
    }

    .category-card-full {
        background: white;
        border-radius: 20px;
        overflow: hidden;
        box-shadow: var(--shadow-sm);
        transition: var(--transition);
    }

    .category-card-full:hover {
        transform: translateY(-8px);
        box-shadow: var(--shadow-md);
    }

    .category-card-full .category-img {
        aspect-ratio: 4/3;
        background: #f8f8f8;
        display: flex;
        align-items: center;
        justify-content: center;
    }

    .category-card-full .category-img img {
        width: 100%;
        height: 100%;
        object-fit: cover;
    }

    .category-card-full .category-info {
        padding: 16px;
        text-align: center;
    }

    .category-card-full h3 {
        font-size: 1.1rem;
        font-weight: 700;
    }

    .h-padding-top {
        padding-top: 48px;
    }

    .h-padding-bottom {
        padding-bottom: 48px;
    }
</style>

<?php require_once __DIR__ . '/includes/footer.php'; ?>