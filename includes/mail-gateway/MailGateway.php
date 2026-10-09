<?php
/**
 * Universal PHP Mail Gateway - PHP client library (single file, no dependencies).
 *
 * Sends signed requests to a WordPress site running the Universal PHP Mail Gateway plugin.
 * Requires PHP 7.4+ and the cURL extension.
 *
 *     $mailer = new MailGateway([
 *         'url'           => 'https://yourgateway.example',
 *         'client_id'     => 'upmg_...',
 *         'client_secret' => '64 hex characters',
 *     ]);
 *     $result = $mailer->send(['to' => 'a@example.com', 'subject' => 'Hi', 'html' => '<p>Hello</p>',
 *                              'client_reference' => 'order:1234:confirmation']);
 *     if ($result->success()) { ... } elseif ($result->shouldFallback()) { ... use your backup provider ... }
 *     elseif ($result->isUncertain()) { ... do NOT resend; the gateway may have sent it ... }
 *
 * Give every distinct email a stable client_reference: a retry with the same reference is never sent twice.
 *
 * The signing code must stay identical to the plugin's UPMG_Credentials class (see dev/tests/vectors.json).
 *
 * @version 1.1.0
 * @license GPL-2.0-or-later
 */

if (!class_exists('MailGatewayException', false)) {
    /**
     * Thrown for invalid configuration (never for send failures, which return a MailGatewayResult).
     */
    class MailGatewayException extends Exception
    {
    }
}

