<?php

/**
 * Load settings from JSON file or return default values.
 *
 * @param string $path Path to the settings.json file
 * @return array Settings data
 */
function loadSettings(string $path): array
{
    $defaultSettings = [
        'timezone' => null,
        'ollama_host' => 'localhost',
        'ollama_port' => 11434,
        'ollama_timeout' => 30,
        'ollama_context_window' => 4096,
        'ollama_model' => '',
        'legacy_ai_workflows_enabled' => false
    ];

    if (!file_exists($path) || !is_readable($path)) {
        return $defaultSettings;
    }

    $contents = file_get_contents($path);
    if ($contents === false) {
        return $defaultSettings;
    }

    $settings = json_decode($contents, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        return $defaultSettings;
    }

    // Merge with defaults to ensure all keys exist
    return array_merge($defaultSettings, $settings);
}

/**
 * Save settings to JSON file with proper validation.
 *
 * @param string $path Path to the settings.json file
 * @param array $settings Settings data to save
 * @return bool Success status
 */
function saveSettings(string $path, array $settings): bool
{
    // Validate port (integer 1-65535)
    if (isset($settings['ollama_port']) && 
        (!is_numeric($settings['ollama_port']) || 
         $settings['ollama_port'] < 1 || 
         $settings['ollama_port'] > 65535)) {
        return false;
    }

    // Validate timeout (min 5)
    if (isset($settings['ollama_timeout']) && 
        (!is_numeric($settings['ollama_timeout']) || 
         $settings['ollama_timeout'] < 5)) {
        return false;
    }

    // Validate context_window (min 256)
    if (isset($settings['ollama_context_window']) && 
        (!is_numeric($settings['ollama_context_window']) || 
         $settings['ollama_context_window'] < 256)) {
        return false;
    }

    // Ensure all required fields are present
    $defaultSettings = [
        'timezone' => null,
        'ollama_host' => 'localhost',
        'ollama_port' => 11434,
        'ollama_timeout' => 30,
        'ollama_context_window' => 4096,
        'ollama_model' => '',
        'legacy_ai_workflows_enabled' => false
    ];
    
    $settings = array_merge($defaultSettings, $settings);

    $json = json_encode($settings, JSON_PRETTY_PRINT);
    if ($json === false) {
        return false;
    }

    // Write to file with proper permissions
    $result = file_put_contents($path, $json);
    if ($result === false) {
        return false;
    }

    // Set proper permissions (0644)
    return chmod($path, 0644);
}