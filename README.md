# Kanban Dashboard

A project management web application with a Kanban board interface, built as a lightweight PHP micro-framework with JSON-backed persistence (no database).

## Features

- **Kanban Board** — Drag-and-drop task management across columns (Todo, In Progress, Done, Archive) with nested subtasks, tags, priority levels, due dates, and metadata stored inline as HTML comments in KANBAN.md files.
- **Project Management** — Create, discover, and manage multiple Markdown-backed projects from the dashboard. Each project has its own `KANBAN.md`, `ROADMAP.md`, and `DESIGN.md`.
- **Roadmap Generation** — Generate ROADMAP.md content (release milestones, timelines) from a project's KANBAN.md via Ollama integration.
- **AI Prompts Registry** — Configurable, versioned prompt templates stored as markdown files in `assets/prompts/` with metadata in `config/ai_prompts.json`. Includes per-prompt model overrides.
- **Settings Management** — Configure Ollama connection (host, port, timeout, context window, default model) and timezone through the UI, persisted to `config/settings.json`.

## Requirements

- **PHP 8.1+** (uses typed properties, union types, attribute-like array shapes in docblocks)
- **Apache** with `mod_rewrite` enabled **or** Nginx with proper rewrite rules **or** PHP built-in server (`php -S localhost:8000 index.php`)
- **cURL extension** enabled (required for Ollama API calls)
- A running **Ollama instance** (default: `http://localhost:11434`) — optional; all UI features work without it

## Installation

### 1. Clone or extract the repository

```bash
cd /path/to/projects
git clone <repository-url> kanban
cd kanban
```

### 2. Install Composer dependencies

```bash
composer install
```

Currently requires `erusev/parsedown ^1.8` (declared in `composer.json`; autoload namespace `Kanban\\` mapped to `lib/`).

### 3. Configure your Ollama instance

Open the **Settings** page (`/settings`) or edit `config/settings.json` directly:

```json
{
    "timezone": "America/Toronto",
    "ollama_host": "localhost",
    "ollama_port": 11434,
    "ollama_timeout": 30,
    "ollama_context_window": 4096,
    "ollama_model": ""
}
```

See [docs/configuration.md](docs/configuration.md) for full schema details.

### 4. Verify server routing

The application uses a front controller pattern — all requests route through `index.php`. Apache `.htaccess` rewrite rules are written automatically on first load (if `mod_rewrite` is enabled). For Nginx, add:

```nginx
location / {
    try_files $uri $uri/ /index.php?$query_string;
}
```

For PHP's built-in development server:

```bash
php -S localhost:8000 index.php
```

## Quick Start

1. Open the app URL in your browser (e.g., `http://localhost/kanban/`).
2. On the dashboard (`/`), create a new project by entering a name and optional Git repository URL, then click **Create**.
3. Navigate to `/kanban` to access the Kanban board for your active project.
4. Add tasks (columns auto-create on first use), drag cards between columns, toggle checkboxes, set priorities, add tags, and manage nested subtasks.
5. Use `/roadmap`, `/design`, `/next`, and `/agents` pages to view generated or manual planning documents.

## Project Structure

