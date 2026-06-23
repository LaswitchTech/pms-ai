

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

<?php renderProjectManager($router, $projectsConfig, $projectActionResult, $setupErrors); ?>
<?php renderServerRoutingStatus($rewriteStatus, $isApache, $isNginx, $isBuiltinServer); ?>

<?php
// Load OpenCode settings for availability card
$openCodeAvailable = null;
$opencodeMessage = '';

if (file_exists(__DIR__ . '/../lib/opencode.php')) {
    require_once __DIR__ . '/../lib/settings.php';
    $loadedSettings = loadSettings(__DIR__ . '/../config/settings.json');
    if ($loadedSettings !== []) {
        $openCodeSettingsLoaded = true;
        $opencodeHost = (string) ($loadedSettings['opencode_host'] ?? 'localhost');
        $opencodePort = (int) ($loadedSettings['opencode_port'] ?? 8080);
        $opencodeTimeout = (int) ($loadedSettings['opencode_timeout'] ?? 60);

        // Inline health probe when settings are loaded
        require_once __DIR__ . '/../lib/opencode.php';
        $probe = OpenCodeClient::isAvailable($opencodeHost, $opencodePort, (int) ceil($opencodeTimeout / 2));
        $opencodeAvailable = $probe['available'];
        $opencodeMessage = $probe['message'] ?: 'OpenCode config incomplete.';
    }
}
?>

<div class="card mb-4" id="opencode-status-card">
    <div class="card-header d-flex align-items-center gap-2">
        <i class="bi bi-robot"></i>
        <h5 class="mb-0">OpenCode</h5>
    </div>
    <div class="card-body">
        <?php if ($openCodeSettingsLoaded): ?>
            <p class="mb-2 text-muted">
                Host: <strong><?= e($opencodeHost) ?></strong>, Port: <strong><?= $opencodePort ?></strong>, Timeout: <strong><?= $opencodeTimeout ?>s</strong>
            </p>
        <?php else: ?>
            <span class="badge bg-secondary">Disabled</span>
            <p class="mt-2 text-muted mb-0">OpenCode is disabled. Enable it in <a href="<?= e($router ? $router->routeUrl('/settings') : '/settings') ?>">Settings</a>.</p>
        <?php endif; ?>

        <div class="mt-3 d-flex align-items-center gap-2" id="opencode-status-indicator">
            <?php if ($opencodeAvailable === true): ?>
                <span class="badge bg-success">Available</span>
            <?php elseif ($opencodeAvailable === false): ?>
                <span class="badge bg-warning text-dark">Unavailable</span>
                <small class="text-muted"><?= e($opencodeMessage) ?></small>
            <?php else: ?>
                <span class="badge bg-secondary" id="opencode-loading-badge">Checking…</span>
            <?php endif; ?>

            <!-- Manual command fallback when unavailable -->
            <?php if ($opencodeAvailable === false): ?>
                <div class="mt-2">
                    <small class="text-muted">Manual: run <code>opencode /plan</code> or <code>opencode /next</code> locally.</small><br>
                    <?php if (!empty($loadedSettings['opencode_executable'])): ?>
                        <small class="text-muted">Executable fallback: <code><?= e($loadedSettings['opencode_executable']) ?></code></small>
                    <?php endif; ?>
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
</script>
</body>
</html>
