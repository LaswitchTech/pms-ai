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
