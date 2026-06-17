# Next — Execution Queue

## Sprint Plan

**Root Goal:** Implement OpenCode settings configuration layer (Phase 1 of OpenCode Orchestration).

**Status:** Active — In Progress

---

## Task Description

Add the foundation for OpenCode server integration by implementing configurable settings, validation, and a reusable client wrapper. This enables dashboard-level detection of OpenCode availability alongside the existing Ollama health checks.

This is Phase 1 of the broader **OpenCode Orchestration** initiative (`In Progress`, priority: high). The next phase (Command Execution UI) depends on completing this work first.

## Design Notes

### Architectural Constraints (from DESIGN.md)

- **Front Controller Pattern**: All routing goes through `index.php`. This sprint does not add routes; it adds configuration and a library service.
- **Shared Library Pattern**: `lib/opencode.php` must be standalone — no Bootstrap, view, or API dependencies. No direct HTML output.
- **Settings layer** (from DESIGN.md): All OpenCode config keys are stored via the existing `loadSettings()` / `saveSettings()` in `lib/settings.php`. New settings must be validated before persistence. Never hardcode OpenCode config values.
- **Flat directory structure**: `lib/opencode.php` lives at the top level of `/lib/` — no subdirectories.

### Implementation Guidance

1. **Settings schema extension** (in `loadSettings()` defaults and `saveSettings()` validation):
   - Add five new keys to `settings.json`:
     - `opencode_enabled` (bool, default: `false`)
     - `opencode_host` (string, default: `"localhost"`)
     - `opencode_port` (int, default: `8080`)
     - `opencode_timeout` (int, default: `60`) — seconds
     - `opencode_executable` (string | null, default: `null`) — local path for manual fallback
   - Each field requires its own validation rule. Invalid values should not break the existing settings schema.

2. **Settings UI** (`view/settings.php`):
   - Append an "OpenCode" section to the existing Settings page forms (below the Ollama section).
   - Inputs must match the `saveSettings()` validation contract.
   - Follow existing Ollama settings input patterns in the same view for consistency.

3. **OpenCode client wrapper** (`lib/opencode.php`):
   - Implement an `OpenCodeClient` final class with static or instantiated methods that use cURL to talk to the configured server.
   - `isAvailable(string $host, int $port, int $timeout): bool` — health-check endpoint probe. Returns a boolean (or error info).
   - `sendCommand(string $host, int $port, int $timeout, string $command, array $args = []): array` — execute a command (`/plan`, `/next`, etc.) and return structured response `{status, body, error}`.
   - Must be usable from both dashboard (health probe) and future API endpoints without circular dependencies.

4. **Dashboard integration** (`view/dashboard.php` → `api/` or inline):
   - On the dashboard, alongside the existing Ollama availability card, add an OpenCode availability card.
   - Probe the configured OpenCode host/port via a lightweight AJAX call (or inline PHP health check on page render).
   - Display: "Available" / "Unavailable" status with configuration hint when unavailable.

5. **Existing patterns to follow**:
   - `lib/settings.php` — settings load/save/validation contract. Extend, do not replace.
   - `lib/ollama.php` — existing health-check pattern (`getOllamaConnection()`) to mirror design closely for OpenCode.
   - `view/dashboard.php` — server routing status section (Apache/Nginx/Builtin detection) is the target location for the new availability card.
   - `config/settings.json` — all config persists here.

### Compatibility Requirements

- Must preserve backward compatibility: existing settings keys and their behavior must not change.
- New settings keys are optional — defaults must allow graceful degradation when OpenCode is disabled or not yet configured.
- No new Composer dependencies; use built-in PHP cURL extension (already required for Ollama).
- PHP 8.1+ typing: typed signatures, union types where sensible.

### Existing Project Patterns (from DESIGN.md)

- **Shared Library Pattern**: Libraries in `/lib/` are standalone — no view/API context.
- **Settings Rule**: Never hardcode application configuration. Always validate before saving.
- **API contracts**: API files return JSON only, check HTTP method first. Any new opencode API endpoint follows this rule.

## Files

### Expected to be created:
| File | Purpose |
|------|---------|
| `lib/opencode.php` | OpenCodeClient class + health detection + command execution |
| `api/opencode.php` | AJAX endpoint for OpenCode availability probe |

### Expected to be modified:
| File | Purpose |
|------|---------|
| `lib/settings.php` | Add OpenCode keys to defaults, add validation rules |
| `view/settings.php` | Append OpenCode configuration section (inputs for host, port, timeout, executable, enabled toggle) |
| `config/settings.json` | New OpenCode config keys added on first save |
| `view/dashboard.php` | Add OpenCode availability status card alongside existing Ollama card |

## Tasks

- [ ] Extend loadSettings() defaults with 5 new OpenCode config keys <!-- created_at: 2026-06-17 priority: high -->
  - Keys: opencode_enabled (bool), opencode_host (string), opencode_port (int), opencode_timeout (int), opencode_executable (string|null)
- [ ] Extend saveSettings() validation for new OpenCode keys <!-- created_at: 2026-06-17 priority: high -->
  - port must be integer 1–65535; timeout must be positive integer; host must be valid hostname; enabled must be boolean
- [ ] Create lib/opencode.php with OpenCodeClient class <!-- created_at: 2026-06-17 priority: high -->
  - Health probe method (cURL GET to `/health` or similar)
  - Command execution method (POST with command name + args, structured response)
- [ ] Create api/opencode.php for availability probe endpoint <!-- created_at: 2026-06-17 priority: high -->
  - POST only; returns JSON `{available: true|false, message: string}`; uses OpenCodeClient::healthProbe()
- [ ] Append OpenCode section to view/settings.php <!-- created_at: 2026-06-17 priority: high -->
  - Enabled toggle (checkbox); host input; port input; timeout input; executable path input
  - Submit handler delegates to lib/settings.php saveSettings() with validation
- [ ] Add OpenCode availability card to view/dashboard.php <!-- created_at: 2026-06-17 priority: normal -->
  - Displays alongside Ollama card; shows Available/Unavailable status; provides manual command instructions when unavailable
