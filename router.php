<?php

declare(strict_types=1);

final class Router
{
    /**
     * @var array<string, array<string, mixed>>
     */
    private array $routes = [];

    /**
     * @param array<string, array<string, mixed>> $routes
     */
    public function __construct(array $routes = [])
    {
        foreach ($routes as $path => $route) {
            $this->add($path, $route);
        }
    }

    /**
     * @param array<string, mixed> $route
     */
    public function add(string $path, array $route): void
    {
        $this->routes[$this->normalizePath($path)] = $route;
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    public function routes(): array
    {
        return $this->routes;
    }

    public function currentPath(?string $requestUri = null): string
    {
        $path = parse_url($requestUri ?? ($_SERVER['REQUEST_URI'] ?? '/'), PHP_URL_PATH);

        return $this->normalizePath((string) $path);
    }

    public function routeUrl(string $path): string
    {
        $path = $this->normalizePath($path);

        return $path === '/' ? '/' : $path;
    }

    public function projectRouteUrl(string $route, string $slug): string
    {
        return rtrim($this->routeUrl($route), '/') . '/' . rawurlencode($slug);
    }

    /**
     * @param array<string, mixed> $projectsConfig
     * @return array{route: ?array<string, mixed>, project: ?array<string, mixed>, project_slug: ?string, error: ?string}
     */
    public function resolve(string $path, array $projectsConfig = []): array
    {
        $path = $this->normalizePath($path);

        if (isset($this->routes[$path])) {
            return [
                'route' => $this->routes[$path],
                'project' => null,
                'project_slug' => null,
                'error' => null,
            ];
        }

        if (preg_match('#^/(kanban|roadmap|design|next|agents)/([^/]+)$#', $path, $matches) !== 1) {
            return [
                'route' => null,
                'project' => null,
                'project_slug' => null,
                'error' => 'This page does not exist.',
            ];
        }

        $basePath = '/' . $matches[1];
        $slug = rawurldecode($matches[2]);
        $projects = is_array($projectsConfig['projects'] ?? null) ? $projectsConfig['projects'] : [];

        if (!isset($this->routes[$basePath])) {
            return [
                'route' => null,
                'project' => null,
                'project_slug' => $slug,
                'error' => 'This page does not exist.',
            ];
        }

        if (!isset($projects[$slug]) || !empty($projects[$slug]['missing'])) {
            return [
                'route' => $this->routes[$basePath],
                'project' => null,
                'project_slug' => $slug,
                'error' => 'The selected project does not exist: ' . $slug,
            ];
        }

        return [
            'route' => $this->routes[$basePath],
            'project' => is_array($projects[$slug]) ? $projects[$slug] : null,
            'project_slug' => $slug,
            'error' => null,
        ];
    }

    private function normalizePath(string $path): string
    {
        $path = '/' . trim($path, '/');

        return $path === '/' ? '/' : rtrim($path, '/');
    }
}
