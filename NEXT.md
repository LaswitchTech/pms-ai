# Next

## Sprint Goal

Review and deprecate custom PHP AI workflows in preparation for OpenCode `/plan` delegation.

## Status

Completed

## Task Description

The project is consolidating AI orchestration through OpenCode, replacing the legacy custom PHP-based AI flows (`lib/roadmap.php`, `lib/ai.php`, and the associated prompt infrastructure) with command delegations to an external OpenCode server's `/plan` pipeline. This sprint focuses on auditing the existing code, defining deprecation boundaries, making a strategic decision on removal vs preservation vs adaptation, and then implementing the chosen approach.

Deprecation decisions will directly affect what prompt entries from `AIPromptsRegistry` survive vs are pruned, which configuration entries in `config/ai_prompts.json` remain, and whether assets (`assets/prompts/`) persist or move to an archive path.

## Design

### Architectural Constraints
- **Shared Library Pattern (DESIGN.md §4)**: Any surviving helper code in `lib/` must be standalone — no view or API dependencies, no direct HTML output.
- **Project-as-Folder Pattern (DESIGN.md §6)**: Never hardcode project paths. Always resolve through the Router or config helpers.
- **No session-based state**: All deprecation artifacts must use JSON config files and filesystem state rather than sessions or cookies.
- **Flat directory structure**: Keep `lib/` flat — do not create subdirectories (e.g., no `lib/deprecated/`).

### Implementation Guidance
1. **Audit** — Map every public function in `lib/roadmap.php`, `lib/ai.php`, and any consumers of these files across `api/` and `view/` directories. Document call sites, entry points, and data flow.
2. **Decide** — For each file, choose one of three strategies:
   - **Remove**: Dead code with no consumers in the current codebase.
   - **Deprecate**: Code still called from views/APIs but planned for removal after `/plan` integration is validated. Add `@deprecated` docblock annotations and trigger a PHP `trigger_error()` at runtime when invoked.
   - **Adapt**: Logic needed by OpenCode pipeline (e.g., Kanban board parsing). Identify what to preserve and ensure it lives in the correct, dependency-free library per Shared Library Pattern.
3. **Implement** — Apply the decision per file:
   - Mark deprecated code with annotations and runtime warnings.
   - Remove files/directories that are entirely dead code.
   - Adapt surviving helpers into `lib/` with proper namespace-like PHP docblocks clarifying ownership.
4. **Validate** — Confirm OpenCode server integration (`lib/opencode.php`) can fully replace the deprecated flow before committing to removal. Add a runtime toggle (e.g., `settings.json` key) so deprecation can be rolled back safely.

### Relevant Design Decisions
- The `/plan` command pipeline is the intended source of truth for ROADMAP.md and NEXT.md generation (KANBAN.md: "Ensure `/plan` updates ROADMAP.md and NEXT.md" inside Preserve generated markdown viewer workflow subtask). PHP-driven roadmap synthesis will become obsolete once OpenCode server integration is validated.
- `AIPromptsRegistry` (`lib/ai_prompts.php`) and its fallback system were built specifically for Ollama-based prompt rendering. The same registry should be pruned of roadmap-specific keys if they are no longer needed by the OpenCode path.

### Compatibility Requirements
- Any file that currently calls `ollamaPrompt()`, parses `config/ai_prompts.json`, or loads from `/assets/prompts/` must continue to operate (even if deprecated) until `/plan` integration is confirmed working for all affected workflows.
- Existing prompt-editor UI (`view/settings.php`) must not break — it reads/writes `AIPromptsRegistry` entries regardless of which are active or deprecated.

## Files

### Expected to be created
- None (review and deprecation work should not require new files)

### Expected to be modified
- `lib/roadmap.php` — mark functions as @deprecated or remove dead code
- `lib/ai.php` — mark functions as @deprecated or remove dead code
- `config/ai_prompts.json` — remove/deprecate roadmap-related prompt keys (roadmap_generation, release_milestones, project_timeline) after confirming `/plan` replacement
- `view/settings.php` — update any direct references to removed/deprecated AI config entries
- `api/opencode.php` — add fallback notes or deprecation warnings where appropriate
- `.gitignore` — ensure `/assets/prompts/` is not tracked if it is being deprecated

**Potentially removed** (if confirmed dead):
- `lib/roadmap.php`
- `lib/ai.php`
- `config/ai_prompts.json` (entirely, if no prompts remain)
- Entire `/assets/prompts/` directory contents

## Tasks

- [x] Audit existing call sites across the codebase for `lib/roadmap.php`, `lib/ai.php`, and prompt registry consumers <!-- created_at: 2026-06-16T18:30:00+00:00 priority: high -->
- [x] Map every public function in `lib/roadmap.php` and `lib/ai.php` with its call graph across `api/`, `view/`, and `index.php` <!-- created_at: 2026-06-16T18:30:00+00:00 priority: high -->
- [x] Document the deprecation decision (remove/deprecate/adapt) for each identified file and function <!-- created_at: 2026-06-16T18:30:00+00:00 priority: high -->
- [x] Apply @deprecated annotations and runtime triggers to legacy AI code, or remove dead-code files entirely <!-- created_at: 2026-06-16T18:30:00+00:00 priority: high -->
- [x] Add deprecation toggle to settings.json for safe rollback of the custom AI workflow shutdown <!-- created_at: 2026-06-16T18:30:00+00:00 priority: normal -->
