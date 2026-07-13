<?php

/**
 * Product Add/Edit
 * Kids Store v5 Admin
 */

require_once __DIR__ . '/includes/auth.php';
requireLogin();

$db = getDB();
$productId = isset($_GET['id']) ? (int)$_GET['id'] : null;
$isEdit = $productId !== null;
$pageTitle = $isEdit ? 'Edit Product' : 'Add Product';

// Get all categories
$categories = getCategories(false);
if (empty($categories)) {
    setFlash('warning', 'Please create a category first');
    redirect(ADMIN_URL . '/category-edit.php');
}

// Default product data
$product = [
    'category_id' => $categories[0]['id'],
    'name' => '',
    'slug' => '',
    'description' => '',
    'base_price' => '',
    'custom_field_values' => '{}',
    'is_available' => 1,
    'is_featured' => 0,
    'meta_title' => '',
    'meta_description' => '',
    'sort_order' => 0
];
$productImages = [];
$variants = [];

// Load existing product
if ($isEdit) {
    $stmt = $db->prepare("SELECT * FROM products WHERE id = ?");
    $stmt->execute([$productId]);
    $existing = $stmt->fetch();

    if (!$existing) {
        setFlash('danger', 'Product not found');
        redirect(ADMIN_URL . '/products.php');
    }

    $product = $existing;

    // Load images
    $stmt = $db->prepare("SELECT * FROM product_images WHERE product_id = ? ORDER BY sort_order");
    $stmt->execute([$productId]);
    $productImages = $stmt->fetchAll();

    // Load variants with their images
    $stmt = $db->prepare("SELECT * FROM product_variants WHERE product_id = ? ORDER BY id");
    $stmt->execute([$productId]);
    $variants = $stmt->fetchAll();

    foreach ($variants as &$v) {
        $imgStmt = $db->prepare("SELECT * FROM variant_images WHERE variant_id = ? ORDER BY sort_order");
        $imgStmt->execute([$v['id']]);
        $v['images'] = $imgStmt->fetchAll();
    }
    unset($v); // Important: break the reference to prevent array corruption
}

// Get current category for form rendering (check GET for category preview, POST for form submission)
$currentCategoryId = (int)($_GET['category_preview'] ?? $_POST['category_id'] ?? $product['category_id']);
$currentCategory = getCategoryById($currentCategoryId);
$customFields = json_decode($currentCategory['custom_fields'] ?? '[]', true) ?: [];
$variantTypes = json_decode($currentCategory['variant_types'] ?? '[]', true) ?: [];

