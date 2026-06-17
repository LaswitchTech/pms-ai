<?php
$selectedProject = is_array($selectedProject ?? null) ? $selectedProject : null;
$projectSlug = $selectedProject['slug'] ?? null;
$projectName = $selectedProject['name'] ?? 'Local Project';
$projectPath = $selectedProject['path'] ?? dirname(__DIR__);
$agentsRoute = $projectSlug !== null ? '/agents/' . rawurlencode((string) $projectSlug) : '/agents';
$agentsFile = rtrim((string) $projectPath, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'AGENTS.md';
$opencodeDirectory = rtrim((string) $projectPath, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . '.opencode';
$opencodeConfigFile = $opencodeDirectory . DIRECTORY_SEPARATOR . 'opencode.json';
$agentsCreateError = null;
$opencodeCreateError = null;

// For the command execution result display
$commandResult = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['command'])) {
    // This would normally be handled by JavaScript, for now just for demo purposes
    // In reality this part should work through AJAX calls with the JS helpers
}

if (!function_exists('e')) {
    function e(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
    }
}

function agentsMarkdownInline(string $value): string
{
    $value = e($value);
    $value = preg_replace('/`([^`]+)`/', '<code>$1</code>', $value);
    $value = preg_replace('/\*\*([^*]+)\*\*/', '<strong>$1</strong>', $value);
    $value = preg_replace('/\*([^*]+)\*/', '<em>$1</em>', $value);
    $value = preg_replace('/\[([^\]]+)\]\(([^)]+)\)/', '<a href="$2" target="_blank" rel="noopener noreferrer">$1</a>', $value);

    return $value;
}

function defaultAgentsTemplate(string $projectName): string
{
    $projectName = trim($projectName) !== '' ? trim($projectName) : 'Project';

    return <<<MARKDOWN
# {$projectName} — Agent Instructions

## Mission

This repository is managed with the Project Management dashboard.

The goal is to keep development organized, incremental, well documented, and easy to verify.

## Current Workflow

Work in small, verifiable increments.

1. Read `NEXT.md`.
2. Read `DESIGN.md`.
3. Read relevant documentation under `docs/` when available.
4. Review the existing implementation.
5. Update documentation if behavior is undocumented.
6. Create or update a TODO list.
7. Implement one small change.
8. Run focused tests.
9. Run broader tests when practical.
10. Run `git diff --check`.
11. Commit the change when appropriate.
12. Update `KANBAN.md` when appropriate.
13. Continue to the next task.

## Documentation First

Before implementing new functionality:

1. Verify the behavior is already documented.
2. If it is not documented:
   - read the code,
   - document the current behavior,
   - update the appropriate document,
   - then continue implementation.

Documentation is considered part of development.

Do not invent behavior that is not documented or observable in code.

## Branch Discipline

The main development branch should stay clean and gradual.

Avoid large speculative rewrites.

If a task requires experimentation, document the risk before changing code.

## Planning Files

The repository uses the following planning documents:

### ROADMAP.md

Long-term project vision.

Contains:
- major milestones,
- future features,
- architectural goals.

Do not use `ROADMAP.md` as the active task list.

### KANBAN.md

Current project board.

Contains project status and task movement.

Use this file to understand current work state.

### NEXT.md

Current sprint or immediate task list.

Contains only the tasks that should be worked on next.

Always start here.

### DESIGN.md

Architecture and design decisions.

Read before proposing structural changes.

## Development Strategy

Prefer this order:

1. Documentation
2. Implementation
3. Tests
4. Small structural changes only when required

Do not modify unrelated areas unless the user explicitly asks.

## Simplicity Rule

Prefer the smallest working solution.

Do not:
- redesign existing architecture unnecessarily,
- refactor unrelated code,
- introduce new patterns when existing patterns already work,
- rewrite files that are not required for the task.

Small, incremental changes are preferred.

## CI Failure Handling

When fixing CI failures:

1. Identify the first failing test.
2. Understand why it fails.
3. Patch only the affected area.
4. Re-run the smallest possible test.
5. Expand testing only after the failing test passes.
6. Run `git diff --check`.
7. Commit the fix when appropriate.

Avoid speculative fixes.

Do not claim that files cannot be edited unless an actual command fails.

## Testing

Use the project’s documented test commands.

Common examples:

```sh
composer install
composer test
vendor/bin/phpunit
npm install
npm test
php -l path/to/file.php
git diff --check
```

For small changes, run the most focused test first.

## Documentation Sources

Read in this order:

1. `NEXT.md`
2. `AGENTS.md`
3. `KANBAN.md`
4. `ROADMAP.md`
5. `DESIGN.md`
6. `README.md`
7. `docs/`

If instructions conflict, follow the newest and most specific instruction.

## Git Rules

Before editing:

```sh
git status
```

Before committing:

```sh
git diff --check
git status
```

Commit only task-related files.

Push when:
- explicitly requested,
- completing a task,
- or preparing for CI validation.
MARKDOWN;
}

