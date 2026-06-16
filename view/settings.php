<?php
require __DIR__ . '/../lib/settings.php';
require __DIR__ . '/../lib/ai_prompts.php';

// Load setting
$settings = loadSettings(__DIR__ . '/../config/settings.json');

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validate and save setting
    $newSettings = [
        'timezone' => $_POST['timezone'] ?? null,
        'ollama_host' => $_POST['ollama_host'] ?? 'localhost',
        'ollama_port' => (int) ($_POST['ollama_port'] ?? 11434),
        'ollama_timeout' => (int) ($_POST['ollama_timeout'] ?? 30),
        'ollama_context_window' => (int) ($_POST['ollama_context_window'] ?? 4096),
        'ollama_model' => $_POST['ollama_model'] ?? ''
    ];
    
    // Validate port
    if ($newSettings['ollama_port'] < 1 || $newSettings['ollama_port'] > 65535) {
        $error = "Port must be an integer between 1 and 65535";
    }
    
    // Validate timeout
    if ($newSettings['ollama_timeout'] < 5) {
        $error = "Timeout must be at least 5 seconds";
    }
    
    // Validate context window
    if ($newSettings['ollama_context_window'] < 256) {
        $error = "Context window must be at least 256";
    }
    
    // Save settings if no validation error
    if (!isset($error)) {
        if (saveSettings(__DIR__ . '/../config/settings.json', $newSettings)) {
            // Redirect back to settings page using a reliable method to avoid confusion with router
            $redirectUrl = '/settings';
            if (headers_sent()) {
                echo '<script type="text/javascript">window.location.href = "' . $redirectUrl . '";</script>';
                exit;
            } else {
                header('Location: ' . $redirectUrl);
                exit;
            }
        } else {
            $error = "Failed to save settings";
        }
    }
    
    // If there was an error, keep the submitted values in the form
    $settings = $newSettings;
}

// Get list of timezone
$timezones = DateTimeZone::listIdentifiers();

// Get all prompts with metadata for display
$allPrompts = getAIPromptsRegistry()->getAllPromptsWithMeta();
?>

