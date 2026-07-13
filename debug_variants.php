<?php
// Fix 17: Block web access to debug utilities
if (php_sapi_name() !== 'cli' && !in_array($_SERVER['REMOTE_ADDR'] ?? '', ['127.0.0.1', '::1'])) {
    http_response_code(403);
    exit('Forbidden');
}
require_once __DIR__ . '/includes/config.php';

$db = getDB();

echo "<h2>All Products with Variants</h2>";
$stmt = $db->query("SELECT p.id, p.name FROM products p ORDER BY p.id DESC LIMIT 5");
$products = $stmt->fetchAll();

foreach ($products as $p) {
    echo "<h3>Product #{$p['id']}: {$p['name']}</h3>";

    $vstmt = $db->prepare("SELECT * FROM product_variants WHERE product_id = ? ORDER BY id");
    $vstmt->execute([$p['id']]);
    $variants = $vstmt->fetchAll();

    echo "<table border='1'><tr><th>ID</th><th>Color</th><th>Size</th><th>Age</th><th>Price Override</th><th>Available</th></tr>";
    foreach ($variants as $v) {
        echo "<tr>";
        echo "<td>{$v['id']}</td>";
        echo "<td>{$v['color']}</td>";
        echo "<td>{$v['size']}</td>";
        echo "<td>{$v['age']}</td>";
        echo "<td>{$v['price_override']}</td>";
        echo "<td>{$v['is_available']}</td>";
        echo "</tr>";
    }
    echo "</table>";
    echo "<p>Total variants: " . count($variants) . "</p><hr>";
}