```
kanban/
├── index.php           — Front controller (routes, helpers, entry point)
├── router.php          — Router class (URL routing, project-aware path matching)
├── bootstrap.php       — Application initialization scaffold
├── composer.json       — Dependencies (Parsedown, PSR-4 autoloader for Kanban\\)
├── config/             — Machine-readable JSON configs
│   ├── settings.json   — Ollama + timezone settings
│   ├── projects.json   — Active project registry
│   └── ai_prompts.json — Prompt registry metadata (version, model overrides)
├── lib/                — Shared PHP libraries / domain logic
│   ├── settings.php    — loadSettings() / saveSettings()
│   ├── ollama.php      — getOllamaConnection(), ollamaRequest(), ollamaPrompt()
│   ├── ai_prompts.php  — AIPromptsRegistry class + global helpers (getPrompt, setPrompt, etc.)
│   ├── ai.php          — generateTaskDecomposition(), generateSubtasks(), suggestTaskPriority(), generateTaskReview()
│   ├── roadmap.php     — RoadmapGenerator class (generateRoadmapFromKanban, generateReleaseMilestones, generateTimeline)
│   ├── kanban.php      — Complete Kanban board parsing, rendering, mutation, persistence (~56 functions)
│   ├── next.php        — (empty; future NEXT.md generation logic)
│   └── projects.php    — (empty; future project management helpers)
├── api/                — AJAX/xhr API endpoints
│   ├── kanban.php      — POST /api/kanban : full KANBAN.md board CRUD + drag-drop sync
│   ├── roadmap.php     — POST /api/roadmap : generate complete roadmap from KANBAN.md content
│   ├── ai_prompts.php  — POST /api/ai_prompts : upsert a prompt template entry
│   ├── settings.php    — (empty stub)
│   ├── ollama.php      — (empty stub)
│   ├── projects.php    — (empty stub)
│   └── next.php        — (empty stub)
├── view/               — Page-view templates (UI; each maps to one route URL)
│   ├── dashboard.php   — / : project discovery, server routing status, quick links
│   ├── kanban.php      — /kanban : drag-and-drop Kanban board UI with task editor sidebar
│   ├── roadmap.php     — /roadmap : rendered markdown viewer for ROADMAP.md
│   ├── design.php      — /design : rendered markdown viewer for DESIGN.md
│   ├── next.php        — /next : rendered markdown viewer for NEXT.md
│   ├── agents.php      — /agents : AI agent interface (command buttons, config)
│   └── settings.php    — /settings : Ollama + timezone configuration form
├── layout/             — Layout/wrapper templates
│   └── index.php       — Master HTML wrapper (doctype, head, nav, footer)
├── assets/             — Static frontend assets
│   ├── css/            — Bootstrap 5, Select2 CSS, font files
│   └── js/             — jQuery, Bootstrap bundle, Sortable.js, timeago.js, Select2, domain-specific JS (kanban.js, settings.js, roadmap.js, etc.)
├── projects/           — Sub-project directories; each contains its own KANBAN.md, ROADMAP.md, DESIGN.md
├── .opencode/          — OpenCode AI tooling config (non-application)
├── docs/               — Documentation files
│   ├── ai_prompts_registry_design.md   — Prompt registry design document
│   └── ai_prompts_registry_update.md   — Implementation changelog
├── DESIGN.md           — Architectural decisions and patterns (directory structure, Front Controller, Router class, Shared Library, Project-as-Folder)
├── KANBAN.md           — Source of truth for task tracking and sprint planning
├── ROADMAP.md          — Long-term project roadmap
├── NEXT.md             — Execution queue (derived from KANBAN.md)
├── AGENTS.md           — Agent workflow conventions
└── .htaccess           — Apache rewrite rules (auto-generated on first load if mod_rewrite is available)
```

## Routes

| URL | Page | Description |
|-----|------|-------------|
| `/` | Dashboard (`view/dashboard.php`) | Project creation form, discovery table, server routing status |
| `/kanban` | Kanban board (`view/kanban.php`) | Drag-and-drop task management; also project-scoped: `/kanban/{slug}` |
| `/roadmap` | Roadmap (`view/roadmap.php`) | Markdown viewer for ROADMAP.md; also `/roadmap/{slug}` |
| `/design` | Design (`view/design.php`) | Markdown viewer for DESIGN.md; also `/design/{slug}` |
| `/next` | Next (`view/next.php`) | Markdown viewer for NEXT.md; also `/next/{slug}` |
| `/agents` | Agents (`view/agents.php`) | AI agent command interface; also `/agents/{slug}` |
| `/settings` | Settings (`view/settings.php`) | Ollama connection and timezone configuration |

Project-scoped routes use URL patterns like `/kanban/core`, `/roadmap/project-name`. The Router class extracts the slug, resolves it against `config/projects.json`, and passes it to the view.

## Server Routing Status

On first page load (dashboard `/`), the app auto-detects the server type and writes `.htaccess` rewrite rules if running Apache with `mod_rewrite`. For other servers, a status card shows manual configuration instructions. The detection checks `$_SERVER['SERVER_SOFTWARE']` for `apache`/`nginx` substrings and `PHP_SAPI === 'cli-server'` for PHP's built-in server.

## API Endpoints

See [docs/api-reference.md](docs/api-reference.md) for complete endpoint documentation including request/response schemas.

## Configuration Files

See [docs/configuration.md](docs/configuration.md) for all config file schemas and current settings.

## Contributing / Development Notes

- All persistent state is stored in JSON config files under `config/` — no database is used.
- Tasks in the Kanban board store structured metadata (priority, due dates, timestamps, archived-from info) inline as HTML comments parsed by `lib/kanban.php`.
- Libraries in `/lib/` must remain standalone: no view or API dependencies, no direct HTML output (Shared Library Pattern per DESIGN.md).
- Views render plain PHP templates via `include $file` — no template engine.
- New routes should be added through the `$routes` array in `index.php`.
- Prompt templates live as markdown files under `assets/prompts/`; their metadata is tracked in `config/ai_prompts.json`.