<div class="container">
    <h1 class="h3 mb-4">Settings</h1>
    
    <?php if (isset($error)): ?>
        <div class="alert alert-danger"><?= e($error) ?></div>
    <?php endif; ?>
    
    <ul class="nav nav-tabs" id="settingsTabs" role="tablist">
        <li class="nav-item" role="presentation">
            <button class="nav-link active" id="general-tab" data-bs-toggle="tab" data-bs-target="#general" type="button" role="tab">General</button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link" id="prompts-tab" data-bs-toggle="tab" data-bs-target="#prompts" type="button" role="tab">AI Prompts</button>
        </li>
    </ul>

    <div class="tab-content mt-3" id="settingsTabsContent">
        <!-- General Settings Tab -->
        <div class="tab-pane fade show active" id="general" role="tabpanel">
            <form method="post" action="">
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0">Timezone</h5>
                    </div>
                    <div class="card-body">
                        <div class="mb-3">
                            <label for="timezone" class="form-label">Select Timezone</label>
                            <select class="form-select" id="timezone" name="timezone">
                                <option value="">Default (UTC)</option>
                                <?php foreach ($timezones as $tz): ?>
                                    <option value="<?= e($tz) ?>" <?= $settings['timezone'] === $tz ? 'selected' : '' ?>>
                                        <?= e($tz) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                </div>

                <div class="card mt-4">
                    <div class="card-header">
                        <h5 class="mb-0">Ollama Configuration</h5>
                    </div>
                    <div class="card-body">
                        <div class="mb-3">
                            <label for="ollama_host" class="form-label">Host</label>
                            <input type="text" class="form-control" id="ollama_host" name="ollama_host" value="<?= e($settings['ollama_host']) ?>">
                        </div>

                        <div class="mb-3">
                            <label for="ollama_port" class="form-label">Port</label>
                            <input type="number" class="form-control" id="ollama_port" name="ollama_port" value="<?= e($settings['ollama_port']) ?>" min="1" max="65535">
                        </div>

                        <div class="mb-3">
                            <label for="ollama_timeout" class="form-label">Timeout (seconds)</label>
                            <input type="number" class="form-control" id="ollama_timeout" name="ollama_timeout" value="<?= e($settings['ollama_timeout']) ?>" min="5">
                        </div>

                        <div class="mb-3">
                            <label for="ollama_context_window" class="form-label">Context Window</label>
                            <input type="number" class="form-control" id="ollama_context_window" name="ollama_context_window" value="<?= e($settings['ollama_context_window']) ?>" min="256">
                        </div>

                        <div class="mb-3">
                            <label for="ollama_model" class="form-label">Default Model</label>
                            <input type="text" class="form-control" id="ollama_model" name="ollama_model" value="<?= e($settings['ollama_model']) ?>">
                        </div>
                    </div>
                </div>

                <div class="mt-4">
                    <button type="submit" class="btn btn-primary">Save Settings</button>
                </div>
            </form>
        </div>

        <!-- AI Prompts Tab -->
        <div class="tab-pane fade" id="prompts" role="tabpanel">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">AI Prompt Templates</h5>
                </div>
                <div class="card-body">
                    <p class="text-muted">Manage AI prompt templates used by the system. These prompts can be customized to improve AI-generated results.</p>
                    
                    <div class="table-responsive">
                        <table class="table table-sm">
                            <thead>
                                <tr>
                                    <th>Key</th>
                                    <th>Template</th>
                                    <th>Model</th>
                                    <th>Version</th>
                                    <th>Updated At</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($allPrompts as $key => $prompt): ?>
                                <tr data-row-key="<?= e($key) ?>" data-template="<?= e($prompt['template']) ?>">
                                    <td><?= e($prompt['key']) ?></td>
                                    <td class="text-break" style="max-width: 300px;"><?= e(substr($prompt['template'], 0, 100)) . (strlen($prompt['template']) > 100 ? '...' : '') ?></td>
                                    <td><?= $prompt['model'] ? e($prompt['model']) : '—' ?></td>
                                    <td><?= $prompt['version'] > 0 ? e($prompt['version']) : '∅' ?></td>
                                    <td><?= $prompt['updated_at'] ? e(date('Y-m-d H:i', strtotime($prompt['updated_at']))) : '—' ?></td>
                                    <td>
                                        <button class="btn btn-sm btn-outline-primary" data-key="<?= e($key) ?>" data-template="<?= e($prompt['template']) ?>" onclick="editPrompt(this.dataset.key, this.dataset.template)">Edit</button>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Edit Prompt Modal -->
<div class="modal fade" id="editPromptModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Edit AI Prompt</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="editPromptForm">
                    <input type="hidden" id="promptKey" name="key">
                    <div class="mb-3">
                        <label for="promptKeyDisplay" class="form-label">Key</label>
                        <input type="text" class="form-control" id="promptKeyDisplay" readonly>
                    </div>
                    <div class="mb-3">
                        <label for="promptTemplate" class="form-label">Template</label>
                        <textarea class="form-control" id="promptTemplate" name="template" rows="8"></textarea>
                    </div>
                    <div class="mb-3">
                        <label for="promptModel" class="form-label">Model (optional)</label>
                        <input type="text" class="form-control" id="promptModel" name="model" placeholder="Leave empty to use default model">
                    </div>
                    <div class="mb-3">
                        <label for="promptVersion" class="form-label">Version</label>
                        <input type="text" class="form-control" id="promptVersion" name="version" readonly>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary" id="savePromptBtn">Save Changes</button>
            </div>
        </div>
    </div>
</div>

<script>
function editPrompt(key, template) {
    document.getElementById('promptKey').value = key;
    document.getElementById('promptKeyDisplay').value = key;
    document.getElementById('promptTemplate').value = template;
    
    // Find the current version and model for this key from any row in the table
    var row = document.querySelector('tr[data-row-key="' + key + '"]');
    var currentVersion = 1;
    var currentModel = '';
    if (row) {
        var verCell = row.querySelector('td:nth-child(4)');
        if (verCell && verCell.textContent !== '—' && verCell.textContent !== '∅') {
            currentVersion = parseInt(verCell.textContent, 10) || 1;
        }
        
        var modelCell = row.querySelector('td:nth-child(3)');
        if (modelCell && modelCell.textContent !== '—') {
            currentModel = modelCell.textContent.trim();
        }
    }

    // Keep a JS-level reference to the row so we can update it after saving,
    // even when the modal is hidden or the DOM is otherwise unstable.
    window._promptEditorRow = row ? row : null;

    document.getElementById('promptVersion').value = currentVersion;
    document.getElementById('promptModel').value = currentModel;
    
    const modal = new bootstrap.Modal(document.getElementById('editPromptModal'));
    modal.show();
}

