# Next

## In Progress

### Prompt Editor UI

A UI for viewing, editing, and saving AI prompt templates stored in the registry, so that users can customize prompts without touching code.

**Design**: An AI Prompts section will be added to `view/settings.php` as a new tab/section within Settings. It displays a table of registered prompts (key, current template, version, updated_at). Each row has an "Edit" button that opens a modal with a `textarea` for the template and fields for key/version. Save writes back via `lib/ai_prompts.php`. Alternatively, if Settings is not the right home, a standalone `view/ai_prompts.php` page can be added with a `/settings/prompts` route, showing the same table + editor UI.

**Files**: `view/settings.php`, `view/ai_prompts.php`, `lib/ai_prompts.php`
**Referenced by**: KANBAN.md → `AI Services → Prompt Editor UI`

- [x] Add an AI Prompts section to the Settings page or standalone view with a table of registered prompts <!-- created_at: 2026-06-15T14:50:28-04:00 priority: normal -->
- [x] Build a prompt editor form that loads, edits, and saves individual prompt entries back via `lib/ai_prompts.php` <!-- created_at: 2026-06-15T14:50:28-04:00 priority: normal -->
