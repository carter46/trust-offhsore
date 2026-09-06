<?php
require_once __DIR__ . '/includes/admin-auth.php';

$success = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Handle form submission with file upload
    $input = [
        'sender_name' => $_POST['sender_name'] ?? '',
        'sender_address' => $_POST['sender_address'] ?? '',
        'sender_city' => $_POST['sender_city'] ?? '',
        'sender_state' => $_POST['sender_state'] ?? '',
        'sender_zip' => $_POST['sender_zip'] ?? '',
        'sender_country' => trim((string) ($_POST['sender_country'] ?? '')) !== ''
            ? trim((string) $_POST['sender_country'])
            : 'United States',
        'sender_email' => $_POST['sender_email'] ?? '',
        'sender_phone' => $_POST['sender_phone'] ?? '',
        'sender_latitude' => $_POST['sender_latitude'] ?? null,
        'sender_longitude' => $_POST['sender_longitude'] ?? null,
        'recipient_name' => $_POST['recipient_name'] ?? '',
        'recipient_address' => $_POST['recipient_address'] ?? '',
        'recipient_city' => $_POST['recipient_city'] ?? '',
        'recipient_state' => $_POST['recipient_state'] ?? '',
        'recipient_zip' => $_POST['recipient_zip'] ?? '',
        'recipient_country' => trim((string) ($_POST['recipient_country'] ?? '')) !== ''
            ? trim((string) $_POST['recipient_country'])
            : 'United States',
        'recipient_email' => $_POST['recipient_email'] ?? '',
        'recipient_phone' => $_POST['recipient_phone'] ?? '',
        'recipient_latitude' => $_POST['recipient_latitude'] ?? null,
        'recipient_longitude' => $_POST['recipient_longitude'] ?? null,
        'pickup_location' => $_POST['pickup_location'] ?? '',
        'pickup_latitude' => $_POST['pickup_latitude'] ?? null,
        'pickup_longitude' => $_POST['pickup_longitude'] ?? null,
        'dropoff_location' => $_POST['dropoff_location'] ?? '',
        'dropoff_latitude' => $_POST['dropoff_latitude'] ?? null,
        'dropoff_longitude' => $_POST['dropoff_longitude'] ?? null,
        'weight' => $_POST['weight'] ?? '',
        'dimensions' => $_POST['dimensions'] ?? '',
        'service_type' => $_POST['service_type'] ?? (getSetting('company_name', 'FedEx') . ' Ground'),
        'status' => $_POST['status'] ?? 'Pending',
        'estimated_delivery' => $_POST['estimated_delivery'] ?? '',
        'shipment_created_at' => $_POST['shipment_created_at'] ?? '',
        'shipment_worth' => isset($_POST['shipment_worth']) && $_POST['shipment_worth'] !== '' ? $_POST['shipment_worth'] : '',
        'base_cost' => isset($_POST['base_cost']) && $_POST['base_cost'] !== '' ? $_POST['base_cost'] : '',
        'clearance_cost' => isset($_POST['clearance_cost']) && $_POST['clearance_cost'] !== '' ? $_POST['clearance_cost'] : '',
        'total_cost' => isset($_POST['total_cost']) && $_POST['total_cost'] !== '' ? $_POST['total_cost'] : '',
        'admin_comment' => $_POST['admin_comment'] ?? '',
        'send_email_notification' => isset($_POST['send_email_notification']) && $_POST['send_email_notification'] == '1' ? true : false
    ];

    // #region agent log
    // Log to site root so it's available on hosting File Manager/FTP
    $agentLogFile = __DIR__ . '/../debug.log';
    $agentLogLine = json_encode([
        'sessionId' => 'shipment-create-timeout',
        'runId' => 'pre-fix',
        'hypothesisId' => 'H2',
        'location' => 'admin/create-shipment.php:input',
        'message' => 'Prepared input for API',
        'data' => [
            'hasSession' => session_status() === PHP_SESSION_ACTIVE,
            'sessionIdHash' => substr(hash('sha256', session_id() ?: 'none'), 0, 12),
            'inputKeysCount' => count($input),
            'hasEmailPhone' => (!empty($input['sender_email']) || !empty($input['sender_phone']) || !empty($input['recipient_email']) || !empty($input['recipient_phone'])),
        ],
        'timestamp' => (int) round(microtime(true) * 1000),
    ]) . "\n";
    if (@file_put_contents($agentLogFile, $agentLogLine, FILE_APPEND) === false) {
        error_log('AGENT_LOG ' . $agentLogLine);
    }
    // #endregion
    
    // Handle image upload if present
    if (isset($_FILES['item_image']) && $_FILES['item_image']['error'] === UPLOAD_ERR_OK) {
        $file = $_FILES['item_image'];
        $allowedTypes = ['image/png', 'image/jpeg', 'image/jpg', 'image/gif', 'image/webp'];
        $maxSize = 5 * 1024 * 1024; // 5MB
        
        if (in_array($file['type'], $allowedTypes) && $file['size'] <= $maxSize) {
            $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
            $newFileName = 'shipment_' . time() . '_' . rand(1000, 9999) . '.' . $extension;
            $targetDir = __DIR__ . '/../asset/shipment_images/';
            
            // Create directory if it doesn't exist
            if (!file_exists($targetDir)) {
                if (!mkdir($targetDir, 0755, true)) {
                    $error = 'Failed to create upload directory. Please check permissions.';
                }
            }
            
            $targetPath = $targetDir . $newFileName;
            if (move_uploaded_file($file['tmp_name'], $targetPath)) {
                $input['item_image'] = '/asset/shipment_images/' . $newFileName;
            }
        }
    }
    
    // Call API via HTTP
    // IMPORTANT: release PHP session lock before making an internal HTTP request,
    // otherwise the API (which calls session_start) can block and cause a timeout.
    if (session_status() === PHP_SESSION_ACTIVE) {
        session_write_close();
    }
    $ch = curl_init();
    $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $apiUrl = $protocol . '://' . $_SERVER['HTTP_HOST'] . '/api/create-shipment.php';

    // #region agent log
    $t0 = microtime(true);
    $agentLogLine = json_encode([
        'sessionId' => 'shipment-create-timeout',
        'runId' => 'pre-fix',
        'hypothesisId' => 'H2',
        'location' => 'admin/create-shipment.php:curl:before',
        'message' => 'About to curl_exec() create-shipment API',
        'data' => [
            'apiUrl' => $apiUrl,
            'protocol' => $protocol,
            'timeoutSeconds' => 30,
        ],
        'timestamp' => (int) round(microtime(true) * 1000),
    ]) . "\n";
    if (@file_put_contents($agentLogFile, $agentLogLine, FILE_APPEND) === false) {
        error_log('AGENT_LOG ' . $agentLogLine);
    }
    // #endregion
    curl_setopt($ch, CURLOPT_URL, $apiUrl);
    curl_setopt($ch, CURLOPT_POST, true);
    $jsonPayload = json_encode($input);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $jsonPayload);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_COOKIE, session_name() . '=' . session_id());
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30); // Add timeout to prevent infinite loading
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $effectiveUrl = curl_getinfo($ch, CURLINFO_EFFECTIVE_URL);
    $curlErrno = curl_errno($ch);
    $curlError = curl_error($ch);
    curl_close($ch);

    // #region agent log
    $agentLogLine = json_encode([
        'sessionId' => 'shipment-create-timeout',
        'runId' => 'pre-fix',
        'hypothesisId' => 'H2',
        'location' => 'admin/create-shipment.php:curl:after',
        'message' => 'curl_exec() returned',
        'data' => [
            'httpCode' => $httpCode,
            'curlErrno' => $curlErrno,
            'curlError' => $curlError ? substr($curlError, 0, 140) : null,
            'effectiveUrl' => $effectiveUrl,
            'responseLen' => is_string($response) ? strlen($response) : null,
            'elapsedMs' => (int) round((microtime(true) - $t0) * 1000),
        ],
        'timestamp' => (int) round(microtime(true) * 1000),
    ]) . "\n";
    if (@file_put_contents($agentLogFile, $agentLogLine, FILE_APPEND) === false) {
        error_log('AGENT_LOG ' . $agentLogLine);
    }
    // #endregion
    
    if ($curlError) {
        $error = 'Connection error: ' . $curlError;
        $response = null;
    }
    
    // Handle null or empty response
    if (empty($response)) {
        $error = 'No response from server. HTTP Code: ' . ($httpCode ?: '0') . ($curlError ? '. Error: ' . $curlError : '');
    } else {
        $result = json_decode($response, true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            $error = 'Invalid response from server: ' . json_last_error_msg() . '. Response: ' . substr($response, 0, 200);
        } elseif ($httpCode === 200 && isset($result['success']) && $result['success']) {
            $tracking = rawurlencode((string) ($result['tracking_number'] ?? ''));
            header('Location: /admin/dashboard.php?created=1&tracking=' . $tracking);
            exit;
        } else {
            $error = isset($result['error']) ? $result['error'] : 'Failed to create shipment. HTTP Code: ' . ($httpCode ?: '0');
        }
    }
}

