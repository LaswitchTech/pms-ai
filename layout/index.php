<?php
/**
 * Main application layout.
 *
 * Expected variables from index.php:
 * - Router $router
 * - array<string, array<string, mixed>> $routes
 * - string $path
 * - ?array<string, mixed> $route
 * - ?string $routeError
 * - ?array<string, mixed> $selectedProject
 * - array<string, mixed> $routeMatch
 * - array<string, mixed> $rewriteStatus
 * - bool $isApache
 * - bool $isNginx
 * - bool $isBuiltinServer
 * - array<string, mixed> $projectsConfig
 * - ?array<string, mixed> $projectActionResult
 * - array<int, string> $setupErrors
 */

$pageTitle = is_array($route ?? null) ? (string) ($route['title'] ?? 'Not Found') : 'Not Found';
$projectSlug = $selectedProject['slug'] ?? ($routeMatch['project_slug'] ?? null);
$showPageTitle = $pageTitle !== 'Dashboard';
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Project Management<?= $showPageTitle ? ' - ' . e($pageTitle) : ''; ?></title>
    <link href="/assets/css/bootstrap.min.css" rel="stylesheet">
    <link href="/assets/css/bootstrap-icons.min.css" rel="stylesheet">
    <link href="/assets/css/select2.min.css" rel="stylesheet">
    <link href="/assets/css/select2-bootstrap-5-theme.min.css" rel="stylesheet">
    <style>
        body {
            background: #f6f7fb;
        }

        .app-shell {
            min-height: 100vh;
        }

        .app-nav {
            background: rgba(255, 255, 255, .9);
            backdrop-filter: blur(12px);
            border-bottom: 1px solid rgba(0, 0, 0, .08);
        }

        .hero-card {
            border-radius: 1rem;
        }

        .content-frame > main,
        .content-frame > .container-fluid,
        .content-frame > .container {
            padding: 0 !important;
            max-width: none !important;
        }
    </style>

    <script src="/assets/js/jquery.min.js"></script>
    <script src="/assets/js/bootstrap.bundle.min.js"></script>
    <script src="/assets/js/timeago.min.js"></script>
    <script src="/assets/js/Sortable.min.js"></script>
    <script src="/assets/js/select2.min.js"></script>
</head>
<body>
    <div class="app-shell">
        <nav class="navbar navbar-expand-lg app-nav sticky-top">
            <div class="container-fluid px-4">
                <a class="navbar-brand fw-semibold" href="<?= e($router->routeUrl('/')); ?>">
                    <i class="bi bi-grid-1x2"></i>
                    Project Management
                </a>
                <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#mainMenu" aria-controls="mainMenu" aria-expanded="false" aria-label="Toggle navigation">
                    <span class="navbar-toggler-icon"></span>
                </button>
                <div class="collapse navbar-collapse" id="mainMenu">
                    <?php $menuProjectSlug = $projectSlug; ?>

                    <ul class="navbar-nav ms-auto mb-2 mb-lg-0 gap-lg-1">
                        <?php foreach ($routes as $routePath => $item): ?>
                            <?php if (!is_array($item) || ($item['nav'] ?? true) === false) { continue; } ?>

                            <?php
                            $menuUrl = $router->routeUrl($routePath);

                            if ($menuProjectSlug !== null && $routePath !== '/') {
                                $menuUrl = $router->projectRouteUrl($routePath, (string) $menuProjectSlug);
                            }
                            ?>

                            <li class="nav-item">
                                <a
                                    class="nav-link<?= ($path === $router->routeUrl((string) $routePath) || str_starts_with($path, $router->routeUrl((string) $routePath) . '/')) ? ' active fw-semibold' : ''; ?>"
                                    href="<?= e($menuUrl); ?>"
                                >
                                    <i class="bi <?= e((string) $item['icon']); ?>"></i>
                                    <?= e((string) $item['title']); ?>
                                </a>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            </div>
        </nav>

        <main class="container-fluid p-4 content-frame">
            <?php if ($route === null): ?>
                <div class="alert alert-danger">
                    <strong>404.</strong> This page does not exist.
                </div>
            <?php else: ?>
                <?php renderIncludedPage($router, $route['file'] ?? null, $selectedProject, $routeError, $rewriteStatus, $isApache, $isNginx, $isBuiltinServer, $projectsConfig, $projectActionResult, $setupErrors); ?>
            <?php endif; ?>
        </main>
    </div>
    <script src="/assets/js/opencode.js"></script>
</body>
</html>
