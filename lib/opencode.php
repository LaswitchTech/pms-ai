<?php

/**
 * OpenCodeClient — Standalone client wrapper for OpenCode server integration.
 *
 * Provides health detection and command execution via HTTP cURL requests.
 * No view or API context dependencies (Shared Library Pattern).
 */
final class OpenCodeClient
{
    /**
     * Check if the OpenCode server is available at the given host/port.
     *
     * @param string $host Hostname or IP address
     * @param int $port TCP port number
     * @param int $timeout Timeout in seconds
     * @return array{available: bool, message: string}
     */
    public static function isAvailable(string $host, int $port, int $timeout = 5): array
    {
        $url = sprintf('http://%s:%d/global/health', rtrim($host, '/'), $port);

        $curl = curl_init($url);
        if ($curl === false) {
            return [
                'available' => false,
                'message'   => 'Failed to initialize cURL.',
            ];
        }

        curl_setopt_array($curl, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => $timeout,
            CURLOPT_CONNECTTIMEOUT => min(3, $timeout),
            CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
        ]);

        $response = curl_exec($curl);
        $error    = curl_error($curl);
        $httpCode = (int) curl_getinfo($curl, CURLINFO_RESPONSE_CODE);

        curl_close($curl);

        if ($response === false || !empty($error)) {
            return [
                'available' => false,
                'message'   => $error ?: 'Request failed.',
            ];
        }

        if ($httpCode >= 200 && $httpCode < 300) {
            return [
                'available' => true,
                'message'   => 'OpenCode is reachable at ' . $url,
            ];
        }

