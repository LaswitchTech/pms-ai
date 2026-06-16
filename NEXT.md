# Next

## Sprint Goal

Implement Roadmap Generation Service — generate ROADMAP.md from KANBAN.md content using AI prompts.

## Active Task List

### Roadmap Generation Service — High Priority
**Files**: `lib/roadmap.php`, `lib/ai.php`

**Design**:
- [x] Generate ROADMAP.md from the current Kanban board state (columns, tasks, priorities) by composing a prompt via `AIPromptsRegistry`.
- [x] Generate release milestones from task groupings and priorities.
- [x] Generate project timelines based on task ordering and metadata.
