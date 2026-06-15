# Design

This document captures the architectural decisions and patterns used in the Kanban project management application. **Agent teams should read this file before proposing structural changes or understanding where new code belongs.**

## Directory Structure

```
kanban/
├── view/           ← Page-view templates (UI)
├── lib/            ← Shared libraries / domain logic services
├── api/            ← AJAX/xhr API endpoints
├── layout/         ← Layout/wrapper templates
├── assets/         ← Static frontend assets (CSS, JS)
├── config/         ← Machine-readable persistence files
├── projects/       ← Sub-project directories (managed repositories)
├── .opencode/      ← OpenCode AI tooling config (non-app)
├── index.php       ← Front controller (routes, helpers, entry point)
├── router.php      ← Router class (URL routing, project-aware matching)
└── bootstrap.php   ← Application initialization scaffold (empty)
```

### Directory Purposes

| Directory | Purpose | Example Files |
|-----------|---------|---------------|
| `view/` | Page-view templates. Each file corresponds to one URL route. Rendered inside the layout via `include`. | `view/kanban.php`, `view/settings.php` |
| `lib/` | Shared libraries/services with business domain logic. Standalone PHP files with functions/classes — no direct HTML output. | `lib/settings.php`, `lib/ollama.php`, `lib/kanban.php` |
| `api/` | AJAX/xhr API endpoints. Names mirror their view counterparts for clarity. | `api/kanban.php`, `api/settings.php` |
| `layout/` | Master HTML wrapper (doctype, head, nav, footer). Each `view/*.php` is included into it. | `layout/index.php` |
| `assets/` | Static frontend assets — CSS frameworks, fonts, and vanilla JS. No custom CSS; app styling is inline or in views. | `assets/css/bootstrap.min.css`, `assets/js/kanban.js` |
| `config/` | Machine-readable persistence files (JSON). No database used. | `config/projects.json` |
| `projects/` | Sub-project directories for managed repositories. Each contains its own Kanban, Roadmap, and Design markdown files. | `projects/myproject/KANBAN.md` |

## Architectural Patterns

### 1. Front Controller Pattern

All requests enter through `index.php`, which defines routes as a PHP array mapping URL paths to view files. This is a custom micro-framework approach — not Laravel/Symfony or similar.

**Rule**: All routing, URL resolution, and the main layout rendering entry point must go through `index.php`. Do not add direct access to view files that bypasses this router.

### 2. Router Class (`router.php`)

A lightweight final class that:
- Resolves paths by URL
- Supports project-scoped variants (e.g., `/kanban/{slug}`)
- Extracts project slugs from URLs
- Generates URL helpers

**Rule**: All new routes and URL helpers should use the Router class, not inline parsing.

### 3. View-Inclusion Pattern

Views are plain PHP templates rendered via `include $file`. No template engine is used. Views mix HTML, PHP logic, and Bootstrap classes directly. The layout wrapper (`layout/index.php`) wraps them.

**Rule**: Views belong **only** in `/view/`. Each page-view file renders content for a single URL route. Never put domain logic or API handlers in view files.

### 4. Shared Library Pattern

Libraries live at the top level of `/lib/` with flat naming that mirrors their domain/service:

- `lib/settings.php` — Settings storage layer
- `lib/ollama.php` — Ollama integration
- `lib/kanban.php` — Kanban board logic
- `lib/projects.php` — Project CRUD/configuration
- `lib/roadmap.php` — Roadmap generation
- `lib/next.php` — NEXT.md task queue generation

**Rule**: Libraries must be standalone, not depend on view or API context. Never include HTML output from libraries.

### 5. Flat Namespace Structure

All views, libraries, and API files live at the same depth within their folder (no subdirectories):
- 9+ view files in `/view/`
- 6+ libs in `/lib/`
- 6+ API files in `/api/`

**Rule**: Keep files flat. Do not create subdirectories in `view/`, `lib/`, or `api/`. If a domain grows too large, consider adding it to the Router class and splitting view/API responsibilities into named modules under a new directory level rather than nesting deeper.

### 6. Project-as-Folder Pattern

Managed projects are discovered from the `projects/` directory. Each project contains its own:
- `KANBAN.md` — Task tracking source of truth
- `ROADMAP.md` — Generated long-term planning view
- `DESIGN.md` — Architecture/design decisions for that project

The active project is tracked in `config/projects.json`. The Router class supports detecting the current active project from the URL.