function defaultOpencodeConfig(string $projectName): array
{
    $projectName = trim($projectName) !== '' ? trim($projectName) : 'Project';

    return [
        '$schema' => 'https://opencode.ai/config.json',
        'instructions' => [
            'AGENTS.md',
            'NEXT.md',
            'KANBAN.md',
            'ROADMAP.md',
            'DESIGN.md',
        ],
        'permission' => [
            'edit' => 'allow',
            'write' => 'allow',
            'bash' => [
                '*' => 'ask',
                'pwd*' => 'allow',
                'ls*' => 'allow',
                'tree*' => 'allow',
                'find*' => 'allow',
                'grep*' => 'allow',
                'egrep*' => 'allow',
                'fgrep*' => 'allow',
                'cat*' => 'allow',
                'head*' => 'allow',
                'tail*' => 'allow',
                'less*' => 'allow',
                'more*' => 'allow',
                'wc*' => 'allow',
                'sed*' => 'allow',
                'awk*' => 'allow',
                'sort*' => 'allow',
                'uniq*' => 'allow',
                'cut*' => 'allow',
                'git status*' => 'allow',
                'git diff*' => 'allow',
                'git log*' => 'allow',
                'git show*' => 'allow',
                'git branch*' => 'allow',
                'git add*' => 'allow',
                'git commit*' => 'allow',
                'git push*' => 'allow',
                'composer install*' => 'allow',
                'composer test*' => 'allow',
                'vendor/bin/phpunit*' => 'allow',
                'php -l*' => 'allow',
                'php cli*' => 'allow',
            ],
        ],
        'agent' => [
            'build' => [
                'mode' => 'primary',
                'model' => 'ollama/qwen3.6:35b-128k',
                'temperature' => 0.1,
                'prompt' => 'You are the ' . $projectName . ' build/debug agent. Focus on diagnosis, small patches, tests, commits, and pushes. Prefer correctness over speed.',
            ],
            'coder' => [
                'mode' => 'primary',
                'model' => 'ollama/qwen3-coder:30b-128k',
                'temperature' => 0.1,
                'prompt' => 'You are the ' . $projectName . ' coding agent. Implement clearly scoped changes using existing project patterns. Do not redesign architecture unless requested.',
            ],
        ],
        'command' => [
            'next' => [
                'description' => 'Work on the next task from NEXT.md',
                'agent' => 'coder',
                'template' => 'Read NEXT.md and complete only the first unchecked task that is ready. Follow AGENTS.md. Keep the change small. After the task is successfully completed and validated, update NEXT.md by marking the completed task as checked ([x]). Then update KANBAN.md so the matching task moves to the correct column: In Progress while active, Testing if validation is still pending, or Done when completed and validated. If completing the task unlocks follow-up work, move the next logical task into the active section in NEXT.md and the appropriate column in KANBAN.md. Run relevant tests, run git diff --check, update NEXT.md, update KANBAN.md, commit all task-related changes, and push.',
            ],
            'plan' => [
                'description' => 'Review ROADMAP.md and KANBAN.md, then update NEXT.md',
                'agent' => 'build',
                'template' => 'Read ROADMAP.md, KANBAN.md, DESIGN.md, NEXT.md, and AGENTS.md. Act as the project planning agent only. Do not implement code. Do not refactor. Do not modify ROADMAP.md. Use ROADMAP.md for strategic priorities, KANBAN.md for current project state, DESIGN.md for architectural constraints, and AGENTS.md for workflow rules. Select the highest-priority task that is not blocked, has prerequisites completed, aligns with the current roadmap phase, can be completed incrementally, and is suitable for the next focused sprint. Prefer tasks from In Progress or Ready in KANBAN.md before selecting items from Backlog. Update NEXT.md with a single sprint goal, a short active task list, and a clear definition of done. Update KANBAN.md so selected tasks move to In Progress and completed planning tasks move to Done when appropriate. Keep NEXT.md small enough for the next command to execute without ambiguity. Run git diff --check, commit the planning changes, and push.',
            ],
            'fix-ci' => [
                'description' => 'Fix a GitHub Actions CI failure',
                'agent' => 'build',
                'template' => "Fix the CI failure below. Find the first meaningful failure, patch the smallest correct fix, run the closest local test, run git diff --check, commit, and push.\n\n\$ARGUMENTS",
            ],
            'docs' => [
                'description' => 'Document existing behavior before implementation',
                'agent' => 'build',
                'template' => "Document the existing behavior requested below. Read relevant code first. Do not invent behavior. Create or update docs under docs/ when appropriate. Run git diff --check, commit, and push.\n\n\$ARGUMENTS",
            ],
        ],
    ];
}

