<?php

declare(strict_types=1);

function ollamaConfig(): array
{
    return [
        'host' => getenv('OLLAMA_HOST') ?: 'http://localhost:11434',
        'model' => getenv('OLLAMA_MODEL') ?: 'qwen3-coder:30b-128k',
        'timeout' => (int) (getenv('OLLAMA_TIMEOUT') ?: 120),
        'context' => (int) (getenv('OLLAMA_CONTEXT') ?: 128000),
    ];
}

function ollamaPrompt(string $prompt, array $options = []): array
{
    $config = array_merge(ollamaConfig(), $options);

    $payload = [
        'model' => $config['model'],
        'prompt' => $prompt,
        'stream' => false,
        'options' => [
            'num_ctx' => $config['context'],
        ],
    ];

    return ollamaRequest('/api/generate', $payload, $config);
}

function ollamaRequest(string $endpoint, array $payload, array $config): array
{
    $url = rtrim((string) $config['host'], '/') . $endpoint;

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