$errors = [];

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrf($_POST['csrf_token'] ?? '')) {
        $errors[] = 'Invalid security token';
    } else {
        // Collect basic data
        $product['category_id'] = (int)($_POST['category_id'] ?? $categories[0]['id']);
        $product['name'] = trim($_POST['name'] ?? '');
        $product['slug'] = trim($_POST['slug'] ?? '');
        $product['description'] = trim($_POST['description'] ?? '');
        $product['base_price'] = floatval($_POST['base_price'] ?? 0);
        $product['is_available'] = isset($_POST['is_available']) ? 1 : 0;
        $product['is_featured'] = isset($_POST['is_featured']) ? 1 : 0;
        $product['meta_title'] = trim($_POST['meta_title'] ?? '');
        $product['meta_description'] = trim($_POST['meta_description'] ?? '');
        $product['sort_order'] = (int)($_POST['sort_order'] ?? 0);

        // Collect custom field values
        $fieldValues = [];
        foreach ($customFields as $field) {
            $fieldName = $field['name'];
            $value = trim($_POST['custom_' . $fieldName] ?? '');
            if (!empty($value)) {
                $fieldValues[$fieldName] = $value;
            }
        }
        $product['custom_field_values'] = json_encode($fieldValues);


        // Validation
        if (empty($product['name'])) {
            $errors[] = 'Product name is required';
        }
        if ($product['base_price'] <= 0) {
            $errors[] = 'Base price must be greater than 0';
        }

        // Check required custom fields
        foreach ($customFields as $field) {
            if (!empty($field['required']) && empty($fieldValues[$field['name']])) {
                $errors[] = $field['label'] . ' is required';
            }
        }

        // Generate slug
        if (empty($product['slug'])) {
            $product['slug'] = generateSlug($product['name']);
        } else {
            $product['slug'] = generateSlug($product['slug']);
        }
        $product['slug'] = ensureUniqueSlug($db, 'products', $product['slug'], $productId);

        // Process variants
        $variantData = [];
        if (!empty($variantTypes) && (!empty($_POST['variant_color']) || !empty($_POST['variant_size']) || !empty($_POST['variant_age']))) {
            $variantCount = max(
                count($_POST['variant_color'] ?? []),
                count($_POST['variant_size'] ?? []),
                count($_POST['variant_age'] ?? [])
            );

            // Get the variant indices from the form
            $variantIndices = $_POST['variant_index'] ?? [];

            for ($i = 0; $i < $variantCount; $i++) {
                $color = trim($_POST['variant_color'][$i] ?? '');
                $size = trim($_POST['variant_size'][$i] ?? '');
                $age = trim($_POST['variant_age'][$i] ?? '');

                // Skip empty rows
                if (empty($color) && empty($size) && empty($age)) continue;

                $priceOverride = $_POST['variant_price'][$i] ?? '';
                $priceOverride = $priceOverride !== '' ? floatval($priceOverride) : null;

                // Get the form index for this variant row (for checkbox matching)
                $formIndex = $variantIndices[$i] ?? $i;

                $variantData[] = [
                    'color' => $color ?: null,
                    'size' => $size ?: null,
                    'age' => $age ?: null,
                    'price_override' => $priceOverride,
                    'is_available' => isset($_POST['variant_available'][$formIndex]) ? 1 : 0,
                    'existing_id' => !empty($_POST['variant_id'][$i]) ? (int)$_POST['variant_id'][$i] : null,
                    'form_index' => $formIndex
                ];
            }

            // Check for duplicates
            $seen = [];
            foreach ($variantData as $v) {
                $key = ($v['color'] ?? '') . '|' . ($v['size'] ?? '') . '|' . ($v['age'] ?? '');
                if (isset($seen[$key])) {
                    $errors[] = 'Duplicate variant combination found';
                    break;
                }
                $seen[$key] = true;
            }

            // Require at least one variant if variants are enabled
            if (empty($variantData)) {
                $errors[] = 'At least one variant is required';
            }
        }

        // Save if no errors
        if (empty($errors)) {
            try {
                $db->beginTransaction();

                if ($isEdit) {
                    $stmt = $db->prepare("
                        UPDATE products SET 
                            category_id = ?, name = ?, slug = ?, description = ?,
                            base_price = ?, custom_field_values = ?,
                            is_available = ?, is_featured = ?,
                            meta_title = ?, meta_description = ?, sort_order = ?
                        WHERE id = ?
                    ");
                    $stmt->execute([
                        $product['category_id'],
                        $product['name'],
                        $product['slug'],
                        $product['description'],
                        $product['base_price'],
                        $product['custom_field_values'],
                        $product['is_available'],
                        $product['is_featured'],
                        $product['meta_title'],
                        $product['meta_description'],
                        $product['sort_order'],
                        $productId
                    ]);
                } else {
                    $stmt = $db->prepare("
                        INSERT INTO products 
                            (category_id, name, slug, description, base_price,
                             custom_field_values, is_available, is_featured,
                             meta_title, meta_description, sort_order)
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                    ");
                    $stmt->execute([
                        $product['category_id'],
                        $product['name'],
                        $product['slug'],
                        $product['description'],
                        $product['base_price'],
                        $product['custom_field_values'],
                        $product['is_available'],
                        $product['is_featured'],
                        $product['meta_title'],
                        $product['meta_description'],
                        $product['sort_order']
                    ]);
                    $productId = $db->lastInsertId();
                }

                // Handle product image uploads
                if (!empty($_FILES['images']['name'][0])) {
                    // Count how many images will remain after deletions
                    $deletingCount = count($_POST['delete_images'] ?? []);
                    $currentImageCount = count($productImages) - $deletingCount;
                    $sortOrder = count($productImages);
                    foreach ($_FILES['images']['name'] as $i => $name) {
                        if (empty($name)) continue;
                        if ($currentImageCount >= MAX_IMAGES_PER_PRODUCT) break;

                        $file = [
                            'name' => $_FILES['images']['name'][$i],
                            'type' => $_FILES['images']['type'][$i],
                            'tmp_name' => $_FILES['images']['tmp_name'][$i],
                            'error' => $_FILES['images']['error'][$i],
                            'size' => $_FILES['images']['size'][$i]
                        ];

                        $uploadError = null;
                        $imagePath = uploadImage($file, 'products', $uploadError);
                        if ($imagePath) {
                            $stmt = $db->prepare("INSERT INTO product_images (product_id, image_path, sort_order) VALUES (?, ?, ?)");
                            $stmt->execute([$productId, $imagePath, $sortOrder++]);
                            $currentImageCount++;
                        } elseif ($uploadError) {
                            // Log but don't fail - continue with other images
                            error_log("Image upload failed: $uploadError");
                        }
                    }
                }

                // Handle image deletions
                if (!empty($_POST['delete_images'])) {
                    foreach ($_POST['delete_images'] as $imageId) {
                        $stmt = $db->prepare("SELECT image_path FROM product_images WHERE id = ? AND product_id = ?");
                        $stmt->execute([$imageId, $productId]);
                        $img = $stmt->fetch();
                        if ($img) {
                            deleteImage($img['image_path']);
                            $stmt = $db->prepare("DELETE FROM product_images WHERE id = ?");
                            $stmt->execute([$imageId]);
                        }
                    }
                }

                // Handle variants
                if (!empty($variantTypes)) {
                    // Get existing variant IDs
                    $existingVariantIds = [];
                    if ($isEdit) {
                        $stmt = $db->prepare("SELECT id FROM product_variants WHERE product_id = ?");
                        $stmt->execute([$productId]);
                        while ($row = $stmt->fetch()) {
                            $existingVariantIds[] = $row['id'];
                        }
                    }

                    $newVariantIds = [];

                    foreach ($variantData as $vi => $vData) {
                        if (!empty($vData['existing_id']) && in_array($vData['existing_id'], $existingVariantIds)) {
                            // Update existing variant
                            $stmt = $db->prepare("
                                UPDATE product_variants SET 
                                    color = ?, size = ?, age = ?, price_override = ?, is_available = ?
                                WHERE id = ?
                            ");
                            $stmt->execute([
                                $vData['color'],
                                $vData['size'],
                                $vData['age'],
                                $vData['price_override'],
                                $vData['is_available'],
                                $vData['existing_id']
                            ]);
                            $variantId = $vData['existing_id'];
                        } else {
                            // Insert new variant
                            $stmt = $db->prepare("
                                INSERT INTO product_variants 
                                    (product_id, color, size, age, price_override, is_available)
                                VALUES (?, ?, ?, ?, ?, ?)
                            ");
                            $stmt->execute([
                                $productId,
                                $vData['color'],
                                $vData['size'],
                                $vData['age'],
                                $vData['price_override'],
                                $vData['is_available']
                            ]);
                            $variantId = $db->lastInsertId();
                        }

                        $newVariantIds[] = $variantId;

                        // Handle variant image uploads - use form_index for correct field matching
                        $formIdx = $vData['form_index'];
                        $fileKey = "variant_images_{$formIdx}";
                        if (!empty($_FILES[$fileKey]['name'][0])) {
                            // Get current image count
                            $stmt = $db->prepare("SELECT COUNT(*) FROM variant_images WHERE variant_id = ?");
                            $stmt->execute([$variantId]);
                            $currentCount = $stmt->fetchColumn();

                            $sortOrder = $currentCount;
                            foreach ($_FILES[$fileKey]['name'] as $fi => $fname) {
                                if (empty($fname)) continue;
                                if ($sortOrder >= MAX_IMAGES_PER_PRODUCT) break;

                                $file = [
                                    'name' => $_FILES[$fileKey]['name'][$fi],
                                    'type' => $_FILES[$fileKey]['type'][$fi],
                                    'tmp_name' => $_FILES[$fileKey]['tmp_name'][$fi],
                                    'error' => $_FILES[$fileKey]['error'][$fi],
                                    'size' => $_FILES[$fileKey]['size'][$fi]
                                ];

                                $uploadError = null;
                                $imagePath = uploadImage($file, 'variants', $uploadError);
                                if ($imagePath) {
                                    $stmt = $db->prepare("INSERT INTO variant_images (variant_id, image_path, sort_order) VALUES (?, ?, ?)");
                                    $stmt->execute([$variantId, $imagePath, $sortOrder++]);
                                } elseif ($uploadError) {
                                    // Log but don't fail - continue with other images
                                    error_log("Variant image upload failed: $uploadError");
                                }
                            }
                        }

                        // Handle variant image deletions
                        $deleteKey = "delete_variant_images_{$formIdx}";
                        if (!empty($_POST[$deleteKey])) {
                            foreach ($_POST[$deleteKey] as $imgId) {
                                $stmt = $db->prepare("SELECT image_path FROM variant_images WHERE id = ? AND variant_id = ?");
                                $stmt->execute([$imgId, $variantId]);
                                $img = $stmt->fetch();
                                if ($img) {
                                    deleteImage($img['image_path']);
                                    $stmt = $db->prepare("DELETE FROM variant_images WHERE id = ?");
                                    $stmt->execute([$imgId]);
                                }
                            }
                        }
                    }

                    // Delete variants that were removed
                    $toDelete = array_diff($existingVariantIds, $newVariantIds);
                    foreach ($toDelete as $delId) {
                        // Delete variant images first
                        $stmt = $db->prepare("SELECT image_path FROM variant_images WHERE variant_id = ?");
                        $stmt->execute([$delId]);
                        while ($img = $stmt->fetch()) {
                            deleteImage($img['image_path']);
                        }
                        $stmt = $db->prepare("DELETE FROM product_variants WHERE id = ?");
                        $stmt->execute([$delId]);
                    }
                }

                $db->commit();
                setFlash('success', $isEdit ? 'Product updated successfully' : 'Product created successfully');
                redirect(ADMIN_URL . '/products.php');
            } catch (Exception $e) {
                $db->rollBack();
                $errors[] = 'Error saving product: ' . $e->getMessage();
            }
        }
    }
}

// Parse JSON for display
$customFieldValues = json_decode($product['custom_field_values'] ?? '{}', true) ?: [];

require_once __DIR__ . '/includes/header.php';
?>

<div class="page-header">
    <div>
        <h1><?= e($pageTitle) ?></h1>
        <p><?= $isEdit ? 'Update product details and variants' : 'Create a new product' ?></p>
    </div>
    <a href="<?= ADMIN_URL ?>/products.php" class="btn">← Back to Products</a>
</div>

<?php if (!empty($errors)): ?>
    <div class="alert alert-danger">
        <ul class="error-list">
            <?php foreach ($errors as $error): ?>
                <li><?= e($error) ?></li>
            <?php endforeach; ?>
        </ul>
    </div>
<?php endif; ?>

<form method="POST" enctype="multipart/form-data" class="product-form" id="productForm">
    <?= csrfField() ?>

    <div class="form-grid">
        <!-- Left Column -->
        <div>
            <!-- Basic Info -->
            <div class="card">
                <div class="card-header">
                    <h2>Basic Information</h2>
                </div>
                <div class="card-body">
                    <div class="form-group">
                        <label for="category_id">Category *</label>
                        <select id="category_id" name="category_id" onchange="reloadForCategory(this.value)">
                            <?php foreach ($categories as $cat): ?>
                                <option value="<?= $cat['id'] ?>" <?= $currentCategoryId == $cat['id'] ? 'selected' : '' ?>>
                                    <?= e($cat['name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label for="name">Product Name *</label>
                        <input type="text" id="name" name="name" value="<?= e($product['name']) ?>" required>
                    </div>

                    <div class="form-group">
                        <label for="slug">URL Slug</label>
                        <input type="text" id="slug" name="slug" value="<?= e($product['slug']) ?>"
                            placeholder="Auto-generated from name">
                    </div>

                    <div class="form-group">
                        <label for="description">Description</label>
                        <textarea id="description" name="description" rows="4"><?= e($product['description']) ?></textarea>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label for="base_price">Base Price (<?= e(getSetting('currency_symbol', '₹')) ?>) *</label>
                            <input type="number" id="base_price" name="base_price"
                                value="<?= e($product['base_price']) ?>" step="0.01" min="0" required>
                        </div>

                        <div class="form-group">
                            <label for="sort_order">Sort Order</label>
                            <input type="number" id="sort_order" name="sort_order"
                                value="<?= e($product['sort_order']) ?>" min="0">
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label class="checkbox-label">
                                <input type="checkbox" name="is_available" value="1"
                                    <?= $product['is_available'] ? 'checked' : '' ?>>
                                Available
                            </label>
                        </div>

                        <div class="form-group">
                            <label class="checkbox-label">
                                <input type="checkbox" name="is_featured" value="1"
                                    <?= $product['is_featured'] ? 'checked' : '' ?>>
                                Featured
                            </label>
                        </div>
                    </div>
                </div>
            </div>


            <!-- SEO -->
            <div class="card mt-24">
                <div class="card-header">
                    <h2>SEO Settings</h2>
                </div>
                <div class="card-body">
                    <div class="form-group">
                        <label for="meta_title">Meta Title</label>
                        <input type="text" id="meta_title" name="meta_title"
                            value="<?= e($product['meta_title']) ?>" maxlength="160">
                    </div>

                    <div class="form-group">
                        <label for="meta_description">Meta Description</label>
                        <textarea id="meta_description" name="meta_description"
                            rows="2" maxlength="320"><?= e($product['meta_description']) ?></textarea>
                    </div>
                </div>
            </div>

            <!-- Custom Fields -->
            <?php if (!empty($customFields)): ?>
                <div class="card mt-24">
                    <div class="card-header">
                        <h2>Custom Fields</h2>
                    </div>
                    <div class="card-body">
                        <?php foreach ($customFields as $field): ?>
                            <div class="form-group">
                                <label for="custom_<?= e($field['name']) ?>">
                                    <?= e($field['label']) ?><?= !empty($field['required']) ? ' *' : '' ?>
                                </label>
                                <?php
                                $fieldValue = $customFieldValues[$field['name']] ?? '';
                                $fieldType = $field['type'] ?? 'text';
                                ?>
                                <?php if ($fieldType === 'textarea'): ?>
                                    <textarea id="custom_<?= e($field['name']) ?>"
                                        name="custom_<?= e($field['name']) ?>"
                                        rows="3" <?= !empty($field['required']) ? ' required' : '' ?>><?= e($fieldValue) ?></textarea>
                                <?php elseif ($fieldType === 'select' && !empty($field['options'])): ?>
                                    <select id="custom_<?= e($field['name']) ?>"
                                        name="custom_<?= e($field['name']) ?>" <?= !empty($field['required']) ? ' required' : '' ?>>
                                        <option value="">-- Select --</option>
                                        <?php foreach ($field['options'] as $opt): ?>
                                            <option value="<?= e($opt) ?>" <?= $fieldValue === $opt ? 'selected' : '' ?>>
                                                <?= e($opt) ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                <?php elseif ($fieldType === 'checkbox'): ?>
                                    <label class="checkbox-label">
                                        <input type="checkbox" id="custom_<?= e($field['name']) ?>"
                                            name="custom_<?= e($field['name']) ?>"
                                            value="1" <?= $fieldValue ? 'checked' : '' ?>>
                                        <?= e($field['label']) ?>
                                    </label>
                                <?php else: ?>
                                    <input type="text" id="custom_<?= e($field['name']) ?>"
                                        name="custom_<?= e($field['name']) ?>"
                                        value="<?= e($fieldValue) ?>" <?= !empty($field['required']) ? ' required' : '' ?>>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endif; ?>
        </div>

        <!-- Right Column (Images) -->
        <div>
            <!-- Product Images -->
            <div class="card">
                <div class="card-header">
                    <h2>Product Images</h2>
                    <span class="text-muted">Max <?= MAX_IMAGES_PER_PRODUCT ?> images</span>
                </div>
                <div class="card-body">
                    <?php if (!empty($productImages)): ?>
                        <div class="image-grid mb-16">
                            <?php foreach ($productImages as $img): ?>
                                <div class="image-item">
                                    <img src="<?= imageUrl($img['image_path']) ?>" alt="">
                                    <label class="image-delete">
                                        <input type="checkbox" name="delete_images[]" value="<?= $img['id'] ?>">
                                        <span>×</span>
                                    </label>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>

                    <div class="form-group">
                        <label>Product Images</label>
                        <div class="camera-upload-widget">
                            <button type="button" class="btn-camera"
                                onclick="CameraCapture.open(
                                    document.getElementById('productFileInput'),
                                    document.getElementById('productPreviewStrip'),
                                    <?= count($productImages) ?>
                                )">
                                📷 Take Photos
                            </button>
                            <button type="button" class="btn-gallery"
                                onclick="CameraCapture.openGallery(
                                    document.getElementById('productFileInput'),
                                    document.getElementById('productPreviewStrip')
                                )">
                                🖼 Choose from Gallery
                            </button>
                            <input type="file" name="images[]" id="productFileInput"
                                multiple accept="image/*" style="display:none">
                        </div>
                        <div class="capture-preview-strip" id="productPreviewStrip"></div>
                        <small>JPG, PNG, or WebP. Max 10MB each (auto-compressed if over 2MB).</small>
                    </div>
                </div>
            </div>

            <!-- Variants -->
            <?php if (!empty($variantTypes)): ?>
                <div class="card mt-24" id="variantsCard">
                    <div class="card-header">
                        <h2>Variants</h2>
                        <button type="button" class="btn btn-sm" onclick="addVariantRow()">+ Add Variant</button>
                    </div>
                    <div class="card-body">
                        <p class="form-hint mb-16">
                            Variant types for this category:
                            <strong><?= implode(', ', array_map('ucfirst', $variantTypes)) ?></strong>
                        </p>


                        <div id="variantsContainer">
                            <?php if (!empty($variants)): ?>
                                <?php foreach ($variants as $vi => $v): ?>
                                    <div class="variant-row" data-index="<?= $vi ?>">
                                        <div class="variant-header">
                                            <span class="variant-number">#<?= $vi + 1 ?></span>
                                            <button type="button" class="btn btn-sm btn-danger" onclick="removeVariantRow(this)">Remove</button>
                                        </div>
                                        <input type="hidden" name="variant_id[]" value="<?= $v['id'] ?>">
                                        <input type="hidden" name="variant_index[]" value="<?= $vi ?>">

                                        <div class="variant-fields">
                                            <?php if (in_array('color', $variantTypes)): ?>
                                                <div class="form-group">
                                                    <label>Color</label>
                                                    <input type="text" name="variant_color[]" value="<?= e($v['color'] ?? '') ?>" placeholder="e.g., Red, Blue">
                                                </div>
                                            <?php else: ?>
                                                <input type="hidden" name="variant_color[]" value="">
                                            <?php endif; ?>

                                            <?php if (in_array('size', $variantTypes)): ?>
                                                <div class="form-group">
                                                    <label>Size</label>
                                                    <input type="text" name="variant_size[]" value="<?= e($v['size'] ?? '') ?>" placeholder="e.g., S, M, L">
                                                </div>
                                            <?php else: ?>
                                                <input type="hidden" name="variant_size[]" value="">
                                            <?php endif; ?>

                                            <?php if (in_array('age', $variantTypes)): ?>
                                                <div class="form-group">
                                                    <label>Age</label>
                                                    <input type="text" name="variant_age[]" value="<?= e($v['age'] ?? '') ?>" placeholder="e.g., 0-3, 3-6">
                                                </div>
                                            <?php else: ?>
                                                <input type="hidden" name="variant_age[]" value="">
                                            <?php endif; ?>

                                            <div class="form-group">
                                                <label>Price Override</label>
                                                <input type="number" name="variant_price[]" value="<?= e($v['price_override'] ?? '') ?>" step="0.01" min="0" placeholder="Leave empty for base price">
                                            </div>

                                            <div class="form-group">
                                                <label class="checkbox-label">
                                                    <input type="checkbox" name="variant_available[<?= $vi ?>]" value="1" <?= $v['is_available'] ? 'checked' : '' ?>>
                                                    Available
                                                </label>
                                            </div>
                                        </div>

                                        <!-- Variant Images -->
                                        <div class="variant-images">
                                            <label>Variant Images</label>
                                            <?php if (!empty($v['images'])): ?>
                                                <div class="image-grid mb-8">
                                                    <?php foreach ($v['images'] as $vimg): ?>
                                                        <div class="image-item small">
                                                            <img src="<?= imageUrl($vimg['image_path']) ?>" alt="">
                                                            <label class="image-delete">
                                                                <input type="checkbox" name="delete_variant_images_<?= $vi ?>[]" value="<?= $vimg['id'] ?>">
                                                                <span>×</span>
                                                            </label>
                                                        </div>
                                                    <?php endforeach; ?>
                                                </div>
                                            <?php endif; ?>
                                            <?php
                                            $vExistingCount = count($v['images'] ?? []);
                                            $vInputId = 'variantFileInput_' . $vi;
                                            $vStripId = 'variantPreviewStrip_' . $vi;
                                            ?>
                                            <div class="camera-upload-widget">
                                                <button type="button" class="btn-camera"
                                                    onclick="CameraCapture.open(
                                                        document.getElementById('<?= $vInputId ?>'),
                                                        document.getElementById('<?= $vStripId ?>'),
                                                        <?= $vExistingCount ?>
                                                    )">
                                                    📷 Take Photos
                                                </button>
                                                <button type="button" class="btn-gallery"
                                                    onclick="CameraCapture.openGallery(
                                                        document.getElementById('<?= $vInputId ?>'),
                                                        document.getElementById('<?= $vStripId ?>')
                                                    )">
                                                    🖼 Gallery
                                                </button>
                                                <input type="file" name="variant_images_<?= $vi ?>[]" id="<?= $vInputId ?>"
                                                    multiple accept="image/*" style="display:none">
                                            </div>
                                            <div class="capture-preview-strip" id="<?= $vStripId ?>"></div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <!-- Empty state handled by JS -->
                            <?php endif; ?>
                        </div>

                        <div id="noVariantsMessage" class="empty-state" style="<?= !empty($variants) ? 'display:none' : '' ?>">
                            <p>No variants added yet. Click "Add Variant" to create one.</p>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Submit -->
    <div class="admin-footer-actions">
        <button type="submit" class="btn btn-primary btn-lg">
            <?= $isEdit ? 'Update Product' : 'Create Product' ?>
        </button>
        <a href="<?= ADMIN_URL ?>/products.php" class="btn btn-lg">Cancel</a>
    </div>
</form>

<!-- Variant Row Template -->
<template id="variantRowTemplate">
    <div class="variant-row">
        <div class="variant-header">
            <span class="variant-number">#</span>
            <button type="button" class="btn btn-sm btn-danger" onclick="removeVariantRow(this)">Remove</button>
        </div>
        <input type="hidden" name="variant_id[]" value="">
        <input type="hidden" name="variant_index[]" value="INDEX">

        <div class="variant-fields">
            <?php if (in_array('color', $variantTypes)): ?>
                <div class="form-group">
                    <label>Color</label>
                    <input type="text" name="variant_color[]" placeholder="e.g., Red, Blue">
                </div>
            <?php else: ?>
                <input type="hidden" name="variant_color[]" value="">
            <?php endif; ?>

            <?php if (in_array('size', $variantTypes)): ?>
                <div class="form-group">
                    <label>Size</label>
                    <input type="text" name="variant_size[]" placeholder="e.g., S, M, L">
                </div>
            <?php else: ?>
                <input type="hidden" name="variant_size[]" value="">
            <?php endif; ?>

            <?php if (in_array('age', $variantTypes)): ?>
                <div class="form-group">
                    <label>Age</label>
                    <input type="text" name="variant_age[]" placeholder="e.g., 0-3, 3-6">
                </div>
            <?php else: ?>
                <input type="hidden" name="variant_age[]" value="">
            <?php endif; ?>

            <div class="form-group">
                <label>Price Override</label>
                <input type="number" name="variant_price[]" step="0.01" min="0" placeholder="Leave empty for base price">
            </div>

            <div class="form-group">
                <label class="checkbox-label">
                    <input type="checkbox" name="variant_available[INDEX]" value="1" checked>
                    Available
                </label>
            </div>
        </div>

        <div class="variant-images">
            <label>Variant Images</label>
            <div class="camera-upload-widget">
                <button type="button" class="btn-camera"
                    onclick="CameraCapture.open(
                        this.closest('.variant-row').querySelector('input[type=file]'),
                        this.closest('.variant-images').querySelector('.capture-preview-strip'),
                        0
                    )">
                    📷 Take Photos
                </button>
                <button type="button" class="btn-gallery"
                    onclick="CameraCapture.openGallery(
                        this.closest('.variant-row').querySelector('input[type=file]'),
                        this.closest('.variant-images').querySelector('.capture-preview-strip')
                    )">
                    🖼 Gallery
                </button>
                <input type="file" name="variant_images_INDEX[]" multiple accept="image/*" style="display:none">
            </div>
            <div class="capture-preview-strip"></div>
        </div>
    </div>
