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
        'sender_country' => $_POST['sender_country'] ?? 'United States',
        'sender_email' => $_POST['sender_email'] ?? '',
        'sender_phone' => $_POST['sender_phone'] ?? '',
        'sender_latitude' => $_POST['sender_latitude'] ?? null,
        'sender_longitude' => $_POST['sender_longitude'] ?? null,
        'recipient_name' => $_POST['recipient_name'] ?? '',
        'recipient_address' => $_POST['recipient_address'] ?? '',
        'recipient_city' => $_POST['recipient_city'] ?? '',
        'recipient_state' => $_POST['recipient_state'] ?? '',
        'recipient_zip' => $_POST['recipient_zip'] ?? '',
        'recipient_country' => $_POST['recipient_country'] ?? 'United States',
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
            $success = 'Shipment created successfully! Tracking Number: ' . $result['tracking_number'];
            // Clear form by redirecting
            header('Location: /admin/create-shipment.php?success=1');
            exit;
        } else {
            $error = isset($result['error']) ? $result['error'] : 'Failed to create shipment. HTTP Code: ' . ($httpCode ?: '0');
        }
    }
}

// Check for success message from redirect
if (isset($_GET['success']) && $_GET['success'] == '1') {
    $success = 'Shipment created successfully!';
}

// Only render the admin layout AFTER handling POST/redirects
include __DIR__ . '/includes/admin-header.php';
?>
<div class="mb-8">
    <h1 class="text-3xl font-light text-gray-800 dark:text-white mb-2">Create New Shipment</h1>
    <p class="text-gray-600 dark:text-gray-400">Create a new shipment and generate a tracking number</p>
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

