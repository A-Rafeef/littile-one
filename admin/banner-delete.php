<?php

/**
 * Delete Banner
 * Kids Store v5
 */

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/includes/auth.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrf($_POST['csrf_token'] ?? '')) {
        die('CSRF token mismatch');
    }

    $id = (int)$_POST['id'];

    // Get image path to delete file
    $stmt = getDB()->prepare("SELECT image_path FROM banners WHERE id = ?");
    $stmt->execute([$id]);
    $banner = $stmt->fetch();

    if ($banner) {
        if ($banner['image_path']) {
            deleteImage($banner['image_path']);
        }

        $stmt = getDB()->prepare("DELETE FROM banners WHERE id = ?");
        $stmt->execute([$id]);

        setFlash('success', 'Banner deleted successfully.');
    } else {
        setFlash('danger', 'Banner not found.');
    }
}

redirect('banners.php');
