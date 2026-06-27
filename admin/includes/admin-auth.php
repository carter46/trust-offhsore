<?php
/**
 * Admin Authentication Check
 * Include this file at the top of admin pages that require authentication
 */

require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../includes/functions.php';

if (!isAdminLoggedIn()) {
    header('Location: /admin/login.php');
    exit;
}

