<?php

/**
 * Category Add/Edit
 * Kids Store v5 Admin
 */

require_once __DIR__ . '/includes/auth.php';
requireLogin();

$db = getDB();
$categoryId = isset($_GET['id']) ? (int)$_GET['id'] : null;
$isEdit = $categoryId !== null;
$pageTitle = $isEdit ? 'Edit Category' : 'Add Category';

// Default category data
$category = [
    'name' => '',
    'slug' => '',
    'description' => '',
    'image' => '',
    'custom_fields' => '[]',
    'variant_types' => '[]',
    'color_listable' => 0,
    'sort_order' => 0,
    'is_active' => 1,
    'meta_title' => '',
    'meta_description' => ''
];

// Load existing category
if ($isEdit) {
    $stmt = $db->prepare("SELECT * FROM categories WHERE id = ?");
    $stmt->execute([$categoryId]);
    $existing = $stmt->fetch();

    if (!$existing) {
        setFlash('danger', 'Category not found');
        redirect(ADMIN_URL . '/categories.php');
    }

    $category = $existing;
}

$errors = [];

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrf($_POST['csrf_token'] ?? '')) {
        $errors[] = 'Invalid security token';
    } else {
        // Collect form data
        $category['name'] = trim($_POST['name'] ?? '');
        $category['slug'] = trim($_POST['slug'] ?? '');
        $category['description'] = trim($_POST['description'] ?? '');
        $category['variant_types'] = json_encode($_POST['variant_types'] ?? []);
        $category['color_listable'] = isset($_POST['color_listable']) ? 1 : 0;
        $category['sort_order'] = (int)($_POST['sort_order'] ?? 0);
        $category['is_active'] = isset($_POST['is_active']) ? 1 : 0;
        $category['meta_title'] = trim($_POST['meta_title'] ?? '');
        $category['meta_description'] = trim($_POST['meta_description'] ?? '');

        // Build custom fields from form
        $customFields = [];
        if (!empty($_POST['field_name'])) {
            foreach ($_POST['field_name'] as $i => $fieldName) {
                if (empty($fieldName)) continue;

                $field = [
                    'name' => generateSlug($fieldName),
                    'label' => trim($fieldName),
                    'type' => $_POST['field_type'][$i] ?? 'text',
                    'required' => isset($_POST['field_required'][$i]),
                ];

                if ($field['type'] === 'select' && !empty($_POST['field_options'][$i])) {
                    $options = array_map('trim', explode(',', $_POST['field_options'][$i]));
                    $field['options'] = array_filter($options);
                }

                $customFields[] = $field;
            }
        }
        $category['custom_fields'] = json_encode($customFields);

        // Validation
        if (empty($category['name'])) {
            $errors[] = 'Category name is required';
        }

        // Generate slug if empty
        if (empty($category['slug'])) {
            $category['slug'] = generateSlug($category['name']);
        } else {
            $category['slug'] = generateSlug($category['slug']);
        }

        // Ensure unique slug
        $category['slug'] = ensureUniqueSlug($db, 'categories', $category['slug'], $categoryId);

        // Handle image upload
        if (!empty($_FILES['image']['name'])) {
            $uploadError = null;
            $imagePath = uploadImage($_FILES['image'], 'categories', $uploadError);
            if ($imagePath) {
                // Delete old image
                if ($isEdit && !empty($existing['image'])) {
                    deleteImage($existing['image']);
                }
                $category['image'] = $imagePath;
            } elseif ($uploadError) {
                $errors[] = 'Image upload failed: ' . $uploadError;
            }
        }

        // Save if no errors
        if (empty($errors)) {
            try {
                if ($isEdit) {
                    $stmt = $db->prepare("
                        UPDATE categories SET 
                            name = ?, slug = ?, description = ?, image = ?,
                            custom_fields = ?, variant_types = ?, color_listable = ?,
                            sort_order = ?, is_active = ?, meta_title = ?, meta_description = ?
                        WHERE id = ?
                    ");
                    $stmt->execute([
                        $category['name'],
                        $category['slug'],
                        $category['description'],
                        $category['image'],
                        $category['custom_fields'],
                        $category['variant_types'],
                        $category['color_listable'],
                        $category['sort_order'],
                        $category['is_active'],
                        $category['meta_title'],
                        $category['meta_description'],
                        $categoryId
                    ]);
                    setFlash('success', 'Category updated successfully');
                } else {
                    $stmt = $db->prepare("
                        INSERT INTO categories 
                            (name, slug, description, image, custom_fields, variant_types, 
                             color_listable, sort_order, is_active, meta_title, meta_description)
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                    ");
                    $stmt->execute([
                        $category['name'],
                        $category['slug'],
                        $category['description'],
                        $category['image'],
                        $category['custom_fields'],
                        $category['variant_types'],
                        $category['color_listable'],
                        $category['sort_order'],
                        $category['is_active'],
                        $category['meta_title'],
                        $category['meta_description']
                    ]);
                    setFlash('success', 'Category created successfully');
                }

                redirect(ADMIN_URL . '/categories.php');
            } catch (Exception $e) {
                $errors[] = 'Error saving category: ' . $e->getMessage();
            }
        }
    }
}

// Parse JSON fields for display
$customFields = json_decode($category['custom_fields'] ?? '[]', true) ?: [];
$variantTypes = json_decode($category['variant_types'] ?? '[]', true) ?: [];

require_once __DIR__ . '/includes/header.php';
?>

<div class="page-header">
    <div>
        <h1><?= e($pageTitle) ?></h1>
        <p><?= $isEdit ? 'Update category settings and custom fields' : 'Create a new product category' ?></p>
    </div>
    <a href="<?= ADMIN_URL ?>/categories.php" class="btn">← Back to Categories</a>
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

<form method="POST" enctype="multipart/form-data" class="category-form">
    <?= csrfField() ?>

    <div class="form-grid">
        <!-- Left Column - Basic Info -->
        <div class="card">
            <div class="card-header">
                <h2>Basic Information</h2>
            </div>
            <div class="card-body">
                <div class="form-group">
                    <label for="name">Category Name *</label>
                    <input type="text" id="name" name="name" value="<?= e($category['name']) ?>" required>
                </div>

                <div class="form-group">
                    <label for="slug">URL Slug</label>
                    <input type="text" id="slug" name="slug" value="<?= e($category['slug']) ?>"
                        placeholder="Auto-generated from name">
                    <small>Leave empty to auto-generate</small>
                </div>

                <div class="form-group">
                    <label for="description">Description</label>
                    <textarea id="description" name="description" rows="3"><?= e($category['description']) ?></textarea>
                </div>

                <div class="form-group">
                    <label for="image">Category Image</label>
                    <?php if (!empty($category['image'])): ?>
                        <div class="current-image">
                            <img src="<?= imageUrl($category['image']) ?>" alt="Current image" style="max-width: 120px;">
                        </div>
                    <?php endif; ?>
                    <input type="file" id="image" name="image" accept="image/*">
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label for="sort_order">Sort Order</label>
                        <input type="number" id="sort_order" name="sort_order"
                            value="<?= e($category['sort_order']) ?>" min="0">
                    </div>

                    <div class="form-group">
                        <label class="checkbox-label">
                            <input type="checkbox" name="is_active" value="1"
                                <?= $category['is_active'] ? 'checked' : '' ?>>
                            Active
                        </label>
                    </div>
                </div>
            </div>
        </div>

        <!-- Right Column - Variant Settings -->
        <div class="card">
            <div class="card-header">
                <h2>Variant Types</h2>
            </div>
            <div class="card-body">
                <p class="form-hint mb-16">Select which variant types are allowed for products in this category.</p>

                <div class="variant-checkboxes">
                    <label class="checkbox-card">
                        <input type="checkbox" name="variant_types[]" value="color"
                            <?= in_array('color', $variantTypes) ? 'checked' : '' ?>>
                        <span class="checkbox-icon">🎨</span>
                        <span class="checkbox-text">Color</span>
                    </label>

                    <label class="checkbox-card">
                        <input type="checkbox" name="variant_types[]" value="size"
                            <?= in_array('size', $variantTypes) ? 'checked' : '' ?>>
                        <span class="checkbox-icon">📐</span>
                        <span class="checkbox-text">Size</span>
                    </label>

                    <label class="checkbox-card">
                        <input type="checkbox" name="variant_types[]" value="age"
                            <?= in_array('age', $variantTypes) ? 'checked' : '' ?>>
                        <span class="checkbox-icon">👶</span>
                        <span class="checkbox-text">Age</span>
                    </label>
                </div>

                <div class="form-group mt-24">
                    <label class="checkbox-label">
                        <input type="checkbox" name="color_listable" value="1"
                            <?= $category['color_listable'] ? 'checked' : '' ?>>
                        Show color variants as separate product cards
                    </label>
                    <small>Each color will appear as its own card in product listings</small>
                </div>
            </div>
        </div>
    </div>


    <!-- Custom Fields Builder -->
    <div class="card mt-24">
        <div class="card-header">
            <h2>Custom Fields</h2>
            <button type="button" class="btn btn-sm" onclick="addCustomField()">+ Add Field</button>
        </div>
        <div class="card-body">
            <p class="form-hint mb-16">Define custom fields that will appear in the product form for this category.</p>

            <div id="customFieldsContainer">
                <?php if (!empty($customFields)): ?>
                    <?php foreach ($customFields as $i => $field): ?>
                        <div class="custom-field-row" data-index="<?= $i ?>">
                            <div class="field-inputs">
                                <input type="text" name="field_name[]" value="<?= e($field['label']) ?>" placeholder="Field Label">
                                <select name="field_type[]">
                                    <option value="text" <?= ($field['type'] ?? 'text') === 'text' ? 'selected' : '' ?>>Text</option>
                                    <option value="textarea" <?= ($field['type'] ?? '') === 'textarea' ? 'selected' : '' ?>>Textarea</option>
                                    <option value="select" <?= ($field['type'] ?? '') === 'select' ? 'selected' : '' ?>>Dropdown</option>
                                    <option value="checkbox" <?= ($field['type'] ?? '') === 'checkbox' ? 'selected' : '' ?>>Checkbox</option>
                                </select>
                                <input type="text" name="field_options[]"
                                    value="<?= e(implode(', ', $field['options'] ?? [])) ?>"
                                    placeholder="Options (comma-separated)"
                                    class="field-options"
                                    style="<?= ($field['type'] ?? '') === 'select' ? '' : 'display:none' ?>">
                                <label class="checkbox-inline">
                                    <input type="checkbox" name="field_required[<?= $i ?>]" value="1"
                                        <?= !empty($field['required']) ? 'checked' : '' ?>>
                                    Required
                                </label>
                            </div>
                            <button type="button" class="btn btn-sm btn-danger" onclick="removeCustomField(this)">×</button>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>

            <div id="noFieldsMessage" class="empty-state" style="<?= !empty($customFields) ? 'display:none' : '' ?>">
                <p>No custom fields defined. Click "Add Field" to create one.</p>
            </div>
        </div>
    </div>

    <!-- SEO Settings -->
    <div class="card mt-24">
        <div class="card-header">
            <h2>SEO Settings</h2>
        </div>
        <div class="card-body">
            <div class="form-group">
                <label for="meta_title">Meta Title</label>
                <input type="text" id="meta_title" name="meta_title"
                    value="<?= e($category['meta_title']) ?>" maxlength="160">
                <small>Leave empty to use category name</small>
            </div>

            <div class="form-group">
                <label for="meta_description">Meta Description</label>
                <textarea id="meta_description" name="meta_description"
                    rows="2" maxlength="320"><?= e($category['meta_description']) ?></textarea>
            </div>
        </div>
    </div>

    <!-- Submit -->
    <div class="admin-footer-actions">
        <button type="submit" class="btn btn-primary btn-lg">
            <?= $isEdit ? 'Update Category' : 'Create Category' ?>
        </button>
        <a href="<?= ADMIN_URL ?>/categories.php" class="btn btn-lg">Cancel</a>
    </div>
</form>

<template id="customFieldTemplate">
    <div class="custom-field-row">
        <div class="field-inputs">
            <input type="text" name="field_name[]" placeholder="Field Label">
            <select name="field_type[]" onchange="toggleFieldOptions(this)">
                <option value="text">Text</option>
                <option value="textarea">Textarea</option>
                <option value="select">Dropdown</option>
                <option value="checkbox">Checkbox</option>
            </select>
            <input type="text" name="field_options[]" placeholder="Options (comma-separated)" class="field-options" style="display:none">
            <label class="checkbox-inline">
                <input type="checkbox" name="field_required[]" value="1">
                Required
            </label>
        </div>
        <button type="button" class="btn btn-sm btn-danger" onclick="removeCustomField(this)">×</button>
    </div>
</template>

<?php
$extraScripts = <<<'JS'
<script>
let fieldIndex = document.querySelectorAll('.custom-field-row').length;

function addCustomField() {
    const template = document.getElementById('customFieldTemplate');
    const container = document.getElementById('customFieldsContainer');
    const noFieldsMsg = document.getElementById('noFieldsMessage');
    
    const clone = template.content.cloneNode(true);
    const row = clone.querySelector('.custom-field-row');
    row.dataset.index = fieldIndex++;
    
    // Update checkbox name with index
    const checkbox = row.querySelector('input[name="field_required[]"]');
    checkbox.name = `field_required[${row.dataset.index}]`;
    
    container.appendChild(clone);
    noFieldsMsg.style.display = 'none';
}

function removeCustomField(btn) {
    const row = btn.closest('.custom-field-row');
    row.remove();
    
    const container = document.getElementById('customFieldsContainer');
    const noFieldsMsg = document.getElementById('noFieldsMessage');
    
    if (container.children.length === 0) {
        noFieldsMsg.style.display = '';
    }
}

function toggleFieldOptions(select) {
    const row = select.closest('.custom-field-row');
    const optionsInput = row.querySelector('.field-options');
    optionsInput.style.display = select.value === 'select' ? '' : 'none';
}

// Initialize field type toggles
document.querySelectorAll('.custom-field-row select[name="field_type[]"]').forEach(select => {
    select.addEventListener('change', function() {
        toggleFieldOptions(this);
    });
});
</script>
JS;
?>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
<!-- V: FIX_LAYOUT_FINAL -->