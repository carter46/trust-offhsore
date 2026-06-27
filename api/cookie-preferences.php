<?php
/**
 * Cookie Preferences API
 * Save user cookie preferences to database
 */

header('Content-Type: application/json');
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/functions.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!isset($input['session_id']) || !isset($input['preferences'])) {
        http_response_code(400);
        echo json_encode(['error' => 'Missing required fields']);
        exit;
    }
    
    $sessionId = sanitizeInput($input['session_id']);
    $preferences = $input['preferences'];
    
    // Insert or update cookie preferences
    $stmt = $conn->prepare("INSERT INTO cookie_preferences (session_id, preferences) VALUES (?, ?) ON DUPLICATE KEY UPDATE preferences = ?");
    $stmt->bind_param("sss", $sessionId, $preferences, $preferences);
    
    if ($stmt->execute()) {
        echo json_encode(['success' => true]);
    } else {
        http_response_code(500);
        echo json_encode(['error' => 'Failed to save preferences']);
    }
    
    $stmt->close();
} else {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
}

