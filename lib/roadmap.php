<?php

/**
 * Roadmap Generator - Centralized service for generating ROADMAP.md content from KANBAN.md.
 *
 * This class implements the roadmap generation functionality as specified in the design documentation,
 * following the Shared Library Pattern with no view or API dependencies, and proper markdown structure
 * preservation including ## Now / ## Next / ## Done sections.
 *
 * @deprecated Use OpenCode `/plan` delegation instead. Will be removed in a future version.
 */
class RoadmapGenerator {
    private $settingsPath;

    public function __construct(string $settingsPath = __DIR__ . '/../settings.json') {
        $this->settingsPath = $settingsPath;
    }

    /**
     * Generate complete ROADMAP.md content from KANBAN.md content
     *
     * @param string $kanbanContent The content of KANBAN.md
     * @return array Response data with roadmap content
     * @deprecated Use OpenCode `/plan` delegation instead. Will be removed in a future version.
     */
    public function generateRoadmapFromKanban(string $kanbanContent): array {
        trigger_error('generateRoadmapFromKanban is deprecated and will be removed in a future version. Use OpenCode `/plan` delegation instead.', E_USER_DEPRECATED);
        require_once __DIR__ . '/ollama.php';
        require_once __DIR__ . '/ai_prompts.php';

        if (empty(trim($kanbanContent))) {
            return [
                'success' => false,
                'error' => 'KANBAN.md content is empty',
            ];
        }

        $registry = getAIPromptsRegistry();
        $promptKey = 'roadmap_generation';
        $promptTemplate = getPrompt($promptKey);
        if ($promptTemplate === null) {
            // Fallback to hardcoded prompt if registry doesn't have it (shouldn't happen)
            $promptTemplate = "Generate a ROADMAP.md file based on the following KANBAN.md content. Structure it in proper markdown with sections, and include release milestones and timeline information:\n\n### KANBAN.md Content:\n{kanban_content}\n\nGenerate a comprehensive roadmap that includes:\n1. Release milestones\n2. Timeline estimation\n3. Priority ordering of tasks\n4. Strategic planning considerations\n\nFormat properly with markdown headers and lists.";
        }

        $prompt = str_replace('{kanban_content}', $kanbanContent, $promptTemplate);

        // Get prompt metadata to check for specific model
        $allPrompts = $registry->getAllPromptsWithMeta();
        $model = null;
        if (isset($allPrompts[$promptKey]) && isset($allPrompts[$promptKey]['model'])) {
            $model = $allPrompts[$promptKey]['model'];
        }

        $options = $model ? ['model' => $model] : [];
        $response = ollamaPrompt($prompt, $options, $this->settingsPath);

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
     * @return array Response data with milestone information
     * @deprecated Use OpenCode `/plan` delegation instead. Will be removed in a future version.
     */
    public function generateReleaseMilestones(string $kanbanContent): array {
        trigger_error('generateReleaseMilestones is deprecated and will be removed in a future version. Use OpenCode `/plan` delegation instead.', E_USER_DEPRECATED);
        require_once __DIR__ . '/ollama.php';
        require_once __DIR__ . '/ai_prompts.php';

        if (empty(trim($kanbanContent))) {
            return [
                'success' => false,
                'error' => 'KANBAN.md content is empty',
            ];
        }

        $registry = getAIPromptsRegistry();
        $promptKey = 'release_milestones';
        $promptTemplate = getPrompt($promptKey);
        if ($promptTemplate === null) {
            // Fallback to hardcoded prompt if registry doesn't have it (shouldn't happen)
            $promptTemplate = "Extract and organize the following KANBAN.md content into release milestones:\n\n{kanban_content}\n\nGenerate a list of release milestones with approximate dates, based on task dependencies and complexity. Format as a markdown list.";
        }

        $prompt = str_replace('{kanban_content}', $kanbanContent, $promptTemplate);

        // Get prompt metadata to check for specific model
        $allPrompts = $registry->getAllPromptsWithMeta();
        $model = null;
        if (isset($allPrompts[$promptKey]) && isset($allPrompts[$promptKey]['model'])) {
            $model = $allPrompts[$promptKey]['model'];
        }

        $options = $model ? ['model' => $model] : [];
        $response = ollamaPrompt($prompt, $options, $this->settingsPath);

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
     * @return array Response data with timeline information
     * @deprecated Use OpenCode `/plan` delegation instead. Will be removed in a future version.
     */
    public function generateTimeline(string $kanbanContent): array {
        trigger_error('generateTimeline is deprecated and will be removed in a future version. Use OpenCode `/plan` delegation instead.', E_USER_DEPRECATED);
        require_once __DIR__ . '/ollama.php';
        require_once __DIR__ . '/ai_prompts.php';

        if (empty(trim($kanbanContent))) {
            return [
                'success' => false,
                'error' => 'KANBAN.md content is empty',
            ];
        }

        $registry = getAIPromptsRegistry();
        $promptKey = 'project_timeline';
        $promptTemplate = getPrompt($promptKey);
        if ($promptTemplate === null) {
            // Fallback to hardcoded prompt if registry doesn't have it (shouldn't happen)
            $promptTemplate = "Based on the following KANBAN.md content, generate a project timeline with key dates:\n\n{kanban_content}\n\nCreate a timeline that shows planned completion dates for major features or groups of tasks. Format it as a markdown timeline.";
        }

        $prompt = str_replace('{kanban_content}', $kanbanContent, $promptTemplate);

        // Get prompt metadata to check for specific model
        $allPrompts = $registry->getAllPromptsWithMeta();
        $model = null;
        if (isset($allPrompts[$promptKey]) && isset($allPrompts[$promptKey]['model'])) {
            $model = $allPrompts[$promptKey]['model'];
        }

        $options = $model ? ['model' => $model] : [];
        $response = ollamaPrompt($prompt, $options, $this->settingsPath);

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
     * @return array Complete roadmap information including all components
     * @deprecated Use OpenCode `/plan` delegation instead. Will be removed in a future version.
     */
    public function generateCompleteRoadmap(string $kanbanContent): array {
        trigger_error('generateCompleteRoadmap is deprecated and will be removed in a future version. Use OpenCode `/plan` delegation instead.', E_USER_DEPRECATED);
        // Generate roadmap from kanban
        $roadmapResult = $this->generateRoadmapFromKanban($kanbanContent);

        if (!$roadmapResult['success']) {
            return $roadmapResult;
        }

        // Generate milestone information
        $milestoneResult = $this->generateReleaseMilestones($kanbanContent);

        // Generate timeline information
        $timelineResult = $this->generateTimeline($kanbanContent);

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

    /**
     * Save roadmap content to a ROADMAP.md file
     *
     * @param string $content The roadmap content to save
     * @param string $filePath The path where to save the ROADMAP.md file
     * @return bool Success status
     */
    public function saveRoadmap(string $content, string $filePath): bool {
        return file_put_contents($filePath, $content) !== false;
    }
}

/**
 * Generate ROADMAP.md content from KANBAN.md.
 *
 * This is the old function that will probably be deprecated but kept for backward compatibility
 *
 * @param string $kanbanContent The content of KANBAN.md
 * @param string $settingsPath Path to the settings.json file (default: __DIR__ . '/../settings.json')
 * @return array Response data with roadmap content
 * @deprecated Use OpenCode `/plan` delegation instead. Will be removed in a future version.
 */
function generateRoadmapFromKanban(string $kanbanContent, string $settingsPath = __DIR__ . '/../settings.json'): array
{
    // Create a RoadmapGenerator instance and use it to provide functionality
    $generator = new RoadmapGenerator($settingsPath);
    return $generator->generateRoadmapFromKanban($kanbanContent);
}

/**
 * Generate release milestones from kanban content.
 *
 * This is the old function that will probably be deprecated but kept for backward compatibility
 *
 * @param string $kanbanContent The content of KANBAN.md
 * @param string $settingsPath Path to the settings.json file (default: __DIR__ . '/../settings.json')
 * @return array Response data with milestone information
 * @deprecated Use OpenCode `/plan` delegation instead. Will be removed in a future version.
 */
function generateReleaseMilestones(string $kanbanContent, string $settingsPath = __DIR__ . '/../settings.json'): array
{
    // Create a RoadmapGenerator instance and use it to provide functionality
    $generator = new RoadmapGenerator($settingsPath);
    return $generator->generateReleaseMilestones($kanbanContent);
}

/**
 * Generate timeline from kanban content.
 *
 * This is the old function that will probably be deprecated but kept for backward compatibility
 *
 * @param string $kanbanContent The content of KANBAN.md
 * @param string $settingsPath Path to the settings.json file (default: __DIR__ . '/../settings.json')
 * @return array Response data with timeline information
 * @deprecated Use OpenCode `/plan` delegation instead. Will be removed in a future version.
 */
function generateTimeline(string $kanbanContent, string $settingsPath = __DIR__ . '/../settings.json'): array
{
    // Create a RoadmapGenerator instance and use it to provide functionality
    $generator = new RoadmapGenerator($settingsPath);
    return $generator->generateTimeline($kanbanContent);
}

/**
 * Generate complete roadmap data from kanban.
 *
 * This is the old function that will probably be deprecated but kept for backward compatibility
 *
 * @param string $kanbanContent The content of KANBAN.md
 * @param string $settingsPath Path to the settings.json file (default: __DIR__ . '/../settings.json')
 * @return array Complete roadmap information including all components
 * @deprecated Use OpenCode `/plan` delegation instead. Will be removed in a future version.
 */
function generateCompleteRoadmap(string $kanbanContent, string $settingsPath = __DIR__ . '/../settings.json'): array
{
    // Create a RoadmapGenerator instance and use it to provide functionality
    $generator = new RoadmapGenerator($settingsPath);
    return $generator->generateCompleteRoadmap($kanbanContent);
}