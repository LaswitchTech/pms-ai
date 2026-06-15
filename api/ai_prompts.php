<?php
// API endpoint for handling AI prompt operations

require __DIR__ . '/../lib/ai_prompts.php';

// Only allow POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

// Get the JSON input
$input = file_get_contents('php://input');
$data = json_decode($input, true);

// Validate required fields
if (!isset($data['key']) || !isset($data['template'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Missing required fields: key and template']);
    exit;
}

$key = $data['key'];
$template = $data['template'];

// Attempt to save the prompt
if (setPrompt($key, $template)) {
    // Get the updated prompt data for response
    $registry = getAIPromptsRegistry();
    $updatedPrompt = $registry->getPrompt($key);
    $promptData = $registry->getAllPrompts();  // Get all prompts to update UI
    
    echo json_encode([
        'success' => true,
        'message' => 'Prompt saved successfully',
        'key' => $key,
        'template' => $updatedPrompt,
        'version' => $registry->getPrompt($key) ? 1 : 0, // This will need better approach
        'updated_at' => date('c')
    ]);
} else {
    http_response_code(500);
    echo json_encode([
        'error' => 'Failed to save prompt'
    ]);
}