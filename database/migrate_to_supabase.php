<?php

/**
 * Supabase Data & Schema Migration Tool
 * Syncs local MySQL database structure and data to Supabase PostgreSQL.
 * 
 * Usage: php database/migrate_to_supabase.php "<supabase_connection_url>"
 * Example: php database/migrate_to_supabase.php "postgresql://postgres.yourref:password@aws-0-us-east-1.pooler.supabase.com:6543/postgres"
 */

require_once __DIR__ . '/../includes/config.php';

if (php_sapi_name() !== 'cli') {
    die("This script can only be run via CLI.\n");
}

if ($argc < 2) {
    echo "Error: Missing Supabase Connection URL.\n";
    echo "Usage: php database/migrate_to_supabase.php \"postgresql://user:pass@host:port/dbname\"\n";
    exit(1);
}

$pgUrlStr = $argv[1];
$parsedUrl = parse_url($pgUrlStr);

if (!$parsedUrl || !isset($parsedUrl['host']) || !isset($parsedUrl['user'])) {
    echo "Error: Invalid connection URL format.\n";
    exit(1);
}

$pgHost = $parsedUrl['host'];
$pgPort = $parsedUrl['port'] ?? 5432;
$pgUser = $parsedUrl['user'];
$pgPass = $parsedUrl['pass'] ?? '';
$pgDb = ltrim($parsedUrl['path'] ?? 'postgres', '/');

echo "--------------------------------------------------\n";
echo "Starting Supabase Database Migration Tool\n";
echo "--------------------------------------------------\n";

// 1. Connect to local MySQL database
echo "Connecting to local MySQL (DB: " . DB_NAME . ")... ";
try {
    $mysql = getDB();
    echo "Connected.\n";
} catch (PDOException $e) {
    echo "FAILED: " . $e->getMessage() . "\n";
    exit(1);
}

// 2. Connect to remote Supabase PostgreSQL
echo "Connecting to remote Supabase PostgreSQL (Host: $pgHost)... ";
try {
    $pgDsn = sprintf("pgsql:host=%s;port=%s;dbname=%s;sslmode=require", $pgHost, $pgPort, $pgDb);
    $pgOptions = [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ];
    $postgres = new PDO($pgDsn, $pgUser, $pgPass, $pgOptions);
    echo "Connected.\n";
} catch (PDOException $e) {
    echo "FAILED: " . $e->getMessage() . "\n";
    exit(1);
}

// 3. Load and run Supabase Schema
$schemaFile = __DIR__ . '/supabase_schema.sql';
echo "Loading PostgreSQL schema from " . basename($schemaFile) . "... ";
if (!file_exists($schemaFile)) {
    echo "FAILED: File not found.\n";
    exit(1);
}
$schemaSql = file_get_contents($schemaFile);
echo "Loaded.\n";

echo "Creating database structure on Supabase... ";
try {
    $postgres->exec($schemaSql);
    echo "Tables & Indices created successfully.\n";
} catch (PDOException $e) {
    echo "FAILED to run schema: " . $e->getMessage() . "\n";
    exit(1);
}

// 4. Migrate Data for each table
$tables = [
    'admins',
    'settings',
    'categories',
    'products',
    'product_images',
    'product_variants',
    'variant_images',
    'store_product_fields',
    'plans',
    'users',
    'user_feature_flags',
    'stores'
];

foreach ($tables as $table) {
    echo "Migrating table: $table... ";
    
    // Read from MySQL
    try {
        $stmt = $mysql->query("SELECT * FROM `$table`");
        $rows = $stmt->fetchAll();
    } catch (PDOException $e) {
        // Table might not exist in local MySQL (e.g. stores, plans, etc. from unused feature gates)
        echo "Skipped (does not exist in source MySQL database).\n";
        continue;
    }
    
    if (empty($rows)) {
        echo "Done (0 rows migrated).\n";
        continue;
    }
    
    // Insert into Postgres
    $columns = array_keys($rows[0]);
    $colList = implode(', ', $columns);
    $placeholders = implode(', ', array_fill(0, count($columns), '?'));
    
    $insertSql = "INSERT INTO $table ($colList) VALUES ($placeholders)";
    $pgStmt = $postgres->prepare($insertSql);
    
    $postgres->beginTransaction();
    try {
        $count = 0;
        foreach ($rows as $row) {
            $params = [];
            foreach ($columns as $col) {
                $val = $row[$col];
                // Handle JSON conversions or boolean tinyints
                if (($table === 'categories' && ($col === 'custom_fields' || $col === 'variant_types')) ||
                    ($table === 'products' && ($col === 'custom_field_values' || $col === 'custom_attributes')) ||
                    ($table === 'plans' && $col === 'features')) {
                    // Check if already valid JSON or null, bind as parameter
                    if ($val === null) {
                        $params[] = null;
                    } else {
                        $params[] = $val;
                    }
                } else {
                    $params[] = $val;
                }
            }
            $pgStmt->execute($params);
            $count++;
        }
        $postgres->commit();
        echo "Done ($count rows migrated).\n";
    } catch (PDOException $e) {
        $postgres->rollBack();
        echo "FAILED: " . $e->getMessage() . "\n";
        exit(1);
    }
}

// 5. Update PostgreSQL sequence values to prevent auto-increment conflicts
echo "Updating PostgreSQL sequences... ";
$sequences = [
    'admins' => 'id',
    'banners' => 'id',
    'categories' => 'id',
    'products' => 'id',
    'product_images' => 'id',
    'product_variants' => 'id',
    'variant_images' => 'id',
    'stores' => 'id',
    'users' => 'id',
    'plans' => 'id'
];

try {
    foreach ($sequences as $table => $col) {
        // Query to check if table exists and has rows before resetting sequence
        $check = $postgres->query("SELECT 1 FROM pg_tables WHERE tablename = '$table'")->fetch();
        if ($check) {
            $postgres->exec("SELECT setval('{$table}_{$col}_seq', COALESCE((SELECT MAX({$col}) FROM {$table}), 0) + 1, false)");
        }
    }
    echo "Done.\n";
} catch (PDOException $e) {
    echo "FAILED: " . $e->getMessage() . "\n";
    exit(1);
}

echo "--------------------------------------------------\n";
echo "Migration successfully completed!\n";
echo "--------------------------------------------------\n";
