# Next

## In Progress

### Prompt Fallback Defaults

Ensure all prompt templates have safe fallback defaults when registry entries are missing or corrupted, so that `lib/ai.php` and `lib/roadmap.php` never raise errors during AI service calls.

**Design**: Ship a set of default prompt templates embedded in the codebase as fallback values. Both consumers (`lib/ai.php`, `lib/roadmap.php`) call a registry accessor that returns defaults when a key is missing or corrupted. No new UI needed — this is an internal reliability layer.

**Files**: `lib/ai_prompts.php`
**Referenced by**: KANBAN.md → `AI Services → Prompt Fallback Defaults`

- [x] Ship default prompt templates in the codebase as fallback values for any registry entries that are missing or corrupted <!-- created_at: 2026-06-16T08:30:00-04:00 priority: high -->
- [x] Ensure `lib/ai.php` and `lib/roadmap.php` gracefully fall back to defaults when a prompt key does not exist in the registry <!-- created_at: 2026-06-16T08:30:00-04:00 priority: high -->

## Completed (previous)

### Prompt Editor UI

A UI for viewing, editing, and saving AI prompt templates stored in the registry.

**Referenced by**: KANBAN.md → `AI Services → Prompt Editor UI`

- [x] Add an AI Prompts section to the Settings page or standalone view with a table of registered prompts <!-- created_at: 2026-06-15T14:50:28-04:00 completed_at: 2026-06-15T16:18:35-04:00 priority: normal -->
- [x] Build a prompt editor form that loads, edits, and saves individual prompt entries back via `lib/ai_prompts.php` <!-- created_at: 2026-06-15T14:50:28-04:00 completed_at: 2026-06-15T20:28:48-04:00 priority: normal -->
