<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/includes/admin-auth.php';

$shipmentId = isset($_GET['id']) ? intval($_GET['id']) : 0;
$shipment = null;
$events = [];

if ($shipmentId) {
    $stmt = $conn->prepare("SELECT * FROM shipments WHERE id = ?");
    $stmt->bind_param("i", $shipmentId);
    $stmt->execute();
    $result = $stmt->get_result();
    $shipment = $result->fetch_assoc();
    $stmt->close();
    
    if ($shipment) {
        $events = getTrackingEvents($shipmentId);
    }
}

// Default current location for the Add Event form:
// latest tracking event location -> pickup location -> sender city/state fallback
$currentLocationText = '';
$currentLatitude = null;
$currentLongitude = null;
if (!empty($events)) {
    $latest = $events[0];
    if (!empty($latest['location'])) {
        $currentLocationText = $latest['location'];
        $currentLatitude = !empty($latest['latitude']) ? floatval($latest['latitude']) : null;
        $currentLongitude = !empty($latest['longitude']) ? floatval($latest['longitude']) : null;
    }
}
if (empty($currentLocationText) && !empty($shipment)) {
    if (!empty($shipment['pickup_location'])) {
        $currentLocationText = $shipment['pickup_location'];
        $currentLatitude = !empty($shipment['pickup_latitude']) ? floatval($shipment['pickup_latitude']) : null;
        $currentLongitude = !empty($shipment['pickup_longitude']) ? floatval($shipment['pickup_longitude']) : null;
    } else {
        $fallback = trim(($shipment['sender_city'] ?? '') . (!empty($shipment['sender_city']) && !empty($shipment['sender_state']) ? ', ' : '') . ($shipment['sender_state'] ?? ''));
        $currentLocationText = $fallback ?: 'Origin';
    }
}

// Latest remark (Admin Note) for remark editor
$latestRemarkText = '';
if (!empty($events)) {
    foreach ($events as $ev) {
        if (($ev['event_type'] ?? '') === 'Admin Note' && !empty($ev['description'])) {
            $latestRemarkText = $ev['description'];
            break; // events are DESC, first match is latest
        }
    }
}