if (!class_exists('MailGatewayResult', false)) {
    /**
     * Outcome of a gateway call.
     */
    final class MailGatewayResult
    {
        /** wp_mail() on the gateway accepted the email (or the ping succeeded). Not proof of delivery. */
        const ACCEPTED = 'accepted';
        /** The request provably never reached a reservation on the gateway (transport, hosting edge, pre-auth refusal). */
        const NOT_DISPATCHED = 'not_dispatched';
        /** The gateway reserved the reference and refused it before sending (signed). */
        const FAILED_BEFORE_DISPATCH = 'failed_before_dispatch';
        /** The gateway's mail system was called and reported a failure; it may still have sent the email. */
        const FAILED_AFTER_DISPATCH = 'failed_after_dispatch';
        /** The outcome is unknown: the gateway may have sent the email. */
        const UNCERTAIN = 'uncertain';
        const CONFIG_ERROR = 'config_error';
        const REST_BLOCKED = 'rest_blocked';
        const BAD_SIGNATURE = 'bad_signature';
        const INVALID_REQUEST = 'invalid_request';
        /** Internal: one attempt whose outcome is ambiguous (resolved by a same-reference retry). */
        const AMBIGUOUS = 'ambiguous';
        /** Internal: another request with the same reference is running (resolved by one poll). */
        const IN_PROGRESS = 'in_progress';

        /** Categories where the email was provably not sent and a backup provider may be used. */
        const FALLBACK_CATEGORIES = array(self::NOT_DISPATCHED, self::FAILED_BEFORE_DISPATCH);

        /** Categories that mean the setup is broken. Never fall back silently. */
        const CONFIG_CATEGORIES = array(self::CONFIG_ERROR, self::REST_BLOCKED, self::BAD_SIGNATURE);

        /** @var string */
        private $category;
        /** @var int */
        private $httpCode;
        /** @var string|null */
        private $errorCode;
        /** @var string */
        private $errorMessage;
        /** @var string|null */
        private $field;
        /** @var array */
        private $data;
        /** @var string */
        private $raw;
        /** @var bool */
        private $duplicate;
        /** @var int */
        private $retryAfter;
        /** @var bool */
        private $signed;
        /** @var string|null */
        private $reference;
        /** @var int */
        private $attempts;

        /**
         * @param string      $category     One of the class constants.
         * @param int         $httpCode     HTTP status (0 if no response).
         * @param string|null $errorCode    Machine-readable code.
         * @param string      $errorMessage Human-readable message.
         * @param array       $data         Decoded JSON response.
         * @param string      $raw          Raw body (truncated).
         * @param array       $extra        field, duplicate, retry_after, signed, reference, attempts.
         */
        public function __construct($category, $httpCode = 0, $errorCode = null, $errorMessage = '', array $data = array(), $raw = '', array $extra = array())
        {
            $this->category = (string) $category;
            $this->httpCode = (int) $httpCode;
            $this->errorCode = null === $errorCode ? null : (string) $errorCode;
            $this->errorMessage = (string) $errorMessage;
            $this->data = $data;
            $this->raw = substr((string) $raw, 0, 500);
            $this->field = isset($extra['field']) ? (string) $extra['field'] : null;
            $this->duplicate = !empty($extra['duplicate']);
            $this->retryAfter = isset($extra['retry_after']) ? (int) $extra['retry_after'] : 0;
            $this->signed = !empty($extra['signed']);
            $this->reference = isset($extra['reference']) ? (string) $extra['reference'] : null;
            $this->attempts = isset($extra['attempts']) ? (int) $extra['attempts'] : 1;
        }

        /**
         * Copy with another category (used when a retry changes the meaning of an outcome).
         *
         * @param string      $category  New category.
         * @param string|null $errorCode New code (null keeps the current one).
         * @param string|null $message   New message (null keeps the current one).
         * @return MailGatewayResult
         */
        public function withCategory($category, $errorCode = null, $message = null)
        {
            $copy = clone $this;
            $copy->category = (string) $category;
            if (null !== $errorCode) {
                $copy->errorCode = (string) $errorCode;
            }
            if (null !== $message) {
                $copy->errorMessage = (string) $message;
            }
            return $copy;
        }

        /**
         * Copy carrying call-level details.
         *
         * @param string|null $reference client_reference sent.
         * @param int         $attempts  HTTP attempts made.
         * @return MailGatewayResult
         */
        public function withCall($reference, $attempts)
        {
            $copy = clone $this;
            $copy->reference = null === $reference ? null : (string) $reference;
            $copy->attempts = (int) $attempts;
            return $copy;
        }

        /** Email accepted by the gateway's WordPress mail system (or ping succeeded). */
        public function success()
        {
            return self::ACCEPTED === $this->category;
        }

        /** "accepted", "duplicate", "uncertain" or "failed". */
        public function status()
        {
            if ($this->success()) {
                return $this->duplicate ? 'duplicate' : 'accepted';
            }
            return $this->isUncertain() || $this->isFailedAfterDispatch() ? 'uncertain' : 'failed';
        }

        public function category()
        {
            return $this->category;
        }

        public function requestId()
        {
            if (isset($this->data['request_id']) && is_string($this->data['request_id'])) {
                return $this->data['request_id'];
            }
            return isset($this->data['original']['request_id']) ? (string) $this->data['original']['request_id'] : null;
        }

        public function httpCode()
        {
            return $this->httpCode;
        }

        public function errorCode()
        {
            return $this->errorCode;
        }

        public function errorMessage()
        {
            return $this->errorMessage;
        }

        public function field()
        {
            return $this->field;
        }

        /** The client_reference sent with the email (null for ping). */
        public function clientReference()
        {
            return $this->reference;
        }

        /** Number of HTTP attempts made for this call. */
        public function attempts()
        {
            return $this->attempts;
        }

        /** Whether the response signature was verified with this site's secret. */
        public function isSigned()
        {
            return $this->signed;
        }

        /** The gateway's state for the reference (signed responses only), for example "accepted" or "unknown". */
        public function referenceState()
        {
            return $this->signed && isset($this->data['reference_state']) && is_string($this->data['reference_state']) ? $this->data['reference_state'] : null;
        }

        /**
         * The email was provably not sent: a backup provider may be used.
         *
         * @param bool $afterSendFailure Also fall back when the gateway's mail system reported a failure
         *                               (failed_after_dispatch). Off by default because it may send twice.
         * @return bool
         */
        public function shouldFallback($afterSendFailure = false)
        {
            return in_array($this->category, self::FALLBACK_CATEGORIES, true)
                || ($afterSendFailure && self::FAILED_AFTER_DISPATCH === $this->category);
        }

        /** Alias of shouldFallback(). */
        public function isRetryable()
        {
            return $this->shouldFallback();
        }

        /** The setup is broken (wrong credentials, disabled site, wrong URL, blocked REST API, bad signature). Never fall back silently. */
        public function isConfigurationError()
        {
            return in_array($this->category, self::CONFIG_CATEGORIES, true);
        }

        /** The request itself is invalid and would fail on any provider. */
        public function isInvalidRequest()
        {
            return self::INVALID_REQUEST === $this->category;
        }

        /** The outcome is unknown: the gateway may have sent the email. Do not fall back automatically. */
        public function isUncertain()
        {
            return self::UNCERTAIN === $this->category;
        }

        /** The gateway's mail system was called and reported a failure. It may still have sent the email. */
        public function isFailedAfterDispatch()
        {
            return self::FAILED_AFTER_DISPATCH === $this->category;
        }

        /** The gateway host answered with a browser challenge page (for example InfinityFree free). */
        public function isBlockedByHost()
        {
            return 'host_blocked' === $this->errorCode;
        }

        /** A retry matched an earlier accepted request with the same client_reference. */
        public function isDuplicate()
        {
            return $this->duplicate;
        }

        public function retryAfter()
        {
            return $this->retryAfter;
        }

        public function data()
        {
            return $this->data;
        }

        /** Truncated raw response body, for diagnostics. */
        public function raw()
        {
            return $this->raw;
        }

        /** Detected mail delivery on the gateway: {detected, method, provider}. */
        public function mailDelivery()
        {
            return isset($this->data['mail_delivery']) && is_array($this->data['mail_delivery']) ? $this->data['mail_delivery'] : array();
        }

        public function gatewayName()
        {
            return isset($this->data['gateway']['name']) ? (string) $this->data['gateway']['name'] : null;
        }

        public function clientName()
        {
            return isset($this->data['client']['name']) ? (string) $this->data['client']['name'] : null;
        }

        public function serverTime()
        {
            return isset($this->data['server_time']) ? (string) $this->data['server_time'] : null;
        }

        /** A readable explanation for admin screens. */
        public function friendlyMessage()
        {
            if ($this->success()) {
                return $this->duplicate ? 'Already accepted earlier (same client_reference); not sent again.' : 'Accepted by the WordPress mail system.';
            }
            $messages = array(
                'authentication_failed' => 'Wrong Client ID or Secret (or the secret was rotated).',
                'client_disabled' => 'This site is disabled on the gateway.',
                'credential_revoked' => 'This secret was rotated and its grace period has ended. Paste the new secret.',
                'nonce_reused' => 'The request nonce was reused (client bug).',
                'ip_not_allowed' => 'This server\'s IP address is not in the gateway\'s allowlist for this site.',
                'https_required' => 'The gateway only accepts HTTPS. Use an https:// Gateway URL.',
                'route_not_found' => 'Gateway URL is not a WordPress Mail Gateway (route missing or plugin inactive).',
                'redirect' => 'The Gateway URL redirects. Use the final https:// address.',
                'rest_blocked' => 'The WordPress REST API is restricted by a security plugin. Allow the upmg namespace.',
                'host_blocked' => 'Gateway host blocks API requests (browser challenge, e.g. InfinityFree free hosting).',
                'bad_signature' => 'The gateway response was not signed with this site\'s secret (wrong URL or interception). The email may have been sent.',
                'mail_unavailable' => 'No mail delivery plugin is active on the gateway.',
                'gateway_disabled' => 'The gateway is switched off.',
                'gateway_key_unavailable' => 'The gateway cannot read this site\'s credentials (its encryption key changed).',
                'upgrade_pending' => 'The gateway is finishing an update.',
                'rate_limited' => 'The gateway rate limit was reached.',
                'payload_too_large' => 'The email is larger than the gateway accepts.',
                'exceeds_server_limit' => 'The email is larger than the gateway\'s server accepts (post_max_size).',
                'edge_rejected' => 'The gateway\'s web server or firewall rejected the request before WordPress saw it.',
                'mail_send_failed' => 'The gateway\'s mail system reported a failure. It may still have sent the email, so it is not resent automatically.',
                'reference_unknown' => 'The gateway stopped while sending this email; whether it went out is unknown.',
                'in_progress' => 'The gateway is still handling this email.',
            );
            if ('timestamp_out_of_range' === $this->errorCode) {
                return 'Clock difference too large between this server and the gateway' . (null !== $this->serverTime() ? ' (gateway time ' . $this->serverTime() . ').' : '.');
            }
            if ('connection_failed' === $this->errorCode) {
                return 'Could not reach the gateway: ' . $this->errorMessage;
            }
            if (null !== $this->errorCode && isset($messages[$this->errorCode])) {
                return $messages[$this->errorCode];
            }
            if (self::UNCERTAIN === $this->category) {
                return 'The gateway did not give a clear answer; the email may or may not have been sent.';
            }
            return '' !== $this->errorMessage ? $this->errorMessage : 'Gateway request failed (HTTP ' . $this->httpCode . ').';
        }

        public function toArray()
        {
            return array(
                'success' => $this->success(),
                'status' => $this->status(),
                'category' => $this->category,
                'http_code' => $this->httpCode,
                'error_code' => $this->errorCode,
                'error_message' => $this->errorMessage,
                'request_id' => $this->requestId(),
                'reference' => $this->reference,
                'reference_state' => $this->referenceState(),
                'duplicate' => $this->duplicate,
                'attempts' => $this->attempts,
            );
        }
    }
}

