<?php

/**
 * AI Prompts Registry - Centralized storage and management of AI prompts.
 * 
 * This class provides a registry for storing and retrieving AI prompts used by
 * the application's AI services. All hardcoded prompts in lib/ai.php and 
 * lib/roadmap.php will be extracted to this registry.
 */

class AIPromptsRegistry {
    private $configFile;
    private $prompts = [];
    private $defaults = [];

    /**
     * Constructor
     *
     * @param string $configFile Path to the prompts config file (default: __DIR__ . '/../config/ai_prompts.json')
     */
    public function __construct(string $configFile = __DIR__ . '/../config/ai_prompts.json') {
        $this->configFile = $configFile;
        
        // Load defaults from code
        $this->loadDefaults();
        
        // Load config file prompts if it exists
        $this->loadFromFile();
    }

    /**
     * Get prompt template by key
     *
     * @param string $key The prompt key
     * @return string|null Prompt template or null if not found
     */
    public function getPrompt(string $key): ?string {
        // Check if we have a custom version in config file
        if (isset($this->prompts[$key])) {
            return $this->prompts[$key]['template'];
        }
        
        // Return fallback default if available
        if (isset($this->defaults[$key])) {
            return $this->defaults[$key];
        }
        
        // No prompt found
        return null;
    }

    /**
     * Set prompt template by key (optionally with a specific model).
     *
     * @param string $key The prompt key
     * @param string $template The prompt template
     * @param string|null $model Optional Ollama model name for this prompt
     * @return bool Success status
     */
    public function setPrompt(string $key, string $template, ?string $model = null): bool {
        // Preserve existing model if not overridden
        $existingModel = null;
        if (isset($this->prompts[$key]) && isset($this->prompts[$key]['model'])) {
            $existingModel = $this->prompts[$key]['model'];
        }

        $this->prompts[$key] = [
            'key' => $key,
            'template' => $template,
            'version' => $this->getNewVersion($key),
            'updated_at' => date('c'),
            'model' => $model !== null ? $model : $existingModel
        ];

        // Save to file
        return $this->saveToFile();
    }

    /**
     * Get all prompts
     *
     * @return array All registered prompts
     */
    public function getAllPrompts(): array {
        // Merge defaults and config values, with config taking precedence
        $all = $this->defaults;
        foreach ($this->prompts as $key => $prompt) {
            $all[$key] = $prompt['template'];
        }
        return $all;
    }

    /**
     * Get all prompts with its metadata (version, updated_at, model).
     * Returns a flat array keyed by prompt key:
     *   [
     *     'task_decomposition' => ['key' => '...', 'template' => '...', 'version' => 1, 'updated_at' => '...', 'model' => '...'],
     *     ...
     *   ]
     */
    public function getAllPromptsWithMeta(): array {
        $merged = [];

        // Collect all keys in a deterministic order
        $allKeys = array_keys($this->defaults);
        foreach (array_keys($this->prompts) as $k) {
            if (!in_array($k, $allKeys, true)) {
                $allKeys[] = $k;
            }
        }

        foreach ($allKeys as $key) {
            if (isset($this->prompts[$key])) {
                // Custom version stored in config file
                $merged[$key] = [
                    'key' => $this->prompts[$key]['key'],
                    'template' => $this->prompts[$key]['template'],
                    'version' => $this->prompts[$key]['version'],
                    'updated_at' => $this->prompts[$key]['updated_at'],
                    'model' => $this->prompts[$key]['model'] ?? null
                ];
            } else {
                // Default — shows as-is from code, no user edits
                $merged[$key] = [
                    'key' => $key,
                    'template' => $this->defaults[$key],
                    'version' => 0,
                    'updated_at' => null,
                    'model' => null
                ];
            }
        }

        return $merged;
    }

