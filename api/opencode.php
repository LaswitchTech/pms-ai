<?php

declare(strict_types=1);

/**
 * OpenCode API endpoint — availability and command execution.
 *
 * POST /api/opencode.php → { available: bool, message: string }
 * POST /api/opencode.php → { status: string, output: string, errors: string, commitHash: string } (when command is provided)
 */

require_once dirname(__DIR__) . '/lib/opencode.php';

/**
 * Send JSON response and exit.
 */
function opencodeApiRespond(array $payload, int $status = 200): never
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');
    echo json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    exit;
}

// Method check — POST only
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    opencodeApiRespond([
        'available' => false,
        'message'   => 'Only POST requests are supported.',
    ], 405);
}

// Check if the request contains command data (indicating it's a command execution)
$rawInput = file_get_contents('php://input');
$inputData = json_decode($rawInput, true);

// If we don't have a command payload, it's just a health check
if (!is_array($inputData) || !isset($inputData['command'])) {
    // Use the health probe helper from OpenCodeClient which reads settings.json
    $probe = OpenCodeClient::healthProbe();

    opencodeApiRespond($probe);
} else {
    // This is a command execution request - validate and execute command

    // Validate command
    $validCommands = ['/plan', '/next', '/debug', '/review', '/document'];
    $command = trim((string) ($inputData['command'] ?? ''));

    if (!in_array($command, $validCommands, true)) {
        opencodeApiRespond([
            'status' => 'error',
            'message'   => 'Command not supported. Must be one of: ' . implode(', ', $validCommands),
        ], 400);
    }

    // Get OpenCode settings for running the command
    require_once __DIR__ . '/../lib/settings.php';
    $settings = loadSettings(__DIR__ . '/../config/settings.json');

    if (empty($settings['opencode_enabled']) || !is_bool($settings['opencode_enabled'])) {
        opencodeApiRespond([
            'status' => 'error',
            'message'   => 'OpenCode is not enabled in settings.',
        ], 400);
    }

    $host = (string) ($settings['opencode_host'] ?? 'localhost');
    $port = (int) ($settings['opencode_port'] ?? 8080);
    $timeout = (int) ($settings['opencode_timeout'] ?? 60);

    // For command execution, we'll use a longer timeout for session creation + response
    $sessionTimeout = max(30, $timeout);
    $commandTimeout = max(120, $timeout * 3);

    // Step 1: Create an OpenCode session
    $sessionResult = OpenCodeClient::createSession($host, $port, $sessionTimeout);
    if (!empty($sessionResult['error'])) {
        opencodeApiRespond([
            'status' => 'error',
            'message'=> 'Failed to create OpenCode session: ' . $sessionResult['error'],
        ], 502);
    }

    // Step 2: Execute the command within the session using server-default agent and model
    $apiResponse = OpenCodeClient::executeCommandInSession(
        $host,
        $port,
        $commandTimeout,
        $sessionResult['id'],
        $command,
    );

    // Format response for client consumption
    $output = '';
    if (is_string($apiResponse['body'])) {
        $output = $apiResponse['body'];
    } elseif (is_array($apiResponse['body']) && isset($apiResponse['body']['output'])) {
        $output = $apiResponse['body']['output'];
    } elseif (is_array($apiResponse['body'])) {
        // In case where the response is just an array, output it as JSON
        $output = json_encode($apiResponse['body']);
    }

    // For slash commands OpenCode does not return commitHash; leave as null
    opencodeApiRespond([
        'status' => $apiResponse['status'],
        'output' => $output,
        'errors' => $apiResponse['error'] ?? null,
        'commitHash' => null,
    ]);
}
