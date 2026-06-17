# Next — Execution Queue

## Sprint Plan

**Root Goal:** Complete OpenCode Orchestration phases 1–3 (Settings → Health Detection → Command Execution UI → Generator Workflow Preservation).

**Status:** Active — In Progress (Phase 1 done, Phase 2 start)

---

## Completed Work

### Phase 1: Settings + Server Connection ✅ COMPLETED

The foundation for OpenCode server integration is now in place:

| File | Action | What It Does |
|------|--------|-------------|
| `lib/settings.php` | Modified | Added 5 new keys (`opencode_enabled`, `opencode_host`, `opencode_port`, `opencode_timeout`, `opencode_executable`) to defaults + validation rules in `saveSettings()` |
| `lib/opencode.php` | **New** | `OpenCodeClient` final class with `isAvailable()`, `sendCommand()`, `healthProbe()` static methods using cURL |
| `api/opencode.php` | **New** | POST-only endpoint at `/api/opencode.php` returning `{available, message}` JSON |
| `view/settings.php` | Modified | "OpenCode Configuration" card in General tab with enabled toggle, host/port/timeout inputs, optional executable path |
| `view/dashboard.php` | Modified | OpenCode availability status card (static state from settings.json + AJAX probe every 500ms for live updates) |

All Phase 1 tasks verified complete:
- [x] Extend loadSettings() defaults with 5 new OpenCode config keys
- [x] Extend saveSettings() validation for new OpenCode keys
- [x] Create lib/opencode.php with OpenCodeClient class
- [x] Create api/opencode.php for availability probe endpoint
- [x] Append OpenCode section to view/settings.php
- [x] Add OpenCode availability card to view/dashboard.php
