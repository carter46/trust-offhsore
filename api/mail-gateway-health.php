<?php
/**
 * Signed health endpoint for the WordPress email gateway's "Check connection" button.
 * Only answers requests signed with this site's Client Secret; everything else gets 401 without a signature.
 */

ini_set('display_errors', 0);
ob_start();

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/functions.php';
ob_end_clean();

$credentials = mailGatewayCredentials();
$siteUrl = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' ? 'https://' : 'http://') . ($_SERVER['HTTP_HOST'] ?? '');

MailGateway::emitHealthResponse(
    MailGateway::healthResponse(
        $_SERVER['REQUEST_METHOD'] ?? 'GET',
        MailGateway::incomingHeaders(),
        (string) file_get_contents('php://input', false, null, 0, 65537),
        [
            'client_id' => $credentials['client_id'],
            'client_secret' => $credentials['client_secret'],
            'site_name' => getCompanyName(),
            'site_url' => $siteUrl,
            'gateway_enabled' => mailGatewayEnabled(),
        ]
    )
);
