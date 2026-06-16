# Next

## Sprint Goal

Per-prompt model routing — allow each prompt template in the AI Prompts Registry to optionally specify which Ollama model should be used when invoking it, so different prompts can target different models (e.g., a lightweight model for priority triage vs. a reasoning-intensive model for task decomposition).

## Active Task List

### Prompt Per-Prompt Models — High Priority
**Files**: `lib/ai_prompts.php`, `lib/ai.php`, `lib/roadmap.php`, `view/ai_prompts.php` (editor)

**Design**:
- Extend `AIPromptsRegistry::loadDefaults()` to include an optional `"model"` key per prompt entry (null defaults when omitted).
- Update `getAllPromptsWithMeta()` to expose the model field alongside template/version/updated_at.
- Add a `"model"` column and editor cell in the prompt editor UI (`view/ai_prompts.php`), falling back to the global setting when no prompt-level model is set.
- In consumers (`lib/ai.php`, `lib/roadmap.php`), after calling `$registry->getAllPromptsWithMeta()`, pass the prompt's `"model"` (if non-null) into `ollamaPrompt()`'s `$options` array so it overrides the global default.

1. [x] Extend registry schema — add an optional `model` field alongside template, version, and updated_at
2. [x] Update consumers (`lib/ai.php`, `lib/roadmap.php`) to pass the prompt model to `ollamaPrompt()` via options
3. [x] Update prompt editor UI to show and edit the optional "model" field per-prompt