// Handle updates (single Save action)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $shipmentId) {
    if ($_POST['action'] === 'save') {
        // --- Shipment details ---
        $senderName = sanitizeInput($_POST['sender_name'] ?? '');
        $senderAddress = sanitizeInput($_POST['sender_address'] ?? '');
        $senderCity = sanitizeInput($_POST['sender_city'] ?? '');
        $senderState = sanitizeInput($_POST['sender_state'] ?? '');
        $senderZip = sanitizeInput($_POST['sender_zip'] ?? '');
        $senderCountry = sanitizeInput($_POST['sender_country'] ?? 'United States');
        $senderEmail = sanitizeInput($_POST['sender_email'] ?? '');
        $senderPhone = sanitizeInput($_POST['sender_phone'] ?? '');

        $recipientName = sanitizeInput($_POST['recipient_name'] ?? '');
        $recipientAddress = sanitizeInput($_POST['recipient_address'] ?? '');
        $recipientCity = sanitizeInput($_POST['recipient_city'] ?? '');
        $recipientState = sanitizeInput($_POST['recipient_state'] ?? '');
        $recipientZip = sanitizeInput($_POST['recipient_zip'] ?? '');
        $recipientCountry = sanitizeInput($_POST['recipient_country'] ?? 'United States');
        $recipientEmail = sanitizeInput($_POST['recipient_email'] ?? '');
        $recipientPhone = sanitizeInput($_POST['recipient_phone'] ?? '');

        $pickupLocation = sanitizeInput($_POST['pickup_location'] ?? '');
        $pickupLatitude = $_POST['pickup_latitude'] !== '' ? floatval($_POST['pickup_latitude']) : null;
        $pickupLongitude = $_POST['pickup_longitude'] !== '' ? floatval($_POST['pickup_longitude']) : null;
        $dropoffLocation = sanitizeInput($_POST['dropoff_location'] ?? '');
        $dropoffLatitude = $_POST['dropoff_latitude'] !== '' ? floatval($_POST['dropoff_latitude']) : null;
        $dropoffLongitude = $_POST['dropoff_longitude'] !== '' ? floatval($_POST['dropoff_longitude']) : null;

        $weight = $_POST['weight'] !== '' ? floatval($_POST['weight']) : null;
        $dimensions = sanitizeInput($_POST['dimensions'] ?? '');
        $serviceType = sanitizeInput($_POST['service_type'] ?? '');
        $shipmentStatus = sanitizeInput($_POST['shipment_status'] ?? '');
        $estimatedDelivery = sanitizeInput($_POST['estimated_delivery'] ?? '');
        if ($estimatedDelivery === '') {
            $estimatedDelivery = null;
        }
        $shipmentCreatedAt = parseDateTimeInput($_POST['shipment_created_at'] ?? '');
        if ($shipmentCreatedAt === null && !empty($shipment['shipment_created_at'])) {
            $shipmentCreatedAt = $shipment['shipment_created_at'];
        } elseif ($shipmentCreatedAt === null) {
            $shipmentCreatedAt = getShipmentCreatedAt($shipment);
        }
        $referenceNumber = sanitizeInput($_POST['reference_number'] ?? '');

        $shipmentWorth = $_POST['shipment_worth'] !== '' ? floatval($_POST['shipment_worth']) : null;
        $baseCost = $_POST['base_cost'] !== '' ? floatval($_POST['base_cost']) : null;
        $clearanceCost = $_POST['clearance_cost'] !== '' ? floatval($_POST['clearance_cost']) : null;
        $totalCost = null;
        if ($baseCost !== null || $clearanceCost !== null) {
            $totalCost = ($baseCost ?? 0) + ($clearanceCost ?? 0);
        }

        if ($senderName === '' || $senderAddress === '' || $recipientName === '' || $recipientAddress === '' || $serviceType === '' || $shipmentStatus === '') {
            header('Location: /admin/manage-shipments.php?id=' . $shipmentId . '&error=' . urlencode('Please fill in required sender, recipient, service, and status fields.'));
            exit;
        }

        $updShipment = $conn->prepare("UPDATE shipments SET
            sender_name = ?, sender_address = ?, sender_city = ?, sender_state = ?, sender_zip = ?, sender_country = ?, sender_email = ?, sender_phone = ?,
            recipient_name = ?, recipient_address = ?, recipient_city = ?, recipient_state = ?, recipient_zip = ?, recipient_country = ?, recipient_email = ?, recipient_phone = ?,
            pickup_location = ?, pickup_latitude = ?, pickup_longitude = ?,
            dropoff_location = ?, dropoff_latitude = ?, dropoff_longitude = ?,
            weight = ?, dimensions = ?, service_type = ?, status = ?, estimated_delivery = ?, shipment_created_at = ?, reference_number = ?,
            shipment_worth = ?, base_cost = ?, clearance_cost = ?, total_cost = ?
            WHERE id = ?");

        if ($updShipment) {
            $updShipment->bind_param(
                'ssssssssssssssssddsddssssssddddi',
                $senderName, $senderAddress, $senderCity, $senderState, $senderZip, $senderCountry, $senderEmail, $senderPhone,
                $recipientName, $recipientAddress, $recipientCity, $recipientState, $recipientZip, $recipientCountry, $recipientEmail, $recipientPhone,
                $pickupLocation, $pickupLatitude, $pickupLongitude,
                $dropoffLocation, $dropoffLatitude, $dropoffLongitude,
                $weight, $dimensions, $serviceType, $shipmentStatus, $estimatedDelivery, $shipmentCreatedAt, $referenceNumber,
                $shipmentWorth, $baseCost, $clearanceCost, $totalCost,
                $shipmentId
            );
            $updShipment->execute();
            $updShipment->close();
        }

        if ($shipmentCreatedAt) {
            $labelStmt = $conn->prepare("SELECT id FROM tracking_events WHERE shipment_id = ? AND event_type = 'Label Created' ORDER BY event_date ASC, id ASC LIMIT 1");
            $labelStmt->bind_param("i", $shipmentId);
            $labelStmt->execute();
            $labelRes = $labelStmt->get_result();
            $labelRow = $labelRes ? $labelRes->fetch_assoc() : null;
            $labelStmt->close();

            if ($labelRow && !empty($labelRow['id'])) {
                $labelId = (int) $labelRow['id'];
                $syncStmt = $conn->prepare("UPDATE tracking_events SET event_date = ? WHERE id = ?");
                $syncStmt->bind_param("si", $shipmentCreatedAt, $labelId);
                $syncStmt->execute();
                $syncStmt->close();
            }
        }

        // --- Remark (shipment-level, shown under Travel History) ---
        $remark = sanitizeInput($_POST['remark'] ?? '');

        // Editable remark behavior:
        // - If a remark already exists (Admin Note), update the latest one in-place.
        // - If no remark exists and remark is non-empty, insert a new Admin Note.
        // - If remark is empty, clear all existing Admin Note rows for this shipment.
        if ($remark === '') {
            $delStmt = $conn->prepare("DELETE FROM tracking_events WHERE shipment_id = ? AND event_type = 'Admin Note'");
            $delStmt->bind_param("i", $shipmentId);
            $delStmt->execute();
            $delStmt->close();
        } else {
            $findStmt = $conn->prepare("SELECT id FROM tracking_events WHERE shipment_id = ? AND event_type = 'Admin Note' ORDER BY event_date DESC, id DESC LIMIT 1");
            $findStmt->bind_param("i", $shipmentId);
            $findStmt->execute();
            $findRes = $findStmt->get_result();
            $row = $findRes ? $findRes->fetch_assoc() : null;
            $findStmt->close();

            if ($row && !empty($row['id'])) {
                $remarkId = (int) $row['id'];
                $updStmt = $conn->prepare("UPDATE tracking_events SET description = ?, event_date = NOW() WHERE id = ?");
                $updStmt->bind_param("si", $remark, $remarkId);
                $updStmt->execute();
                $updStmt->close();
            } else {
                $insType = 'Admin Note';
                $insStmt = $conn->prepare("INSERT INTO tracking_events (shipment_id, event_type, description, location, event_date) VALUES (?, ?, ?, NULL, NOW())");
                $insStmt->bind_param("iss", $shipmentId, $insType, $remark);
                $insStmt->execute();
                $insStmt->close();
            }
        }

        // --- Optional: Add tracking event OR update current location on latest event ---
        $eventType = sanitizeInput($_POST['event_type'] ?? '');
        $location = sanitizeInput($_POST['location'] ?? '');
        $latitude = $_POST['latitude'] !== '' ? floatval($_POST['latitude']) : null;
        $longitude = $_POST['longitude'] !== '' ? floatval($_POST['longitude']) : null;
        $description = sanitizeInput($_POST['description'] ?? '');

        if ($eventType === '' && $location !== '' && $latitude !== null && $longitude !== null) {
            $latestStmt = $conn->prepare("SELECT id FROM tracking_events WHERE shipment_id = ? AND event_type != 'Admin Note' ORDER BY event_date DESC, id DESC LIMIT 1");
            $latestStmt->bind_param("i", $shipmentId);
            $latestStmt->execute();
            $latestRes = $latestStmt->get_result();
            $latestRow = $latestRes ? $latestRes->fetch_assoc() : null;
            $latestStmt->close();

            if ($latestRow && !empty($latestRow['id'])) {
                $latestId = (int) $latestRow['id'];
                $locStmt = $conn->prepare("UPDATE tracking_events SET location = ?, latitude = ?, longitude = ? WHERE id = ?");
                $locStmt->bind_param("sddi", $location, $latitude, $longitude, $latestId);
                $locStmt->execute();
                $locStmt->close();
            }
        }

        if ($eventType !== '') {
            // Auto-generate a reasonable description if admin leaves it empty.
            if ($description === '') {
                $description = $eventType . ($location !== '' ? ' — ' . $location : '');
            }

            // Always update shipment status to match selected shipment status
            $updateStatus = true;
            $newStatus = $eventType;

            // Release session lock before internal HTTP request
            if (session_status() === PHP_SESSION_ACTIVE) {
                session_write_close();
            }

            $ch = curl_init();
            $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
            curl_setopt($ch, CURLOPT_URL, $protocol . '://' . $_SERVER['HTTP_HOST'] . '/api/update-status.php');
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode([
                'shipment_id' => $shipmentId,
                'event_type' => $eventType,
                'description' => $description,
                'location' => $location,
                'latitude' => $latitude,
                'longitude' => $longitude,
                'update_status' => $updateStatus,
                'new_status' => $newStatus
            ]));
            curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_COOKIE, session_name() . '=' . session_id());

            $response = curl_exec($ch);
            curl_close($ch);
        }

        header('Location: /admin/manage-shipments.php?id=' . $shipmentId . '&saved=1');
        exit;
    }
}