<div class="bg-white dark:bg-surface-dark rounded-lg shadow p-6">
    <form method="POST" action="" id="create-shipment-form" enctype="multipart/form-data">
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            <!-- Sender Information -->
            <div class="md:col-span-2">
                <h2 class="text-xl font-bold text-gray-800 dark:text-white mb-4">Sender Information</h2>
            </div>
            
            <div>
                <label class="block text-sm font-bold text-gray-700 dark:text-gray-300 mb-2" for="sender_name">Name *</label>
                <input type="text" id="sender_name" name="sender_name" required
                       class="w-full px-4 py-2 border border-gray-300 dark:border-gray-600 rounded bg-white dark:bg-background-dark text-gray-800 dark:text-white focus:ring-2 focus:ring-primary">
            </div>
            
            <div>
                <label class="block text-sm font-bold text-gray-700 dark:text-gray-300 mb-2" for="sender_address">Address *</label>
                <input type="text" id="sender_address" name="sender_address" required
                       class="w-full px-4 py-2 border border-gray-300 dark:border-gray-600 rounded bg-white dark:bg-background-dark text-gray-800 dark:text-white focus:ring-2 focus:ring-primary">
                <input type="hidden" id="sender_latitude" name="sender_latitude">
                <input type="hidden" id="sender_longitude" name="sender_longitude">
            </div>
            
            <div>
                <label class="block text-sm font-bold text-gray-700 dark:text-gray-300 mb-2" for="sender_city">City</label>
                <input type="text" id="sender_city" name="sender_city"
                       class="w-full px-4 py-2 border border-gray-300 dark:border-gray-600 rounded bg-white dark:bg-background-dark text-gray-800 dark:text-white focus:ring-2 focus:ring-primary">
            </div>
            
            <div>
                <label class="block text-sm font-bold text-gray-700 dark:text-gray-300 mb-2" for="sender_state">State</label>
                <input type="text" id="sender_state" name="sender_state"
                       class="w-full px-4 py-2 border border-gray-300 dark:border-gray-600 rounded bg-white dark:bg-background-dark text-gray-800 dark:text-white focus:ring-2 focus:ring-primary">
            </div>
            
            <div>
                <label class="block text-sm font-bold text-gray-700 dark:text-gray-300 mb-2" for="sender_zip">ZIP Code</label>
                <input type="text" id="sender_zip" name="sender_zip"
                       class="w-full px-4 py-2 border border-gray-300 dark:border-gray-600 rounded bg-white dark:bg-background-dark text-gray-800 dark:text-white focus:ring-2 focus:ring-primary">
            </div>
            
            <div>
                <label class="block text-sm font-bold text-gray-700 dark:text-gray-300 mb-2" for="sender_email">Email</label>
                <input type="email" id="sender_email" name="sender_email"
                       class="w-full px-4 py-2 border border-gray-300 dark:border-gray-600 rounded bg-white dark:bg-background-dark text-gray-800 dark:text-white focus:ring-2 focus:ring-primary"
                       placeholder="sender@example.com">
            </div>
            
            <div>
                <label class="block text-sm font-bold text-gray-700 dark:text-gray-300 mb-2" for="sender_phone">Phone Number</label>
                <input type="tel" id="sender_phone" name="sender_phone"
                       class="w-full px-4 py-2 border border-gray-300 dark:border-gray-600 rounded bg-white dark:bg-background-dark text-gray-800 dark:text-white focus:ring-2 focus:ring-primary"
                       placeholder="+1 (555) 123-4567">
            </div>
            
            <!-- Recipient Information -->
            <div class="md:col-span-2 mt-6">
                <h2 class="text-xl font-bold text-gray-800 dark:text-white mb-4">Recipient Information</h2>
            </div>
            
            <div>
                <label class="block text-sm font-bold text-gray-700 dark:text-gray-300 mb-2" for="recipient_name">Name *</label>
                <input type="text" id="recipient_name" name="recipient_name" required
                       class="w-full px-4 py-2 border border-gray-300 dark:border-gray-600 rounded bg-white dark:bg-background-dark text-gray-800 dark:text-white focus:ring-2 focus:ring-primary">
            </div>
            
            <div>
                <label class="block text-sm font-bold text-gray-700 dark:text-gray-300 mb-2" for="recipient_address">Address *</label>
                <input type="text" id="recipient_address" name="recipient_address" required
                       class="w-full px-4 py-2 border border-gray-300 dark:border-gray-600 rounded bg-white dark:bg-background-dark text-gray-800 dark:text-white focus:ring-2 focus:ring-primary">
                <input type="hidden" id="recipient_latitude" name="recipient_latitude">
                <input type="hidden" id="recipient_longitude" name="recipient_longitude">
            </div>
            
            <div>
                <label class="block text-sm font-bold text-gray-700 dark:text-gray-300 mb-2" for="recipient_city">City</label>
                <input type="text" id="recipient_city" name="recipient_city"
                       class="w-full px-4 py-2 border border-gray-300 dark:border-gray-600 rounded bg-white dark:bg-background-dark text-gray-800 dark:text-white focus:ring-2 focus:ring-primary">
            </div>
            
            <div>
                <label class="block text-sm font-bold text-gray-700 dark:text-gray-300 mb-2" for="recipient_state">State</label>
                <input type="text" id="recipient_state" name="recipient_state"
                       class="w-full px-4 py-2 border border-gray-300 dark:border-gray-600 rounded bg-white dark:bg-background-dark text-gray-800 dark:text-white focus:ring-2 focus:ring-primary">
            </div>
            
            <div>
                <label class="block text-sm font-bold text-gray-700 dark:text-gray-300 mb-2" for="recipient_zip">ZIP Code</label>
                <input type="text" id="recipient_zip" name="recipient_zip"
                       class="w-full px-4 py-2 border border-gray-300 dark:border-gray-600 rounded bg-white dark:bg-background-dark text-gray-800 dark:text-white focus:ring-2 focus:ring-primary">
            </div>
            
            <div>
                <label class="block text-sm font-bold text-gray-700 dark:text-gray-300 mb-2" for="recipient_email">Email</label>
                <input type="email" id="recipient_email" name="recipient_email"
                       class="w-full px-4 py-2 border border-gray-300 dark:border-gray-600 rounded bg-white dark:bg-background-dark text-gray-800 dark:text-white focus:ring-2 focus:ring-primary"
                       placeholder="recipient@example.com">
            </div>
            
            <div>
                <label class="block text-sm font-bold text-gray-700 dark:text-gray-300 mb-2" for="recipient_phone">Phone Number</label>
                <input type="tel" id="recipient_phone" name="recipient_phone"
                       class="w-full px-4 py-2 border border-gray-300 dark:border-gray-600 rounded bg-white dark:bg-background-dark text-gray-800 dark:text-white focus:ring-2 focus:ring-primary"
                       placeholder="+1 (555) 123-4567">
            </div>
            
            <!-- Pickup Location -->
            <div class="md:col-span-2 mt-6">
                <h2 class="text-xl font-bold text-gray-800 dark:text-white mb-4">Pickup Location (Optional)</h2>
            </div>
            
            <div class="md:col-span-2">
                <label class="block text-sm font-bold text-gray-700 dark:text-gray-300 mb-2" for="pickup_location">Pickup Location</label>
                <input type="text" id="pickup_location" name="pickup_location"
                       class="w-full px-4 py-2 border border-gray-300 dark:border-gray-600 rounded bg-white dark:bg-background-dark text-gray-800 dark:text-white focus:ring-2 focus:ring-primary"
                       placeholder="Search for pickup location">
                <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">Or enter coordinates below to find location</p>
                <div class="mt-2 grid grid-cols-2 gap-2">
                    <div>
                        <label class="block text-xs font-medium text-gray-600 dark:text-gray-400 mb-1" for="pickup_latitude_display">Latitude (Optional)</label>
                        <input type="number" step="any" id="pickup_latitude_display" 
                               class="w-full px-3 py-2 text-sm border border-gray-300 dark:border-gray-600 rounded bg-white dark:bg-background-dark text-gray-800 dark:text-white"
                               placeholder="e.g., 34.0522">
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-gray-600 dark:text-gray-400 mb-1" for="pickup_longitude_display">Longitude (Optional)</label>
                        <input type="number" step="any" id="pickup_longitude_display"
                               class="w-full px-3 py-2 text-sm border border-gray-300 dark:border-gray-600 rounded bg-white dark:bg-background-dark text-gray-800 dark:text-white"
                               placeholder="e.g., -118.2437">
                    </div>
                </div>
                <input type="hidden" id="pickup_latitude" name="pickup_latitude">
                <input type="hidden" id="pickup_longitude" name="pickup_longitude">
                <div id="pickup_map_preview" class="w-full h-64 bg-gray-200 dark:bg-gray-800 mt-4 rounded" style="display: none;"></div>
            </div>
            
            <!-- Dropoff Location -->
            <div class="md:col-span-2 mt-6">
                <h2 class="text-xl font-bold text-gray-800 dark:text-white mb-4">Dropoff Location (Optional)</h2>
            </div>
            
            <div class="md:col-span-2">
                <label class="block text-sm font-bold text-gray-700 dark:text-gray-300 mb-2" for="dropoff_location">Dropoff Location</label>
                <input type="text" id="dropoff_location" name="dropoff_location"
                       class="w-full px-4 py-2 border border-gray-300 dark:border-gray-600 rounded bg-white dark:bg-background-dark text-gray-800 dark:text-white focus:ring-2 focus:ring-primary"
                       placeholder="Search for dropoff location">
                <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">Or enter coordinates below to find location</p>
                <div class="mt-2 grid grid-cols-2 gap-2">
                    <div>
                        <label class="block text-xs font-medium text-gray-600 dark:text-gray-400 mb-1" for="dropoff_latitude_display">Latitude (Optional)</label>
                        <input type="number" step="any" id="dropoff_latitude_display"
                               class="w-full px-3 py-2 text-sm border border-gray-300 dark:border-gray-600 rounded bg-white dark:bg-background-dark text-gray-800 dark:text-white"
                               placeholder="e.g., 34.0522">
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-gray-600 dark:text-gray-400 mb-1" for="dropoff_longitude_display">Longitude (Optional)</label>
                        <input type="number" step="any" id="dropoff_longitude_display"
                               class="w-full px-3 py-2 text-sm border border-gray-300 dark:border-gray-600 rounded bg-white dark:bg-background-dark text-gray-800 dark:text-white"
                               placeholder="e.g., -118.2437">
                    </div>
                </div>
                <input type="hidden" id="dropoff_latitude" name="dropoff_latitude">
                <input type="hidden" id="dropoff_longitude" name="dropoff_longitude">
                <div id="dropoff_map_preview" class="w-full h-64 bg-gray-200 dark:bg-gray-800 mt-4 rounded" style="display: none;"></div>
            </div>
            
            <!-- Route Map Preview -->
            <div class="md:col-span-2 mt-6">
                <h2 class="text-xl font-bold text-gray-800 dark:text-white mb-4">Route Preview</h2>
                <p class="text-sm text-gray-600 dark:text-gray-400 mb-4">Map showing route from pickup to dropoff location</p>
                <div id="route_map_preview" class="w-full h-96 bg-gray-200 dark:bg-gray-800 rounded" style="display: none;"></div>
                <p id="route_map_message" class="text-sm text-gray-500 dark:text-gray-400 mt-2">Enter both pickup and dropoff locations to see the route</p>
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
// Load Google Maps API and initialize autocomplete
(function() {
    // Get API key from settings
    fetch('/api/settings.php?key=google_maps_api_key')
        .then(response => response.json())
        .then(data => {
            if (data.success && data.value) {
                const apiKey = data.value;
                
                // Load Google Maps script
                const script = document.createElement('script');
                script.src = `https://maps.googleapis.com/maps/api/js?key=${apiKey}&libraries=places,geometry`;
                script.async = true;
                script.defer = true;
                script.onload = function() {
                    initializeAutocomplete();
                };
                script.onerror = function() {
                    console.error('Failed to load Google Maps API');
                };
                document.head.appendChild(script);
            } else {
                console.warn('Google Maps API key not configured');
            }
        })
        .catch(error => {
            console.error('Error loading Google Maps API key:', error);
        });
    
    function initializeAutocomplete() {
        // Initialize sender address autocomplete (no map preview)
        const senderInput = document.getElementById('sender_address');
        if (senderInput && typeof google !== 'undefined' && google.maps) {
            const senderAutocomplete = new google.maps.places.Autocomplete(senderInput, {
                types: ['address'],
                fields: ['formatted_address', 'address_components', 'geometry']
            });
            
            senderAutocomplete.addListener('place_changed', function() {
                const place = senderAutocomplete.getPlace();
                if (place.geometry) {
                    const lat = place.geometry.location.lat();
                    const lng = place.geometry.location.lng();
                    document.getElementById('sender_latitude').value = lat;
                    document.getElementById('sender_longitude').value = lng;
                    
                    // Auto-fill address components
                    fillAddressFields(place, 'sender');
                }
            });
        }
        
        // Initialize recipient address autocomplete (no map preview)
        const recipientInput = document.getElementById('recipient_address');
        if (recipientInput && typeof google !== 'undefined' && google.maps) {
            const recipientAutocomplete = new google.maps.places.Autocomplete(recipientInput, {
                types: ['address'],
                fields: ['formatted_address', 'address_components', 'geometry']
            });
            
            recipientAutocomplete.addListener('place_changed', function() {
                const place = recipientAutocomplete.getPlace();
                if (place.geometry) {
                    const lat = place.geometry.location.lat();
                    const lng = place.geometry.location.lng();
                    document.getElementById('recipient_latitude').value = lat;
                    document.getElementById('recipient_longitude').value = lng;
                    
                    // Auto-fill address components
                    fillAddressFields(place, 'recipient');
                }
            });
        }
        
        // Initialize pickup location autocomplete with map preview
        const pickupInput = document.getElementById('pickup_location');
        if (pickupInput && typeof google !== 'undefined' && google.maps) {
            const pickupAutocomplete = new google.maps.places.Autocomplete(pickupInput, {
                types: ['establishment', 'geocode'],
                fields: ['formatted_address', 'name', 'geometry']
            });
            
            let pickupMap = null;
            pickupAutocomplete.addListener('place_changed', function() {
                const place = pickupAutocomplete.getPlace();
                if (place.geometry) {
                    const lat = place.geometry.location.lat();
                    const lng = place.geometry.location.lng();
                    // Update both hidden and visible fields
                    document.getElementById('pickup_latitude').value = lat;
                    document.getElementById('pickup_longitude').value = lng;
                    document.getElementById('pickup_latitude_display').value = lat;
                    document.getElementById('pickup_longitude_display').value = lng;
                    
                    // Show and display map preview
                    displayMapPreview('pickup', lat, lng, place.name || place.formatted_address || 'Pickup Location', pickupMap);
                    pickupMap = window.pickupMapInstance;
                    
                    // Update route map
                    setTimeout(updateRouteMap, 100);
                }
            });
            
            // Reverse geocoding when lat/long are manually entered
            const pickupLatDisplay = document.getElementById('pickup_latitude_display');
            const pickupLngDisplay = document.getElementById('pickup_longitude_display');
            
            function updatePickupFromCoordinates() {
                const lat = parseFloat(pickupLatDisplay.value);
                const lng = parseFloat(pickupLngDisplay.value);
                
                if (!isNaN(lat) && !isNaN(lng) && lat >= -90 && lat <= 90 && lng >= -180 && lng <= 180) {
                    // Update hidden fields
                    document.getElementById('pickup_latitude').value = lat;
                    document.getElementById('pickup_longitude').value = lng;
                    
                    // Reverse geocode to get location name
                    const geocoder = new google.maps.Geocoder();
                    geocoder.geocode({ location: { lat: lat, lng: lng } }, function(results, status) {
                        if (status === 'OK' && results[0]) {
                            document.getElementById('pickup_location').value = results[0].formatted_address;
                        }
                    });
                    
                    // Show map preview
                    displayMapPreview('pickup', lat, lng, 'Pickup Location', pickupMap);
                    pickupMap = window.pickupMapInstance;
                    
                    // Update route map
                    setTimeout(updateRouteMap, 100);
                }
            }
            
            pickupLatDisplay.addEventListener('blur', updatePickupFromCoordinates);
            pickupLngDisplay.addEventListener('blur', updatePickupFromCoordinates);
        }
        
        // Initialize dropoff location autocomplete with map preview
        const dropoffInput = document.getElementById('dropoff_location');
        if (dropoffInput && typeof google !== 'undefined' && google.maps) {
            const dropoffAutocomplete = new google.maps.places.Autocomplete(dropoffInput, {
                types: ['establishment', 'geocode'],
                fields: ['formatted_address', 'name', 'geometry']
            });
            
            let dropoffMap = null;
            dropoffAutocomplete.addListener('place_changed', function() {
                const place = dropoffAutocomplete.getPlace();
                if (place.geometry) {
                    const lat = place.geometry.location.lat();
                    const lng = place.geometry.location.lng();
                    // Update both hidden and visible fields
                    document.getElementById('dropoff_latitude').value = lat;
                    document.getElementById('dropoff_longitude').value = lng;
                    document.getElementById('dropoff_latitude_display').value = lat;
                    document.getElementById('dropoff_longitude_display').value = lng;
                    
                    // Show and display map preview
                    displayMapPreview('dropoff', lat, lng, place.name || place.formatted_address || 'Dropoff Location', dropoffMap);
                    dropoffMap = window.dropoffMapInstance;
                    
                    // Update route map
                    setTimeout(updateRouteMap, 100);
                }
            });
            
            // Reverse geocoding when lat/long are manually entered
            const dropoffLatDisplay = document.getElementById('dropoff_latitude_display');
            const dropoffLngDisplay = document.getElementById('dropoff_longitude_display');
            
            function updateDropoffFromCoordinates() {
                const lat = parseFloat(dropoffLatDisplay.value);
                const lng = parseFloat(dropoffLngDisplay.value);
                
                if (!isNaN(lat) && !isNaN(lng) && lat >= -90 && lat <= 90 && lng >= -180 && lng <= 180) {
                    // Update hidden fields
                    document.getElementById('dropoff_latitude').value = lat;
                    document.getElementById('dropoff_longitude').value = lng;
                    
                    // Reverse geocode to get location name
                    const geocoder = new google.maps.Geocoder();
                    geocoder.geocode({ location: { lat: lat, lng: lng } }, function(results, status) {
                        if (status === 'OK' && results[0]) {
                            document.getElementById('dropoff_location').value = results[0].formatted_address;
                        }
                    });
                    
                    // Show map preview
                    displayMapPreview('dropoff', lat, lng, 'Dropoff Location', dropoffMap);
                    dropoffMap = window.dropoffMapInstance;
                    
                    // Update route map
                    setTimeout(updateRouteMap, 100);
                }
            }
            
            dropoffLatDisplay.addEventListener('blur', updateDropoffFromCoordinates);
            dropoffLngDisplay.addEventListener('blur', updateDropoffFromCoordinates);
        }
        
        // Initialize route map when both locations are available (after a delay to ensure Google Maps is loaded)
        if (typeof google !== 'undefined' && google.maps) {
            setTimeout(updateRouteMap, 500);
        } else {
            // Wait for Google Maps to load
            window.addEventListener('load', function() {
                if (typeof google !== 'undefined' && google.maps) {
                    setTimeout(updateRouteMap, 500);
                }
            });
        }
    }
    
    // Function to update route map when both pickup and dropoff are set
    function updateRouteMap() {
        const pickupLat = parseFloat(document.getElementById('pickup_latitude').value || document.getElementById('pickup_latitude_display').value);
        const pickupLng = parseFloat(document.getElementById('pickup_longitude').value || document.getElementById('pickup_longitude_display').value);
        const dropoffLat = parseFloat(document.getElementById('dropoff_latitude').value || document.getElementById('dropoff_latitude_display').value);
        const dropoffLng = parseFloat(document.getElementById('dropoff_longitude').value || document.getElementById('dropoff_longitude_display').value);
        
        const routeMapDiv = document.getElementById('route_map_preview');
        const routeMapMessage = document.getElementById('route_map_message');
        
        if (!isNaN(pickupLat) && !isNaN(pickupLng) && !isNaN(dropoffLat) && !isNaN(dropoffLng) && typeof google !== 'undefined' && google.maps) {
            routeMapDiv.style.display = 'block';
            routeMapMessage.style.display = 'none';
            
            if (!window.routeMapInstance) {
                window.routeMapInstance = new google.maps.Map(routeMapDiv, {
                    zoom: 6,
                    center: { lat: (pickupLat + dropoffLat) / 2, lng: (pickupLng + dropoffLng) / 2 },
                    mapTypeId: 'roadmap'
                });
            }
            
            const directionsService = new google.maps.DirectionsService();
            const directionsRenderer = new google.maps.DirectionsRenderer({
                map: window.routeMapInstance,
                suppressMarkers: false,
                polylineOptions: {
                    strokeColor: '#4D148C',
                    strokeWeight: 5,
                    strokeOpacity: 0.8
                }
            });
            
            directionsService.route({
                origin: { lat: pickupLat, lng: pickupLng },
                destination: { lat: dropoffLat, lng: dropoffLng },
                travelMode: google.maps.TravelMode.DRIVING
            }, function(response, status) {
                if (status === 'OK') {
                    directionsRenderer.setDirections(response);

                    // Clear any fallback line/markers from previous failures
                    if (window.routeFallbackPolyline) {
                        window.routeFallbackPolyline.setMap(null);
                        window.routeFallbackPolyline = null;
                    }
                    if (window.routeFallbackMarkers && Array.isArray(window.routeFallbackMarkers)) {
                        window.routeFallbackMarkers.forEach(m => m.setMap(null));
                        window.routeFallbackMarkers = [];
                    }
                    if (routeMapMessage) {
                        routeMapMessage.style.display = 'none';
                        routeMapMessage.classList.remove('text-red-500');
                        routeMapMessage.textContent = '';
                    }

                    const bounds = new google.maps.LatLngBounds();
                    bounds.extend({ lat: pickupLat, lng: pickupLng });
                    bounds.extend({ lat: dropoffLat, lng: dropoffLng });
                    window.routeMapInstance.fitBounds(bounds);
                } else {
                    console.error('Directions request failed: ' + status);

                    // Fallback: draw a straight line between pickup and dropoff so the admin still sees a route preview.
                    try {
                        directionsRenderer.setDirections({ routes: [] });
                    } catch (e) {}

                    // Clear previous fallback
                    if (window.routeFallbackPolyline) {
                        window.routeFallbackPolyline.setMap(null);
                    }
                    if (!window.routeFallbackMarkers) window.routeFallbackMarkers = [];
                    window.routeFallbackMarkers.forEach(m => m.setMap(null));
                    window.routeFallbackMarkers = [];

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

                    window.routeFallbackMarkers.push(new google.maps.Marker({
                        position: originLatLng,
                        map: window.routeMapInstance,
                        title: 'Pickup'
                    }));
                    window.routeFallbackMarkers.push(new google.maps.Marker({
                        position: destLatLng,
                        map: window.routeMapInstance,
                        title: 'Dropoff'
                    }));

                    const bounds = new google.maps.LatLngBounds();
                    bounds.extend(originLatLng);
                    bounds.extend(destLatLng);
                    window.routeMapInstance.fitBounds(bounds);

                    // Show message but don't block preview
                    if (routeMapMessage) {
                        routeMapMessage.textContent = `Directions unavailable (${status}). Showing a straight-line preview.`;
                        routeMapMessage.style.display = 'block';
                        routeMapMessage.classList.add('text-red-500');
                    }
                }
            });
        } else {
            routeMapDiv.style.display = 'none';
            routeMapMessage.style.display = 'block';
        }
    }
    
    // Helper function to display map preview
    function displayMapPreview(prefix, lat, lng, title, existingMap) {
        const mapDiv = document.getElementById(prefix + '_map_preview');
        if (mapDiv && typeof google !== 'undefined' && google.maps) {
            mapDiv.style.display = 'block';
            let map = existingMap;
            
            if (!map) {
                map = new google.maps.Map(mapDiv, {
                    zoom: 15,
                    center: { lat: lat, lng: lng },
                    disableDefaultUI: true,
                    zoomControl: true
                });
                // Store map instance globally for reuse
                window[prefix + 'MapInstance'] = map;
            } else {
                map.setCenter({ lat: lat, lng: lng });
            }
            
            // Clear existing markers
            map.markers = map.markers || [];
            map.markers.forEach(marker => marker.setMap(null));
            map.markers = [];
            
            // Add new marker
            const marker = new google.maps.Marker({
                position: { lat: lat, lng: lng },
                map: map,
                title: title
            });
            map.markers.push(marker);
        }
    }
    
    function fillAddressFields(place, prefix) {
        const addressComponents = place.address_components || [];
        
        addressComponents.forEach(component => {
            const types = component.types;
            
            if (types.includes('street_number') || types.includes('route')) {
                // Address is already filled by autocomplete
            } else if (types.includes('locality')) {
                document.getElementById(prefix + '_city').value = component.long_name;
            } else if (types.includes('administrative_area_level_1')) {
                document.getElementById(prefix + '_state').value = component.short_name;
            } else if (types.includes('postal_code')) {
                document.getElementById(prefix + '_zip').value = component.long_name;
            } else if (types.includes('country')) {
                document.getElementById(prefix + '_country').value = component.long_name;
            }
        });
    }
})();