</template>

<?php
// Camera assets + MAX_IMAGES_PER_PRODUCT config
$camCssV = filemtime(__DIR__ . '/assets/css/camera-capture.css');
$camJsV  = filemtime(__DIR__ . '/assets/js/camera-capture.js');
$extraScripts  = '<link rel="stylesheet" href="' . ADMIN_URL . '/assets/css/camera-capture.css?v=' . $camCssV . '">' . "\n";
$extraScripts .= '<script src="' . ADMIN_URL . '/assets/js/camera-capture.js?v=' . $camJsV . '"></script>' . "\n";
$extraScripts .= '<script>window.MAX_IMAGES_PER_PRODUCT = ' . MAX_IMAGES_PER_PRODUCT . ';</script>' . "\n";

// Existing variant / form JS
$extraScripts .= <<<'JS'
<script>
let variantIndex = document.querySelectorAll('.variant-row').length;

// Restore form data after category change
(function() {
    const saved = sessionStorage.getItem('productFormData');
    if (saved && new URL(window.location.href).searchParams.has('category_preview')) {
        try {
            const data = JSON.parse(saved);
            const form = document.getElementById('productForm');
            // Restore basic text/number fields (not checkboxes, files, or variants)
            const restoreFields = ['name', 'slug', 'description', 'base_price', 'sort_order', 'meta_title', 'meta_description'];
            restoreFields.forEach(function(name) {
                if (data[name] !== undefined) {
                    const el = form.querySelector('[name="' + name + '"]');
                    if (el) el.value = data[name];
                }
            });
            // Restore checkboxes
            ['is_available', 'is_featured'].forEach(function(name) {
                const el = form.querySelector('[name="' + name + '"]');
                if (el) el.checked = !!data[name];
            });
        } catch(e) { /* ignore parse errors */ }
        sessionStorage.removeItem('productFormData');
    }
})();

