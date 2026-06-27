<?php
/**
 * Database Configuration File
 * Courier Tracking Site
 */

// Database configuration
define('DB_HOST', '127.0.0.1');
define('DB_USER', 'u502532383_safegate');
define('DB_PASS', 'Secretpass0721//');
define('DB_NAME', 'u502532383_safegate');

// Session configuration
ini_set('session.cookie_httponly', 1);
ini_set('session.use_only_cookies', 1);
// SECURITY: Set to 1 in production with HTTPS
$isProduction = false; // Change to true in production
ini_set('session.cookie_secure', $isProduction ? 1 : 0);
ini_set('session.cookie_samesite', 'Strict');

// Start session
if (session_status() === PHP_SESSION_NONE) {
    // #region agent log
    $isTrackResult = isset($_SERVER['REQUEST_URI']) && strpos($_SERVER['REQUEST_URI'], 'track-result.php') !== false;
    if ($isTrackResult) {
        $logFile = __DIR__ . '/.cursor/debug.log';
        $t0 = microtime(true);
        $logLine = json_encode([
            'sessionId' => 'track-result-504-debug',
            'runId' => 'pre-fix',
            'hypothesisId' => 'A',
            'location' => 'config.php:session_start:before',
            'message' => 'About to session_start()',
            'data' => [
                'uri' => $_SERVER['REQUEST_URI'] ?? null,
                'hasCookieHeader' => isset($_SERVER['HTTP_COOKIE']),
            ],
            'timestamp' => (int) round($t0 * 1000),
        ]) . "\n";
        @file_put_contents($logFile, $logLine, FILE_APPEND);
    }
    if (isset($_SERVER['REQUEST_URI']) && strpos($_SERVER['REQUEST_URI'], '/api/create-shipment.php') !== false) {
        // Log to site root so it's available on hosting File Manager/FTP
        $logFile = __DIR__ . '/debug.log';
        $logLine = json_encode([
            'sessionId' => 'shipment-create-timeout',
            'runId' => 'pre-fix',
            'hypothesisId' => 'H1',
            'location' => 'config.php:session_start:before',
            'message' => 'About to session_start() for create-shipment API',
            'data' => [
                'uri' => $_SERVER['REQUEST_URI'] ?? null,
                'hasCookieHeader' => isset($_SERVER['HTTP_COOKIE']),
            ],
            'timestamp' => (int) round(microtime(true) * 1000),
        ]) . "\n";
        if (@file_put_contents($logFile, $logLine, FILE_APPEND) === false) {
            error_log('AGENT_LOG ' . $logLine);
        }
    }
    // #endregion
    session_start();
    // #region agent log
    if ($isTrackResult) {
        $t1 = microtime(true);
        $logLine = json_encode([
            'sessionId' => 'track-result-504-debug',
            'runId' => 'pre-fix',
            'hypothesisId' => 'A',
            'location' => 'config.php:session_start:after',
            'message' => 'session_start() completed',
            'data' => [
                'uri' => $_SERVER['REQUEST_URI'] ?? null,
                'sessionStatus' => session_status(),
                'sessionTimeMs' => (int) round(($t1 - $t0) * 1000),
            ],
            'timestamp' => (int) round($t1 * 1000),
        ]) . "\n";
        @file_put_contents($logFile, $logLine, FILE_APPEND);
    }
    if (isset($_SERVER['REQUEST_URI']) && strpos($_SERVER['REQUEST_URI'], '/api/create-shipment.php') !== false) {
        // Log to site root so it's available on hosting File Manager/FTP
        $logFile = __DIR__ . '/debug.log';
        $logLine = json_encode([
            'sessionId' => 'shipment-create-timeout',
            'runId' => 'pre-fix',
            'hypothesisId' => 'H1',
            'location' => 'config.php:session_start:after',
            'message' => 'session_start() completed for create-shipment API',
            'data' => [
                'uri' => $_SERVER['REQUEST_URI'] ?? null,
                'sessionStatus' => session_status(),
            ],
            'timestamp' => (int) round(microtime(true) * 1000),
        ]) . "\n";
        if (@file_put_contents($logFile, $logLine, FILE_APPEND) === false) {
            error_log('AGENT_LOG ' . $logLine);
        }
    }
    // #endregion
}

// Database connection
$conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);

// Check connection
if ($conn->connect_error) {
    // SECURITY: Don't expose connection details in production
    if ($isProduction) {
        error_log("Database connection failed: " . $conn->connect_error);
        die("Database connection error. Please contact administrator.");
    } else {
        die("Connection failed: " . $conn->connect_error);
    }
}

// Set charset to utf8mb4 for proper character support
$conn->set_charset("utf8mb4");

// Error reporting (disable in production)
// SECURITY: Set to 0 in production
$isProduction = false; // Change to true in production
if ($isProduction) {
    error_reporting(0);
    ini_set('display_errors', 0);
    ini_set('log_errors', 1);
    ini_set('error_log', __DIR__ . '/error.log');
} else {
    error_reporting(E_ALL);
    ini_set('display_errors', 1);
}

// Timezone
date_default_timezone_set('UTC');

// Email Configuration (using PHPMailer)
// These can be overridden via admin settings
define('SMTP_HOST', getenv('SMTP_HOST') ?: 'smtp.hostinger.com');
define('SMTP_PORT', getenv('SMTP_PORT') ?: 465);
define('SMTP_USERNAME', getenv('SMTP_USERNAME') ?: 'info@logistics.nationaltrustofshore.com');
define('SMTP_PASSWORD', getenv('SMTP_PASSWORD') ?: 'Secretpass0721//');
define('SMTP_ENCRYPTION', getenv('SMTP_ENCRYPTION') ?: 'ssl'); // 'tls' for port 587, 'ssl' for port 465
define('SMTP_FROM_EMAIL', getenv('SMTP_FROM_EMAIL') ?: 'info@logistics.nationaltrustofshore.com');
define('SMTP_FROM_NAME', getenv('SMTP_FROM_NAME') ?: 'Shipping Company');

/**
 * Temporary admin setup key for /setup-admin.php
 * Leave empty when not in use. Set a long random string to create a new admin
 * when you cannot log in, then clear this value and delete setup-admin.php.
 */
define('ADMIN_SETUP_KEY', '');

// Helper function to get database connection
function getDBConnection() {
    global $conn;
    return $conn;
}

