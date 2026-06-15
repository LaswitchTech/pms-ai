<?php

/**
 * Generate task decomposition suggestions using Ollama.
 *
 * @param string $taskDescription The task description to decompose
 * @param string $settingsPath Path to the settings.json file (default: __DIR__ . '/../settings.json')
 * @return array Response data with suggestions
 */
function generateTaskDecomposition(string $taskDescription, string $settingsPath = __DIR__ . '/../settings.json'): array
{
    require_once __DIR__ . '/ollama.php';
    require_once __DIR__ . '/ai_prompts.php';
    
    $promptTemplate = getPrompt('task_decomposition');
    if ($promptTemplate === null) {
        // Fallback to hardcoded prompt if registry doesn't have it (shouldn't happen)
        $promptTemplate = "Please decompose the following task into 3-5 logical subtasks:\n\n{task_description}\n\nFormat your response as a markdown list with subtasks numbered.";
    }
    
    $prompt = str_replace('{task_description}', $taskDescription, $promptTemplate);
    
    $response = ollamaPrompt($prompt, [], $settingsPath);
    
    if (!$response['success']) {
        return [
            'success' => false,
            'error' => 'Failed to generate task decomposition: ' . ($response['error'] ?? 'Unknown error'),
            'data' => $response
        ];
    }
    
    // Parse the response into structured data
    $subtasks = [];
    $lines = explode("\n", trim($response['response']));
    
    foreach ($lines as $line) {
        if (preg_match('/^(\d+)\.\s+(.+)$/', trim($line), $matches)) {
            $subtasks[] = [
                'id' => (int)$matches[1],
                'description' => trim($matches[2]),
                'priority' => 'medium'
            ];
        }
    }
    
    return [
        'success' => true,
        'subtasks' => $subtasks,
        'raw_response' => $response['response']
    ];
}

/**
 * Generate subtasks for a given task description.
 *
 * @param string $taskDescription The task description
 * @param string $settingsPath Path to the settings.json file (default: __DIR__ . '/../settings.json')
 * @return array Response data with subtasks
 */
function generateSubtasks(string $taskDescription, string $settingsPath = __DIR__ . '/../settings.json'): array
{
    require_once __DIR__ . '/ollama.php';
    require_once __DIR__ . '/ai_prompts.php';
    
    $promptTemplate = getPrompt('task_subtasks');
    if ($promptTemplate === null) {
        // Fallback to hardcoded prompt if registry doesn't have it (shouldn't happen)
        $promptTemplate = "Generate 3-5 detailed subtasks for the following main task:\n\n{task_description}\n\nFormat each subtask with a brief description only, one per line.";
    }
    
    $prompt = str_replace('{task_description}', $taskDescription, $promptTemplate);
    
    $response = ollamaPrompt($prompt, [], $settingsPath);
    
    if (!$response['success']) {
        return [
            'success' => false,
            'error' => 'Failed to generate subtasks: ' . ($response['error'] ?? 'Unknown error'),
            'data' => $response
        ];
    }
    
    // Parse the response into structured data
    $subtasks = [];
    $lines = explode("\n", trim($response['response']));
    
    foreach ($lines as $line) {
        $line = trim($line);
        if (!empty($line)) {
            $subtasks[] = [
                'description' => $line,
                'priority' => 'medium'
            ];
        }
    }
    
    return [
        'success' => true,
        'subtasks' => $subtasks,
        'raw_response' => $response['response']
    ];
}

/**
 * Suggest priorities for a task based on its description.
 *
 * @param string $taskDescription The task description
 * @param string $settingsPath Path to the settings.json file (default: __DIR__ . '/../settings.json')
 * @return array Response data with priority suggestion
 */
function suggestTaskPriority(string $taskDescription, string $settingsPath = __DIR__ . '/../settings.json'): array
{
    require_once __DIR__ . '/ollama.php';
    require_once __DIR__ . '/ai_prompts.php';
    
    $promptTemplate = getPrompt('task_priority');
    if ($promptTemplate === null) {
        // Fallback to hardcoded prompt if registry doesn't have it (shouldn't happen)
        $promptTemplate = "Based on the following task description, please suggest a priority level from the options: high, medium, low\n\n{task_description}\n\nRespond with only the priority level (high/medium/low) and nothing else.";
    }
    
    $prompt = str_replace('{task_description}', $taskDescription, $promptTemplate);
    
    $response = ollamaPrompt($prompt, [], $settingsPath);
    
    if (!$response['success']) {
        return [
            'success' => false,
            'error' => 'Failed to suggest task priority: ' . ($response['error'] ?? 'Unknown error'),
            'data' => $response
        ];
    }
    
    $priority = trim(strtolower($response['response']));
    if (!in_array($priority, ['high', 'medium', 'low'])) {
        $priority = 'medium';
    }
    
    return [
        'success' => true,
        'priority' => $priority,
        'raw_response' => $response['response']
    ];
}

/**
 * Generate AI-based suggestions for a task including decomposition, subtasks and priorities.
 *
 * @param string $taskDescription The task description
 * @param string $settingsPath Path to the settings.json file (default: __DIR__ . '/../settings.json')
 * @return array Complete task review data
 */
function generateTaskReview(string $taskDescription, string $settingsPath = __DIR__ . '/../settings.json'): array
{
    // Generate decomposed subtasks
    $subtasksResult = generateSubtasks($taskDescription, $settingsPath);
    
    // Suggest priority
    $priorityResult = suggestTaskPriority($taskDescription, $settingsPath);
    
    return [
        'success' => $subtasksResult['success'] && $priorityResult['success'],
        'subtasks' => $subtasksResult['subtasks'],
        'priority' => $priorityResult['priority'],
        'raw_data' => [
            'decomposition' => $subtasksResult['raw_response'],
            'priority' => $priorityResult['raw_response']
        ]
    ];
}