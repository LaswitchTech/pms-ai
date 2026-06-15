<?php

/**
 * Get Ollama connection parameters from settings.
 *
 * @param string $settingsPath Path to the settings.json file (default: __DIR__ . '/../settings.json')
 * @return array Connection configuration
 */
function getOllamaConnection(string $settingsPath = __DIR__ . '/../settings.json'): array
{
    require_once __DIR__ . '/settings.php';
    
    // Load settings from file
    $settings = loadSettings($settingsPath);
    
    return [
        'host' => $settings['ollama_host'] ?? 'localhost',
        'port' => $settings['ollama_port'] ?? 11434,
        'timeout' => $settings['ollama_timeout'] ?? 30,
        'context_window' => $settings['ollama_context_window'] ?? 4096,
        'model' => $settings['ollama_model'] ?? '',
    ];
}

/**
 * Make a request to Ollama using settings from the configuration file.
 *
 * @param string $endpoint The API endpoint
 * @param array $payload Request payload
 * @param string $settingsPath Path to settings.json (default: __DIR__ . '/../settings.json')
 * @return array Response data
 */
function ollamaRequest(string $endpoint, array $payload, string $settingsPath = __DIR__ . '/../settings.json'): array
{
    $config = getOllamaConnection($settingsPath);
    
    $url = 'http://' . $config['host'] . ':' . $config['port'] . $endpoint;
    
    $curl = curl_init($url);
    
    if ($curl === false) {
        return [
            'success' => false,
            'error' => 'Unable to initialize cURL.',
        ];
    }
    
    curl_setopt_array($curl, [
        CURLOPT_POST => true,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
        CURLOPT_POSTFIELDS => json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
        CURLOPT_TIMEOUT => (int) $config['timeout'],
    ]);
    
    $response = curl_exec($curl);
    $error = curl_error($curl);
    $status = (int) curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
    
    curl_close($curl);
    
    if ($response === false) {
        return [
            'success' => false,
            'error' => $error ?: 'Ollama request failed.',
        ];
    }
    
    $decoded = json_decode((string) $response, true);
    
    if (!is_array($decoded)) {
        return [
            'success' => false,
            'error' => 'Invalid JSON response from Ollama.',
            'status' => $status,
            'raw' => $response,
        ];
    }
    
    return [
        'success' => $status >= 200 && $status < 300,
        'status' => $status,
        'response' => $decoded['response'] ?? null,
        'data' => $decoded,
    ];
}

/**
 * Generate a prompt using Ollama with settings from configuration.
 *
 * @param string $prompt The prompt to send
 * @param array $options Additional options
 * @param string $settingsPath Path to settings.json (default: __DIR__ . '/../settings.json')
 * @return array Response data
 */
function ollamaPrompt(string $prompt, array $options = [], string $settingsPath = __DIR__ . '/../settings.json'): array
{
    $config = getOllamaConnection($settingsPath);
    
    // Merge default config with options
    $mergedOptions = array_merge($config, $options);
    
    $payload = [
        'model' => $mergedOptions['model'],
        'prompt' => $prompt,
        'stream' => false,
        'options' => [
            'num_ctx' => $mergedOptions['context_window'],
        ],
    ];
    
    return ollamaRequest('/api/generate', $payload, $settingsPath);
}