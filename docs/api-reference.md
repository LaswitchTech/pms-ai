# API Reference

All AJAX endpoints are under `/api/` and respond only with JSON. Each endpoint explicitly rejects non-matching HTTP methods with a 405 status.

## Endpoints

### POST /api/kanban

Full Kanban board CRUD: create, update, delete tasks; drag-drop sync; archiving/restoring tasks; column management. Supports copying task markdown for display in a sidebar editor.

**Request:**
```http
POST /api/kanban HTTP/1.1
Content-Type: application/json

{
    "action": "copy_markdown",         // or other board actions handled by lib/kanban.php
    "project_slug": "hauser",          // optional: scope to a specific project
    "column": "todo",                  // column name (for copy markdown action)
    "path": "0.1.2"                    // dot-separated path to task within column
}
```

**Response (copy_markdown success):**
```json
{
    "success": true,
    "action": "copy_markdown",
    "message": "Task markdown generated.",
    "saved": false,
    "data": {
        "column": "todo",
        "path": "0.1.2",
        "task": {},                       // task object with all properties
        "markdown": "# Task title\n\nDescription..."
    },
    "board": {}                          // full board state (columns, archive tasks, column settings)
}
```

**Response (error / task not found):**
```json
{
    "success": false,
    "action": "copy_markdown",
    "message": "Task could not be found.",
    "saved": false,
    "data": {
        "column": "todo",
        "path": "0.1.2"
    },
    "board": {}
}
```

**Response (missing helpers — 501):**
```json
{
    "success": false,
    "message": "Kanban API is not ready yet. Missing required helpers: ...",
    "missing_helpers": true,
    "missing_functions": ["handleKanbanAction", "..."]
}
```

**Supported actions (observed in code):**
- `copy_markdown` / `copy_task_markdown` — generate task markdown representation
- All other board mutations are delegated to `handleKanbanAction()` in `lib/kanban.php`

---

### POST /api/roadmap

Generate ROADMAP.md content from KANBAN.md using the RoadmapGenerator class (which relies on `AIPromptsRegistry`).

**Request:**
```http
POST /api/roadmap HTTP/1.1
Content-Type: application/json

{
    "kanban_content": "# Full KANBAN.md file contents as a string"
}
```

Raw markdown input is also accepted (any non-JSON body is treated as kanban content directly).

**Response (success):**
```json
{
    "success": true,
    "roadmap": "Markdown roadmap content...",
    "milestones": ["Milestone 1", "Milestone 2"],
    "timeline": [{"title": "", "date": "", "description": ""}]
}
```

**Response (error):**
```json
{
    "success": false,
    "error": "Roadmap generation failed: ..."
}
```

---

### POST /api/ai_prompts

Upsert a prompt template entry in the AI Prompts Registry. Saves to both `config/ai_prompts.json` (metadata) and `assets/prompts/{key}.md` (template content).

**Request:**
```http
POST /api/ai_prompts HTTP/1.1
Content-Type: application/json

{
    "key": "task_decomposition",        // required: unique prompt identifier
    "template": "Your prompt text here.", // required: the template string
    "model": "qwen3.6:35b-128k"         // optional: per-prompt Ollama model override; null = use default
}
```

**Response (success):**
```json
{
    "success": true,
    "message": "Prompt saved successfully",
    "key": "task_decomposition",
    "template": "Your prompt text here.",
    "version": 14,
    "updated_at": "2026-06-16T18:30:00+00:00",
    "model": "qwen3.6:35b-128k"
}
```

**Response (missing required fields — 400):**
```json
{
    "error": "Missing required fields: key and template"
}
```

---

## Empty API Stubs

The following endpoints exist but are not yet implemented:

| Endpoint | File | Status |
|----------|------|--------|
| POST /api/settings | `api/settings.php` | Empty stub — no implementation |
| POST /api/ollama | `api/ollama.php` | Empty stub — no implementation |
| POST /api/projects | `api/projects.php` | Empty stub — no implementation |
| POST /api/next | `api/next.php` | Empty stub — no implementation |

These stubs serve as route placeholders. When implemented, they should follow the same pattern: POST-only request handling, JSON response format, and 405 rejection of non-POST methods.

## Error Conventions

All endpoints use standard HTTP status codes:

| Status | Meaning |
|--------|---------|
| 200 | Success |
| 400 | Bad input / missing required fields |
| 405 | Not allowed (wrong HTTP method) |
| 422 | Unprocessable — task not found, validation failure |
| 500 | Server error — internal failure |
| 501 | Service unavailable — API not ready yet (missing helper functions) |
