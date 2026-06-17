<?php

require_once dirname(__DIR__) . '/lib/kanban.php';

$selectedProject = is_array($selectedProject ?? null) ? $selectedProject : null;
$projectSlug = $selectedProject['slug'] ?? null;
$projectName = $selectedProject['name'] ?? 'Local Project';
$projectPath = $selectedProject['path'] ?? dirname(__DIR__);
$kanbanRoute = $projectSlug !== null ? '/kanban/' . rawurlencode((string) $projectSlug) : '/kanban';
$kanbanFile = kanbanFileForProjectPath((string) $projectPath);
$board = loadKanbanBoard($kanbanFile);

$columns = $board['columns'];
$archiveTasks = $board['archive_tasks'];
$columnSettings = $board['column_settings'];
$totalTasks = $board['total_tasks'];
$completedTasks = $board['completed_tasks'];
$kanbanCreateError = $board['create_error'];

if (!function_exists('e')) {
    function e(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
    }
}


function markdownInline(string $value): string
{
    $value = e($value);
    $value = preg_replace('/`([^`]+)`/', '<code>$1</code>', $value);
    $value = preg_replace('/\*\*([^*]+)\*\*/', '<strong>$1</strong>', $value);
    $value = preg_replace('/\*([^*]+)\*/', '<em>$1</em>', $value);
    return $value;
}



function slugify(string $value): string
{
    $value = strtolower(trim($value));
    $value = preg_replace('/[^a-z0-9]+/', '-', $value);
    $value = trim((string) $value, '-');
    return $value !== '' ? $value : 'column';
}



function priorityBadgeClass(?string $priority): string
{
    return match (normalizeTaskPriority($priority)) {
        'low' => 'text-bg-secondary',
        'high' => 'text-bg-warning',
        'urgent' => 'text-bg-danger',
        default => 'text-bg-primary',
    };
}

function priorityLabel(?string $priority): string
{
    return ucfirst(normalizeTaskPriority($priority));
}

function renderTaskTags(array $task): string
{
    $tags = normalizeTaskTags($task['tags'] ?? []);

    if ($tags === []) {
        return '';
    }

    $html = '<div class="task-tags d-flex flex-wrap gap-1 mt-2">';

    foreach ($tags as $tag) {
        $html .= '<span class="badge text-bg-light border task-tag" data-task-tag="' . e($tag) . '">#' . e($tag) . '</span>';
    }

    return $html . '</div>';
}

function taskTagsValue(array $task): string
{
    return implode(',', normalizeTaskTags($task['tags'] ?? []));
}

function formatTaskDateTooltip(?string $timestamp): string
{
    $timestamp = trim((string) $timestamp);

    if ($timestamp === '') {
        return '';
    }

    try {
        return (new DateTimeImmutable($timestamp))->format('F j, Y \a\t g:i A T');
    } catch (Throwable) {
        return $timestamp;
    }
}

function renderTimeAgo(?string $timestamp): string
{
    $timestamp = trim((string) $timestamp);

    if ($timestamp === '') {
        return '';
    }

    return '<time class="timeago" datetime="' . e($timestamp) . '" title="' . e(formatTaskDateTooltip($timestamp)) . '">' . e(formatTaskDateTooltip($timestamp)) . '</time>';
}


function dueDateClass(array $task): string
{
    if (!empty($task['done']) || empty($task['due_at'])) {
        return 'text-muted';
    }

    try {
        $due = new DateTimeImmutable((string) $task['due_at']);
        $now = new DateTimeImmutable();

        if ($due < $now) {
            return 'text-danger fw-semibold';
        }

        if ($due <= $now->modify('+48 hours')) {
            return 'text-warning fw-semibold';
        }
    } catch (Throwable) {
        return 'text-muted';
    }

    return 'text-muted';
}

function dueDateIndicator(array $task): ?array
{
    if (!empty($task['done']) || empty($task['due_at'])) {
        return null;
    }

    try {
        $due = new DateTimeImmutable((string) $task['due_at']);
        $now = new DateTimeImmutable();

        if ($due < $now) {
            return [
                'class' => 'text-bg-danger',
                'icon' => 'bi-exclamation-triangle-fill',
                'label' => 'Overdue',
            ];
        }

        if ($due <= $now->modify('+48 hours')) {
            return [
                'class' => 'text-bg-warning',
                'icon' => 'bi-clock-fill',
                'label' => 'Due soon',
            ];
        }
    } catch (Throwable) {
        return null;
    }

    return null;
}

function formatDateTimeLocalValue(?string $timestamp): string
{
    $timestamp = trim((string) $timestamp);

    if ($timestamp === '') {
        return '';
    }

    try {
        return (new DateTimeImmutable($timestamp))->format('Y-m-d\TH:i');
    } catch (Throwable) {
        return '';
    }
}

