<?php
/**
 * Actions on the WordPress email gateway's unconfirmed-email ledger (Settings page):
 * retry through the gateway (same reference, never sent twice), send with the backup provider, or dismiss.
 */

require_once __DIR__ . '/includes/admin-auth.php';

function ledgerRedirect($type, $message) {
    $_SESSION[$type === 'error' ? 'settings_flash_error' : ($type === 'warning' ? 'settings_flash_warning' : 'settings_flash_success')] = $message;
    header('Location: /admin/settings.php#mail-gateway-ledger');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: /admin/settings.php#mail-gateway-ledger');
    exit;
}
if (!csrfValid()) {
    ledgerRedirect('error', 'Your session expired. Reload the page and try again.');
}

$entry = mailGatewayLedgerEntry((string) ($_POST['id'] ?? ''));
if ($entry === null) {
    ledgerRedirect('error', 'That unconfirmed email was not found.');
}
if (($entry['state'] ?? '') !== 'open') {
    ledgerRedirect('warning', 'That email was already handled.');
}

set_time_limit(60);
$action = (string) ($_POST['ledger_action'] ?? '');

if ($action === 'dismiss') {
    updateMailGatewayLedger(['id' => (string) $entry['id']], ['state' => 'dismissed', 'note' => 'Dismissed by an admin.']);
    writeAppLog('email', 'Unconfirmed gateway email dismissed', ['reference' => $entry['reference'] ?? null], 'INFO');
    ledgerRedirect('success', 'Dismissed.');
}

if ($action === 'retry') {
    if (!mailGatewayEnabled()) {
        ledgerRedirect('error', 'The WordPress email gateway is switched off.');
    }
    $result = retryMailGatewayLedgerEntry($entry);
    if (!empty($result['success'])) {
        ledgerRedirect('success', $result['message'] ?? 'Accepted by the WordPress email gateway.');
    }
    ledgerRedirect(!empty($result['uncertain']) ? 'warning' : 'error', 'Retry: ' . ($result['message'] ?? $result['error'] ?? 'failed'));
}

if ($action === 'backup') {
    $result = sendMailGatewayLedgerEntryWithBackup($entry);
    if (!empty($result['success'])) {
        ledgerRedirect('success', 'Sent with the backup provider.');
    }
    ledgerRedirect('error', 'Backup send failed: ' . ($result['error'] ?? $result['message'] ?? 'unknown error'));
}

ledgerRedirect('error', 'Unknown action.');
