<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/lib/kanban.php';

function kanbanApiRespond(array $payload, int $status = 200): never
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');

    echo json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    exit;
}

function kanbanApiMissingRequiredFunctions(): array
{
    $requiredFunctions = [
        'handleKanbanAction',
        'enrichKanbanActionResult',
        'kanbanBoardState',
        'kanbanFileForProjectPath',
        'serializableKanbanColumns',
        'taskAtKanbanPath',
        'taskMarkdown',
        'taskForDataAttribute',
        'loadKanbanBoard',
        'normalizeColumnSettings',
        'normalizeTask',
        'normalizeTaskTags',
        'parseTaskTagsLine',
        'parsePath',
        'saveKanban',
    ];

    return array_values(array_filter($requiredFunctions, static function (string $function): bool {
        return !function_exists($function);
    }));
}

function kanbanApiRequiredFunctionsAvailable(): bool
{
    return kanbanApiMissingRequiredFunctions() === [];
}

function kanbanApiRequestData(): array
{
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        kanbanApiRespond([
            'success' => false,
            'message' => 'Only POST requests are supported.',
        ], 405);
    }

    $contentType = strtolower((string) ($_SERVER['CONTENT_TYPE'] ?? ''));

    if (str_contains($contentType, 'application/json')) {
        $decoded = json_decode((string) file_get_contents('php://input'), true);

        if (!is_array($decoded)) {
            kanbanApiRespond([
                'success' => false,
                'message' => 'Invalid JSON request body.',
            ], 400);
        }

        return $decoded;
    }

    return $_POST;
}

function kanbanApiProjectRoot(): string
{
    return dirname(__DIR__);
}

function kanbanApiProjectsConfigFile(): string
{
    return kanbanApiProjectRoot() . '/config/projects.json';
}

function kanbanApiReadProjectsConfig(): array
{
    $configFile = kanbanApiProjectsConfigFile();

    if (!is_file($configFile)) {
        return [
            'active_project' => null,
            'projects' => [],
        ];
    }

    $decoded = json_decode((string) file_get_contents($configFile), true);

    if (!is_array($decoded)) {
        return [
            'active_project' => null,
            'projects' => [],
        ];
    }

    return [
        'active_project' => $decoded['active_project'] ?? null,
        'projects' => is_array($decoded['projects'] ?? null) ? $decoded['projects'] : [],
    ];
}

function kanbanApiResolveProjectPath(array $request): string
{
    $slug = trim((string) ($request['project_slug'] ?? ''));

    $config = kanbanApiReadProjectsConfig();
    $projects = is_array($config['projects'] ?? null) ? $config['projects'] : [];

    if ($slug === '') {
        return kanbanApiProjectRoot();
    }

    if (!isset($projects[$slug]) || !is_array($projects[$slug])) {
        kanbanApiRespond([
            'success' => false,
            'message' => 'Project does not exist: ' . $slug,
        ], 404);
    }

    if (!empty($projects[$slug]['missing'])) {
        kanbanApiRespond([
            'success' => false,
            'message' => 'Project folder is missing: ' . $slug,
        ], 404);
    }

    $path = realpath((string) ($projects[$slug]['path'] ?? ''));

    if ($path === false || !is_dir($path)) {
        kanbanApiRespond([
            'success' => false,
            'message' => 'Project path is invalid: ' . $slug,
        ], 404);
    }

    return $path;
}


$missingRequiredFunctions = kanbanApiMissingRequiredFunctions();

if ($missingRequiredFunctions !== []) {
    kanbanApiRespond([
        'success' => false,
        'message' => 'Kanban API is not ready yet. Missing required helpers: ' . implode(', ', $missingRequiredFunctions),
        'missing_helpers' => true,
        'missing_functions' => $missingRequiredFunctions,
    ], 501);
}

$request = kanbanApiRequestData();
$projectPath = kanbanApiResolveProjectPath($request);
$kanbanFile = kanbanFileForProjectPath($projectPath);
$board = loadKanbanBoard($kanbanFile);

$columns = $board['columns'];
$archiveTasks = $board['archive_tasks'];
$columnSettings = $board['column_settings'];

if ($board['create_error'] !== null) {
    kanbanApiRespond([
        'success' => false,
        'message' => $board['create_error'],
    ], 500);
}

$requestedAction = trim((string) ($request['action'] ?? ''));

// Support both 'copy_markdown' and 'copy_task_markdown' actions.
// Task titles, descriptions, tags, metadata, and children are serialized by shared helpers in lib.php.

if (in_array($requestedAction, ['copy_markdown', 'copy_task_markdown'], true)) {
    $columnName = trim((string) ($request['column'] ?? ''));
    $path = parsePath(trim((string) ($request['path'] ?? '')));
    $task = taskAtKanbanPath($columns, $columnName, $path);

    if ($task === null) {
        kanbanApiRespond([
            'success' => false,
            'action' => $requestedAction,
            'message' => 'Task could not be found.',
            'saved' => false,
            'data' => [
                'column' => $columnName,
                'path' => implode('.', array_map(static fn (int $index): string => (string) $index, $path)),
            ],
            'board' => kanbanBoardState($columns, $archiveTasks, $columnSettings),
        ], 422);
    }

    kanbanApiRespond([
        'success' => true,
        'action' => $requestedAction,
        'message' => 'Task markdown generated.',
        'saved' => false,
        'data' => [
            'column' => $columnName,
            'path' => implode('.', array_map(static fn (int $index): string => (string) $index, $path)),
            'task' => $task,
            'markdown' => taskMarkdown($task),
        ],
        'board' => kanbanBoardState($columns, $archiveTasks, $columnSettings),
    ]);
}
$restoreArchiveIndex = max(0, (int) ($request['archive_index'] ?? 0));
$restoreSourceTask = is_array($archiveTasks[$restoreArchiveIndex] ?? null)
    ? normalizeTask($archiveTasks[$restoreArchiveIndex])
    : null;
$result = handleKanbanAction($columns, $archiveTasks, $columnSettings, $request);

enrichKanbanActionResult($result, $request, $columns, $archiveTasks, $columnSettings, $restoreSourceTask);

if (($result['success'] ?? false) === true) {
    saveKanban($kanbanFile, $columns, $archiveTasks, $columnSettings);
}

$status = ($result['success'] ?? false) === true ? 200 : 422;

if (($result['action'] ?? '') === '') {
    $status = 400;
}

kanbanApiRespond([
    'success' => (bool) ($result['success'] ?? false),
    'action' => $result['action'] ?? null,
    'message' => $result['message'] ?? '',
    'saved' => ($result['success'] ?? false) === true,
    'data' => $result['data'] ?? [],
    'board' => kanbanBoardState($columns, $archiveTasks, $columnSettings),
], $status);