// Only render the admin layout AFTER handling POST/redirects
include __DIR__ . '/includes/admin-header.php';
?>
<div class="mb-8">
    <h1 class="text-3xl font-light text-gray-800 dark:text-white mb-2">Create New Shipment</h1>
    <p class="text-gray-600 dark:text-gray-400">Create a new shipment and generate a tracking number</p>
</div>

<?php if ($error): ?>
    <div class="bg-red-100 dark:bg-red-900 text-red-700 dark:text-red-200 px-4 py-3 rounded mb-6">
        <?php echo htmlspecialchars($error); ?>
    </div>
<?php endif; ?>

<div class="bg-white dark:bg-surface-dark rounded-lg shadow p-6">
    <?php
    // Block browser autofill only on address/city/state (Places owns those).
    // Name, email, phone, zip keep normal browser autofill.
    $lockAf = 'autocomplete="new-password" autocorrect="off" autocapitalize="off" spellcheck="false" data-lpignore="true" data-1p-ignore="true" data-bwignore="true" data-form-type="other"';
    ?>
    <form method="POST" action="" id="create-shipment-form" enctype="multipart/form-data" autocomplete="on">
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            <!-- Sender Information -->
            <div class="md:col-span-2">
                <h2 class="text-xl font-bold text-gray-800 dark:text-white mb-4">Sender Information</h2>
            </div>
            
            <div>
                <label class="block text-sm font-bold text-gray-700 dark:text-gray-300 mb-2" for="sender_name">Name *</label>
                <input type="text" id="sender_name" name="sender_name" required autocomplete="section-sender name"
                       class="w-full px-4 py-2 border border-gray-300 dark:border-gray-600 rounded bg-white dark:bg-background-dark text-gray-800 dark:text-white focus:ring-2 focus:ring-primary">
            </div>
            
            <div>
                <label class="block text-sm font-bold text-gray-700 dark:text-gray-300 mb-2" for="sender_address">Address *</label>
                <input type="text" id="sender_address" name="sender_address" required <?php echo $lockAf; ?>
                       class="w-full px-4 py-2 border border-gray-300 dark:border-gray-600 rounded bg-white dark:bg-background-dark text-gray-800 dark:text-white focus:ring-2 focus:ring-primary"
                       placeholder="Start typing and select from Google suggestions">
                <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">Select a suggestion so the route map can plot the pickup point.</p>
                <input type="hidden" id="sender_latitude" name="sender_latitude" autocomplete="off">
                <input type="hidden" id="sender_longitude" name="sender_longitude" autocomplete="off">
                <input type="hidden" id="sender_country" name="sender_country" value="" autocomplete="off">
            </div>
            
            <div>
                <label class="block text-sm font-bold text-gray-700 dark:text-gray-300 mb-2" for="sender_city">City</label>
                <input type="text" id="sender_city" name="sender_city" <?php echo $lockAf; ?>
                       class="w-full px-4 py-2 border border-gray-300 dark:border-gray-600 rounded bg-white dark:bg-background-dark text-gray-800 dark:text-white focus:ring-2 focus:ring-primary">
            </div>
            
            <div>
                <label class="block text-sm font-bold text-gray-700 dark:text-gray-300 mb-2" for="sender_state">State</label>
                <input type="text" id="sender_state" name="sender_state" <?php echo $lockAf; ?>
                       class="w-full px-4 py-2 border border-gray-300 dark:border-gray-600 rounded bg-white dark:bg-background-dark text-gray-800 dark:text-white focus:ring-2 focus:ring-primary">
            </div>
            
            <div>
                <label class="block text-sm font-bold text-gray-700 dark:text-gray-300 mb-2" for="sender_zip">ZIP Code</label>
                <input type="text" id="sender_zip" name="sender_zip" autocomplete="section-sender postal-code"
                       class="w-full px-4 py-2 border border-gray-300 dark:border-gray-600 rounded bg-white dark:bg-background-dark text-gray-800 dark:text-white focus:ring-2 focus:ring-primary">
            </div>
            
            <div>
                <label class="block text-sm font-bold text-gray-700 dark:text-gray-300 mb-2" for="sender_email">Email</label>
                <input type="email" id="sender_email" name="sender_email" autocomplete="section-sender email"
                       class="w-full px-4 py-2 border border-gray-300 dark:border-gray-600 rounded bg-white dark:bg-background-dark text-gray-800 dark:text-white focus:ring-2 focus:ring-primary"
                       placeholder="sender@example.com">
            </div>
            
            <div>
                <label class="block text-sm font-bold text-gray-700 dark:text-gray-300 mb-2" for="sender_phone">Phone Number</label>
                <input type="tel" id="sender_phone" name="sender_phone" autocomplete="section-sender tel"
                       class="w-full px-4 py-2 border border-gray-300 dark:border-gray-600 rounded bg-white dark:bg-background-dark text-gray-800 dark:text-white focus:ring-2 focus:ring-primary"
                       placeholder="+1 (555) 123-4567">
            </div>
            
            <!-- Recipient Information -->
            <div class="md:col-span-2 mt-6">
                <h2 class="text-xl font-bold text-gray-800 dark:text-white mb-4">Recipient Information</h2>
            </div>
            
            <div>
                <label class="block text-sm font-bold text-gray-700 dark:text-gray-300 mb-2" for="recipient_name">Name *</label>
                <input type="text" id="recipient_name" name="recipient_name" required autocomplete="section-recipient name"
                       class="w-full px-4 py-2 border border-gray-300 dark:border-gray-600 rounded bg-white dark:bg-background-dark text-gray-800 dark:text-white focus:ring-2 focus:ring-primary">
            </div>
            
            <div>
                <label class="block text-sm font-bold text-gray-700 dark:text-gray-300 mb-2" for="recipient_address">Address *</label>
                <input type="text" id="recipient_address" name="recipient_address" required <?php echo $lockAf; ?>
                       class="w-full px-4 py-2 border border-gray-300 dark:border-gray-600 rounded bg-white dark:bg-background-dark text-gray-800 dark:text-white focus:ring-2 focus:ring-primary"
                       placeholder="Start typing and select from Google suggestions">
                <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">Select a suggestion so the route map can plot the drop-off point.</p>
                <input type="hidden" id="recipient_latitude" name="recipient_latitude" autocomplete="off">
                <input type="hidden" id="recipient_longitude" name="recipient_longitude" autocomplete="off">
                <input type="hidden" id="recipient_country" name="recipient_country" value="" autocomplete="off">
            </div>
            
            <div>
                <label class="block text-sm font-bold text-gray-700 dark:text-gray-300 mb-2" for="recipient_city">City</label>
                <input type="text" id="recipient_city" name="recipient_city" <?php echo $lockAf; ?>
                       class="w-full px-4 py-2 border border-gray-300 dark:border-gray-600 rounded bg-white dark:bg-background-dark text-gray-800 dark:text-white focus:ring-2 focus:ring-primary">
            </div>
            
            <div>
                <label class="block text-sm font-bold text-gray-700 dark:text-gray-300 mb-2" for="recipient_state">State</label>
                <input type="text" id="recipient_state" name="recipient_state" <?php echo $lockAf; ?>
                       class="w-full px-4 py-2 border border-gray-300 dark:border-gray-600 rounded bg-white dark:bg-background-dark text-gray-800 dark:text-white focus:ring-2 focus:ring-primary">
            </div>
            
            <div>
                <label class="block text-sm font-bold text-gray-700 dark:text-gray-300 mb-2" for="recipient_zip">ZIP Code</label>
                <input type="text" id="recipient_zip" name="recipient_zip" autocomplete="section-recipient postal-code"
                       class="w-full px-4 py-2 border border-gray-300 dark:border-gray-600 rounded bg-white dark:bg-background-dark text-gray-800 dark:text-white focus:ring-2 focus:ring-primary">
            </div>
            
            <div>
                <label class="block text-sm font-bold text-gray-700 dark:text-gray-300 mb-2" for="recipient_email">Email</label>
                <input type="email" id="recipient_email" name="recipient_email" autocomplete="section-recipient email"
                       class="w-full px-4 py-2 border border-gray-300 dark:border-gray-600 rounded bg-white dark:bg-background-dark text-gray-800 dark:text-white focus:ring-2 focus:ring-primary"
                       placeholder="recipient@example.com">
            </div>
            
            <div>
                <label class="block text-sm font-bold text-gray-700 dark:text-gray-300 mb-2" for="recipient_phone">Phone Number</label>
                <input type="tel" id="recipient_phone" name="recipient_phone" autocomplete="section-recipient tel"
                       class="w-full px-4 py-2 border border-gray-300 dark:border-gray-600 rounded bg-white dark:bg-background-dark text-gray-800 dark:text-white focus:ring-2 focus:ring-primary"
                       placeholder="+1 (555) 123-4567">
            </div>
            
            <!-- Hidden map endpoints (auto-filled from sender / recipient addresses) -->
            <input type="hidden" id="pickup_location" name="pickup_location" value="">
            <input type="hidden" id="pickup_latitude" name="pickup_latitude" value="">
            <input type="hidden" id="pickup_longitude" name="pickup_longitude" value="">
            <input type="hidden" id="dropoff_location" name="dropoff_location" value="">
            <input type="hidden" id="dropoff_latitude" name="dropoff_latitude" value="">
            <input type="hidden" id="dropoff_longitude" name="dropoff_longitude" value="">

            <!-- Route Map Preview -->
            <div class="md:col-span-2 mt-6">
                <h2 class="text-xl font-bold text-gray-800 dark:text-white mb-4">Route Preview</h2>
                <p class="text-sm text-gray-600 dark:text-gray-400 mb-4">Automatically plotted from the sender and recipient addresses</p>
                <div id="route_map_preview" class="w-full h-96 bg-gray-200 dark:bg-gray-800 rounded border border-gray-300 dark:border-gray-600"></div>
                <p id="route_map_message" class="text-sm text-gray-500 dark:text-gray-400 mt-2">Select sender and recipient addresses from the suggestions to see the route.</p>
            </div>
            
            <!-- Shipment Details -->
            <div class="md:col-span-2 mt-6">
                <h2 class="text-xl font-bold text-gray-800 dark:text-white mb-4">Shipment Details</h2>
            </div>
            
            <div>
                <label class="block text-sm font-bold text-gray-700 dark:text-gray-300 mb-2" for="status">Status *</label>
                <select id="status" name="status" required
                        class="w-full px-4 py-2 border border-gray-300 dark:border-gray-600 rounded bg-white dark:bg-background-dark text-gray-800 dark:text-white focus:ring-2 focus:ring-primary">
                    <option value="Label Created">Label Created</option>
                    <option value="Pending" selected>Pending</option>
                    <option value="Picked Up">Picked Up</option>
                    <option value="In Transit">In Transit</option>
                    <option value="On Hold">On Hold</option>
                    <option value="Out for Delivery">Out for Delivery</option>
                    <option value="Delivered">Delivered</option>
                    <option value="Cancelled">Cancelled</option>
                    <option value="Returned">Returned</option>
                    <option value="Exception">Exception</option>
                </select>
            </div>
            
            <div>
                <label class="block text-sm font-bold text-gray-700 dark:text-gray-300 mb-2" for="service_type">Service Type *</label>
                <select id="service_type" name="service_type" required
                        class="w-full px-4 py-2 border border-gray-300 dark:border-gray-600 rounded bg-white dark:bg-background-dark text-gray-800 dark:text-white focus:ring-2 focus:ring-primary">
                    <?php 
                    $companyName = getSetting('company_name', 'FedEx');
                    ?>
                    <option value="<?php echo htmlspecialchars($companyName); ?> Ground"><?php echo htmlspecialchars($companyName); ?> Ground</option>
                    <option value="<?php echo htmlspecialchars($companyName); ?> Express"><?php echo htmlspecialchars($companyName); ?> Express</option>
                    <option value="<?php echo htmlspecialchars($companyName); ?> Standard Overnight"><?php echo htmlspecialchars($companyName); ?> Standard Overnight</option>
                    <option value="<?php echo htmlspecialchars($companyName); ?> 2Day"><?php echo htmlspecialchars($companyName); ?> 2Day</option>
                </select>
            </div>
            
            <div>
                <label class="block text-sm font-bold text-gray-700 dark:text-gray-300 mb-2" for="weight">Weight (lbs)</label>
                <input type="number" step="0.01" id="weight" name="weight"
                       class="w-full px-4 py-2 border border-gray-300 dark:border-gray-600 rounded bg-white dark:bg-background-dark text-gray-800 dark:text-white focus:ring-2 focus:ring-primary">
            </div>
            
            <div>
                <label class="block text-sm font-bold text-gray-700 dark:text-gray-300 mb-2" for="dimensions">Dimensions (LxWxH)</label>
                <input type="text" id="dimensions" name="dimensions" placeholder="e.g., 12x10x8"
                       class="w-full px-4 py-2 border border-gray-300 dark:border-gray-600 rounded bg-white dark:bg-background-dark text-gray-800 dark:text-white focus:ring-2 focus:ring-primary">
            </div>
            
            <div>
                <label class="block text-sm font-bold text-gray-700 dark:text-gray-300 mb-2" for="item_image">Item Image (Optional)</label>
                <input type="file" id="item_image" name="item_image" accept="image/png,image/jpeg,image/jpg,image/gif,image/webp"
                       class="w-full px-4 py-2 border border-gray-300 dark:border-gray-600 rounded bg-white dark:bg-background-dark text-gray-800 dark:text-white focus:ring-2 focus:ring-primary">
                <p class="mt-1 text-xs text-gray-500">Upload PNG, JPG, GIF, or WEBP (max 5MB)</p>
            </div>
            
            <div>
                <label class="block text-sm font-bold text-gray-700 dark:text-gray-300 mb-2" for="estimated_delivery">Estimated Delivery Date &amp; Time</label>
                <input type="datetime-local" id="estimated_delivery" name="estimated_delivery"
                       class="w-full px-4 py-2 border border-gray-300 dark:border-gray-600 rounded bg-white dark:bg-background-dark text-gray-800 dark:text-white focus:ring-2 focus:ring-primary">
                <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">Optional. Set both date and delivery time.</p>
            </div>

            <div>
                <label class="block text-sm font-bold text-gray-700 dark:text-gray-300 mb-2" for="shipment_created_at">Creation Date &amp; Time</label>
                <input type="datetime-local" id="shipment_created_at" name="shipment_created_at"
                       class="w-full px-4 py-2 border border-gray-300 dark:border-gray-600 rounded bg-white dark:bg-background-dark text-gray-800 dark:text-white focus:ring-2 focus:ring-primary">
                <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">Leave blank to use the current date and time.</p>
            </div>
            
            <div>
                <label class="block text-sm font-bold text-gray-700 dark:text-gray-300 mb-2" for="shipment_worth">Shipment Worth ($)</label>
                <input type="number" step="0.01" min="0" id="shipment_worth" name="shipment_worth"
                       class="w-full px-4 py-2 border border-gray-300 dark:border-gray-600 rounded bg-white dark:bg-background-dark text-gray-800 dark:text-white focus:ring-2 focus:ring-primary"
                       placeholder="0.00">
                <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">Declared value of the shipment contents</p>
            </div>
            
            <div>
                <label class="block text-sm font-bold text-gray-700 dark:text-gray-300 mb-2" for="base_cost">Shipping Cost ($)</label>
                <input type="number" step="0.01" min="0" id="base_cost" name="base_cost"
                       class="w-full px-4 py-2 border border-gray-300 dark:border-gray-600 rounded bg-white dark:bg-background-dark text-gray-800 dark:text-white focus:ring-2 focus:ring-primary"
                       placeholder="0.00">
            </div>

            <div>
                <label class="block text-sm font-bold text-gray-700 dark:text-gray-300 mb-2" for="clearance_cost">Clearance Cost ($)</label>
                <input type="number" step="0.01" min="0" id="clearance_cost" name="clearance_cost"
                       class="w-full px-4 py-2 border border-gray-300 dark:border-gray-600 rounded bg-white dark:bg-background-dark text-gray-800 dark:text-white focus:ring-2 focus:ring-primary"
                       placeholder="0.00">
            </div>
            
            <div>
                <label class="block text-sm font-bold text-gray-700 dark:text-gray-300 mb-2" for="total_cost">Total Due ($)</label>
                <input type="text" id="total_cost" name="total_cost" readonly
                       class="w-full px-4 py-2 border border-gray-300 dark:border-gray-600 rounded bg-gray-50 dark:bg-gray-800 text-gray-800 dark:text-white font-semibold"
                       placeholder="0.00"
                       value="0.00">
                <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">Auto-calculated: Shipping Cost + Clearance Cost</p>
            </div>
            
            <div class="md:col-span-2">
                <label class="block text-sm font-bold text-gray-700 dark:text-gray-300 mb-2" for="admin_comment">Remark (Optional)</label>
                <textarea id="admin_comment" name="admin_comment" rows="4"
                          class="w-full px-4 py-2 border border-gray-300 dark:border-gray-600 rounded bg-white dark:bg-background-dark text-gray-800 dark:text-white focus:ring-2 focus:ring-primary"
                          placeholder="Admin remark (shown under Travel History)"></textarea>
            </div>
            
            <!-- Email Notification Toggle -->
            <div class="md:col-span-2">
                <div class="flex items-start gap-4 p-4 rounded-xl border-2 border-primary/20 bg-primary/5 dark:bg-primary/10">
                    <div class="flex items-center h-5">
                        <input id="send_email_notification" name="send_email_notification" type="checkbox" value="1" checked
                               class="w-4 h-4 text-primary bg-gray-100 border-gray-300 rounded focus:ring-primary focus:ring-2 dark:bg-gray-700 dark:border-gray-600">
                    </div>
                    <div class="flex-1">
                        <label for="send_email_notification" class="text-sm font-bold text-gray-700 dark:text-gray-300 cursor-pointer">
                            Send Delivery Notification Email to Recipient
                        </label>
                        <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">
                            Send an email notification to the recipient with tracking information and delivery details. The recipient will also receive email updates when shipment status changes.
                        </p>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="mt-8 flex gap-4">
            <button type="submit" class="bg-primary hover:bg-primary-dark text-white font-bold py-3 px-8 rounded uppercase tracking-wide transition-colors">
                Create Shipment
            </button>
            <a href="/admin/dashboard.php" class="bg-gray-300 dark:bg-gray-600 hover:bg-gray-400 dark:hover:bg-gray-700 text-gray-800 dark:text-white font-bold py-3 px-8 rounded uppercase tracking-wide transition-colors">
                Cancel
            </a>
        </div>
    </form>
