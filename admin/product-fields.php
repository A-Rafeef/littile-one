<?php

/**
 * Product Fields Management
 * Kids Store v5 Admin
 */

require_once __DIR__ . '/includes/auth.php';
requireLogin();

// Feature Gate: Unlocked for all
// if (!userCan('custom_fields')) { ... }

$db = getDB();
$pageTitle = 'Product Fields';

// Use default store ID (Single Store Mode)
$storeId = 1;

// Handle Form Submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (verifyCsrf($_POST['csrf_token'] ?? '')) {
        try {
            // 1. Update Recommended Fields (Toggle Active)
            if (isset($_POST['toggle_field'])) {
                // ...
            }

            // 2. Add/Edit Custom Field
            if (isset($_POST['save_field'])) {
                $name = trim($_POST['field_name'] ?? '');
                $key = generateSlug($name);
                $type = $_POST['field_type'] ?? 'text';
                $required = isset($_POST['is_required']) ? 1 : 0;

                if (!empty($name)) {
                    $stmt = $db->prepare("
                        INSERT INTO store_product_fields 
                        (store_id, field_key, display_name, field_type, is_required, is_active)
                        VALUES (?, ?, ?, ?, ?, 1)
                        ON DUPLICATE KEY UPDATE 
                        display_name = VALUES(display_name), 
                        field_type = VALUES(field_type),
                        is_required = VALUES(is_required),
                        is_active = 1
                    ");
                    $stmt->execute([$storeId, $key, $name, $type, $required]);
                    setFlash('success', 'Field saved successfully');
                }
            }

            // 3. Delete Field
            if (isset($_POST['delete_field'])) {
                $key = $_POST['field_key'];
                $stmt = $db->prepare("DELETE FROM store_product_fields WHERE store_id = ? AND field_key = ?");
                $stmt->execute([$storeId, $key]);
                setFlash('success', 'Field deleted');
            }
        } catch (PDOException $e) {
            setFlash('error', 'Database Error: Tables missing. Any pending migrations?');
        }
    }
    redirect(ADMIN_URL . '/product-fields.php');
}

// Get Enabled Fields
$enabledFields = [];
try {
    $stmt = $db->prepare("SELECT * FROM store_product_fields WHERE store_id = ? ORDER BY sort_order, created_at");
    $stmt->execute([$storeId]);
    $enabledFields = $stmt->fetchAll();
} catch (PDOException $e) {
    // Table missing, show empty state
}

require_once __DIR__ . '/includes/header.php';
?>

<div class="page-header">
    <div>
        <h1><?= e($pageTitle) ?></h1>
        <p>Manage custom attributes for your products</p>
    </div>
</div>

<div class="grid-layout">
    <div class="card">
        <div class="card-header">
            <h2>Your Active Fields</h2>
        </div>
        <div class="card-body">
            <?php if (empty($enabledFields)): ?>
                <div class="empty-state">
                    <p>No custom fields enabled.</p>
                </div>
            <?php else: ?>
                <table class="table">
                    <thead>
                        <tr>
                            <th>Field Name</th>
                            <th>Type</th>
                            <th>Required</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($enabledFields as $field): ?>
                            <tr>
                                <td><?= e($field['display_name']) ?></td>
                                <td><?= e(ucfirst($field['field_type'])) ?></td>
                                <td><?= $field['is_required'] ? 'Yes' : 'No' ?></td>
                                <td>
                                    <form method="POST" style="display:inline" onsubmit="return confirm('Delete this field?')">
                                        <?= csrfField() ?>
                                        <input type="hidden" name="field_key" value="<?= e($field['field_key']) ?>">
                                        <button type="submit" name="delete_field" class="btn btn-sm btn-danger">Delete</button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
    </div>

    <div class="card">
        <div class="card-header">
            <h2>Add Custom Field</h2>
        </div>
        <div class="card-body">
            <form method="POST">
                <?= csrfField() ?>
                <div class="form-group">
                    <label>Field Name</label>
                    <input type="text" name="field_name" required placeholder="e.g. Material, Brand">
                </div>

                <div class="form-group">
                    <label>Type</label>
                    <select name="field_type">
                        <option value="text">Text</option>
                        <option value="number">Number</option>
                        <option value="select">Dropdown</option>
                        <option value="boolean">Yes/No</option>
                        <option value="textarea">Long Text</option>
                    </select>
                </div>

                <div class="form-group">
                    <label class="checkbox-label">
                        <input type="checkbox" name="is_required" value="1">
                        Required
                    </label>
                </div>

                <button type="submit" name="save_field" class="btn btn-primary">Add Field</button>
            </form>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>