<?php

/**
 * Database Migration Runner
 */

require_once __DIR__ . '/includes/config.php';

// Ensure we are running in CLI or authorized environment
if (php_sapi_name() !== 'cli' && ENVIRONMENT !== 'development') {
    die('Access denied');
}

echo "Starting Database Migration...\n";

$db = getDB();

// Migration files to run
$files = [
    __DIR__ . '/database/flex_migration.sql'
];

foreach ($files as $file) {
    echo "Running " . basename($file) . "... ";

    if (!file_exists($file)) {
        echo "File not found!\n";
        continue;
    }

    $sql = file_get_contents($file);

    try {
        // executeMultiQuery equivalent logic
        // PDO doesn't support multiple queries in one prepare statement easily depending on driver settings,
        // but executing raw SQL string usually works if emulation is on or driver allows.
        // For safety, we'll try to execute it raw.

        $db->exec($sql);
        echo "Done.\n";
    } catch (PDOException $e) {
        echo "Error: " . $e->getMessage() . "\n";
    }
}

echo "Migration completed.\n";
