# Next

## Sprint Goal

Implement OpenCode settings layer — enable/disable toggle, server host/port/timeout, executable path, and project working directory fields — persisted to `settings.json` with validation on save so the application can locate and communicate with an external OpenCode server before any command execution is attempted.

## Status

Active

## Task Description

The project has deprecated its custom PHP AI workflows (roadmap generation, AI prompts) in favor of delegating planning to an external OpenCode server's `/plan` pipeline. Before commands can be executed, the application must first know how to reach the OpenCode server and whether it is enabled. This sprint builds that configuration backbone: new settings keys, a validation layer, and UI surface areas in the Settings view.

## Design

### Architectural Constraints
- **Settings Persistence Pattern (DESIGN.md §Configuration & Persistence)**: All application settings live in `settings.json`. Use `lib/settings.php` (`loadSettings()` / `saveSettings()`) for reading and writing — never write directly to JSON files.
- **Validation on Save**: Settings loaded from the UI must be validated before being persisted per `lib/settings.php`'s existing validation contract. New fields follow the same pattern.
- **Shared Library Pattern (DESIGN.md §4)**: Any helper code added to `lib/` must remain standalone — no view or API dependencies, no direct HTML output.
- **Project-as-Folder Pattern (DESIGN.md §6)**: Never hardcode project paths in settings resolution. Always resolve through the Router or config helpers.
- **Flat directory structure**: Do not create subdirectories in `lib/` or `view/`.

### Implementation Guidance
1. **Extend settings schema** — Add five new keys to `settings.json`:
   - `opencode_enabled` (bool, default: false)
   - `opencode_host` (string, default: "localhost")
   - `opencode_port` (int, default: 8080)
   - `opencode_timeout` (int, default: 60)
   - `opencode_executable_path` (string, default: "") — blank means use server mode; non-empty means fallback/exec locally via process execution
   - `opencode_project_dir` (string, default: "") — project root for the `/plan` working directory
2. **Add validation in `lib/settings.php`** — validate host format (hostname or IP), port range (1–65535), timeout > 0, and ensure that when `opencode_enabled` is true, at least `opencode_host` and `opencode_port` are non-empty.
3. **Update Settings UI (`view/settings.php`)** — Append an "OpenCode" section to the existing Ollama settings form with bootstrap-form inputs matching the five new keys. Reuse the same save POST pattern already established in this view.
4. **Preserve backward compatibility** — When `settings.json` already exists without these keys, defaults must be applied on load; no data migration is required.

### Relevant Design Decisions
- The OpenCode settings layer is a prerequisite for all subsequent OpenCode work items: server integration (`lib/opencode.php`, `api/opencode.php`), command execution UI (`view/agents.php`), and command templates management (`.opencode/commands/`). Implementing it first establishes the contract downstream components will read from.
- The roadmap already lists these exact settings keys under the "Implement OpenCode settings" task; follow that list precisely without adding extras.

### Compatibility Requirements
- Existing Ollama settings must remain fully functional in the Settings view — this sprint only adds, never removes.
- `view/settings.php` must not break when saving: it currently does a POST-handling save. Add the new keys to the same save handler.
- Any API endpoint that reads settings (e.g., dashboard server detection) will eventually consume these keys; they should be safely ignored by consumers until downstream code is updated.

## Files

### Expected to be created
- None

### Expected to be modified
- `lib/settings.php` — add validation and default values for the six new OpenCode settings
- `config/settings.json` — schema update (keys added on next save; no file rewrite needed if already saved)
- `view/settings.php` — Add an "OpenCode Settings" section to the existing form with inputs for enable toggle, host, port, timeout, executable path, and project directory. Wire to the same POST-save handler.
- `api/settings.php` — ensure POST payload handles the new fields (may be a no-op if `view/settings.php` writes directly)

## Tasks

- [ ] Audit current `lib/settings.php` settings structure and defaults <!-- created_at: 2026-06-17T08:05:00+00:00 priority: high -->
  - [ ] Review `loadSettings()` defaults for current keys (timezone, ollama_host, ollama_port, ollama_timeout, ollama_context_window, ollama_model) <!-- created_at: 2026-06-17T08:05:00+00:00 priority: high -->
  - [ ] Review `saveSettings()` validation logic for existing fields <!-- created_at: 2026-06-17T08:05:00+00:00 priority: high -->