document.getElementById('savePromptBtn').addEventListener('click', function() {
    var key = document.getElementById('promptKey').value;
    var template = document.getElementById('promptTemplate').value;
    var model = document.getElementById('promptModel').value;
    
    // Make AJAX call to save the prompt
    fetch('/api/ai_prompts.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json'
        },
        body: JSON.stringify({
            key: key,
            template: template,
            model: model
        })
    })
    .then(function(response) { return response.json(); })
    .then(function(data) {
        var alertClass = 'alert-success';
        var alertMsg;
        
        if (data.success) {
            // Close the modal
            var modalInstance = bootstrap.Modal.getInstance(document.getElementById('editPromptModal'));
            if (modalInstance) {
                modalInstance.hide();
            }
            
            // Update the table row in-place with the new data from the API response.
            // Re-query by key — window._promptEditorRow can become stale after modal closes.
            var rows = document.querySelectorAll('tr[data-row-key]');
            var targetRow = null;
            for (var i = 0; i < rows.length; i++) {
                if (rows[i].getAttribute('data-row-key') === key) {
                    targetRow = rows[i];
                    break;
                }
            }

            if (targetRow) {
                // Update the data-template attribute on the row and any buttons inside it,
                // so that the next time the modal opens, it reads the fresh template value.
                targetRow.setAttribute('data-template', data.template);
                var btns = targetRow.querySelectorAll('[data-key]');
                for (var b = 0; b < btns.length; b++) {
                    btns[b].setAttribute('data-template', data.template);
                }

                var cells = targetRow.querySelectorAll('td');
                // Column 2: template preview (first 100 chars + "..." if truncated).
                // Clone the cell to force DOM update even when textContent is identical.
                var newTD = document.createElement('td');
                newTD.className = cells[1].className;
                if (data.template.length > 100) {
                    newTD.textContent = data.template.substring(0, 100) + '...';
                } else {
                    newTD.textContent = data.template;
                }
                cells[1].parentNode.replaceChild(newTD, cells[1]);

                // Column 3: version (from API)
                cells[2].textContent = data.version !== undefined ? String(data.version) : '';

                // Column 4: updated_at (formatted timestamp from API)
                if (data.updated_at) {
                    var dt = new Date(data.updated_at);
                    var formattedDate = dt.getFullYear() + '-' +
                        String(dt.getMonth() + 1).padStart(2, '0') + '-' +
                        String(dt.getDate()).padStart(2, '0') + ' ' +
                        String(dt.getHours()).padStart(2, '0') + ':' +
                        String(dt.getMinutes()).padStart(2, '0');
                    cells[3].textContent = formattedDate;
                }
            } else {
                console.error('PromptEditor: Could not find table row for key="' + key + '"');
            }
            
            alertMsg = 'Prompt saved successfully!';
        } else {
            // Show error message
            var errDiv = document.createElement('div');
            errDiv.className = 'alert alert-danger alert-dismissible fade show mt-3';
            errDiv.innerHTML = 'Failed to save prompt: ' + (data.error || 'Unknown error') + ' <button type="button" class="btn-close" data-bs-dismiss="alert"></button>';
            document.querySelector('.tab-content').prepend(errDiv);
            return; // Don't show success alert on error
        }
        
        // Show success message
        var alertDiv = document.createElement('div');
        alertDiv.className = 'alert alert-' + alertClass + ' alert-dismissible fade show mt-3';
        alertDiv.innerHTML = (alertMsg || '') + ' <button type="button" class="btn-close" data-bs-dismiss="alert"></button>';
        document.querySelector('.tab-content').prepend(alertDiv);
    })
    .catch(function(error) {
        // Show error message
        var errDiv = document.createElement('div');
        errDiv.className = 'alert alert-danger alert-dismissible fade show mt-3';
        errDiv.innerHTML = 'Network error while saving prompt: ' + error.message + ' <button type="button" class="btn-close" data-bs-dismiss="alert"></button>';
        document.querySelector('.tab-content').prepend(errDiv);
    });
});
</script>