if (!class_exists('MailGateway', false)) {
    /**
     * Gateway client.
     */
    final class MailGateway
    {
        const VERSION = '1.1.0';
        const SCHEME = 'UPMG1';
        const HEALTH_ROUTE = '/mail-gateway/health';
        const TIMESTAMP_WINDOW = 300;

        /** Codes the gateway sends before it has verified the request, so they are never signed. */
        const PRE_AUTH_CODES = array(
            'invalid_headers', 'authentication_failed', 'timestamp_out_of_range', 'client_disabled',
            'rate_limited', 'payload_too_large', 'exceeds_server_limit', 'gateway_key_unavailable',
        );

        /** Codes returned by security plugins that block the REST API for logged-out requests. */
        const REST_BLOCK_CODES = array(
            'rest_not_logged_in', 'rest_disabled', 'rest_forbidden', 'rest_cannot_access',
            'rest_login_required', 'rest_authentication_error', 'rest_api_disabled', 'rest_unauthorized',
        );

        /** @var string */
        private $url;
        /** @var string */
        private $clientId;
        /** @var string */
        private $clientSecret;
        /** @var array */
        private $options;
        /** @var bool */
        private $useQueryRoute;

        /**
         * @param array $config url, client_id, client_secret, timeout (15), connect_timeout (5), verify_ssl (true),
         *                      use_query_route (false), allow_http (false), encode_body (true), user_agent,
         *                      close_on_refusal (false: ask the gateway to close a reference it refuses, so a
         *                      later retry with the same reference cannot send it; use it when you fall back
         *                      to another mailer), max_poll_wait (5 seconds).
         * @throws MailGatewayException On invalid configuration.
         */
        public function __construct(array $config)
        {
            if (!function_exists('curl_init')) {
                throw new MailGatewayException('MailGateway needs the PHP cURL extension.');
            }
            if (PHP_VERSION_ID < 70400) {
                throw new MailGatewayException('MailGateway needs PHP 7.4 or later.');
            }
            $this->options = array_merge(
                array(
                    'timeout' => 15,
                    'connect_timeout' => 5,
                    'verify_ssl' => true,
                    'use_query_route' => false,
                    'allow_http' => false,
                    'encode_body' => true,
                    'user_agent' => 'MailGatewayClient/' . self::VERSION . ' PHP/' . PHP_VERSION,
                    'close_on_refusal' => false,
                    'max_poll_wait' => 5,
                    'transport' => null,
                    'sleep' => null,
                ),
                $config
            );

            $url = rtrim(trim((string) ($config['url'] ?? '')), '/');
            $scheme = strtolower((string) parse_url($url, PHP_URL_SCHEME));
            if ('' === $url || !in_array($scheme, array('http', 'https'), true) || !parse_url($url, PHP_URL_HOST)) {
                throw new MailGatewayException('The gateway URL must be a full http(s) address, for example https://mail.example.com.');
            }
            if ('https' !== $scheme && empty($this->options['allow_http'])) {
                throw new MailGatewayException('The gateway URL must use https:// (set allow_http only for local testing).');
            }
            $clientId = trim((string) ($config['client_id'] ?? ''));
            if (!preg_match('/^upmg_[a-f0-9]{24}$/', $clientId)) {
                throw new MailGatewayException('The Client ID looks wrong. It starts with upmg_ followed by 24 characters.');
            }
            $secret = strtolower(trim((string) ($config['client_secret'] ?? '')));
            if (!preg_match('/^[a-f0-9]{64}$/', $secret)) {
                throw new MailGatewayException('The Client Secret looks wrong. It is 64 hexadecimal characters.');
            }

            $this->url = $url;
            $this->clientId = $clientId;
            $this->clientSecret = $secret;
            $this->useQueryRoute = !empty($this->options['use_query_route']);
        }

        /**
         * Builds a client from the single "connection key" copied from WordPress.
         *
         * @param string $key     Connection key.
         * @param array  $options Extra options (timeouts etc.).
         * @return MailGateway
         * @throws MailGatewayException On an invalid key.
         */
        public static function fromConnectionKey($key, array $options = array())
        {
            return new self(array_merge($options, self::parseConnectionKey($key)));
        }

        /**
         * Decodes a connection key into url, client_id and client_secret.
         *
         * @param string $key Connection key.
         * @return array
         * @throws MailGatewayException On an invalid key.
         */
        public static function parseConnectionKey($key)
        {
            $json = base64_decode(trim((string) $key), true);
            $data = false === $json ? null : json_decode($json, true);
            if (!is_array($data) || empty($data['url']) || empty($data['client_id']) || empty($data['client_secret'])) {
                throw new MailGatewayException('The connection key is not valid. Copy it again from Mail Gateway > Connected Sites.');
            }
            return array(
                'url' => (string) $data['url'],
                'client_id' => (string) $data['client_id'],
                'client_secret' => (string) $data['client_secret'],
            );
        }

        /**
         * Signed connection test. Optionally reports this site's name and URL to the gateway.
         *
         * @param array $siteInfo site_name, site_url.
         * @return MailGatewayResult
         */
        public function ping(array $siteInfo = array())
        {
            $body = array();
            foreach (array('site_name', 'site_url') as $key) {
                if (isset($siteInfo[$key]) && is_string($siteInfo[$key])) {
                    $body[$key] = $siteInfo[$key];
                }
            }
            return $this->request('/upmg/v1/ping', $this->encode($body), false);
        }

        /**
         * Sends an email.
         *
         * @param array $message to, cc, bcc, subject, html, text, from_name, reply_to, attachments, client_reference.
         *                       There is no From email option: the gateway's WordPress decides it.
         * @return MailGatewayResult
         */
        public function send(array $message)
        {
            $payload = $this->buildPayload($message);
            if ($payload instanceof MailGatewayResult) {
                return $payload;
            }
            $json = $this->encode($payload);
            if ('' === $json) {
                return new MailGatewayResult(MailGatewayResult::INVALID_REQUEST, 0, 'invalid_request', 'The message could not be encoded as JSON (check that all text is UTF-8).');
            }
            return $this->request('/upmg/v1/send', $json, true, $payload['client_reference']);
        }

        /**
         * Reads a file into an attachment entry.
         *
         * @param string      $path File path.
         * @param string|null $name File name shown to the recipient.
         * @param string|null $type Content type.
         * @return array
         * @throws MailGatewayException When the file cannot be read.
         */
        public static function attachmentFromFile($path, $name = null, $type = null)
        {
            if (!is_file($path) || !is_readable($path)) {
                throw new MailGatewayException('Attachment file not found or not readable.');
            }
            $data = file_get_contents($path);
            if (false === $data) {
                throw new MailGatewayException('Attachment file could not be read.');
            }
            if (null === $type && function_exists('finfo_open')) {
                $finfo = finfo_open(FILEINFO_MIME_TYPE);
                $type = $finfo ? (string) finfo_file($finfo, $path) : null;
            }
            return array(
                'filename' => null !== $name ? (string) $name : basename($path),
                'content_type' => null !== $type ? (string) $type : 'application/octet-stream',
                'content' => base64_encode($data),
            );
        }

        /**
         * Verifies a signed request from the gateway (for the site's health endpoint).
         *
         * @param array  $headers      Request headers (any case; HTTP_X_UPMG_* $_SERVER keys also work).
         * @param string $body         Raw request body.
         * @param string $clientId     This site's Client ID.
         * @param string $clientSecret This site's Client Secret.
         * @param int    $window       Allowed clock difference in seconds.
         * @return bool
         */
        public static function verifyIncomingHealthRequest(array $headers, $body, $clientId, $clientSecret, $window = self::TIMESTAMP_WINDOW)
        {
            $h = self::normalizeHeaders($headers);
            $id = $h['x-upmg-client-id'] ?? '';
            $ts = $h['x-upmg-timestamp'] ?? '';
            $nonce = $h['x-upmg-nonce'] ?? '';
            $sig = strtolower($h['x-upmg-signature'] ?? '');
            if ('' === (string) $clientId || '' === (string) $clientSecret || !hash_equals((string) $clientId, (string) $id)) {
                return false;
            }
            if (!preg_match('/^\d{1,12}$/', $ts) || !preg_match('/^[A-Za-z0-9_-]{22,64}$/', $nonce) || !preg_match('/^[a-f0-9]{64}$/', $sig)) {
                return false;
            }
            if (abs(time() - (int) $ts) > (int) $window) {
                return false;
            }
            $expected = self::sign($clientSecret, 'POST', self::HEALTH_ROUTE, $clientId, $ts, $nonce, (string) $body);
            return hash_equals($expected, $sig);
        }

        /**
         * Builds the health endpoint's answer to a request from the gateway (pure function: no output).
         *
         * @param string $method  HTTP method.
         * @param array  $headers Request headers.
         * @param string $body    Raw request body.
         * @param array  $config  client_id, client_secret, site_name, site_url, gateway_enabled.
         * @return array status (int), headers (array), body (string)
         */
        public static function healthResponse($method, array $headers, $body, array $config)
        {
            $json = function ($status, array $data, $sign = false) use ($headers, $config) {
                $out = json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
                $responseHeaders = array(
                    'Content-Type' => 'application/json; charset=utf-8',
                    'Cache-Control' => 'no-store, no-cache, must-revalidate, max-age=0',
                    'X-Content-Type-Options' => 'nosniff',
                );
                if ($sign) {
                    $h = self::normalizeHeaders($headers);
                    $responseHeaders['X-UPMG-Response-Signature'] = self::signResponse((string) $config['client_secret'], (string) ($h['x-upmg-nonce'] ?? ''), $status, $out);
                }
                return array('status' => $status, 'headers' => $responseHeaders, 'body' => $out);
            };

            if ('POST' !== strtoupper((string) $method)) {
                return $json(405, array('ok' => false, 'error' => 'method_not_allowed'));
            }
            if (empty($config['client_id']) || empty($config['client_secret'])) {
                return $json(503, array('ok' => false, 'error' => 'not_configured'));
            }
            if (strlen((string) $body) > 65536 || !self::verifyIncomingHealthRequest($headers, (string) $body, (string) $config['client_id'], (string) $config['client_secret'])) {
                return $json(401, array('ok' => false, 'error' => 'invalid_signature'));
            }
            return $json(200, array(
                'ok' => true,
                'site' => (string) ($config['site_name'] ?? ''),
                'site_url' => (string) ($config['site_url'] ?? ''),
                'gateway_enabled' => !empty($config['gateway_enabled']),
                'client_version' => self::VERSION,
                'time' => gmdate('c'),
            ), true);
        }

        /**
         * Sends a healthResponse() result to the browser/gateway.
         *
         * @param array $response Result of healthResponse().
         */
        public static function emitHealthResponse(array $response)
        {
            if (!headers_sent()) {
                http_response_code((int) $response['status']);
                foreach ($response['headers'] as $name => $value) {
                    header($name . ': ' . $value);
                }
            }
            echo $response['body'];
        }

        /**
         * Request headers of the current PHP request, normalised (for verifyIncomingHealthRequest()).
         *
         * @return array
         */
        public static function incomingHeaders()
        {
            $headers = array();
            foreach ($_SERVER as $key => $value) {
                if (0 === strpos((string) $key, 'HTTP_')) {
                    $headers[strtolower(str_replace('_', '-', substr($key, 5)))] = (string) $value;
                }
            }
            return $headers;
        }

        /**
         * Canonical UPMG1 string to sign.
         */
        public static function stringToSign($method, $route, $clientId, $timestamp, $nonce, $body)
        {
            return implode("\n", array(
                self::SCHEME,
                strtoupper((string) $method),
                (string) $route,
                (string) $clientId,
                (string) $timestamp,
                (string) $nonce,
                hash('sha256', (string) $body),
            ));
        }

        /**
         * Request signature (lowercase hex HMAC-SHA256).
         */
        public static function sign($secret, $method, $route, $clientId, $timestamp, $nonce, $body)
        {
            return hash_hmac('sha256', self::stringToSign($method, $route, $clientId, $timestamp, $nonce, $body), (string) $secret);
        }

        /**
         * Response signature: request nonce + HTTP status + SHA-256 of the exact response body.
         */
        public static function signResponse($secret, $nonce, $status, $body)
        {
            $string = implode("\n", array(self::SCHEME . '-RESPONSE', (string) $nonce, (string) (int) $status, hash('sha256', (string) $body)));
            return hash_hmac('sha256', $string, (string) $secret);
        }

        /**
         * New random nonce (32 URL-safe characters).
         */
        public static function nonce()
        {
            return rtrim(strtr(base64_encode(random_bytes(24)), '+/', '-_'), '=');
        }

        /* ------------------------------------------------------------------ internals */

        /**
         * Validates the message and builds the JSON payload.
         *
         * @param array $message Message.
         * @return array|MailGatewayResult
         */
        private function buildPayload(array $message)
        {
            foreach (array('from', 'from_email', 'sender', 'sender_email', 'headers') as $forbidden) {
                if (array_key_exists($forbidden, $message)) {
                    return $this->invalid('The From email is controlled by the gateway; remove "' . $forbidden . '".', $forbidden);
                }
            }

            $payload = array();
            foreach (array('to', 'cc', 'bcc') as $field) {
                $list = $this->normalizeRecipients($message[$field] ?? array());
                if (null === $list) {
                    return $this->invalid('Invalid email address in "' . $field . '".', $field);
                }
                if ($list) {
                    $payload[$field] = $list;
                }
            }
            if (empty($payload['to'])) {
                return $this->invalid('At least one "to" recipient is required.', 'to');
            }

            $subject = trim((string) ($message['subject'] ?? ''));
            if ('' === $subject || false !== strpbrk($subject, "\r\n")) {
                return $this->invalid('A single-line subject is required.', 'subject');
            }
            $payload['subject'] = $subject;

            $html = (string) ($message['html'] ?? '');
            $text = (string) ($message['text'] ?? '');
            if ('' === trim($html) && '' === trim($text)) {
                return $this->invalid('An html or text body is required.', 'html');
            }
            if (!empty($this->options['encode_body'])) {
                if ('' !== $html) {
                    $payload['html_b64'] = base64_encode($html);
                }
                if ('' !== $text) {
                    $payload['text_b64'] = base64_encode($text);
                }
            } else {
                if ('' !== $html) {
                    $payload['html'] = $html;
                }
                if ('' !== $text) {
                    $payload['text'] = $text;
                }
            }

            if (isset($message['from_name']) && '' !== (string) $message['from_name']) {
                $payload['from_name'] = (string) $message['from_name'];
            }

            if (!empty($message['reply_to'])) {
                $reply = $this->normalizeRecipients(array($message['reply_to']));
                if (null === $reply) {
                    return $this->invalid('Invalid reply_to address.', 'reply_to');
                }
                $payload['reply_to'] = $reply[0];
            }

            if (!empty($message['attachments'])) {
                $attachments = array();
                foreach ((array) $message['attachments'] as $item) {
                    if (!is_array($item) || empty($item['filename']) || (!isset($item['content']) && !isset($item['data']))) {
                        return $this->invalid('Each attachment needs filename and content (base64) or data (raw bytes).', 'attachments');
                    }
                    $attachments[] = array(
                        'filename' => basename(str_replace('\\', '/', (string) $item['filename'])),
                        'content_type' => isset($item['content_type']) ? (string) $item['content_type'] : 'application/octet-stream',
                        'content' => isset($item['content']) ? (string) $item['content'] : base64_encode((string) $item['data']),
                    );
                }
                $payload['attachments'] = $attachments;
            }

            $reference = isset($message['client_reference']) ? (string) $message['client_reference'] : '';
            if ('' === $reference) {
                $reference = 'auto-' . bin2hex(random_bytes(12));
            } elseif (!preg_match('/^[A-Za-z0-9._:\-]{1,100}$/', $reference)) {
                return $this->invalid('client_reference must be 1-100 characters of letters, digits, dot, underscore, colon or dash.', 'client_reference');
            }
            $payload['client_reference'] = $reference;
            if (!empty($this->options['close_on_refusal']) || !empty($message['close_on_refusal'])) {
                $payload['close_on_refusal'] = true;
            }

            return $payload;
        }

        /**
         * Normalises recipients to a list of strings or {email, name}; null if any address is invalid.
         *
         * @param mixed $value Raw value.
         * @return array|null
         */
        private function normalizeRecipients($value)
        {
            if (is_string($value)) {
                $value = '' === trim($value) ? array() : array($value);
            }
            if (is_array($value) && isset($value['email'])) {
                $value = array($value);
            }
            if (!is_array($value)) {
                return null;
            }
            $out = array();
            foreach ($value as $item) {
                if (is_string($item)) {
                    $email = trim($item);
                    $name = '';
                } elseif (is_array($item) && isset($item['email'])) {
                    $email = trim((string) $item['email']);
                    $name = trim((string) ($item['name'] ?? ''));
                } else {
                    return null;
                }
                if (!filter_var($email, FILTER_VALIDATE_EMAIL) || false !== strpbrk($email . $name, "\r\n")) {
                    return null;
                }
                $out[] = '' !== $name ? array('email' => $email, 'name' => $name) : $email;
            }
            return $out;
        }

        /**
         * Validation failure result.
         */
        private function invalid($message, $field)
        {
            return new MailGatewayResult(MailGatewayResult::INVALID_REQUEST, 0, 'invalid_request', $message, array(), '', array('field' => $field));
        }

        /**
         * JSON-encodes a payload ('' on failure).
         */
        private function encode(array $payload)
        {
            $json = json_encode((object) $payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
            return false === $json ? '' : $json;
        }

        /**
         * Signed POST with the retry rules:
         * - an ambiguous outcome (post-connect cURL error, unsigned or non-gateway 5xx, non-JSON 2xx) is retried
         *   once with the same body and client_reference; the gateway never sends a reference twice;
         * - "in progress" is polled once after Retry-After;
         * - an automatic ?rest_route= retry when /wp-json/ is not routed.
         *
         * @param string      $route      Canonical route.
         * @param string      $body       JSON body.
         * @param bool        $allowRetry Whether ambiguous outcomes may be retried (send only).
         * @param string|null $reference  client_reference in the body.
         * @return MailGatewayResult
         */
        private function request($route, $body, $allowRetry, $reference = null)
        {
            $attempts = 1;
            $result = $this->attempt($route, $body);

            if (!$this->useQueryRoute && 404 === $result->httpCode() && in_array($result->errorCode(), array('route_not_found', 'rest_no_route'), true)) {
                $this->useQueryRoute = true;
                $attempts++;
                $probe = $this->attempt($route, $body);
                $notGateway = 404 === $probe->httpCode()
                    || (MailGatewayResult::NOT_DISPATCHED === $probe->category() && 0 === $probe->httpCode());
                if ($notGateway) {
                    $this->useQueryRoute = false;
                    return $result->withCall($reference, $attempts);
                }
                // The probe reached something: from here on it is the attempt that counts.
                $result = $probe;
            }

            if (!$allowRetry) {
                return $this->finalize($result)->withCall($reference, $attempts);
            }

            if (MailGatewayResult::IN_PROGRESS === $result->category()) {
                $attempts++;
                return $this->poll($route, $body, $result)->withCall($reference, $attempts);
            }

            if (MailGatewayResult::AMBIGUOUS !== $result->category()) {
                return $this->finalize($result)->withCall($reference, $attempts);
            }

            // Ambiguous first attempt: one retry with the same body and reference (new nonce).
            $attempts++;
            $retry = $this->attempt($route, $body);
            switch ($retry->category()) {
                case MailGatewayResult::ACCEPTED:
                case MailGatewayResult::FAILED_AFTER_DISPATCH:
                case MailGatewayResult::CONFIG_ERROR:
                case MailGatewayResult::REST_BLOCKED:
                case MailGatewayResult::INVALID_REQUEST:
                    return $retry->withCall($reference, $attempts);
                case MailGatewayResult::FAILED_BEFORE_DISPATCH:
                    // Only a signed reference state proves the reference was refused; a version 1.0
                    // gateway's refusal does not, because the first attempt may have been sent.
                    if (null !== $retry->referenceState()) {
                        return $retry->withCall($reference, $attempts);
                    }
                    return $this->uncertain($retry)->withCall($reference, $attempts);
                case MailGatewayResult::IN_PROGRESS:
                    $attempts++;
                    return $this->poll($route, $body, $retry)->withCall($reference, $attempts);
                default:
                    // Ambiguous again, bad signature, unknown, or a pre-connect failure on the retry.
                    return $this->uncertain($result)->withCall($reference, $attempts);
            }
        }

        /**
         * Waits for Retry-After (capped) and asks once more about an in-progress reference.
         *
         * @param string            $route   Route.
         * @param string            $body    Body.
         * @param MailGatewayResult $pending The in-progress answer.
         * @return MailGatewayResult
         */
        private function poll($route, $body, MailGatewayResult $pending)
        {
            $wait = max(1, min((int) $this->options['max_poll_wait'], $pending->retryAfter() > 0 ? $pending->retryAfter() : 2));
            $sleep = $this->options['sleep'];
            if (is_callable($sleep)) {
                $sleep($wait);
            } else {
                sleep($wait);
            }
            $answer = $this->attempt($route, $body);
            if (in_array($answer->category(), array(MailGatewayResult::ACCEPTED, MailGatewayResult::FAILED_AFTER_DISPATCH), true)) {
                return $answer;
            }
            if (MailGatewayResult::FAILED_BEFORE_DISPATCH === $answer->category() && null !== $answer->referenceState()) {
                return $answer;
            }
            return $this->uncertain($pending, 'in_progress', 'The gateway is still handling this email; the outcome is not known yet. It will not be sent twice.');
        }

        /**
         * Converts internal categories of a final attempt.
         */
        private function finalize(MailGatewayResult $result)
        {
            if (MailGatewayResult::AMBIGUOUS === $result->category() || MailGatewayResult::IN_PROGRESS === $result->category()) {
                return $this->uncertain($result);
            }
            return $result;
        }

        /**
         * Uncertain result based on an attempt.
         */
        private function uncertain(MailGatewayResult $result, $code = null, $message = null)
        {
            return $result->withCategory(MailGatewayResult::UNCERTAIN, $code, $message);
        }

        /**
         * One signed HTTP attempt.
         */
        private function attempt($route, $body)
        {
            $timestamp = (string) time();
            $nonce = self::nonce();
            $headers = array(
                'Content-Type: application/json',
                'Accept: application/json',
                'X-UPMG-Client-Id: ' . $this->clientId,
                'X-UPMG-Timestamp: ' . $timestamp,
                'X-UPMG-Nonce: ' . $nonce,
                'X-UPMG-Signature: ' . self::sign($this->clientSecret, 'POST', $route, $this->clientId, $timestamp, $nonce, $body),
                'Expect:',
            );
            $url = $this->useQueryRoute ? $this->url . '/?rest_route=' . $route : $this->url . '/wp-json' . $route;
            $transport = $this->options['transport'];
            $http = is_callable($transport) ? $transport($url, $headers, $body, $this->options) : self::post($url, $headers, $body, $this->options);
            return $this->interpret(array_merge(array('status' => 0, 'headers' => array(), 'body' => '', 'errno' => 0, 'error' => '', 'connected' => true), (array) $http), $nonce);
        }

        /**
         * Performs the HTTP POST.
         *
         * @return array status, headers, body, errno, error, connected
         */
        private static function post($url, array $headers, $body, array $options)
        {
            $responseHeaders = array();
            $ch = curl_init($url);
            curl_setopt_array($ch, array(
                CURLOPT_POST => true,
                CURLOPT_POSTFIELDS => $body,
                CURLOPT_HTTPHEADER => $headers,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_FOLLOWLOCATION => false,
                CURLOPT_TIMEOUT => max(1, (int) $options['timeout']),
                CURLOPT_CONNECTTIMEOUT => max(1, (int) $options['connect_timeout']),
                CURLOPT_SSL_VERIFYPEER => !empty($options['verify_ssl']),
                CURLOPT_SSL_VERIFYHOST => !empty($options['verify_ssl']) ? 2 : 0,
                CURLOPT_USERAGENT => (string) $options['user_agent'],
                CURLOPT_PROTOCOLS => CURLPROTO_HTTP | CURLPROTO_HTTPS,
                CURLOPT_HEADERFUNCTION => function ($ch, $line) use (&$responseHeaders) {
                    $parts = explode(':', $line, 2);
                    if (2 === count($parts)) {
                        $responseHeaders[strtolower(trim($parts[0]))] = trim($parts[1]);
                    }
                    return strlen($line);
                },
            ));
            $responseBody = curl_exec($ch);
            $errno = curl_errno($ch);
            $error = curl_error($ch);
            $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
            // Timing fields are filled in even after a failed connect on some cURL builds; request bytes are not.
            $connected = (int) curl_getinfo($ch, CURLINFO_REQUEST_SIZE) > 0;
            curl_close($ch);

            return array(
                'status' => $status,
                'headers' => $responseHeaders,
                'body' => false === $responseBody ? '' : (string) $responseBody,
                'errno' => $errno,
                'error' => $error,
                'connected' => $connected,
            );
        }

        /**
         * Classifies one HTTP attempt.
         *
         * Gateway error codes are trusted only from signed responses, except the codes the gateway sends
         * before it has verified the request (PRE_AUTH_CODES), which cannot be signed.
         */
        private function interpret(array $http, $nonce)
        {
            $status = (int) $http['status'];
            $body = (string) $http['body'];
            $retryAfter = (int) ($http['headers']['retry-after'] ?? 0);

            if (0 !== (int) $http['errno']) {
                $preConnect = array(6, 7, 35, 51, 53, 54, 58, 59, 60, 77, 83, 90, 91);
                if (in_array((int) $http['errno'], $preConnect, true) || empty($http['connected'])) {
                    return new MailGatewayResult(MailGatewayResult::NOT_DISPATCHED, 0, 'connection_failed', self::cleanCurlError($http['error']));
                }
                return new MailGatewayResult(MailGatewayResult::AMBIGUOUS, $status, 'timeout', self::cleanCurlError($http['error']));
            }

            if ($status >= 300 && $status < 400) {
                $location = $http['headers']['location'] ?? '';
                return new MailGatewayResult(MailGatewayResult::CONFIG_ERROR, $status, 'redirect', 'The gateway URL redirects' . ('' !== $location ? ' to ' . $location : '') . '.', array(), $body);
            }

            if (self::looksLikeChallenge($body)) {
                return new MailGatewayResult(MailGatewayResult::NOT_DISPATCHED, $status, 'host_blocked', 'The gateway host answered with a browser challenge page.', array(), $body);
            }

            $data = json_decode($body, true);
            if (!is_array($data)) {
                return $this->interpretNonJson($status, $body, $retryAfter);
            }

            $ours = array_key_exists('success', $data) || array_key_exists('ok', $data);
            $code = null;
            $message = '';
            $field = null;
            if (isset($data['error']) && is_array($data['error'])) {
                $code = isset($data['error']['code']) ? (string) $data['error']['code'] : null;
                $message = isset($data['error']['message']) ? (string) $data['error']['message'] : '';
                $field = isset($data['error']['field']) ? (string) $data['error']['field'] : null;
            } elseif (isset($data['code'])) {
                $code = (string) $data['code'];
                $message = isset($data['message']) ? (string) $data['message'] : '';
                $ours = false;
            }

            if (null !== $code && in_array($code, self::REST_BLOCK_CODES, true)) {
                return new MailGatewayResult(MailGatewayResult::REST_BLOCKED, $status, 'rest_blocked', $message, $data, $body);
            }
            if ('rest_no_route' === $code || (404 === $status && !$ours)) {
                return new MailGatewayResult(MailGatewayResult::CONFIG_ERROR, $status, 'route_not_found', $message, $data, $body);
            }
            if (!$ours && (401 === $status || 403 === $status)) {
                return new MailGatewayResult(MailGatewayResult::REST_BLOCKED, $status, 'rest_blocked', $message, $data, $body);
            }

            $signature = strtolower($http['headers']['x-upmg-response-signature'] ?? '');
            $signed = '' !== $signature && hash_equals(self::signResponse($this->clientSecret, $nonce, $status, $body), $signature);
            if ('' !== $signature && !$signed) {
                return new MailGatewayResult(MailGatewayResult::BAD_SIGNATURE, $status, 'bad_signature', 'The response signature is invalid.', array(), $body);
            }
            $extra = array('field' => $field, 'retry_after' => $retryAfter, 'signed' => $signed);

            if (!$signed) {
                return $this->interpretUnsigned($status, $code, $message, $data, $body, $extra, $ours);
            }

            if (200 === $status && (!empty($data['success']) || !empty($data['ok']))) {
                return new MailGatewayResult(MailGatewayResult::ACCEPTED, $status, null, '', $data, $body, $extra);
            }

            if (isset($data['reference_state']) && is_string($data['reference_state'])) {
                return $this->interpretState($data['reference_state'], $status, $code, $message, $data, $body, $extra);
            }
            return $this->interpretLegacy($status, $code, $message, $data, $body, $extra);
        }

        /**
         * Non-JSON answers: hosting edge, PHP output before the JSON, cached pages.
         */
        private function interpretNonJson($status, $body, $retryAfter)
        {
            if (404 === $status) {
                return new MailGatewayResult(MailGatewayResult::CONFIG_ERROR, $status, 'route_not_found', 'The gateway route was not found.', array(), $body);
            }
            if (401 === $status || 403 === $status) {
                return new MailGatewayResult(MailGatewayResult::REST_BLOCKED, $status, 'rest_blocked', 'The request was blocked before reaching the gateway (security plugin or firewall).', array(), $body);
            }
            if (413 === $status || 429 === $status) {
                // Web server, CDN or firewall limits answer before WordPress runs.
                return new MailGatewayResult(MailGatewayResult::NOT_DISPATCHED, $status, 'edge_rejected', 'The gateway\'s web server rejected the request (HTTP ' . $status . ').', array(), $body, array('retry_after' => $retryAfter));
            }
            return new MailGatewayResult(MailGatewayResult::AMBIGUOUS, $status, $status >= 500 ? 'server_error' : 'invalid_response', 'The gateway did not return JSON (HTTP ' . $status . ').', array(), $body);
        }

        /**
         * Unsigned JSON answers: only pre-auth gateway codes are believed.
         */
        private function interpretUnsigned($status, $code, $message, array $data, $body, array $extra, $ours)
        {
            if ($ours && null !== $code && in_array($code, self::PRE_AUTH_CODES, true)) {
                if (in_array($code, array('rate_limited', 'gateway_key_unavailable'), true)) {
                    return new MailGatewayResult(MailGatewayResult::NOT_DISPATCHED, $status, $code, $message, $data, $body, $extra);
                }
                if (in_array($code, array('payload_too_large', 'exceeds_server_limit'), true)) {
                    return new MailGatewayResult(MailGatewayResult::INVALID_REQUEST, $status, $code, $message, $data, $body, $extra);
                }
                return new MailGatewayResult(MailGatewayResult::CONFIG_ERROR, $status, $code, $message, $data, $body, $extra);
            }
            if (($status >= 200 && $status < 300) || 409 === $status) {
                return new MailGatewayResult(MailGatewayResult::BAD_SIGNATURE, $status, 'bad_signature', 'The response signature is missing.', array(), $body);
            }
            if (401 === $status || 403 === $status) {
                return new MailGatewayResult(MailGatewayResult::CONFIG_ERROR, $status, $code, $message, $data, $body, $extra);
            }
            if (!$ours && (413 === $status || 429 === $status)) {
                return new MailGatewayResult(MailGatewayResult::NOT_DISPATCHED, $status, 'edge_rejected', $message, $data, $body, $extra);
            }
            if (in_array($status, array(400, 415, 422), true)) {
                return new MailGatewayResult(MailGatewayResult::INVALID_REQUEST, $status, $code, $message, $data, $body, $extra);
            }
            // WordPress fatal-error JSON, proxy errors, anything else unsigned.
            return new MailGatewayResult(MailGatewayResult::AMBIGUOUS, $status, null !== $code ? $code : ($status >= 500 ? 'server_error' : 'invalid_response'), $message, $data, $body, $extra);
        }

        /**
         * Signed answers carrying the gateway's state for the reference.
         */
        private function interpretState($state, $status, $code, $message, array $data, $body, array $extra)
        {
            switch ($state) {
                case 'accepted':
                    return new MailGatewayResult(MailGatewayResult::ACCEPTED, $status, null, '', $data, $body, array_merge($extra, array('duplicate' => 409 === $status)));
                case 'in_progress':
                    return new MailGatewayResult(MailGatewayResult::IN_PROGRESS, $status, 'in_progress', $message, $data, $body, $extra);
                case 'failed_after_dispatch':
                    return new MailGatewayResult(MailGatewayResult::FAILED_AFTER_DISPATCH, $status, null !== $code && 'duplicate_request' !== $code ? $code : 'mail_send_failed', $message, $data, $body, $extra);
                case 'unknown':
                    return new MailGatewayResult(MailGatewayResult::UNCERTAIN, $status, 'reference_unknown', $message, $data, $body, $extra);
                case 'failed_before_dispatch':
                case 'refused_closed':
                case 'none':
                    if (in_array($status, array(400, 413, 415, 422), true)) {
                        return new MailGatewayResult(MailGatewayResult::INVALID_REQUEST, $status, $code, $message, $data, $body, $extra);
                    }
                    if (401 === $status || 403 === $status || 404 === $status) {
                        return new MailGatewayResult(MailGatewayResult::CONFIG_ERROR, $status, $code, $message, $data, $body, $extra);
                    }
                    if (409 === $status && 'refused_closed' !== $state) {
                        return new MailGatewayResult(MailGatewayResult::UNCERTAIN, $status, $code, $message, $data, $body, $extra);
                    }
                    return new MailGatewayResult('none' === $state ? MailGatewayResult::NOT_DISPATCHED : MailGatewayResult::FAILED_BEFORE_DISPATCH, $status, $code, $message, $data, $body, $extra);
            }
            return new MailGatewayResult(MailGatewayResult::UNCERTAIN, $status, $code, $message, $data, $body, $extra);
        }

        /**
         * Signed answers without a reference state (ping, or a version 1.0 gateway).
         */
        private function interpretLegacy($status, $code, $message, array $data, $body, array $extra)
        {
            if (409 === $status && 'duplicate_request' === $code) {
                $original = isset($data['original']['status']) ? (string) $data['original']['status'] : '';
                if ('accepted' === $original) {
                    return new MailGatewayResult(MailGatewayResult::ACCEPTED, $status, null, '', $data, $body, array_merge($extra, array('duplicate' => true)));
                }
                // Version 1.0 reported "processing" for requests that may still be sending.
                return new MailGatewayResult(MailGatewayResult::UNCERTAIN, $status, $code, $message, $data, $body, $extra);
            }
            if (in_array($status, array(400, 409, 413, 415, 422), true)) {
                return new MailGatewayResult(MailGatewayResult::INVALID_REQUEST, $status, $code, $message, $data, $body, $extra);
            }
            if (401 === $status || 403 === $status || 404 === $status) {
                return new MailGatewayResult(MailGatewayResult::CONFIG_ERROR, $status, $code, $message, $data, $body, $extra);
            }
            if (502 === $status && 'mail_send_failed' === $code) {
                return new MailGatewayResult(MailGatewayResult::FAILED_AFTER_DISPATCH, $status, $code, $message, $data, $body, $extra);
            }
            if (429 === $status || 503 === $status) {
                // Version 1.0 refused these before sending.
                return new MailGatewayResult(MailGatewayResult::FAILED_BEFORE_DISPATCH, $status, $code, $message, $data, $body, $extra);
            }
            return new MailGatewayResult(MailGatewayResult::AMBIGUOUS, $status, null !== $code ? $code : 'server_error', $message, $data, $body, $extra);
        }

        /**
         * Recognises host browser-challenge pages (for example InfinityFree's aes.js check).
         */
        public static function looksLikeChallenge($body)
        {
            $sample = strtolower(substr((string) $body, 0, 4000));
            return false !== strpos($sample, 'aes.js')
                || false !== strpos($sample, 'slowaes')
                || false !== strpos($sample, '__test=')
                || false !== strpos($sample, 'requires javascript');
        }

        /**
         * Shortens cURL errors.
         */
        private static function cleanCurlError($error)
        {
            return substr(preg_replace('/\s+/', ' ', (string) $error), 0, 200);
        }

        /**
         * Lower-cases header names and accepts $_SERVER-style HTTP_X_UPMG_* keys.
         */
        private static function normalizeHeaders(array $headers)
        {
            $out = array();
            foreach ($headers as $key => $value) {
                $key = strtolower((string) $key);
                if (0 === strpos($key, 'http_')) {
                    $key = substr($key, 5);
                }
                $out[str_replace('_', '-', $key)] = is_array($value) ? (string) reset($value) : trim((string) $value);
            }
            return $out;
        }
    }
}
