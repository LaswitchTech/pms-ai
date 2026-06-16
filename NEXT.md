# Next

## Sprint Goal

Migrate prompts from `config/ai_prompts.json` to individual markdown template files at `/assets/prompts/{key}.md`, with the registry falling back to reading `.md` templates and the editor UI persisting changes as `.md` files.

## Active Task List

### Migrate Prompts to Markdown Files — High Priority
**Files**: `lib/ai_prompts.php`, `config/ai_prompts.json`, `view/settings.php` (editor), `assets/prompts/`

**Design**:
- Create `/assets/prompts/{key}.md` for each prompt in the current registry (task_decomposition, task_subtasks, task_priority, roadmap_generation, release_milestones, project_timeline) with template content as raw body.
- In `AIPromptsRegistry::loadFromFile()`, after loading JSON config, fall back to reading corresponding `.md` files from `/assets/prompts/` for missing or custom entries so the markdown source is authoritative.
- Update the prompt editor UI (save handler in `view/settings.php`) to write updated `template` field to `assets/prompts/{key}.md`; on save failure, fall back to writing the JSON manifest (`config/ai_prompts.json`) as the backing store.

1. [x] Create `/assets/prompts/{key}.md` markdown files for each prompt template currently in `config/ai_prompts.json`
2. [ ] Update `AIPromptsRegistry::loadFromFile()` to fall back to reading markdown template files from `/assets/prompts/`
3. [ ] Update prompt editor UI (view) to save edits as `.md` files in `/assets/prompts/` with a JSON manifest fallback
