<?php
/**
 * Generate Shipment PDF
 * Generate PDF document for a shipment
 */

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/functions.php';

// Check if admin is logged in
if (!isAdminLoggedIn()) {
    http_response_code(401);
    die('Unauthorized');
}

$shipmentId = isset($_GET['id']) ? intval($_GET['id']) : 0;
// When download=1, force a file download (HTML document that can be printed/saved as PDF)
$download = isset($_GET['download']) && $_GET['download'] == '1';

if (!$shipmentId) {
    die('Shipment ID is required');
}

// Get shipment data
$stmt = $conn->prepare("SELECT * FROM shipments WHERE id = ?");
$stmt->bind_param("i", $shipmentId);
$stmt->execute();
$result = $stmt->get_result();
$shipment = $result->fetch_assoc();
$stmt->close();

if (!$shipment) {
    die('Shipment not found');
}

$events = getTrackingEvents($shipment['id']);
$autoprint = isset($_GET['autoprint']) && $_GET['autoprint'] == '1';

// Set content type for HTML (print / Save as PDF in the browser)
header('Content-Type: text/html; charset=utf-8');

if ($download) {
    $safeTracking = preg_replace('/[^A-Za-z0-9_-]+/', '-', (string) ($shipment['tracking_number'] ?? $shipmentId));
    header('Content-Disposition: attachment; filename="shipment-receipt-' . $safeTracking . '.html"');
}

include __DIR__ . '/../templates/shipment-pdf-template.php';

