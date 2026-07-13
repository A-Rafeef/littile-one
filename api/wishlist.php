<?php

/**
 * Wishlist API
 * Returns product data for given IDs
 * Usage: GET /api/wishlist.php?ids=1,2,3
 */

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/functions.php';

header('Content-Type: application/json');

$ids = $_GET['ids'] ?? '';
if (empty($ids)) {
    jsonResponse(['products' => []]);
}

// Parse and sanitize IDs
$idArray = array_filter(array_map('intval', explode(',', $ids)));
if (empty($idArray)) {
    jsonResponse(['products' => []]);
}

$db = getDB();
$placeholders = implode(',', array_fill(0, count($idArray), '?'));

$sql = "SELECT p.id, p.name, p.slug, p.base_price, p.is_available,
               c.name as category_name, c.slug as category_slug
        FROM products p
        JOIN categories c ON p.category_id = c.id
        WHERE p.id IN ($placeholders)";

$stmt = $db->prepare($sql);
$stmt->execute($idArray);
$products = $stmt->fetchAll();

// Get primary image for each product
$result = [];
foreach ($products as $p) {
    $img = getProductImage($p['id']);
    $result[] = [
        'id' => (int)$p['id'],
        'name' => $p['name'],
        'slug' => $p['slug'],
        'price' => (float)$p['base_price'],
        'price_formatted' => formatPrice($p['base_price']),
        'image' => $img ? imageUrl($img) : SITE_URL . '/assets/img/placeholder.png',
        'category' => $p['category_name'],
        'category_slug' => $p['category_slug'],
        'is_available' => (bool)$p['is_available']
    ];
}

jsonResponse(['products' => $result]);
