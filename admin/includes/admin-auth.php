<?php
/**
 * Admin Authentication Check
 * Include this file at the top of admin pages that require authentication.
 * Also runs pending database auto-migrations (idempotent, once per request).
 */

require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../includes/functions.php';

if (!isAdminLoggedIn()) {
    header('Location: /admin/login.php');
    exit;
}

// Apply pending DB auto-migrations on every admin page load (idempotent)
try {
    require_once __DIR__ . '/../../includes/database-auto-migrate.php';
    runAdminDatabaseAutoMigrations($_SESSION['admin_user_id'] ?? null);
} catch (Throwable $migrateError) {
    error_log('Auto-migration runner failed: ' . $migrateError->getMessage());
}

