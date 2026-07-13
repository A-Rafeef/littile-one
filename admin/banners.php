<?php

/**
 * Banner Management
 * Kids Store v5
 */

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/includes/auth.php';

$pageTitle = 'Banners';
$banners = getBanners(false); // Get all banners including inactive ones

require_once __DIR__ . '/includes/header.php';
?>

<div class="content-header">
    <h1>Banner Management</h1>
    <a href="banner-edit.php" class="btn btn-primary">Add New Banner</a>
</div>

<div class="card">
    <div class="table-responsive">
        <table class="table">
            <thead>
                <tr>
                    <th width="80">Image</th>
                    <th>Details</th>
                    <th width="100">Sort</th>
                    <th width="100">Status</th>
                    <th width="150" class="text-right">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($banners)): ?>
                    <tr>
                        <td colspan="5" class="text-center">No banners found.</td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($banners as $banner): ?>
                        <tr>
                            <td>
                                <?php if ($banner['image_path']): ?>
                                    <img src="<?= imageUrl($banner['image_path']) ?>" alt="" style="width: 60px; height: 40px; object-fit: cover; border-radius: 4px;">
                                <?php else: ?>
                                    <div style="width: 60px; height: 40px; background: #eee; border-radius: 4px;"></div>
                                <?php endif; ?>
                            </td>
                            <td>
                                <strong><?= e($banner['title']) ?></strong><br>
                                <small class="text-muted"><?= e($banner['subtitle']) ?></small>
                            </td>
                            <td><?= (int)$banner['sort_order'] ?></td>
                            <td>
                                <span class="badge badge-<?= $banner['is_active'] ? 'success' : 'danger' ?>">
                                    <?= $banner['is_active'] ? 'Active' : 'Inactive' ?>
                                </span>
                            </td>
                            <td class="text-right">
                                <a href="banner-edit.php?id=<?= $banner['id'] ?>" class="btn btn-sm btn-info">Edit</a>
                                <form action="banner-delete.php" method="POST" style="display:inline-block;" onsubmit="return confirm('Are you sure you want to delete this banner?');">
                                    <input type="hidden" name="id" value="<?= $banner['id'] ?>">
                                    <?= csrfField() ?>
                                    <button type="submit" class="btn btn-sm btn-danger">Delete</button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>