// Get all shipments (safe - no user input)
$result = $conn->query("SELECT * FROM shipments ORDER BY created_at DESC");
$allShipments = [];
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $allShipments[] = $row;
    }
    $result->free();
}

// Render admin layout AFTER handling POST/redirects
include __DIR__ . '/includes/admin-header.php';
?>
<div class="mb-8">
    <h1 class="text-3xl font-light text-gray-800 dark:text-white mb-2">Manage Shipments</h1>
    <p class="text-gray-600 dark:text-gray-400">View and edit shipment details, costs, and tracking events</p>
</div>

<?php if (isset($_GET['saved']) && $_GET['saved'] == '1'): ?>
    <div class="bg-green-100 dark:bg-green-900 text-green-700 dark:text-green-200 px-4 py-3 rounded mb-6">
        Shipment updated successfully.
    </div>
<?php endif; ?>
<?php if (!empty($_GET['error'])): ?>
    <div class="bg-red-100 dark:bg-red-900 text-red-700 dark:text-red-200 px-4 py-3 rounded mb-6">
        <?php echo htmlspecialchars($_GET['error']); ?>
    </div>
<?php endif; ?>

<?php if (isset($_GET['deleted']) && $_GET['deleted'] == '1'): ?>
    <div class="bg-green-100 dark:bg-green-900 text-green-700 dark:text-green-200 px-4 py-3 rounded mb-6">
        Shipment deleted successfully.
    </div>
<?php endif; ?>
<?php if (!empty($_GET['delete_error'])): ?>
    <div class="bg-red-100 dark:bg-red-900 text-red-700 dark:text-red-200 px-4 py-3 rounded mb-6">
        <?php echo htmlspecialchars($_GET['delete_error']); ?>
    </div>
<?php endif; ?>