**Rule**: Never hardcode project paths. Always use the Router or Config helpers to resolve project-specific file paths.

## UI Structure

### View Files (UI)

All view files live in `/view/`. Each corresponds to one URL route:

| Route URL | View File |
|-----------|-----------|
| `/` | `view/dashboard.php` |
| `/kanban` | `view/kanban.php` |
| `/roadmap` | `view/roadmap.php` |
| `/design` | `view/design.php` |
| `/next` | `view/next.php` |
| `/agents` | `view/agents.php` |
| /settings | `view/settings.php` |

View files have the following structure:
1. Include any necessary libraries via `require __DIR__ . '/../lib/filename.php'`
2. Set up any page-specific logic (form processing, AJAX response)
3. Output HTML with Bootstrap classes

The layout wrapper (`layout/index.php`) provides the outer shell (doctype, head, nav, footer). View files are included into this layout via `include $file`.

### Layout Files

Layouts live in `/layout/`. The master layout file is:

- `layout/index.php` — Main HTML wrapper with Bootstrap 5 setup

Do not create additional layout wrappers unless a fundamentally different page structure is required. Use the main one for all pages.

## API Structure

API endpoints mirror both domain services and their paired views. Each file in `/api/` corresponds to its `/view/` counterpart:

| API File | Paired With |
|----------|-------------|
| `api/kanban.php` | `view/kanban.php` (drag/drop, status changes) |
| `api/settings.php` | `view/settings.php` (form POST persistence) |
| `api/ollama.php` | `view/ollama.php` (connection testing, health checks) |
| `api/projects.php` | `view/projects.php` |
| `api/roadmap.php` | `view/roadmap.php` |
| `api/next.php` | `view/next.php` |

API files respond:
1. Only to the expected HTTP method (GET, POST) — reject others with 405
2. Return JSON responses only (no HTML output)
3. Always check request method before processing

## Static Asset Organization

Static assets live in `/assets/`:

### CSS
- `assets/css/bootstrap.min.css`
- `assets/css/bootstrap-icons.min.css`
- `assets/css/select2.min.css`
- `assets/css/select2-bootstrap-5-theme.min.css`
- Any custom CSS should be inline in views (no separate file for now)

### JavaScript
- `assets/js/jquery.min.js`
- `assets/js/bootstrap.bundle.min.js`
- `assets/js/Sortable.min.js`
- `assets/js/timeago.min.js`
- `assets/js/select2.min.js`
- Domain-specific JS files follow the pattern: `{feature}.js` (e.g., `kanban.js`, `settings.js`)

### Fonts
- `assets/css/fonts/bootstrap-icons.woff`
- `assets/css/fonts/bootstrap-icons.woff2`

**Rule**: Do not modify existing Bootstrap 5 or other third-party CSS/JS files. Always place new custom JavaScript in `/assets/js/` with a `{feature}.js` name pattern.

## Configuration & Persistence

### Machine-Readable Config

All persistent config lives in `/config/`:

| File | Purpose |
|------|---------|
| `config/projects.json` | Project registry map (active_project, list of managed projects) |

**Rule**: No database is used. All state flows through JSON config files and POST requests. Do not introduce file-based config formats other than JSON. Session-based state is not implemented or expected.

### Settings

User/application settings are stored in a root-level `settings.json` file:

```json
{
  "timezone": null,
  "ollama_host": "localhost",
  "ollama_port": 11434,
  "ollama_timeout": 30,
  "ollama_context_window": 4096,
  "ollama_model": ""
}
```

Settings are loaded and saved via `lib/settings.php`:
- `loadSettings(string $path): array` — reads from file or returns defaults
- `saveSettings(string $path, array $settings): bool` — validates and writes with 0644 permissions

**Rule**: Never hardcode application configuration values. Always use the settings layer. Always validate before saving.

## Constraints & Guardrails

1. **Views only contain UI logic** — domain logic goes in `/lib/`, API logic goes in `/api/`.
2. **Libraries must be framework-agnostic** — standalone PHP files with no Bootstrap, DOM, or view dependencies.
3. **All new routes go through `index.php`** — never add direct file access to bypass the router.
4. **No session-based state** — use JSON config files and POST request payloads instead.
5. **Flat directory structure** — do not create subdirectories in `view/`, `lib/`, or `api/`.
6. **All persistent config is JSON in `/config/`** — no databases, no env vars for app settings.
