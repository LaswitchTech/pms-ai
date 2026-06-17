<?php

declare(strict_types=1);

/**
 * OpenCode API endpoint — availability probe.
 *
 * POST /api/opencode.php → { available: bool, message: string }
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

// Use the health probe helper from OpenCodeClient which reads settings.json
$probe = OpenCodeClient::healthProbe();

opencodeApiRespond($probe);
