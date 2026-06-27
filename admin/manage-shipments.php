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

        // --- Optional: Add tracking event ---
        // We only add a tracking event if a status is selected (keeps "Save" safe for remark-only edits).
        $eventType = sanitizeInput($_POST['event_type'] ?? '');
        $location = sanitizeInput($_POST['location'] ?? '');
        $latitude = !empty($_POST['latitude']) ? floatval($_POST['latitude']) : null;
        $longitude = !empty($_POST['longitude']) ? floatval($_POST['longitude']) : null;
        $description = sanitizeInput($_POST['description'] ?? '');

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

        header('Location: /admin/manage-shipments.php?id=' . $shipmentId);
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
    <p class="text-gray-600 dark:text-gray-400">View and update shipment status</p>
</div>

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
            <p class="text-sm text-gray-600 dark:text-gray-400 mb-2"><strong>Status:</strong> <?php echo htmlspecialchars($shipment['status']); ?></p>
            <p class="text-sm text-gray-600 dark:text-gray-400"><strong>Service:</strong> <?php echo htmlspecialchars($shipment['service_type']); ?></p>
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

        <!-- Save (Remark + optional tracking event) -->
        <form method="POST" action="" class="border-t border-gray-200 dark:border-gray-700 pt-6" id="shipment-save-form">
            <input type="hidden" name="action" value="save">

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
                    <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">Start typing and select a suggested location (Google Places).</p>
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

            <button type="submit" class="mt-4 bg-primary hover:bg-primary-dark text-white font-bold py-2 px-6 rounded uppercase tracking-wide transition-colors">
                Save
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
    // Only enforce Places selection if the admin is adding a tracking event.
    if (!wantsToAddEvent()) return;

    if (!locationInput.value.trim()) {
      e.preventDefault();
      alert('Please enter a Current Location (select from suggestions) when adding a tracking event.');
      return;
    }

    if (!latInput.value || !lngInput.value) {
      e.preventDefault();
      alert('Please select a location from the suggestions so latitude/longitude can be saved.');
      return;
    }

    // Auto-fill description if left blank.
    maybeAutofillDescription();
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