</div>

<script>
(function() {
    let mapsReady = false;
    let geocoder = null;
    let routeUpdateTimer = null;
    let routeRequestId = 0;
    let lastDrawnKey = '';
    const placeSelectedAt = { sender: 0, recipient: 0 };
    const lastCommittedAddress = { sender: '', recipient: '' };
    const blurTimers = { sender: null, recipient: null };

    fetch('/api/settings.php?key=google_maps_api_key')
        .then(function (response) { return response.json(); })
        .then(function (data) {
            if (!(data.success && data.value)) {
                const msg = document.getElementById('route_map_message');
                if (msg) msg.textContent = 'Google Maps API key is not configured in Settings. Route preview is unavailable.';
                return;
            }
            const script = document.createElement('script');
            script.src = 'https://maps.googleapis.com/maps/api/js?key=' + encodeURIComponent(data.value) + '&libraries=places,geometry';
            script.async = true;
            script.defer = true;
            script.onload = function () {
                mapsReady = true;
                geocoder = new google.maps.Geocoder();
                initializeAddressSync();
            };
            script.onerror = function () {
                const msg = document.getElementById('route_map_message');
                if (msg) msg.textContent = 'Failed to load Google Maps. Check the API key and billing.';
            };
            document.head.appendChild(script);
        })
        .catch(function () {
            const msg = document.getElementById('route_map_message');
            if (msg) msg.textContent = 'Could not load map settings.';
        });

    function scheduleRouteMapUpdate(delayMs) {
        clearTimeout(routeUpdateTimer);
        routeUpdateTimer = setTimeout(function () {
            updateRouteMap();
        }, typeof delayMs === 'number' ? delayMs : 200);
    }

    function setEndpoint(endpoint, lat, lng, label) {
        const loc = document.getElementById(endpoint + '_location');
        const latEl = document.getElementById(endpoint + '_latitude');
        const lngEl = document.getElementById(endpoint + '_longitude');
        if (loc) loc.value = label || '';
        if (latEl) latEl.value = lat;
        if (lngEl) lngEl.value = lng;
        lastDrawnKey = ''; // allow redraw after intentional address commit
        scheduleRouteMapUpdate(200);
    }

    function syncFromPlace(endpoint, place, personPrefix) {
        if (!place || !place.geometry || !place.geometry.location) return;
        const lat = place.geometry.location.lat();
        const lng = place.geometry.location.lng();
        const label = place.formatted_address || place.name || (endpoint === 'pickup' ? 'Pickup' : 'Drop-off');

        // Cancel any pending blur-geocode from the same field (blur fires before place_changed)
        if (blurTimers[personPrefix]) {
            clearTimeout(blurTimers[personPrefix]);
            blurTimers[personPrefix] = null;
        }
        placeSelectedAt[personPrefix] = Date.now();
        lastCommittedAddress[personPrefix] = label;

        const personLat = document.getElementById(personPrefix + '_latitude');
        const personLng = document.getElementById(personPrefix + '_longitude');
        if (personLat) personLat.value = lat;
        if (personLng) personLng.value = lng;

        fillAddressFields(place, personPrefix);
        lockPlaceOwnedFields(personPrefix);
        setEndpoint(endpoint, lat, lng, label);
    }

    /** Keep address/city/state readonly unless the user is editing that field. */
    function lockPlaceOwnedFields(prefix) {
        ['address', 'city', 'state'].forEach(function (suffix) {
            const el = document.getElementById(prefix + '_' + suffix);
            if (!el) return;
            if (document.activeElement === el) return;
            el.setAttribute('readonly', 'readonly');
        });
    }

    function buildAddressQuery(prefix) {
        const parts = [
            document.getElementById(prefix + '_address')?.value,
            document.getElementById(prefix + '_city')?.value,
            document.getElementById(prefix + '_state')?.value,
            document.getElementById(prefix + '_zip')?.value,
            document.getElementById(prefix + '_country')?.value
        ].map(function (v) { return (v || '').trim(); }).filter(Boolean);
        return parts.join(', ');
    }

    function endpointHasCoords(endpoint) {
        const lat = parseFloat(document.getElementById(endpoint + '_latitude')?.value);
        const lng = parseFloat(document.getElementById(endpoint + '_longitude')?.value);
        return !isNaN(lat) && !isNaN(lng) && !(lat === 0 && lng === 0);
    }

    /**
     * Gated fallback: typed address without picking a suggestion.
     * Skips when Places already committed this exact address, or within 1.5s of place_changed.
     */
    function geocodePersonAddressFallback(prefix, endpoint) {
        if (!mapsReady || !geocoder) return;
        if (Date.now() - (placeSelectedAt[prefix] || 0) < 1500) return;

        const input = document.getElementById(prefix + '_address');
        const currentText = (input && input.value ? input.value : '').trim();
        if (!currentText || currentText.length < 5) return;

        // Already committed this exact address (Places or prior geocode) — skip when tabbing away
        if (lastCommittedAddress[prefix] && currentText === lastCommittedAddress[prefix]) {
            return;
        }

        // Backup: coords + location label already match the visible address
        if (endpointHasCoords(endpoint)) {
            const loc = (document.getElementById(endpoint + '_location')?.value || '').trim();
            if (loc && currentText === loc) return;
        }

        geocoder.geocode({ address: buildAddressQuery(prefix) }, function (results, status) {
            if (status !== 'OK' || !results || !results[0] || !results[0].geometry) {
                return;
            }
            if (Date.now() - (placeSelectedAt[prefix] || 0) < 1500) return;

            const place = results[0];
            const lat = place.geometry.location.lat();
            const lng = place.geometry.location.lng();
            const label = place.formatted_address || currentText;

            const personLat = document.getElementById(prefix + '_latitude');
            const personLng = document.getElementById(prefix + '_longitude');
            if (personLat) personLat.value = lat;
            if (personLng) personLng.value = lng;

            if (place.address_components) {
                fillAddressFields(place, prefix, { onlyEmpty: true });
            }

            if (input && place.formatted_address) {
                input.value = place.formatted_address;
            }
            lastCommittedAddress[prefix] = place.formatted_address || label;
            lockPlaceOwnedFields(prefix);
            setEndpoint(endpoint, lat, lng, label);
        });
    }

    function bindPersonAddress(prefix, endpoint) {
        const input = document.getElementById(prefix + '_address');
        if (!input || !google.maps.places) return;

        input.setAttribute('autocomplete', 'new-password');
        input.setAttribute('autocorrect', 'off');
        input.setAttribute('autocapitalize', 'off');
        input.setAttribute('spellcheck', 'false');
        input.setAttribute('data-lpignore', 'true');
        input.setAttribute('data-1p-ignore', 'true');

        const ac = new google.maps.places.Autocomplete(input, {
            types: ['address'],
            fields: ['formatted_address', 'address_components', 'geometry', 'name']
        });
        ac.addListener('place_changed', function () {
            const place = ac.getPlace();
            if (place && place.geometry) {
                if (place.formatted_address) input.value = place.formatted_address;
                syncFromPlace(endpoint, place, prefix);
            }
        });

        // Optional gated blur fallback only (no city/state listeners)
        input.addEventListener('blur', function () {
            if (blurTimers[prefix]) clearTimeout(blurTimers[prefix]);
            blurTimers[prefix] = setTimeout(function () {
                blurTimers[prefix] = null;
                geocodePersonAddressFallback(prefix, endpoint);
            }, 700);
        });
    }

    function initializeAddressSync() {
        bindPersonAddress('sender', 'pickup');
        bindPersonAddress('recipient', 'dropoff');
        scheduleRouteMapUpdate(300);
    }

    function clearRouteOverlays() {
        if (window.routeFallbackPolyline) {
            window.routeFallbackPolyline.setMap(null);
            window.routeFallbackPolyline = null;
        }
        if (window.routeEndpointMarkers) {
            window.routeEndpointMarkers.forEach(function (m) { m.setMap(null); });
            window.routeEndpointMarkers = [];
        }
        if (window.routeEndpointInfos) {
            window.routeEndpointInfos.forEach(function (iw) {
                try { iw.close(); } catch (e) {}
            });
            window.routeEndpointInfos = [];
        }
    }

    function placeEndpointMarkers(pickupLat, pickupLng, dropoffLat, dropoffLng, pickupLabel, dropoffLabel) {
        if (window.routeEndpointMarkers) {
            window.routeEndpointMarkers.forEach(function (m) { m.setMap(null); });
        }
        window.routeEndpointMarkers = [
            new google.maps.Marker({
                position: { lat: pickupLat, lng: pickupLng },
                map: window.routeMapInstance,
                title: 'Pickup: ' + pickupLabel
            }),
            new google.maps.Marker({
                position: { lat: dropoffLat, lng: dropoffLng },
                map: window.routeMapInstance,
                title: 'Delivery: ' + dropoffLabel
            })
        ];
        // Intentionally no InfoWindows — they steal focus from address inputs
    }

    function updateRouteMap() {
        const pickupLat = parseFloat(document.getElementById('pickup_latitude')?.value);
        const pickupLng = parseFloat(document.getElementById('pickup_longitude')?.value);
        const dropoffLat = parseFloat(document.getElementById('dropoff_latitude')?.value);
        const dropoffLng = parseFloat(document.getElementById('dropoff_longitude')?.value);
        const routeMapDiv = document.getElementById('route_map_preview');
        const routeMapMessage = document.getElementById('route_map_message');
        if (!routeMapDiv || !routeMapMessage) return;

        const ready = mapsReady
            && !isNaN(pickupLat) && !isNaN(pickupLng)
            && !isNaN(dropoffLat) && !isNaN(dropoffLng)
            && !(pickupLat === 0 && pickupLng === 0)
            && !(dropoffLat === 0 && dropoffLng === 0);

        if (!ready) {
            routeMapMessage.style.display = 'block';
            routeMapMessage.classList.remove('text-red-500');
            if (!mapsReady) {
                routeMapMessage.textContent = 'Loading map…';
            } else if (isNaN(pickupLat) || isNaN(pickupLng)) {
                routeMapMessage.textContent = 'Waiting for sender address…';
            } else if (isNaN(dropoffLat) || isNaN(dropoffLng)) {
                routeMapMessage.textContent = 'Waiting for recipient address…';
            } else {
                routeMapMessage.textContent = 'Select sender and recipient addresses from the suggestions to see the route.';
            }
            return;
        }

        const drawKey = pickupLat.toFixed(6) + ',' + pickupLng.toFixed(6) + '|' + dropoffLat.toFixed(6) + ',' + dropoffLng.toFixed(6);
        if (drawKey === lastDrawnKey && window.routeMapInstance) {
            return;
        }

        if (!window.routeMapInstance) {
            window.routeMapInstance = new google.maps.Map(routeMapDiv, {
                zoom: 6,
                center: { lat: (pickupLat + dropoffLat) / 2, lng: (pickupLng + dropoffLng) / 2 },
                mapTypeId: 'roadmap'
            });
        }

        if (!window.routeDirectionsService) {
            window.routeDirectionsService = new google.maps.DirectionsService();
        }
        if (!window.routeDirectionsRenderer) {
            window.routeDirectionsRenderer = new google.maps.DirectionsRenderer({
                map: window.routeMapInstance,
                suppressMarkers: true,
                polylineOptions: {
                    strokeColor: '#4D148C',
                    strokeWeight: 5,
                    strokeOpacity: 0.8
                }
            });
        } else {
            window.routeDirectionsRenderer.setMap(window.routeMapInstance);
        }

        const requestId = ++routeRequestId;
        const pickupLabel = document.getElementById('pickup_location')?.value || 'Pickup';
        const dropoffLabel = document.getElementById('dropoff_location')?.value || 'Drop-off';

        window.routeDirectionsService.route({
            origin: { lat: pickupLat, lng: pickupLng },
            destination: { lat: dropoffLat, lng: dropoffLng },
            travelMode: google.maps.TravelMode.DRIVING
        }, function (response, status) {
            if (requestId !== routeRequestId) {
                return; // stale response
            }

            clearRouteOverlays();

            if (status === 'OK') {
                try {
                    window.routeDirectionsRenderer.setDirections(response);
                } catch (e) {}
                const bounds = new google.maps.LatLngBounds();
                bounds.extend({ lat: pickupLat, lng: pickupLng });
                bounds.extend({ lat: dropoffLat, lng: dropoffLng });
                window.routeMapInstance.fitBounds(bounds, { top: 48, right: 48, bottom: 48, left: 48 });
                placeEndpointMarkers(pickupLat, pickupLng, dropoffLat, dropoffLng, pickupLabel, dropoffLabel);
                routeMapMessage.style.display = 'none';
                routeMapMessage.classList.remove('text-red-500');
                lastDrawnKey = drawKey;
                return;
            }

            // Stable direct-line fallback (overseas / ZERO_RESULTS)
            try { window.routeDirectionsRenderer.setDirections({ routes: [] }); } catch (e) {}
            const originLatLng = { lat: pickupLat, lng: pickupLng };
            const destLatLng = { lat: dropoffLat, lng: dropoffLng };
            window.routeFallbackPolyline = new google.maps.Polyline({
                path: [originLatLng, destLatLng],
                geodesic: true,
                strokeColor: '#4D148C',
                strokeOpacity: 0.9,
                strokeWeight: 4
            });
            window.routeFallbackPolyline.setMap(window.routeMapInstance);
            placeEndpointMarkers(pickupLat, pickupLng, dropoffLat, dropoffLng, pickupLabel, dropoffLabel);
            const bounds = new google.maps.LatLngBounds();
            bounds.extend(originLatLng);
            bounds.extend(destLatLng);
            window.routeMapInstance.fitBounds(bounds, { top: 48, right: 48, bottom: 48, left: 48 });
            routeMapMessage.textContent = 'Exact driving route unavailable — showing direct line between locations.';
            routeMapMessage.style.display = 'block';
            routeMapMessage.classList.add('text-red-500');
            lastDrawnKey = drawKey;
        });
    }

    function fillAddressFields(place, prefix, options) {
        const onlyEmpty = !!(options && options.onlyEmpty);
        const addressComponents = place.address_components || [];
        let citySet = false;

        addressComponents.forEach(function (component) {
            const types = component.types;
            if (types.includes('locality')) {
                const el = document.getElementById(prefix + '_city');
                if (el && (!onlyEmpty || !el.value.trim())) {
                    el.value = component.long_name;
                    citySet = true;
                }
            } else if (types.includes('administrative_area_level_1')) {
                const el = document.getElementById(prefix + '_state');
                if (el && (!onlyEmpty || !el.value.trim())) {
                    el.value = component.long_name;
                }
            } else if (types.includes('postal_code')) {
                const el = document.getElementById(prefix + '_zip');
                if (el && (!onlyEmpty || !el.value.trim())) {
                    el.value = component.long_name;
                }
            } else if (types.includes('country')) {
                const el = document.getElementById(prefix + '_country');
                if (el && (!onlyEmpty || !el.value.trim())) {
                    el.value = component.long_name;
                }
            }
        });

        // International addresses often use postal_town instead of locality
        if (!citySet) {
            const cityFallbacks = [
                'postal_town',
                'administrative_area_level_2',
                'sublocality',
                'sublocality_level_1'
            ];
            for (let i = 0; i < addressComponents.length && !citySet; i++) {
                const component = addressComponents[i];
                const types = component.types || [];
                const matched = cityFallbacks.some(function (t) { return types.indexOf(t) !== -1; });
                if (!matched) continue;
                const el = document.getElementById(prefix + '_city');
                if (el && (!onlyEmpty || !el.value.trim())) {
                    el.value = component.long_name;
                    citySet = true;
                }
            }
        }
    }
})();

