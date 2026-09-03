<?php
/**
 * Quick status + location update (Admin)
 * Used by the dashboard / manage-shipments 3-dot menu modal.
 */

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/includes/admin-auth.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: /admin/dashboard.php');
    exit;
}

$shipmentId = isset($_POST['id']) ? intval($_POST['id']) : 0;
$returnTo = safeAdminReturnUrl($_POST['return_to'] ?? '', '/admin/dashboard.php');

function quickUpdateRedirect($returnTo, $params) {
    $sep = strpos($returnTo, '?') === false ? '?' : '&';
    header('Location: ' . $returnTo . $sep . http_build_query($params));
    exit;
}

if ($shipmentId <= 0) {
    quickUpdateRedirect($returnTo, ['error' => 'Invalid shipment ID']);
}

$statusOptions = getShipmentStatusOptions();
$newStatus = sanitizeInput($_POST['status'] ?? '');
$location = sanitizeInput($_POST['location'] ?? '');
$note = sanitizeInput($_POST['note'] ?? '');
$latitude = (isset($_POST['latitude']) && $_POST['latitude'] !== '') ? floatval($_POST['latitude']) : null;
$longitude = (isset($_POST['longitude']) && $_POST['longitude'] !== '') ? floatval($_POST['longitude']) : null;

if ($newStatus === '' || !in_array($newStatus, $statusOptions, true)) {
    quickUpdateRedirect($returnTo, ['error' => 'Please choose a valid status.']);
}

if ($location !== '' && !hasUsableMapCoords($latitude, $longitude)) {
    quickUpdateRedirect($returnTo, ['error' => 'Select the location from the suggestions so it can be plotted on the map.']);
}

$stmt = $conn->prepare('SELECT * FROM shipments WHERE id = ?');
$stmt->bind_param('i', $shipmentId);
$stmt->execute();
$result = $stmt->get_result();
$shipment = $result ? $result->fetch_assoc() : null;
$stmt->close();

if (!$shipment) {
    quickUpdateRedirect($returnTo, ['error' => 'Shipment not found']);
}

$statusChanged = (string) $shipment['status'] !== (string) $newStatus;
$hasLocation = $location !== '' && hasUsableMapCoords($latitude, $longitude);
$hasNote = $note !== '';

if (!$statusChanged && !$hasLocation && !$hasNote) {
    quickUpdateRedirect($returnTo, ['error' => 'Change the status, location, or add a note before saving.']);
}

if ($statusChanged) {
    $upd = $conn->prepare('UPDATE shipments SET status = ? WHERE id = ?');
    $upd->bind_param('si', $newStatus, $shipmentId);
    $upd->execute();
    $upd->close();
}

$eventLocation = null;
$eventLat = null;
$eventLng = null;

if ($hasLocation) {
    $eventLocation = $location;
    $eventLat = $latitude;
    $eventLng = $longitude;
} else {
    // Copy the latest plotted point as a whole. Never fall back to pickup —
    // that snaps the truck back to origin and can store 0,0 via bind_param.
    $latestStmt = $conn->prepare("SELECT location, latitude, longitude FROM tracking_events WHERE shipment_id = ? AND event_type != 'Admin Note' ORDER BY event_date DESC, id DESC LIMIT 1");
    $latestStmt->bind_param('i', $shipmentId);
    $latestStmt->execute();
    $latestRes = $latestStmt->get_result();
    $latest = $latestRes ? $latestRes->fetch_assoc() : null;
    $latestStmt->close();
    if ($latest && hasUsableMapCoords($latest['latitude'] ?? null, $latest['longitude'] ?? null)) {
        $eventLocation = $latest['location'] !== '' ? $latest['location'] : null;
        $eventLat = floatval($latest['latitude']);
        $eventLng = floatval($latest['longitude']);
    }
}

if ($hasNote) {
    $description = $note;
} else {
    $description = $newStatus . ($eventLocation ? ' — ' . $eventLocation : '');
}

$eventDate = date('Y-m-d H:i:s');
$hasGeo = hasUsableMapCoords($eventLat, $eventLng);

if ($hasGeo) {
    $eventStmt = $conn->prepare('INSERT INTO tracking_events (shipment_id, event_type, description, location, latitude, longitude, event_date) VALUES (?, ?, ?, ?, ?, ?, ?)');
    $eventStmt->bind_param('isssdds', $shipmentId, $newStatus, $description, $eventLocation, $eventLat, $eventLng, $eventDate);
} else {
    $eventStmt = $conn->prepare('INSERT INTO tracking_events (shipment_id, event_type, description, location, latitude, longitude, event_date) VALUES (?, ?, ?, ?, NULL, NULL, ?)');
    $emptyLocation = $eventLocation ?? '';
    $eventStmt->bind_param('issss', $shipmentId, $newStatus, $description, $emptyLocation, $eventDate);
}

if (!$eventStmt->execute()) {
    $eventStmt->close();
    quickUpdateRedirect($returnTo, ['error' => 'Failed to save tracking event.']);
}
$eventStmt->close();

$emailStatus = '';
if ($statusChanged) {
    if (!empty($shipment['recipient_email'])) {
        $emailResult = sendShipmentNotificationEmail($shipmentId);
        $emailStatus = !empty($emailResult['success']) ? 'sent' : 'failed';
    } else {
        $emailStatus = 'missing-recipient-email';
    }
}

$params = ['updated' => '1'];
if ($emailStatus !== '') {
    $params['email'] = $emailStatus;
}
quickUpdateRedirect($returnTo, $params);