    /**
     * Load defaults from code
     */
    private function loadDefaults(): void {
        $this->defaults = [
            // Task-related prompts (no specific model specified)
            'task_decomposition' => "Please decompose the following task into 3-5 logical subtasks:\n\n{task_description}\n\nFormat your response as a markdown list with subtasks numbered.",
            
            'task_subtasks' => "Generate 3-5 detailed subtasks for the following main task:\n\n{task_description}\n\nFormat each subtask with a brief description only, one per line.",
            
            'task_priority' => "Based on the following task description, please suggest a priority level from the options: high, medium, low\n\n{task_description}\n\nRespond with only the priority level (high/medium/low) and nothing else.",
            
            // Roadmap-related prompts (no specific model specified) 
            'roadmap_generation' => "Generate a ROADMAP.md file based on the following KANBAN.md content. Structure it in proper markdown with sections, and include release milestones and timeline information:\n\n### KANBAN.md Content:\n{kanban_content}\n\nGenerate a comprehensive roadmap that includes:\n1. Release milestones\n2. Timeline estimation\n3. Priority ordering of tasks\n4. Strategic planning considerations\n\nFormat properly with markdown headers and lists.",
            
            'release_milestones' => "Extract and organize the following KANBAN.md content into release milestones:\n\n{kanban_content}\n\nGenerate a list of release milestones with approximate dates, based on task dependencies and complexity. Format as a markdown list.",
            
            'project_timeline' => "Based on the following KANBAN.md content, generate a project timeline with key dates:\n\n{kanban_content}\n\nCreate a timeline that shows planned completion dates for major features or groups of tasks. Format it as a markdown timeline."
        ];
    }

    /**
     * Load prompts from config file
     */
    private function loadFromFile(): void {
        if (!file_exists($this->configFile)) {
            return;
        }
        
        $content = file_get_contents($this->configFile);
        if ($content === false) {
            return;
        }
        
        $data = json_decode($content, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            // Log error or handle gracefully - not critical
            return;
        }
        
        // Process each prompt in the config file
        foreach ($data as $promptData) {
            if (isset($promptData['key']) && isset($promptData['template'])) {
                $this->prompts[$promptData['key']] = [
                    'key' => $promptData['key'],
                    'template' => $promptData['template'],
                    'version' => $promptData['version'] ?? 1,
                    'updated_at' => $promptData['updated_at'] ?? date('c'),
                    'model' => $promptData['model'] ?? null
                ];
            }
        }
    }

    /**
     * Save prompts to config file 
     */
    private function saveToFile(): bool {
        // Convert prompts array to the expected format
        $saveData = [];
        foreach ($this->prompts as $key => $prompt) {
            $saveData[] = [
                'key' => $key,
                'template' => $prompt['template'],
                'version' => $prompt['version'],
                'updated_at' => $prompt['updated_at'],
                'model' => $prompt['model'] ?? null
            ];
        }
        
        $dir = dirname($this->configFile);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
        
        return file_put_contents($this->configFile, json_encode($saveData, JSON_PRETTY_PRINT)) !== false;
    }

    /**
     * Get next version number for a prompt
     */
    private function getNewVersion(string $key): int {
        if (isset($this->prompts[$key]['version'])) {
            return $this->prompts[$key]['version'] + 1;
        }
        return 1;
    }
}

/**
 * Helper function to get the singleton instance of the AI Prompts Registry
 */
function getAIPromptsRegistry(): AIPromptsRegistry {
    static $registry = null;
    if ($registry === null) {
        $registry = new AIPromptsRegistry();
    }
    return $registry;
}

/**
 * Get a prompt template by key (helper function)
 *
 * @param string $key The prompt key
 * @return string|null Prompt template or null if not found
 */
function getPrompt(string $key): ?string {
    return getAIPromptsRegistry()->getPrompt($key);
}

/**
 * Set a prompt template by key (helper function)
 *
 * @param string $key The prompt key
 * @param string $template The prompt template  
 * @param string|null $model Optional Ollama model name for this prompt
 * @return bool Success status
 */
function setPrompt(string $key, string $template, ?string $model = null): bool {
    return getAIPromptsRegistry()->setPrompt($key, $template, $model);
}

/**
 * Get all prompts (helper function)
 *
 * @return array All registered prompts
 */
function getAllPrompts(): array {
    return getAIPromptsRegistry()->getAllPrompts();
}