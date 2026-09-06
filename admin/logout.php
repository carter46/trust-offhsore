<?php
require_once __DIR__ . '/../config.php';

$_SESSION = [];

global $isHttps;
$secure = !empty($isHttps);

if (function_exists('clearStaleSessionCookies')) {
    clearStaleSessionCookies($secure);
} elseif (ini_get('session.use_cookies')) {
    $params = session_get_cookie_params();
    setcookie(
        session_name(),
        '',
        time() - 42000,
        $params['path'] ?? '/',
        $params['domain'] ?? '',
        !empty($params['secure']),
        !empty($params['httponly'])
    );
}

session_destroy();
header('Location: /admin/login.php');
exit;
