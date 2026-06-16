<?php

/**
 * API endpoint for generating ROADMAP.md from KANBAN.md content.
 *
 * This endpoint expects a POST request with KANBAN.md content and returns
 * generated roadmap data that can be used to create/update ROADMAP.md.
 */

// Only accept POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Method not allowed. Use POST request.']);
    exit;
}

// Get the raw POST data
$input = file_get_contents('php://input');
if (empty($input)) {
    http_response_code(400);
    header('Content-Type: application/json');
    echo json_encode(['error' => 'No input data provided.']);
    exit;
}

// Parse JSON input if it's JSON
$data = json_decode($input, true);

// Check if we have a kanban content or get it from the raw input directly
$kanbanContent = '';
if (isset($data['kanban_content'])) {
    $kanbanContent = $data['kanban_content'];
} else {
    // If no JSON structure, assume raw markdown input
    $kanbanContent = $input;
}

// Make sure the kanban content is not empty
if (empty(trim($kanbanContent))) {
    http_response_code(400);
    header('Content-Type: application/json');
    echo json_encode(['error' => 'KANBAN.md content cannot be empty.']);
    exit;
}

try {
    // Include the roadmap generation functions
    require_once __DIR__ . '/../lib/roadmap.php';
    
    // Generate complete roadmap from kanban content
    $result = generateCompleteRoadmap($kanbanContent);
    
    if (!$result['success']) {
        http_response_code(500);
        header('Content-Type: application/json');
        echo json_encode([
            'success' => false,
            'error' => $result['error']
        ]);
        exit;
    }
    
    // Return the generated roadmap data as JSON
    header('Content-Type: application/json');
    echo json_encode([
        'success' => true,
        'roadmap' => $result['roadmap'],
        'milestones' => $result['milestones'],
        'timeline' => $result['timeline']
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    header('Content-Type: application/json');
    echo json_encode([
        'success' => false,
        'error' => 'Internal server error: ' . $e->getMessage()
    ]);
}
