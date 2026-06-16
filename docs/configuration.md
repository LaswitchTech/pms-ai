# Configuration Guide

All persistent application configuration is stored as JSON files under `config/`. No database, environment variables, or session-based state is used.

## Files

### config/settings.json

User/application settings loaded by `lib/settings.php` via `loadSettings()` and written via `saveSettings()`.

```jsonc
{
    "timezone": "America/Toronto",          // PHP timezone identifier or null
    "ollama_host": "localhost",              // Ollama server hostname
    "ollama_port": 11434,                    // Ollama API port (integer)
    "ollama_timeout": 30,                    // Request timeout in seconds (integer)
    "ollama_context_window": 4096,           // Context window size for LLM requests (integer)
    "ollama_model": ""                       // Default model name sent to Ollama; empty means no override
}
```

**Constraints:**
- `ollama_port`, `ollama_timeout`, and `ollama_context_window` are validated as integers on save.
- Values not present in the file are filled from defaults defined in `lib/settings.php`.

### config/projects.json

Project registry maintained by the application. Defines which projects are managed and which is active.

```jsonc
{
    "active_project": "hauser",              // Slug of the currently active project; null means no project selected
    "projects": {                            // Keyed by project slug
        "core": {                            // Each value:
            "name": "Core",                  // human-readable project name
            "slug": "core",                  // URL-safe identifier used in routes like /kanban/core
            "path": "/Users/louis/Projects/LaswitchTech/core",  // absolute filesystem path to the project root
            "git_repository": "",            // Git origin URL; "" or null means not configured
            "created_at": null,              // ISO 8601 timestamp or null
            "updated_at": "2026-06-16T19:10:02-04:00"  // ISO 8601 timestamp; never null once created
        }
    }
}
```

**Constraints:**
- Slugs must be unique within the `projects` map.
- The Router class resolves project-scoped routes (e.g. `/kanban/{slug}`) by matching the URL slug against keys in this file.

### config/ai_prompts.json

AI prompt registry metadata — a JSON array of entries. Each entry references a corresponding markdown template stored under `assets/prompts/{key}.md`.

```jsonc
[
    {
        "key": "task_decomposition",         // Unique identifier; matches the filename without .md extension in assets/prompts/
        "version": 13,                        // Incremented each time the entry is modified
        "updated_at": "2026-06-16T16:11:02+00:00", // ISO 8601 timestamp of last save
        "model": "qwen3.6:35b-128k"          // Per-prompt model override; null means use default model from settings.json
    }
]
```

**Constraints:**
- The file is an array (not keyed object). `AIPromptsRegistry` in `lib/ai_prompts.php` builds an internal lookup by `key`.
- All prompt entries must have a corresponding markdown template at `assets/prompts/{key}.md`; if a template is missing, the registry falls back to its built-in default.

## Settings Schema Reference

All settings keys and their defaults as defined in `lib/settings.php`:

| Key | Type | Default | Description |
|-----|------|---------|-------------|
| `timezone` | string or null | `null` | PHP timezone identifier passed to `date_default_timezone_set()` |
| `ollama_host` | string | `"localhost"` | Ollama server hostname |
| `ollama_port` | int | `11434` | Ollama listening port |
| `ollama_timeout` | int | `30` | HTTP timeout in seconds for Ollama API calls |
| `ollama_context_window` | int | `4096` | Context window sent to Ollama |
| `ollama_model` | string | `""` | Default model; empty string means the server's default model is used |

## Project Structure Files

Each project under `projects/` (or an external directory pointed to via `path` in `config/projects.json`) should contain:

- `KANBAN.md` — Task tracking source of truth
- `ROADMAP.md` — Generated long-term planning view
- `DESIGN.md` — Architecture/design documentation

These files are read as plain markdown and rendered with Parsedown (`erusev/parsedown`).

## Prompt Markdown Templates

Prompt templates live under `assets/prompts/` as individual markdown files. Each filename (minus `.md`) must match a `key` in `config/ai_prompts.json`. 

**Observed prompt keys:**
- `task_decomposition`
- `task_subtasks`
- `task_priority`
- `roadmap_generation`
- `release_milestones`
- `project_timeline`

The `AIPromptsRegistry::loadFromFile()` method in `lib/ai_prompts.php` loads the config from `config/ai_prompts.json`, then for each entry attempts to load its markdown template from `assets/prompts/{key}.md`. If no template file exists, it uses the built-in default string.

## Editing Configuration

### Via UI
- Settings are edited on `/settings` and saved through the form's POST handler (in `view/settings.php`).
- AI prompts are edited via the prompt editor in the settings page; changes are persisted to both `config/ai_prompts.json` and `assets/prompts/{key}.md`.

### Direct File Editing
- All config files are plain JSON. Edit directly at your own risk and ensure valid JSON syntax.
- After editing `projects.json`, restart or reload the application — the project registry is not reloaded per-request in a way that would pick up hot changes to this file.
