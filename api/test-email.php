<?php
/**
 * Test Email API
 * Send a test email to verify SMTP configuration
 */

// Suppress ALL error output to ensure clean JSON response
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ob_start();

// Set JSON header immediately
header('Content-Type: application/json');

try {
    // Temporarily disable display_errors before including files
    $oldDisplayErrors = ini_get('display_errors');
    ini_set('display_errors', 0);
    
    require_once __DIR__ . '/../config.php';
    require_once __DIR__ . '/../includes/functions.php';
    
    // Restore display_errors setting
    ini_set('display_errors', $oldDisplayErrors);
    
    // Clear any output that might have been generated
    ob_clean();
} catch (Exception $e) {
    ob_end_clean();
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Failed to load required files: ' . $e->getMessage()]);
    exit;
} catch (Error $e) {
    ob_end_clean();
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Failed to load required files: ' . $e->getMessage()]);
    exit;
}

// Check if admin is logged in
if (!isAdminLoggedIn()) {
    ob_end_clean();
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // Set execution time limit for email sending
        set_time_limit(30); // 30 seconds max
        
        // Clear any output buffer
        ob_clean();
        
        $input = json_decode(file_get_contents('php://input'), true);
        
        if (empty($input['email'])) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Email address is required']);
            exit;
        }
        
        $testEmail = filter_var($input['email'], FILTER_VALIDATE_EMAIL);
        if (!$testEmail) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Invalid email address']);
            exit;
        }
        
        // Get company name for email
        $companyName = getSetting('company_name', 'Shipping Company');
        $fromName = getSetting('smtp_from_name', defined('SMTP_FROM_NAME') ? SMTP_FROM_NAME : 'Shipping Company');
        
        // Create mock shipment data for test email template
        $mockShipment = [
            'tracking_number' => 'TEST-' . strtoupper(substr(md5(time()), 0, 8)),
            'reference_number' => 'TEST-REF-' . date('Ymd'),
            'status' => 'Out for Delivery',
            'service_type' => $companyName . ' Express',
            'recipient_name' => 'Test Recipient',
            'recipient_address' => '123 Test Street',
            'recipient_city' => 'Test City',
            'recipient_state' => 'TS',
            'recipient_zip' => '12345',
            'recipient_country' => 'United States',
            'estimated_delivery' => date('Y-m-d', strtotime('+1 day'))
        ];
        
        // Create mock tracking events for test email
        $mockTrackingEvents = [
            [
                'event_type' => 'Order Processed',
                'description' => 'Shipment label created',
                'location' => 'Origin Facility',
                'event_date' => date('Y-m-d H:i:s', strtotime('-2 days'))
            ],
            [
                'event_type' => 'In Transit - Hub North',
                'description' => 'Package in transit',
                'location' => 'Distribution Center',
                'event_date' => date('Y-m-d H:i:s', strtotime('-1 day'))
            ],
            [
                'event_type' => 'Out for Delivery',
                'description' => 'Package out for delivery',
                'location' => 'Local Facility',
                'event_date' => date('Y-m-d H:i:s')
            ]
        ];
        
        // Generate email using the shipment template
        $subject = 'Test Email - ' . $companyName . ' Email Configuration';
        $body = generateShipmentEmailTemplate($mockShipment, $mockTrackingEvents);
        
        // Add a note at the top of the email body indicating this is a test
        $testNote = '
        <div style="background-color: #fbbf24; color: #1a1a1a; padding: 16px; margin-bottom: 20px; border-radius: 8px; text-align: center; font-weight: bold;">
            ⚠️ This is a TEST EMAIL to verify your SMTP configuration is working correctly.
        </div>';
        
        // Insert test note after the opening body tag
        $body = str_replace('<body style="background-color: #f6f6f8; padding: 20px 0;">', 
                           '<body style="background-color: #f6f6f8; padding: 20px 0;">' . $testNote, 
                           $body);
        
        // Send test email
        $result = sendEmail($testEmail, $subject, $body, true);
        
        // Clear any unexpected output before sending JSON
        ob_clean();
        
        if ($result['success']) {
            echo json_encode([
                'success' => true,
                'message' => 'Test email sent successfully'
            ]);
            exit;
        } else {
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'message' => $result['message']
            ]);
            exit;
        }
    } catch (Exception $e) {
        ob_clean();
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'message' => 'Error: ' . $e->getMessage()
        ]);
        exit;
    } catch (Error $e) {
        ob_clean();
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'message' => 'Fatal error: ' . $e->getMessage()
        ]);
        exit;
    }
} else {
    ob_clean();
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}
