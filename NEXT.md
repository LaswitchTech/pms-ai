# Next — Execution Queue
## Sprint Plan
**Root Goal:** Add `/plan`, `/next`, `/debug`, `/review`, and `/document` command buttons across Agents, Kanban, Roadmap, Dashboard, and Next views, wire them to the OpenCode API with progress output, error display, and duplicate-execution guards.
**Status:** Active — Start Implementation (Phase 2)
---
## Design Notes
### Architectural Constraints
- **Front Controller Pattern** — All routes must go through `index.php`. No direct file access to bypass the router.
- **Flat namespace** — Keep files flat in `/view/`, `/lib/`, and `/api/`. Do not create subdirectories.
- **Shared Library Pattern** — Libraries must be standalone PHP with no view or API dependencies, no HTML output.
- **API Contract** — All new API endpoints respond to only the expected HTTP method, return JSON only, and check request method before processing (per `DESIGN.md` API Structure rules).
- **No session-based state** — Duplicate-execution guards must not use PHP sessions; rely on client-side state tracking in JavaScript.
- **View-Inclusion Pattern** — Views are plain PHP templates rendered via `include $file`. Domain logic belongs in `/lib/`, API logic in `/api/`.
### Relevant Existing Code
| File | Role | Notes |
|------|------|-------|
| `lib/opencode.php` | OpenCodeClient class (final) | Already has `isAvailable()`, `sendCommand()`, `healthProbe()`. `sendCommand()` returns JSON with output/status/errors/commitHash. |
| `api/opencode.php` | Availability probe endpoint | Currently POST-only, returns `{available, message}`. Must be extended to accept a `command` + optional `project_slug` payload and stream or poll for execution results. |
| `view/settings.php` | Settings page | Already contains OpenCode Configuration card. Source of host/port/timeout values. |
| `view/dashboard.php` | Dashboard | Already shows OpenCode availability status card with AJAX probe. Good pattern to replicate for command progress. |
| `view/agents.php` | Agents page (primary UI) | Should host the main command buttons bar. Has sidebar layout already suitable for result display. |
| `view/kanban.php` | Kanban board | Will need a small "Command" toolbar above the board. Task cards can also expose per-task `/debug` and `/review`. |
| `view/roadmap.php`, `view/next.php`, `view/design.php` | Markdown viewer pages | Each gets one `/plan` shortcut button. These pages read generated markdown files, so after a successful command the page should auto-refresh or show a reload prompt. |
### Implementation Guidance
1. **Command payload schema** (POST to `/api/opencode.php`):
   ```json
   {
     "command": "/plan",
     "project_slug": "kanban"
   }
   ```
   - `command` is required; must be one of the five documented commands.
   - `project_slug` is optional for global commands (`/plan`) but may be passed for context.
2. **Execution model options** (pick one during implementation):
   - **A. Fire-and-poll:** API kicks off a background process, returns a task ID; client polls `/api/opencode.php?id={task_id}` for progress/status.
   - **B. Long-polling SSE:** API starts an OpenTelemetry-style stream and pushes output frames to the client.
   - **C. Synchronous with extended timeout:** `sendCommand()` sets cURL timeout to 120s, streams back chunks in real-time.
3. **Duplicate-execution guard** — Client-side only: track `isExecuting` per project with a boolean flag on the command toolbar. Disable button(s) while true. No server-side lock is required for Phase 2; future phases may add one.
4. **Fallback behavior** — When OpenCode is unavailable (`available === false`), show manual instructions as documented in the existing code (e.g., "Run `/plan` manually on the project directory"). Do not create a new error path yet.
5. **Existing JavaScript patterns** — Reuse `submitKanbanAction()` style AJAX calls already present in `assets/js/kanban.js`. Place new OpenCode-specific JS in `assets/js/opencode.js`. Use event delegation for dynamic button clicks.
6. **Bootstrap modal confirmation** — For destructive commands (e.g., `/document` can overwrite), use a Bootstrap 5 confirm dialog before sending the API request. This mirrors the "Replace alert() calls" Kanban UX Polish task but with modals, not `alert()`.
7. **Result display model:**
   - Agents page: show results in the existing sidebar area or an empty-slot panel below the command buttons.
   - Other pages (Kanban, Roadmap, Next, Design, Dashboard): use a Bootstrap toast + expandable card showing progress/output/errors after command completion. Auto-refresh ROADMAP.md / NEXT.md views on success when relevant.
