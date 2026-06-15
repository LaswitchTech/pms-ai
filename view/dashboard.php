

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
