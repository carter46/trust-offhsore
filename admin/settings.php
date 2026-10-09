<?php
/**
 * Settings (Admin)
 * All POST handling runs before admin-header.php is included, so every save can redirect (Post/Redirect/Get).
 */
require_once __DIR__ . '/includes/admin-auth.php';

// Saved trimmed instead of through sanitizeInput(): HTML-escaping would corrupt passwords, secrets and URLs.
$rawSecretKeys = [
    'smtp_host',
    'smtp_port',
    'smtp_username',
    'smtp_password',
    'smtp_encryption',
    'smtp_from_email',
    'smtp_from_name',
    'email_logo_url',
    'wp_gateway_url',
    'wp_gateway_client_id',
    'wp_gateway_client_secret',
    'wp_gateway_reply_to',
];

/**
 * Detect the real image type of an uploaded file and map it to a safe extension.
 * Returns '' when the file is not one of the allowed image types.
 */
function detectBrandImageExtension($tmpPath, array $allowedExtensions) {
    $mimeToExt = [
        'image/png' => 'png',
        'image/jpeg' => 'jpg',
        'image/gif' => 'gif',
        'image/webp' => 'webp',
        'image/x-icon' => 'ico',
        'image/vnd.microsoft.icon' => 'ico',
    ];

    $info = @getimagesize($tmpPath);
    if ($info === false || empty($info['mime']) || !isset($mimeToExt[$info['mime']])) {
        return '';
    }
    $ext = $mimeToExt[$info['mime']];

    if (function_exists('finfo_open')) {
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        if ($finfo) {
            $realMime = finfo_file($finfo, $tmpPath);
            finfo_close($finfo);
            if (!isset($mimeToExt[$realMime]) || $mimeToExt[$realMime] !== $ext) {
                return '';
            }
        }
    }

    return in_array($ext, $allowedExtensions, true) ? $ext : '';
}

/**
 * Delete a previously uploaded brand file, but only if it is one of ours inside /asset/.
 */
function deleteUploadedBrandFile($storedPath, $prefix, $assetDir) {
    $name = basename((string) $storedPath);
    if ($name === '' || strpos($name, $prefix) !== 0 || strpos((string) $storedPath, '/asset/') !== 0) {
        return;
    }
    $fullPath = $assetDir . $name;
    if (is_file($fullPath)) {
        @unlink($fullPath);
    }
}

/**
 * Validate and store an uploaded brand image. Returns ['ok' => bool, 'message' => string].
 */
function storeBrandUpload(array $file, $settingKey, $prefix, array $allowedExtensions, $maxSize, $assetDir) {
    if ($file['size'] <= 0 || $file['size'] > $maxSize) {
        return ['ok' => false, 'message' => 'file is too large'];
    }
    $ext = detectBrandImageExtension($file['tmp_name'], $allowedExtensions);
    if ($ext === '') {
        return ['ok' => false, 'message' => 'file type is not allowed'];
    }

    $newFileName = $prefix . bin2hex(random_bytes(8)) . '.' . $ext;
    if (!move_uploaded_file($file['tmp_name'], $assetDir . $newFileName)) {
        return ['ok' => false, 'message' => 'file could not be saved'];
    }

    deleteUploadedBrandFile(getSetting($settingKey, ''), $prefix, $assetDir);
    updateSetting($settingKey, '/asset/' . $newFileName);
    return ['ok' => true, 'message' => ''];
}

function settingsTestEmailRespond($status, array $data) {
    http_response_code($status);
    echo json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    exit;
}

