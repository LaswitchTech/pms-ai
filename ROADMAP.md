# Roadmap

## Now

### AI Services (high) — In Progress

Generate task decomposition, subtasks, and priorities via Ollama.

- [x] Task Review Service (`lib/ai.php`, `lib/ollama.php`)
  - [x] Generate task decomposition suggestions
  - [x] Generate subtasks
  - [x] Suggest priorities
- [x] AI Prompts Registry (`lib/ai_prompts.php`, `config/ai_prompts.json`)
  - [x] Design prompt registry structure — define how prompts are stored as named, versioned entries keyed by purpose
  - [x] Create `lib/ai_prompts.php` with prompt registry class and helper functions to load/store prompts from a config source (JSON)
  - [x] Extract all hardcoded prompts from `lib/ai.php` into the registry so each prompt key is configurable at runtime
  - [x] Extract all hardcoded prompts from `lib/roadmap.php` into the registry using the same mechanism
- [ ] Prompt Editor UI (`view/settings.php`, `view/ai_prompts.php`)
  - [ ] Add an AI Prompts section to the Settings page or standalone view with a table of registered prompts
  - [ ] Build a prompt editor form that loads, edits, and saves individual prompt entries back via `lib/ai_prompts.php`
- [ ] Prompt Fallback Defaults (`lib/ai_prompts.php`)
  - [ ] Ship a set of default prompt templates in the codebase as fallback values for any registry entries that are missing or corrupted
  - [ ] Ensure `lib/ai.php` and `lib/roadmap.php` gracefully fall back to defaults when a prompt key does not exist in the registry
- [ ] Roadmap Generation Service (`lib/roadmap.php`, `lib/ai.php`)
  - [ ] Generate ROADMAP.md from Kanban
  - [ ] Generate release milestones
  - [ ] Generate timelines

### Application Architecture Refactor (urgent)

Critical refactor to clean up index.php, introduce Renderer class, and consolidate helpers.

- [ ] Create lib/renderer.php (`lib/renderer.php`)
  - [ ] Renderer class handles page rendering
  - [ ] Layout inclusion via Renderer, not index.php
  - [ ] View file existence checks in Renderer
- [ ] Extract route mapping into Router or separate config
  - [ ] Remove $routes array from index.php
  - [ ] Delegate route registration to Router class or route_config.php
  - [ ] index.php becomes thin bootstrap (includes, bootstraps router, dispatches)
- [ ] Helper consolidation in /lib
  - [ ] Move escape/e() helper to lib/helpers.php or view_helpers.php
  - [ ] Move ensureDirectory() into lib/file_helpers.php
  - [ ] Move slugifyProject() into lib/helpers.php
  - [ ] Move readProjectsConfig()/writeProjectsConfig() into lib/config_helpers.php
  - [ ] Move discoverProjects()/syncProjectsConfig()/createProject() into lib/project_helpers.php
  - [ ] Move git helpers (detectGitRepository, sanitizeGitRepositoryForDisplay) into lib/git_helpers.php or lib/project_helpers.php
  - [ ] Move rewrite/config helpers (ensureRewriteConfiguration, readFirstMarkdownHeading) into appropriate lib files
- [ ] Update all views to use shared helpers
  - [ ] Remove duplicate function_exists blocks from views
  - [ ] Confirm bootstrap.php or index.php includes all shared helpers before layout rendering

### Controller Layer (high)

Extract business logic from views and index.php into a controller layer.

- [ ] Create controller/directory structure
  - [ ] Establish controller naming conventions and responsibilities
  - [ ] Create shared library for cross-controller utilities in /lib
- [ ] Create controller/dashboard.php (`controller/dashboard.php`, `view/dashboard.php`)
  - [ ] Extract dashboard data preparation into controller
  - [ ] Remove dashboard logic from index.php
  - [ ] View becomes purely presentational
- [ ] Create controller/kanban.php (`controller/kanban.php`, `view/kanban.php`)
  - [ ] Extract kanban board loading/parsing logic into controller
  - [ ] Controller handles POST actions and delegates to lib/kanban.php
  - [ ] View becomes presentational-only layer
- [ ] Create controller/roadmap.php (`controller/roadmap.php`, `view/roadmap.php`)
  - [ ] Extract roadmap file path resolution into controller
  - [ ] Move markdown rendering to MarkdownRenderer via lib
  - [ ] View becomes presentational-only layer
- [ ] Create controller/design.php (`controller/design.php`, `view/design.php`)
  - [ ] Extract design file path resolution into controller
  - [ ] Reuse shared MarkdownRenderer for HTML rendering
  - [ ] View becomes presentational-only layer
