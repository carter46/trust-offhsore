<?php
/**
 * Pages API
 * Create, read, update, and delete page content
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

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    // Get page content
    $slug = isset($_GET['slug']) ? sanitizeInput($_GET['slug']) : null;
    
    if ($slug) {
        $page = getPageContent($slug);
        if ($page) {
            echo json_encode([
                'success' => true,
                'page' => $page
            ]);
        } else {
            http_response_code(404);
            echo json_encode(['error' => 'Page not found']);
        }
    } else {
        // Get all pages
        $pages = [];
        $result = $conn->query("SELECT id, page_slug, page_title, updated_at FROM pages ORDER BY page_title ASC");
        if ($result) {
            while ($row = $result->fetch_assoc()) {
                $pages[] = $row;
            }
            $result->free();
        }
        
        echo json_encode([
            'success' => true,
            'pages' => $pages
        ]);
    }
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Create or update page
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (empty($input['slug']) || empty($input['title'])) {
        http_response_code(400);
        echo json_encode(['error' => 'slug and title are required']);
        exit;
    }
    
    $slug = sanitizeInput($input['slug']);
    $title = sanitizeInput($input['title']);
    $content = isset($input['content']) ? $input['content'] : [];
    
    // Validate content is array
    if (!is_array($content)) {
        http_response_code(400);
        echo json_encode(['error' => 'content must be an array']);
        exit;
    }
    
    if (updatePageContent($slug, $title, $content)) {
        echo json_encode([
            'success' => true,
            'message' => 'Page updated successfully'
        ]);
    } else {
        http_response_code(500);
        echo json_encode(['error' => 'Failed to update page']);
    }
} elseif ($_SERVER['REQUEST_METHOD'] === 'PUT') {
    // Update page (alternative method)
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (empty($input['slug']) || empty($input['title'])) {
        http_response_code(400);
        echo json_encode(['error' => 'slug and title are required']);
        exit;
    }
    
    $slug = sanitizeInput($input['slug']);
    $title = sanitizeInput($input['title']);
    $content = isset($input['content']) ? $input['content'] : [];
    
    if (!is_array($content)) {
        http_response_code(400);
        echo json_encode(['error' => 'content must be an array']);
        exit;
    }
    
    if (updatePageContent($slug, $title, $content)) {
        echo json_encode([
            'success' => true,
            'message' => 'Page updated successfully'
        ]);
    } else {
        http_response_code(500);
        echo json_encode(['error' => 'Failed to update page']);
    }
} elseif ($_SERVER['REQUEST_METHOD'] === 'DELETE') {
    // Delete page
    $slug = isset($_GET['slug']) ? sanitizeInput($_GET['slug']) : null;
    
    if (!$slug) {
        http_response_code(400);
        echo json_encode(['error' => 'slug is required']);
        exit;
    }
    
    $stmt = $conn->prepare("DELETE FROM pages WHERE page_slug = ?");
    $stmt->bind_param("s", $slug);
    
    if ($stmt->execute()) {
        echo json_encode([
            'success' => true,
            'message' => 'Page deleted successfully'
        ]);
    } else {
        http_response_code(500);
        echo json_encode(['error' => 'Failed to delete page']);
    }
    
    $stmt->close();
} else {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
}
