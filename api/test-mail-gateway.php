<?php
/**
 * Test connection to the WordPress email gateway (admin only).
 * Uses the values typed on the Settings page; a blank secret means "the saved secret".
 */

ini_set('display_errors', 0);
ob_start();
header('Content-Type: application/json');
header('Cache-Control: no-store');

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/functions.php';
ob_clean();

function testMailGatewayRespond($status, array $data) {
    ob_end_clean();
    http_response_code($status);
    echo json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    exit;
}

if (!isAdminLoggedIn()) {
    testMailGatewayRespond(401, ['success' => false, 'message' => 'Unauthorized']);
}
if ($_SERVER['REQUEST_METHOD'] !== 'POST' || stripos((string) ($_SERVER['CONTENT_TYPE'] ?? ''), 'application/json') !== 0) {
    testMailGatewayRespond(405, ['success' => false, 'message' => 'Method not allowed']);
}
if (!csrfValid()) {
    testMailGatewayRespond(403, ['success' => false, 'message' => 'Your session expired. Reload the page and try again.']);
}

set_time_limit(40);
$input = json_decode((string) file_get_contents('php://input'), true);
$input = is_array($input) ? $input : [];

$saved = mailGatewayCredentials();
$credentials = [
    'url' => isset($input['url']) ? trim((string) $input['url']) : $saved['url'],
    'client_id' => isset($input['client_id']) ? trim((string) $input['client_id']) : $saved['client_id'],
    'client_secret' => trim((string) ($input['client_secret'] ?? '')) !== '' ? trim((string) $input['client_secret']) : $saved['client_secret'],
];
$fingerprint = hash('sha256', $credentials['url'] . "\n" . $credentials['client_id'] . "\n" . $credentials['client_secret']);
$logContext = [
    'gateway' => (string) parse_url($credentials['url'], PHP_URL_HOST),
    'client_id' => $credentials['client_id'],
    'client_secret' => maskSecret($credentials['client_secret']),
];

try {
    $gateway = mailGatewayClient($credentials);
} catch (MailGatewayException $e) {
    saveMailGatewayLastTest(['ok' => false, 'message' => $e->getMessage(), 'fingerprint' => $fingerprint]);
    writeAppLog('email', 'Gateway test: invalid settings', $logContext + ['error' => $e->getMessage()], 'WARN');
    testMailGatewayRespond(200, ['success' => false, 'message' => $e->getMessage()]);
}

$siteUrl = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' ? 'https://' : 'http://') . ($_SERVER['HTTP_HOST'] ?? '');
$result = $gateway->ping(['site_name' => getCompanyName(), 'site_url' => $siteUrl]);

if ($result->success()) {
    $delivery = $result->mailDelivery();
    $deliveryLabel = !empty($delivery['detected']) && trim((string) ($delivery['provider'] ?? '')) !== ''
        ? trim((string) $delivery['provider'])
        : (!empty($delivery['detected']) ? 'detected' : 'none detected');
    $data = $result->data();
    $warnings = [];
    if (empty($data['gateway']['enabled'])) {
        $warnings[] = 'The gateway is switched off on the WordPress site.';
    }
    if (isset($delivery['sending_allowed']) && !$delivery['sending_allowed']) {
        $warnings[] = 'No mail delivery plugin was detected on the WordPress site, so sends will be refused.';
    }
    $message = sprintf(
        'Connected to %s as "%s" · Mail delivery: %s',
        (string) $result->gatewayName(),
        (string) $result->clientName(),
        $deliveryLabel
    );
    saveMailGatewayLastTest(['ok' => true, 'message' => $message, 'warnings' => $warnings, 'fingerprint' => $fingerprint]);
    if ($fingerprint === hash('sha256', $saved['url'] . "\n" . $saved['client_id'] . "\n" . $saved['client_secret'])) {
        clearMailGatewayAlert();
    }
    writeAppLog('email', 'Gateway test connection succeeded', $logContext + ['gateway_name' => $result->gatewayName(), 'mail_delivery' => $deliveryLabel], 'INFO');
    testMailGatewayRespond(200, ['success' => true, 'message' => $message, 'warnings' => $warnings]);
}

$message = $result->friendlyMessage();
saveMailGatewayLastTest(['ok' => false, 'message' => $message, 'fingerprint' => $fingerprint]);
writeAppLog('email', 'Gateway test connection failed', $logContext + [
    'category' => $result->category(),
    'http_code' => $result->httpCode(),
    'error_code' => $result->errorCode(),
    'error' => $message,
], 'WARN');
testMailGatewayRespond(200, ['success' => false, 'message' => $message, 'category' => $result->category(), 'http_code' => $result->httpCode()]);
