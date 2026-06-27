<?php
/**
 * Settings API
 * Get and update site settings
 */

header('Content-Type: application/json');
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/functions.php';

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    // Get all settings or specific setting
    $key = isset($_GET['key']) ? $_GET['key'] : null;
    
    if ($key) {
        $value = getSetting($key);
        echo json_encode([
            'success' => true,
            'key' => $key,
            'value' => $value
        ]);
    } else {
        // Get all settings
        $stmt = $conn->prepare("SELECT setting_key, setting_value FROM settings");
        $stmt->execute();
        $result = $stmt->get_result();
        
        $settings = [];
        while ($row = $result->fetch_assoc()) {
            $settings[$row['setting_key']] = $row['setting_value'];
        }
        
        $stmt->close();
        
        echo json_encode([
            'success' => true,
            'settings' => $settings
        ]);
    }
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Update settings (Admin only)
    if (!isAdminLoggedIn()) {
        http_response_code(401);
        echo json_encode(['error' => 'Unauthorized']);
        exit;
    }
    
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (empty($input['key']) || !isset($input['value'])) {
        http_response_code(400);
        echo json_encode(['error' => 'key and value are required']);
        exit;
    }
    
    $key = sanitizeInput($input['key']);
    $value = sanitizeInput($input['value']);
    
    if (updateSetting($key, $value)) {
        echo json_encode([
            'success' => true,
            'message' => 'Setting updated successfully'
        ]);
    } else {
        http_response_code(500);
        echo json_encode(['error' => 'Failed to update setting']);
    }
} else {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
}

