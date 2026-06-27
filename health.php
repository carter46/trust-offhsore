<?php
/**
 * Deployment health check — delete after debugging.
 * Visit: https://your-domain.com/health.php
 */
define('HEALTH_CHECK', true);
header('Content-Type: text/plain; charset=utf-8');

echo "PHP version: " . PHP_VERSION . "\n";

$checks = [];

try {
    mysqli_report(MYSQLI_REPORT_OFF);
    require_once __DIR__ . '/config.php';
    $checks[] = 'config.php loaded';
} catch (Throwable $e) {
    echo "FAIL config.php: " . $e->getMessage() . "\n\n";
    echo "FIX: In Hostinger hPanel → Databases → MySQL Databases:\n";
    echo "  1. Confirm database name, username, and password\n";
    echo "  2. Ensure the user is assigned to the database\n";
    echo "  3. Update config.php (DB_HOST, DB_USER, DB_PASS, DB_NAME) on the server\n";
    echo "  4. Import database/u502532383_safegate.sql if tables are missing\n";
    exit(1);
}

if (!isset($conn) || !($conn instanceof mysqli)) {
    echo "FAIL: No mysqli connection object\n";
    exit(1);
}

if ($conn->connect_errno) {
    echo "FAIL database: " . $conn->connect_error . "\n\n";
    echo "FIX: In Hostinger hPanel → Databases → MySQL Databases:\n";
    echo "  1. Confirm database name, username, and password\n";
    echo "  2. Ensure the user is assigned to the database\n";
    echo "  3. Update config.php (DB_HOST, DB_USER, DB_PASS, DB_NAME) on the server\n";
    echo "  4. Import database/u502532383_safegate.sql if tables are missing\n";
    exit(1);
}
$checks[] = 'database connected';

try {
    require_once __DIR__ . '/includes/functions.php';
    $checks[] = 'functions.php loaded';
} catch (Throwable $e) {
    echo "FAIL functions.php: " . $e->getMessage() . "\n";
    exit(1);
}

$tables = ['settings', 'pages', 'shipments', 'tracking_events', 'admin_users', 'cookie_preferences'];
foreach ($tables as $table) {
    $result = $conn->query("SHOW TABLES LIKE '" . $conn->real_escape_string($table) . "'");
    if (!$result || $result->num_rows === 0) {
        echo "FAIL missing table: {$table}\n";
        exit(1);
    }
    $checks[] = "table {$table} exists";
}

$name = getSetting('company_name', 'DEFAULT');
$checks[] = "getSetting works (company_name=" . $name . ")";

$logo = getLogo('light');
$checks[] = "getLogo works ({$logo})";

echo "OK — all checks passed\n";
foreach ($checks as $line) {
    echo "  - {$line}\n";
}