function calculateTotalCost() {
    const shippingCost = parseFloat(document.getElementById('base_cost').value) || 0;
    const clearanceCost = parseFloat(document.getElementById('clearance_cost').value) || 0;
    const totalCostField = document.getElementById('total_cost');
    if (totalCostField) {
        totalCostField.value = '$' + (shippingCost + clearanceCost).toFixed(2);
    }
}

document.addEventListener('DOMContentLoaded', function () {
    // Only lock address/city/state. Name, email, phone, zip keep browser autofill.
    // Readonly blocks Chrome from overwriting Places values when clicking email/phone.
    const form = document.getElementById('create-shipment-form');
    if (form) {
        const placeOwnedIds = [
            'sender_address', 'sender_city', 'sender_state',
            'recipient_address', 'recipient_city', 'recipient_state'
        ];
        placeOwnedIds.forEach(function (id) {
            const el = document.getElementById(id);
            if (!el) return;
            el.setAttribute('readonly', 'readonly');
            el.addEventListener('focus', function () {
                el.removeAttribute('readonly');
            });
            el.addEventListener('mousedown', function () {
                el.removeAttribute('readonly');
            });
            el.addEventListener('blur', function () {
                // Re-lock after edit so later autofill into email/phone cannot wipe them
                setTimeout(function () {
                    if (document.activeElement !== el) {
                        el.setAttribute('readonly', 'readonly');
                    }
                }, 0);
            });
        });
    }

    ['base_cost', 'clearance_cost'].forEach(function (id) {
        const el = document.getElementById(id);
        if (el) {
            el.addEventListener('input', calculateTotalCost);
            el.addEventListener('blur', calculateTotalCost);
        }
    });

    const submitButton = form && form.querySelector('button[type="submit"]');
    if (form && submitButton) {
        form.addEventListener('submit', function () {
            calculateTotalCost();
            const totalCostField = document.getElementById('total_cost');
            if (totalCostField) {
                let totalCostValue = totalCostField.value.replace('$', '').trim();
                if (isNaN(parseFloat(totalCostValue))) totalCostValue = '0.00';
                totalCostField.value = totalCostValue;
            }
            // Readonly fields still submit; unlock just in case some browsers skip them
            ['sender_address', 'sender_city', 'sender_state', 'recipient_address', 'recipient_city', 'recipient_state'].forEach(function (id) {
                const el = document.getElementById(id);
                if (el) el.removeAttribute('readonly');
            });
            submitButton.disabled = true;
            submitButton.textContent = 'Creating...';
            submitButton.classList.add('opacity-50', 'cursor-not-allowed');
        });
    }
});
</script>

<?php include __DIR__ . '/includes/admin-footer.php'; ?>

