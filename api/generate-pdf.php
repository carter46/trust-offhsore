<?php
/**
 * Public / shared shipping receipt
 * Same professional template used by admin Print receipt + tracking Print.
 */

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/functions.php';

$trackingNumber = isset($_GET['id']) ? trim((string) $_GET['id']) : '';

if ($trackingNumber === '') {
    http_response_code(400);
    die('Tracking number is required');
}

$shipment = getShipmentByTracking($trackingNumber);

if (!$shipment) {
    http_response_code(404);
    die('Shipment not found');
}

$events = getTrackingEvents($shipment['id']);
$autoprint = isset($_GET['autoprint']) && $_GET['autoprint'] == '1';

header('Content-Type: text/html; charset=utf-8');
include __DIR__ . '/../templates/shipment-pdf-template.php';
