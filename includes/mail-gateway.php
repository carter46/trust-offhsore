<?php
/**
 * WordPress Email Gateway: optional sending through a WordPress site running the Universal PHP Mail Gateway plugin.
 * Used by sendEmail() in functions.php; the SMTP path stays unchanged when the gateway is off.
 */

require_once __DIR__ . '/mail-gateway/MailGateway.php';

if (!defined('MAIL_GATEWAY_PROVIDER')) {
    define('MAIL_GATEWAY_PROVIDER', 'wordpress-gateway');
}

function mailGatewayEnabled() {
    return getSetting('wp_gateway_enabled', '0') === '1';
}

function mailGatewayFallbackEnabled() {
    return getSetting('wp_gateway_fallback', '1') !== '0';
}

/**
 * Also fall back when the gateway's mail system reported a failure. Off by default: that email may already have been sent.
 */
function mailGatewayFallbackAfterFailureEnabled() {
    return mailGatewayFallbackEnabled() && getSetting('wp_gateway_fallback_after_failure', '0') === '1';
}

function mailGatewayCredentials() {
    return [
        'url' => trim((string) getSetting('wp_gateway_url', '')),
        'client_id' => trim((string) getSetting('wp_gateway_client_id', '')),
        'client_secret' => trim((string) getSetting('wp_gateway_client_secret', '')),
    ];
}

/**
 * Plain http:// is only accepted for a gateway on this machine (local testing).
 */
function mailGatewayIsLocalUrl($url) {
    $host = strtolower(trim((string) parse_url((string) $url, PHP_URL_HOST), '[]'));
    return in_array($host, ['localhost', '127.0.0.1', '::1'], true);
}

/**
 * @throws MailGatewayException When the URL, Client ID or Secret is missing or malformed.
 */
function mailGatewayClient(?array $credentials = null) {
    $credentials = $credentials ?? mailGatewayCredentials();
    if ($credentials['url'] === '' || $credentials['client_id'] === '' || $credentials['client_secret'] === '') {
        throw new MailGatewayException('The Gateway URL, Client ID or Client Secret is missing.');
    }
    return new MailGateway($credentials + [
        'timeout' => 15,
        'connect_timeout' => 5,
        'allow_http' => mailGatewayIsLocalUrl($credentials['url']),
    ]);
}

function mailGatewayHost() {
    return (string) parse_url(mailGatewayCredentials()['url'], PHP_URL_HOST);
}

function mailGatewayPort() {
    $url = mailGatewayCredentials()['url'];
    $port = parse_url($url, PHP_URL_PORT);
    if ($port) {
        return (string) $port;
    }
    return strtolower((string) parse_url($url, PHP_URL_SCHEME)) === 'http' ? '80' : '443';
}

/**
 * Persistent configuration-error alert (rule C), cleared by the next successful send or test.
 */