// Cost calculation function (global scope)
function calculateTotalCost() {
    const shippingCost = parseFloat(document.getElementById('base_cost').value) || 0;
    const clearanceCost = parseFloat(document.getElementById('clearance_cost').value) || 0;
    const totalCost = shippingCost + clearanceCost;
    
    const totalCostField = document.getElementById('total_cost');
    if (totalCostField) {
        totalCostField.value = '$' + totalCost.toFixed(2);
    }
}

// Add event listeners for cost calculation
document.addEventListener('DOMContentLoaded', function() {
    const baseCostInput = document.getElementById('base_cost');
    const clearanceCostInput = document.getElementById('clearance_cost');
    
    if (baseCostInput) {
        baseCostInput.addEventListener('input', calculateTotalCost);
        baseCostInput.addEventListener('blur', calculateTotalCost);
    }

    if (clearanceCostInput) {
        clearanceCostInput.addEventListener('input', calculateTotalCost);
        clearanceCostInput.addEventListener('blur', calculateTotalCost);
    }
});

// Handle form submission with loading state
document.addEventListener('DOMContentLoaded', function() {
    const form = document.getElementById('create-shipment-form');
    const submitButton = form.querySelector('button[type="submit"]');
    
    if (form) {
        form.addEventListener('submit', function(e) {
            // Sync visible lat/long fields to hidden fields before submission
            const pickupLatDisplay = document.getElementById('pickup_latitude_display');
            const pickupLngDisplay = document.getElementById('pickup_longitude_display');
            if (pickupLatDisplay && pickupLngDisplay) {
                const lat = pickupLatDisplay.value;
                const lng = pickupLngDisplay.value;
                if (lat && lng) {
                    document.getElementById('pickup_latitude').value = lat;
                    document.getElementById('pickup_longitude').value = lng;
                }
            }
            
            const dropoffLatDisplay = document.getElementById('dropoff_latitude_display');
            const dropoffLngDisplay = document.getElementById('dropoff_longitude_display');
            if (dropoffLatDisplay && dropoffLngDisplay) {
                const lat = dropoffLatDisplay.value;
                const lng = dropoffLngDisplay.value;
                if (lat && lng) {
                    document.getElementById('dropoff_latitude').value = lat;
                    document.getElementById('dropoff_longitude').value = lng;
                }
            }
            
            // Calculate and sync total cost (remove $ sign for submission)
            calculateTotalCost();
            const totalCostField = document.getElementById('total_cost');
            if (totalCostField) {
                let totalCostValue = totalCostField.value.replace('$', '').trim();
                // Ensure it's a valid number
                if (isNaN(parseFloat(totalCostValue))) {
                    totalCostValue = '0.00';
                }
                totalCostField.value = totalCostValue;
            }
            
            // Show loading state
            submitButton.disabled = true;
            submitButton.textContent = 'Creating...';
            submitButton.classList.add('opacity-50', 'cursor-not-allowed');
            
            // Form will submit normally via POST
            // Loading state will be cleared on page reload
        });
    }
});
</script>

<?php include __DIR__ . '/includes/admin-footer.php'; ?>