<?php if ($shipment): ?>
    <!-- Edit Shipment Form -->
    <div class="bg-white dark:bg-surface-dark rounded-lg shadow p-6 mb-8">
        <h2 class="text-xl font-bold text-gray-800 dark:text-white mb-4">Shipment: <?php echo htmlspecialchars($shipment['tracking_number']); ?></h2>
        
        <div class="mb-6">
            <p class="text-sm text-gray-600 dark:text-gray-400 mb-2"><strong>Tracking Number:</strong> <?php echo htmlspecialchars($shipment['tracking_number']); ?></p>
        </div>

        <div class="mb-6">
            <form method="POST" action="/admin/delete-shipment.php" onsubmit="return confirm('Delete this shipment permanently? This will erase it from the database and delete all tracking events.');">
                <input type="hidden" name="id" value="<?php echo (int) $shipment['id']; ?>">
                <input type="hidden" name="return_to" value="/admin/manage-shipments.php">
                <button type="submit" class="bg-red-600 hover:bg-red-700 text-white font-bold py-2 px-6 rounded uppercase tracking-wide transition-colors">
                    Delete Shipment
                </button>
            </form>
        </div>

        <!-- Save (details + remark + optional tracking event) -->
        <form method="POST" action="" class="border-t border-gray-200 dark:border-gray-700 pt-6" id="shipment-save-form">
            <input type="hidden" name="action" value="save">

            <h3 class="text-lg font-bold text-gray-800 dark:text-white mb-4">Sender Information</h3>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-6">
                <div>
                    <label class="block text-sm font-bold text-gray-700 dark:text-gray-300 mb-2" for="sender_name">Name *</label>
                    <input type="text" id="sender_name" name="sender_name" required value="<?php echo htmlspecialchars($shipment['sender_name']); ?>"
                           class="w-full px-4 py-2 border border-gray-300 dark:border-gray-600 rounded bg-white dark:bg-background-dark text-gray-800 dark:text-white focus:ring-2 focus:ring-primary">
                </div>
                <div>
                    <label class="block text-sm font-bold text-gray-700 dark:text-gray-300 mb-2" for="sender_address">Address *</label>
                    <input type="text" id="sender_address" name="sender_address" required value="<?php echo htmlspecialchars($shipment['sender_address']); ?>"
                           class="w-full px-4 py-2 border border-gray-300 dark:border-gray-600 rounded bg-white dark:bg-background-dark text-gray-800 dark:text-white focus:ring-2 focus:ring-primary">
                </div>
                <div>
                    <label class="block text-sm font-bold text-gray-700 dark:text-gray-300 mb-2" for="sender_city">City</label>
                    <input type="text" id="sender_city" name="sender_city" value="<?php echo htmlspecialchars($shipment['sender_city'] ?? ''); ?>"
                           class="w-full px-4 py-2 border border-gray-300 dark:border-gray-600 rounded bg-white dark:bg-background-dark text-gray-800 dark:text-white focus:ring-2 focus:ring-primary">
                </div>
                <div>
                    <label class="block text-sm font-bold text-gray-700 dark:text-gray-300 mb-2" for="sender_state">State</label>
                    <input type="text" id="sender_state" name="sender_state" value="<?php echo htmlspecialchars($shipment['sender_state'] ?? ''); ?>"
                           class="w-full px-4 py-2 border border-gray-300 dark:border-gray-600 rounded bg-white dark:bg-background-dark text-gray-800 dark:text-white focus:ring-2 focus:ring-primary">
                </div>
                <div>
                    <label class="block text-sm font-bold text-gray-700 dark:text-gray-300 mb-2" for="sender_zip">ZIP</label>
                    <input type="text" id="sender_zip" name="sender_zip" value="<?php echo htmlspecialchars($shipment['sender_zip'] ?? ''); ?>"
                           class="w-full px-4 py-2 border border-gray-300 dark:border-gray-600 rounded bg-white dark:bg-background-dark text-gray-800 dark:text-white focus:ring-2 focus:ring-primary">
                </div>
                <div>
                    <label class="block text-sm font-bold text-gray-700 dark:text-gray-300 mb-2" for="sender_country">Country</label>
                    <input type="text" id="sender_country" name="sender_country" value="<?php echo htmlspecialchars($shipment['sender_country'] ?? 'United States'); ?>"
                           class="w-full px-4 py-2 border border-gray-300 dark:border-gray-600 rounded bg-white dark:bg-background-dark text-gray-800 dark:text-white focus:ring-2 focus:ring-primary">
                </div>
                <div>
                    <label class="block text-sm font-bold text-gray-700 dark:text-gray-300 mb-2" for="sender_email">Email</label>
                    <input type="email" id="sender_email" name="sender_email" value="<?php echo htmlspecialchars($shipment['sender_email'] ?? ''); ?>"
                           class="w-full px-4 py-2 border border-gray-300 dark:border-gray-600 rounded bg-white dark:bg-background-dark text-gray-800 dark:text-white focus:ring-2 focus:ring-primary">
                </div>
                <div>
                    <label class="block text-sm font-bold text-gray-700 dark:text-gray-300 mb-2" for="sender_phone">Phone</label>
                    <input type="text" id="sender_phone" name="sender_phone" value="<?php echo htmlspecialchars($shipment['sender_phone'] ?? ''); ?>"
                           class="w-full px-4 py-2 border border-gray-300 dark:border-gray-600 rounded bg-white dark:bg-background-dark text-gray-800 dark:text-white focus:ring-2 focus:ring-primary">
                </div>
            </div>

            <h3 class="text-lg font-bold text-gray-800 dark:text-white mb-4">Recipient Information</h3>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-6">
                <div>
                    <label class="block text-sm font-bold text-gray-700 dark:text-gray-300 mb-2" for="recipient_name">Name *</label>
                    <input type="text" id="recipient_name" name="recipient_name" required value="<?php echo htmlspecialchars($shipment['recipient_name']); ?>"
                           class="w-full px-4 py-2 border border-gray-300 dark:border-gray-600 rounded bg-white dark:bg-background-dark text-gray-800 dark:text-white focus:ring-2 focus:ring-primary">
                </div>
                <div>
                    <label class="block text-sm font-bold text-gray-700 dark:text-gray-300 mb-2" for="recipient_address">Address *</label>
                    <input type="text" id="recipient_address" name="recipient_address" required value="<?php echo htmlspecialchars($shipment['recipient_address']); ?>"
                           class="w-full px-4 py-2 border border-gray-300 dark:border-gray-600 rounded bg-white dark:bg-background-dark text-gray-800 dark:text-white focus:ring-2 focus:ring-primary">
                </div>
                <div>
                    <label class="block text-sm font-bold text-gray-700 dark:text-gray-300 mb-2" for="recipient_city">City</label>
                    <input type="text" id="recipient_city" name="recipient_city" value="<?php echo htmlspecialchars($shipment['recipient_city'] ?? ''); ?>"
                           class="w-full px-4 py-2 border border-gray-300 dark:border-gray-600 rounded bg-white dark:bg-background-dark text-gray-800 dark:text-white focus:ring-2 focus:ring-primary">
                </div>
                <div>
                    <label class="block text-sm font-bold text-gray-700 dark:text-gray-300 mb-2" for="recipient_state">State</label>
                    <input type="text" id="recipient_state" name="recipient_state" value="<?php echo htmlspecialchars($shipment['recipient_state'] ?? ''); ?>"
                           class="w-full px-4 py-2 border border-gray-300 dark:border-gray-600 rounded bg-white dark:bg-background-dark text-gray-800 dark:text-white focus:ring-2 focus:ring-primary">
                </div>
                <div>
                    <label class="block text-sm font-bold text-gray-700 dark:text-gray-300 mb-2" for="recipient_zip">ZIP</label>
                    <input type="text" id="recipient_zip" name="recipient_zip" value="<?php echo htmlspecialchars($shipment['recipient_zip'] ?? ''); ?>"
                           class="w-full px-4 py-2 border border-gray-300 dark:border-gray-600 rounded bg-white dark:bg-background-dark text-gray-800 dark:text-white focus:ring-2 focus:ring-primary">
                </div>
                <div>
                    <label class="block text-sm font-bold text-gray-700 dark:text-gray-300 mb-2" for="recipient_country">Country</label>
                    <input type="text" id="recipient_country" name="recipient_country" value="<?php echo htmlspecialchars($shipment['recipient_country'] ?? 'United States'); ?>"
                           class="w-full px-4 py-2 border border-gray-300 dark:border-gray-600 rounded bg-white dark:bg-background-dark text-gray-800 dark:text-white focus:ring-2 focus:ring-primary">
                </div>
                <div>
                    <label class="block text-sm font-bold text-gray-700 dark:text-gray-300 mb-2" for="recipient_email">Email</label>
                    <input type="email" id="recipient_email" name="recipient_email" value="<?php echo htmlspecialchars($shipment['recipient_email'] ?? ''); ?>"
                           class="w-full px-4 py-2 border border-gray-300 dark:border-gray-600 rounded bg-white dark:bg-background-dark text-gray-800 dark:text-white focus:ring-2 focus:ring-primary">
                </div>
                <div>
                    <label class="block text-sm font-bold text-gray-700 dark:text-gray-300 mb-2" for="recipient_phone">Phone</label>
                    <input type="text" id="recipient_phone" name="recipient_phone" value="<?php echo htmlspecialchars($shipment['recipient_phone'] ?? ''); ?>"
                           class="w-full px-4 py-2 border border-gray-300 dark:border-gray-600 rounded bg-white dark:bg-background-dark text-gray-800 dark:text-white focus:ring-2 focus:ring-primary">
                </div>
            </div>

            <h3 class="text-lg font-bold text-gray-800 dark:text-white mb-4">Shipment Details</h3>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-6">
                <div>
                    <label class="block text-sm font-bold text-gray-700 dark:text-gray-300 mb-2" for="service_type">Service *</label>
                    <input type="text" id="service_type" name="service_type" required value="<?php echo htmlspecialchars($shipment['service_type']); ?>"
                           class="w-full px-4 py-2 border border-gray-300 dark:border-gray-600 rounded bg-white dark:bg-background-dark text-gray-800 dark:text-white focus:ring-2 focus:ring-primary">
                </div>
                <div>
                    <label class="block text-sm font-bold text-gray-700 dark:text-gray-300 mb-2" for="shipment_status">Status *</label>
                    <select id="shipment_status" name="shipment_status" required
                            class="w-full px-4 py-2 border border-gray-300 dark:border-gray-600 rounded bg-white dark:bg-background-dark text-gray-800 dark:text-white focus:ring-2 focus:ring-primary">
                        <?php
                        $statusOptions = ['Label Created', 'Pending', 'Picked Up', 'In Transit', 'On Hold', 'Out for Delivery', 'Delivered', 'Cancelled', 'Returned', 'Exception'];
                        foreach ($statusOptions as $opt):
                        ?>
                        <option value="<?php echo htmlspecialchars($opt); ?>"<?php echo ($shipment['status'] === $opt) ? ' selected' : ''; ?>><?php echo htmlspecialchars($opt); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-bold text-gray-700 dark:text-gray-300 mb-2" for="weight">Weight (lbs)</label>
                    <input type="number" step="0.01" min="0" id="weight" name="weight" value="<?php echo htmlspecialchars($shipment['weight'] ?? ''); ?>"
                           class="w-full px-4 py-2 border border-gray-300 dark:border-gray-600 rounded bg-white dark:bg-background-dark text-gray-800 dark:text-white focus:ring-2 focus:ring-primary">
                </div>
                <div>
                    <label class="block text-sm font-bold text-gray-700 dark:text-gray-300 mb-2" for="dimensions">Dimensions</label>
                    <input type="text" id="dimensions" name="dimensions" value="<?php echo htmlspecialchars($shipment['dimensions'] ?? ''); ?>"
                           class="w-full px-4 py-2 border border-gray-300 dark:border-gray-600 rounded bg-white dark:bg-background-dark text-gray-800 dark:text-white focus:ring-2 focus:ring-primary">
                </div>
                <div>
                    <label class="block text-sm font-bold text-gray-700 dark:text-gray-300 mb-2" for="estimated_delivery">Estimated Delivery</label>
                    <input type="date" id="estimated_delivery" name="estimated_delivery" value="<?php echo htmlspecialchars($shipment['estimated_delivery'] ?? ''); ?>"
                           class="w-full px-4 py-2 border border-gray-300 dark:border-gray-600 rounded bg-white dark:bg-background-dark text-gray-800 dark:text-white focus:ring-2 focus:ring-primary">
                </div>
                <div>
                    <label class="block text-sm font-bold text-gray-700 dark:text-gray-300 mb-2" for="shipment_created_at">Creation Date &amp; Time</label>
                    <input type="datetime-local" id="shipment_created_at" name="shipment_created_at" value="<?php echo htmlspecialchars(formatDateTimeLocalValue(getShipmentCreatedAt($shipment))); ?>"
                           class="w-full px-4 py-2 border border-gray-300 dark:border-gray-600 rounded bg-white dark:bg-background-dark text-gray-800 dark:text-white focus:ring-2 focus:ring-primary">
                    <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">Shown on the tracking timeline. Defaults to when the shipment was first created.</p>
                </div>
                <div>
                    <label class="block text-sm font-bold text-gray-700 dark:text-gray-300 mb-2" for="reference_number">Reference Number</label>
                    <input type="text" id="reference_number" name="reference_number" value="<?php echo htmlspecialchars($shipment['reference_number'] ?? ''); ?>"
                           class="w-full px-4 py-2 border border-gray-300 dark:border-gray-600 rounded bg-white dark:bg-background-dark text-gray-800 dark:text-white focus:ring-2 focus:ring-primary">
                </div>
                <div>
                    <label class="block text-sm font-bold text-gray-700 dark:text-gray-300 mb-2" for="pickup_location">Pickup Location</label>
                    <input type="text" id="pickup_location" name="pickup_location" value="<?php echo htmlspecialchars($shipment['pickup_location'] ?? ''); ?>"
                           class="w-full px-4 py-2 border border-gray-300 dark:border-gray-600 rounded bg-white dark:bg-background-dark text-gray-800 dark:text-white focus:ring-2 focus:ring-primary">
                    <input type="hidden" id="pickup_latitude" name="pickup_latitude" value="<?php echo htmlspecialchars($shipment['pickup_latitude'] ?? ''); ?>">
                    <input type="hidden" id="pickup_longitude" name="pickup_longitude" value="<?php echo htmlspecialchars($shipment['pickup_longitude'] ?? ''); ?>">
                </div>
                <div>
                    <label class="block text-sm font-bold text-gray-700 dark:text-gray-300 mb-2" for="dropoff_location">Dropoff Location</label>
                    <input type="text" id="dropoff_location" name="dropoff_location" value="<?php echo htmlspecialchars($shipment['dropoff_location'] ?? ''); ?>"
                           class="w-full px-4 py-2 border border-gray-300 dark:border-gray-600 rounded bg-white dark:bg-background-dark text-gray-800 dark:text-white focus:ring-2 focus:ring-primary">
                    <input type="hidden" id="dropoff_latitude" name="dropoff_latitude" value="<?php echo htmlspecialchars($shipment['dropoff_latitude'] ?? ''); ?>">
                    <input type="hidden" id="dropoff_longitude" name="dropoff_longitude" value="<?php echo htmlspecialchars($shipment['dropoff_longitude'] ?? ''); ?>">
                </div>
            </div>

            <h3 class="text-lg font-bold text-gray-800 dark:text-white mb-4">Costs</h3>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-6">
                <div>
                    <label class="block text-sm font-bold text-gray-700 dark:text-gray-300 mb-2" for="shipment_worth">Shipment Worth ($)</label>
                    <input type="number" step="0.01" min="0" id="shipment_worth" name="shipment_worth" value="<?php echo htmlspecialchars($shipment['shipment_worth'] ?? ''); ?>"
                           class="w-full px-4 py-2 border border-gray-300 dark:border-gray-600 rounded bg-white dark:bg-background-dark text-gray-800 dark:text-white focus:ring-2 focus:ring-primary">
                </div>
                <div>
                    <label class="block text-sm font-bold text-gray-700 dark:text-gray-300 mb-2" for="base_cost">Shipping Cost ($)</label>
                    <input type="number" step="0.01" min="0" id="base_cost" name="base_cost" value="<?php echo htmlspecialchars($shipment['base_cost'] ?? ''); ?>"
                           class="w-full px-4 py-2 border border-gray-300 dark:border-gray-600 rounded bg-white dark:bg-background-dark text-gray-800 dark:text-white focus:ring-2 focus:ring-primary">
                </div>
                <div>
                    <label class="block text-sm font-bold text-gray-700 dark:text-gray-300 mb-2" for="clearance_cost">Clearance Cost ($)</label>
                    <input type="number" step="0.01" min="0" id="clearance_cost" name="clearance_cost" value="<?php echo htmlspecialchars($shipment['clearance_cost'] ?? ''); ?>"
                           class="w-full px-4 py-2 border border-gray-300 dark:border-gray-600 rounded bg-white dark:bg-background-dark text-gray-800 dark:text-white focus:ring-2 focus:ring-primary">
                </div>
                <div>
                    <label class="block text-sm font-bold text-gray-700 dark:text-gray-300 mb-2" for="total_cost">Total Due ($)</label>
                    <input type="text" id="total_cost" name="total_cost" readonly
                           value="<?php $editTotal = getShipmentTotalDue($shipment); echo $editTotal !== null ? '$' . formatMoney($editTotal) : '$0.00'; ?>"
                           class="w-full px-4 py-2 border border-gray-300 dark:border-gray-600 rounded bg-gray-50 dark:bg-gray-800 text-gray-800 dark:text-white font-semibold">
                    <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">Auto-calculated: Shipping + Clearance</p>
                </div>
            </div>

            <div class="border-t border-gray-200 dark:border-gray-700 pt-6">
            <h3 class="text-lg font-bold text-gray-800 dark:text-white mb-4">Remark</h3>
            <label class="block text-sm font-bold text-gray-700 dark:text-gray-300 mb-2" for="remark">Remark (shown on tracking page)</label>
            <textarea id="remark" name="remark" rows="3"
                      class="w-full px-4 py-2 border border-gray-300 dark:border-gray-600 rounded bg-white dark:bg-background-dark text-gray-800 dark:text-white focus:ring-2 focus:ring-primary"
                      placeholder="Type the remark..."><?php echo htmlspecialchars($latestRemarkText); ?></textarea>

            <div class="mt-6 pt-6 border-t border-gray-200 dark:border-gray-700">
                <h3 class="text-lg font-bold text-gray-800 dark:text-white mb-4">Add Tracking Event (Optional)</h3>
            
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                <div>
                    <label class="block text-sm font-bold text-gray-700 dark:text-gray-300 mb-2" for="event_type">Shipment Status</label>
                    <select id="event_type" name="event_type"
                            class="w-full px-4 py-2 border border-gray-300 dark:border-gray-600 rounded bg-white dark:bg-background-dark text-gray-800 dark:text-white focus:ring-2 focus:ring-primary">
                        <option value="">— Select a status to add an event —</option>
                        <option value="Label Created">Label Created</option>
                        <option value="Pending">Pending</option>
                        <option value="Picked Up">Picked Up</option>
                        <option value="In Transit">In Transit</option>
                        <option value="On Hold">On Hold</option>
                        <option value="Out for Delivery">Out for Delivery</option>
                        <option value="Delivered">Delivered</option>
                        <option value="Cancelled">Cancelled</option>
                        <option value="Returned">Returned</option>
                        <option value="Exception">Exception</option>
                    </select>
                    <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">If you select a status, we will add a tracking event and update the shipment status.</p>
                </div>
                
                <div>
                    <label class="block text-sm font-bold text-gray-700 dark:text-gray-300 mb-2" for="description">Event Description (Optional)</label>
                    <input type="text" id="description" name="description"
                           placeholder="Leave blank to auto-fill (e.g. 'In Transit — Lagos, Nigeria')"
                           class="w-full px-4 py-2 border border-gray-300 dark:border-gray-600 rounded bg-white dark:bg-background-dark text-gray-800 dark:text-white focus:ring-2 focus:ring-primary">
                </div>
                
                <div>
                    <label class="block text-sm font-bold text-gray-700 dark:text-gray-300 mb-2" for="location">Current Location</label>
                    <input type="text" id="location" name="location"
                           value="<?php echo htmlspecialchars($currentLocationText); ?>"
                           class="w-full px-4 py-2 border border-gray-300 dark:border-gray-600 rounded bg-white dark:bg-background-dark text-gray-800 dark:text-white focus:ring-2 focus:ring-primary">
                    <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">Start typing and select a suggested location (Google Places). Saving updates the map even if you do not add a new status event.</p>
                </div>
                
                <div>
                    <label class="block text-sm font-bold text-gray-700 dark:text-gray-300 mb-2">Coordinates</label>
                    <div class="text-xs text-gray-500 dark:text-gray-400">Saved automatically from the selected place.</div>
                    <input type="hidden" id="latitude" name="latitude" value="<?php echo $currentLatitude !== null ? htmlspecialchars((string)$currentLatitude) : ''; ?>">
                    <input type="hidden" id="longitude" name="longitude" value="<?php echo $currentLongitude !== null ? htmlspecialchars((string)$currentLongitude) : ''; ?>">
                </div>
                
                <div class="md:col-span-2">
                    <p class="text-xs text-gray-500 dark:text-gray-400 -mt-2">Tip: If you only want to update the remark, leave “Shipment Status” empty and click Save.</p>
                </div>
            </div>
            </div>
            </div>

            <button type="submit" class="mt-4 bg-primary hover:bg-primary-dark text-white font-bold py-2 px-6 rounded uppercase tracking-wide transition-colors">
                Save Changes
            </button>
        </form>
        
        <!-- Tracking Events -->
        <div class="mt-8 border-t border-gray-200 dark:border-gray-700 pt-6">
            <h3 class="text-lg font-bold text-gray-800 dark:text-white mb-4">Tracking Events</h3>
            <div class="space-y-2">
                <?php foreach ($events as $event): ?>
                    <div class="flex items-center justify-between p-3 bg-gray-50 dark:bg-gray-800 rounded">
                        <div>
                            <p class="font-bold text-sm text-gray-800 dark:text-white"><?php echo htmlspecialchars($event['description']); ?></p>
                            <p class="text-xs text-gray-500"><?php echo formatDateTime($event['event_date']); ?> - <?php echo htmlspecialchars($event['location'] ?? 'N/A'); ?></p>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
