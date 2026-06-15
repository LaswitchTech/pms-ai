# AI Prompts Registry Design

## Prompt Structure

Prompts will be stored as a named, versioned collection keyed by purpose. The JSON schema for `config/ai_prompts.json` will be:

```json
{
  "key": "string",
  "template": "string", 
  "version": "int",
  "updated_at": "iso"
}
```

Where:
- `key`: unique identifier for prompt (e.g. "task_subtasks", "task_priority", "roadmap_generation")
- `template`: the actual prompt string with placeholders if needed
- `version`: semantic version number to track changes  
- `updated_at`: ISO timestamp of when the entry was last modified

## Fallback Strategy

We'll implement a two-tier fallback mechanism:
1. **In-code defaults**: Every known prompt key will have a built-in default template in code which is always available as fallback
2. **File-based config**: The config file will specify custom templates for any prompts that need to be customized  

This ensures we never have null returns from the registry and provides maximum flexibility.

## Key Prompt Identifiers

Based on analysis of existing prompts, the following keys will be used:

### Task-related prompts:
- `task_decomposition` - prompts for decomposing tasks into subtasks
- `task_subtasks` - prompts for generating detailed subtasks  
- `task_priority` - prompts for suggesting task priorities

### Roadmap-related prompts:
- `roadmap_generation` - prompts for generating roadmap content from kanban
- `release_milestones` - prompts for extracting release milestones 
- `project_timeline` - prompts for generating project timeline

This registry allows runtime configuration of all AI prompts used by the application.