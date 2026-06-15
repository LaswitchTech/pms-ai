<?php

declare(strict_types=1);

function validTaskControls(): array
{
    return ['archive', 'copy'];
}

function normalizeColumnSettings(?array $settings): array
{
    $settings = is_array($settings) ? $settings : [];
    $hiddenControls = is_array($settings['hide'] ?? null) ? $settings['hide'] : [];
    $hiddenControls = array_values(array_unique(array_filter(array_map(static function ($control): string {
        return strtolower(trim((string) $control));
    }, $hiddenControls), static function (string $control): bool {
        return in_array($control, validTaskControls(), true);
    })));

    return [
        'hide' => $hiddenControls,
    ];
}

function parseColumnHeading(string $heading): array
{
    $settings = [
        'hide' => [],
    ];

    $pattern = '/\s*<!--\s*(.*?)\s*-->\s*$/';

    if (preg_match($pattern, $heading, $matches) === 1) {
        $heading = trim((string) preg_replace($pattern, '', $heading));
        $rawMetadata = trim((string) ($matches[1] ?? ''));

        if (preg_match('/(?:^|\s)hide:\s*([^;]+)/', $rawMetadata, $hideMatches) === 1) {
            $settings['hide'] = preg_split('/\s*,\s*/', trim((string) $hideMatches[1])) ?: [];
        }
    }

    return [
        'name' => trim($heading),
        'settings' => normalizeColumnSettings($settings),
    ];
}

function formatColumnMetadata(array $settings): string
{
    $settings = normalizeColumnSettings($settings);

    if ($settings['hide'] === []) {
        return '';
    }

    return ' <!-- hide: ' . implode(',', $settings['hide']) . ' -->';
}

function columnControlHidden(array $columnSettings, string $columnName, string $control): bool
{
    $settings = normalizeColumnSettings($columnSettings[$columnName] ?? []);

    return in_array($control, $settings['hide'], true);
}

function columnControlClass(array $columnSettings, string $columnName): string
{
    $settings = normalizeColumnSettings($columnSettings[$columnName] ?? []);
    $classes = [];

    foreach ($settings['hide'] as $control) {
        $classes[] = 'hide-' . $control;
    }

    return implode(' ', $classes);
}

function toggleColumnControl(array &$columnSettings, string $columnName, string $control): bool
{
    $control = strtolower(trim($control));

    if ($columnName === '' || !in_array($control, validTaskControls(), true)) {
        return false;
    }

    $settings = normalizeColumnSettings($columnSettings[$columnName] ?? []);

    if (in_array($control, $settings['hide'], true)) {
        $settings['hide'] = array_values(array_diff($settings['hide'], [$control]));
    } else {
        $settings['hide'][] = $control;
    }

    $columnSettings[$columnName] = normalizeColumnSettings($settings);

    return true;
}

function parseTaskLine(string $line): ?array
{
    if (preg_match('/^(\s*)-\s+\[( |x|X)\]\s+(.+)$/', $line, $matches) === 1) {
        $done = strtolower($matches[2]) === 'x';
        $parsed = parseTaskMetadata(trim($matches[3]));

        return [
            'level' => intdiv(strlen(str_replace("\t", '  ', $matches[1])), 2),
            'done' => $done,
            'title' => $parsed['title'],
            'description' => '',
            'tags' => [],
            'type' => 'task',
            'created_at' => $parsed['metadata']['created_at'],
            'completed_at' => $done ? $parsed['metadata']['completed_at'] : null,
            'due_at' => $parsed['metadata']['due_at'],
            'archived_at' => $parsed['metadata']['archived_at'],
            'archived_from' => $parsed['metadata']['archived_from'],
            'priority' => $parsed['metadata']['priority'],
            'children' => [],
        ];
    }

    return null;
}

function parseTaskDescriptionLine(string $line): ?array
{
    if (preg_match('/^(\s*)-\s+(?!\[(?: |x|X)\]\s+)(.+)$/', $line, $matches) === 1) {
        return [
            'level' => intdiv(strlen(str_replace("\t", '  ', $matches[1])), 2),
            'description' => trim((string) $matches[2]),
        ];
    }

    if (preg_match('/^(\s+)([^\s].*)$/', $line, $matches) === 1) {
        return [
            'level' => intdiv(strlen(str_replace("\t", '  ', $matches[1])), 2),
            'description' => trim((string) $matches[2]),
        ];
    }

    return null;
}

function appendTaskDescription(array &$tasks, string $description, int $level): bool
{
    $description = trim($description);

    if ($description === '' || $tasks === []) {
        return false;
    }

    $lastIndex = array_key_last($tasks);

    if ($lastIndex === null || !isset($tasks[$lastIndex]) || !is_array($tasks[$lastIndex])) {
        return false;
    }

    if ($level <= 1) {
        $existingDescription = trim((string) ($tasks[$lastIndex]['description'] ?? ''));
        $tasks[$lastIndex]['description'] = $existingDescription === ''
            ? $description
            : $existingDescription . "\n" . $description;

        return true;
    }

    if (!isset($tasks[$lastIndex]['children']) || !is_array($tasks[$lastIndex]['children'])) {
        return false;
    }

    return appendTaskDescription($tasks[$lastIndex]['children'], $description, $level - 1);
}

function appendTaskTags(array &$tasks, array $tags, int $level): bool
{
    $tags = normalizeTaskTags($tags);

    if ($tags === [] || $tasks === []) {
        return false;
    }

    $lastIndex = array_key_last($tasks);

    if ($lastIndex === null || !isset($tasks[$lastIndex]) || !is_array($tasks[$lastIndex])) {
        return false;
    }

    if ($level <= 1) {
        $existingTags = normalizeTaskTags($tasks[$lastIndex]['tags'] ?? []);
        $tasks[$lastIndex]['tags'] = normalizeTaskTags(array_merge($existingTags, $tags));

        return true;
    }

    if (!isset($tasks[$lastIndex]['children']) || !is_array($tasks[$lastIndex]['children'])) {
        return false;
    }

    return appendTaskTags($tasks[$lastIndex]['children'], $tags, $level - 1);
}