function toggleVariants(checked) {
    const card = document.getElementById('variantsCard');
    if (card) {
        card.style.display = checked ? 'block' : 'none';
    }
}

function reloadForCategory(categoryId) {
    const form = document.getElementById('productForm');
    const currentUrl = new URL(window.location.href);
    currentUrl.searchParams.set('category_preview', categoryId);
    
    // Save form data temporarily
    const formData = new FormData(form);
    sessionStorage.setItem('productFormData', JSON.stringify(Object.fromEntries(formData)));
    
    if (!confirm('Changing category will reload the page and may reset variant fields. Continue?')) {
        return;
    }
    window.location.href = currentUrl.toString();
}

function addVariantRow() {
    const template = document.getElementById('variantRowTemplate');
    const container = document.getElementById('variantsContainer');
    const noVariantsMsg = document.getElementById('noVariantsMessage');
    
    const clone = template.content.cloneNode(true);
    const row = clone.querySelector('.variant-row');
    row.dataset.index = variantIndex;
    
    // Update variant number
    row.querySelector('.variant-number').textContent = '#' + (variantIndex + 1);
    
    // Update variant_index hidden field
    const indexInput = row.querySelector('input[name="variant_index[]"]');
    if (indexInput) {
        indexInput.value = variantIndex;
    }
    
    // Update file input name with correct index
    const fileInput = row.querySelector('input[type="file"]');
    if (fileInput) {
        fileInput.name = 'variant_images_' + variantIndex + '[]';
    }
    
    // Update checkbox name with correct index
    const checkbox = row.querySelector('input[name="variant_available[INDEX]"]');
    if (checkbox) {
        checkbox.name = 'variant_available[' + variantIndex + ']';
    }
    
    container.appendChild(clone);
    noVariantsMsg.style.display = 'none';
    variantIndex++;
    
    updateVariantNumbers();
}

function removeVariantRow(btn) {
    const row = btn.closest('.variant-row');
    row.remove();
    
    const container = document.getElementById('variantsContainer');
    const noVariantsMsg = document.getElementById('noVariantsMessage');
    
    if (container.children.length === 0) {
        noVariantsMsg.style.display = '';
    }
    
    updateVariantNumbers();
}

function updateVariantNumbers() {
    const rows = document.querySelectorAll('.variant-row');
    rows.forEach((row, i) => {
        row.querySelector('.variant-number').textContent = '#' + (i + 1);
    });
}
</script>
JS;
?>

<?php require_once __DIR__ . '/includes/footer.php'; ?>