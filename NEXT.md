# Next

## Sprint Goal

Implement Roadmap Generation Service — generate ROADMAP.md from KANBAN.md content using AI prompts.

## Active Task List <!-- default: In Progress -->

### Roadmap Generation Service

Generate a `ROADMAP.md` file from the current Kanban board state by composing an AI prompt via `AIPromptsRegistry`, calling the LLM endpoint, and persisting the generated markdown. This includes generating release milestones from task groupings/priorities and project timelines from task ordering/metadata.

**Design**:
- Follow `Shared Library Pattern` — create standalone PHP in `lib/roadmap.php`, no view or API dependencies, no direct HTML output (DESIGN.md §4).
- Use existing `AIPromptsRegistry` for prompt resolution (`config/ai_prompts.json` + markdown fallback) with per-prompt model support (already implemented per KANBAN.md Done section).
- Call LLM via the same `ollamaPrompt()` helper used by `lib/ai.php`, respecting settings (`lib/settings.php`) for host/port/timeout.
- Kanban board state is parsed using existing `board = parseKanbanBoard($path)` from `lib/kanban.php`.
- Output goes to the ROADMAP.md file in the active project's root, resolved via project config or current working directory (DESIGN.md §6: never hardcode project paths).
- Preserve markdown structure: keep `## Now / ## Next / ## Done` sections; update task checkboxes within each.

**Files**:
- Created: `lib/roadmap.php`, `lib/ai.php`
- Modified: `config/ai_prompts.json` (add roadmap_generation, release_milestones, project_timeline entries)
- Optional view integration: `view/roadmap.php` (trigger generation via action/button or API call to `api/roadmap.php`)

**Tasks**:
- [ ] Add prompt registry entries for roadmap tasks <!-- created_at: 2026-06-16T17:58:18+00:00 priority: high -->
- [ ] Implement RoadmapGenerator class — parse Kanban board, resolve prompts via AIPromptsRegistry, call ollamaPrompt(), persist markdown output <!-- created_at: 2026-06-16T17:58:18+00:00 priority: high -->
- [ ] Implement generateReleaseMilestones — group urgent/high tasks into logical milestone releases with titles and descriptions, using the existing prompt template in the registry <!-- created_at: 2026-06-16T17:58:18+00:00 priority: high -->
- [ ] Implement generateProjectTimelines — build timelines from task ordering, metadata, and priorities, using the existing prompt template in the registry <!-- created_at: 2026-06-16T17:58:18+00:00 priority: normal -->
