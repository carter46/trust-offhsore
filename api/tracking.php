<?php
/**
 * Tracking API
 * Get tracking information for a shipment
 */

header('Content-Type: application/json');
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/functions.php';

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $trackingNumber = isset($_GET['id']) ? $_GET['id'] : '';
    
    if (empty($trackingNumber)) {
        http_response_code(400);
        echo json_encode(['error' => 'Tracking number is required']);
        exit;
    }
    
    // Get shipment
    $shipment = getShipmentByTracking($trackingNumber);
    
    if (!$shipment) {
        http_response_code(404);
        echo json_encode(['error' => 'Shipment not found']);
        exit;
    }
    
    // Get tracking events
    $events = getTrackingEvents($shipment['id']);
    
    // Format response
    $response = [
        'success' => true,
        'shipment' => [
            'id' => $shipment['id'],
            'tracking_number' => $shipment['tracking_number'],
            'sender_name' => $shipment['sender_name'],
            'sender_address' => $shipment['sender_address'],
            'sender_city' => $shipment['sender_city'],
            'sender_state' => $shipment['sender_state'],
            'sender_zip' => $shipment['sender_zip'],
            'sender_email' => $shipment['sender_email'] ?? null,
            'sender_phone' => $shipment['sender_phone'] ?? null,
            'recipient_name' => $shipment['recipient_name'],
            'recipient_address' => $shipment['recipient_address'],
            'recipient_city' => $shipment['recipient_city'],
            'recipient_state' => $shipment['recipient_state'],
            'recipient_zip' => $shipment['recipient_zip'],
            'recipient_email' => $shipment['recipient_email'] ?? null,
            'recipient_phone' => $shipment['recipient_phone'] ?? null,
            'weight' => $shipment['weight'],
            'dimensions' => $shipment['dimensions'],
            'service_type' => $shipment['service_type'],
            'status' => $shipment['status'],
            'estimated_delivery' => $shipment['estimated_delivery'],
            'reference_number' => $shipment['reference_number'],
            'created_at' => $shipment['created_at']
        ],
        'events' => $events
    ];
    
    echo json_encode($response);
} else {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
}