// ---- AJAX: send test email (posted to this page so the logged-in admin session is always available) ----
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'send_test_email') {
    header('Content-Type: application/json');
    header('Cache-Control: no-store');
    set_time_limit(60);

    if (!csrfValid()) {
        settingsTestEmailRespond(403, ['success' => false, 'message' => 'Your session expired. Reload the page and try again.']);
    }

    $testEmail = filter_var(trim((string) ($_POST['email'] ?? '')), FILTER_VALIDATE_EMAIL);
    if (!$testEmail) {
        settingsTestEmailRespond(400, ['success' => false, 'message' => 'Please enter a valid email address.']);
    }

    try {
        list($subject, $body) = buildTestEmail();
        $reference = 'test:' . bin2hex(random_bytes(8));
        $result = sendEmail($testEmail, $subject, $body, true, null, null, $reference);

        writeAppLog(
            'email',
            $result['success'] ? 'Test email sent' : (!empty($result['uncertain']) ? 'Test email outcome uncertain' : 'Test email failed'),
            [
                'to' => $testEmail,
                'reference' => $reference,
                'provider' => $result['provider'] ?? null,
                'host' => $result['host'] ?? null,
                'port' => $result['port'] ?? null,
                'messageId' => $result['messageId'] ?? null,
                'http_code' => $result['http_code'] ?? null,
                'fallback_reason' => $result['fallback_reason'] ?? null,
                'error' => $result['error'] ?? null,
            ],
            $result['success'] ? 'INFO' : 'ERROR'
        );

        if ($result['success']) {
            settingsTestEmailRespond(200, [
                'success' => true,
                'message' => 'Test email accepted for delivery to ' . $testEmail,
                'provider' => $result['provider'] ?? null,
                'messageId' => $result['messageId'] ?? null,
                'fallback_reason' => $result['fallback_reason'] ?? null,
            ]);
        }

        settingsTestEmailRespond(500, [
            'success' => false,
            'uncertain' => !empty($result['uncertain']),
            'title' => $result['title'] ?? 'Email Error',
            'message' => $result['message'] ?? 'Email failed',
            'provider' => $result['provider'] ?? null,
            'host' => $result['host'] ?? null,
            'port' => $result['port'] ?? null,
            'http_code' => $result['http_code'] ?? null,
            'error' => $result['error'] ?? ($result['message'] ?? 'Unknown error'),
        ]);
    } catch (Throwable $e) {
        writeAppLog('email', 'Test email exception', ['error' => $e->getMessage()], 'ERROR');
        settingsTestEmailRespond(500, ['success' => false, 'title' => 'Email Error', 'message' => 'Error: ' . $e->getMessage(), 'error' => $e->getMessage()]);
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && empty($_POST) && empty($_FILES) && (int) ($_SERVER['CONTENT_LENGTH'] ?? 0) > 0) {
    $_SESSION['settings_flash_error'] = 'The upload was larger than the server allows, so nothing was saved. Use a smaller image.';
    header('Location: /admin/settings.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !csrfValid()) {
    $_SESSION['settings_flash_error'] = 'Your session expired, so the settings were not saved. Reload the page and try again.';
    header('Location: /admin/settings.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $assetDir = __DIR__ . '/../asset/';
    $updatedKeys = [];
    $success = '';
    $error = '';

    $brandAssets = [
        'logo' => [
            'label' => 'Logo',
            'setting' => 'company_logo',
            'prefix' => 'logo_uploaded_',
            'extensions' => ['png', 'jpg', 'gif', 'webp'],
            'maxSize' => 5 * 1024 * 1024,
            'help' => 'PNG, JPG, GIF, or WEBP (max 5MB)',
        ],
        'favicon' => [
            'label' => 'Favicon',
            'setting' => 'site_favicon',
            'prefix' => 'favicon_uploaded_',
            'extensions' => ['png', 'jpg', 'gif', 'webp', 'ico'],
            'maxSize' => 2 * 1024 * 1024,
            'help' => 'PNG, JPG, GIF, WEBP, or ICO (max 2MB)',
        ],
    ];

    foreach ($brandAssets as $field => $cfg) {
        $upload = $_FILES[$field . '_upload'] ?? null;
        if ($upload && $upload['error'] === UPLOAD_ERR_OK) {
            $result = storeBrandUpload($upload, $cfg['setting'], $cfg['prefix'], $cfg['extensions'], $cfg['maxSize'], $assetDir);
            if ($result['ok']) {
                $updatedKeys[] = $cfg['setting'];
                $success = trim($success . ' ' . $cfg['label'] . ' uploaded successfully!');
            } else {
                $error = trim($error . ' Invalid ' . strtolower($cfg['label']) . ' (' . $result['message'] . '). Please upload ' . $cfg['help'] . '.');
                writeAppLog('settings', $cfg['label'] . ' upload rejected', ['reason' => $result['message']], 'WARN');
            }
        } elseif ($upload && $upload['error'] !== UPLOAD_ERR_NO_FILE) {
            $reason = in_array($upload['error'], [UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE], true) ? 'file is too large for the server' : 'upload failed';
            $error = trim($error . ' ' . $cfg['label'] . ' was not uploaded (' . $reason . ').');
            writeAppLog('settings', $cfg['label'] . ' upload error', ['code' => $upload['error']], 'WARN');
        }
    }

    // Color settings (validate hex format)
    foreach (['primary_color', 'secondary_color'] as $colorKey) {
        if (isset($_POST['setting_' . $colorKey])) {
            $color = trim((string) $_POST['setting_' . $colorKey]);
            if (preg_match('/^#[0-9A-Fa-f]{6}$/', $color)) {
                updateSetting($colorKey, strtoupper($color));
                $updatedKeys[] = $colorKey;
            }
        }
    }

    // Text settings
    foreach ($_POST as $key => $value) {
        if (strpos($key, 'setting_') !== 0 || $key === 'setting_primary_color' || $key === 'setting_secondary_color') {
            continue;
        }
        $settingKey = substr($key, strlen('setting_'));

        // Blank SMTP password / gateway secret = keep existing
        if (in_array($settingKey, ['smtp_password', 'wp_gateway_client_secret'], true) && trim((string) $value) === '') {
            continue;
        }

        if (in_array($settingKey, ['wp_gateway_enabled', 'wp_gateway_fallback', 'wp_gateway_fallback_after_failure'], true)) {
            $cleanValue = ((string) $value === '1') ? '1' : '0';
        } elseif (in_array($settingKey, $rawSecretKeys, true)) {
            $cleanValue = trim((string) $value);
            if ($settingKey === 'smtp_encryption') {
                $cleanValue = strtolower($cleanValue);
                if (!in_array($cleanValue, ['tls', 'ssl', 'none'], true)) {
                    $cleanValue = 'tls';
                }
            }
            if ($settingKey === 'email_logo_url' && $cleanValue !== ''
                && (!preg_match('#^https://[^\s/]+/\S+$#i', $cleanValue) || filter_var($cleanValue, FILTER_VALIDATE_URL) === false)) {
                $error = trim($error . ' The Email Logo Link must be a full image link starting with https://.');
                continue;
            }
            if ($settingKey === 'wp_gateway_url') {
                $cleanValue = preg_replace('#/wp-json/?$#i', '', rtrim($cleanValue, '/'));
                if ($cleanValue !== '' && !preg_match('#^https?://[^\s/]+#i', $cleanValue)) {
                    $error = trim($error . ' The WordPress Site URL must start with https://.');
                    continue;
                }
            }
            if ($settingKey === 'wp_gateway_client_id' || $settingKey === 'wp_gateway_client_secret') {
                $cleanValue = strtolower($cleanValue);
            }
            if ($settingKey === 'wp_gateway_reply_to' && $cleanValue !== '' && !filter_var($cleanValue, FILTER_VALIDATE_EMAIL)) {
                $error = trim($error . ' The gateway Reply-To address is not a valid email address.');
                continue;
            }
        } else {
            $cleanValue = sanitizeInput($value);
        }

        updateSetting($settingKey, $cleanValue);
        $updatedKeys[] = $settingKey;
    }

    if (!empty($updatedKeys)) {
        $secretSet = ['smtp_password', 'wp_gateway_client_secret', 'smartsupp_key', 'google_maps_api_key'];
        writeAppLog('settings', 'Settings updated', [
            'keys' => array_values(array_diff($updatedKeys, $secretSet)),
            'secrets_updated' => array_values(array_intersect($updatedKeys, $secretSet)),
        ], 'INFO');
    }

    if (empty($success) && empty($error)) {
        $success = 'Settings updated successfully!';
    }

    if (mailGatewayEnabled()) {
        $savedGateway = mailGatewayCredentials();
        $lastTest = getMailGatewayLastTest();
        $savedFingerprint = hash('sha256', $savedGateway['url'] . "\n" . $savedGateway['client_id'] . "\n" . $savedGateway['client_secret']);
        if (empty($lastTest['ok']) || ($lastTest['fingerprint'] ?? '') !== $savedFingerprint) {
            $_SESSION['settings_flash_warning'] = 'The WordPress email gateway is switched on, but these settings have not passed Test connection yet. Emails will be sent through the gateway; use Test connection to confirm it works.';
        }
    }

    if ($success !== '') {
        $_SESSION['settings_flash_success'] = $success;
    }
    if ($error !== '') {
        $_SESSION['settings_flash_error'] = $error;
    }
    header('Location: /admin/settings.php');
    exit;
}

$success = (string) ($_SESSION['settings_flash_success'] ?? '');
$error = (string) ($_SESSION['settings_flash_error'] ?? '');
$warning = (string) ($_SESSION['settings_flash_warning'] ?? '');
unset($_SESSION['settings_flash_success'], $_SESSION['settings_flash_error'], $_SESSION['settings_flash_warning']);

include __DIR__ . '/includes/admin-header.php';

// Get all settings (safe - no user input)
$settings = [];
$result = $conn->query("SELECT setting_key, setting_value FROM settings");
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $value = $row['setting_value'];
        // Text settings are stored HTML-escaped; show them as plain text so re-saving does not double-escape.
        if (!in_array($row['setting_key'], $rawSecretKeys, true) && $value !== null) {
            $value = html_entity_decode((string) $value, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        }
        $settings[$row['setting_key']] = $value;
    }
    $result->free();
}

$gatewayEnabled = ($settings['wp_gateway_enabled'] ?? '0') === '1';
$gatewayFallback = ($settings['wp_gateway_fallback'] ?? '1') !== '0';
$gatewayFallbackAfterFailure = ($settings['wp_gateway_fallback_after_failure'] ?? '0') === '1';
$gatewayLastTest = getMailGatewayLastTest();
$gatewayLedger = array_reverse(mailGatewayLedger());
$ledgerLabels = [
    'open' => 'Unconfirmed',
    'accepted' => 'Accepted by gateway',
    'backup_sent' => 'Sent with SMTP',
    'dismissed' => 'Dismissed',
];
?>
<div class="mb-8">
    <h1 class="text-3xl font-light text-gray-800 dark:text-white mb-2">Settings</h1>
    <p class="text-gray-600 dark:text-gray-400">Manage site settings and configuration</p>
</div>

<?php if ($success): ?>
    <div class="bg-green-100 dark:bg-green-900 text-green-700 dark:text-green-200 px-4 py-3 rounded mb-6">
        <?php echo htmlspecialchars($success); ?>
    </div>
<?php endif; ?>

<?php if ($error): ?>
    <div class="bg-red-100 dark:bg-red-900 text-red-700 dark:text-red-200 px-4 py-3 rounded mb-6">
        <?php echo htmlspecialchars($error); ?>
    </div>
<?php endif; ?>

<?php if ($warning): ?>
    <div class="bg-amber-100 dark:bg-amber-900 text-amber-800 dark:text-amber-200 px-4 py-3 rounded mb-6">
        <?php echo htmlspecialchars($warning); ?>
    </div>
<?php endif; ?>

<?php echo renderMailGatewayBanners(); ?>

<div class="bg-white dark:bg-surface-dark rounded-lg shadow p-6">
    <form method="POST" action="" enctype="multipart/form-data">
        <?php echo csrfField(); ?>
        <div class="space-y-6">
            <!-- Company Logo Upload -->
            <div>
                <label class="block text-sm font-bold text-gray-700 dark:text-gray-300 mb-2">
                    Company Logo
                </label>
                <div class="mb-2">
                    <?php 
                    $currentLogo = getSetting('company_logo', '/asset/logo1.png');
                    if ($currentLogo): ?>
                        <img src="<?php echo htmlspecialchars($currentLogo); ?>" alt="Current Logo" class="h-12 w-auto mb-2 border border-gray-300 dark:border-gray-600 rounded p-1">
                    <?php endif; ?>
                </div>
                <input type="file" id="logo_upload" name="logo_upload" accept="image/png,image/jpeg,image/gif,image/webp"
                       class="w-full px-4 py-2 border border-gray-300 dark:border-gray-600 rounded bg-white dark:bg-background-dark text-gray-800 dark:text-white focus:ring-2 focus:ring-primary">
                <p class="mt-1 text-xs text-gray-500">Upload PNG, JPG, GIF, or WEBP (max 5MB)</p>
            </div>

            <!-- Email Logo Link -->
            <div>
                <label class="block text-sm font-bold text-gray-700 dark:text-gray-300 mb-2" for="setting_email_logo_url">
                    Email Logo Link (optional)
                </label>
                <?php $emailLogoUrl = (string) ($settings['email_logo_url'] ?? ''); ?>
                <?php if ($emailLogoUrl !== ''): ?>
                    <img src="<?php echo htmlspecialchars($emailLogoUrl); ?>" alt="Email Logo" class="h-12 w-auto mb-2 border border-gray-300 dark:border-gray-600 rounded p-1">
                <?php endif; ?>
                <input type="url" id="setting_email_logo_url" name="setting_email_logo_url"
                       value="<?php echo htmlspecialchars($emailLogoUrl); ?>"
                       class="w-full px-4 py-2 border border-gray-300 dark:border-gray-600 rounded bg-white dark:bg-background-dark text-gray-800 dark:text-white focus:ring-2 focus:ring-primary"
                       placeholder="https://your-wordpress-site.com/wp-content/uploads/logo.png">
                <p class="mt-1 text-xs text-gray-500">Logo shown in emails. Leave empty to use the uploaded logo above. Use this when email apps cannot load images from this site (paste a direct https:// image link, for example from the WordPress Media Library).</p>
            </div>
            
            <!-- Site Favicon Upload -->
            <div>
                <label class="block text-sm font-bold text-gray-700 dark:text-gray-300 mb-2">
                    Site Favicon
                </label>
                <div class="mb-2">
                    <?php 
                    $currentFavicon = getSetting('site_favicon', '/asset/logo1.png');
                    if ($currentFavicon): ?>
                        <img src="<?php echo htmlspecialchars($currentFavicon); ?>" alt="Current Favicon" class="h-8 w-8 mb-2 border border-gray-300 dark:border-gray-600 rounded p-1">
                    <?php endif; ?>
                </div>
                <input type="file" id="favicon_upload" name="favicon_upload" accept="image/png,image/jpeg,image/gif,image/webp,image/x-icon,image/vnd.microsoft.icon"
                       class="w-full px-4 py-2 border border-gray-300 dark:border-gray-600 rounded bg-white dark:bg-background-dark text-gray-800 dark:text-white focus:ring-2 focus:ring-primary">
                <p class="mt-1 text-xs text-gray-500">Upload PNG, JPG, GIF, WEBP, or ICO (max 2MB)</p>
            </div>
            
            <div class="border-t border-gray-300 dark:border-gray-600 pt-6">
            <!-- Google Maps API Key -->
            <div>
                <label class="block text-sm font-bold text-gray-700 dark:text-gray-300 mb-2" for="setting_google_maps_api_key">
                    Google Maps API Key
                </label>
                <input type="text" id="setting_google_maps_api_key" name="setting_google_maps_api_key" 
                       value="<?php echo htmlspecialchars($settings['google_maps_api_key'] ?? ''); ?>"
                       class="w-full px-4 py-2 border border-gray-300 dark:border-gray-600 rounded bg-white dark:bg-background-dark text-gray-800 dark:text-white focus:ring-2 focus:ring-primary"
                       placeholder="Enter your Google Maps API key">
                <p class="mt-1 text-xs text-gray-500">Get your API key from <a href="https://console.cloud.google.com/" target="_blank" class="text-primary hover:underline">Google Cloud Console</a></p>
                <div class="mt-2 p-3 bg-yellow-50 dark:bg-yellow-900/20 border border-yellow-200 dark:border-yellow-800 rounded">
                    <p class="text-xs font-semibold text-yellow-800 dark:text-yellow-200 mb-1">Required APIs:</p>
                    <ul class="text-xs text-yellow-700 dark:text-yellow-300 list-disc list-inside space-y-1">
                        <li>Places API</li>
                        <li>Maps JavaScript API</li>
                        <li>Geolocation API</li>
                        <li>Geocoding API</li>
                    </ul>
                </div>
                <!-- Map Preview -->
                <div class="mt-4">
                    <label class="block text-sm font-bold text-gray-700 dark:text-gray-300 mb-2">
                        Map Preview (Test API Key)
                    </label>
                    <div id="map-preview" class="w-full h-64 border border-gray-300 dark:border-gray-600 rounded" style="display: none;"></div>
                    <button type="button" id="test-map-btn" class="mt-2 px-4 py-2 bg-secondary hover:bg-secondary-dark text-white text-sm rounded transition-colors">
                        Test Map Display
                    </button>
                    <p id="map-status" class="mt-2 text-xs"></p>
                </div>
            </div>
            
            <!-- Company Name -->
            <div>
                <label class="block text-sm font-bold text-gray-700 dark:text-gray-300 mb-2" for="setting_company_name">
                    Company Name
                </label>
                <input type="text" id="setting_company_name" name="setting_company_name" 
                       value="<?php echo htmlspecialchars($settings['company_name'] ?? 'FedEx'); ?>"
                       class="w-full px-4 py-2 border border-gray-300 dark:border-gray-600 rounded bg-white dark:bg-background-dark text-gray-800 dark:text-white focus:ring-2 focus:ring-primary">
            </div>
            
            <!-- Company Tagline -->
            <div>
                <label class="block text-sm font-bold text-gray-700 dark:text-gray-300 mb-2" for="setting_company_tagline">
                    Company Tagline
                </label>
                <input type="text" id="setting_company_tagline" name="setting_company_tagline" 
                       value="<?php echo htmlspecialchars($settings['company_tagline'] ?? 'Global Shipping Solutions'); ?>"
                       class="w-full px-4 py-2 border border-gray-300 dark:border-gray-600 rounded bg-white dark:bg-background-dark text-gray-800 dark:text-white focus:ring-2 focus:ring-primary"
                       placeholder="e.g., Global Shipping Solutions">
                <p class="mt-1 text-xs text-gray-500">Tagline or subtitle displayed under company name (e.g., in PDF documents)</p>
            </div>
            
            <!-- Site Title -->
            <div>
                <label class="block text-sm font-bold text-gray-700 dark:text-gray-300 mb-2" for="setting_site_title">
                    Site Title
                </label>
                <input type="text" id="setting_site_title" name="setting_site_title" 
                       value="<?php echo htmlspecialchars($settings['site_title'] ?? ''); ?>"
                       class="w-full px-4 py-2 border border-gray-300 dark:border-gray-600 rounded bg-white dark:bg-background-dark text-gray-800 dark:text-white focus:ring-2 focus:ring-primary">
            </div>
            
            <!-- Contact Information Section -->
            <div class="border-t border-gray-300 dark:border-gray-600 pt-6">
                <h3 class="text-lg font-bold text-gray-800 dark:text-white mb-4">Contact Information</h3>
                <p class="text-sm text-gray-600 dark:text-gray-400 mb-6">Manage contact details displayed on the Contact Us page.</p>
                
                <!-- Contact Phone Number -->
                <div class="mb-6">
                    <label class="block text-sm font-bold text-gray-700 dark:text-gray-300 mb-2" for="setting_contact_phone_number">
                        Contact Phone Number
                    </label>
                    <input type="text" id="setting_contact_phone_number" name="setting_contact_phone_number" 
                           value="<?php echo htmlspecialchars($settings['contact_phone_number'] ?? '+1 (800) 555-0199'); ?>"
                           class="w-full px-4 py-2 border border-gray-300 dark:border-gray-600 rounded bg-white dark:bg-background-dark text-gray-800 dark:text-white focus:ring-2 focus:ring-primary"
                           placeholder="+1 (800) 555-0199">
                    <p class="mt-1 text-xs text-gray-500">Phone number displayed on the Contact Us page. Formatting will be preserved for display, but cleaned for the tel: link.</p>
                </div>
                
                <!-- Contact HQ Address -->
                <div>
                    <label class="block text-sm font-bold text-gray-700 dark:text-gray-300 mb-2" for="setting_contact_hq_address">
                        Headquarters Address
                    </label>
                    <textarea id="setting_contact_hq_address" name="setting_contact_hq_address" rows="4"
                              class="w-full px-4 py-2 border border-gray-300 dark:border-gray-600 rounded bg-white dark:bg-background-dark text-gray-800 dark:text-white focus:ring-2 focus:ring-primary"
                              placeholder="1200 Logistics Blvd, Suite 500&#10;San Francisco, CA 94107&#10;United States"><?php echo htmlspecialchars($settings['contact_hq_address'] ?? "1200 Logistics Blvd, Suite 500\nSan Francisco, CA 94107\nUnited States"); ?></textarea>
                    <p class="mt-1 text-xs text-gray-500">Headquarters address displayed on the Contact Us page. Use line breaks (Enter) to separate address lines.</p>
                </div>
            </div>
            
            <!-- Cookie Message -->
            <div>
                <label class="block text-sm font-bold text-gray-700 dark:text-gray-300 mb-2" for="setting_cookie_message">
                    Cookie Consent Message
                </label>
                <textarea id="setting_cookie_message" name="setting_cookie_message" rows="4"
                          class="w-full px-4 py-2 border border-gray-300 dark:border-gray-600 rounded bg-white dark:bg-background-dark text-gray-800 dark:text-white focus:ring-2 focus:ring-primary"><?php echo htmlspecialchars($settings['cookie_message'] ?? ''); ?></textarea>
            </div>
            
            <!-- Email Configuration Section -->
            <div class="border-t border-gray-300 dark:border-gray-600 pt-6">
                <h3 class="text-lg font-bold text-gray-800 dark:text-white mb-4">Email Configuration</h3>
                <p class="text-sm text-gray-600 dark:text-gray-400 mb-6">Configure SMTP settings for sending emails. These settings can also be configured in config.php. When the WordPress Email Gateway below is on, SMTP is the backup.</p>
                
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <!-- SMTP Host -->
                    <div>
                        <label class="block text-sm font-bold text-gray-700 dark:text-gray-300 mb-2" for="setting_smtp_host">
                            SMTP Host
                        </label>
                        <input type="text" id="setting_smtp_host" name="setting_smtp_host" 
                               value="<?php echo htmlspecialchars($settings['smtp_host'] ?? (defined('SMTP_HOST') ? SMTP_HOST : '')); ?>"
                               class="w-full px-4 py-2 border border-gray-300 dark:border-gray-600 rounded bg-white dark:bg-background-dark text-gray-800 dark:text-white focus:ring-2 focus:ring-primary"
                               placeholder="smtp.gmail.com">
                        <p class="mt-1 text-xs text-gray-500">SMTP server hostname</p>
                    </div>
                    
                    <!-- SMTP Port -->
                    <div>
                        <label class="block text-sm font-bold text-gray-700 dark:text-gray-300 mb-2" for="setting_smtp_port">
                            SMTP Port
                        </label>
                        <input type="number" id="setting_smtp_port" name="setting_smtp_port" 
                               value="<?php echo htmlspecialchars($settings['smtp_port'] ?? (defined('SMTP_PORT') ? SMTP_PORT : '587')); ?>"
                               class="w-full px-4 py-2 border border-gray-300 dark:border-gray-600 rounded bg-white dark:bg-background-dark text-gray-800 dark:text-white focus:ring-2 focus:ring-primary"
                               placeholder="587">
                        <p class="mt-1 text-xs text-gray-500">Common: 587 (TLS), 465 (SSL), 25 (unencrypted)</p>
                    </div>
                    
                    <!-- SMTP Username -->
                    <div>
                        <label class="block text-sm font-bold text-gray-700 dark:text-gray-300 mb-2" for="setting_smtp_username">
                            SMTP Username
                        </label>
                        <input type="text" id="setting_smtp_username" name="setting_smtp_username" 
                               value="<?php echo htmlspecialchars($settings['smtp_username'] ?? (defined('SMTP_USERNAME') ? SMTP_USERNAME : '')); ?>"
                               class="w-full px-4 py-2 border border-gray-300 dark:border-gray-600 rounded bg-white dark:bg-background-dark text-gray-800 dark:text-white focus:ring-2 focus:ring-primary"
                               placeholder="your-email@gmail.com">
                        <p class="mt-1 text-xs text-gray-500">Email address for SMTP authentication</p>
                    </div>
                    
                    <!-- SMTP Password -->
                    <div>
                        <label class="block text-sm font-bold text-gray-700 dark:text-gray-300 mb-2" for="setting_smtp_password">
                            SMTP Password
                        </label>
                        <input type="password" id="setting_smtp_password" name="setting_smtp_password" value=""
                               autocomplete="new-password"
                               class="w-full px-4 py-2 border border-gray-300 dark:border-gray-600 rounded bg-white dark:bg-background-dark text-gray-800 dark:text-white focus:ring-2 focus:ring-primary"
                               placeholder="<?php echo !empty($settings['smtp_password']) ? 'Leave blank to keep current' : 'SMTP password or app password'; ?>">
                        <p class="mt-1 text-xs text-gray-500">SMTP password or app password (leave blank to keep current)</p>
                    </div>
                    
                    <!-- SMTP Encryption -->
                    <div>
                        <?php $smtpEncryption = $settings['smtp_encryption'] ?? (defined('SMTP_ENCRYPTION') ? SMTP_ENCRYPTION : 'tls'); ?>
                        <label class="block text-sm font-bold text-gray-700 dark:text-gray-300 mb-2" for="setting_smtp_encryption">
                            SMTP Encryption
                        </label>
                        <select id="setting_smtp_encryption" name="setting_smtp_encryption" 
                                class="w-full px-4 py-2 border border-gray-300 dark:border-gray-600 rounded bg-white dark:bg-background-dark text-gray-800 dark:text-white focus:ring-2 focus:ring-primary">
                            <option value="tls" <?php echo $smtpEncryption === 'tls' ? 'selected' : ''; ?>>TLS (Recommended)</option>
                            <option value="ssl" <?php echo $smtpEncryption === 'ssl' ? 'selected' : ''; ?>>SSL</option>
                            <option value="none" <?php echo $smtpEncryption === 'none' ? 'selected' : ''; ?>>None</option>
                        </select>
                        <p class="mt-1 text-xs text-gray-500">Encryption method (TLS recommended for port 587)</p>
                    </div>
                    
                    <!-- From Email -->
                    <div>
                        <label class="block text-sm font-bold text-gray-700 dark:text-gray-300 mb-2" for="setting_smtp_from_email">
                            From Email Address
                        </label>
                        <input type="email" id="setting_smtp_from_email" name="setting_smtp_from_email" 
                               value="<?php echo htmlspecialchars($settings['smtp_from_email'] ?? (defined('SMTP_FROM_EMAIL') ? SMTP_FROM_EMAIL : '')); ?>"
                               class="w-full px-4 py-2 border border-gray-300 dark:border-gray-600 rounded bg-white dark:bg-background-dark text-gray-800 dark:text-white focus:ring-2 focus:ring-primary"
                               placeholder="noreply@example.com">
                        <p class="mt-1 text-xs text-gray-500">Default sender email address</p>
                    </div>
                    
                    <!-- From Name -->
                    <div class="md:col-span-2">
                        <label class="block text-sm font-bold text-gray-700 dark:text-gray-300 mb-2" for="setting_smtp_from_name">
                            From Name
                        </label>
                        <input type="text" id="setting_smtp_from_name" name="setting_smtp_from_name" 
                               value="<?php echo htmlspecialchars(html_entity_decode((string) ($settings['smtp_from_name'] ?? (defined('SMTP_FROM_NAME') ? SMTP_FROM_NAME : '')), ENT_QUOTES | ENT_HTML5, 'UTF-8')); ?>"
                               class="w-full px-4 py-2 border border-gray-300 dark:border-gray-600 rounded bg-white dark:bg-background-dark text-gray-800 dark:text-white focus:ring-2 focus:ring-primary"
                               placeholder="Shipping Company">
                        <p class="mt-1 text-xs text-gray-500">Display name for sender</p>
                    </div>
                </div>

                <!-- WordPress Email Gateway -->
                <div id="wordpress-email-gateway" class="mt-6 p-4 border border-gray-200 dark:border-gray-700 rounded">
                    <h4 class="text-sm font-bold text-gray-800 dark:text-white mb-2">WordPress Email Gateway</h4>
                    <p class="text-xs text-gray-500 dark:text-gray-400 mb-3">
                        Send emails through a WordPress site running the Universal PHP Mail Gateway plugin. WordPress delivers them with its own mail plugin
                        and decides the From email; the From name sent is your Company Name. When this is off, SMTP above is used unchanged.
                    </p>

                    <input type="hidden" name="setting_wp_gateway_enabled" value="0">
                    <label class="inline-flex items-center text-sm font-bold text-gray-700 dark:text-gray-300 mb-4">
                        <input type="checkbox" id="setting_wp_gateway_enabled" name="setting_wp_gateway_enabled" value="1" <?php echo $gatewayEnabled ? 'checked' : ''; ?>
                               class="mr-2 rounded border-gray-300 text-primary focus:ring-primary">
                        Send emails through the WordPress gateway
                    </label>

                    <div class="mb-4">
                        <label class="block text-sm font-bold text-gray-700 dark:text-gray-300 mb-2" for="wp_gateway_connection_key">Paste connection key (optional)</label>
                        <input type="text" id="wp_gateway_connection_key" autocomplete="off" spellcheck="false"
                               class="w-full px-4 py-2 border border-gray-300 dark:border-gray-600 rounded bg-white dark:bg-background-dark text-gray-800 dark:text-white focus:ring-2 focus:ring-primary"
                               placeholder="Paste the key from WordPress → Mail Gateway → Connected Sites → Copy connection key">
                        <p id="wp_gateway_key_status" class="mt-1 text-xs text-gray-500">Fills in the three fields below. The key itself is not saved.</p>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div class="md:col-span-2">
                            <label class="block text-sm font-bold text-gray-700 dark:text-gray-300 mb-2" for="setting_wp_gateway_url">WordPress Site URL</label>
                            <input type="url" id="setting_wp_gateway_url" name="setting_wp_gateway_url"
                                   value="<?php echo htmlspecialchars($settings['wp_gateway_url'] ?? ''); ?>"
                                   class="w-full px-4 py-2 border border-gray-300 dark:border-gray-600 rounded bg-white dark:bg-background-dark text-gray-800 dark:text-white focus:ring-2 focus:ring-primary"
                                   placeholder="https://your-wordpress-site.example">
                        </div>
                        <div>
                            <label class="block text-sm font-bold text-gray-700 dark:text-gray-300 mb-2" for="setting_wp_gateway_client_id">Client ID</label>
                            <input type="text" id="setting_wp_gateway_client_id" name="setting_wp_gateway_client_id" autocomplete="off" spellcheck="false"
                                   value="<?php echo htmlspecialchars($settings['wp_gateway_client_id'] ?? ''); ?>"
                                   class="w-full px-4 py-2 border border-gray-300 dark:border-gray-600 rounded bg-white dark:bg-background-dark text-gray-800 dark:text-white focus:ring-2 focus:ring-primary"
                                   placeholder="upmg_...">
                        </div>
                        <div>
                            <label class="block text-sm font-bold text-gray-700 dark:text-gray-300 mb-2" for="setting_wp_gateway_client_secret">Client Secret</label>
                            <input type="password" id="setting_wp_gateway_client_secret" name="setting_wp_gateway_client_secret" value=""
                                   class="w-full px-4 py-2 border border-gray-300 dark:border-gray-600 rounded bg-white dark:bg-background-dark text-gray-800 dark:text-white focus:ring-2 focus:ring-primary"
                                   placeholder="<?php echo !empty($settings['wp_gateway_client_secret']) ? 'Leave blank to keep current' : '64-character secret'; ?>"
                                   autocomplete="new-password">
                            <p class="mt-1 text-xs text-gray-500">Leave blank to keep the current secret.</p>
                        </div>
                        <div>
                            <label class="block text-sm font-bold text-gray-700 dark:text-gray-300 mb-2" for="setting_wp_gateway_reply_to">Reply-To for gateway emails (optional)</label>
                            <input type="email" id="setting_wp_gateway_reply_to" name="setting_wp_gateway_reply_to"
                                   value="<?php echo htmlspecialchars($settings['wp_gateway_reply_to'] ?? ''); ?>"
                                   class="w-full px-4 py-2 border border-gray-300 dark:border-gray-600 rounded bg-white dark:bg-background-dark text-gray-800 dark:text-white focus:ring-2 focus:ring-primary"
                                   placeholder="support@yourdomain.com">
                            <p class="mt-1 text-xs text-gray-500">Used only if it is on this site's allowed Reply-To list in WordPress.</p>
                        </div>
                        <div class="flex items-center">
                            <input type="hidden" name="setting_wp_gateway_fallback" value="0">
                            <label class="inline-flex items-center text-sm text-gray-700 dark:text-gray-300">
                                <input type="checkbox" name="setting_wp_gateway_fallback" value="1" <?php echo $gatewayFallback ? 'checked' : ''; ?>
                                       class="mr-2 rounded border-gray-300 text-primary focus:ring-primary">
                                Fall back to SMTP above if the gateway is unavailable
                            </label>
                        </div>
                        <div class="md:col-span-2">
                            <input type="hidden" name="setting_wp_gateway_fallback_after_failure" value="0">
                            <label class="inline-flex items-center text-sm text-gray-700 dark:text-gray-300">
                                <input type="checkbox" name="setting_wp_gateway_fallback_after_failure" value="1" <?php echo $gatewayFallbackAfterFailure ? 'checked' : ''; ?>
                                       class="mr-2 rounded border-gray-300 text-primary focus:ring-primary">
                                Also fall back after a gateway send failure (may send twice)
                            </label>
                            <p class="mt-1 text-xs text-gray-500">Off by default. When WordPress's mail plugin reports a failure, the email may still have gone out, so an SMTP send can deliver it twice. Only used when the fallback above is on.</p>
                        </div>
                    </div>
                    <p class="mt-2 text-xs text-gray-500">Wrong credentials, a disabled site or a wrong URL never fall back: they show a red alert instead so the problem is not hidden.
                        SMTP is only used when the gateway proves it did not send the email; when the outcome is unknown, nothing is resent and the email is listed under Unconfirmed emails below.</p>

                    <div class="mt-4 flex flex-col sm:flex-row sm:items-center gap-3">
                        <button type="button" id="wp_gateway_test_btn"
                                class="px-6 py-2 bg-secondary hover:bg-secondary-dark text-white font-bold rounded transition-colors whitespace-nowrap">
                            Test connection
                        </button>
                        <p id="wp_gateway_status" class="text-sm <?php echo $gatewayLastTest === null ? 'text-gray-500' : (!empty($gatewayLastTest['ok']) ? 'text-green-700 dark:text-green-300' : 'text-red-700 dark:text-red-300'); ?>">
                            <?php if ($gatewayLastTest === null): ?>
                                Not tested yet.
                            <?php else: ?>
                                <?php echo (!empty($gatewayLastTest['ok']) ? '✓ ' : '✗ ') . htmlspecialchars((string) ($gatewayLastTest['message'] ?? '')); ?>
                                · tested <?php echo htmlspecialchars(date('M j, Y g:i A', (int) ($gatewayLastTest['time'] ?? time()))); ?>
                                <?php foreach (($gatewayLastTest['warnings'] ?? []) as $gatewayWarning): ?>
                                    <span class="block text-amber-700 dark:text-amber-300"><?php echo htmlspecialchars((string) $gatewayWarning); ?></span>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </p>
                    </div>
                </div>
                
                <!-- Test Email Section -->
                <div class="mt-6 p-4 bg-blue-50 dark:bg-blue-900/20 border border-blue-200 dark:border-blue-800 rounded">
                    <h4 class="text-sm font-bold text-blue-800 dark:text-blue-200 mb-3">Test Email Configuration</h4>
                    <p class="text-xs text-blue-700 dark:text-blue-300 mb-4">Send a test email to any address. It goes through the WordPress gateway when that is switched on, otherwise SMTP. Save settings first if you just changed them.</p>
                    <div class="flex flex-col sm:flex-row gap-3">
                        <input type="email" id="test_email_address" 
                               class="flex-1 px-4 py-2 border border-blue-300 dark:border-blue-700 rounded bg-white dark:bg-background-dark text-gray-800 dark:text-white focus:ring-2 focus:ring-blue-500"
                               placeholder="Enter email address to test">
                        <button type="button" id="send_test_email_btn" 
                                class="px-6 py-2 bg-blue-600 hover:bg-blue-700 text-white font-bold rounded transition-colors">
                            Send Test Email
                        </button>
                    </div>
                    <div id="test_email_result" class="mt-3 text-sm"></div>
                </div>
            </div>
            
            <!-- Smartsupp Live Chat Configuration -->
            <div class="border-t border-gray-300 dark:border-gray-600 pt-6">
                <h3 class="text-lg font-bold text-gray-800 dark:text-white mb-4">Live Chat Configuration</h3>
                <p class="text-sm text-gray-600 dark:text-gray-400 mb-6">Configure Smartsupp live chat widget. The chat will appear on Homepage, Contact, Track, and Track Result pages.</p>
                
                <div>
                    <label class="block text-sm font-bold text-gray-700 dark:text-gray-300 mb-2" for="setting_smartsupp_key">
                        Smartsupp API Key
                    </label>
                    <input type="text" id="setting_smartsupp_key" name="setting_smartsupp_key" 
                           value="<?php echo htmlspecialchars($settings['smartsupp_key'] ?? ''); ?>"
                           class="w-full px-4 py-2 border border-gray-300 dark:border-gray-600 rounded bg-white dark:bg-background-dark text-gray-800 dark:text-white focus:ring-2 focus:ring-primary"
                           placeholder="Enter your Smartsupp API key">
                    <p class="mt-1 text-xs text-gray-500">Get your API key from <a href="https://www.smartsupp.com" target="_blank" class="text-primary underline">Smartsupp.com</a>. The chat widget will automatically appear on: Homepage, Contact, Track, and Track Result pages.</p>
                </div>
            </div>
            
            <!-- Color Settings -->
            <div class="border-t border-gray-300 dark:border-gray-600 pt-6">
                <h3 class="text-lg font-bold text-gray-800 dark:text-white mb-4">Brand Colors</h3>
                <p class="text-sm text-gray-600 dark:text-gray-400 mb-6">Customize your brand colors. Dark variants will be automatically generated for hover states.</p>
                
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <!-- Primary Color -->
                    <div>
                        <label class="block text-sm font-bold text-gray-700 dark:text-gray-300 mb-2" for="setting_primary_color">
                            Primary Color
                        </label>
                        <div class="flex items-center gap-3">
                            <input type="color" id="setting_primary_color" name="setting_primary_color" 
                                   value="<?php echo htmlspecialchars($settings['primary_color'] ?? '#4D148C'); ?>"
                                   class="h-12 w-20 border border-gray-300 dark:border-gray-600 rounded cursor-pointer">
                            <input type="text" id="setting_primary_color_text" 
                                   value="<?php echo htmlspecialchars($settings['primary_color'] ?? '#4D148C'); ?>"
                                   class="flex-1 px-4 py-2 border border-gray-300 dark:border-gray-600 rounded bg-white dark:bg-background-dark text-gray-800 dark:text-white focus:ring-2 focus:ring-primary uppercase"
                                   pattern="^#[0-9A-Fa-f]{6}$"
                                   placeholder="#4D148C">
                        </div>
                        <p class="mt-1 text-xs text-gray-500">Used for headers, primary buttons, and links</p>
                    </div>
                    
                    <!-- Secondary Color -->
                    <div>
                        <label class="block text-sm font-bold text-gray-700 dark:text-gray-300 mb-2" for="setting_secondary_color">
                            Secondary Color
                        </label>
                        <div class="flex items-center gap-3">
                            <input type="color" id="setting_secondary_color" name="setting_secondary_color" 
                                   value="<?php echo htmlspecialchars($settings['secondary_color'] ?? '#FF6200'); ?>"
                                   class="h-12 w-20 border border-gray-300 dark:border-gray-600 rounded cursor-pointer">
                            <input type="text" id="setting_secondary_color_text" 
                                   value="<?php echo htmlspecialchars($settings['secondary_color'] ?? '#FF6200'); ?>"
                                   class="flex-1 px-4 py-2 border border-gray-300 dark:border-gray-600 rounded bg-white dark:bg-background-dark text-gray-800 dark:text-white focus:ring-2 focus:ring-primary uppercase"
                                   pattern="^#[0-9A-Fa-f]{6}$"
                                   placeholder="#FF6200">
                        </div>
                        <p class="mt-1 text-xs text-gray-500">Used for secondary buttons and accent elements</p>
                    </div>
                </div>
                
                <div class="mt-4 p-4 bg-blue-50 dark:bg-blue-900/20 border border-blue-200 dark:border-blue-800 rounded">
                    <p class="text-xs font-semibold text-blue-800 dark:text-blue-200 mb-1">💡 Tip:</p>
                    <p class="text-xs text-blue-700 dark:text-blue-300">Dark variants for hover states are automatically generated. After saving, refresh the page to see changes.</p>
                </div>
            </div>
            </div>
        </div>
        
        <div class="mt-8">
            <button type="submit" class="bg-primary hover:bg-primary-dark text-white font-bold py-3 px-8 rounded uppercase tracking-wide transition-colors">
                Save Settings
            </button>
        </div>
    </form>
</div>

<div id="mail-gateway-ledger" class="bg-white dark:bg-surface-dark rounded-lg shadow p-6 mt-8">
    <h3 class="text-lg font-bold text-gray-800 dark:text-white mb-2">WordPress Email Gateway — Unconfirmed emails</h3>
    <p class="text-sm text-gray-600 dark:text-gray-400 mb-4">
        Emails whose outcome the gateway could not confirm. They may or may not have been sent, so they were not resent automatically.
        <strong>Retry through gateway</strong> is safe: the gateway sends each email at most once and answers "already accepted" if it went out.
        <strong>Send with SMTP anyway</strong> uses the SMTP settings above and may deliver a second copy. Shipment emails are rebuilt from the shipment's current details.
    </p>
    <?php if (empty($gatewayLedger)): ?>
        <p class="text-sm text-gray-500">Nothing here.</p>
    <?php else: ?>
        <div class="overflow-x-auto">
            <table class="min-w-full text-sm">
                <thead>
                    <tr class="text-left text-gray-600 dark:text-gray-300 border-b border-gray-200 dark:border-gray-700">
                        <th class="py-2 pr-4">When</th>
                        <th class="py-2 pr-4">Email</th>
                        <th class="py-2 pr-4">What happened</th>
                        <th class="py-2 pr-4">Status</th>
                        <th class="py-2">Actions</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach (array_slice($gatewayLedger, 0, 50) as $entry):
                    $entryState = (string) ($entry['state'] ?? 'open');
                    $entryInfo = mailGatewayReferenceInfo($entry['reference'] ?? '');
                    ?>
                    <tr class="border-b border-gray-100 dark:border-gray-800 align-top">
                        <td class="py-2 pr-4 whitespace-nowrap text-gray-600 dark:text-gray-400"><?php echo htmlspecialchars(date('M j, Y g:i A', (int) ($entry['time'] ?? 0))); ?></td>
                        <td class="py-2 pr-4 text-gray-800 dark:text-gray-200">
                            <?php echo htmlspecialchars((string) ($entry['subject'] ?? '')); ?>
                            <span class="block text-xs text-gray-500">To <?php echo htmlspecialchars((string) ($entry['to'] ?? '')); ?> · <?php echo htmlspecialchars((string) ($entry['reference'] ?? '')); ?></span>
                        </td>
                        <td class="py-2 pr-4 text-gray-700 dark:text-gray-300">
                            <?php echo htmlspecialchars((string) ($entry['reason'] ?? '')); ?>
                            <?php if (!empty($entry['note'])): ?>
                                <span class="block text-xs text-gray-500"><?php echo htmlspecialchars((string) $entry['note']); ?></span>
                            <?php endif; ?>
                        </td>
                        <td class="py-2 pr-4 whitespace-nowrap <?php echo $entryState === 'open' ? 'text-amber-700 dark:text-amber-300 font-bold' : 'text-gray-600 dark:text-gray-400'; ?>">
                            <?php echo htmlspecialchars($ledgerLabels[$entryState] ?? $entryState); ?>
                        </td>
                        <td class="py-2">
                            <?php if ($entryState === 'open'): ?>
                                <div class="flex flex-wrap gap-2">
                                    <?php if ($entryInfo['kind'] !== 'other'): ?>
                                        <form method="POST" action="/admin/mail-gateway-ledger.php">
                                            <?php echo csrfField(); ?>
                                            <input type="hidden" name="id" value="<?php echo htmlspecialchars((string) $entry['id']); ?>">
                                            <input type="hidden" name="ledger_action" value="retry">
                                            <button type="submit" class="px-3 py-1 rounded text-white bg-primary hover:bg-primary-dark text-xs font-bold">Retry through gateway</button>
                                        </form>
                                        <form method="POST" action="/admin/mail-gateway-ledger.php"
                                              onsubmit="return confirm('Send this email with SMTP? If the gateway already sent it, the recipient gets it twice.');">
                                            <?php echo csrfField(); ?>
                                            <input type="hidden" name="id" value="<?php echo htmlspecialchars((string) $entry['id']); ?>">
                                            <input type="hidden" name="ledger_action" value="backup">
                                            <button type="submit" class="px-3 py-1 rounded text-white bg-amber-600 hover:bg-amber-700 text-xs font-bold">Send with SMTP anyway</button>
                                        </form>
                                    <?php endif; ?>
                                    <form method="POST" action="/admin/mail-gateway-ledger.php">
                                        <?php echo csrfField(); ?>
                                        <input type="hidden" name="id" value="<?php echo htmlspecialchars((string) $entry['id']); ?>">
                                        <input type="hidden" name="ledger_action" value="dismiss">
                                        <button type="submit" class="px-3 py-1 rounded text-gray-700 dark:text-gray-200 bg-gray-100 dark:bg-gray-700 text-xs font-bold">Dismiss</button>
                                    </form>
                                </div>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

<script>
const csrfToken = (document.querySelector('meta[name="csrf-token"]') || {}).content
    || (document.querySelector('input[name="csrf_token"]') || {}).value
    || '';

function escapeHtml(value) {
    return String(value).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
}

// Sync color picker and text input
(function() {
    const primaryColor = document.getElementById('setting_primary_color');
    const primaryColorText = document.getElementById('setting_primary_color_text');
    const secondaryColor = document.getElementById('setting_secondary_color');
    const secondaryColorText = document.getElementById('setting_secondary_color_text');
    
    if (primaryColor && primaryColorText) {
        primaryColor.addEventListener('input', function() {
            primaryColorText.value = this.value.toUpperCase();
        });
        primaryColorText.addEventListener('input', function() {
            if (/^#[0-9A-Fa-f]{6}$/.test(this.value)) {
                primaryColor.value = this.value.toUpperCase();
            }
        });
        primaryColorText.addEventListener('blur', function() {
            if (!/^#[0-9A-Fa-f]{6}$/.test(this.value)) {
                this.value = primaryColor.value.toUpperCase();
            }
        });
    }
    
    if (secondaryColor && secondaryColorText) {
        secondaryColor.addEventListener('input', function() {
            secondaryColorText.value = this.value.toUpperCase();
        });
        secondaryColorText.addEventListener('input', function() {
            if (/^#[0-9A-Fa-f]{6}$/.test(this.value)) {
                secondaryColor.value = this.value.toUpperCase();
            }
        });
        secondaryColorText.addEventListener('blur', function() {
            if (!/^#[0-9A-Fa-f]{6}$/.test(this.value)) {
                this.value = secondaryColor.value.toUpperCase();
            }
        });
    }
    
    // Sync text inputs with hidden color inputs on form submit
    const form = document.querySelector('form');
    if (form) {
        form.addEventListener('submit', function() {
            if (primaryColorText) {
                primaryColor.value = primaryColorText.value.toUpperCase();
            }
            if (secondaryColorText) {
                secondaryColor.value = secondaryColorText.value.toUpperCase();
            }
        });
    }
})();

// Test Google Maps API Key
(function() {
    const apiKeyInput = document.getElementById('setting_google_maps_api_key');
    const testBtn = document.getElementById('test-map-btn');
    const mapPreview = document.getElementById('map-preview');
    const mapStatus = document.getElementById('map-status');
    
    if (testBtn && apiKeyInput) {
        testBtn.addEventListener('click', function() {
            const apiKey = apiKeyInput.value.trim();
            
            if (!apiKey) {
                mapStatus.textContent = 'Please enter an API key first.';
                mapStatus.className = 'mt-2 text-xs text-red-600 dark:text-red-400';
                return;
            }
            
            mapStatus.textContent = 'Loading map...';
            mapStatus.className = 'mt-2 text-xs text-blue-600 dark:text-blue-400';
            mapPreview.style.display = 'block';
            
            // Load Google Maps script
            const script = document.createElement('script');
            script.src = `https://maps.googleapis.com/maps/api/js?key=${encodeURIComponent(apiKey)}&libraries=places`;
            script.async = true;
            script.defer = true;
            
            script.onload = function() {
                try {
                    const map = new google.maps.Map(mapPreview, {
                        zoom: 10,
                        center: { lat: 40.7128, lng: -74.0060 }, // New York City
                        disableDefaultUI: false
                    });
                    
                    new google.maps.Marker({
                        position: { lat: 40.7128, lng: -74.0060 },
                        map: map,
                        title: 'Test Location'
                    });
                    
                    mapStatus.textContent = '✓ Map loaded successfully! API key is working.';
                    mapStatus.className = 'mt-2 text-xs text-green-600 dark:text-green-400';
                } catch (error) {
                    mapStatus.textContent = '✗ Error initializing map: ' + error.message;
                    mapStatus.className = 'mt-2 text-xs text-red-600 dark:text-red-400';
                }
            };
            
            script.onerror = function() {
                mapStatus.textContent = '✗ Failed to load Google Maps. Check your API key and ensure required APIs are enabled.';
                mapStatus.className = 'mt-2 text-xs text-red-600 dark:text-red-400';
            };
            
            // Remove old script if exists
            const oldScript = document.querySelector('script[src*="maps.googleapis.com"]');
            if (oldScript) {
                oldScript.remove();
            }
            
            document.head.appendChild(script);
        });
    }
})();

// WordPress Email Gateway: connection key + Test connection
(function() {
    const keyInput = document.getElementById('wp_gateway_connection_key');
    const keyStatus = document.getElementById('wp_gateway_key_status');
    const urlInput = document.getElementById('setting_wp_gateway_url');
    const idInput = document.getElementById('setting_wp_gateway_client_id');
    const secretInput = document.getElementById('setting_wp_gateway_client_secret');
    const testBtn = document.getElementById('wp_gateway_test_btn');
    const status = document.getElementById('wp_gateway_status');

    function setStatus(text, tone) {
        status.textContent = text;
        status.className = 'text-sm ' + ({
            ok: 'text-green-700 dark:text-green-300',
            error: 'text-red-700 dark:text-red-300',
            info: 'text-blue-600 dark:text-blue-400'
        }[tone] || 'text-gray-500');
    }

    if (keyInput) {
        keyInput.addEventListener('input', function() {
            const raw = keyInput.value.trim();
            if (!raw) return;
            try {
                const data = JSON.parse(atob(raw));
                if (!data || !data.url || !data.client_id || !data.client_secret) throw new Error('incomplete');
                urlInput.value = data.url;
                idInput.value = data.client_id;
                secretInput.value = data.client_secret;
                keyInput.value = '';
                keyStatus.textContent = '✓ URL, Client ID and Secret filled in. Click Test connection, then Save Settings.';
                keyStatus.className = 'mt-1 text-xs text-green-700 dark:text-green-300';
            } catch (e) {
                keyStatus.textContent = '✗ That is not a valid connection key. Copy it again from WordPress → Mail Gateway → Connected Sites.';
                keyStatus.className = 'mt-1 text-xs text-red-700 dark:text-red-300';
            }
        });
    }

    if (!testBtn) return;
    testBtn.addEventListener('click', async function() {
        testBtn.disabled = true;
        testBtn.textContent = 'Testing...';
        setStatus('Contacting the WordPress gateway...', 'info');
        const controller = new AbortController();
        const timeoutId = setTimeout(() => controller.abort(), 45000);
        try {
            const response = await fetch('/api/test-mail-gateway.php', {
                method: 'POST',
                credentials: 'same-origin',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': csrfToken },
                body: JSON.stringify({ url: urlInput.value.trim(), client_id: idInput.value.trim(), client_secret: secretInput.value.trim() }),
                signal: controller.signal
            });
            if (response.status === 401) {
                throw new Error('Unauthorized — please log in again and retry.');
            }
            const data = await response.json();
            const warnings = Array.isArray(data.warnings) && data.warnings.length ? ' ⚠ ' + data.warnings.join(' ') : '';
            setStatus((data.success ? '✓ ' : '✗ ') + (data.message || 'Unknown result') + warnings, data.success ? 'ok' : 'error');
        } catch (error) {
            setStatus(error.name === 'AbortError' ? '✗ The test timed out.' : '✗ ' + (error.message || 'Request failed'), 'error');
        } finally {
            clearTimeout(timeoutId);
            testBtn.disabled = false;
            testBtn.textContent = 'Test connection';
        }
    });
})();

// Test Email
(function() {
    const testEmailBtn = document.getElementById('send_test_email_btn');
    const testEmailInput = document.getElementById('test_email_address');
    const testEmailResult = document.getElementById('test_email_result');

    if (!testEmailBtn || !testEmailInput) return;

    testEmailBtn.addEventListener('click', async function() {
        const email = testEmailInput.value.trim();

        if (!email) {
            testEmailResult.innerHTML = '<p class="text-red-600 dark:text-red-400">Please enter an email address.</p>';
            return;
        }

        if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) {
            testEmailResult.innerHTML = '<p class="text-red-600 dark:text-red-400">Please enter a valid email address.</p>';
            return;
        }

        testEmailBtn.disabled = true;
        testEmailBtn.textContent = 'Sending...';
        testEmailResult.innerHTML = '<p class="text-blue-600 dark:text-blue-400">Sending test email...</p>';

        const controller = new AbortController();
        const timeoutId = setTimeout(() => controller.abort(), 60000);

        try {
            const formData = new FormData();
            formData.append('action', 'send_test_email');
            formData.append('email', email);
            formData.append('csrf_token', csrfToken);

            const response = await fetch('/admin/settings.php', {
                method: 'POST',
                credentials: 'same-origin',
                body: formData,
                signal: controller.signal
            });
            clearTimeout(timeoutId);

            let data = null;
            try {
                data = await response.json();
            } catch (parseErr) {
                throw new Error('Server responded with status: ' + response.status);
            }

            if (data.success) {
                const providerLabels = { phpmailer: 'SMTP', 'wordpress-gateway': 'WordPress email gateway' };
                const label = providerLabels[data.provider] || '';
                const via = label ? ' (' + label + ')' : '';
                const msgId = data.messageId ? String(data.messageId) : '';
                const fallback = data.fallback_reason ? String(data.fallback_reason) : '';
                let hint = '<p class="text-xs mt-2">If it does not arrive, check the Spam folder.</p>';
                let idLine = '';
                if (data.provider === 'wordpress-gateway') {
                    idLine = msgId ? '<p class="text-xs"><strong>Gateway request ID:</strong> ' + escapeHtml(msgId) + '</p>' : '';
                    hint = '<p class="text-xs mt-2">Check Spam, and WordPress → Mail Gateway → Logs. The From email is set by the WordPress site\'s mail plugin.</p>';
                }
                testEmailResult.innerHTML =
                    '<div class="text-green-700 dark:text-green-300 bg-green-50 dark:bg-green-900/20 border border-green-200 dark:border-green-800 rounded p-3">' +
                    '<p class="font-bold mb-2">✓ Test email accepted for delivery' + via + '</p>' +
                    (fallback ? '<p class="text-xs text-amber-700 dark:text-amber-300 mb-1"><strong>SMTP backup used:</strong> ' + escapeHtml(fallback) + '</p>' : '') +
                    '<p class="text-xs"><strong>To:</strong> ' + escapeHtml(email) + '</p>' +
                    idLine +
                    hint +
                    '</div>';
            } else {
                if (response.status === 401) {
                    throw new Error('Unauthorized — please log in again and retry.');
                }
                const title = data.title || 'Email failed';
                const err = data.error || data.message || 'Unknown error';
                const provider = data.provider ? '<p class="text-xs"><strong>Provider:</strong> ' + escapeHtml(data.provider) + '</p>' : '';
                const host = data.host ? '<p class="text-xs"><strong>Host:</strong> ' + escapeHtml(data.host) + '</p>' : '';
                const port = data.port ? '<p class="text-xs"><strong>Port:</strong> ' + escapeHtml(data.port) + '</p>' : '';
                const httpCode = data.http_code != null ? '<p class="text-xs"><strong>HTTP:</strong> ' + escapeHtml(data.http_code) + '</p>' : '';
                const box = data.uncertain
                    ? 'text-amber-800 dark:text-amber-200 bg-amber-50 dark:bg-amber-900/30 border border-amber-200 dark:border-amber-800'
                    : 'text-red-700 dark:text-red-300 bg-red-50 dark:bg-red-900/30 border border-red-200 dark:border-red-800';
                testEmailResult.innerHTML =
                    '<div class="' + box + ' rounded p-3">' +
                    '<p class="font-bold mb-2">' + escapeHtml(title) + '</p>' +
                    provider + host + port + httpCode +
                    '<p class="text-xs mt-1"><strong>Error:</strong> ' + escapeHtml(err) + '</p>' +
                    '</div>';
            }
        } catch (error) {
            clearTimeout(timeoutId);
            if (error.name === 'AbortError') {
                testEmailResult.innerHTML = '<p class="text-red-600 dark:text-red-400">✗ Request timed out. Check your email settings and try again.</p>';
            } else {
                testEmailResult.innerHTML = '<p class="text-red-600 dark:text-red-400">✗ ' + escapeHtml(error.message || 'Request failed') + '</p>';
            }
        } finally {
            testEmailBtn.disabled = false;
            testEmailBtn.textContent = 'Send Test Email';
        }
    });
})();
</script>

<?php include __DIR__ . '/includes/admin-footer.php'; ?>
