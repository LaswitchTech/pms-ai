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
        'opencode_enabled' => false,
        'opencode_host' => 'localhost',
        'opencode_port' => 8080,
        'opencode_timeout' => 60,
        'opencode_executable' => null,
        'opencode_username' => null,
        'opencode_password' => null,
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

    // Ensure all required fields are present; saveSettings intentionally omits
    // opencode_enabled here so that future saves do not re-inject the key.
    // loadSettings() still provides it as a backward-compat default for existing
    // configs that have the key present.
    $defaultSettings = [
        'timezone' => null,
        'ollama_host' => 'localhost',
        'ollama_port' => 11434,
        'ollama_timeout' => 30,
        'ollama_context_window' => 4096,
        'ollama_model' => '',
        // NOTE: legacy_ai_workflows_enabled was removed from saveSettings().
        // It has no consumers and was deprecated in favor of OpenCode.
        'opencode_host' => 'localhost',
        'opencode_port' => 8080,
        'opencode_timeout' => 60,
        'opencode_executable' => null,
        'opencode_username' => null,
        'opencode_password' => null,
    ];

    // Validate opencode_host (valid hostname or IP)
    if (($settings['opencode_host'] ?? null) !== null && ($settings['opencode_host'] ?? '') !== '') {
        $host = (string) $settings['opencode_host'];
        // Accept valid IP addresses OR hostnames (alphanumeric, dots, dashes).
        // FILTER_VALIDATE_URL requires a scheme (http://...) so plain hostnames like "localhost" fail.
        $isIp = filter_var($host, FILTER_VALIDATE_IP);
        $isHostname = preg_match('/^[a-zA-Z0-9]([a-zA-Z0-9\-]*[a-zA-Z0-9])?(\.[a-zA-Z0-9]([a-zA-Z0-9\-]*[a-zA-Z0-9])?)*$/', $host) === 1;
        if (!$isIp && !$isHostname) {
            return false;
        }
    }

    // Validate opencode_port (integer 1–65535)
    if (isset($settings['opencode_port']) && (!is_numeric($settings['opencode_port']) || (int) $settings['opencode_port'] < 1 || (int) $settings['opencode_port'] > 65535)) {
        return false;
    }

    // Validate opencode_timeout (positive integer, min 1 second)
    if (isset($settings['opencode_timeout']) && (!is_numeric($settings['opencode_timeout']) || (int) $settings['opencode_timeout'] < 1)) {
        return false;
    }

    // opencode_executable is optional string | null — no extra validation needed beyond presence check

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