8. **Command templates** — For Phase 2, invoke OpenCode's built-in slash commands directly (no custom template files). Template management is Phase 4.
9. **Security** — Validate `command` against a whitelist before passing to OpenCode. Validate that `project_slug` (when provided) maps to an existing project in `config/projects.json`. Reject non-POST requests with 405.
---
## Files
### Expected New Files
| File | Purpose |
|------|---------|
| `assets/js/opencode.js` | OpenCode client-side logic: command buttons, progress display, polling/fetching results, duplicate-execution guard, fallback rendering |
| `lib/opencode_client.php` (rename/refactor from `lib/opencode.php` if needed for naming consistency) or keep as-is | Extend existing OpenCodeClient to support async execution with task IDs and progress tracking |
### Expected Modified Files
| File | Change |
|------|--------|
| `api/opencode.php` | Add command execution handler: validate payload, dispatch via `OpenCodeClient::sendCommand()`, poll/return result; add `/execute` endpoint |
| `view/agents.php` | Add command toolbar with `/plan`, `/next`, `/debug`, `/review`, `/document` buttons + "Run" button + argument input + results panel |
| `view/kanban.php` | Add compact Command toolbar above the board (e.g., `/plan` shortcut); show toast for non-Agents pages |
| `view/dashboard.php` | Add global command button row below OpenCode status card |
| `view/roadmap.php` | Add one-shot `/plan` button at page top; auto-refresh on success |
| `view/next.php` | Add one-shot `/next` button at page top; auto-refresh on success |
| `view/design.php` | Add one-shot `/review` button at page top (reuse generic command UI, default to `/review`) |
| `lib/opencode.php` | Extend `OpenCodeClient` with task ID management, optional async mode, and status polling |
---
## Tasks
- [x] Phase 2.1: Decide execution model (fire-and-poll vs SSE vs synchronous) and extend `api/opencode.php` to support it <!-- created_at: 2026-06-17T12:00:00 priority: high -->
- [x] Phase 2.2: Extend `OpenCodeClient` with task-id management and progress polling <!-- created_at: 2026-06-17T12:00:00 priority: high -->
- [x] Phase 2.3: Validate command payload (whitelist, project slug lookup) in API endpoint <!-- created_at: 2026-06-17T12:00:00 priority: high -->
- [ ] Phase 2.4: Create `assets/js/opencode.js` with core helpers: executeCommand, pollProgress, displayResult, duplicate guard <!-- created_at: 2026-06-17T12:00:00 priority: high -->
- [ ] Phase 2.5: Add command toolbar to `view/agents.php` — full buttons, argument input, results panel <!-- created_at: 2026-06-17T12:00:00 priority: high -->
- [ ] Phase 2.6: Wire command buttons on `view/kanban.php`, `view/dashboard.php`, `view/roadmap.php`, `view/next.php`, `view/design.php` with toast result display <!-- created_at: 2026-06-17T12:00:00 priority: high -->
- [ ] Phase 2.7: Auto-refresh roadmap/next views after successful `/plan` or `/next` execution, show manual instructions when OpenCode unavailable <!-- created_at: 2026-06-17T12:00:00 priority: normal -->
- [ ] Phase 2.8: Bootstrap confirm dialog for destructive commands (`/document`) <!-- created_at: 2026-06-17T12:00:00 priority: normal -->
- [ ] Phase 2.9: Integration verification — run end-to-end test of all five commands across Agents and one other view, confirm progress output + error handling work correctly <!-- created_at: 2026-06-17T12:00:00 priority: high -->
