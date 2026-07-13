<?php

/**
 * Add/Edit Banner
 * Kids Store v5
 */

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/includes/auth.php';

$id = $_GET['id'] ?? null;
$banner = null;
$pageTitle = $id ? 'Edit Banner' : 'Add Banner';

if ($id) {
    $stmt = getDB()->prepare("SELECT * FROM banners WHERE id = ?");
    $stmt->execute([$id]);
    $banner = $stmt->fetch();
    if (!$banner) {
        setFlash('danger', 'Banner not found.');
        redirect('banners.php');
    }
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrf($_POST['csrf_token'] ?? '')) {
        die('CSRF token mismatch');
    }

    $title = trim($_POST['title']);
    $subtitle = trim($_POST['subtitle']);
    $button_text = trim($_POST['button_text']);
    $button_url = trim($_POST['button_url']);
    $sort_order = (int)$_POST['sort_order'];
    $is_active = isset($_POST['is_active']) ? 1 : 0;

    $image_path = $banner['image_path'] ?? null;

    // Handle image upload
    if (!empty($_FILES['image']['name'])) {
        $uploadError = null;
        $uploaded = uploadImage($_FILES['image'], 'banners', $uploadError);
        if ($uploaded) {
            // Delete old image if exists
            if ($image_path) {
                deleteImage($image_path);
            }
            $image_path = $uploaded;
        } else {
            $errorMsg = $uploadError ?? 'Unknown error';
            setFlash('danger', 'Failed to upload image: ' . $errorMsg);
        }
    }

    if ($id) {
        $stmt = getDB()->prepare("UPDATE banners SET title = ?, subtitle = ?, button_text = ?, button_url = ?, image_path = ?, sort_order = ?, is_active = ? WHERE id = ?");
        $stmt->execute([$title, $subtitle, $button_text, $button_url, $image_path, $sort_order, $is_active, $id]);
        setFlash('success', 'Banner updated successfully.');
    } else {
        $stmt = getDB()->prepare("INSERT INTO banners (title, subtitle, button_text, button_url, image_path, sort_order, is_active) VALUES (?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([$title, $subtitle, $button_text, $button_url, $image_path, $sort_order, $is_active]);
        setFlash('success', 'Banner created successfully.');
    }

    redirect('banners.php');
}

require_once __DIR__ . '/includes/header.php';
?>

<div class="content-header">
    <h1><?= $pageTitle ?></h1>
    <a href="banners.php" class="btn btn-secondary">Back to List</a>
</div>

<div class="card">
    <form action="" method="POST" enctype="multipart/form-data" class="form">
        <?= csrfField() ?>

        <div class="row">
            <div class="col-md-6">
                <div class="form-group">
                    <label for="title">Banner Title</label>
                    <input type="text" name="title" id="title" class="form-control" value="<?= e($banner['title'] ?? '') ?>" required placeholder="e.g. Playtime Redefined">
                </div>
            </div>
            <div class="col-md-6">
                <div class="form-group">
                    <label for="subtitle">Subtitle</label>
                    <input type="text" name="subtitle" id="subtitle" class="form-control" value="<?= e($banner['subtitle'] ?? '') ?>" placeholder="e.g. NEW SEASON">
                </div>
            </div>
        </div>

        <div class="row">
            <div class="col-md-6">
                <div class="form-group">
                    <label for="button_text">Button Text</label>
                    <input type="text" name="button_text" id="button_text" class="form-control" value="<?= e($banner['button_text'] ?? '') ?>" placeholder="e.g. Shop Collection">
                </div>
            </div>
            <div class="col-md-6">
                <div class="form-group">
                    <label for="button_url">Button URL</label>
                    <input type="text" name="button_url" id="button_url" class="form-control" value="<?= e($banner['button_url'] ?? '') ?>" placeholder="e.g. category/toys">
                </div>
            </div>
        </div>

        <div class="form-group">
            <label for="image">Banner Image</label>
            <?php if (!empty($banner['image_path'])): ?>
                <div class="mb-8">
                    <img src="<?= imageUrl($banner['image_path']) ?>" alt="Current image" style="max-width: 200px; border-radius: 8px; border: 1px solid #ddd;">
                </div>
            <?php endif; ?>
            <input type="file" name="image" id="image" class="form-control" accept="image/*" <?= $id ? '' : 'required' ?>>
            <small class="text-muted">Recommended size: 1200x600px. Max 10MB (auto-compressed if over 2MB).</small>
        </div>

        <div class="row">
            <div class="col-md-6">
                <div class="form-group">
                    <label for="sort_order">Sort Order</label>
                    <input type="number" name="sort_order" id="sort_order" class="form-control" value="<?= (int)($banner['sort_order'] ?? 0) ?>">
                </div>
            </div>
            <div class="col-md-6">
                <div class="form-group" style="padding-top: 32px;">
                    <label class="checkbox-label">
                        <input type="checkbox" name="is_active" <?= (!isset($banner['is_active']) || $banner['is_active']) ? 'checked' : '' ?>>
                        Active
                    </label>
                </div>
            </div>
        </div>

        <div class="form-actions">
            <button type="submit" class="btn btn-primary"><?= $id ? 'Update Banner' : 'Create Banner' ?></button>
        </div>
    </form>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>