function writeDefaultOpencodeConfig(string $file, string $projectName): ?string
{
    $json = json_encode(defaultOpencodeConfig($projectName), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

    if ($json === false) {
        return 'Unable to encode default OpenCode configuration.';
    }

    if (@file_put_contents($file, $json . "\n") === false) {
        return 'Unable to create .opencode/opencode.json. Check folder permissions.';
    }

    return null;
}

function readOpencodeConfigSummary(string $file): array
{
    if (!is_file($file)) {
        return [
            'valid' => false,
            'agents' => [],
            'commands' => [],
            'instructions' => [],
            'message' => 'Configuration file does not exist.',
        ];
    }

    $decoded = json_decode((string) file_get_contents($file), true);

    if (!is_array($decoded)) {
        return [
            'valid' => false,
            'agents' => [],
            'commands' => [],
            'instructions' => [],
            'message' => 'Configuration file exists but contains invalid JSON.',
        ];
    }

    return [
        'valid' => true,
        'agents' => array_keys(is_array($decoded['agent'] ?? null) ? $decoded['agent'] : []),
        'commands' => array_keys(is_array($decoded['command'] ?? null) ? $decoded['command'] : []),
        'instructions' => is_array($decoded['instructions'] ?? null) ? $decoded['instructions'] : [],
        'message' => 'Configuration file is valid JSON.',
    ];
}

function renderAgentsMarkdown(string $content): string
{
    $html = '';
    $lines = preg_split('/\R/', $content);
    $inList = false;
    $inCode = false;
    $codeBuffer = [];

    foreach ($lines as $line) {
        $trimmed = trim($line);

        if (str_starts_with($trimmed, '```')) {
            if ($inList === true) {
                $html .= '</ul>';
                $inList = false;
            }

            if ($inCode === false) {
                $inCode = true;
                $codeBuffer = [];
            } else {
                $inCode = false;
                $html .= '<pre class="bg-dark text-light rounded-3 p-3 overflow-auto"><code>' . e(implode("\n", $codeBuffer)) . '</code></pre>';
            }

            continue;
        }

        if ($inCode === true) {
            $codeBuffer[] = $line;
            continue;
        }

        if ($trimmed === '') {
            if ($inList === true) {
                $html .= '</ul>';
                $inList = false;
            }
            continue;
        }

        if (preg_match('/^(#{1,6})\s+(.+)$/', $trimmed, $matches) === 1) {
            if ($inList === true) {
                $html .= '</ul>';
                $inList = false;
            }

            $level = strlen($matches[1]);
            $text = agentsMarkdownInline(trim($matches[2]));
            $class = match ($level) {
                1 => 'display-6 fw-bold border-bottom pb-3 mb-4',
                2 => 'h3 mt-5 mb-3',
                3 => 'h4 mt-4 mb-3',
                default => 'h5 mt-3 mb-2',
            };

            $html .= '<h' . $level . ' class="' . $class . '">' . $text . '</h' . $level . '>';
            continue;
        }

        if (preg_match('/^-\s+\[( |x|X)\]\s+(.+)$/', $trimmed, $matches) === 1) {
            if ($inList === false) {
                $html .= '<ul class="list-group list-group-flush mb-4">';
                $inList = true;
            }

            $done = strtolower($matches[1]) === 'x';
            $badge = $done ? '<span class="badge text-bg-success">Done</span>' : '<span class="badge text-bg-secondary">Open</span>';
            $titleClass = $done ? 'text-decoration-line-through text-muted' : '';

            $html .= '<li class="list-group-item d-flex gap-3 align-items-start px-0">'
                . '<div class="pt-1"><i class="bi ' . ($done ? 'bi-check-circle-fill text-success' : 'bi-circle text-secondary') . '"></i></div>'
                . '<div class="flex-grow-1 ' . $titleClass . '">' . agentsMarkdownInline(trim($matches[2])) . '</div>'
                . '<div>' . $badge . '</div>'
                . '</li>';
            continue;
        }

        if (preg_match('/^-\s+(.+)$/', $trimmed, $matches) === 1) {
            if ($inList === false) {
                $html .= '<ul class="list-group list-group-flush mb-4">';
                $inList = true;
            }

            $html .= '<li class="list-group-item d-flex gap-3 align-items-start px-0">'
                . '<div class="pt-1"><i class="bi bi-dot"></i></div>'
                . '<div class="flex-grow-1">' . agentsMarkdownInline(trim($matches[1])) . '</div>'
                . '</li>';
            continue;
        }

        if ($inList === true) {
            $html .= '</ul>';
            $inList = false;
        }

        $html .= '<p class="lead-sm mb-3">' . agentsMarkdownInline($trimmed) . '</p>';
    }

    if ($inList === true) {
        $html .= '</ul>';
    }

    if ($inCode === true && $codeBuffer !== []) {
        $html .= '<pre class="bg-dark text-light rounded-3 p-3 overflow-auto"><code>' . e(implode("\n", $codeBuffer)) . '</code></pre>';
    }

    return $html;
}

if (!is_file($agentsFile)) {
    if (@file_put_contents($agentsFile, defaultAgentsTemplate((string) $projectName)) === false) {
        $agentsCreateError = 'Unable to create AGENTS.md. Check folder permissions.';
    }
}

if (!is_dir($opencodeDirectory)) {
    if (@mkdir($opencodeDirectory, 0775, true) === false && !is_dir($opencodeDirectory)) {
        $opencodeCreateError = 'Unable to create .opencode directory. Check folder permissions.';
    }
}

if ($opencodeCreateError === null && !is_file($opencodeConfigFile)) {
    $opencodeCreateError = writeDefaultOpencodeConfig($opencodeConfigFile, (string) $projectName);
}

$opencodeSummary = readOpencodeConfigSummary($opencodeConfigFile);

$fileExists = is_file($agentsFile);
$content = $fileExists ? (string) file_get_contents($agentsFile) : '';
$renderedAgents = $fileExists ? renderAgentsMarkdown($content) : '';
?>
<style>
    .agents-card {
        border: 0;
        border-radius: 1rem;
        box-shadow: 0 .35rem 1rem rgba(0, 0, 0, .06);
    }

    .agents-content h1:first-child {
        margin-top: 0 !important;
    }

    .agents-content code {
        color: #d63384;
    }

    .agents-page .lead-sm {
        font-size: 1.05rem;
        line-height: 1.65;
    }

    .opencode-card {
        border: 0;
        border-radius: 1rem;
        box-shadow: 0 .35rem 1rem rgba(0, 0, 0, .06);
    }
</style>

<div class="agents-page agents-shell">
    <div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-4">
        <div>
            <h1 class="h3 mb-1"><?= e((string) $projectName); ?> Agents</h1>
            <p class="text-muted mb-0">Reading <code><?= e(basename($agentsFile)); ?></code> from <code><?= e(dirname($agentsFile)); ?></code>.</p>
        </div>
    </div>

    <!-- OpenCode Command Toolbar -->
    <article class="card agents-card mb-4">
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

            <div id="command-result" class="mt-3">
                <!-- Command results will be displayed here -->
            </div>
        </div>
    </article>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const commandButtons = document.querySelectorAll('.command-btn');
            const projectSlugInput = document.getElementById('command-project-slug');
            const resultDiv = document.getElementById('command-result');

            // Apply the same duplicate execution guard we use in the JS helper files
            const executingCommands = new Set();

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

                    // Duplicate guard - prevent concurrent execution
                    const key = projectSlug ? `${command}:${projectSlug}` : command;
                    if (executingCommands.has(key)) {
                        showToast('Command is already executing. Please wait for completion.', 'warning');
                        return;
                    }

                    // Mark as executing
                    executingCommands.add(key);
                    this.disabled = true;
                    this.innerHTML = '<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Executing...';

                    try {
                        // Use the execute and display helper from opencode.js
                        await executeAndDisplayCommand(command, projectSlug || null, resultDiv);
                    } catch (error) {
                        console.error('Command execution failed:', error);
                    } finally {
                        // Reset button state
                        executingCommands.delete(key);
                        this.disabled = false;
                        this.innerHTML = command.slice(1).replace(/^./, c => c.toUpperCase()); // Reset button text to command name
                    }
                });
            });
        });
    </script>

    <article class="card opencode-card mb-4">
        <div class="card-body p-4">
            <div class="d-flex flex-wrap align-items-start justify-content-between gap-3 mb-3">
                <div>
                    <h2 class="h5 mb-1">
                        <i class="bi bi-terminal"></i>
                        OpenCode configuration
                    </h2>
                    <p class="text-muted mb-0">
                        Managing <code><?= e(str_replace(dirname($opencodeDirectory) . DIRECTORY_SEPARATOR, '', $opencodeConfigFile)); ?></code> for this project.
                    </p>
                </div>
                <?php if ($opencodeSummary['valid']): ?>
                    <span class="badge text-bg-success">Valid JSON</span>
                <?php else: ?>
                    <span class="badge text-bg-warning">Needs attention</span>
                <?php endif; ?>
            </div>

            <?php if ($opencodeCreateError !== null): ?>
                <div class="alert alert-warning mb-0">
                    <strong>OpenCode config could not be created.</strong> <?= e($opencodeCreateError); ?>
                </div>
            <?php else: ?>
                <div class="row g-3">
                    <div class="col-lg-4">
                        <div class="border rounded-3 p-3 h-100 bg-light">
                            <div class="text-muted small mb-1">Instructions</div>
                            <div class="fw-semibold"><?= e((string) count($opencodeSummary['instructions'])); ?> files</div>
                            <div class="small text-muted"><?= e(implode(', ', $opencodeSummary['instructions']) ?: '—'); ?></div>
                        </div>
                    </div>
                    <div class="col-lg-4">
                        <div class="border rounded-3 p-3 h-100 bg-light">
                            <div class="text-muted small mb-1">Agents</div>
                            <div class="fw-semibold"><?= e((string) count($opencodeSummary['agents'])); ?> configured</div>
                            <div class="small text-muted"><?= e(implode(', ', $opencodeSummary['agents']) ?: '—'); ?></div>
                        </div>
                    </div>
                    <div class="col-lg-4">
                        <div class="border rounded-3 p-3 h-100 bg-light">
                            <div class="text-muted small mb-1">Commands</div>
                            <div class="fw-semibold"><?= e((string) count($opencodeSummary['commands'])); ?> configured</div>
                            <div class="small text-muted"><?= e(implode(', ', $opencodeSummary['commands']) ?: '—'); ?></div>
                        </div>
                    </div>
                </div>
                <p class="text-muted small mb-0 mt-3">
                    Path: <code><?= e($opencodeConfigFile); ?></code>
                </p>
            <?php endif; ?>
        </div>
    </article>

    <?php if ($agentsCreateError !== null): ?>
        <div class="alert alert-warning">
            <strong>AGENTS.md could not be created.</strong> <?= e($agentsCreateError); ?>
        </div>
    <?php elseif (trim($content) === ''): ?>
        <div class="alert alert-info">
            <strong>AGENTS.md is empty.</strong> Add agent instructions to render them here.
        </div>
    <?php else: ?>
        <article class="card agents-card">
            <div class="card-body p-4 p-lg-5 agents-content">
                <?= $renderedAgents; ?>
            </div>
        </article>
    <?php endif; ?>
</div>

<!-- Page-level toast container -->
<div id="opencode-toast-container" class="position-fixed top-0 start-50 translate-middle-x p-3" style="z-index: 1100;"></div>