- [ ] Add new settings keys and defaults to `settings.json` schema <!-- created_at: 2026-06-17T08:05:00+00:00 priority: high -->
  - [ ] Add `opencode_enabled` (bool, default false) as first new key <!-- created_at: 2026-06-17T08:05:00+00:00 priority: high -->
  - [ ] Add `opencode_host` (string, default "localhost") <!-- created_at: 2026-06-17T08:05:00+00:00 priority: high -->
  - [ ] Add `opencode_port` (int, default 11434) match Ollama convention <!-- created_at: 2026-06-17T08:05:00+00:00 priority: high -->
  - [ ] Add `opencode_timeout` (int, default 60) <!-- created_at: 2026-06-17T08:05:00+00:00 priority: normal -->
  - [ ] Add `opencode_executable_path` (string, default "") <!-- created_at: 2026-06-17T08:05:00+00:00 priority: normal -->
  - [ ] Add `opencode_project_dir` (string, default "") <!-- created_at: 2026-06-17T08:05:00+00:00 priority: high -->
- [ ] Implement validation for OpenCode settings in `lib/settings.php` <!-- created_at: 2026-06-17T08:05:00+00:00 priority: normal -->
  - [ ] Validate opencode_host is a valid hostname or IP format <!-- created_at: 2026-06-17T08:05:00+00:00 priority: normal -->
  - [ ] Validate opencode_port is within range (1–65535) <!-- created_at: 2026-06-17T08:05:00+00:00 priority: normal -->
  - [ ] Validate opencode_timeout > 0 if provided <!-- created_at: 2026-06-17T08:05:00+00:00 priority: normal -->
  - [ ] Ensure host + port are non-empty when opencode_enabled is true <!-- created_at: 2026-06-17T08:05:00+00:00 priority: normal -->
- [ ] Update Settings UI (`view/settings.php`) with OpenCode configuration section <!-- created_at: 2026-06-17T08:05:00+00:00 priority: high -->
  - [ ] Add form section header "OpenCode Server" below existing Ollama section <!-- created_at: 2026-06-17T08:05:00+00:00 priority: high -->
  - [ ] Add enable/disable toggle (checkbox or bootstrap-switch) for opencode_enabled <!-- created_at: 2026-06-17T08:05:00+00:00 priority: high -->
  - [ ] Add host input field (text, default localhost) <!-- created_at: 2026-06-17T08:05:00+00:00 priority: high -->
  - [ ] Add port input field (number, default 11434) <!-- created_at: 2026-06-17T08:05:00+00:00 priority: high -->
  - [ ] Add timeout input field (number) <!-- created_at: 2026-06-17T08:05:00+00:00 priority: normal -->
  - [ ] Add executable-path input field (text, optional) <!-- created_at: 2026-06-17T08:05:00+00:00 priority: normal -->
  - [ ] Add project-dir input field (text, optional) <!-- created_at: 2026-06-17T08:05:00+00:00 priority: high -->
  - [ ] Wire all new inputs to the existing POST-save handler in view/settings.php <!-- created_at: 2026-06-17T08:05:00+00:00 priority: normal -->
- [ ] Verify persistence and UI display round-trip <!-- created_at: 2026-06-17T08:05:00+00:00 priority: high -->
  - [ ] Confirm settings load with correct defaults when keys are absent from file <!-- created_at: 2026-06-17T08:05:00+00:00 priority: high -->
  - [ ] Confirm save writes new keys to settings.json without corrupting existing Ollama keys <!-- created_at: 2026-06-17T08:05:00+00:00 priority: high -->
  - [ ] Confirm validation errors display to user in the Settings UI when saving invalid data <!-- created_at: 2026-06-17T08:05:00+00:00 priority: normal -->
  - [ ] Confirm existing Ollama configuration is unaffected by the new changes (no regressions) <!-- created_at: 2026-06-17T08:05:00+00:00 priority: high -->
