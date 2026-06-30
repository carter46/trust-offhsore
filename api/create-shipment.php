<?php
/**
 * Create Shipment API
 * Create a new shipment (Admin only)
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
    $rawInput = file_get_contents('php://input');
    $input = json_decode($rawInput, true);
    
    // #region agent log
    // Log to site root so it's available on hosting File Manager/FTP
    $agentLogFile = __DIR__ . '/../debug.log';
    $agentLogLine = json_encode([
        'sessionId' => 'bug-check',
        'runId' => 'pre-fix',
        'hypothesisId' => 'A',
        'location' => 'api/create-shipment.php:20',
        'message' => 'API request received',
        'data' => ['method' => $_SERVER['REQUEST_METHOD'], 'inputKeys' => $input ? array_keys($input) : [], 'rawInputLength' => strlen($rawInput)],
        'timestamp' => time() * 1000
    ]) . "\n";
    if (@file_put_contents($agentLogFile, $agentLogLine, FILE_APPEND) === false) {
        error_log('AGENT_LOG ' . $agentLogLine);
    }
    // #endregion
    
    // Validate required fields
    $required = ['sender_name', 'sender_address', 'recipient_name', 'recipient_address', 'service_type'];
    foreach ($required as $field) {
        if (empty($input[$field])) {
            http_response_code(400);
            echo json_encode(['error' => "Field '$field' is required"]);
            exit;
        }
    }
    
    // Generate tracking number (12 digits default)
    $trackingNumber = generateTrackingNumber(12);
    
    // Auto-generate reference number
    $referenceNumber = generateReferenceNumber();
    
    // Prepare data
    $senderName = sanitizeInput($input['sender_name']);
    $senderAddress = sanitizeInput($input['sender_address']);
    $senderCity = isset($input['sender_city']) ? sanitizeInput($input['sender_city']) : '';
    $senderState = isset($input['sender_state']) ? sanitizeInput($input['sender_state']) : '';
    $senderZip = isset($input['sender_zip']) ? sanitizeInput($input['sender_zip']) : '';
    $senderCountry = isset($input['sender_country']) ? sanitizeInput($input['sender_country']) : 'United States';
    $senderEmail = isset($input['sender_email']) && !empty($input['sender_email']) ? sanitizeInput($input['sender_email']) : null;
    $senderPhone = isset($input['sender_phone']) && !empty($input['sender_phone']) ? sanitizeInput($input['sender_phone']) : null;
    $senderLatitude = isset($input['sender_latitude']) && !empty($input['sender_latitude']) ? floatval($input['sender_latitude']) : null;
    $senderLongitude = isset($input['sender_longitude']) && !empty($input['sender_longitude']) ? floatval($input['sender_longitude']) : null;
    
    $recipientName = sanitizeInput($input['recipient_name']);
    $recipientAddress = sanitizeInput($input['recipient_address']);
    $recipientCity = isset($input['recipient_city']) ? sanitizeInput($input['recipient_city']) : '';
    $recipientState = isset($input['recipient_state']) ? sanitizeInput($input['recipient_state']) : '';
    $recipientZip = isset($input['recipient_zip']) ? sanitizeInput($input['recipient_zip']) : '';
    $recipientCountry = isset($input['recipient_country']) ? sanitizeInput($input['recipient_country']) : 'United States';
    $recipientEmail = isset($input['recipient_email']) && !empty($input['recipient_email']) ? sanitizeInput($input['recipient_email']) : null;
    $recipientPhone = isset($input['recipient_phone']) && !empty($input['recipient_phone']) ? sanitizeInput($input['recipient_phone']) : null;
    $recipientLatitude = isset($input['recipient_latitude']) && !empty($input['recipient_latitude']) ? floatval($input['recipient_latitude']) : null;
    $recipientLongitude = isset($input['recipient_longitude']) && !empty($input['recipient_longitude']) ? floatval($input['recipient_longitude']) : null;
    
    $pickupLocation = isset($input['pickup_location']) && !empty($input['pickup_location']) ? sanitizeInput($input['pickup_location']) : null;
    $pickupLatitude = isset($input['pickup_latitude']) && !empty($input['pickup_latitude']) ? floatval($input['pickup_latitude']) : null;
    $pickupLongitude = isset($input['pickup_longitude']) && !empty($input['pickup_longitude']) ? floatval($input['pickup_longitude']) : null;
    
    $dropoffLocation = isset($input['dropoff_location']) && !empty($input['dropoff_location']) ? sanitizeInput($input['dropoff_location']) : null;
    $dropoffLatitude = isset($input['dropoff_latitude']) && !empty($input['dropoff_latitude']) ? floatval($input['dropoff_latitude']) : null;
    $dropoffLongitude = isset($input['dropoff_longitude']) && !empty($input['dropoff_longitude']) ? floatval($input['dropoff_longitude']) : null;
    
    $weight = isset($input['weight']) && !empty($input['weight']) ? sanitizeInput($input['weight']) : null;
    $dimensions = isset($input['dimensions']) && !empty($input['dimensions']) ? sanitizeInput($input['dimensions']) : null;
    $serviceType = sanitizeInput($input['service_type']);
    $status = isset($input['status']) && !empty($input['status']) ? sanitizeInput($input['status']) : 'Pending';
    $estimatedDelivery = isset($input['estimated_delivery']) && !empty($input['estimated_delivery']) ? sanitizeInput($input['estimated_delivery']) : null;
    $shipmentCreatedAt = isset($input['shipment_created_at']) && !empty($input['shipment_created_at'])
        ? parseDateTimeInput($input['shipment_created_at'])
        : date('Y-m-d H:i:s');
    $itemImage = isset($input['item_image']) && !empty($input['item_image']) ? sanitizeInput($input['item_image']) : null;
    $adminComment = isset($input['admin_comment']) && !empty($input['admin_comment']) ? sanitizeInput($input['admin_comment']) : null;
    
    $shipmentWorth = isset($input['shipment_worth']) && $input['shipment_worth'] !== '' ? floatval($input['shipment_worth']) : null;
    $baseCost = isset($input['base_cost']) && $input['base_cost'] !== '' ? floatval($input['base_cost']) : null;
    $clearanceCost = isset($input['clearance_cost']) && $input['clearance_cost'] !== '' ? floatval($input['clearance_cost']) : null;
    $totalCost = null;
    if ($baseCost !== null || $clearanceCost !== null) {
        $totalCost = ($baseCost ?? 0) + ($clearanceCost ?? 0);
    }
    
    // Insert shipment with all fields (38 placeholders)
    $stmt = $conn->prepare("INSERT INTO shipments (tracking_number, sender_name, sender_address, sender_city, sender_state, sender_zip, sender_country, sender_email, sender_phone, sender_latitude, sender_longitude, recipient_name, recipient_address, recipient_city, recipient_state, recipient_zip, recipient_country, recipient_email, recipient_phone, recipient_latitude, recipient_longitude, pickup_location, pickup_latitude, pickup_longitude, dropoff_location, dropoff_latitude, dropoff_longitude, weight, dimensions, service_type, status, estimated_delivery, shipment_created_at, reference_number, item_image, shipment_worth, base_cost, clearance_cost, total_cost) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
    
    if (!$stmt) {
        // #region agent log
        $agentLogFile = __DIR__ . '/../debug.log';
        $agentLogLine = json_encode([
            'sessionId' => 'bug-check',
            'runId' => 'pre-fix',
            'hypothesisId' => 'B',
            'location' => 'api/create-shipment.php:88',
            'message' => 'Statement preparation failed',
            'data' => ['error' => $conn->error, 'errno' => $conn->errno],
            'timestamp' => time() * 1000
        ]) . "\n";
        if (@file_put_contents($agentLogFile, $agentLogLine, FILE_APPEND) === false) {
            error_log('AGENT_LOG ' . $agentLogLine);
        }
        // #endregion
        http_response_code(500);
        echo json_encode(['error' => 'Failed to prepare statement: ' . $conn->error]);
        exit;
    }
    
    // Convert weight to float if it's a string (weight is DECIMAL in DB)
    if ($weight !== null && is_string($weight)) {
        $weight = floatval($weight);
    }
    // Ensure weight is null or float (not string) for DECIMAL field
    if ($weight !== null && !is_float($weight) && !is_int($weight)) {
        $weight = floatval($weight);
    }
    
    // Correct bind_param string: 9s + 2d + 8s + 2d + 1s + 2d + 1s + 2d + 1d + 6s + 3d = 37 chars
    // tracking_number(1s) + sender(7s: name,address,city,state,zip,country,email) + sender_phone(1s) + sender_lat/lng(2d) + 
    // recipient(6s: name,address,city,state,zip,country) + recipient_email(1s) + recipient_phone(1s) + recipient_lat/lng(2d) + 
    // pickup_location(1s) + pickup_lat/lng(2d) + dropoff_location(1s) + dropoff_lat/lng(2d) + 
    // weight(1d) + dimensions/service/status/est_delivery/ref/item(6s) + worth/base/total(3d)
    // Bind string: 9s + 2d + 8s + 2d + 1s + 2d + 1s + 2d + 1d + 6s + 3d = 37 characters
    // Parameters: tracking+sender(9s) + sender_coords(2d) + recipient(8s) + recipient_coords(2d) + 
    //             pickup(1s) + pickup_coords(2d) + dropoff(1s) + dropoff_coords(2d) + 
    //             weight(1d) + other_fields(6s) + costs(3d)
    // Bind string: 37 params + clearance_cost (1d) = 38 chars
    $bindString = "sssssssssddssssssssddsddsdddsssssssdddd";
    
    $stmt->bind_param($bindString, 
        $trackingNumber, $senderName, $senderAddress, $senderCity, $senderState, $senderZip, $senderCountry,
        $senderEmail, $senderPhone,
        $senderLatitude, $senderLongitude,
        $recipientName, $recipientAddress, $recipientCity, $recipientState, $recipientZip, $recipientCountry,
        $recipientEmail, $recipientPhone,
        $recipientLatitude, $recipientLongitude,
        $pickupLocation, $pickupLatitude, $pickupLongitude,
        $dropoffLocation, $dropoffLatitude, $dropoffLongitude,
        $weight, $dimensions, $serviceType, $status, $estimatedDelivery, $shipmentCreatedAt, $referenceNumber, $itemImage,
        $shipmentWorth, $baseCost, $clearanceCost, $totalCost
    );
    
    $executeResult = $stmt->execute();
    
    // #region agent log
    $agentLogFile = __DIR__ . '/../debug.log';
    $agentLogLine = json_encode([
        'sessionId' => 'bug-check',
        'runId' => 'pre-fix',
        'hypothesisId' => 'C',
        'location' => 'api/create-shipment.php:126',
        'message' => 'Statement execution result',
        'data' => ['executeResult' => $executeResult, 'error' => $stmt->error, 'errno' => $stmt->errno, 'affectedRows' => $stmt->affected_rows],
        'timestamp' => time() * 1000
    ]) . "\n";
    if (@file_put_contents($agentLogFile, $agentLogLine, FILE_APPEND) === false) {
        error_log('AGENT_LOG ' . $agentLogLine);
    }
    // #endregion
    
    if ($executeResult) {
        $shipmentId = $stmt->insert_id;
        
        // Create initial tracking event
        $eventType = 'Label Created';
        $description = 'Shipment label created';
        // Prefer pickup location as the shipment's initial active location (if provided)
        $location = $pickupLocation ?: ($senderCity ? $senderCity . ', ' . $senderState : 'Origin');
        $eventLat = $pickupLatitude;
        $eventLng = $pickupLongitude;
        
        $eventStmt = $conn->prepare("INSERT INTO tracking_events (shipment_id, event_type, description, location, latitude, longitude, event_date) VALUES (?, ?, ?, ?, ?, ?, ?)");
        $eventStmt->bind_param("isssdds", $shipmentId, $eventType, $description, $location, $eventLat, $eventLng, $shipmentCreatedAt);
        $eventStmt->execute();
        $eventStmt->close();
        
        // Create tracking event for admin comment if provided
        if ($adminComment) {
            $commentEventStmt = $conn->prepare("INSERT INTO tracking_events (shipment_id, event_type, description, location, event_date) VALUES (?, ?, ?, ?, ?)");
            $commentEventType = 'Admin Note';
            $commentLocation = $pickupLocation ?: ($senderCity ? $senderCity . ', ' . $senderState : 'Origin');
            $commentEventStmt->bind_param("issss", $shipmentId, $commentEventType, $adminComment, $commentLocation, $shipmentCreatedAt);
            $commentEventStmt->execute();
            $commentEventStmt->close();
        }
        
        $stmt->close();
        
        // Send email notification if requested and recipient email exists
        $emailSent = false;
        $emailError = null;
        if (isset($input['send_email_notification']) && $input['send_email_notification'] && !empty($recipientEmail)) {
            $emailResult = sendShipmentNotificationEmail($shipmentId);
            $emailSent = $emailResult['success'];
            if (!$emailSent) {
                $emailError = $emailResult['message'];
            }
        }
        
        $response = [
            'success' => true,
            'tracking_number' => $trackingNumber,
            'shipment_id' => $shipmentId
        ];
        
        if ($emailSent) {
            $response['email_sent'] = true;
        } elseif (isset($input['send_email_notification']) && $input['send_email_notification']) {
            $response['email_sent'] = false;
            $response['email_error'] = $emailError;
        }
        
        echo json_encode($response);
    } else {
        // #region agent log
        $agentLogFile = __DIR__ . '/../debug.log';
        $agentLogLine = json_encode([
            'sessionId' => 'bug-check',
            'runId' => 'pre-fix',
            'hypothesisId' => 'D',
            'location' => 'api/create-shipment.php:160',
            'message' => 'Statement execution failed',
            'data' => ['error' => $stmt->error, 'errno' => $stmt->errno, 'sqlState' => $stmt->sqlstate],
            'timestamp' => time() * 1000
        ]) . "\n";
        if (@file_put_contents($agentLogFile, $agentLogLine, FILE_APPEND) === false) {
            error_log('AGENT_LOG ' . $agentLogLine);
        }
        // #endregion
        http_response_code(500);
        $errorMsg = 'Failed to create shipment: ' . $stmt->error;
        // Check if error is due to missing columns
        if (strpos($stmt->error, "Unknown column") !== false) {
            $errorMsg .= '. Please run the database migration to add email and phone columns.';
        }
        echo json_encode(['error' => $errorMsg]);
        $stmt->close();
    }
} else {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
}