function setMailGatewayAlert($message, $code = null) {
    updateSetting('wp_gateway_alert', json_encode([
        'message' => (string) $message,
        'code' => $code,
        'time' => time(),
    ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
}

function clearMailGatewayAlert() {
    if (getSetting('wp_gateway_alert', '') !== '') {
        updateSetting('wp_gateway_alert', '');
    }
}

function getMailGatewayAlert() {
    $alert = json_decode((string) getSetting('wp_gateway_alert', ''), true);
    return is_array($alert) && !empty($alert['message']) ? $alert : null;
}

/**
 * Read-modify-write of a JSON list setting under a MySQL named lock, trimmed to $max entries and to what fits
 * in the settings TEXT column.
 *
 * @param callable $mutate function (array &$list) { ... } ; its return value is passed through.
 */
function mailGatewayUpdateList($key, callable $mutate, $max = 200) {
    global $conn;
    $lockName = "'" . $conn->real_escape_string('mail_gateway_' . $key) . "'";
    $lock = $conn->query('SELECT GET_LOCK(' . $lockName . ', 5) AS l');
    $locked = $lock && (int) ($lock->fetch_assoc()['l'] ?? 0) === 1;
    try {
        clearSettingCache($key);
        $list = json_decode((string) getSetting($key, ''), true);
        $list = is_array($list) ? array_values($list) : [];
        $result = $mutate($list);
        $list = array_slice(array_values($list), -$max);
        $json = json_encode($list, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        while ($list && strlen((string) $json) > 60000) {
            array_shift($list);
            $json = json_encode($list, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        }
        updateSetting($key, $json === false ? '[]' : $json);
        return $result;
    } finally {
        if ($locked) {
            $conn->query('SELECT RELEASE_LOCK(' . $lockName . ')');
        }
    }
}

/**
 * Fallback events (rule A) for the last 24 hours, kept in a setting for the amber banner.
 */
function recordMailGatewayFallback($reason, $reference = null) {
    mailGatewayUpdateList('wp_gateway_fallback_log', function (array &$events) use ($reason, $reference) {
        $events[] = ['time' => time(), 'reason' => substr((string) $reason, 0, 200), 'reference' => $reference];
    });
}

function mailGatewayFallbackEvents() {
    $events = json_decode((string) getSetting('wp_gateway_fallback_log', ''), true);
    if (!is_array($events)) {
        return [];
    }
    $cutoff = time() - 86400;
    return array_values(array_filter($events, function ($event) use ($cutoff) {
        return is_array($event) && isset($event['time']) && (int) $event['time'] >= $cutoff;
    }));
}

/**
 * Ledger of gateway sends whose outcome is unknown (uncertain, or the gateway's mail system reported a failure).
 * Nothing in it is resent automatically; an admin retries it through the gateway (same reference, never sent
 * twice) or sends it with the backup provider (may duplicate).
 */
function mailGatewayLedger() {
    $entries = json_decode((string) getSetting('wp_gateway_ledger', ''), true);
    return is_array($entries) ? array_values(array_filter($entries, 'is_array')) : [];
}

function mailGatewayOpenLedgerEntries() {
    return array_values(array_filter(mailGatewayLedger(), function ($entry) {
        return ($entry['state'] ?? '') === 'open';
    }));
}

function mailGatewayLedgerEntry($id) {
    foreach (mailGatewayLedger() as $entry) {
        if (($entry['id'] ?? '') === (string) $id) {
            return $entry;
        }
    }
    return null;
}

function mailGatewayTruncate($text, $length) {
    return preg_match('/^.{0,' . (int) $length . '}/us', (string) $text, $m) ? $m[0] : substr((string) $text, 0, $length);
}

function recordMailGatewayUncertain($reference, $to, $subject, MailGatewayResult $result) {
    $fields = [
        'time' => time(),
        'to' => is_array($to) ? implode(', ', $to) : (string) $to,
        'subject' => mailGatewayTruncate($subject, 120),
        'category' => $result->category(),
        'code' => $result->errorCode(),
        'reason' => mailGatewayTruncate($result->friendlyMessage(), 200),
        'request_id' => $result->requestId(),
        'state' => 'open',
    ];
    mailGatewayUpdateList('wp_gateway_ledger', function (array &$entries) use ($reference, $fields) {
        foreach ($entries as $i => $entry) {
            if (($entry['reference'] ?? '') === $reference && ($entry['state'] ?? '') === 'open') {
                $entries[$i] = array_merge($entry, $fields);
                return;
            }
        }
        $entries[] = ['id' => bin2hex(random_bytes(6)), 'reference' => $reference, 'resends' => 0, 'note' => ''] + $fields;
    });
}

/**
 * Closes ledger entries: by entry ID, or every open entry with the given reference.
 */
function updateMailGatewayLedger(array $match, array $changes) {
    return mailGatewayUpdateList('wp_gateway_ledger', function (array &$entries) use ($match, $changes) {
        $updated = 0;
        foreach ($entries as $i => $entry) {
            $byId = isset($match['id']) && ($entry['id'] ?? '') === $match['id'];
            $byReference = isset($match['reference']) && ($entry['reference'] ?? '') === $match['reference'] && ($entry['state'] ?? '') === 'open';
            if ($byId || $byReference) {
                $entries[$i] = array_merge($entry, $changes, isset($changes['state']) && $changes['state'] !== 'open' ? ['closed_at' => time()] : []);
                $updated++;
            }
        }
        return $updated;
    });
}

/**
 * What a ledger entry was about: ['kind' => shipment|test|other, 'shipment_id', 'event_id'].
 */
function mailGatewayReferenceInfo($reference) {
    if (preg_match('/^shipment:(\d+):event:(\d+)(?::resend:\d+)?$/', (string) $reference, $m)) {
        return ['kind' => 'shipment', 'shipment_id' => (int) $m[1], 'event_id' => (int) $m[2]];
    }
    if (strpos((string) $reference, 'test:') === 0) {
        return ['kind' => 'test', 'shipment_id' => 0, 'event_id' => 0];
    }
    return ['kind' => 'other', 'shipment_id' => 0, 'event_id' => 0];
}

function getMailGatewayLastTest() {
    $test = json_decode((string) getSetting('wp_gateway_last_test', ''), true);
    return is_array($test) ? $test : null;
}

/**
 * Saves the Test connection outcome shown as the status line on the Settings page.
 */
function saveMailGatewayLastTest(array $test) {
    $test['time'] = time();
    updateSetting('wp_gateway_last_test', json_encode($test, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
}

/**
 * Plain-text alternative for an HTML email (drops <head>/<style> so CSS does not leak into the text part).
 */
function mailGatewayPlainText($html) {
    $text = preg_replace('#<(head|style|script)\b[^>]*>.*?</\1>#is', '', (string) $html);
    $text = preg_replace('#<br\s*/?>#i', "\n", $text);
    $text = preg_replace('#</(p|div|h[1-6]|tr|li|table)>#i', "\n", $text);
    $text = html_entity_decode(strip_tags($text), ENT_QUOTES | ENT_HTML5, 'UTF-8');
    $text = preg_replace("/[ \t\x{00A0}]+/u", ' ', $text);
    $text = preg_replace("/ *\n */", "\n", $text);
    return trim(preg_replace("/\n{3,}/", "\n\n", $text));
}

/**
 * Sends through the WordPress gateway and applies the fallback rules.
 *
 * Only an email the gateway provably did not send goes to the backup provider. An unknown outcome is never
 * resent automatically: it is put in the ledger for an admin to decide.
 *
 * @param string|null $reference Gateway reference (a random one is used when null).
 * @return array ['fallback' => bool, 'fallback_reason' => string|null, 'reference' => string, 'result' => sendEmail()-shaped array|null]
 */
function sendEmailViaWordPressGateway($to, $subject, $body, $isHTML, $reference = null) {
    $reference = ($reference !== null && $reference !== '') ? (string) $reference : 'mail:' . bin2hex(random_bytes(8));
    $host = mailGatewayHost();
    $base = [
        'provider' => MAIL_GATEWAY_PROVIDER,
        'host' => $host,
        'port' => mailGatewayPort(),
        'from' => null,
        'messageId' => null,
        'http_code' => null,
        'reference' => $reference,
    ];
    $logContext = [
        'provider' => MAIL_GATEWAY_PROVIDER,
        'to' => $to,
        'reference' => $reference,
        'gateway' => $host,
        'client_id' => getSetting('wp_gateway_client_id', ''),
        'client_secret' => maskSecret(getSetting('wp_gateway_client_secret', '')),
    ];
    $noFallback = ['fallback' => false, 'fallback_reason' => null, 'reference' => $reference];

    try {
        $gateway = mailGatewayClient();
    } catch (MailGatewayException $e) {
        $message = 'WordPress email gateway: ' . $e->getMessage() . ' Emails are NOT being sent. Fix in Settings.';
        setMailGatewayAlert($message, 'not_configured');
        writeAppLog('email', 'Gateway configuration error', $logContext + ['error' => $e->getMessage()], 'ERROR');
        return $noFallback + ['result' => $base + [
            'success' => false,
            'title' => 'Email gateway not configured',
            'message' => $message,
            'error' => $e->getMessage(),
        ]];
    }

    $message = [
        'to' => $to,
        'subject' => (string) $subject,
        'from_name' => getCompanyName(),
        'client_reference' => $reference,
    ];
    if ($isHTML) {
        $message['html'] = (string) $body;
        $message['text'] = mailGatewayPlainText($body);
    } else {
        $message['text'] = (string) $body;
    }
    $replyTo = trim((string) getSetting('wp_gateway_reply_to', ''));
    if ($replyTo !== '' && filter_var($replyTo, FILTER_VALIDATE_EMAIL)) {
        $message['reply_to'] = $replyTo;
    }
    if (mailGatewayFallbackEnabled()) {
        // A refused reference is closed, so a delayed copy of this request can never send it after the backup did.
        $message['close_on_refusal'] = true;
    }

    $result = $gateway->send($message);
    $logContext += [
        'category' => $result->category(),
        'http_code' => $result->httpCode(),
        'error_code' => $result->errorCode(),
        'request_id' => $result->requestId(),
        'reference_state' => $result->referenceState(),
        'attempts' => $result->attempts(),
    ];
    $base['messageId'] = $result->requestId();
    $base['http_code'] = $result->httpCode();

    if ($result->success()) {
        clearMailGatewayAlert();
        mailGatewayCloseLedgerReference($reference, 'accepted', $result->isDuplicate() ? 'Already accepted earlier; not sent again.' : 'Accepted by the gateway.');
        writeAppLog('email', $result->isDuplicate() ? 'Gateway send accepted (duplicate of earlier attempt)' : 'Gateway send accepted', $logContext, 'INFO');
        return $noFallback + ['result' => $base + [
            'success' => true,
            'message' => $result->isDuplicate() ? 'Email already accepted earlier by the WordPress email gateway (not sent again)' : 'Email accepted by the WordPress email gateway',
            'error' => null,
        ]];
    }

    $reason = $result->friendlyMessage();
    $logContext['error'] = $reason;

    if ($result->isConfigurationError()) {
        $alert = 'WordPress email gateway: ' . rtrim($reason, '.') . '. Emails are NOT being sent. Fix in Settings.';
        setMailGatewayAlert($alert, $result->errorCode());
        if ($result->category() === MailGatewayResult::BAD_SIGNATURE) {
            // The email may have been sent by whoever answered.
            recordMailGatewayUncertain($reference, $to, $subject, $result);
        }
        writeAppLog('email', 'Gateway configuration error (no fallback)', $logContext, 'ERROR');
        return $noFallback + ['result' => $base + [
            'success' => false,
            'uncertain' => $result->category() === MailGatewayResult::BAD_SIGNATURE,
            'title' => 'Email gateway configuration error',
            'message' => $alert,
            'error' => $reason,
        ]];
    }

    if ($result->shouldFallback(mailGatewayFallbackAfterFailureEnabled()) && mailGatewayFallbackEnabled()) {
        writeAppLog('email', $result->isFailedAfterDispatch() ? 'Gateway send failed; backup provider allowed by setting (may duplicate)' : 'Gateway did not send the email; backup provider allowed', $logContext, 'WARN');
        return ['fallback' => true, 'fallback_reason' => $reason, 'reference' => $reference, 'result' => null];
    }

    if ($result->isUncertain() || $result->isFailedAfterDispatch()) {
        recordMailGatewayUncertain($reference, $to, $subject, $result);
        writeAppLog('email', 'Gateway outcome uncertain (not resent automatically)', $logContext, 'ERROR');
        return $noFallback + ['result' => $base + [
            'success' => false,
            'uncertain' => true,
            'title' => 'Email outcome uncertain',
            'message' => $reason . ' It was not resent automatically. Review it under Settings → WordPress Email Gateway → Unconfirmed emails.',
            'error' => $reason,
        ]];
    }

    $title = $result->isInvalidRequest() ? 'Email rejected by the gateway' : 'Email gateway failed';
    if ($result->shouldFallback()) {
        $logContext['fallback'] = 'disabled';
    }
    writeAppLog('email', 'Gateway send failed', $logContext, 'ERROR');
    return $noFallback + ['result' => $base + [
        'success' => false,
        'title' => $title,
        'message' => $reason,
        'error' => $reason,
    ]];
}

/**
 * Closes the open ledger entry for a reference, without taking the lock when there is none.
 */
function mailGatewayCloseLedgerReference($reference, $state, $note) {
    foreach (mailGatewayOpenLedgerEntries() as $entry) {
        if (($entry['reference'] ?? '') === $reference) {
            updateMailGatewayLedger(['reference' => $reference], ['state' => $state, 'note' => $note]);
            return;
        }
    }
}

/**
 * Retries a ledger entry through the gateway with its original reference. Safe: if the gateway already
 * sent it, the answer is "already accepted" and nothing is sent again.
 *
 * @return array sendEmail()-shaped result
 */
function retryMailGatewayLedgerEntry(array $entry) {
    $reference = (string) ($entry['reference'] ?? '');
    $info = mailGatewayReferenceInfo($reference);
    if ($info['kind'] === 'shipment') {
        $email = buildShipmentNotificationEmail($info['shipment_id']);
        if (!$email['success']) {
            return $email + ['error' => $email['message'] ?? 'Shipment email could not be built'];
        }
        $gateway = sendEmailViaWordPressGateway($email['to'], $email['subject'], $email['body'], true, $reference);
    } elseif ($info['kind'] === 'test') {
        list($subject, $body) = buildTestEmail();
        $gateway = sendEmailViaWordPressGateway((string) $entry['to'], $subject, $body, true, $reference);
    } else {
        return ['success' => false, 'message' => 'This email cannot be rebuilt here, so it can only be dismissed.', 'error' => 'not_rebuildable'];
    }
    if ($gateway['fallback']) {
        $note = 'Retry refused by the gateway, so it was not sent: ' . $gateway['fallback_reason'];
        updateMailGatewayLedger(['id' => (string) $entry['id']], ['note' => mailGatewayTruncate($note, 200)]);
        return ['success' => false, 'message' => $note . ' Sending it with the backup provider will not duplicate it unless the reason is a send failure.', 'error' => $gateway['fallback_reason']];
    }
    return $gateway['result'];
}

/**
 * Sends a ledger entry with the backup provider (the admin accepted a possible duplicate).
 *
 * @return array sendEmail()-shaped result
 */
function sendMailGatewayLedgerEntryWithBackup(array $entry) {
    $reference = (string) ($entry['reference'] ?? '');
    $info = mailGatewayReferenceInfo($reference);
    if ($info['kind'] === 'shipment') {
        $email = buildShipmentNotificationEmail($info['shipment_id']);
        if (!$email['success']) {
            return $email + ['error' => $email['message'] ?? 'Shipment email could not be built'];
        }
        $result = sendEmailViaConfiguredProvider($email['to'], $email['subject'], $email['body'], true);
    } elseif ($info['kind'] === 'test') {
        list($subject, $body) = buildTestEmail();
        $result = sendEmailViaConfiguredProvider((string) $entry['to'], $subject, $body, true);
    } else {
        return ['success' => false, 'message' => 'This email cannot be rebuilt here, so it can only be dismissed.', 'error' => 'not_rebuildable'];
    }
    writeAppLog('email', $result['success'] ? 'Unconfirmed gateway email sent with the backup provider' : 'Backup send of unconfirmed gateway email failed', [
        'reference' => $reference,
        'to' => $entry['to'] ?? null,
        'provider' => $result['provider'] ?? null,
        'error' => $result['error'] ?? null,
    ], $result['success'] ? 'WARN' : 'ERROR');
    if ($result['success']) {
        updateMailGatewayLedger(['id' => (string) $entry['id']], ['state' => 'backup_sent', 'note' => 'Sent with the backup provider by an admin (may be a duplicate).']);
    }
    return $result;
}

/**
 * Red (configuration error), amber (recent fallbacks) and amber (unconfirmed emails) banners for the admin
 * dashboard and Settings page.
 */
function renderMailGatewayBanners() {
    $html = '';
    $alert = getMailGatewayAlert();
    if ($alert !== null && mailGatewayEnabled()) {
        $html .= '<div class="bg-red-100 dark:bg-red-900 text-red-700 dark:text-red-200 px-4 py-3 rounded mb-6" role="alert">'
            . htmlspecialchars($alert['message'])
            . ' <a href="/admin/settings.php#wordpress-email-gateway" class="underline font-bold">Open Settings</a></div>';
    }
    $fallbacks = count(mailGatewayFallbackEvents());
    if ($fallbacks > 0 && mailGatewayEnabled()) {
        $html .= '<div class="bg-amber-100 dark:bg-amber-900 text-amber-800 dark:text-amber-200 px-4 py-3 rounded mb-6" role="status">'
            . htmlspecialchars($fallbacks . ($fallbacks === 1 ? ' email' : ' emails') . ' used the backup provider in the last 24 h because the WordPress email gateway was unavailable.')
            . '</div>';
    }
    $open = count(mailGatewayOpenLedgerEntries());
    if ($open > 0) {
        $html .= '<div class="bg-amber-100 dark:bg-amber-900 text-amber-800 dark:text-amber-200 px-4 py-3 rounded mb-6" role="status">'
            . htmlspecialchars($open . ($open === 1 ? ' email has' : ' emails have') . ' an unconfirmed outcome: the WordPress email gateway may or may not have sent ' . ($open === 1 ? 'it' : 'them') . '. Nothing was resent automatically.')
            . ' <a href="/admin/settings.php#mail-gateway-ledger" class="underline font-bold">Review</a></div>';
    }
    return $html;
}