function renderTaskNode(array $task, string $columnName, string $path, string $columnSlug, string $kanbanRoute, bool $canArchive = false, int $level = 0): void
{
    $nodeId = ($level === 0 ? 'card-' : 'nested-') . $columnSlug . '-' . str_replace('.', '-', $path);
    $taskData = json_encode(taskForDataAttribute($task), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    $children = is_array($task['children'] ?? null) ? $task['children'] : [];
    $description = trim((string) ($task['description'] ?? ''));
    $tagsHtml = renderTaskTags($task);
    $dueIndicator = dueDateIndicator($task);
    $hasIncompleteSubtasks = taskHasIncompleteSubtasks($task);
    $toggleDisabled = empty($task['done']) && $hasIncompleteSubtasks;
    $isTopLevel = $level === 0;
    ?>
    <article class="card task-card task-node<?= !empty($task['done']) ? ' done' : ''; ?>" data-task="<?= e((string) $taskData); ?>">
        <div class="card-body">
            <div class="d-flex align-items-start align-items-center gap-2 mb-3">
                <span class="drag-handle text-muted" title="Drag task">
                    <i class="bi bi-grip-vertical"></i>
                </span>
                <form method="post" action="<?= e($kanbanRoute); ?>" class="d-inline" data-kanban-action-form data-kanban-update-card-on-success>
                    <input type="hidden" name="action" value="toggle_task">
                    <input type="hidden" name="column" value="<?= e($columnName); ?>">
                    <input type="hidden" name="path" value="<?= e($path); ?>">
                    <button type="submit" class="badge border-0 <?= !empty($task['done']) ? 'text-bg-success' : 'text-bg-light border text-dark'; ?> mt-1" title="<?= $toggleDisabled ? 'Complete all subtasks before marking this task done.' : 'Toggle task status'; ?>" <?= $toggleDisabled ? 'disabled' : ''; ?>>
                        <?= !empty($task['done']) ? 'Done' : 'Open'; ?>
                    </button>
                </form>
                <div class="task-title ms-auto <?= !empty($task['done']) ? 'text-decoration-line-through text-muted' : ''; ?>">
                    <div class="task-title-badges d-flex flex-wrap gap-1 justify-content-end align-items-center">
                        <form method="post" action="<?= e($kanbanRoute); ?>" class="d-inline priority-form" data-kanban-action-form data-kanban-update-card-on-success>
                            <input type="hidden" name="action" value="update_priority">
                            <input type="hidden" name="column" value="<?= e($columnName); ?>">
                            <input type="hidden" name="path" value="<?= e($path); ?>">
                            <select name="priority" class="form-select form-select-sm task-priority-select priority-<?= e(normalizeTaskPriority($task['priority'] ?? null)); ?>" title="Priority" data-kanban-priority-select>
                                <?php foreach (validTaskPriorities() as $priorityOption): ?>
                                    <option value="<?= e($priorityOption); ?>" <?= normalizeTaskPriority($task['priority'] ?? null) === $priorityOption ? 'selected' : ''; ?>>
                                        <?= e(priorityLabel($priorityOption)); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </form>
                        <?php if ($dueIndicator !== null): ?>
                            <span class="badge <?= e($dueIndicator['class']); ?> task-due-indicator" title="<?= e($dueIndicator['label']); ?>">
                                <i class="bi <?= e($dueIndicator['icon']); ?>"></i>
                                <span><?= e($dueIndicator['label']); ?></span>
                            </span>
                        <?php endif; ?>
                        <div class="dropdown task-controls">
                            <button class="btn btn-sm" type="button" data-bs-toggle="dropdown" aria-expanded="false" title="Task controls">
                                <i class="bi bi-three-dots-vertical"></i>
                            </button>
                            <ul class="dropdown-menu dropdown-menu-end">
                                <li>
                                    <button class="dropdown-item" type="button" data-bs-toggle="collapse" data-bs-target="#edit-<?= e($nodeId); ?>">
                                        <i class="bi bi-pencil"></i>
                                        Edit
                                    </button>
                                </li>
                                <li class="copy-action">
                                    <button class="dropdown-item" type="button" data-copy-task-markdown>
                                        <i class="bi bi-clipboard"></i>
                                        Copy
                                    </button>
                                </li>
                                <?php if ($isTopLevel): ?>
                                    <li>
                                        <form method="post" action="<?= e($kanbanRoute); ?>" class="archive-action" data-kanban-action-form data-kanban-patch="archive" data-kanban-confirm="Archive this task and its subtasks?">
                                            <input type="hidden" name="action" value="archive_task">
                                            <input type="hidden" name="column" value="<?= e($columnName); ?>">
                                            <input type="hidden" name="path" value="<?= e($path); ?>">
                                            <button type="submit" class="dropdown-item">
                                                <i class="bi bi-archive"></i>
                                                Archive
                                            </button>
                                        </form>
                                    </li>
                                <?php endif; ?>
                                <li>
                                    <form method="post" action="<?= e($kanbanRoute); ?>" data-kanban-action-form data-kanban-remove-card-on-success data-kanban-confirm="Delete this task and its subtasks?">
                                        <input type="hidden" name="action" value="delete_task">
                                        <input type="hidden" name="column" value="<?= e($columnName); ?>">
                                        <input type="hidden" name="path" value="<?= e($path); ?>">
                                        <button type="submit" class="dropdown-item text-danger">
                                            <i class="bi bi-trash"></i>
                                            Delete
                                        </button>
                                    </form>
                                </li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>
            <div class="d-flex align-items-start gap-2 mb-3">
                <div class="task-title flex-grow-1 <?= !empty($task['done']) ? 'text-decoration-line-through text-muted' : ''; ?>">
                    <div class="d-flex flex-column gap-2">
                        <h3 class="card-title h6 mb-0"><?= markdownInline((string) $task['title']); ?></h3>
                        <?php if ($description !== ''): ?>
                            <div class="task-description small text-muted mt-2">
                                <?= nl2br(markdownInline($description)); ?>
                            </div>
                        <?php endif; ?>
                        <?= $tagsHtml; ?>
                    </div>
                </div>
            </div>

            <div class="collapse" id="edit-<?= e($nodeId); ?>">
                <form method="post" action="<?= e($kanbanRoute); ?>" class="border rounded-3 p-2 my-3" data-kanban-action-form data-kanban-update-card-on-success>
                    <input type="hidden" name="action" value="update_task">
                    <input type="hidden" name="column" value="<?= e($columnName); ?>">
                    <input type="hidden" name="path" value="<?= e($path); ?>">

                    <label class="form-label small" for="title-<?= e($nodeId); ?>">Title</label>
                    <textarea class="form-control form-control-sm mb-2" id="title-<?= e($nodeId); ?>" name="title" rows="2" required><?= e((string) $task['title']); ?></textarea>
                    <label class="form-label small" for="description-<?= e($nodeId); ?>">Description</label>
                    <textarea class="form-control form-control-sm mb-2" id="description-<?= e($nodeId); ?>" name="description" rows="3"><?= e((string) ($task['description'] ?? '')); ?></textarea>
                    <label class="form-label small" for="due-<?= e($nodeId); ?>">Due date</label>
                    <input type="datetime-local" class="form-control form-control-sm mb-2" id="due-<?= e($nodeId); ?>" name="due_at" value="<?= e(formatDateTimeLocalValue($task['due_at'] ?? null)); ?>">
                    <label class="form-label small" for="tags-<?= e($nodeId); ?>">Tags</label>
                    <input type="hidden" name="tags_present" value="1">
                    <select class="form-select form-select-sm task-tags-select" id="tags-<?= e($nodeId); ?>" name="tags[]" multiple data-kanban-tags-select>
                        <?php foreach (normalizeTaskTags($task['tags'] ?? []) as $tag): ?>
                            <option value="<?= e($tag); ?>" selected><?= e($tag); ?></option>
                        <?php endforeach; ?>
                    </select>

                    <button type="button" class="btn btn-sm btn-secondary mt-3" title="Cancel" data-bs-toggle="collapse" data-bs-target="#edit-<?= e($nodeId); ?>">
                        Cancel
                    </button>
                    <button type="submit" class="btn btn-sm btn-success mt-3" title="Save">
                        <i class="bi bi-floppy"></i>
                    </button>
                </form>
            </div>

            <div class="nested-list <?= $children === [] ? 'nested-list-empty' : ''; ?>" data-empty-label="Drop subtask here">
                <?php foreach ($children as $childIndex => $childTask): ?>
                    <?php renderTaskNode($childTask, $columnName, $path . '.' . $childIndex, $columnSlug, $kanbanRoute, $canArchive, $level + 1); ?>
                <?php endforeach; ?>
            </div>

            <div class="task-footer d-flex flex-wrap gap-2 align-items-center justify-content-between mt-3">
                <div class="task-actions d-flex flex-wrap gap-2 align-items-center"></div>

                <?php if (!empty($task['created_at']) || !empty($task['completed_at']) || !empty($task['due_at'])): ?>
                    <div class="task-meta small text-muted text-end">
                        <?php if (!empty($task['created_at'])): ?>
                            <span>
                                <i class="bi bi-calendar-plus" title="Created"></i>
                                <?= renderTimeAgo($task['created_at'] ?? null); ?>
                            </span>
                        <?php endif; ?>
                        <?php if (!empty($task['completed_at'])): ?>
                            <span class="ms-2 text-success">
                                <i class="bi bi-calendar-check" title="Completed"></i>
                                <?= renderTimeAgo($task['completed_at'] ?? null); ?>
                            </span>
                        <?php endif; ?>
                        <?php if (!empty($task['due_at'])): ?>
                            <span class="ms-2 <?= e(dueDateClass($task)); ?>">
                                <i class="bi bi-calendar-event" title="Due"></i>
                                <?= renderTimeAgo($task['due_at'] ?? null); ?>
                            </span>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </article>
    <?php
}

// --- Added: renderArchivedTaskNode ---
function renderArchivedTaskNode(array $task, string $kanbanRoute, ?int $archiveIndex = null, int $level = 0): void
{
    $children = is_array($task['children'] ?? null) ? $task['children'] : [];
    $description = trim((string) ($task['description'] ?? ''));
    $tagsHtml = renderTaskTags($task);
    $dueIndicator = dueDateIndicator($task);
    ?>
    <article class="card task-card archived-task-node<?= !empty($task['done']) ? ' done' : ''; ?>">
        <div class="card-body">
            <div class="d-flex align-items-center justify-content-between gap-2 mb-2">
                <span class="badge <?= !empty($task['done']) ? 'text-bg-success' : 'text-bg-light border text-dark'; ?>">
                    <?= !empty($task['done']) ? 'Done' : 'Open'; ?>
                </span>
                <div class="task-title-badges d-flex flex-wrap gap-1 justify-content-end">
                    <span class="badge <?= e(priorityBadgeClass($task['priority'] ?? null)); ?> archived-priority-badge" title="Priority: <?= e(priorityLabel($task['priority'] ?? null)); ?>">
                        <?= e(priorityLabel($task['priority'] ?? null)); ?>
                    </span>
                    <?php if ($dueIndicator !== null): ?>
                        <span class="badge <?= e($dueIndicator['class']); ?> task-due-indicator" title="<?= e($dueIndicator['label']); ?>">
                            <i class="bi <?= e($dueIndicator['icon']); ?>"></i>
                            <span><?= e($dueIndicator['label']); ?></span>
                        </span>
                    <?php endif; ?>
                </div>
            </div>
            <div class="d-flex flex-column gap-2 mb-2">
                <div class="task-title <?= !empty($task['done']) ? 'text-decoration-line-through text-muted' : ''; ?>">
                    <h3 class="card-title h6 mb-0"><?= markdownInline((string) $task['title']); ?></h3>
                    <?php if ($description !== ''): ?>
                        <div class="task-description small text-muted mt-2">
                            <?= nl2br(markdownInline($description)); ?>
                        </div>
                    <?php endif; ?>
                    <?= $tagsHtml; ?>
                </div>
            </div>

            <?php if ($children !== []): ?>
                <div class="archived-nested-list mt-2">
                    <?php foreach ($children as $childTask): ?>
                        <?php renderArchivedTaskNode($childTask, $kanbanRoute, null, $level + 1); ?>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <?php if ($level === 0 && $archiveIndex !== null): ?>
                <div class="task-actions d-flex flex-wrap gap-2 align-items-center mt-3">
                    <form method="post" action="<?= e($kanbanRoute); ?>" class="d-inline" data-kanban-action-form data-kanban-patch="restore">
                        <input type="hidden" name="action" value="restore_task">
                        <input type="hidden" name="archive_index" value="<?= $archiveIndex; ?>">
                        <button type="submit" class="btn btn-sm btn-outline-success" title="Restore task">
                            <i class="bi bi-arrow-counterclockwise"></i>
                            Restore
                        </button>
                    </form>
                </div>
            <?php endif; ?>

            <?php if (!empty($task['created_at']) || !empty($task['completed_at']) || !empty($task['due_at']) || !empty($task['archived_at'])): ?>
                <div class="task-meta small text-muted text-end mt-3">
                    <?php if (!empty($task['created_at'])): ?>
                        <span>
                            <i class="bi bi-calendar-plus" title="Created"></i>
                            <?= renderTimeAgo($task['created_at'] ?? null); ?>
                        </span>
                    <?php endif; ?>
                    <?php if (!empty($task['completed_at'])): ?>
                        <span class="ms-2 text-success">
                            <i class="bi bi-calendar-check" title="Completed"></i>
                            <?= renderTimeAgo($task['completed_at'] ?? null); ?>
                        </span>
                    <?php endif; ?>
                    <?php if (!empty($task['due_at'])): ?>
                        <span class="ms-2 <?= e(dueDateClass($task)); ?>">
                            <i class="bi bi-calendar-event" title="Due"></i>
                            <?= renderTimeAgo($task['due_at'] ?? null); ?>
                        </span>
                    <?php endif; ?>
                    <?php if (!empty($task['archived_at'])): ?>
                        <span class="ms-2">
                            <i class="bi bi-archive" title="Archived"></i>
                            <?= renderTimeAgo($task['archived_at'] ?? null); ?>
                        </span>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>
    </article>
    <?php
}

?>
<style>
    .kanban-board {
        display: grid;
        grid-auto-flow: column;
        grid-auto-columns: minmax(340px, 1fr);
        gap: 1rem;
        overflow-x: auto;
        padding-bottom: 1rem;
    }
    .kanban-column {
        min-height: calc(100vh - 220px);
        background: #eef1f6;
        border: 1px solid rgba(0, 0, 0, .08);
        border-radius: 1rem;
        box-shadow: 0 .35rem 1rem rgba(0, 0, 0, .06);
    }

    .column-drag-handle {
        cursor: grab;
        line-height: 1;
    }

    .column-drag-handle:active {
        cursor: grabbing;
    }
    .column-controls .dropdown-toggle::after {
        display: none;
    }
    .task-card {
        border: 0;
        box-shadow: 0 .35rem 1rem rgba(0, 0, 0, .06);
    }

    .nested-list > .task-card {
        border: 1px solid rgba(0, 0, 0, .15);
        border-left-width: 3px;
        margin-bottom: .5rem;
        font-size: .925rem;
    }

    .nested-list > .task-card > .card-body {
        padding: .65rem .75rem;
    }

    .nested-list > .task-card .card-title {
        font-size: .925rem;
        font-weight: 400;
    }
    .archived-task-node {
        border: 1px solid rgba(0, 0, 0, .15);
        box-shadow: 0 .35rem 1rem rgba(0, 0, 0, .06);
    }
    .archived-nested-list {
        margin-left: .25rem;
        padding-left: .75rem;
    }
    .archived-nested-list > .archived-task-node {
        border-left-width: 3px;
        box-shadow: none;
        margin-bottom: .5rem;
        font-size: .925rem;
    }
    .archived-nested-list > .archived-task-node > .card-body {
        padding: .65rem .75rem;
    }
    .archived-priority-badge {
        line-height: 1;
        padding: .35em .55em;
        font-size: .7rem;
        white-space: nowrap;
    }
    .task-node {
        cursor: default;
    }
    .task-node.done .task-title {
        text-decoration: line-through;
    }
    .task-footer {
        row-gap: .5rem;
    }
    .task-meta {
        line-height: 1.35;
        word-break: break-word;
        margin-left: auto;
    }
    .task-meta .text-warning {
        color: #b58100 !important;
    }
    .task-description {
        white-space: normal;
        line-height: 1.45;
    }

    .task-description p:last-child {
        margin-bottom: 0;
    }
    .task-tags {
        line-height: 1.2;
    }
    .task-tag {
        font-size: .7rem;
        font-weight: 600;
    }
    .task-tags-select {
        width: 100%;
    }
    .task-tag-filter {
        max-width: 320px;
    }
    .task-due-indicator {
        flex: 0 0 auto;
        line-height: 1;
        padding: .35em .45em;
    }
    .task-title-badges {
        flex: 0 0 auto;
    }
    .priority-form {
        margin: 0;
    }
    .task-priority-select {
        width: auto;
        min-width: 5rem;
        border: 0;
        border-radius: 999px;
        padding: .15rem 1.65rem .15rem .55rem;
        font-size: .7rem;
        font-weight: 700;
        line-height: 1.1;
        cursor: pointer;
        background-position: right .45rem center;
        background-size: 12px 9px;
    }
    .task-priority-select.priority-low {
        color: #fff;
        background-color: #6c757d;
    }
    .task-priority-select.priority-normal {
        color: #fff;
        background-color: #0d6efd;
    }
    .task-priority-select.priority-high {
        color: #212529;
        background-color: #ffc107;
    }
    .task-priority-select.priority-urgent {
        color: #fff;
        background-color: #dc3545;
    }
    .drag-handle {
        cursor: grab;
        line-height: 1.5rem;
    }
    .drag-handle:active {
        cursor: grabbing;
    }
    button.badge {
        cursor: pointer;
    }
    button.badge:disabled {
        cursor: not-allowed;
        opacity: .65;
    }
    .task-list,
    .nested-list {
        min-height: 2.5rem;
    }
    .archive-action {
        display: block !important;
    }
    .kanban-column.hide-archive .archive-action {
        display: none !important;
    }
    .copy-action {
        display: block !important;
    }
    .kanban-column.hide-copy .copy-action {
        display: none !important;
    }
    .nested-list {
        margin-left: .25rem;
        padding-left: .5rem;
    }
    .nested-list-empty {
        display: none;
        border: 1px dashed rgba(0, 0, 0, .15);
        border-radius: .5rem;
        margin-top: .5rem;
    }

    .kanban-page.is-sorting .nested-list-empty {
        display: block;
        min-height: 3.25rem;
    }

    .nested-list-empty::before {
        content: attr(data-empty-label);
        display: block;
        color: #6c757d;
        font-size: .8rem;
        padding: .35rem .5rem;
    }

    .nested-list-empty.sortable-has-items::before {
        display: none;
    }
    .sortable-ghost {
        opacity: .45;
        outline: 2px dashed rgba(13, 110, 253, .45);
        outline-offset: 2px;
    }
    .sortable-chosen {
        transform: scale(.985);
    }
    .kanban-column.sortable-chosen {
        transform: scale(.975);
        transform-origin: center top;
    }
    .sortable-drag {
        opacity: .9;
        transform: rotate(.5deg);
    }
    .kanban-column.sortable-drag {
        transform: scale(.975);
        transform-origin: center top;
    }
    .refresh-control {
        max-width: 380px;
    }
    .kanban-page code {
        color: #d63384;
    }
</style>

<div class="kanban-page">
    <div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-4">
        <div>
            <h1 class="h3 mb-1"><?= e((string) $projectName); ?> Kanban</h1>
            <p class="text-muted mb-0">Reading and writing <code><?= e(basename($kanbanFile)); ?></code> from <code><?= e(dirname($kanbanFile)); ?></code>.</p>
        </div>
        <div class="d-flex flex-column flex-grow-1 gap-2 align-items-end justify-content-center">
            <div class="d-flex gap-2 w-100 justify-content-end">
                <div class="input-group input-group-sm refresh-control">
                    <span class="input-group-text" title="Auto refresh">
                        <i class="bi bi-arrow-clockwise"></i>
                    </span>
                    <input type="number" class="form-control" id="refreshInterval" min="0" step="1" value="0" aria-label="Refresh interval">
                    <select class="form-select" id="refreshUnit" aria-label="Refresh unit">
                        <option value="seconds">Seconds</option>
                        <option value="minutes">Minutes</option>
                    </select>
                    <button type="button" class="btn btn-outline-secondary" id="applyRefresh" title="Apply Refresh">
                        <i class="bi bi-check-lg"></i>
                    </button>
                </div>
                <div class="input-group input-group-sm task-tag-filter">
                    <span class="input-group-text">
                        <i class="bi bi-tags"></i>
                    </span>
                    <select class="form-select form-select-sm task-tag-filter-select" id="tagFilter" data-kanban-tag-filter data-placeholder="Filter by tag" multiple></select>
                </div>
            </div>
            <div>
                <span class="badge text-bg-primary rounded-pill"><?= count($columns); ?> columns</span>
                <span class="badge text-bg-secondary rounded-pill" data-total-task-count><?= $totalTasks; ?> tasks</span>
                <span class="badge text-bg-success rounded-pill"><?= $completedTasks; ?> done</span>
                <button type="button" class="badge text-bg-dark rounded-pill border-0" data-bs-toggle="modal" data-bs-target="#archiveModal" title="Open archive">
                    <?= countColumnTasks($archiveTasks ?? []); ?> archived
                </button>
            </div>
        </div>
    </div>

    <!-- OpenCode Command Toolbar -->
    <article class="card mb-4">
        <div class="card-body p-4">
            <h2 class="h5 mb-3">OpenCode Commands</h2>

            <div class="d-flex flex-wrap gap-2 mb-3">
                <button type="button" class="btn btn-outline-primary btn-sm command-btn" data-command="/plan">Plan</button>
                <button type="button" class="btn btn-outline-success btn-sm command-btn" data-command="/next">Next</button>
                <button type="button" class="btn btn-outline-info btn-sm command-btn" data-command="/debug">Debug</button>
                <button type="button" class="btn btn-outline-warning btn-sm command-btn" data-command="/review">Review</button>
                <button type="button" class="btn btn-outline-secondary btn-sm command-btn" data-command="/document">Document</button>
            </div>

            <div class="mb-3">
                <label for="command-project-slug" class="form-label small">Project Slug (optional)</label>
                <input type="text"
                       id="command-project-slug"
                       class="form-control form-control-sm"
                       placeholder="Enter project slug if needed"
                       value="<?= $projectSlug ? e($projectSlug) : '' ?>">
            </div>

            <div id="command-result">
                <!-- Command results will be displayed here -->
            </div>
        </div>
    </article>

    <form method="post" action="<?= e($kanbanRoute); ?>" class="card border-0 shadow-sm mb-4" data-kanban-action-form data-kanban-reset-on-success data-kanban-render-column-on-success>
        <input type="hidden" name="action" value="add_column">
        <div class="card-body d-flex flex-wrap gap-2 align-items-end">
            <div class="flex-grow-1">
                <label for="column" class="form-label">Add column</label>
                <input type="text" class="form-control" id="column" name="column" placeholder="Todo, In Progress, Done..." required>
            </div>
            <button type="submit" class="btn btn-primary" title="Add Column">
                <i class="bi bi-plus-lg"></i>
            </button>
        </div>
    </form>

    <?php if ($kanbanCreateError !== null): ?>
        <div class="alert alert-warning">
            <strong>KANBAN.md could not be created.</strong> <?= e($kanbanCreateError); ?>
        </div>
    <?php elseif ($columns === []): ?>
        <div class="alert alert-info">
            <strong>No Kanban items found.</strong> Add a column to start building your board.
        </div>
    <?php endif; ?>

    <?php if ($columns !== []): ?>
        <section class="kanban-board" aria-label="Kanban board">
    <?php foreach ($columns as $columnName => $tasks): ?>
        <?php $columnSlug = slugify($columnName); ?>
        <?php $isLastColumn = $columnName === array_key_last($columns); ?>
                <div class="kanban-column p-3<?= $isLastColumn ? ' is-archive-column' : ''; ?> <?= e(columnControlClass($columnSettings, $columnName)); ?>" data-column="<?= e($columnName); ?>" data-is-last-column="<?= $isLastColumn ? '1' : '0'; ?>">
                    <div class="d-flex align-items-center justify-content-between gap-2 mb-3">
                        <div class="d-flex align-items-center gap-2 min-w-0">
                            <span class="column-drag-handle text-muted" title="Drag column">
                                <i class="bi bi-grip-vertical"></i>
                            </span>
                            <h2 class="h5 mb-0 text-truncate"><?= e($columnName); ?></h2>
                        </div>
                        <div class="d-flex align-items-center gap-2">
                            <span class="badge text-bg-light border" data-column-task-count><?= countColumnTasks($tasks); ?></span>
                            <div class="dropdown column-controls">
                                <button class="btn btn-sm dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false" title="Column controls">
                                    <i class="bi bi-three-dots-vertical"></i>
                                </button>
                                <ul class="dropdown-menu dropdown-menu-end">
                                    <li><h6 class="dropdown-header">Task controls</h6></li>
                                    <li>
                                        <form method="post" action="<?= e($kanbanRoute); ?>" data-kanban-action-form data-kanban-patch="column-control">
                                            <input type="hidden" name="action" value="toggle_column_control">
                                            <input type="hidden" name="column" value="<?= e($columnName); ?>">
                                            <input type="hidden" name="control" value="copy">
                                            <button type="submit" class="dropdown-item">
                                                <i class="bi <?= columnControlHidden($columnSettings, $columnName, 'copy') ? 'bi-square' : 'bi-check-square'; ?>"></i>
                                                Copy
                                            </button>
                                        </form>
                                    </li>
                                    <li>
                                        <form method="post" action="<?= e($kanbanRoute); ?>" data-kanban-action-form data-kanban-patch="column-control">
                                            <input type="hidden" name="action" value="toggle_column_control">
                                            <input type="hidden" name="column" value="<?= e($columnName); ?>">
                                            <input type="hidden" name="control" value="archive">
                                            <button type="submit" class="dropdown-item">
                                                <i class="bi <?= columnControlHidden($columnSettings, $columnName, 'archive') ? 'bi-square' : 'bi-check-square'; ?>"></i>
                                                Archive
                                            </button>
                                        </form>
                                    </li>
                                    <li><hr class="dropdown-divider"></li>
                                    <li>
                                        <form method="post" action="<?= e($kanbanRoute); ?>" data-kanban-action-form data-kanban-remove-column-on-success data-kanban-confirm="Remove this column? Existing tasks will be moved to the first remaining column.">
                                            <input type="hidden" name="action" value="remove_column">
                                            <input type="hidden" name="column" value="<?= e($columnName); ?>">
                                            <button type="submit" class="dropdown-item text-danger" <?= count($columns) <= 1 ? 'disabled' : ''; ?>>
                                                <i class="bi bi-trash"></i>
                                                Remove
                                            </button>
                                        </form>
                                    </li>
                                </ul>
                            </div>
                        </div>
                    </div>

                    <form method="post" action="<?= e($kanbanRoute); ?>" class="mb-3" data-kanban-action-form data-kanban-reset-on-success data-kanban-render-card-on-success>
                        <input type="hidden" name="action" value="add_card">
                        <input type="hidden" name="column" value="<?= e($columnName); ?>">
                        <div class="input-group">
                            <input type="text" class="form-control" name="title" placeholder="New task..." required>
                            <button type="submit" class="btn btn-outline-primary" title="Add Task">
                                <i class="bi bi-plus-lg"></i>
                            </button>
                        </div>
                    </form>

                    <div class="text-muted small column-empty-placeholder<?= $tasks === [] ? '' : ' d-none'; ?>" data-column-empty-placeholder>
                        No cards in this column.
                    </div>

                    <div class="d-grid gap-3 task-list" data-column="<?= e($columnName); ?>">
                        <?php foreach ($tasks as $index => $task): ?>
                            <?php renderTaskNode($task, $columnName, (string) $index, $columnSlug, $kanbanRoute, $isLastColumn); ?>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </section>
    <?php endif; ?>
</div>

<div class="modal fade" id="archiveModal" tabindex="-1" aria-labelledby="archiveModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h2 class="modal-title h5" id="archiveModalLabel">
                    <i class="bi bi-archive"></i>
                    Archived tasks
                </h2>
                <span class="badge text-bg-dark rounded-pill ms-2"><?= countColumnTasks($archiveTasks ?? []); ?></span>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <?php if (($archiveTasks ?? []) === []): ?>
                    <div class="text-muted small">
                        No archived tasks yet.
                    </div>
                <?php else: ?>
                    <div class="d-grid gap-3">
                        <?php foreach ($archiveTasks as $archiveIndex => $archivedTask): ?>
                            <?php renderArchivedTaskNode($archivedTask, $kanbanRoute, (int) $archiveIndex); ?>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<script>
    (() => {
        const taskLists = document.querySelectorAll('.task-list');
        const kanbanBoard = document.querySelector('.kanban-board');
        const kanbanPage = document.querySelector('.kanban-page');
        const refreshIntervalInput = document.getElementById('refreshInterval');
        const refreshUnitSelect = document.getElementById('refreshUnit');
        const applyRefreshButton = document.getElementById('applyRefresh');
        const kanbanApiRoute = <?= json_encode('/api/kanban', JSON_UNESCAPED_SLASHES); ?>;
        const kanbanProjectSlug = <?= json_encode($projectSlug, JSON_UNESCAPED_SLASHES); ?>;
        const validTaskControls = ['archive', 'copy'];
        let refreshTimer = null;
        let isDragging = false;

        async function submitKanbanAction(actionData) {
            const formData = actionData instanceof FormData
                ? actionData
                : new FormData(actionData instanceof HTMLFormElement ? actionData : undefined);

            if (!(actionData instanceof FormData) && !(actionData instanceof HTMLFormElement) && actionData && typeof actionData === 'object') {
                Object.entries(actionData).forEach(([key, value]) => {
                    formData.set(key, value === null || value === undefined ? '' : String(value));
                });
            }

            if (kanbanProjectSlug !== null && kanbanProjectSlug !== '' && !formData.has('project_slug')) {
                formData.set('project_slug', kanbanProjectSlug);
            }

            const response = await fetch(kanbanApiRoute, {
                method: 'POST',
                body: formData,
                headers: {
                    'X-Requested-With': 'XMLHttpRequest',
                },
            });
            const payload = await response.json().catch(() => ({
                success: false,
                message: 'Invalid API response.',
            }));

            if (!response.ok || payload.success !== true) {
                throw new Error(payload.message || 'Kanban action failed.');
            }

            return payload;
        }

        window.submitKanbanAction = submitKanbanAction;

        function showKanbanActionError(error) {
            alert(error instanceof Error ? error.message : 'Kanban action failed.');
        }

        function escapeHtml(value) {
            return String(value ?? '')
                .replaceAll('&', '&amp;')
                .replaceAll('<', '&lt;')
                .replaceAll('>', '&gt;')
                .replaceAll('"', '&quot;')
                .replaceAll("'", '&#039;');
        }

        function slugifyColumn(value) {
            const slug = String(value || '')
                .trim()
                .toLowerCase()
                .replace(/[^a-z0-9]+/g, '-')
                .replace(/^-+|-+$/g, '');

            return slug !== '' ? slug : 'column';
        }

        function columnControlIcon(columnSettings, control) {
            const hiddenControls = Array.isArray(columnSettings?.hide) ? columnSettings.hide : [];
            return hiddenControls.includes(control) ? 'bi-square' : 'bi-check-square';
        }

        function columnControlClassName(columnSettings) {
            const hiddenControls = Array.isArray(columnSettings?.hide) ? columnSettings.hide : [];
            return hiddenControls
                .filter((control) => validTaskControls.includes(control))
                .map((control) => 'hide-' + control)
                .join(' ');
        }

        function renderKanbanColumn(columnState) {
            const columnName = String(columnState?.name || '').trim();

            if (columnName === '' || !kanbanBoard) {
                return;
            }

            if (document.querySelector('.kanban-column[data-column="' + CSS.escape(columnName) + '"]')) {
                return;
            }

            const columnSettings = columnState?.settings || { hide: [] };
            const columnClasses = columnControlClassName(columnSettings);
            const columnElement = document.createElement('div');
            columnElement.className = 'kanban-column p-3' + (columnClasses !== '' ? ' ' + columnClasses : '');
            columnElement.dataset.column = columnName;
            columnElement.dataset.isLastColumn = '0';

            columnElement.innerHTML = `
                <div class="d-flex align-items-center justify-content-between gap-2 mb-3">
                    <div class="d-flex align-items-center gap-2 min-w-0">
                        <span class="column-drag-handle text-muted" title="Drag column">
                            <i class="bi bi-grip-vertical"></i>
                        </span>
                        <h2 class="h5 mb-0 text-truncate">${escapeHtml(columnName)}</h2>
                    </div>
                    <div class="d-flex align-items-center gap-2">
                        <span class="badge text-bg-light border" data-column-task-count>0</span>
                        <div class="dropdown column-controls">
                            <button class="btn btn-sm dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false" title="Column controls">
                                <i class="bi bi-three-dots-vertical"></i>
                            </button>
                            <ul class="dropdown-menu dropdown-menu-end">
                                <li><h6 class="dropdown-header">Task controls</h6></li>
                                <li>
                                    <form method="post" action="${escapeHtml(<?= json_encode($kanbanRoute, JSON_UNESCAPED_SLASHES); ?>)}" data-kanban-action-form data-kanban-patch="column-control">
                                        <input type="hidden" name="action" value="toggle_column_control">
                                        <input type="hidden" name="column" value="${escapeHtml(columnName)}">
                                        <input type="hidden" name="control" value="copy">
                                        <button type="submit" class="dropdown-item">
                                            <i class="bi ${columnControlIcon(columnSettings, 'copy')}"></i>
                                            Copy
                                        </button>
                                    </form>
                                </li>
                                <li>
                                    <form method="post" action="${escapeHtml(<?= json_encode($kanbanRoute, JSON_UNESCAPED_SLASHES); ?>)}" data-kanban-action-form data-kanban-patch="column-control">
                                        <input type="hidden" name="action" value="toggle_column_control">
                                        <input type="hidden" name="column" value="${escapeHtml(columnName)}">
                                        <input type="hidden" name="control" value="archive">
                                        <button type="submit" class="dropdown-item">
                                            <i class="bi ${columnControlIcon(columnSettings, 'archive')}"></i>
                                            Archive
                                        </button>
                                    </form>
                                </li>
                                <li><hr class="dropdown-divider"></li>
                                <li>
                                    <form method="post" action="${escapeHtml(<?= json_encode($kanbanRoute, JSON_UNESCAPED_SLASHES); ?>)}" data-kanban-action-form data-kanban-remove-column-on-success data-kanban-confirm="Remove this column? Existing tasks will be moved to the first remaining column.">
                                        <input type="hidden" name="action" value="remove_column">
                                        <input type="hidden" name="column" value="${escapeHtml(columnName)}">
                                        <button type="submit" class="dropdown-item text-danger">
                                            <i class="bi bi-trash"></i>
                                            Remove
                                        </button>
                                    </form>
                                </li>
                            </ul>
                        </div>
                    </div>
                </div>

                <form method="post" action="${escapeHtml(<?= json_encode($kanbanRoute, JSON_UNESCAPED_SLASHES); ?>)}" class="mb-3" data-kanban-action-form data-kanban-reset-on-success data-kanban-render-card-on-success>
                    <input type="hidden" name="action" value="add_card">
                    <input type="hidden" name="column" value="${escapeHtml(columnName)}">
                    <div class="input-group">
                        <input type="text" class="form-control" name="title" placeholder="New task..." required>
                        <button type="submit" class="btn btn-outline-primary" title="Add Task">
                            <i class="bi bi-plus-lg"></i>
                        </button>
                    </div>
                </form>

                <div class="text-muted small column-empty-placeholder" data-column-empty-placeholder>
                    No cards in this column.
                </div>

                <div class="d-grid gap-3 task-list" data-column="${escapeHtml(columnName)}"></div>
            `;

            kanbanBoard.appendChild(columnElement);

            const taskList = columnElement.querySelector('.task-list');
            if (taskList) {
                initializeTaskSortable(taskList);
            }

            updateColumnState();
            updateTaskCounts();
            updateColumnEmptyPlaceholders();
        }

        function renderColumnFromPayload(payload) {
            const columnName = String(payload?.data?.column || '').trim();
            const columns = Array.isArray(payload?.board?.columns) ? payload.board.columns : [];
            const columnState = columns.find((column) => String(column?.name || '') === columnName) || {
                name: columnName,
                task_count: 0,
                settings: { hide: [] },
            };

            renderKanbanColumn(columnState);
        }

        function taskTitleHtml(value) {
            return escapeHtml(value)
                .replace(/`([^`]+)`/g, '<code>$1</code>')
                .replace(/\*\*([^*]+)\*\*/g, '<strong>$1</strong>')
                .replace(/\*([^*]+)\*/g, '<em>$1</em>');
        }

        function normalizeTaskPriorityForRender(priority) {
            const value = String(priority || '').trim().toLowerCase();
            return ['low', 'normal', 'high', 'urgent'].includes(value) ? value : 'normal';
        }

        function priorityLabelForRender(priority) {
            const normalized = normalizeTaskPriorityForRender(priority);
            return normalized.charAt(0).toUpperCase() + normalized.slice(1);
        }

        function formatDateTimeLocalValueForRender(timestamp) {
            const value = String(timestamp || '').trim();

            if (value === '') {
                return '';
            }

            const date = new Date(value);

            if (Number.isNaN(date.getTime())) {
                return '';
            }

            const pad = (number) => String(number).padStart(2, '0');

            return date.getFullYear()
                + '-' + pad(date.getMonth() + 1)
                + '-' + pad(date.getDate())
                + 'T' + pad(date.getHours())
                + ':' + pad(date.getMinutes());
        }

        function formatTaskDateTooltipForRender(timestamp) {
            const value = String(timestamp || '').trim();

            if (value === '') {
                return '';
            }

            const date = new Date(value);

            if (Number.isNaN(date.getTime())) {
                return value;
            }

            return date.toLocaleString(undefined, {
                year: 'numeric',
                month: 'long',
                day: 'numeric',
                hour: 'numeric',
                minute: '2-digit',
                timeZoneName: 'short',
            });
        }

        function renderTimeAgoHtml(timestamp) {
            const value = String(timestamp || '').trim();

            if (value === '') {
                return '';
            }

            const tooltip = formatTaskDateTooltipForRender(value);

            return '<time class="timeago" datetime="' + escapeHtml(value) + '" title="' + escapeHtml(tooltip) + '">' + escapeHtml(tooltip) + '</time>';
        }

        function dueDateClassForRender(task) {
            if (task?.done || !task?.due_at) {
                return 'text-muted';
            }

            const due = new Date(task.due_at);

            if (Number.isNaN(due.getTime())) {
                return 'text-muted';
            }

            const now = new Date();
            const soon = new Date(now.getTime() + (48 * 60 * 60 * 1000));

            if (due < now) {
                return 'text-danger fw-semibold';
            }

            if (due <= soon) {
                return 'text-warning fw-semibold';
            }

            return 'text-muted';
        }

        function dueDateIndicatorForRender(task) {
            if (task?.done || !task?.due_at) {
                return null;
            }

            const due = new Date(task.due_at);

            if (Number.isNaN(due.getTime())) {
                return null;
            }

            const now = new Date();
            const soon = new Date(now.getTime() + (48 * 60 * 60 * 1000));

            if (due < now) {
                return {
                    class: 'text-bg-danger',
                    icon: 'bi-exclamation-triangle-fill',
                    label: 'Overdue',
                };
            }

            if (due <= soon) {
                return {
                    class: 'text-bg-warning',
                    icon: 'bi-clock-fill',
                    label: 'Due soon',
                };
            }

            return null;
        }

        function taskHasIncompleteSubtasksForRender(task) {
            const children = Array.isArray(task?.children) ? task.children : [];

            return children.some((child) => !child.done || taskHasIncompleteSubtasksForRender(child));
        }

        function taskDataAttribute(task) {
            return JSON.stringify({
                done: task?.done === true,
                title: String(task?.title || ''),
                description: String(task?.description || ''),
                tags: Array.isArray(task?.tags) ? task.tags : [],
                type: String(task?.type || 'task'),
                created_at: task?.created_at || null,
                completed_at: task?.completed_at || null,
                due_at: task?.due_at || null,
                archived_at: task?.archived_at || null,
                archived_from: task?.archived_from || null,
                priority: task?.priority || null,
            });
        }

        function normalizeTaskTagsForRender(tags) {
            if (Array.isArray(tags)) {
                return [...new Set(tags.map((tag) => String(tag || '').trim().toLowerCase()).filter(Boolean))];
            }

            return String(tags || '')
                .split(',')
                .map((tag) => tag.trim().toLowerCase())
                .filter(Boolean)
                .filter((tag, index, all) => all.indexOf(tag) === index);
        }

        function renderTaskTagsHtml(tags) {
            const normalizedTags = normalizeTaskTagsForRender(tags);

            if (normalizedTags.length === 0) {
                return '';
            }

            return `
                <div class="task-tags d-flex flex-wrap gap-1 mt-2">
                    ${normalizedTags.map((tag) => `<span class="badge text-bg-light border task-tag" data-task-tag="${escapeHtml(tag)}">#${escapeHtml(tag)}</span>`).join('')}
                </div>`;
        }

        function renderTaskNodeFromPayload(task, columnName, path, columnSlug, level = 0) {
            const normalizedTask = task && typeof task === 'object' ? task : {};
            const nodeId = (level === 0 ? 'card-' : 'nested-') + columnSlug + '-' + String(path).replaceAll('.', '-');
            const children = Array.isArray(normalizedTask.children) ? normalizedTask.children : [];
            const dueIndicator = dueDateIndicatorForRender(normalizedTask);
            const hasIncompleteSubtasks = taskHasIncompleteSubtasksForRender(normalizedTask);
            const toggleDisabled = !normalizedTask.done && hasIncompleteSubtasks;
            const isTopLevel = level === 0;
            const doneClass = normalizedTask.done ? ' done' : '';
            const titleDoneClass = normalizedTask.done ? ' text-decoration-line-through text-muted' : '';
            const statusClass = normalizedTask.done ? 'text-bg-success' : 'text-bg-light border text-dark';
            const statusLabel = normalizedTask.done ? 'Done' : 'Open';
            const toggleTitle = toggleDisabled ? 'Complete all subtasks before marking this task done.' : 'Toggle task status';
            const priority = normalizeTaskPriorityForRender(normalizedTask.priority);
            const description = String(normalizedTask.description || '').trim();
            const descriptionHtml = description === '' ? '' : `
                                <div class="task-description small text-muted mt-2">
                                    ${taskTitleHtml(description).replace(/\n/g, '<br>')}
                                </div>`;
            const tags = normalizeTaskTagsForRender(normalizedTask.tags);
            const tagsHtml = renderTaskTagsHtml(tags);
            const dueIndicatorHtml = dueIndicator === null ? '' : `
                            <span class="badge ${escapeHtml(dueIndicator.class)} task-due-indicator" title="${escapeHtml(dueIndicator.label)}">
                                <i class="bi ${escapeHtml(dueIndicator.icon)}"></i>
                                <span>${escapeHtml(dueIndicator.label)}</span>
                            </span>`;
            const priorityOptionsHtml = ['low', 'normal', 'high', 'urgent'].map((priorityOption) => `
                                    <option value="${escapeHtml(priorityOption)}" ${priority === priorityOption ? 'selected' : ''}>
                                        ${escapeHtml(priorityLabelForRender(priorityOption))}
                                    </option>`).join('');
            const archiveActionHtml = isTopLevel ? `
                                    <li>
                                        <form method="post" action="${escapeHtml(<?= json_encode($kanbanRoute, JSON_UNESCAPED_SLASHES); ?>)}" class="archive-action" data-kanban-action-form data-kanban-patch="archive" data-kanban-confirm="Archive this task and its subtasks?">
                                            <input type="hidden" name="action" value="archive_task">
                                            <input type="hidden" name="column" value="${escapeHtml(columnName)}">
                                            <input type="hidden" name="path" value="${escapeHtml(path)}">
                                            <button type="submit" class="dropdown-item">
                                                <i class="bi bi-archive"></i>
                                                Archive
                                            </button>
                                        </form>
                                    </li>` : '';
            const childrenHtml = children.map((childTask, childIndex) => renderTaskNodeFromPayload(childTask, columnName, path + '.' + childIndex, columnSlug, level + 1)).join('');
            const createdAtHtml = normalizedTask.created_at ? `
                            <span>
                                <i class="bi bi-calendar-plus" title="Created"></i>
                                ${renderTimeAgoHtml(normalizedTask.created_at)}
                            </span>` : '';
            const completedAtHtml = normalizedTask.completed_at ? `
                            <span class="ms-2 text-success">
                                <i class="bi bi-calendar-check" title="Completed"></i>
                                ${renderTimeAgoHtml(normalizedTask.completed_at)}
                            </span>` : '';
            const dueAtHtml = normalizedTask.due_at ? `
                            <span class="ms-2 ${escapeHtml(dueDateClassForRender(normalizedTask))}">
                                <i class="bi bi-calendar-event" title="Due"></i>
                                ${renderTimeAgoHtml(normalizedTask.due_at)}
                            </span>` : '';
            const metaHtml = createdAtHtml !== '' || completedAtHtml !== '' || dueAtHtml !== '' ? `
                    <div class="task-meta small text-muted text-end">
                        ${createdAtHtml}${completedAtHtml}${dueAtHtml}
                    </div>` : '';

            return `
    <article class="card task-card task-node${doneClass}" data-task="${escapeHtml(taskDataAttribute(normalizedTask))}">
        <div class="card-body">
            <div class="d-flex align-items-start align-items-center gap-2 mb-3">
                <span class="drag-handle text-muted" title="Drag task">
                    <i class="bi bi-grip-vertical"></i>
                </span>
                <form method="post" action="${escapeHtml(<?= json_encode($kanbanRoute, JSON_UNESCAPED_SLASHES); ?>)}" class="d-inline" data-kanban-action-form data-kanban-update-card-on-success>
                    <input type="hidden" name="action" value="toggle_task">
                    <input type="hidden" name="column" value="${escapeHtml(columnName)}">
                    <input type="hidden" name="path" value="${escapeHtml(path)}">
                    <button type="submit" class="badge border-0 ${statusClass} mt-1" title="${escapeHtml(toggleTitle)}" ${toggleDisabled ? 'disabled' : ''}>
                        ${statusLabel}
                    </button>
                </form>
                <div class="task-title ms-auto${titleDoneClass}">
                    <div class="task-title-badges d-flex flex-wrap gap-1 justify-content-end align-items-center">
                        <form method="post" action="${escapeHtml(<?= json_encode($kanbanRoute, JSON_UNESCAPED_SLASHES); ?>)}" class="d-inline priority-form" data-kanban-action-form data-kanban-update-card-on-success>
                            <input type="hidden" name="action" value="update_priority">
                            <input type="hidden" name="column" value="${escapeHtml(columnName)}">
                            <input type="hidden" name="path" value="${escapeHtml(path)}">
                            <select name="priority" class="form-select form-select-sm task-priority-select priority-${escapeHtml(priority)}" title="Priority" data-kanban-priority-select>
                                ${priorityOptionsHtml}
                            </select>
                        </form>
                        ${dueIndicatorHtml}
                        <div class="dropdown task-controls">
                            <button class="btn btn-sm" type="button" data-bs-toggle="dropdown" aria-expanded="false" title="Task controls">
                                <i class="bi bi-three-dots-vertical"></i>
                            </button>
                            <ul class="dropdown-menu dropdown-menu-end">
                                <li>
                                    <button class="dropdown-item" type="button" data-bs-toggle="collapse" data-bs-target="#edit-${escapeHtml(nodeId)}">
                                        <i class="bi bi-pencil"></i>
                                        Edit
                                    </button>
                                </li>
                                <li class="copy-action">
                                    <button class="dropdown-item" type="button" data-copy-task-markdown>
                                        <i class="bi bi-clipboard"></i>
                                        Copy
                                    </button>
                                </li>
                                ${archiveActionHtml}
                                <li>
                                    <form method="post" action="${escapeHtml(<?= json_encode($kanbanRoute, JSON_UNESCAPED_SLASHES); ?>)}" data-kanban-action-form data-kanban-remove-card-on-success data-kanban-confirm="Delete this task and its subtasks?">
                                        <input type="hidden" name="action" value="delete_task">
                                        <input type="hidden" name="column" value="${escapeHtml(columnName)}">
                                        <input type="hidden" name="path" value="${escapeHtml(path)}">
                                        <button type="submit" class="dropdown-item text-danger">
                                            <i class="bi bi-trash"></i>
                                            Delete
                                        </button>
                                    </form>
                                </li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>
            <div class="d-flex align-items-start gap-2 mb-3">
                <div class="task-title flex-grow-1${titleDoneClass}">
                    <div class="d-flex flex-column gap-2">
                        <h3 class="card-title h6 mb-0">${taskTitleHtml(normalizedTask.title || '')}</h3>
                        ${descriptionHtml}
                        ${tagsHtml}
                    </div>
                </div>
            </div>

            <div class="collapse" id="edit-${escapeHtml(nodeId)}">
                <form method="post" action="${escapeHtml(<?= json_encode($kanbanRoute, JSON_UNESCAPED_SLASHES); ?>)}" class="border rounded-3 p-2 my-3" data-kanban-action-form data-kanban-update-card-on-success>
                    <input type="hidden" name="action" value="update_task">
                    <input type="hidden" name="column" value="${escapeHtml(columnName)}">
                    <input type="hidden" name="path" value="${escapeHtml(path)}">

                    <label class="form-label small" for="title-${escapeHtml(nodeId)}">Title</label>
                    <textarea class="form-control form-control-sm mb-2" id="title-${escapeHtml(nodeId)}" name="title" rows="2" required>${escapeHtml(normalizedTask.title || '')}</textarea>
                    <label class="form-label small" for="description-${escapeHtml(nodeId)}">Description</label>
                    <textarea class="form-control form-control-sm mb-2" id="description-${escapeHtml(nodeId)}" name="description" rows="3">${escapeHtml(normalizedTask.description || '')}</textarea>
                    <label class="form-label small" for="due-${escapeHtml(nodeId)}">Due date</label>
                    <input type="datetime-local" class="form-control form-control-sm mb-2" id="due-${escapeHtml(nodeId)}" name="due_at" value="${escapeHtml(formatDateTimeLocalValueForRender(normalizedTask.due_at))}">
                    <label class="form-label small" for="tags-${escapeHtml(nodeId)}">Tags</label>
                    <input type="hidden" name="tags_present" value="1">
                    <select class="form-select form-select-sm task-tags-select" id="tags-${escapeHtml(nodeId)}" name="tags[]" multiple data-kanban-tags-select>
                        ${tags.map((tag) => `<option value="${escapeHtml(tag)}" selected>${escapeHtml(tag)}</option>`).join('')}
                    </select>

                    <button type="button" class="btn btn-sm btn-secondary mt-3" title="Cancel" data-bs-toggle="collapse" data-bs-target="#edit-${escapeHtml(nodeId)}">
                        Cancel
                    </button>
                    <button type="submit" class="btn btn-sm btn-success mt-3" title="Save">
                        <i class="bi bi-floppy"></i>
                    </button>
                </form>
            </div>

            <div class="nested-list ${children.length === 0 ? 'nested-list-empty' : ''}" data-empty-label="Drop subtask here">
                ${childrenHtml}
            </div>

            <div class="task-footer d-flex flex-wrap gap-2 align-items-center justify-content-between mt-3">
                <div class="task-actions d-flex flex-wrap gap-2 align-items-center"></div>
                ${metaHtml}
            </div>
        </div>
    </article>`;
        }

        function priorityBadgeClassForRender(priority) {
            const normalized = normalizeTaskPriorityForRender(priority);

            if (normalized === 'low') {
                return 'text-bg-secondary';
            }

            if (normalized === 'high') {
                return 'text-bg-warning';
            }

            if (normalized === 'urgent') {
                return 'text-bg-danger';
            }

            return 'text-bg-primary';
        }

        function renderArchivedTaskNodeFromPayload(task, archiveIndex = null, level = 0) {
            const normalizedTask = task && typeof task === 'object' ? task : {};
            const children = Array.isArray(normalizedTask.children) ? normalizedTask.children : [];
            const dueIndicator = dueDateIndicatorForRender(normalizedTask);
            const doneClass = normalizedTask.done ? ' done' : '';
            const titleDoneClass = normalizedTask.done ? ' text-decoration-line-through text-muted' : '';
            const statusClass = normalizedTask.done ? 'text-bg-success' : 'text-bg-light border text-dark';
            const statusLabel = normalizedTask.done ? 'Done' : 'Open';
            const priority = normalizeTaskPriorityForRender(normalizedTask.priority);
            const description = String(normalizedTask.description || '').trim();
            const descriptionHtml = description === '' ? '' : `
                                        <div class="task-description small text-muted mt-2">
                                            ${taskTitleHtml(description).replace(/\n/g, '<br>')}
                                        </div>`;
            const tags = normalizeTaskTagsForRender(normalizedTask.tags);
            const tagsHtml = renderTaskTagsHtml(tags);
            const dueIndicatorHtml = dueIndicator === null ? '' : `
                                <span class="badge ${escapeHtml(dueIndicator.class)} task-due-indicator" title="${escapeHtml(dueIndicator.label)}">
                                    <i class="bi ${escapeHtml(dueIndicator.icon)}"></i>
                                    <span>${escapeHtml(dueIndicator.label)}</span>
                                </span>`;
            const childrenHtml = children.length === 0 ? '' : `
                    <div class="archived-nested-list mt-2">
                        ${children.map((childTask) => renderArchivedTaskNodeFromPayload(childTask, null, level + 1)).join('')}
                    </div>`;
            const restoreHtml = level === 0 && archiveIndex !== null ? `
                    <div class="task-actions d-flex flex-wrap gap-2 align-items-center mt-3">
                        <form method="post" action="${escapeHtml(<?= json_encode($kanbanRoute, JSON_UNESCAPED_SLASHES); ?>)}" class="d-inline" data-kanban-action-form data-kanban-patch="restore">
                            <input type="hidden" name="action" value="restore_task">
                            <input type="hidden" name="archive_index" value="${escapeHtml(String(archiveIndex))}">
                            <button type="submit" class="btn btn-sm btn-outline-success" title="Restore task">
                                <i class="bi bi-arrow-counterclockwise"></i>
                                Restore
                            </button>
                        </form>
                    </div>` : '';
            const createdAtHtml = normalizedTask.created_at ? `
                            <span>
                                <i class="bi bi-calendar-plus" title="Created"></i>
                                ${renderTimeAgoHtml(normalizedTask.created_at)}
                            </span>` : '';
            const completedAtHtml = normalizedTask.completed_at ? `
                            <span class="ms-2 text-success">
                                <i class="bi bi-calendar-check" title="Completed"></i>
                                ${renderTimeAgoHtml(normalizedTask.completed_at)}
                            </span>` : '';
            const dueAtHtml = normalizedTask.due_at ? `
                            <span class="ms-2 ${escapeHtml(dueDateClassForRender(normalizedTask))}">
                                <i class="bi bi-calendar-event" title="Due"></i>
                                ${renderTimeAgoHtml(normalizedTask.due_at)}
                            </span>` : '';
            const archivedAtHtml = normalizedTask.archived_at ? `
                            <span class="ms-2">
                                <i class="bi bi-archive" title="Archived"></i>
                                ${renderTimeAgoHtml(normalizedTask.archived_at)}
                            </span>` : '';
            const metaHtml = createdAtHtml !== '' || completedAtHtml !== '' || dueAtHtml !== '' || archivedAtHtml !== '' ? `
                    <div class="task-meta small text-muted text-end mt-3">
                        ${createdAtHtml}${completedAtHtml}${dueAtHtml}${archivedAtHtml}
                    </div>` : '';

            return `
        <article class="card task-card archived-task-node${doneClass}">
            <div class="card-body">
                <div class="d-flex align-items-start gap-2 mb-2">
                    <span class="badge ${statusClass} mt-1">
                        ${statusLabel}
                    </span>
                    <div class="task-title-badges d-flex flex-wrap gap-1 justify-content-end">
                        <span class="badge ${escapeHtml(priorityBadgeClassForRender(priority))} archived-priority-badge" title="Priority: ${escapeHtml(priorityLabelForRender(priority))}">
                            ${escapeHtml(priorityLabelForRender(priority))}
                        </span>
                        ${dueIndicatorHtml}
                    </div>
                </div>
                <div class="d-flex flex-column gap-2 mb-2">
                    <div class="task-title${titleDoneClass}">
                        <h3 class="card-title h6 mb-0">${taskTitleHtml(normalizedTask.title || '')}</h3>
                        ${descriptionHtml}
                        ${tagsHtml}
                    </div>
                </div>
                ${childrenHtml}
                ${restoreHtml}
                ${metaHtml}
            </div>
        </article>`;
        }

        function appendArchivedTaskFromPayload(payload) {
            const task = payload?.data?.task;

            if (!task) {
                return;
            }

            const modalBody = document.querySelector('#archiveModal .modal-body');

            if (!modalBody) {
                return;
            }

            let archivedList = modalBody.querySelector(':scope > .d-grid.gap-3');

            if (!archivedList) {
                modalBody.innerHTML = '<div class="d-grid gap-3"></div>';
                archivedList = modalBody.querySelector(':scope > .d-grid.gap-3');
            }

            if (!archivedList) {
                return;
            }

            const archiveIndex = payload?.data?.archive_index ?? payload?.data?.index ?? Math.max(0, archivedList.querySelectorAll(':scope > .archived-task-node').length);
            const wrapper = document.createElement('div');
            wrapper.innerHTML = renderArchivedTaskNodeFromPayload(task, archiveIndex).trim();
            const archivedNode = wrapper.firstElementChild;

            if (!archivedNode) {
                return;
            }

            archivedList.appendChild(archivedNode);

            initializeTagSelects(archivedNode);

            if (window.timeago) {
                window.timeago.render(archivedNode.querySelectorAll('.timeago'));
            }

            initializeTagFilter();
            applyTagFilter();
        }

        function updateCardFromPayload(payload) {
            const columnName = String(payload?.data?.column || '').trim();
            const path = String(payload?.data?.path || '').trim();
            const task = payload?.data?.task;

            if (columnName === '' || path === '' || !task) {
                return;
            }

            const columnElement = document.querySelector('.kanban-column[data-column="' + CSS.escape(columnName) + '"]');
            const taskList = columnElement?.querySelector(':scope > .task-list');

            if (!taskList) {
                return;
            }

            let existingCard = null;

            function findNode(currentNode, currentPath) {
                if (currentPath === path) {
                    existingCard = currentNode;
                    return;
                }

                const childList = currentNode.querySelector(':scope > .card-body > .nested-list');

                if (!childList) {
                    return;
                }

                directTaskChildren(childList).forEach((childNode, childIndex) => {
                    findNode(childNode, currentPath + '.' + childIndex);
                });
            }

            directTaskChildren(taskList).forEach((node, index) => {
                findNode(node, String(index));
            });

            if (!existingCard) {
                return;
            }

            const columnSlug = slugifyColumn(columnName);
            const level = path.includes('.') ? path.split('.').length - 1 : 0;
            const wrapper = document.createElement('div');

            wrapper.innerHTML = renderTaskNodeFromPayload(task, columnName, path, columnSlug, level).trim();

            const replacementCard = wrapper.firstElementChild;

            if (!replacementCard) {
                return;
            }

            existingCard.replaceWith(replacementCard);

            replacementCard.querySelectorAll('.nested-list').forEach(initializeTaskSortable);

            initializeTagSelects(replacementCard);

            if (window.timeago) {
                window.timeago.render(replacementCard.querySelectorAll('.timeago'));
            }

            initializeTagFilter();
            applyTagFilter();
            refreshTaskFormPaths();
            updateEmptyNestedLists();
            updateTaskCounts();
            updateColumnEmptyPlaceholders();
            updateTaskToggleGuards();
        }

        function updateTaskToggleGuards() {
            document.querySelectorAll('.task-node').forEach((taskNode) => {
                const task = serializeTaskNode(taskNode);
                const toggleForm = Array.from(taskNode.querySelectorAll(':scope > .card-body > .d-flex form[data-kanban-action-form]')).find((form) => {
                    return form.querySelector('input[name="action"][value="toggle_task"]') !== null;
                });

                if (!(toggleForm instanceof HTMLFormElement)) {
                    return;
                }

                const toggleButton = toggleForm.querySelector('button[type="submit"]');

                if (!(toggleButton instanceof HTMLButtonElement)) {
                    return;
                }

                const shouldDisable = !task.done && taskHasIncompleteSubtasks(task);
                toggleButton.disabled = shouldDisable;
                toggleButton.title = shouldDisable
                    ? 'Complete all subtasks before marking this task done.'
                    : 'Toggle task status';
            });
        }

        function removeCardFromPayload(payload) {
            const columnName = String(payload?.data?.column || '').trim();
            const path = String(payload?.data?.path || '').trim();

            if (columnName === '' || path === '') {
                return;
            }

            const columnElement = document.querySelector('.kanban-column[data-column="' + CSS.escape(columnName) + '"]');
            const taskList = columnElement?.querySelector(':scope > .task-list');

            if (!taskList) {
                return;
            }

            let existingCard = null;

            function findNode(currentNode, currentPath) {
                if (currentPath === path) {
                    existingCard = currentNode;
                    return;
                }

                const childList = currentNode.querySelector(':scope > .card-body > .nested-list');

                if (!childList) {
                    return;
                }

                directTaskChildren(childList).forEach((childNode, childIndex) => {
                    findNode(childNode, currentPath + '.' + childIndex);
                });
            }

            directTaskChildren(taskList).forEach((node, index) => {
                findNode(node, String(index));
            });

            if (!existingCard) {
                return;
            }

            existingCard.remove();

            initializeTagFilter();
            applyTagFilter();
            refreshTaskFormPaths();
            updateEmptyNestedLists();
            updateTaskCounts();
            updateColumnEmptyPlaceholders();
            updateTaskToggleGuards();
        }

        function updateColumnControlsFromPayload(payload) {
            const columnName = String(payload?.data?.column || '').trim();

            if (columnName === '') {
                return;
            }

            const columnElement = document.querySelector('.kanban-column[data-column="' + CSS.escape(columnName) + '"]');

            if (!columnElement) {
                return;
            }

            const settings = payload?.data?.settings || { hide: [] };
            const hiddenControls = Array.isArray(settings.hide) ? settings.hide : [];

            validTaskControls.forEach((control) => {
                columnElement.classList.toggle('hide-' + control, hiddenControls.includes(control));
            });

            columnElement.querySelectorAll('form[data-kanban-patch="column-control"]').forEach((form) => {
                const control = form.querySelector('input[name="control"]')?.value || '';
                const icon = form.querySelector('button[type="submit"] .bi');

                if (!validTaskControls.includes(control) || !(icon instanceof HTMLElement)) {
                    return;
                }

                icon.classList.toggle('bi-square', hiddenControls.includes(control));
                icon.classList.toggle('bi-check-square', !hiddenControls.includes(control));
            });
        }

        function removeColumnFromPayload(payload) {
            const columnName = String(payload?.data?.column || '').trim();

            if (columnName === '') {
                return;
            }

            const columnElement = document.querySelector('.kanban-column[data-column="' + CSS.escape(columnName) + '"]');

            if (!columnElement) {
                return;
            }

            columnElement.remove();

            updateColumnState();
            refreshTaskFormPaths();
            updateEmptyNestedLists();
            updateTaskCounts();
            updateColumnEmptyPlaceholders();
            updateTaskToggleGuards();
            initializeTagFilter();
            applyTagFilter();
        }

        function archiveTaskFromPayload(payload) {
            removeCardFromPayload(payload);
            appendArchivedTaskFromPayload(payload);
            updateTaskCounts();
            updateColumnEmptyPlaceholders();

            const archiveBadge = document.querySelector('#archiveModalLabel + .badge');
            const archiveButton = document.querySelector('[data-bs-target="#archiveModal"]');

            if (payload.board && typeof payload.board.archive_count !== 'undefined') {
                if (archiveBadge) {
                    archiveBadge.textContent = String(payload.board.archive_count);
                }

                if (archiveButton) {
                    archiveButton.textContent = payload.board.archive_count + ' archived';
                }
            }

            initializeTagFilter();
            applyTagFilter();
        }

        function restoreTaskFromPayload(payload) {
            const archiveIndex = payload?.data?.archive_index;

            if (archiveIndex !== undefined) {
                const archiveInput = document.querySelector('form[data-kanban-patch="restore"] input[name="archive_index"][value="' + CSS.escape(String(archiveIndex)) + '"]');
                const archivedNode = archiveInput instanceof HTMLInputElement
                    ? archiveInput.closest('.archived-task-node')
                    : null;

                if (archivedNode) {
                    archivedNode.remove();
                }

                const modalBody = document.querySelector('#archiveModal .modal-body');
                const archivedList = modalBody?.querySelector(':scope > .d-grid.gap-3');

                if (modalBody && archivedList && archivedList.querySelectorAll(':scope > .archived-task-node').length === 0) {
                    modalBody.innerHTML = '<div class="text-muted small">No archived tasks yet.</div>';
                }
            }

            if (payload?.data?.column && payload?.data?.task) {
                renderCardFromPayload(payload);
            }
            updateTaskCounts();
            updateColumnEmptyPlaceholders();

            const archiveBadge = document.querySelector('#archiveModalLabel + .badge');
            const archiveButton = document.querySelector('[data-bs-target="#archiveModal"]');

            if (payload.board && typeof payload.board.archive_count !== 'undefined') {
                if (archiveBadge) {
                    archiveBadge.textContent = String(payload.board.archive_count);
                }

                if (archiveButton) {
                    archiveButton.textContent = payload.board.archive_count + ' archived';
                }
            }

            initializeTagFilter();
            applyTagFilter();
        }

        function renderCardFromPayload(payload) {
            const columnName = String(payload?.data?.column || '').trim();
            const task = payload?.data?.task;

            if (columnName === '' || !task) {
                return;
            }

            const columnElement = document.querySelector(
                '.kanban-column[data-column="' + CSS.escape(columnName) + '"]'
            );
            const taskList = columnElement?.querySelector(':scope > .task-list');

            if (!taskList) {
                return;
            }

            const index = Number.isInteger(payload?.data?.index)
                ? payload.data.index
                : directTaskChildren(taskList).length;
            const columnSlug = slugifyColumn(columnName);
            const wrapper = document.createElement('div');
            wrapper.innerHTML = renderTaskNodeFromPayload(task, columnName, String(index), columnSlug).trim();
            const card = wrapper.firstElementChild;

            if (!card) {
                return;
            }

            taskList.appendChild(card);

            card.querySelectorAll('.nested-list').forEach(initializeTaskSortable);

            initializeTagSelects(card);

            if (window.timeago) {
                window.timeago.render(card.querySelectorAll('.timeago'));
            }

            initializeTagFilter();
            applyTagFilter();
            refreshTaskFormPaths();
            updateEmptyNestedLists();
            updateTaskCounts();
            updateColumnEmptyPlaceholders();
            updateTaskToggleGuards();
        }

        async function handleKanbanActionFormSubmit(form, event) {
            event.preventDefault();
            event.stopPropagation();

            const confirmMessage = form.getAttribute('data-kanban-confirm');

            if (confirmMessage && !window.confirm(confirmMessage)) {
                return;
            }

            const submitButton = event.submitter instanceof HTMLButtonElement
                ? event.submitter
                : form.querySelector('button[type="submit"]');

            if (submitButton && submitButton.disabled) {
                submitButton.setAttribute('data-was-disabled', '1');
            }
            if (submitButton) {
                submitButton.disabled = true;
            }

            try {
                const payload = await submitKanbanAction(form);

                const patchType = form.getAttribute('data-kanban-patch');

                if (patchType === 'archive') {
                    archiveTaskFromPayload(payload);
                    return;
                }

                if (patchType === 'restore') {
                    restoreTaskFromPayload(payload);
                    return;
                }

                if (patchType === 'column-control') {
                    updateColumnControlsFromPayload(payload);
                    return;
                }

                if (submitButton) {
                    submitButton.removeAttribute('data-was-disabled');
                }

                if (form.hasAttribute('data-kanban-update-card-on-success')) {
                    updateCardFromPayload(payload);
                }

                // Also update card if action is toggle_task and payload has data.task
                if (payload?.action === 'toggle_task' && payload?.data?.task) {
                    updateCardFromPayload(payload);
                }

                if (form.hasAttribute('data-kanban-remove-card-on-success')) {
                    removeCardFromPayload(payload);
                }

                if (form.hasAttribute('data-kanban-remove-column-on-success')) {
                    removeColumnFromPayload(payload);
                }

                if (form.hasAttribute('data-kanban-render-column-on-success')) {
                    renderColumnFromPayload(payload);
                }

                if (form.hasAttribute('data-kanban-reset-on-success')) {
                    form.reset();
                }

                if (form.hasAttribute('data-kanban-reload-on-success')) {
                    window.location.reload();
                }

                if (form.hasAttribute('data-kanban-render-card-on-success')) {
                    renderCardFromPayload(payload);
                }
            } catch (error) {
                showKanbanActionError(error);
            } finally {
                if (submitButton && submitButton.isConnected && !submitButton.hasAttribute('data-was-disabled')) {
                    submitButton.disabled = false;
                }
            }
        }

        document.addEventListener('submit', (event) => {
            const form = event.target instanceof HTMLFormElement
                ? event.target.closest('[data-kanban-action-form]')
                : null;

            if (!(form instanceof HTMLFormElement)) {
                return;
            }

            handleKanbanActionFormSubmit(form, event);
        });

        document.addEventListener('change', (event) => {
            const prioritySelect = event.target instanceof HTMLSelectElement
                ? event.target.closest('[data-kanban-priority-select]')
                : null;

            if (!(prioritySelect instanceof HTMLSelectElement)) {
                return;
            }

            const form = prioritySelect.closest('[data-kanban-action-form]');

            if (!(form instanceof HTMLFormElement)) {
                return;
            }

            form.requestSubmit();
        });

        if (window.timeago) {
            window.timeago.render(document.querySelectorAll('.timeago'));
        }

        function hasActiveEditor() {
            const activeElement = document.activeElement;
            return activeElement instanceof HTMLInputElement
                || activeElement instanceof HTMLTextAreaElement
                || activeElement instanceof HTMLSelectElement
                || document.querySelector('.collapse.show textarea') !== null;
        }
        function getRefreshMilliseconds() {
            const value = Math.max(0, parseInt(refreshIntervalInput.value || '0', 10));
            const multiplier = refreshUnitSelect.value === 'minutes' ? 60000 : 1000;
            return value * multiplier;
        }
        function saveRefreshSettings() {
            localStorage.setItem('kanbanRefreshInterval', refreshIntervalInput.value || '0');
            localStorage.setItem('kanbanRefreshUnit', refreshUnitSelect.value || 'seconds');
        }
        function loadRefreshSettings() {
            refreshIntervalInput.value = localStorage.getItem('kanbanRefreshInterval') || '0';
            refreshUnitSelect.value = localStorage.getItem('kanbanRefreshUnit') || 'seconds';
        }
        function startRefreshTimer() {
            if (refreshTimer !== null) {
                clearInterval(refreshTimer);
            }
            const milliseconds = getRefreshMilliseconds();
            if (milliseconds <= 0) {
                refreshTimer = null;
                return;
            }
            refreshTimer = setInterval(() => {
                if (!isDragging && !hasActiveEditor()) {
                    window.location.reload();
                }
            }, milliseconds);
        }
        function applyRefreshSettings() {
            saveRefreshSettings();
            startRefreshTimer();
        }
        function directTaskChildren(container) {
            return Array.from(container.children).filter((child) => child.classList.contains('task-node'));
        }

        function refreshTaskFormPaths() {
            document.querySelectorAll('.task-list').forEach((list) => {
                const column = list.getAttribute('data-column') || '';

                function updateNode(node, path) {
                    node.querySelectorAll(':scope > .card-body > .d-flex form input[name="column"], :scope > .card-body > .task-footer form input[name="column"], :scope > .card-body > .collapse form input[name="column"]').forEach((input) => {
                        input.value = column;
                    });

                    node.querySelectorAll(':scope > .card-body > .d-flex form input[name="path"], :scope > .card-body > .task-footer form input[name="path"], :scope > .card-body > .collapse form input[name="path"]').forEach((input) => {
                        input.value = path;
                    });

                    const childList = node.querySelector(':scope > .card-body > .nested-list');

                    if (!childList) {
                        return;
                    }

                    directTaskChildren(childList).forEach((childNode, childIndex) => {
                        updateNode(childNode, path + '.' + childIndex);
                    });
                }

                directTaskChildren(list).forEach((node, index) => {
                    updateNode(node, String(index));
                });
            });
        }
        function serializeTaskNode(node) {
            const rawTask = node.getAttribute('data-task') || '{}';
            let task = {};
            try {
                task = JSON.parse(rawTask);
            } catch (error) {
                task = {};
            }
            task.description = String(task.description || '');
            task.tags = normalizeTaskTagsForRender(task.tags);
            const childList = node.querySelector(':scope > .card-body > .nested-list');
            task.children = childList ? directTaskChildren(childList).map(serializeTaskNode) : [];
            return task;
        }

        function normalizeTaskPriorityValue(priority) {
            const value = String(priority || '').trim().toLowerCase();
            return ['low', 'normal', 'high', 'urgent'].includes(value) ? value : 'normal';
        }

        function formatTaskMetadataForClipboard(task) {
            const metadata = [];
            const createdAt = String(task.created_at || '').trim();
            const completedAt = String(task.completed_at || '').trim();
            const dueAt = String(task.due_at || '').trim();
            const archivedAt = String(task.archived_at || '').trim();
            const archivedFrom = String(task.archived_from || '').trim();
            const priority = normalizeTaskPriorityValue(task.priority);

            if (createdAt !== '') {
                metadata.push('created_at: ' + createdAt);
            }

            if (completedAt !== '') {
                metadata.push('completed_at: ' + completedAt);
            }

            if (dueAt !== '') {
                metadata.push('due_at: ' + dueAt);
            }

            if (archivedAt !== '') {
                metadata.push('archived_at: ' + archivedAt);
            }

            if (archivedFrom !== '') {
                metadata.push('archived_from: "' + archivedFrom.replaceAll('"', '\\"') + '"');
            }

            metadata.push('priority: ' + priority);

            return metadata.length > 0 ? ' <!-- ' + metadata.join(' ') + ' -->' : '';
        }

        async function copyTaskMarkdown(button) {
            const taskNode = button.closest('.task-node');

            if (!taskNode) {
                return;
            }

            const actionForm = taskNode.querySelector(':scope > .card-body form[data-kanban-action-form]');
            const column = actionForm?.querySelector('input[name="column"]')?.value || '';
            const path = actionForm?.querySelector('input[name="path"]')?.value || '';

            if (column.trim() === '' || path.trim() === '') {
                showKanbanActionError(new Error('Unable to identify the task to copy.'));
                return;
            }

            const originalText = button.innerHTML;
            button.disabled = true;

            try {
                const payload = await submitKanbanAction({
                    action: 'copy_task_markdown',
                    column,
                    path,
                });

                const markdown = String(payload?.data?.markdown || '');

                if (markdown.trim() === '') {
                    throw new Error('The API did not return any Markdown to copy.');
                }

                await navigator.clipboard.writeText(markdown);
                button.innerHTML = '<i class="bi bi-check-lg"></i> Copied';

                setTimeout(() => {
                    button.innerHTML = originalText;
                }, 1200);
            } catch (error) {
                button.innerHTML = originalText;
                showKanbanActionError(error);
            } finally {
                button.disabled = false;
            }
        }
        function taskHasIncompleteSubtasks(task) {
            const children = Array.isArray(task.children) ? task.children : [];

            return children.some((child) => {
                if (!child.done) {
                    return true;
                }

                return taskHasIncompleteSubtasks(child);
            });
        }

        function updateTaskCounts() {
            let totalTasks = 0;

            document.querySelectorAll('.kanban-board > .kanban-column').forEach((column) => {
                const list = column.querySelector(':scope > .task-list');
                const countBadge = column.querySelector('[data-column-task-count]');

                if (!list || !countBadge) {
                    return;
                }

                const columnTasks = list.querySelectorAll('.task-node').length;
                countBadge.textContent = String(columnTasks);
                totalTasks += columnTasks;
            });

            const totalBadge = document.querySelector('[data-total-task-count]');

            if (totalBadge) {
                totalBadge.textContent = totalTasks + ' tasks';
            }
        }

        function updateColumnEmptyPlaceholders() {
            document.querySelectorAll('.kanban-board > .kanban-column').forEach((column) => {
                const list = column.querySelector(':scope > .task-list');
                const placeholder = column.querySelector('[data-column-empty-placeholder]');

                if (!list || !placeholder) {
                    return;
                }

                placeholder.classList.toggle('d-none', directTaskChildren(list).length > 0);
            });
        }



        function updateEmptyNestedLists() {
            document.querySelectorAll('.nested-list').forEach((list) => {
                const hasTasks = directTaskChildren(list).length > 0;
                list.classList.toggle('sortable-has-items', hasTasks);
                list.classList.toggle('nested-list-empty', !hasTasks);
            });
        }
        function updateColumnState() {
            const columns = Array.from(document.querySelectorAll('.kanban-board > .kanban-column'));

            columns.forEach((column, index) => {
                const isLastColumn = index === columns.length - 1;
                column.classList.toggle('is-archive-column', isLastColumn);
                column.setAttribute('data-is-last-column', isLastColumn ? '1' : '0');
            });
        }
        async function persistBoardOrder() {
            refreshTaskFormPaths();

            const payload = {};

            document.querySelectorAll('.kanban-column').forEach((columnElement) => {
                const list = columnElement.querySelector(':scope > .task-list');

                if (!list) {
                    return;
                }

                const column = list.getAttribute('data-column');

                if (!column) {
                    return;
                }

                payload[column] = directTaskChildren(list).map(serializeTaskNode);
            });

            try {
                await submitKanbanAction({
                    action: 'reorder_cards',
                    payload: JSON.stringify(payload),
                });
            } catch (error) {
                showKanbanActionError(error);
            }
        }
        if (refreshIntervalInput && refreshUnitSelect && applyRefreshButton) {
            loadRefreshSettings();
            startRefreshTimer();
            applyRefreshButton.addEventListener('click', applyRefreshSettings);
            refreshIntervalInput.addEventListener('change', applyRefreshSettings);
            refreshUnitSelect.addEventListener('change', applyRefreshSettings);
        }
        if (kanbanBoard) {
            kanbanBoard.sortable = new Sortable(kanbanBoard, {
                animation: 260,
                ghostClass: 'sortable-ghost',
                chosenClass: 'sortable-chosen',
                dragClass: 'sortable-drag',
                draggable: '.kanban-column',
                handle: '.column-drag-handle',
                filter: 'button, input, textarea, select, form, a',
                preventOnFilter: false,
                fallbackOnBody: false,
                fallbackTolerance: 2,
                fallbackClass: 'sortable-drag',
                forceFallback: true,
                onStart: () => {
                    isDragging = true;
                    kanbanPage?.classList.add('is-sorting');
                },
                onEnd: () => {
                    isDragging = false;
                    kanbanPage?.classList.remove('is-sorting');
                    window.requestAnimationFrame(() => {
                        updateColumnState();
                        refreshTaskFormPaths();
                        updateTaskCounts();
                        updateColumnEmptyPlaceholders();
                        updateTaskToggleGuards();
                        applyTagFilter();
                        persistBoardOrder();
                    });
                },
            });
        }

        function initializeTaskSortable(list) {
            if (list.dataset.sortableInitialized === '1') {
                return;
            }

            list.dataset.sortableInitialized = '1';

            new Sortable(list, {
                group: 'kanban-tasks',
                animation: 260,
                ghostClass: 'sortable-ghost',
                chosenClass: 'sortable-chosen',
                dragClass: 'sortable-drag',
                draggable: '.task-node',
                handle: '.drag-handle',
                filter: 'button, input, textarea, select, form, a',
                preventOnFilter: false,
                fallbackOnBody: true,
                fallbackTolerance: 6,
                emptyInsertThreshold: 18,
                swapThreshold: 0.35,
                invertedSwapThreshold: 0.65,
                dragoverBubble: true,
                onStart: () => {
                    isDragging = true;
                    kanbanPage?.classList.add('is-sorting');
                    updateEmptyNestedLists();
                    updateColumnState();
                    refreshTaskFormPaths();
                    updateTaskCounts();
                    updateColumnEmptyPlaceholders();
                    updateTaskToggleGuards();
                },
                onAdd: () => {
                    window.requestAnimationFrame(() => {
                        updateEmptyNestedLists();
                        updateColumnState();
                        refreshTaskFormPaths();
                        updateTaskCounts();
                        updateColumnEmptyPlaceholders();
                        updateTaskToggleGuards();
                    });
                },
                onRemove: () => {
                    window.requestAnimationFrame(() => {
                        updateEmptyNestedLists();
                        updateColumnState();
                        refreshTaskFormPaths();
                        updateTaskCounts();
                        updateColumnEmptyPlaceholders();
                        updateTaskToggleGuards();
                    });
                },
                onSort: () => {
                    window.requestAnimationFrame(() => {
                        updateEmptyNestedLists();
                        updateColumnState();
                        refreshTaskFormPaths();
                        updateTaskCounts();
                        updateColumnEmptyPlaceholders();
                        updateTaskToggleGuards();
                    });
                },
                onEnd: () => {
                    isDragging = false;
                    kanbanPage?.classList.remove('is-sorting');
                    updateEmptyNestedLists();
                    updateColumnState();
                    refreshTaskFormPaths();
                    updateTaskCounts();
                    updateColumnEmptyPlaceholders();
                    updateTaskToggleGuards();
                    applyTagFilter();
                    persistBoardOrder();
                },
            });

            initializeTagSelects(document);
            initializeTagFilter();
            applyTagFilter();
        }

        function initializeTagSelects(container = document) {
            if (!window.jQuery || !jQuery.fn.select2) {
                return;
            }

            $(container).find('[data-kanban-tags-select]').each(function () {
                if ($(this).hasClass('select2-hidden-accessible')) {
                    return;
                }

                $(this).select2({
                    tags: true,
                    theme: 'bootstrap-5',
                    tokenSeparators: [','],
                    placeholder: 'Tags',
                    allowClear: true,
                    dropdownParent: $(this).closest('.collapse').length
                        ? $(this).closest('.collapse')
                        : $(document.body),
                });
            });
        }

        function collectBoardTags() {
            const tags = new Set();

            document.querySelectorAll('.task-node, .archived-task-node').forEach((node) => {
                try {
                    const task = JSON.parse(node.getAttribute('data-task') || '{}');
                    normalizeTaskTagsForRender(task.tags).forEach((tag) => tags.add(tag));
                } catch {}
            });

            return Array.from(tags).sort();
        }

        function initializeTagFilter() {
            const filter = document.querySelector('[data-kanban-tag-filter]');

            if (!(filter instanceof HTMLSelectElement)) {
                return;
            }

            const selectedValues = Array.from(filter.selectedOptions || [])
                .map((option) => String(option.value || '').trim().toLowerCase())
                .filter((tag) => tag !== '');

            const tags = collectBoardTags();

            filter.innerHTML = tags
                .map((tag) => `<option value="${escapeHtml(tag)}">${escapeHtml(tag)}</option>`)
                .join('');

            if (window.jQuery && window.jQuery.fn && window.jQuery.fn.select2) {
                const $filter = window.jQuery(filter);

                if (!$filter.hasClass('select2-hidden-accessible')) {
                    $filter.select2({
                        tags: true,
                        theme: 'bootstrap-5',
                        tokenSeparators: [','],
                        placeholder: filter.dataset.placeholder || 'Filter by tag',
                        allowClear: true,
                    });
                }

                $filter.val(selectedValues.length > 0 ? selectedValues : null).trigger('change');
            } else {
                Array.from(filter.options).forEach((option) => {
                    option.selected = selectedValues.includes(option.value);
                });
            }

            if (!filter.dataset.tagFilterInitialized) {
                filter.addEventListener('change', applyTagFilter);

                if (window.jQuery && window.jQuery.fn && window.jQuery.fn.select2) {
                    $(filter).on('change', applyTagFilter);
                }

                filter.dataset.tagFilterInitialized = '1';
            }

            applyTagFilter();
        }

        function applyTagFilter() {
            const filter = document.querySelector('[data-kanban-tag-filter]');
            const selected = filter instanceof HTMLSelectElement
                ? Array.from(filter.selectedOptions || [])
                    .map((option) => String(option.value || '').trim().toLowerCase())
                    .filter((tag) => tag !== '')
                : [];

            document.querySelectorAll('.task-node').forEach((node) => {
                const task = serializeTaskNode(node);
                const tags = normalizeTaskTagsForRender(task.tags);
                const visible = selected.length === 0 || selected.some((tag) => tags.includes(tag));

                node.classList.toggle('d-none', !visible);
            });

            updateColumnEmptyPlaceholders();
        }

        document.querySelectorAll('.task-list, .nested-list').forEach(initializeTaskSortable);
        document.addEventListener('click', (event) => {
            const button = event.target instanceof Element
                ? event.target.closest('[data-copy-task-markdown]')
                : null;

            if (!(button instanceof HTMLButtonElement)) {
                return;
            }

            event.preventDefault();
            copyTaskMarkdown(button);
        });

        updateEmptyNestedLists();
        updateColumnState();
        refreshTaskFormPaths();
        updateTaskCounts();
        updateColumnEmptyPlaceholders();
        updateTaskToggleGuards();

        initializeTagSelects();
        initializeTagFilter();
        applyTagFilter();
    })();

    // Add command execution logic using existing JS helper from assets/js/opencode.js
    document.addEventListener('DOMContentLoaded', function() {
        // Apply command button logic using existing JS helper from assets/js/opencode.js
        const commandButtons = document.querySelectorAll('.command-btn');
        const projectSlugInput = document.getElementById('command-project-slug');
        const resultDiv = document.getElementById('command-result');

        commandButtons.forEach(button => {
            button.addEventListener('click', async function() {
                const command = this.dataset.command;
                const projectSlug = projectSlugInput.value.trim();

                // Check for confirmation needed (only for /document)
                if (command === '/document') {
                    const confirmed = await showConfirmationModal(command, () => {});
                    if (!confirmed) {
                        return;
                    }
                }

                // Simple duplicate guard in this case just disables the button
                this.disabled = true;
                this.innerHTML = '<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Executing...';

                try {
                    // Run the command and display result using existing helper
                    await executeAndDisplayCommand(command, projectSlug || null, resultDiv);
                } catch (error) {
                    console.error('Command execution failed:', error);
                } finally {
                    // Reset button state
                    this.disabled = false;
                    this.innerHTML = command.charAt(1).toUpperCase() + command.slice(2);
                }
            });
        });
    });
</script>