<?php endif; ?>

<!-- All Shipments List -->
<div class="bg-white dark:bg-surface-dark rounded-lg shadow overflow-hidden">
    <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700">
        <h2 class="text-xl font-bold text-gray-800 dark:text-white">All Shipments</h2>
    </div>
    <div class="overflow-x-auto">
        <table class="w-full">
            <thead class="bg-gray-50 dark:bg-gray-800">
                <tr>
                    <th class="px-6 py-3 text-left text-xs font-bold text-gray-500 dark:text-gray-400 uppercase tracking-wider">Tracking Number</th>
                    <th class="px-6 py-3 text-left text-xs font-bold text-gray-500 dark:text-gray-400 uppercase tracking-wider">Recipient</th>
                    <th class="px-6 py-3 text-left text-xs font-bold text-gray-500 dark:text-gray-400 uppercase tracking-wider">Status</th>
                    <th class="px-6 py-3 text-left text-xs font-bold text-gray-500 dark:text-gray-400 uppercase tracking-wider">Created</th>
                    <th class="px-6 py-3 text-left text-xs font-bold text-gray-500 dark:text-gray-400 uppercase tracking-wider">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                <?php foreach ($allShipments as $s): ?>
                    <tr class="hover:bg-gray-50 dark:hover:bg-gray-800">
                        <td class="px-6 py-4 whitespace-nowrap">
                            <div class="text-sm font-medium text-primary"><?php echo htmlspecialchars($s['tracking_number']); ?></div>
                        </td>
                        <td class="px-6 py-4">
                            <div class="text-sm text-gray-800 dark:text-gray-200"><?php echo htmlspecialchars($s['recipient_name']); ?></div>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap">
                            <span class="px-2 py-1 text-xs font-bold rounded-full <?php echo getStatusBadgeClass($s['status']); ?>">
                                <?php echo htmlspecialchars($s['status']); ?>
                            </span>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                            <?php echo formatDate($s['created_at']); ?>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm">
                            <div class="flex flex-wrap items-center gap-3">
                                <a href="/admin/manage-shipments.php?id=<?php echo $s['id']; ?>"
                                   class="inline-flex items-center px-3 py-1.5 rounded border border-secondary text-secondary hover:bg-secondary hover:text-white transition-colors font-bold">
                                    Edit
                                </a>
                                <a href="<?php echo htmlspecialchars(trackingResultUrl($s['tracking_number'])); ?>" target="_blank"
                                   class="inline-flex items-center px-3 py-1.5 rounded border border-primary text-primary hover:bg-primary hover:text-white transition-colors font-bold">
                                    View
                                </a>
                                <a href="/admin/view-shipment-pdf.php?id=<?php echo $s['id']; ?>"
                                   class="inline-flex items-center px-3 py-1.5 rounded border border-gray-400 dark:border-gray-600 text-gray-700 dark:text-gray-200 hover:bg-gray-100 dark:hover:bg-gray-700 transition-colors font-bold">
                                    <span class="material-icons-outlined text-sm mr-1">download</span>
                                    PDF
                                </a>
                                <form method="POST" action="/admin/delete-shipment.php"
                                      onsubmit="return confirm('Delete this shipment permanently? This will erase it from the database and delete all tracking events.');">
                                    <input type="hidden" name="id" value="<?php echo (int) $s['id']; ?>">
                                    <input type="hidden" name="return_to" value="/admin/manage-shipments.php">
                                    <button type="submit"
                                            class="inline-flex items-center px-3 py-1.5 rounded border border-red-600 text-red-600 hover:bg-red-600 hover:text-white transition-colors font-bold">
                                        Delete
                                    </button>
                                </form>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<?php if ($shipment): ?>
