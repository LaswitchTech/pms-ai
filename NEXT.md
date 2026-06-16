# Next

## Sprint Goal

Implement Roadmap Generation Service — generate ROADMAP.md from KANBAN.md content using AI prompts.

## Active Task List

### Roadmap Generation Service — High Priority
**Files**: `lib/roadmap.php`, `lib/ai.php`

**Design**:
- [ ] Generate ROADMAP.md from the current Kanban board state (columns, tasks, priorities) by composing a prompt via `AIPromptsRegistry`.
- [ ] Generate release milestones from task groupings and priorities.
- [ ] Generate project timelines based on task ordering and metadata.