- [ ] Create controller/next.php (`controller/next.php`, `view/next.php`)
  - [ ] Extract next file path resolution into controller
  - [ ] Reuse shared MarkdownRenderer for HTML rendering
  - [ ] View becomes presentational-only layer
- [ ] Create controller/agents.php (`controller/agents.php`, `view/agents.php`)
  - [ ] Extract agents file path resolution, opencode config handling into controller
  - [ ] View becomes presentational-only layer
- [ ] Create controller/projects.php (`controller/projects.php`, `view/projects.php`)
  - [ ] Extract project CRUD, git detection, config sync into controller
  - [ ] Build view as presentational layer
  - [ ] Remove duplicate project logic from index.php

### Markdown Rendering (high)

Replace inline markdown parsing with Parsedown via lib/markdown_renderer.php.

- [ ] Configure Composer autoload for erusev/parsedown
  - [ ] Run composer install / composer dump-autoload to verify parsedown is loaded
  - [ ] Confirm vendor/erusev/parsedown exists and autoload works
- [ ] Create lib/markdown_renderer.php (`lib/markdown_renderer.php`)
  - [ ] MarkdownRenderer class wraps Parsedown with caching layer
  - [ ] Support tables, code blocks, links, headers, lists correctly for GitHub-flavored markdown
  - [ ] Support task checklists (checkbox lists) rendering
  - [ ] Add inline HTML sanitizer for safety
- [ ] Migrate all pages to Parsedown (`controller/roadmap.php`, `lib/markdown_renderer.php`)
  - [ ] Migrate roadmap page — replace custom parser with MarkdownRenderer
  - [ ] Migrate design page — replace custom parser with MarkdownRenderer
  - [ ] Migrate next page — replace custom parser with MarkdownRenderer
  - [ ] Migrate agents page — replace custom parser with MarkdownRenderer

### Dynamic Roadmap Generation (high)

Generate ROADMAP.md dynamically from KANBAN.md.

- [ ] ROADMAP generated from KANBAN
- [ ] Generate release milestones
- [ ] Generate timeline
- [ ] Add roadmap page

### Project Repository Management (high)

- [ ] Project Details Page (`view/projects.php`, `projects/show.php`, `lib/git.php`)
  - [ ] Project metadata
  - [ ] Repository metadata
  - [ ] Branch display
- [ ] Git Configuration
  - [ ] Configure repository URL
  - [ ] Configure branch
  - [ ] Configure token storage
- [ ] Repository Status
  - [ ] Detect git repository
  - [ ] Current branch
  - [ ] Dirty working tree
  - [ ] Ahead / behind status

### Workflow Automation (high)

- [ ] NEXT.md Integration (`view/kanban.php`, `lib/next.php`)
  - [ ] Add task action button
  - [ ] Restrict button to In Progress column
  - [ ] Preserve metadata
  - [ ] Preserve subtasks
- [ ] Task Planning Workflow (`view/kanban.php`, `assets/js/kanban.js`)
  - [ ] Add Review button
  - [ ] Display AI suggestions modal
  - [ ] Allow user acceptance
  - [ ] Create subtasks automatically

### Improve Kanban UX (high)

- [ ] Replace alert() calls (`view/kanban.php`, `assets/js/kanban.js`)
  - [ ] Create reusable Bootstrap modal
  - [ ] Confirmation dialogs
  - [ ] Error dialogs
- [ ] Task Tags (Files: `lib/kanban.php`, `view/kanban.php`, `api/kanban.php`)
  - [ ] Extend parser
  - [ ] Add tag editor
  - [ ] Add tag rendering
  - [ ] Add tag filtering
- [ ] Read-only Mode (Files: `lib/settings.php`, `view/kanban.php`, `api/kanban.php`)
  - [ ] Add toggle
  - [ ] Disable mutations
  - [ ] Disable drag/drop
- [ ] Archive Improvements (`view/kanban.php`)
  - [ ] Search archive
  - [ ] Filter archive by tag
- [ ] Column Controls Review
  - [ ] Review existing controls
  - [ ] Standardize dropdown actions
  - [ ] Add missing bulk actions

## Next

### Reorganize Agents Workspace (normal)

- [ ] Create Agents dashboard
- [ ] Move Design page under Agents
- [ ] Move Next page under Agents
- [ ] Update navigation
- [ ] Update routes

### OpenCode Management (low)

- [ ] Define Global Defaults
- [ ] Project Overrides
- [ ] Settings Merge Engine
- [ ] Generate .opencode/opencode.json

## Done

- [x] Task Review Service (`lib/ai.php`, `lib/ollama.php`)
  - [x] Generate task decomposition suggestions
  - [x] Generate subtasks
  - [x] Suggest priorities
