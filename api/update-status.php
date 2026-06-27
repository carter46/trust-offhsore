<?php
/**
 * Update Status API
 * Add tracking event to a shipment (Admin only)
 */

header('Content-Type: application/json');
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/functions.php';

// Check if admin is logged in
if (!isAdminLoggedIn()) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    
    // Validate required fields
    if (empty($input['shipment_id']) || empty($input['event_type']) || empty($input['description'])) {
        http_response_code(400);
        echo json_encode(['error' => 'shipment_id, event_type, and description are required']);
        exit;
    }
    
    $shipmentId = intval($input['shipment_id']);
    $eventType = sanitizeInput($input['event_type']);
    $description = sanitizeInput($input['description']);
    $location = isset($input['location']) ? sanitizeInput($input['location']) : null;
    // Treat empty strings as null (prevents 0.0 from floatval('') and losing route points)
    $latitude = (isset($input['latitude']) && $input['latitude'] !== '' && $input['latitude'] !== null) ? floatval($input['latitude']) : null;
    $longitude = (isset($input['longitude']) && $input['longitude'] !== '' && $input['longitude'] !== null) ? floatval($input['longitude']) : null;
    $eventDate = isset($input['event_date']) ? sanitizeInput($input['event_date']) : date('Y-m-d H:i:s');
    
    // Verify shipment exists
    $checkStmt = $conn->prepare("SELECT id, status FROM shipments WHERE id = ?");
    $checkStmt->bind_param("i", $shipmentId);
    $checkStmt->execute();
    $result = $checkStmt->get_result();
    
    if ($result->num_rows === 0) {
        http_response_code(404);
        echo json_encode(['error' => 'Shipment not found']);
        $checkStmt->close();
        exit;
    }
    
    $shipment = $result->fetch_assoc();
    $checkStmt->close();
    
    // Insert tracking event
    $stmt = $conn->prepare("INSERT INTO tracking_events (shipment_id, event_type, description, location, latitude, longitude, event_date) VALUES (?, ?, ?, ?, ?, ?, ?)");
    $stmt->bind_param("isssdds", $shipmentId, $eventType, $description, $location, $latitude, $longitude, $eventDate);
    
    if ($stmt->execute()) {
        $oldStatus = $shipment['status'];
        $statusChanged = false;
        
        // Update shipment status if provided
        if (isset($input['update_status']) && $input['update_status']) {
            $newStatus = sanitizeInput($input['new_status']);
            if ($oldStatus !== $newStatus) {
                $updateStmt = $conn->prepare("UPDATE shipments SET status = ? WHERE id = ?");
                $updateStmt->bind_param("si", $newStatus, $shipmentId);
                $updateStmt->execute();
                $updateStmt->close();
                $statusChanged = true;
            }
        }
        
        $eventId = $stmt->insert_id;
        $stmt->close();
        
        // Send email notification if status changed and recipient email exists
        $emailSent = false;
        $emailError = null;
        if ($statusChanged) {
            // Get updated shipment data
            $updatedStmt = $conn->prepare("SELECT * FROM shipments WHERE id = ?");
            $updatedStmt->bind_param("i", $shipmentId);
            $updatedStmt->execute();
            $updatedResult = $updatedStmt->get_result();
            if ($updatedResult->num_rows > 0) {
                $updatedShipment = $updatedResult->fetch_assoc();
                if (!empty($updatedShipment['recipient_email'])) {
                    $emailResult = sendShipmentNotificationEmail($shipmentId);
                    $emailSent = $emailResult['success'];
                    if (!$emailSent) {
                        $emailError = $emailResult['message'];
                    }
                }
            }
            $updatedStmt->close();
        }
        
        $response = [
            'success' => true,
            'event_id' => $eventId
        ];
        
        if ($emailSent) {
            $response['email_sent'] = true;
        } elseif ($statusChanged) {
            $response['email_sent'] = false;
            $response['email_error'] = $emailError;
        }
        
        echo json_encode($response);
    } else {
        http_response_code(500);
        echo json_encode(['error' => 'Failed to add tracking event: ' . $stmt->error]);
        $stmt->close();
    }
} else {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
}

