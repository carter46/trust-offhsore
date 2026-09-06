<?php
/**
 * EXAMPLE config — copy to config.php on the server and fill in YOUR credentials.
 * Do not commit real passwords.
 *
 * Required hardening already included below:
 * 1) Session cookie path "/" + SameSite=Lax (stay logged in after visiting homepage)
 * 2) MySQL connect timeouts (reduce nginx 504 hangs)
 */

// Database configuration — REPLACE with this site's values
define('DB_HOST', 'YOUR_DB_HOST');
define('DB_USER', 'YOUR_DB_USER');
define('DB_PASS', 'YOUR_DB_PASS');
define('DB_NAME', 'YOUR_DB_NAME');

// Session — path MUST be "/" so admin login survives visiting the public homepage
ini_set('session.use_only_cookies', '1');
ini_set('session.use_strict_mode', '1');
ini_set('session.cookie_httponly', '1');
$isProduction = true; // true on live HTTPS hosts
$isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
    || (isset($_SERVER['SERVER_PORT']) && (int) $_SERVER['SERVER_PORT'] === 443)
    || (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https');
session_set_cookie_params([
    'lifetime' => 0,
    'path' => '/',
    'secure' => $isHttps,
    'httponly' => true,
    'samesite' => 'Lax',
]);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Database connection with short timeouts
mysqli_report(MYSQLI_REPORT_OFF);
$conn = mysqli_init();
if (!$conn) {
    die('Database connection error. Please contact administrator.');
}
@$conn->options(MYSQLI_OPT_CONNECT_TIMEOUT, 8);
if (defined('MYSQLI_OPT_READ_TIMEOUT')) {
    @$conn->options(MYSQLI_OPT_READ_TIMEOUT, 15);
}
$connected = @$conn->real_connect(DB_HOST, DB_USER, DB_PASS, DB_NAME);
if (!$connected || $conn->connect_error) {
    if ($isProduction) {
        error_log('Database connection failed: ' . ($conn->connect_error ?? 'timeout'));
        die('Database connection error. Please contact administrator.');
    }
    die('Connection failed: ' . ($conn->connect_error ?: 'Could not connect (timeout)'));
}
$conn->set_charset('utf8mb4');

if ($isProduction) {
    error_reporting(0);
    ini_set('display_errors', 0);
    ini_set('log_errors', 1);
    ini_set('error_log', __DIR__ . '/error.log');
} else {
    error_reporting(E_ALL);
    ini_set('display_errors', 1);
}

date_default_timezone_set('UTC');

// SMTP defaults (override in Admin → Settings)
if (!defined('SMTP_HOST')) {
    define('SMTP_HOST', getenv('SMTP_HOST') ?: '');
}
if (!defined('SMTP_PORT')) {
    define('SMTP_PORT', getenv('SMTP_PORT') ?: 587);
}
if (!defined('SMTP_USERNAME')) {
    define('SMTP_USERNAME', getenv('SMTP_USERNAME') ?: '');
}
if (!defined('SMTP_PASSWORD')) {
    define('SMTP_PASSWORD', getenv('SMTP_PASSWORD') ?: '');
}
if (!defined('SMTP_ENCRYPTION')) {
    define('SMTP_ENCRYPTION', getenv('SMTP_ENCRYPTION') ?: 'tls');
}
if (!defined('SMTP_FROM_EMAIL')) {
    define('SMTP_FROM_EMAIL', getenv('SMTP_FROM_EMAIL') ?: '');
}
if (!defined('SMTP_FROM_NAME')) {
    define('SMTP_FROM_NAME', getenv('SMTP_FROM_NAME') ?: 'Shipping Company');
}
if (!defined('BREVO_API_KEY')) {
    define('BREVO_API_KEY', getenv('BREVO_API_KEY') ?: '');
}

function getDBConnection() {
    global $conn;
    return $conn;
}