<script>
function calculateEditTotalCost() {
    const shippingCost = parseFloat(document.getElementById('base_cost')?.value) || 0;
    const clearanceCost = parseFloat(document.getElementById('clearance_cost')?.value) || 0;
    const totalField = document.getElementById('total_cost');
    if (totalField) {
        totalField.value = '$' + (shippingCost + clearanceCost).toFixed(2);
    }
}

document.addEventListener('DOMContentLoaded', function () {
    ['base_cost', 'clearance_cost'].forEach(function (id) {
        const el = document.getElementById(id);
        if (el) {
            el.addEventListener('input', calculateEditTotalCost);
            el.addEventListener('blur', calculateEditTotalCost);
        }
    });

    const form = document.getElementById('shipment-save-form');
    if (form) {
        form.addEventListener('submit', function () {
            calculateEditTotalCost();
            const totalField = document.getElementById('total_cost');
            if (totalField) {
                totalField.value = totalField.value.replace('$', '').trim();
            }
        });
    }
});

// Initialize Google Places for the "Current Location" field (Places-only)
(function () {
  const form = document.getElementById('shipment-save-form');
  const locationInput = document.getElementById('location');
  const latInput = document.getElementById('latitude');
  const lngInput = document.getElementById('longitude');
  const statusSelect = document.getElementById('event_type');
  const descInput = document.getElementById('description');
  if (!form || !locationInput || !latInput || !lngInput) return;

  // Clear coords if admin edits text manually (forces selecting from suggestions)
  locationInput.addEventListener('input', function () {
    latInput.value = '';
    lngInput.value = '';
  });

  function wantsToAddEvent() {
    return !!(statusSelect && statusSelect.value && statusSelect.value.trim());
  }

  function maybeAutofillDescription() {
    if (!descInput || !statusSelect) return;
    const status = (statusSelect.value || '').trim();
    if (!status) return;
    if ((descInput.value || '').trim()) return;
    const loc = (locationInput.value || '').trim();
    descInput.value = status + (loc ? ' — ' + loc : '');
  }

  if (statusSelect) {
    statusSelect.addEventListener('change', maybeAutofillDescription);
  }
  if (locationInput) {
    locationInput.addEventListener('blur', maybeAutofillDescription);
  }

  form.addEventListener('submit', function (e) {
    const hasLocation = locationInput.value.trim() && latInput.value && lngInput.value;

    // Require Places selection when adding a tracking event OR when updating current location coords.
    if (wantsToAddEvent() || hasLocation) {
      if (!locationInput.value.trim()) {
        e.preventDefault();
        alert('Please enter a Current Location (select from suggestions).');
        return;
      }

      if (!latInput.value || !lngInput.value) {
        e.preventDefault();
        alert('Please select a location from the suggestions so latitude/longitude can be saved.');
        return;
      }
    }

    if (wantsToAddEvent()) {
      maybeAutofillDescription();
    }
  });

  fetch('/api/settings.php?key=google_maps_api_key')
    .then(r => r.json())
    .then(data => {
      const apiKey = data?.value;
      if (!apiKey) return;

      const load = () => new Promise((resolve) => {
        if (window.google && google.maps && google.maps.places) return resolve();
        const s = document.createElement('script');
        s.src = `https://maps.googleapis.com/maps/api/js?key=${apiKey}&libraries=places`;
        s.async = true;
        s.defer = true;
        s.onload = () => resolve();
        document.head.appendChild(s);
      });

      load().then(() => {
        if (!(window.google && google.maps && google.maps.places)) return;
        const ac = new google.maps.places.Autocomplete(locationInput, {
          types: ['geocode', 'establishment'],
          fields: ['formatted_address', 'name', 'geometry']
        });
        ac.addListener('place_changed', function () {
          const place = ac.getPlace();
          if (!place || !place.geometry) return;
          const lat = place.geometry.location.lat();
          const lng = place.geometry.location.lng();
          latInput.value = lat;
          lngInput.value = lng;
          locationInput.value = place.name || place.formatted_address || locationInput.value;
          maybeAutofillDescription();
        });
      });
    })
    .catch(() => {});
})();
</script>
<?php endif; ?>

<?php include __DIR__ . '/includes/admin-footer.php'; ?>

