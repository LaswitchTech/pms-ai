<?php

/**
 * Generate ROADMAP.md content from KANBAN.md.
 *
 * @param string $kanbanContent The content of KANBAN.md
 * @param string $settingsPath Path to the settings.json file (default: __DIR__ . '/../settings.json')
 * @return array Response data with roadmap content
 */
function generateRoadmapFromKanban(string $kanbanContent, string $settingsPath = __DIR__ . '/../settings.json'): array
{
    require_once __DIR__ . '/ollama.php';
    require_once __DIR__ . '/ai_prompts.php';
    
    if (empty(trim($kanbanContent))) {
        return [
            'success' => false,
            'error' => 'KANBAN.md content is empty',
        ];
    }
    
    $promptTemplate = getPrompt('roadmap_generation');
    if ($promptTemplate === null) {
        // Fallback to hardcoded prompt if registry doesn't have it (shouldn't happen)
        $promptTemplate = "Generate a ROADMAP.md file based on the following KANBAN.md content. Structure it in proper markdown with sections, and include release milestones and timeline information:\n\n### KANBAN.md Content:\n{kanban_content}\n\nGenerate a comprehensive roadmap that includes:\n1. Release milestones\n2. Timeline estimation\n3. Priority ordering of tasks\n4. Strategic planning considerations\n\nFormat properly with markdown headers and lists.";
    }
    
    $prompt = str_replace('{kanban_content}', $kanbanContent, $promptTemplate);
    
    $response = ollamaPrompt($prompt, [], $settingsPath);
    
    if (!$response['success']) {
        return [
            'success' => false,
            'error' => 'Failed to generate roadmap: ' . ($response['error'] ?? 'Unknown error'),
            'data' => $response
        ];
    }
    
    return [
        'success' => true,
        'content' => $response['response'],
        'raw_response' => $response['response']
    ];
}

/**
 * Generate release milestones from kanban content.
 *
 * @param string $kanbanContent The content of KANBAN.md
 * @param string $settingsPath Path to the settings.json file (default: __DIR__ . '/../settings.json')
 * @return array Response data with milestone information
 */
function generateReleaseMilestones(string $kanbanContent, string $settingsPath = __DIR__ . '/../settings.json'): array
{
    require_once __DIR__ . '/ollama.php';
    require_once __DIR__ . '/ai_prompts.php';
    
    if (empty(trim($kanbanContent))) {
        return [
            'success' => false,
            'error' => 'KANBAN.md content is empty',
        ];
    }
    
    $promptTemplate = getPrompt('release_milestones');
    if ($promptTemplate === null) {
        // Fallback to hardcoded prompt if registry doesn't have it (shouldn't happen)
        $promptTemplate = "Extract and organize the following KANBAN.md content into release milestones:\n\n{kanban_content}\n\nGenerate a list of release milestones with approximate dates, based on task dependencies and complexity. Format as a markdown list.";
    }
    
    $prompt = str_replace('{kanban_content}', $kanbanContent, $promptTemplate);
    
    $response = ollamaPrompt($prompt, [], $settingsPath);
    
    if (!$response['success']) {
        return [
            'success' => false,
            'error' => 'Failed to generate release milestones: ' . ($response['error'] ?? 'Unknown error'),
            'data' => $response
        ];
    }
    
    return [
        'success' => true,
        'milestones' => $response['response'],
        'raw_response' => $response['response']
    ];
}

/**
 * Generate timeline from kanban content.
 *
 * @param string $kanbanContent The content of KANBAN.md
 * @param string $settingsPath Path to the settings.json file (default: __DIR__ . '/../settings.json')
 * @return array Response data with timeline information
 */
function generateTimeline(string $kanbanContent, string $settingsPath = __DIR__ . '/../settings.json'): array
{
    require_once __DIR__ . '/ollama.php';
    require_once __DIR__ . '/ai_prompts.php';
    
    if (empty(trim($kanbanContent))) {
        return [
            'success' => false,
            'error' => 'KANBAN.md content is empty',
        ];
    }
    
    $promptTemplate = getPrompt('project_timeline');
    if ($promptTemplate === null) {
        // Fallback to hardcoded prompt if registry doesn't have it (shouldn't happen)
        $promptTemplate = "Based on the following KANBAN.md content, generate a project timeline with key dates:\n\n{kanban_content}\n\nCreate a timeline that shows planned completion dates for major features or groups of tasks. Format it as a markdown timeline.";
    }
    
    $prompt = str_replace('{kanban_content}', $kanbanContent, $promptTemplate);
    
    $response = ollamaPrompt($prompt, [], $settingsPath);
    
    if (!$response['success']) {
        return [
            'success' => false,
            'error' => 'Failed to generate timeline: ' . ($response['error'] ?? 'Unknown error'),
            'data' => $response
        ];
    }
    
    return [
        'success' => true,
        'timeline' => $response['response'],
        'raw_response' => $response['response']
    ];
}

/**
 * Generate complete roadmap data from kanban.
 *
 * @param string $kanbanContent The content of KANBAN.md
 * @param string $settingsPath Path to the settings.json file (default: __DIR__ . '/../settings.json')
 * @return array Complete roadmap information including all components
 */
function generateCompleteRoadmap(string $kanbanContent, string $settingsPath = __DIR__ . '/../settings.json'): array
{
    // Generate roadmap from kanban
    $roadmapResult = generateRoadmapFromKanban($kanbanContent, $settingsPath);
    
    if (!$roadmapResult['success']) {
        return $roadmapResult;
    }
    
    // Generate milestone information
    $milestoneResult = generateReleaseMilestones($kanbanContent, $settingsPath);
    
    // Generate timeline information
    $timelineResult = generateTimeline($kanbanContent, $settingsPath);
    
    return [
        'success' => true,
        'roadmap' => $roadmapResult['content'],
        'milestones' => $milestoneResult['milestones'],
        'timeline' => $timelineResult['timeline'],
        'raw_data' => [
            'roadmap' => $roadmapResult['raw_response'],
            'milestones' => $milestoneResult['raw_response'],
            'timeline' => $timelineResult['raw_response']
        ]
    ];
}