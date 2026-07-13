<?php
// Fix 17: Block web access to destructive cleanup script
if (php_sapi_name() !== 'cli' && !in_array($_SERVER['REMOTE_ADDR'] ?? '', ['127.0.0.1', '::1'])) {
    http_response_code(403);
    exit('Forbidden');
}
require_once __DIR__ . '/includes/config.php';

$db = getDB();

echo "Starting Ziteo Cleanup...\n";

// Tables to drop
$tables = [
    'user_feature_flags',
    'business_categories',
    'plans'
];

foreach ($tables as $table) {
    try {
        $db->exec("DROP TABLE IF EXISTS `$table`");
        echo "Dropped table: $table\n";
    } catch (PDOException $e) {
        echo "Error dropping $table: " . $e->getMessage() . "\n";
    }
}

echo "Cleanup complete.\n";
