<?php
/**
 * EXAMPLE config — copy values into your real config.php (keep YOUR DB credentials).
 * Includes the hardened session + DB timeout settings.
 */

define('DB_HOST', 'YOUR_DB_HOST');
define('DB_USER', 'YOUR_DB_USER');
define('DB_PASS', 'YOUR_DB_PASS');
define('DB_NAME', 'YOUR_DB_NAME');

ini_set('session.use_only_cookies', '1');
ini_set('session.use_strict_mode', '1');
ini_set('session.cookie_httponly', '1');
ini_set('session.cookie_path', '/');
ini_set('session.cookie_samesite', 'Lax');

$isProduction = true;
$isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
    || (isset($_SERVER['SERVER_PORT']) && (int) $_SERVER['SERVER_PORT'] === 443)
    || (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && strtolower((string) $_SERVER['HTTP_X_FORWARDED_PROTO']) === 'https')
    || (isset($_SERVER['HTTP_X_FORWARDED_SSL']) && $_SERVER['HTTP_X_FORWARDED_SSL'] === 'on');

ini_set('session.cookie_secure', $isHttps ? '1' : '0');

if (PHP_VERSION_ID >= 70300) {
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'domain' => '',
        'secure' => $isHttps,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
} else {
    session_set_cookie_params(0, '/; samesite=Lax', '', $isHttps, true);
}

function clearStaleSessionCookies($secure = false) {
    $name = session_name();
    $expire = time() - 42000;
    foreach (['/', '/admin', '/admin/', '/admin/login.php', '/admin/dashboard.php'] as $path) {
        setcookie($name, '', $expire, $path, '', $secure, true);
        setcookie($name, '', $expire, $path, '', $secure, false);
    }
}

function writeSessionCookie($secure = false) {
    if (session_status() !== PHP_SESSION_ACTIVE) {
        return;
    }
    $id = session_id();
    if ($id === '') {
        return;
    }
    if (PHP_VERSION_ID >= 70300) {
        setcookie(session_name(), $id, [
            'expires' => 0,
            'path' => '/',
            'domain' => '',
            'secure' => $secure,
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
    } else {
        setcookie(session_name(), $id, 0, '/; samesite=Lax', '', $secure, true);
    }
}

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

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

function getDBConnection() {
    global $conn;
    return $conn;
}
