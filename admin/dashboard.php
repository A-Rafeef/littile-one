<?php

/**
 * Admin Dashboard
 * Kids Store v5
 */

require_once __DIR__ . '/includes/auth.php';
requireLogin();

$pageTitle = 'Dashboard';

// Get stats
$db = getDB();

$categoryCount = $db->query("SELECT COUNT(*) FROM categories")->fetchColumn();
$productCount = $db->query("SELECT COUNT(*) FROM products")->fetchColumn();
$variantCount = $db->query("SELECT COUNT(*) FROM product_variants")->fetchColumn();
$outOfStockCount = $db->query("SELECT COUNT(*) FROM products WHERE is_available = 0")->fetchColumn();

// Recent products
$recentProducts = $db->query("
    SELECT p.*, c.name as category_name 
    FROM products p 
    LEFT JOIN categories c ON p.category_id = c.id 
    ORDER BY p.created_at DESC 
    LIMIT 5
")->fetchAll();

require_once __DIR__ . '/includes/header.php';
?>

<div class="page-header">
    <h1>Dashboard</h1>
    <p>Welcome back! Here's an overview of your store.</p>
</div>

<!-- Standard Stats Grid -->
<div class="stats-grid mb-24">
    <div class="stat-card">
        <div class="stat-icon">📁</div>
        <div class="stat-info">
            <div class="stat-value"><?= $categoryCount ?></div>
            <div class="stat-label">Categories</div>
        </div>
    </div>

    <div class="stat-card">
        <div class="stat-icon">🛍️</div>
        <div class="stat-info">
            <div class="stat-value"><?= $productCount ?></div>
            <div class="stat-label">Products</div>
        </div>
    </div>

    <div class="stat-card">
        <div class="stat-icon">🎨</div>
        <div class="stat-info">
            <div class="stat-value"><?= $variantCount ?></div>
            <div class="stat-label">Variants</div>
        </div>
    </div>

    <div class="stat-card stat-warning">
        <div class="stat-icon">⚠️</div>
        <div class="stat-info">
            <div class="stat-value"><?= $outOfStockCount ?></div>
            <div class="stat-label">Out of Stock</div>
        </div>
    </div>
</div>


<!-- Shared: Quick Actions & Recent Products (Always visible for Growing/Power, simplified for New) -->
<!-- Quick Actions -->
<?php if (true): ?>
    <div class="card mb-24">
        <div class="card-header">
            <h2>Quick Actions</h2>
        </div>
        <div class="card-body">
            <div class="quick-actions">
                <a href="<?= ADMIN_URL ?>/product-edit.php" class="quick-action-btn">
                    <span>➕</span> Add Product
                </a>
                <a href="<?= ADMIN_URL ?>/category-edit.php" class="quick-action-btn">
                    <span>📁</span> Add Category
                </a>
                <a href="<?= ADMIN_URL ?>/product-fields.php" class="quick-action-btn">
                    <span>✨</span> Custom Fields
                </a>
                <a href="<?= ADMIN_URL ?>/settings.php" class="quick-action-btn">
                    <span>⚙️</span> Store Settings
                </a>
                <a href="<?= SITE_URL ?>" class="quick-action-btn" target="_blank">
                    <span>🌐</span> View Store
                </a>
            </div>
        </div>
    </div>

    <!-- Recent Products -->
    <div class="card">
        <div class="card-header">
            <h2>Recent Products</h2>
            <a href="<?= ADMIN_URL ?>/products.php" class="btn btn-sm">View All</a>
        </div>
        <div class="card-body">
            <?php if (!empty($recentProducts)): ?>
                <table class="table">
                    <thead>
                        <tr>
                            <th>Product</th>
                            <th>Category</th>
                            <th>Price</th>
                            <th>Status</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($recentProducts as $product): ?>
                            <tr>
                                <td>
                                    <strong><?= e($product['name']) ?></strong>
                                </td>
                                <td><?= e($product['category_name'] ?? 'N/A') ?></td>
                                <td><?= formatPrice($product['base_price']) ?></td>
                                <td>
                                    <span class="badge <?= $product['is_available'] ? 'badge-success' : 'badge-danger' ?>">
                                        <?= $product['is_available'] ? 'Available' : 'Out of Stock' ?>
                                    </span>
                                </td>
                                <td>
                                    <a href="<?= ADMIN_URL ?>/product-edit.php?id=<?= $product['id'] ?>" class="btn btn-sm">Edit</a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <div class="empty-state">
                    <p>No products yet. <a href="<?= ADMIN_URL ?>/product-edit.php">Add your first product</a></p>
                </div>
            <?php endif; ?>
        </div>
    </div>
<?php endif; ?>

<?php require_once __DIR__ . '/includes/footer.php'; ?>