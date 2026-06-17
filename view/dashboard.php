

<?php

/**
 * Dashboard view.
 *
 * Expected variables from index.php/layout:
 * - Router $router
 * - array $projectsConfig
 * - ?array $projectActionResult
 * - array $setupErrors
 * - array $rewriteStatus
 * - bool $isApache
 * - bool $isNginx
 * - bool $isBuiltinServer
 */
?>
<section class="hero-card card border-0 shadow-sm">
    <div class="card-body p-4 p-lg-5">
        <div class="row align-items-center g-4">
            <div class="col-lg-8">
                <span class="badge text-bg-primary rounded-pill mb-3">Dashboard</span>
                <h1 class="display-6 fw-bold mb-3">Project command center</h1>
                <p class="lead text-muted mb-0">
                    Create, discover, and manage local Markdown-backed projects from one place.
                </p>
            </div>
            <div class="col-lg-4">
                <div class="d-grid gap-2">
                    <a href="<?= e($router->routeUrl('/kanban')); ?>" class="btn btn-primary btn-lg">
                        <i class="bi bi-kanban"></i>
                        Open Kanban
                    </a>
                    <a href="<?= e($router->routeUrl('/roadmap')); ?>" class="btn btn-outline-secondary btn-lg">
                        <i class="bi bi-map"></i>
                        View Roadmap
                    </a>
                    <a href="<?= e($router->routeUrl('/design')); ?>" class="btn btn-outline-secondary btn-lg">
                        <i class="bi bi-brush"></i>
                        View Design
                    </a>
                    <a href="<?= e($router->routeUrl('/next')); ?>" class="btn btn-outline-secondary btn-lg">
                        <i class="bi bi-arrow-right-square"></i>
                        View Next
                    </a>
                    <a href="<?= e($router->routeUrl('/agents')); ?>" class="btn btn-outline-secondary btn-lg">
                        <i class="bi bi-robot"></i>
                        View Agents
                    </a>
                </div>
            </div>
        </div>
    </div>
</section>

        </div>
    </div>
</section>

<!-- OpenCode Command Toolbar -->
<section class="mt-4">
    <article class="card">
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
</section>

<?php renderProjectManager($router, $projectsConfig, $projectActionResult, $setupErrors); ?>
<?php renderServerRoutingStatus($rewriteStatus, $isApache, $isNginx, $isBuiltinServer); ?>

<?php
// Load OpenCode settings for availability card
$openCodeSettingsLoaded = false;
$opencodeEnabled = false;
$opencodeHost = 'localhost';
$opencodePort = 8080;
$opencodeTimeout = 60;
$opencodeAvailable = null;
$opencodeMessage = '';

if (file_exists(__DIR__ . '/../lib/opencode.php')) {
    require_once __DIR__ . '/../lib/settings.php';
    $loadedSettings = loadSettings(__DIR__ . '/../config/settings.json');
    if ($loadedSettings !== []) {
        $openCodeSettingsLoaded = true;
        $opencodeEnabled = !empty($loadedSettings['opencode_enabled']);
        $opencodeHost = (string) ($loadedSettings['opencode_host'] ?? 'localhost');
        $opencodePort = (int) ($loadedSettings['opencode_port'] ?? 8080);
        $opencodeTimeout = (int) ($loadedSettings['opencode_timeout'] ?? 60);

        // Inline health probe when settings are loaded and OpenCode is enabled
        require_once __DIR__ . '/../lib/opencode.php';
        $probe = OpenCodeClient::isAvailable($opencodeHost, $opencodePort, (int) ceil($opencodeTimeout / 2));
        if ($opencodeEnabled || !$opencodeEnabled) {
            $opencodeAvailable = $probe['available'];
            $opencodeMessage = $probe['message'] ?: 'OpenCode is not configured in settings.';
        } else {
            $opencodeMessage = 'OpenCode is disabled in Settings.';
        }
    }
}
?>

<div class="card mb-4" id="opencode-status-card">
    <div class="card-header d-flex align-items-center gap-2">
        <i class="bi bi-robot"></i>
        <h5 class="mb-0">OpenCode</h5>
    </div>
    <div class="card-body">
        <?php if ($openCodeEnabled): ?>
            <p class="mb-2 text-muted">
                Host: <strong><?= e($opencodeHost) ?></strong>, Port: <strong><?= $opencodePort ?></strong>, Timeout: <strong><?= $opencodeTimeout ?>s</strong>
            </p>
        <?php else: ?>
            <span class="badge bg-secondary">Disabled</span>
            <p class="mt-2 text-muted mb-0">OpenCode is disabled. Enable it in <a href="<?= e($router ? $router->routeUrl('/settings') : '/settings') ?>">Settings</a>.</p>
        <?php endif; ?>

        <div class="mt-3 d-flex align-items-center gap-2" id="opencode-status-indicator">
            <?php if ($opencodeEnabled && $opencodeAvailable === true): ?>
                <span class="badge bg-success">Available</span>
            <?php elseif ($opencodeEnabled && $opencodeAvailable === false): ?>
                <span class="badge bg-warning text-dark">Unavailable</span>
                <small class="text-muted"><?= e($opencodeMessage) ?></small>
            <?php else: ?>
                <span class="badge bg-secondary" id="opencode-loading-badge">Checking…</span>
            <?php endif; ?>

            <!-- Manual command fallback when unavailable -->
            <?php if (($opencodeEnabled && $opencodeAvailable === false) || ($openCodeSettingsLoaded && !$opencodeEnabled)): ?>
                <div class="mt-2">
                    <small class="text-muted">Manual: run <code>opencode /plan</code> or <code>opencode /next</code> locally.</small><br>
                    <?php if (!empty($loadedSettings['opencode_executable'])): ?>
                        <small class="text-muted">Executable fallback: <code><?= e($loadedSettings['opencode_executable']) ?></code></small>
                    <?php endif; ?>
                </div>
            <?php endif; ?>

            <?php if ($openCodeSettingsLoaded && !$opencodeEnabled): ?>
                <div class="mt-2">
                    <small class="text-muted"><a href="<?= e($router ? $router->routeUrl('/settings') : '/settings') ?>">Enable OpenCode in Settings</a> to check server availability.</small><br>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<script>
// Periodic AJAX health probe for OpenCode when enabled
(function() {
    var interval = setInterval(function() {
        fetch('/api/opencode.php', { method: 'POST' })
            .then(function(res) { return res.json(); })
            .then(function(data) {
                clearInterval(interval);
                updateOpenCodeStatus(data.available, data.message);
            })
            .catch(function() {
                updateOpenCodeStatus(false, 'Network unreachable.');
            });
    }, 500); // brief delay to allow POST body to settle

    function updateOpenCodeStatus(available, message) {
        var badge = document.querySelector('#opencode-status-indicator > .badge');
        if (!badge) return;
        if (available) {
            badge.className = 'badge bg-success';
            badge.textContent = 'Available';
        } else {
            badge.className = 'badge bg-warning text-dark';
            badge.textContent = 'Unavailable';
            var small = document.querySelector('#opencode-status-indicator > small.text-muted');
            if (small) small.textContent = message || '';
        }
    }
})();

// Add command execution logic
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
