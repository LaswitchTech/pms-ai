<?php
$selectedProject = is_array($selectedProject ?? null) ? $selectedProject : null;
$projectSlug = $selectedProject['slug'] ?? null;
$projectName = $selectedProject['name'] ?? 'Local Project';
$projectPath = $selectedProject['path'] ?? dirname(__DIR__);
$designRoute = $projectSlug !== null ? '/design/' . rawurlencode((string) $projectSlug) : '/design';
$designFile = rtrim((string) $projectPath, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'DESIGN.md';
$designCreateError = null;

if (!function_exists('e')) {
    function e(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
    }
}

function designMarkdownInline(string $value): string
{
    $value = e($value);
    $value = preg_replace('/`([^`]+)`/', '<code>$1</code>', $value);
    $value = preg_replace('/\*\*([^*]+)\*\*/', '<strong>$1</strong>', $value);
    $value = preg_replace('/\*([^*]+)\*/', '<em>$1</em>', $value);
    $value = preg_replace('/\[([^\]]+)\]\(([^)]+)\)/', '<a href="$2" target="_blank" rel="noopener noreferrer">$1</a>', $value);

    return $value;
}

function renderDesignMarkdown(string $content): string
{
    $html = '';
    $lines = preg_split('/\R/', $content);
    $inList = false;
    $inCode = false;
    $codeBuffer = [];

    foreach ($lines as $line) {
        $trimmed = trim($line);

        if (str_starts_with($trimmed, '```')) {
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
            $text = designMarkdownInline(trim($matches[2]));
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
                . '<div class="flex-grow-1 ' . $titleClass . '">' . designMarkdownInline(trim($matches[2])) . '</div>'
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
                . '<div class="flex-grow-1">' . designMarkdownInline(trim($matches[1])) . '</div>'
                . '</li>';
            continue;
        }

        if ($inList === true) {
            $html .= '</ul>';
            $inList = false;
        }

        $html .= '<p class="lead-sm mb-3">' . designMarkdownInline($trimmed) . '</p>';
    }

    if ($inList === true) {
        $html .= '</ul>';
    }

    if ($inCode === true && $codeBuffer !== []) {
        $html .= '<pre class="bg-dark text-light rounded-3 p-3 overflow-auto"><code>' . e(implode("\n", $codeBuffer)) . '</code></pre>';
    }

    return $html;
}

if (!is_file($designFile)) {
    $defaultDesign = "# Design\n\n## Now\n\n- [ ] Define immediate priorities\n\n## Next\n\n- [ ] Plan upcoming work\n\n## Later\n\n- [ ] Capture long-term ideas\n";

    if (@file_put_contents($designFile, $defaultDesign) === false) {
        $designCreateError = 'Unable to create DESIGN.md. Check folder permissions.';
    }
}

$fileExists = is_file($designFile);
$content = $fileExists ? (string) file_get_contents($designFile) : '';
$renderedDesign = $fileExists ? renderDesignMarkdown($content) : '';
?>
<style>
    .design-card {
        border: 0;
        border-radius: 1rem;
        box-shadow: 0 .35rem 1rem rgba(0, 0, 0, .06);
    }

    .design-content h1:first-child {
        margin-top: 0 !important;
    }

    .design-content code {
        color: #d63384;
    }

    .design-page .lead-sm {
        font-size: 1.05rem;
        line-height: 1.65;
    }
</style>

<div class="design-page design-shell">
    <div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-4">
        <div>
            <h1 class="h3 mb-1"><?= e((string) $projectName); ?> Design</h1>
            <p class="text-muted mb-0">Reading <code><?= e(basename($designFile)); ?></code> from <code><?= e(dirname($designFile)); ?></code>.</p>
        </div>
    </div>

    <!-- OpenCode Command Toolbar -->
    <article class="card design-card mb-4">
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

    <?php if ($designCreateError !== null): ?>
        <div class="alert alert-warning">
            <strong>DESIGN.md could not be created.</strong> <?= e($designCreateError); ?>
        </div>
    <?php elseif (trim($content) === ''): ?>
        <div class="alert alert-info">
            <strong>DESIGN.md is empty.</strong> Add Markdown content to render your design.
        </div>
    <?php else: ?>
        <article class="card design-card">
            <div class="card-body p-4 p-lg-5 design-content">
                <?= $renderedDesign; ?>
            </div>
        </article>
    <?php endif; ?>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        // Apply command button logic using existing JS helper from assets/js/opencode.js
        const commandButtons = document.querySelectorAll('.command-btn');
        const projectSlugInput = document.getElementById('command-project-slug');
        const resultDiv = document.getElementById('command-result');

        // Simple toast notification system for design view (reusing existing toast infrastructure)
        function showCommandToast(message, type = 'info') {
            // Create or reuse toast container
            let toastContainer = document.getElementById('opencode-toast-container');
            if (!toastContainer) {
                toastContainer = document.createElement('div');
                toastContainer.id = 'opencode-toast-container';
                toastContainer.className = 'position-fixed top-0 start-50 translate-middle-x mt-3 p-3';
                toastContainer.style.zIndex = 1050;
                document.body.appendChild(toastContainer);
            }

            const toast = document.createElement('div');
            toast.className = `alert alert-${type} alert-dismissible fade show shadow-sm`;
            toast.role = 'alert';
            toast.innerHTML = `
                ${message}
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            `;

            toastContainer.appendChild(toast);

            // Auto-remove after 5 seconds
            setTimeout(() => {
                if (toast.parentNode) {
                    toast.remove();
                }
            }, 5000);
        }

        commandButtons.forEach(button => {
            button.addEventListener('click', async function() {
                const command = this.dataset.command;
                const projectSlug = projectSlugInput.value.trim();

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
