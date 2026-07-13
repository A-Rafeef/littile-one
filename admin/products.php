<?php

/**
 * Product Listing
 * Kids Store v5 Admin
 */

require_once __DIR__ . '/includes/auth.php';
requireLogin();

$pageTitle = 'Products';
$db = getDB();

// Handle delete
if (isset($_POST['delete']) && isset($_POST['id'])) {
    if (verifyCsrf($_POST['csrf_token'] ?? '')) {
        $deleteId = (int)$_POST['id'];

        // Delete product images
        $stmt = $db->prepare("SELECT image_path FROM product_images WHERE product_id = ?");
        $stmt->execute([$deleteId]);
        while ($img = $stmt->fetch()) {
            deleteImage($img['image_path']);
        }

        // Delete variant images
        $stmt = $db->prepare("
            SELECT vi.image_path 
            FROM variant_images vi 
            JOIN product_variants pv ON vi.variant_id = pv.id 
            WHERE pv.product_id = ?
        ");
        $stmt->execute([$deleteId]);
        while ($img = $stmt->fetch()) {
            deleteImage($img['image_path']);
        }

        // Delete product (cascades to variants and images)
        $stmt = $db->prepare("DELETE FROM products WHERE id = ?");
        $stmt->execute([$deleteId]);
        setFlash('success', 'Product deleted successfully');
    }
    redirect(ADMIN_URL . '/products.php');
}

// Filters
$categoryFilter = $_GET['category'] ?? '';
$searchFilter = $_GET['search'] ?? '';

// Pagination & Sorting
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = 12;
$offset = ($page - 1) * $limit;

$sort = $_GET['sort'] ?? 'newest';
$view = $_GET['view'] ?? 'grid';

// Build query
$where = "WHERE 1=1";
$params = [];

if ($categoryFilter) {
    $where .= " AND p.category_id = ?";
    $params[] = $categoryFilter;
}

if ($searchFilter) {
    $where .= " AND (p.name LIKE ? OR p.description LIKE ?)";
    $params[] = "%{$searchFilter}%";
    $params[] = "%{$searchFilter}%";
}

// Count total for pagination
$countSql = "SELECT COUNT(*) FROM products p $where";
$stmt = $db->prepare($countSql);
$stmt->execute($params);
$totalProducts = $stmt->fetchColumn();
$totalPages = ceil($totalProducts / $limit);

// Sorting
$orderBy = "p.created_at DESC";
switch ($sort) {
    case 'oldest':
        $orderBy = "p.created_at ASC";
        break;
    case 'price_low':
        $orderBy = "p.base_price ASC";
        break;
    case 'price_high':
        $orderBy = "p.base_price DESC";
        break;
    case 'name_asc':
        $orderBy = "p.name ASC";
        break;
    case 'name_desc':
        $orderBy = "p.name DESC";
        break;
}

$sql = "SELECT p.*, c.name as category_name,
               (SELECT COUNT(*) FROM product_variants WHERE product_id = p.id) as variant_count,
               (SELECT image_path FROM product_images WHERE product_id = p.id ORDER BY sort_order LIMIT 1) as image
        FROM products p
        LEFT JOIN categories c ON p.category_id = c.id
        $where
        ORDER BY $orderBy
        LIMIT $limit OFFSET $offset";

$stmt = $db->prepare($sql);
$stmt->execute($params);
$products = $stmt->fetchAll();

// Get categories for filter
$categories = getCategories(false);

require_once __DIR__ . '/includes/header.php';
?>

<div class="page-header">
    <div>
        <h1>Products</h1>
        <p>Manage your product catalog</p>
    </div>
    <a href="<?= ADMIN_URL ?>/product-edit.php" class="btn btn-primary">
        + Add Product
    </a>
</div>

<!-- Filters & Controls -->
<div class="card mb-24">
    <div class="card-body">
        <form method="GET" class="filter-form">
            <div class="filter-row" style="display: flex; gap: 12px; align-items: center; flex-wrap: wrap; width: 100%;">
                <div class="filter-group" style="flex: 1; min-width: 200px;">
                    <input type="text" name="search" value="<?= e($searchFilter) ?>"
                        placeholder="Search products..." class="form-control">
                </div>

                <div class="filter-group" style="width: 200px;">
                    <select name="category" class="form-control" onchange="this.form.submit()">
                        <option value="">All Categories</option>
                        <?php foreach ($categories as $cat): ?>
                            <option value="<?= $cat['id'] ?>" <?= $categoryFilter == $cat['id'] ? 'selected' : '' ?>>
                                <?= e($cat['name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="filter-group" style="width: 180px;">
                    <select name="sort" class="form-control" onchange="this.form.submit()">
                        <option value="newest" <?= $sort == 'newest' ? 'selected' : '' ?>>Newest First</option>
                        <option value="oldest" <?= $sort == 'oldest' ? 'selected' : '' ?>>Oldest First</option>
                        <option value="price_low" <?= $sort == 'price_low' ? 'selected' : '' ?>>Price: Low to High</option>
                        <option value="price_high" <?= $sort == 'price_high' ? 'selected' : '' ?>>Price: High to Low</option>
                        <option value="name_asc" <?= $sort == 'name_asc' ? 'selected' : '' ?>>Name: A-Z</option>
                        <option value="name_desc" <?= $sort == 'name_desc' ? 'selected' : '' ?>>Name: Z-A</option>
                    </select>
                </div>

                <div class="view-toggle">
                    <button type="submit" name="view" value="grid" class="btn btn-icon <?= $view == 'grid' ? 'active' : '' ?>" title="Grid View">
                        田
                    </button>
                    <button type="submit" name="view" value="list" class="btn btn-icon <?= $view == 'list' ? 'active' : '' ?>" title="List View">
                        ☰
                    </button>
                </div>

                <?php if ($categoryFilter || $searchFilter): ?>
                    <a href="<?= ADMIN_URL ?>/products.php" class="btn btn-sm btn-outline">Clear</a>
                <?php endif; ?>
            </div>
        </form>
    </div>
</div>

<?php if (!empty($products)): ?>

    <?php if ($view == 'list'): ?>
        <!-- List View -->
        <div class="card mb-24">
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th width="80">Image</th>
                            <th>Product Name</th>
                            <th>Category</th>
                            <th>Price</th>
                            <th>Status</th>
                            <th>Variants</th>
                            <th class="text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($products as $product): ?>
                            <tr>
                                <td>
                                    <div class="table-image">
                                        <?php if ($product['image']): ?>
                                            <img src="<?= imageUrl($product['image']) ?>" alt="<?= e($product['name']) ?>">
                                        <?php else: ?>
                                            <span class="no-image">📷</span>
                                        <?php endif; ?>
                                    </div>
                                </td>
                                <td>
                                    <strong><?= e($product['name']) ?></strong>
                                    <?php if ($product['is_featured']): ?>
                                        <span class="badge badge-warning" style="font-size: 10px; margin-left: 4px;">Featured</span>
                                    <?php endif; ?>
                                </td>
                                <td><?= e($product['category_name'] ?? 'Uncategorized') ?></td>
                                <td><?= formatPrice($product['base_price']) ?></td>
                                <td>
                                    <span class="badge <?= $product['is_available'] ? 'badge-success' : 'badge-danger' ?>">
                                        <?= $product['is_available'] ? 'Available' : 'Out of Stock' ?>
                                    </span>
                                </td>
                                <td><?= $product['variant_count'] > 0 ? $product['variant_count'] : '-' ?></td>
                                <td>
                                    <div class="action-buttons justify-end">
                                        <a href="<?= SITE_URL ?>/product/<?= e($product['slug']) ?>" class="btn btn-sm btn-icon" target="_blank" title="View">👁️</a>
                                        <a href="<?= ADMIN_URL ?>/product-edit.php?id=<?= $product['id'] ?>" class="btn btn-sm btn-primary btn-icon" title="Edit">✎</a>
                                        <form method="POST" style="display:inline" data-confirm="Delete this product?">
                                            <?= csrfField() ?>
                                            <input type="hidden" name="id" value="<?= $product['id'] ?>">
                                            <button type="submit" name="delete" class="btn btn-sm btn-danger btn-icon" title="Delete">🗑️</button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    <?php else: ?>
        <!-- Grid View -->
        <div class="product-cards-grid">
            <?php foreach ($products as $product): ?>
                <div class="product-card-admin">
                    <div class="product-card-image">
                        <?php if ($product['image']): ?>
                            <img src="<?= imageUrl($product['image']) ?>" alt="<?= e($product['name']) ?>">
                        <?php else: ?>
                            <div class="no-image-placeholder">📷</div>
                        <?php endif; ?>
                        <?php if ($product['is_featured']): ?>
                            <span class="product-card-featured">⭐ Featured</span>
                        <?php endif; ?>
                        <span class="product-card-status <?= $product['is_available'] ? 'status-available' : 'status-out' ?>">
                            <?= $product['is_available'] ? '✓ Available' : '✗ Out of Stock' ?>
                        </span>
                    </div>
                    <div class="product-card-body">
                        <div class="product-card-category"><?= e($product['category_name'] ?? 'Uncategorized') ?></div>
                        <h3 class="product-card-title"><?= e($product['name']) ?></h3>
                        <div class="product-card-meta">
                            <span class="product-card-price"><?= formatPrice($product['base_price']) ?></span>
                            <?php if ($product['variant_count'] > 0): ?>
                                <span class="product-card-variants"><?= $product['variant_count'] ?> variants</span>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="product-card-actions">
                        <a href="<?= SITE_URL ?>/product/<?= e($product['slug']) ?>" class="btn btn-sm" target="_blank" title="View on Store">👁️</a>
                        <a href="<?= ADMIN_URL ?>/product-edit.php?id=<?= $product['id'] ?>" class="btn btn-sm btn-primary">Edit</a>
                        <form method="POST" style="display:inline" data-confirm="Delete this product?">
                            <?= csrfField() ?>
                            <input type="hidden" name="id" value="<?= $product['id'] ?>">
                            <button type="submit" name="delete" class="btn btn-sm btn-danger">🗑️</button>
                        </form>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <!-- Pagination -->
    <?php if ($totalPages > 1): ?>
        <div class="pagination-container">
            <div class="pagination-info">
                Showing <?= $offset + 1 ?> to <?= min($offset + $limit, $totalProducts) ?> of <?= $totalProducts ?> products
            </div>
            <div class="pagination">
                <?php if ($page > 1): ?>
                    <a href="?page=<?= $page - 1 ?>&sort=<?= $sort ?>&category=<?= $categoryFilter ?>&search=<?= urlencode($searchFilter) ?>&view=<?= $view ?>" class="btn btn-sm">Previous</a>
                <?php endif; ?>

                <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                    <a href="?page=<?= $i ?>&sort=<?= $sort ?>&category=<?= $categoryFilter ?>&search=<?= urlencode($searchFilter) ?>&view=<?= $view ?>"
                        class="btn btn-sm <?= $i == $page ? 'btn-primary' : '' ?>">
                        <?= $i ?>
                    </a>
                <?php endfor; ?>

                <?php if ($page < $totalPages): ?>
                    <a href="?page=<?= $page + 1 ?>&sort=<?= $sort ?>&category=<?= $categoryFilter ?>&search=<?= urlencode($searchFilter) ?>&view=<?= $view ?>" class="btn btn-sm">Next</a>
                <?php endif; ?>
            </div>
        </div>
    <?php endif; ?>

<?php else: ?>
    <div class="card">
        <div class="card-body">
            <div class="empty-state">
                <div class="empty-icon">🛍️</div>
                <h3>No Products Found</h3>
                <?php if ($categoryFilter || $searchFilter): ?>
                    <p>No products match your filters. <a href="<?= ADMIN_URL ?>/products.php">Clear filters</a></p>
                <?php else: ?>
                    <p>Create your first product to start selling.</p>
                    <a href="<?= ADMIN_URL ?>/product-edit.php" class="btn btn-primary">Add Product</a>
                <?php endif; ?>
            </div>
        </div>
    </div>
<?php endif; ?>

<?php require_once __DIR__ . '/includes/footer.php'; ?>