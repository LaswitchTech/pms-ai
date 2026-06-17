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

    // Validate project slug if provided
    $projectSlug = trim((string) ($inputData['project_slug'] ?? ''));
    if (!empty($projectSlug)) {
        // Load projects config from JSON
        $projectsConfig = json_decode(file_get_contents(__DIR__ . '/../config/projects.json'), true);
        if (empty($projectsConfig) || !isset($projectsConfig['projects'][$projectSlug])) {
            opencodeApiRespond([
                'status' => 'error',
                'message'   => 'Project not found: ' . $projectSlug,
            ], 404);
        }

        // Verify that the project directory actually exists
        $projectPath = $projectsConfig['projects'][$projectSlug]['path'] ?? '';
        if (empty($projectPath) || !is_dir($projectPath)) {
            opencodeApiRespond([
                'status' => 'error',
                'message'   => 'Project directory not found: ' . $projectSlug,
            ], 404);
        }
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

    // For command execution, we'll use a longer timeout
    $commandTimeout = max(60, $timeout * 2);

    // Send the command to OpenCode
    // Note: This uses synchronous mode and blocks until completion (as per Phase 2 design)
    $result = OpenCodeClient::sendCommand(
        $host,
        $port,
        $commandTimeout,
        $command,
        $projectSlug ? [$projectSlug] : []
    );

    // Format response for client consumption
    $output = '';
    if (is_string($result['body'])) {
        $output = $result['body'];
    } elseif (is_array($result['body']) && isset($result['body']['output'])) {
        $output = $result['body']['output'];
    } elseif (is_array($result['body'])) {
        // In case where the response is just an array, output it as JSON
        $output = json_encode($result['body']);
    }

    $commitHash = null;
    if (is_array($result['body']) && isset($result['body']['commitHash'])) {
        $commitHash = $result['body']['commitHash'];
    }

    opencodeApiRespond([
        'status' => $result['status'],
        'output'   => $output,
        'errors' => $result['error'] ?? null,
        'commitHash' => $commitHash,
    ]);
}