        return [
            'available' => false,
            'message'   => sprintf('OpenCode returned HTTP %d at %s', $httpCode, $url),
        ];
    }

    /**
     * Send a command to the OpenCode server and return a structured response.
     *
     * @param string $host Hostname or IP address
     * @param int $port TCP port number
     * @param int $timeout Timeout in seconds
     * @param string $command Command name (e.g. "plan", "next", "debug")
     * @param list<string> $arguments Optional command arguments
     * @return array{status: string, body: mixed|null, error: string|null}
     */
    public static function sendCommand(
        string $host,
        int $port,
        int $timeout,
        string $command,
        array $arguments = [],
    ): array {
        $url = sprintf('http://%s:%d/cmd/%s', rtrim($host, '/'), $port, trim($command, '/'));

        $payload = [
            'command' => $command,
        ];

        if (!empty($arguments)) {
            $payload['args'] = array_values($arguments);
        }

        $curl = curl_init($url);
        if ($curl === false) {
            return [
                'status' => 'error',
                'body'   => null,
                'error'  => 'Failed to initialize cURL.',
            ];
        }

        curl_setopt_array($curl, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_TIMEOUT        => $timeout,
            CURLOPT_CONNECTTIMEOUT => min(3, $timeout),
            CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
            CURLOPT_POSTFIELDS     => json_encode($payload, JSON_UNESCAPED_SLASHES),
        ]);

        $response = curl_exec($curl);
        $error    = curl_error($curl);
        $httpCode = (int) curl_getinfo($curl, CURLINFO_RESPONSE_CODE);

        curl_close($curl);

        if ($response === false || !empty($error)) {
            return [
                'status' => 'error',
                'body'   => null,
                'error'  => $error ?: 'Command request failed.',
            ];
        }

        if ($httpCode < 200 || $httpCode >= 300) {
            return [
                'status' => 'error',
                'body'   => null,
                'error'  => sprintf('Server returned HTTP %d for command "%s"', $httpCode, $command),
            ];
        }

        $decoded = json_decode((string) $response, true);

        if (!is_array($decoded)) {
            return [
                'status' => 'error',
                'body'   => null,
                'error'  => 'Invalid JSON response from OpenCode server.',
            ];
        }

        return [
            'status' => $decoded['status'] ?? 'ok',
            'body'   => $decoded['body'] ?? ($decoded['data'] ?? $response),
            'error'  => null,
        ];
    }

    /**
     * Create an OpenCode session for command execution.
     *
     * @param string $host Hostname or IP address
     * @param int $port TCP port number
     * @param int $timeout Timeout in seconds
     * @return array{id: string, slug: string, error: string|null}
     */
    public static function createSession(string $host, int $port, int $timeout = 15): array
    {
        $url = sprintf('http://%s:%d/session', rtrim($host, '/'), $port);

        $curl = curl_init($url);
        if ($curl === false) {
            return [
                'id'    => '',
                'slug'  => '',
                'error' => 'Failed to initialize cURL.',
            ];
        }

        curl_setopt_array($curl, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_TIMEOUT        => $timeout,
            CURLOPT_CONNECTTIMEOUT => min(3, $timeout),
            CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
            CURLOPT_POSTFIELDS     => json_encode([]),
        ]);

        $response = curl_exec($curl);
        $error    = curl_error($curl);
        $httpCode = (int) curl_getinfo($curl, CURLINFO_RESPONSE_CODE);

        curl_close($curl);

        if ($response === false || !empty($error)) {
            return [
                'id'    => '',
                'slug'  => '',
                'error' => $error ?: 'Session creation failed.',
            ];
        }

        if ($httpCode < 200 || $httpCode >= 300) {
            return [
                'id'    => '',
                'slug'  => '',
                'error' => sprintf('Server returned HTTP %d during session creation.', $httpCode),
            ];
        }

        $decoded = json_decode((string) $response, true);

        if (!is_array($decoded) || empty($decoded['id'])) {
            return [
                'id'    => '',
                'slug'  => '',
                'error' => 'Invalid JSON response from OpenCode server during session creation.',
            ];
        }

        return [
            'id'   => (string) $decoded['id'],
            'slug' => (string) ($decoded['slug'] ?? ''),
            'error'=> null,
        ];
    }

    /**
     * Execute a command within an OpenCode session.
     *
     * Uses the correct session-based workflow:
     *   POST /session → POST /session/{id}/command
     *
     * @param string $host Hostname or IP address
     * @param int $port TCP port number
     * @param int $timeout Timeout in seconds
     * @param string $sessionId The session ID returned from createSession()
     * @param string $command Command name (e.g. "/plan", "/next")
     * @param string $agent Agent identifier (required by OpenCode 1.x+)
     * @param string $model Model identifier (required by OpenCode 1.x+)
     * @return array{status: string, body: mixed|null, error: string|null}
     */
    public static function executeCommandInSession(
        string $host,
        int $port,
        int $timeout,
        string $sessionId,
        string $command,
        string $agent = 'agent',
        string $model = 'default'
    ): array {
        $url = sprintf('http://%s:%d/session/%s/command', rtrim($host, '/'), $port, urlencode($sessionId));

        // Build the arguments field as a plain string (OpenCode requirement)
        $commandArg = $command[0] === '/' ? $command : '/' . trim($command, '/');

        $payload = [
            'agent'     => $agent,
            'model'     => $model,
            'arguments' => $commandArg,
        ];

        $curl = curl_init($url);
        if ($curl === false) {
            return [
                'status' => 'error',
                'body'   => null,
                'error'  => 'Failed to initialize cURL.',
            ];
        }

        curl_setopt_array($curl, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_TIMEOUT        => $timeout,
            CURLOPT_CONNECTTIMEOUT => min(3, $timeout),
            CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
            CURLOPT_POSTFIELDS     => json_encode($payload, JSON_UNESCAPED_SLASHES),
        ]);

        $response = curl_exec($curl);
        $error    = curl_error($curl);
        $httpCode = (int) curl_getinfo($curl, CURLINFO_RESPONSE_CODE);

        curl_close($curl);

        if ($response === false || !empty($error)) {
            return [
                'status' => 'error',
                'body'   => null,
                'error'  => $error ?: 'Command execution failed.',
            ];
        }

        if ($httpCode < 200 || $httpCode >= 300) {
            return [
                'status' => 'error',
                'body'   => null,
                'error'  => sprintf('Server returned HTTP %d for command "%s" in session.', $httpCode, $command),
            ];
        }

        $decoded = json_decode((string) $response, true);

        if (!is_array($decoded)) {
            return [
                'status' => 'error',
                'body'   => null,
                'error'  => 'Invalid JSON response from OpenCode server.',
            ];
        }

        return [
            'status' => $decoded['status'] ?? 'ok',
            'body'   => $decoded['body'] ?? ($decoded['data'] ?? $response),
            'error'  => null,
        ];
    }

    /**
     * Load OpenCode configuration from settings.json and run a health probe.
     *
     * @param string|null $settingsPath Override default settings path
     * @return array{available: bool, message: string}
     */
    public static function healthProbe(?string $settingsPath = null): array
    {
        require_once __DIR__ . '/settings.php';

        if ($settingsPath === null) {
            $settingsPath = __DIR__ . '/../config/settings.json';
        }

        if (!file_exists($settingsPath)) {
            return [
                'available' => false,
                'message'   => 'Settings file not found.',
            ];
        }

        $settings = loadSettings($settingsPath);

        if (empty($settings['opencode_enabled']) || !is_bool($settings['opencode_enabled'])) {
            return [
                'available' => false,
                'message'   => 'OpenCode is not enabled in settings.',
            ];
        }

        $host = (string) ($settings['opencode_host'] ?? 'localhost');
        $port = (int) ($settings['opencode_port'] ?? 8080);
        $timeout = (int) ($settings['opencode_timeout'] ?? 60);

        return self::isAvailable($host, $port, $timeout);
    }

    /**
     * Start an asynchronous command execution and return a task ID.
     *
     * This method is for future async implementation. For now, it implements
     * synchronous behavior to maintain backward compatibility with Phase 2.1.
     *
     * @param string $host Hostname or IP address
     * @param int $port TCP port number
     * @param int $timeout Timeout in seconds
     * @param string $command Command name (e.g. "plan", "next", "debug")
     * @param list<string> $arguments Optional command arguments
     * @return array{id: string, status: string}
     */
    public static function startCommand(
        string $host,
        int $port,
        int $timeout,
        string $command,
        array $arguments = []
    ): array {
        // For Phase 2, we keep this as synchronous but still return a "task" structure
        // to support future async integration
        $result = self::sendCommand($host, $port, $timeout, $command, $arguments);

        // Return a task-like structure, even for synchronous execution
        // This prepares the interface for async implementations while maintaining backward compatibility
        return [
            'id' => md5($command . serialize($arguments) . time()), // Simple unique ID generation
            'status' => $result['status']
        ];
    }

    /**
     * Poll for command status and results using a task ID.
     *
     * This method is intended for async execution patterns and returns
     * progress information if the server supports it. For Phase 2,
     * this will return completed status immediately.
     *
     * @param string $host Hostname or IP address
     * @param int $port TCP port number
     * @param string $taskId The task ID returned from startCommand
     * @return array{status: string, output: mixed|null, errors: string|null, isComplete: bool}
     */
    public static function pollCommand(string $host, int $port, string $taskId): array
    {
        // In Phase 2 with synchronous implementation, we just return completed status
        // This is for future async support - we could implement actual polling logic here if needed

        return [
            'status' => 'completed',
            'output' => null,
            'errors' => null,
            'isComplete' => true
        ];
    }
}
