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
        $url = sprintf('http://%s:%d/health', rtrim($host, '/'), $port);

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
}
