<?php

/**
 * Category Listing
 * Kids Store v5 Admin
 */

require_once __DIR__ . '/includes/auth.php';
requireLogin();

$pageTitle = 'Categories';

// Handle delete
if (isset($_POST['delete']) && isset($_POST['id'])) {
    if (verifyCsrf($_POST['csrf_token'] ?? '')) {
        $deleteId = (int)$_POST['id'];
        $db = getDB();

        // Delete category image from disk
        $stmt = $db->prepare("SELECT image FROM categories WHERE id = ?");
        $stmt->execute([$deleteId]);
        $catImage = $stmt->fetchColumn();
        if ($catImage) {
            deleteImage($catImage);
        }

        // Delete all product images from disk
        $stmt = $db->prepare("SELECT image_path FROM product_images WHERE product_id IN (SELECT id FROM products WHERE category_id = ?)");
        $stmt->execute([$deleteId]);
        while ($img = $stmt->fetch()) {
            deleteImage($img['image_path']);
        }

        // Delete all variant images from disk
        $stmt = $db->prepare("
            SELECT vi.image_path 
            FROM variant_images vi
            JOIN product_variants pv ON vi.variant_id = pv.id
            WHERE pv.product_id IN (SELECT id FROM products WHERE category_id = ?)
        ");
        $stmt->execute([$deleteId]);
        while ($img = $stmt->fetch()) {
            deleteImage($img['image_path']);
        }

        // Delete category (cascades to products, variants, images in DB)
        $stmt = $db->prepare("DELETE FROM categories WHERE id = ?");
        $stmt->execute([$deleteId]);
        setFlash('success', 'Category deleted successfully');
    }
    redirect(ADMIN_URL . '/categories.php');
}

// Get all categories
$categories = getDB()->query("
    SELECT c.*, 
           (SELECT COUNT(*) FROM products WHERE category_id = c.id) as product_count
    FROM categories c 
    ORDER BY c.sort_order ASC, c.name ASC
")->fetchAll();

require_once __DIR__ . '/includes/header.php';
?>

<div class="page-header">
    <div>
        <h1>Categories</h1>
        <p>Manage product categories and their custom fields</p>
    </div>
    <a href="<?= ADMIN_URL ?>/category-edit.php" class="btn btn-primary">
        + Add Category
    </a>
</div>

<?php if (!empty($categories)): ?>
    <div class="category-cards-grid">
        <?php foreach ($categories as $cat): ?>
            <div class="category-card-admin">
                <div class="category-card-image">
                    <?php if ($cat['image']): ?>
                        <img src="<?= imageUrl($cat['image']) ?>" alt="<?= e($cat['name']) ?>">
                    <?php else: ?>
                        <div class="no-image-placeholder">📁</div>
                    <?php endif; ?>
                    <span class="category-card-order">#<?= $cat['sort_order'] ?></span>
                    <span class="category-card-status <?= $cat['is_active'] ? 'status-active' : 'status-inactive' ?>">
                        <?= $cat['is_active'] ? '✓ Active' : '✗ Inactive' ?>
                    </span>
                </div>
                <div class="category-card-body">
                    <h3 class="category-card-title"><?= e($cat['name']) ?></h3>
                    <div class="category-card-slug">/<?= e($cat['slug']) ?></div>
                    <div class="category-card-meta">
                        <span class="category-card-products"><?= $cat['product_count'] ?> products</span>
                        <?php
                        $types = json_decode($cat['variant_types'] ?? '[]', true) ?: [];
                        if (!empty($types)):
                        ?>
                            <span class="category-card-variants"><?= implode(', ', $types) ?></span>
                        <?php endif; ?>
                    </div>
                    <?php if ($cat['color_listable'] ?? false): ?>
                        <div class="category-card-badge">🎨 Color Listable</div>
                    <?php endif; ?>
                </div>
                <div class="category-card-actions">
                    <a href="<?= SITE_URL ?>/category/<?= e($cat['slug']) ?>" class="btn btn-sm" target="_blank">👁️</a>
                    <a href="<?= ADMIN_URL ?>/category-edit.php?id=<?= $cat['id'] ?>" class="btn btn-sm btn-primary">Edit</a>
                    <form method="POST" style="display:inline" data-confirm="Delete this category and its <?= $cat['product_count'] ?> product(s)? All images will be removed.">
                        <?= csrfField() ?>
                        <input type="hidden" name="id" value="<?= $cat['id'] ?>">
                        <button type="submit" name="delete" class="btn btn-sm btn-danger">🗑️</button>
                    </form>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
<?php else: ?>
    <div class="card">
        <div class="card-body">
            <div class="empty-state">
                <div class="empty-icon">📁</div>
                <h3>No Categories Yet</h3>
                <p>Create your first category to start adding products.</p>
                <a href="<?= ADMIN_URL ?>/category-edit.php" class="btn btn-primary">Add Category</a>
            </div>
        </div>
    </div>
<?php endif; ?>

<?php require_once __DIR__ . '/includes/footer.php'; ?>