function appendNestedTask(array &$tasks, array $task, int $level): void
{
    unset($task['level']);
    $task = normalizeTask($task);

    if ($level <= 0 || $tasks === []) {
        $tasks[] = $task;
        return;
    }

    $lastIndex = array_key_last($tasks);

    if (!isset($tasks[$lastIndex]['children']) || !is_array($tasks[$lastIndex]['children'])) {
        $tasks[$lastIndex]['children'] = [];
    }

    appendNestedTask($tasks[$lastIndex]['children'], $task, $level - 1);
}

function kanbanFileForProjectPath(string $projectPath): string
{
    return rtrim($projectPath, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'KANBAN.md';
}

function ensureKanbanFile(string $kanbanFile): ?string
{
    if (is_file($kanbanFile)) {
        return null;
    }

    $defaultKanban = "# Kanban\n\n## Todo\n\n## In Progress\n\n## Done\n";

    if (@file_put_contents($kanbanFile, $defaultKanban) === false) {
        return 'Unable to create KANBAN.md. Check folder permissions.';
    }

    return null;
}

function loadKanbanBoard(string $kanbanFile): array
{
    $createError = ensureKanbanFile($kanbanFile);

    $fileExists = is_file($kanbanFile);
    $parsedColumns = $fileExists ? parseKanban((string) file_get_contents($kanbanFile)) : [];
    [$columns, $archiveTasks] = splitKanbanArchive($parsedColumns);

    $columnSettings = is_array($GLOBALS['kanbanColumnSettings'] ?? null)
        ? $GLOBALS['kanbanColumnSettings']
        : [];

    foreach (array_keys($columns) as $columnName) {
        $columnSettings[$columnName] = normalizeColumnSettings($columnSettings[$columnName] ?? []);
    }

    $allTasks = [];

    foreach ($columns as $columnTasks) {
        $allTasks = array_merge($allTasks, $columnTasks);
    }

    [$totalTasks, $completedTasks] = countTasks($allTasks);

    return [
        'columns' => $columns,
        'archive_tasks' => $archiveTasks,
        'column_settings' => $columnSettings,
        'total_tasks' => $totalTasks,
        'completed_tasks' => $completedTasks,
        'create_error' => $createError,
    ];
}

function kanbanBoardState(array $columns, array $archiveTasks, array $columnSettings): array
{
    $allTasks = [];
    $columnStates = [];

    foreach ($columns as $columnName => $tasks) {
        $tasks = is_array($tasks) ? $tasks : [];
        $allTasks = array_merge($allTasks, $tasks);
        $columnStates[] = [
            'name' => (string) $columnName,
            'task_count' => countColumnTasks($tasks),
            'settings' => normalizeColumnSettings($columnSettings[$columnName] ?? []),
            'controls' => [
                'archive_hidden' => columnControlHidden($columnSettings, (string) $columnName, 'archive'),
                'copy_hidden' => columnControlHidden($columnSettings, (string) $columnName, 'copy'),
            ],
        ];
    }

    [$totalTasks, $completedTasks] = countTasks($allTasks);

    return [
        'columns' => $columnStates,
        'column_order' => array_keys($columns),
        'task_count' => $totalTasks,
        'completed_count' => $completedTasks,
        'archive_count' => countColumnTasks($archiveTasks),
    ];
}

function serializableKanbanColumns(array $columns): array
{
    $serializedColumns = [];

    foreach ($columns as $columnName => $tasks) {
        $serializedTasks = [];

        foreach (is_array($tasks) ? $tasks : [] as $task) {
            if (is_array($task)) {
                $serializedTasks[] = normalizeTask($task);
            }
        }

        $serializedColumns[] = [
            'name' => (string) $columnName,
            'tasks' => $serializedTasks,
        ];
    }

    return $serializedColumns;
}

function taskAtKanbanPath(array $columns, string $columnName, array $path): ?array
{
    if ($columnName === '' || $path === [] || !isset($columns[$columnName]) || !is_array($columns[$columnName])) {
        return null;
    }

    $currentTasks = $columns[$columnName];

    foreach ($path as $depth => $index) {
        if (!isset($currentTasks[$index]) || !is_array($currentTasks[$index])) {
            return null;
        }

        if ($depth === count($path) - 1) {
            return normalizeTask($currentTasks[$index]);
        }

        $currentTasks = is_array($currentTasks[$index]['children'] ?? null)
            ? $currentTasks[$index]['children']
            : [];
    }

    return null;
}

function taskMarkdown(array $task): string
{
    return rtrim(writeTasks([$task]));
}

function enrichKanbanActionResult(array &$result, array $request, array $columns, array $archiveTasks, array $columnSettings, ?array $restoreSourceTask = null): void
{
    if (($result['success'] ?? false) !== true) {
        return;
    }

    $action = $result['action'] ?? null;

    if ($action === 'add_card') {
        $columnName = kanbanRequestString($request, 'column');

        if ($columnName !== '' && isset($columns[$columnName]) && is_array($columns[$columnName]) && $columns[$columnName] !== []) {
            $taskIndex = array_key_last($columns[$columnName]);
            $task = $taskIndex !== null && is_array($columns[$columnName][$taskIndex] ?? null)
                ? normalizeTask($columns[$columnName][$taskIndex])
                : null;

            if ($task !== null) {
                $result['data']['task'] = $task;
                $result['data']['index'] = $taskIndex;
            }
        }

        return;
    }

    if (in_array($action, ['toggle_task', 'update_priority', 'update_task', 'update_card', 'update_nested_task'], true)) {
        $columnName = kanbanRequestString($request, 'column');
        $path = $action === 'update_card'
            ? [kanbanRequestInt($request, 'index')]
            : parsePath(kanbanRequestString($request, 'path'));
        $task = taskAtKanbanPath($columns, $columnName, $path);

        if ($task !== null) {
            $result['data']['column'] = $columnName;
            $result['data']['path'] = implode('.', array_map(static fn (int $index): string => (string) $index, $path));
            $result['data']['task'] = $task;
        }

        return;
    }

    if (in_array($action, ['delete_task', 'delete_card', 'delete_nested_task'], true)) {
        $columnName = kanbanRequestString($request, 'column');
        $path = $action === 'delete_card'
            ? [kanbanRequestInt($request, 'index')]
            : parsePath(kanbanRequestString($request, 'path'));

        $result['data']['column'] = $columnName;
        $result['data']['path'] = implode('.', array_map(static fn (int $index): string => (string) $index, $path));
        $result['data']['deleted'] = true;

        return;
    }

    if ($action === 'archive_task') {
        $columnName = kanbanRequestString($request, 'column');
        $path = parsePath(kanbanRequestString($request, 'path'));
        $archiveIndex = array_key_last($archiveTasks);
        $archivedTask = $archiveIndex !== null && is_array($archiveTasks[$archiveIndex] ?? null)
            ? normalizeTask($archiveTasks[$archiveIndex])
            : null;

        $result['data']['column'] = $columnName;
        $result['data']['path'] = implode('.', array_map(static fn (int $index): string => (string) $index, $path));
        $result['data']['archived'] = true;

        if ($archiveIndex !== null) {
            $result['data']['archive_index'] = $archiveIndex;
        }

        if ($archivedTask !== null) {
            $result['data']['task'] = $archivedTask;
        }

        return;
    }

    if ($action === 'restore_task') {
        $restoreArchiveIndex = kanbanRequestInt($request, 'archive_index');
        $destinationColumn = null;
        $destinationIndex = null;
        $restoredTask = null;
        $restoreColumn = $restoreSourceTask !== null ? trim((string) ($restoreSourceTask['archived_from'] ?? '')) : '';

        if ($restoreColumn !== '' && isset($columns[$restoreColumn]) && is_array($columns[$restoreColumn]) && $columns[$restoreColumn] !== []) {
            $candidateIndex = array_key_last($columns[$restoreColumn]);

            if ($candidateIndex !== null && is_array($columns[$restoreColumn][$candidateIndex] ?? null)) {
                $destinationColumn = $restoreColumn;
                $destinationIndex = $candidateIndex;
                $restoredTask = normalizeTask($columns[$restoreColumn][$candidateIndex]);
            }
        }

        if ($restoredTask === null) {
            foreach ($columns as $columnName => $tasks) {
                if (!is_array($tasks) || $tasks === []) {
                    continue;
                }

                foreach ($tasks as $taskIndex => $task) {
                    if (!is_array($task)) {
                        continue;
                    }

                    $normalizedTask = normalizeTask($task);

                    if ($restoreSourceTask !== null && $normalizedTask['title'] === $restoreSourceTask['title'] && $normalizedTask['created_at'] === $restoreSourceTask['created_at']) {
                        $destinationColumn = (string) $columnName;
                        $destinationIndex = (int) $taskIndex;
                        $restoredTask = $normalizedTask;
                        break 2;
                    }
                }
            }
        }

        $result['data']['archive_index'] = $restoreArchiveIndex;
        $result['data']['restored'] = true;

        if ($destinationColumn !== null && $destinationIndex !== null && $restoredTask !== null) {
            $result['data']['column'] = $destinationColumn;
            $result['data']['path'] = (string) $destinationIndex;
            $result['data']['index'] = $destinationIndex;
            $result['data']['task'] = $restoredTask;
        }

        return;
    }

    if ($action === 'toggle_column_control') {
        $columnName = kanbanRequestString($request, 'column');
        $control = kanbanRequestString($request, 'control');
        $settings = normalizeColumnSettings($columnSettings[$columnName] ?? []);

        $result['data']['column'] = $columnName;
        $result['data']['control'] = $control;
        $result['data']['settings'] = $settings;
        $result['data']['controls'] = [
            'archive_hidden' => columnControlHidden($columnSettings, $columnName, 'archive'),
            'copy_hidden' => columnControlHidden($columnSettings, $columnName, 'copy'),
        ];
        $result['data']['class'] = columnControlClass($columnSettings, $columnName);

        return;
    }

    if ($action === 'reorder_cards') {
        $result['data']['reordered'] = true;
        $result['data']['columns'] = serializableKanbanColumns($columns);
        $result['data']['column_order'] = array_keys($columns);

        return;
    }

    if ($action === 'remove_column') {
        $columnName = kanbanRequestString($request, 'column');

        $result['data']['column'] = $columnName;
        $result['data']['removed'] = true;
        $result['data']['column_order'] = array_keys($columns);
    }
}

function parseKanban(string $content): array
{
    $columns = [];
    $columnSettings = [];
    $currentColumn = null;
    $archiveColumn = 'Archive';

    foreach (preg_split('/\R/', $content) as $line) {
        $trimmed = trim($line);

        if ($trimmed === '') {
            continue;
        }

        if (preg_match('/^#{1,3}\s+(.+)$/', $trimmed, $matches) === 1) {
            $parsedHeading = parseColumnHeading(trim($matches[1]));
            $heading = $parsedHeading['name'];
            $normalized = strtolower($heading);

            if (in_array($normalized, ['kanban', 'board'], true)) {
                continue;
            }

            if ($normalized === 'archive') {
                $currentColumn = $archiveColumn;
                $columns[$currentColumn] = $columns[$currentColumn] ?? [];
                $columnSettings[$currentColumn] = $parsedHeading['settings'];
                continue;
            }

            $currentColumn = $heading;
            $columns[$currentColumn] = $columns[$currentColumn] ?? [];
            $columnSettings[$currentColumn] = $parsedHeading['settings'];
            continue;
        }

        if ($currentColumn === null) {
            $currentColumn = 'Inbox';
            $columns[$currentColumn] = $columns[$currentColumn] ?? [];
        }

        $task = parseTaskLine($line);

        if ($task !== null) {
            appendNestedTask($columns[$currentColumn], $task, max(0, (int) $task['level']));
            continue;
        }

        $tags = parseTaskTagsLine($line);

        if ($tags !== null) {
            appendTaskTags($columns[$currentColumn], $tags['tags'], max(0, (int) $tags['level']));
            continue;
        }

        $description = parseTaskDescriptionLine($line);

        if ($description !== null) {
            appendTaskDescription($columns[$currentColumn], $description['description'], max(0, (int) $description['level']));
        }
    }

    $columns[$archiveColumn] = $columns[$archiveColumn] ?? [];
    $columnSettings[$archiveColumn] = $columnSettings[$archiveColumn] ?? normalizeColumnSettings([]);
    $GLOBALS['kanbanColumnSettings'] = $columnSettings;

    return $columns;
}


function splitKanbanArchive(array $columns): array
{
    $activeColumns = [];
    $archiveTasks = [];

    foreach ($columns as $columnName => $tasks) {
        if (strtolower(trim((string) $columnName)) === 'archive') {
            $archiveTasks = array_merge($archiveTasks, $tasks);
            continue;
        }

        $activeTasks = [];

        foreach ($tasks as $task) {
            if (!is_array($task)) {
                continue;
            }

            if (!empty($task['archived_at'])) {
                $archiveTasks[] = $task;
                continue;
            }

            $activeTasks[] = $task;
        }

        $activeColumns[$columnName] = $activeTasks;
    }

    return [$activeColumns, $archiveTasks];
}

function appendArchiveColumn(array $columns, array $archiveTasks): array
{
    $columnsWithoutArchive = [];

    foreach ($columns as $columnName => $tasks) {
        if (strtolower(trim((string) $columnName)) === 'archive') {
            continue;
        }

        $columnsWithoutArchive[$columnName] = $tasks;
    }

    if ($archiveTasks !== []) {
        $columnsWithoutArchive['Archive'] = $archiveTasks;
    }

    return $columnsWithoutArchive;
}

function writeTasks(array $tasks, int $level = 0): string
{
    $content = '';

    foreach ($tasks as $task) {
        $title = trim((string) ($task['title'] ?? ''));

        if ($title === '') {
            continue;
        }

        $indent = str_repeat('  ', $level);
        $checkbox = !empty($task['done']) ? 'x' : ' ';
        $content .= $indent . '- [' . $checkbox . '] ' . $title . formatTaskMetadata($task) . "\n";

        $description = trim((string) ($task['description'] ?? ''));

        if ($description !== '') {
            foreach (preg_split('/\R/', $description) as $descriptionLine) {
                $descriptionLine = trim((string) $descriptionLine);

                if ($descriptionLine === '') {
                    continue;
                }

                $content .= str_repeat('  ', $level + 1) . '- ' . $descriptionLine . "\n";
            }
        }

        $tags = normalizeTaskTags($task['tags'] ?? []);

        if ($tags !== []) {
            $content .= str_repeat('  ', $level + 1) . '- Tags: ' . implode(', ', $tags) . "\n";
        }

        if (!empty($task['children']) && is_array($task['children'])) {
            $content .= writeTasks($task['children'], $level + 1);
        }
    }

    return $content;
}


function saveKanban(string $file, array $columns, array $archiveTasks = [], array $columnSettings = []): void
{
    $content = "# Kanban\n\n";
    $columnsToWrite = appendArchiveColumn($columns, $archiveTasks);

    foreach ($columnsToWrite as $columnName => $tasks) {
        $content .= '## ' . trim((string) $columnName) . formatColumnMetadata($columnSettings[$columnName] ?? []) . "\n";
        $content .= writeTasks($tasks);
        $content .= "\n";
    }

    file_put_contents($file, rtrim($content) . "\n");
}

function parsePath(string $path): array
{
    if (trim($path) === '') {
        return [];
    }

    return array_values(array_filter(array_map(static function (string $value): ?int {
        return is_numeric($value) ? max(0, (int) $value) : null;
    }, explode('.', $path)), static fn ($value): bool => $value !== null));
}

function updateTaskByPath(array &$tasks, array $path, string $title, bool $done, ?string $dueAt = null, ?string $description = null, array|string|null $tags = null): bool
{
    $current = &$tasks;

    foreach ($path as $depth => $index) {
        if (!isset($current[$index])) {
            return false;
        }

        if ($depth === count($path) - 1) {
            $wasDone = !empty($current[$index]['done']);

            $current[$index]['title'] = $title;

            if ($description !== null) {
                $current[$index]['description'] = trim($description);
            }

            if ($tags !== null) {
                $current[$index]['tags'] = normalizeTaskTags($tags);
            }

            $current[$index]['done'] = $done;
            $current[$index]['due_at'] = $dueAt;

            if (empty($current[$index]['created_at'])) {
                $current[$index]['created_at'] = currentKanbanTimestamp();
            }

            if ($done && !$wasDone) {
                $current[$index]['completed_at'] = currentKanbanTimestamp();
            }

            if (!$done) {
                $current[$index]['completed_at'] = null;
            }

            return true;
        }

        if (!isset($current[$index]['children']) || !is_array($current[$index]['children'])) {
            return false;
        }

        $current = &$current[$index]['children'];
    }

    return false;
}

function deleteTaskByPath(array &$tasks, array $path): bool
{
    $current = &$tasks;

    foreach ($path as $depth => $index) {
        if (!isset($current[$index])) {
            return false;
        }

        if ($depth === count($path) - 1) {
            array_splice($current, $index, 1);
            return true;
        }

        if (!isset($current[$index]['children']) || !is_array($current[$index]['children'])) {
            return false;
        }

        $current = &$current[$index]['children'];
    }

    return false;
}

function takeTaskByPath(array &$tasks, array $path): ?array
{
    $current = &$tasks;

    foreach ($path as $depth => $index) {
        if (!isset($current[$index])) {
            return null;
        }

        if ($depth === count($path) - 1) {
            $task = $current[$index];
            array_splice($current, $index, 1);
            return is_array($task) ? $task : null;
        }

        if (!isset($current[$index]['children']) || !is_array($current[$index]['children'])) {
            return null;
        }

        $current = &$current[$index]['children'];
    }

    return null;
}

function archiveTaskByPath(array &$columns, array &$archiveTasks, string $column, array $path): bool
{
    if (!isset($columns[$column]) || $path === []) {
        return false;
    }

    $task = takeTaskByPath($columns[$column], $path);

    if ($task === null) {
        return false;
    }

    $task = normalizeTask($task);
    $task['archived_at'] = currentKanbanTimestamp();
    $task['archived_from'] = $column;
    $archiveTasks[] = $task;

    return true;
}

function restoreArchivedTaskByIndex(array &$columns, array &$archiveTasks, int $index): bool
{
    if (!isset($archiveTasks[$index])) {
        return false;
    }

    $task = $archiveTasks[$index];
    array_splice($archiveTasks, $index, 1);

    if (!is_array($task)) {
        return false;
    }

    $task = normalizeTask($task);
    $restoreColumn = trim((string) ($task['archived_from'] ?? ''));
    $task['archived_at'] = null;
    $task['archived_from'] = null;

    if ($restoreColumn === '' || !array_key_exists($restoreColumn, $columns)) {
        $restoreColumn = array_key_last($columns);
    }

    if ($restoreColumn === null || $restoreColumn === '') {
        $columns['Done'] = [];
        $restoreColumn = 'Done';
    }

    $columns[$restoreColumn][] = $task;

    return true;
}

function updateTaskPriorityByPath(array &$tasks, array $path, ?string $priority): bool
{
    $current = &$tasks;

    foreach ($path as $depth => $index) {
        if (!isset($current[$index])) {
            return false;
        }

        if ($depth === count($path) - 1) {
            $current[$index]['priority'] = normalizeTaskPriority($priority);

            if (empty($current[$index]['created_at'])) {
                $current[$index]['created_at'] = currentKanbanTimestamp();
            }

            return true;
        }

        if (!isset($current[$index]['children']) || !is_array($current[$index]['children'])) {
            return false;
        }

        $current = &$current[$index]['children'];
    }

    return false;
}

function taskHasIncompleteSubtasks(array $task): bool
{
    $children = is_array($task['children'] ?? null) ? $task['children'] : [];

    foreach ($children as $child) {
        if (!is_array($child)) {
            continue;
        }

        if (empty($child['done'])) {
            return true;
        }

        if (taskHasIncompleteSubtasks($child)) {
            return true;
        }
    }

    return false;
}

function toggleTaskByPath(array &$tasks, array $path): bool
{
    $current = &$tasks;

    foreach ($path as $depth => $index) {
        if (!isset($current[$index])) {
            return false;
        }

        if ($depth === count($path) - 1) {
            $willBeDone = empty($current[$index]['done']);

            if ($willBeDone && taskHasIncompleteSubtasks($current[$index])) {
                return false;
            }

            $current[$index]['done'] = $willBeDone;

            if (empty($current[$index]['created_at'])) {
                $current[$index]['created_at'] = currentKanbanTimestamp();
            }

            if (!empty($current[$index]['done'])) {
                $current[$index]['completed_at'] = currentKanbanTimestamp();
            } else {
                $current[$index]['completed_at'] = null;
            }

            return true;
        }

        if (!isset($current[$index]['children']) || !is_array($current[$index]['children'])) {
            return false;
        }

        $current = &$current[$index]['children'];
    }

    return false;
}

function addNestedTask(array &$tasks, array $path, array $task): bool
{
    $current = &$tasks;

    foreach ($path as $index) {
        if (!isset($current[$index])) {
            return false;
        }

        if (!isset($current[$index]['children']) || !is_array($current[$index]['children'])) {
            $current[$index]['children'] = [];
        }

        $current = &$current[$index]['children'];
    }

    $current[] = normalizeTask($task);

    return true;
}

function removeKanbanColumn(array &$columns, string $columnName): bool
{
    if (!array_key_exists($columnName, $columns)) {
        return false;
    }

    if (count($columns) <= 1) {
        return false;
    }

    $removedTasks = is_array($columns[$columnName]) ? $columns[$columnName] : [];
    unset($columns[$columnName]);

    if ($removedTasks === []) {
        return true;
    }

    $fallbackColumn = array_key_first($columns);

    if ($fallbackColumn === null) {
        $columns['Todo'] = [];
        $fallbackColumn = 'Todo';
    }

    $columns[$fallbackColumn] = array_merge($columns[$fallbackColumn], $removedTasks);

    return true;
}


function sanitizeTasksFromPayload(array $tasks): array
{
    $sanitized = [];

    foreach ($tasks as $task) {
        if (!is_array($task)) {
            continue;
        }

        $normalized = normalizeTask($task);

        if ($normalized['title'] === '') {
            continue;
        }

        $sanitized[] = $normalized;
    }

    return $sanitized;
}

function countTasks(array $tasks): array
{
    $total = 0;
    $completed = 0;

    foreach ($tasks as $task) {
        $total++;

        if (!empty($task['done'])) {
            $completed++;
        }

        if (!empty($task['children']) && is_array($task['children'])) {
            [$childTotal, $childCompleted] = countTasks($task['children']);
            $total += $childTotal;
            $completed += $childCompleted;
        }
    }

    return [$total, $completed];
}

function countColumnTasks(array $tasks): int
{
    [$total] = countTasks($tasks);

    return $total;
}

function kanbanActionResult(bool $success, string $action, string $message, array $data = []): array
{
    return [
        'success' => $success,
        'action' => $action,
        'message' => $message,
        'data' => $data,
    ];
}

function kanbanRequestString(array $request, string $key): string
{
    return trim((string) ($request[$key] ?? ''));
}

function kanbanRequestInt(array $request, string $key): int
{
    return max(0, (int) ($request[$key] ?? 0));
}

function kanbanDefaultColumns(): array
{
    return [
        'Todo' => [],
        'In Progress' => [],
        'Done' => [],
    ];
}

function handleKanbanAction(array &$columns, array &$archiveTasks, array &$columnSettings, array $request): array
{
    $action = kanbanRequestString($request, 'action');

    if ($columns === []) {
        $columns = kanbanDefaultColumns();
    }

    if ($action === '') {
        return kanbanActionResult(false, $action, 'Missing Kanban action.');
    }

    if ($action === 'add_column') {
        return handleKanbanAddColumn($columns, $columnSettings, $request, $action);
    }

    if ($action === 'remove_column') {
        return handleKanbanRemoveColumn($columns, $columnSettings, $request, $action);
    }

    if ($action === 'toggle_column_control') {
        return handleKanbanToggleColumnControl($columnSettings, $request, $action);
    }

    if ($action === 'add_card') {
        return handleKanbanAddCard($columns, $request, $action);
    }

    if ($action === 'add_nested_task') {
        return handleKanbanAddNestedTask($columns, $request, $action);
    }

    if ($action === 'toggle_task') {
        return handleKanbanToggleTask($columns, $request, $action);
    }

    if ($action === 'update_priority') {
        return handleKanbanUpdatePriority($columns, $request, $action);
    }

    if ($action === 'update_task' || $action === 'update_card' || $action === 'update_nested_task') {
        return handleKanbanUpdateTask($columns, $request, $action);
    }

    if ($action === 'delete_task' || $action === 'delete_card' || $action === 'delete_nested_task') {
        return handleKanbanDeleteTask($columns, $request, $action);
    }

    if ($action === 'archive_task') {
        return handleKanbanArchiveTask($columns, $archiveTasks, $columnSettings, $request, $action);
    }

    if ($action === 'restore_task') {
        return handleKanbanRestoreTask($columns, $archiveTasks, $request, $action);
    }

    if ($action === 'reorder_cards') {
        return handleKanbanReorderCards($columns, $request, $action);
    }

    return kanbanActionResult(false, $action, 'Unknown Kanban action: ' . $action);
}

function handleKanbanAddColumn(array &$columns, array &$columnSettings, array $request, string $action): array
{
    $column = kanbanRequestString($request, 'column');

    if ($column === '') {
        return kanbanActionResult(false, $action, 'Column name is required.');
    }

    if (isset($columns[$column])) {
        return kanbanActionResult(true, $action, 'Column already exists.', [
            'column' => $column,
        ]);
    }

    $columns[$column] = [];
    $columnSettings[$column] = normalizeColumnSettings($columnSettings[$column] ?? []);

    return kanbanActionResult(true, $action, 'Column added.', [
        'column' => $column,
    ]);
}

function handleKanbanRemoveColumn(array &$columns, array &$columnSettings, array $request, string $action): array
{
    $column = kanbanRequestString($request, 'column');
    $removed = removeKanbanColumn($columns, $column);

    if ($removed) {
        unset($columnSettings[$column]);
    }

    return kanbanActionResult($removed, $action, $removed ? 'Column removed.' : 'Column could not be removed.', [
        'column' => $column,
    ]);
}

function handleKanbanToggleColumnControl(array &$columnSettings, array $request, string $action): array
{
    $column = kanbanRequestString($request, 'column');
    $control = kanbanRequestString($request, 'control');
    $updated = toggleColumnControl($columnSettings, $column, $control);

    return kanbanActionResult($updated, $action, $updated ? 'Column control updated.' : 'Column control could not be updated.', [
        'column' => $column,
        'control' => $control,
        'settings' => $columnSettings[$column] ?? normalizeColumnSettings([]),
    ]);
}

function handleKanbanAddCard(array &$columns, array $request, string $action): array
{
    $column = kanbanRequestString($request, 'column');
    $title = kanbanRequestString($request, 'title');

    if ($column === '' || $title === '') {
        return kanbanActionResult(false, $action, 'Column and title are required.');
    }

    $columns[$column] = $columns[$column] ?? [];
    $columns[$column][] = createKanbanTask($title);

    return kanbanActionResult(true, $action, 'Task added.', [
        'column' => $column,
    ]);
}

function handleKanbanAddNestedTask(array &$columns, array $request, string $action): array
{
    $column = kanbanRequestString($request, 'column');
    $path = parsePath(kanbanRequestString($request, 'path'));
    $title = kanbanRequestString($request, 'title');

    if (!isset($columns[$column]) || $title === '') {
        return kanbanActionResult(false, $action, 'Parent task could not be found.', [
            'column' => $column,
            'path' => $path,
        ]);
    }

    $added = addNestedTask($columns[$column], $path, createKanbanTask($title));

    return kanbanActionResult($added, $action, $added ? 'Subtask added.' : 'Subtask could not be added.', [
        'column' => $column,
        'path' => $path,
    ]);
}

function handleKanbanToggleTask(array &$columns, array $request, string $action): array
{
    $column = kanbanRequestString($request, 'column');
    $path = parsePath(kanbanRequestString($request, 'path'));
    $updated = isset($columns[$column]) && $path !== [] && toggleTaskByPath($columns[$column], $path);

    return kanbanActionResult($updated, $action, $updated ? 'Task status updated.' : 'Task status could not be updated.', [
        'column' => $column,
        'path' => $path,
    ]);
}

function handleKanbanUpdatePriority(array &$columns, array $request, string $action): array
{
    $column = kanbanRequestString($request, 'column');
    $path = parsePath(kanbanRequestString($request, 'path'));
    $priority = kanbanRequestString($request, 'priority');
    $updated = isset($columns[$column]) && $path !== [] && updateTaskPriorityByPath($columns[$column], $path, $priority);

    return kanbanActionResult($updated, $action, $updated ? 'Task priority updated.' : 'Task priority could not be updated.', [
        'column' => $column,
        'path' => $path,
        'priority' => normalizeTaskPriority($priority),
    ]);
}

function handleKanbanUpdateTask(array &$columns, array $request, string $action): array
{
    $column = kanbanRequestString($request, 'column');
    $path = $action === 'update_card'
        ? [kanbanRequestInt($request, 'index')]
        : parsePath(kanbanRequestString($request, 'path'));
    $title = kanbanRequestString($request, 'title');
    $done = isset($request['done']);
    $dueAt = normalizeDateTimeLocal($request['due_at'] ?? null);
    $description = array_key_exists('description', $request) ? trim((string) $request['description']) : null;
    $tags = array_key_exists('tags_present', $request) ? normalizeTaskTags($request['tags'] ?? []) : null;
    $updated = isset($columns[$column]) && $path !== [] && $title !== '' && updateTaskByPath($columns[$column], $path, $title, $done, $dueAt, $description, $tags);

    return kanbanActionResult($updated, $action, $updated ? 'Task updated.' : 'Task could not be updated.', [
        'column' => $column,
        'path' => $path,
    ]);
}

function handleKanbanDeleteTask(array &$columns, array $request, string $action): array
{
    $column = kanbanRequestString($request, 'column');
    $path = $action === 'delete_card'
        ? [kanbanRequestInt($request, 'index')]
        : parsePath(kanbanRequestString($request, 'path'));
    $deleted = isset($columns[$column]) && $path !== [] && deleteTaskByPath($columns[$column], $path);

    return kanbanActionResult($deleted, $action, $deleted ? 'Task deleted.' : 'Task could not be deleted.', [
        'column' => $column,
        'path' => $path,
    ]);
}

function handleKanbanArchiveTask(array &$columns, array &$archiveTasks, array $columnSettings, array $request, string $action): array
{
    $column = kanbanRequestString($request, 'column');
    $path = parsePath(kanbanRequestString($request, 'path'));
    $archived = !columnControlHidden($columnSettings, $column, 'archive')
        && $path !== []
        && archiveTaskByPath($columns, $archiveTasks, $column, $path);

    return kanbanActionResult($archived, $action, $archived ? 'Task archived.' : 'Task could not be archived.', [
        'column' => $column,
        'path' => $path,
    ]);
}

function handleKanbanRestoreTask(array &$columns, array &$archiveTasks, array $request, string $action): array
{
    $index = kanbanRequestInt($request, 'archive_index');
    $restored = restoreArchivedTaskByIndex($columns, $archiveTasks, $index);

    return kanbanActionResult($restored, $action, $restored ? 'Task restored.' : 'Task could not be restored.', [
        'archive_index' => $index,
    ]);
}

function handleKanbanReorderCards(array &$columns, array $request, string $action): array
{
    $payload = json_decode((string) ($request['payload'] ?? ''), true);

    if (!is_array($payload)) {
        return kanbanActionResult(false, $action, 'Invalid reorder payload.');
    }

    $reorderedColumns = [];

    foreach ($payload as $columnName => $tasksPayload) {
        if (!array_key_exists($columnName, $columns)) {
            continue;
        }

        $reorderedColumns[$columnName] = is_array($tasksPayload)
            ? sanitizeTasksFromPayload($tasksPayload)
            : $columns[$columnName];
    }

    foreach ($columns as $columnName => $existingTasks) {
        if (!array_key_exists($columnName, $reorderedColumns)) {
            $reorderedColumns[$columnName] = $existingTasks;
        }
    }

    $columns = $reorderedColumns;

    return kanbanActionResult(true, $action, 'Board order updated.', [
        'columns' => array_keys($columns),
    ]);
}

function createKanbanTask(string $title): array
{
    return [
        'done' => false,
        'title' => $title,
        'description' => '',
        'tags' => [],
        'type' => 'task',
        'created_at' => currentKanbanTimestamp(),
        'completed_at' => null,
        'due_at' => null,
        'archived_at' => null,
        'archived_from' => null,
        'priority' => 'normal',
        'children' => [],
    ];
}

function currentKanbanTimestamp(): string
{
    return date('c');
}

function validTaskPriorities(): array
{
    return ['low', 'normal', 'high', 'urgent'];
}

function normalizeTaskPriority(?string $priority): string
{
    $priority = strtolower(trim((string) $priority));

    return in_array($priority, validTaskPriorities(), true) ? $priority : 'normal';
}

function normalizeTaskTags(array|string|null $tags): array
{
    if ($tags === null) {
        return [];
    }

    if (is_string($tags)) {
        $decoded = json_decode($tags, true);

        if (is_array($decoded)) {
            $tags = $decoded;
        } else {
            $tags = preg_split('/\s*,\s*/', $tags) ?: [];
        }
    }

    if (!is_array($tags)) {
        return [];
    }

    $flatTags = [];

    $flatten = static function (array $values) use (&$flatten, &$flatTags): void {
        foreach ($values as $value) {
            if (is_array($value)) {
                $flatten($value);
                continue;
            }

            $flatTags[] = $value;
        }
    };

    $flatten($tags);

    return array_values(array_unique(array_filter(array_map(static function ($tag): string {
        $tag = strtolower(trim((string) $tag));
        $tag = preg_replace('/[^a-z0-9._-]+/', '-', $tag);

        return trim((string) $tag, '-');
    }, $flatTags), static fn (string $tag): bool => $tag !== '')));
}

function parseTaskTagsLine(string $line): ?array
{
    if (preg_match('/^(\s*)-\s+Tags:\s*(.+)$/i', $line, $matches) === 1) {
        return [
            'level' => intdiv(strlen(str_replace("\t", '  ', $matches[1])), 2),
            'tags' => normalizeTaskTags((string) $matches[2]),
        ];
    }

    if (preg_match('/^(\s+)Tags:\s*(.+)$/i', $line, $matches) === 1) {
        return [
            'level' => intdiv(strlen(str_replace("\t", '  ', $matches[1])), 2),
            'tags' => normalizeTaskTags((string) $matches[2]),
        ];
    }

    return null;
}

function parseTaskMetadata(string $title): array
{
    $metadata = [
        'created_at' => null,
        'completed_at' => null,
        'due_at' => null,
        'archived_at' => null,
        'archived_from' => null,
        'priority' => null,
    ];

    $pattern = '/\s*<!--\s*(.*?)\s*-->\s*$/';

    if (preg_match($pattern, $title, $matches) === 1) {
        $title = trim((string) preg_replace($pattern, '', $title));
        $rawMetadata = trim((string) ($matches[1] ?? ''));

        if (preg_match_all('/(created_at|completed_at|due_at|archived_at|archived_from|priority):\s*("[^"]+"|[^\s]+)/', $rawMetadata, $metadataMatches, PREG_SET_ORDER)) {
            foreach ($metadataMatches as $metadataMatch) {
                $value = trim((string) $metadataMatch[2]);
                $value = trim($value, '"');
                $metadata[$metadataMatch[1]] = $value !== '' ? $value : null;
            }
        }
    }

    return [
        'title' => trim($title),
        'metadata' => array_merge($metadata, [
            'priority' => normalizeTaskPriority($metadata['priority']),
        ]),
    ];
}

function formatTaskMetadata(array $task): string
{
    $createdAt = trim((string) ($task['created_at'] ?? ''));
    $completedAt = trim((string) ($task['completed_at'] ?? ''));
    $dueAt = trim((string) ($task['due_at'] ?? ''));
    $archivedAt = trim((string) ($task['archived_at'] ?? ''));
    $archivedFrom = trim((string) ($task['archived_from'] ?? ''));
    $priority = normalizeTaskPriority($task['priority'] ?? null);

    if ($createdAt === '' && $completedAt === '' && $dueAt === '' && $archivedAt === '' && $archivedFrom === '' && $priority === '') {
        return '';
    }

    if ($createdAt === '') {
        $createdAt = currentKanbanTimestamp();
    }

    $metadata = ' <!-- created_at: ' . $createdAt;

    if ($completedAt !== '') {
        $metadata .= ' completed_at: ' . $completedAt;
    }
    if ($dueAt !== '') {
        $metadata .= ' due_at: ' . $dueAt;
    }
    if ($archivedAt !== '') {
        $metadata .= ' archived_at: ' . $archivedAt;
    }
    if ($archivedFrom !== '') {
        $metadata .= ' archived_from: "' . str_replace('"', '\\"', $archivedFrom) . '"';
    }
    if ($priority !== '') {
        $metadata .= ' priority: ' . $priority;
    }

    return $metadata . ' -->';
}

function normalizeDateTimeLocal(?string $value): ?string
{
    $value = trim((string) $value);

    if ($value === '') {
        return null;
    }

    try {
        return (new DateTimeImmutable($value))->format('c');
    } catch (Throwable) {
        return null;
    }
}

function normalizeTask(array $task): array
{
    $done = !empty($task['done']);
    $createdAt = trim((string) ($task['created_at'] ?? ''));
    $completedAt = trim((string) ($task['completed_at'] ?? ''));
    $dueAt = trim((string) ($task['due_at'] ?? ''));
    $archivedAt = trim((string) ($task['archived_at'] ?? ''));
    $archivedFrom = trim((string) ($task['archived_from'] ?? ''));
    $priority = normalizeTaskPriority($task['priority'] ?? null);
    $tags = normalizeTaskTags($task['tags'] ?? []);

    if ($createdAt === '') {
        $createdAt = currentKanbanTimestamp();
    }

    if (!$done) {
        $completedAt = '';
    }

    if ($done && $completedAt === '') {
        $completedAt = currentKanbanTimestamp();
    }

    return [
        'done' => $done,
        'title' => trim((string) ($task['title'] ?? '')),
        'description' => trim((string) ($task['description'] ?? '')),
        'tags' => $tags,
        'type' => (string) ($task['type'] ?? 'task'),
        'created_at' => $createdAt,
        'completed_at' => $completedAt !== '' ? $completedAt : null,
        'due_at' => $dueAt !== '' ? $dueAt : null,
        'archived_at' => $archivedAt !== '' ? $archivedAt : null,
        'archived_from' => $archivedFrom !== '' ? $archivedFrom : null,
        'priority' => $priority,
        'children' => array_values(array_map(
            static fn (array $child): array => normalizeTask($child),
            array_filter(is_array($task['children'] ?? null) ? $task['children'] : [], 'is_array')
        )),
    ];
}

function taskForDataAttribute(array $task): array
{
    return [
        'done' => !empty($task['done']),
        'title' => (string) ($task['title'] ?? ''),
        'description' => (string) ($task['description'] ?? ''),
        'tags' => normalizeTaskTags($task['tags'] ?? []),
        'type' => (string) ($task['type'] ?? 'task'),
        'created_at' => $task['created_at'] ?? null,
        'completed_at' => $task['completed_at'] ?? null,
        'due_at' => $task['due_at'] ?? null,
        'archived_at' => $task['archived_at'] ?? null,
        'archived_from' => $task['archived_from'] ?? null,
        'priority' => $task['priority'] ?? null,
    ];
}
