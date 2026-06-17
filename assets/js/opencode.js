/**
 * OpenCode command execution utilities
 */

/**
 * Execute an OpenCode command via AJAX
 *
 * @param {string} command - The command to execute (e.g., "/plan", "/next")
 * @param {string|null} projectSlug - Optional project slug
 * @param {Function} onProgress - Callback for progress updates
 * @returns {Promise<Object>} Command execution result
 */
async function executeCommand(command, projectSlug = null, onProgress = null) {
    const payload = {
        command: command
    };

    if (projectSlug) {
        payload.project_slug = projectSlug;
    }

    try {
        const response = await fetch('/api/opencode.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify(payload)
        });

        const result = await response.json();

        // If we got an error, throw it
        if (result.status === 'error') {
            throw new Error(result.message || result.errors || 'Command execution failed');
        }

        return result;
    } catch (error) {
        console.error('OpenCode command failed:', error);
        throw error;
    }
}

/**
 * Execute a command and display results
 *
 * @param {string} command - The command to execute
 * @param {string|null} projectSlug - Optional project slug
 * @param {HTMLElement} outputContainer - Where to display result
 */
async function executeAndDisplayCommand(command, projectSlug = null, outputContainer) {
    // Show loading state
    outputContainer.innerHTML = '<div class="alert alert-info">Executing command...</div>';

    const isRoadmapView = window.location.pathname.includes('/roadmap');
    const isNextView = window.location.pathname.includes('/next');

    try {
        const result = await executeCommand(command, projectSlug);

        // Format the output for display
        let content = '';

        if (result.errors) {
            content += `<div class="alert alert-danger">
                <strong>Errors:</strong><br>
                <pre>${escapeHtml(result.errors)}</pre>
            </div>`;
        }

        if (result.output) {
            content += `<div class="card">
                <div class="card-header">Output</div>
                <div class="card-body">
                    <pre>${escapeHtml(result.output)}</pre>
                </div>
            </div>`;
        }

        if (result.commitHash) {
            content += `<div class="mt-2 small text-muted">Commit: ${escapeHtml(result.commitHash)}</div>`;
        }

        outputContainer.innerHTML = content;

        // Auto-refresh roadmap/next views after successful plan/next command
        if ((isRoadmapView || isNextView) && (command === '/plan' || command === '/next')) {
            // For roadmap and next views, show success toast with refresh button
            const toastContainer = document.getElementById('opencode-toast-container');
            if (!toastContainer) {
                // Create toast container if it doesn't exist
                const container = document.createElement('div');
                container.id = 'opencode-toast-container';
                container.className = 'position-fixed top-0 start-50 translate-middle-x mt-3 p-3';
                container.style.zIndex = 1050;
                document.body.appendChild(container);
            }

            // Create a temporary toast for confirmation
            const successToast = document.createElement('div');
            successToast.className = 'alert alert-success alert-dismissible fade show shadow-sm';
            successToast.role = 'alert';
            successToast.innerHTML = `
                Command executed successfully.
                <button type="button" class="btn btn-outline-success btn-sm ms-2" onclick="window.location.reload()">Refresh View</button>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            `;

            // Insert this in the toast container
            const container = document.getElementById('opencode-toast-container');
            if (container) {
                container.appendChild(successToast);
                // Auto-remove after 10 seconds to give time for action
                setTimeout(() => {
                    if (successToast.parentNode) {
                        successToast.remove();
                    }
                }, 10000);
            }
        }

        return result;
    } catch (error) {
        outputContainer.innerHTML = `<div class="alert alert-danger">
            <strong>Command failed:</strong> ${escapeHtml(error.message)}
        </div>`;
        throw error;
    }
}

/**
 * Poll for progress updates during command execution
 *
 * @param {string} taskId - The task ID returned from startCommand
 * @param {Function} onProgress - Callback to handle progress updates
 * @param {number} interval - Polling interval in milliseconds (default: 1000)
 * @returns {Promise<Object>} Final command result
 */
async function pollProgress(taskId, onProgress, interval = 1000) {
    // In Phase 2 we don't implement async polling yet, but this sets up the interface
    // This is mainly here for future async implementation

    try {
        // For now, we just return immediately with a completed status as in synchronous mode
        // In future async support, this would actively poll the server
        const result =  {
            status: 'completed',
            output: null,
            errors: null,
            isComplete: true
        };

        if (onProgress) {
            onProgress(result);
        }

        return result;
    } catch (error) {
        console.error('Progress polling failed:', error);
        throw error;
    }
}

/**
 * Display command results in a structured way
 *
 * @param {Object} result - Command execution result from API
 * @param {HTMLElement} container - Where to display result
 */
function displayResult(result, container) {
    let content = '';

    if (result.errors) {
        content += `<div class="alert alert-danger">
            <strong>Errors:</strong><br>
            <pre>${escapeHtml(result.errors)}</pre>
        </div>`;
    }

    if (result.output) {
        content += `<div class="card">
            <div class="card-header">Output</div>
            <div class="card-body">
                <pre>${escapeHtml(result.output)}</pre>
            </div>
        </div>`;
    }

    if (result.commitHash) {
        content += `<div class="mt-2 small text-muted">Commit: ${escapeHtml(result.commitHash)}</div>`;
    }

    container.innerHTML = content;
}

/**
 * Simple HTML escaping function
 *
 * @param {string} unsafe - Unsafe string to escape
 * @returns {string} Escaped string
 */
function escapeHtml(unsafe) {
    if (typeof unsafe !== 'string') return '';
    return unsafe
        .replace(/&/g, "&amp;")
        .replace(/</g, "&lt;")
        .replace(/>/g, "&gt;")
        .replace(/"/g, "&quot;")
        .replace(/'/g, "&#039;");
}

// Duplicate execution guard - for tracking which commands are executing
const isExecuting = new Set();

/**
 * Check if a command is currently executing for a project
 *
 * @param {string} command - The command to check
 * @param {string|null} projectSlug - Optional project slug
 * @returns {boolean} True if command is currently executing
 */
function isCommandExecuting(command, projectSlug = null) {
    const key = projectSlug ? `${command}:${projectSlug}` : command;
    return isExecuting.has(key);
}

/**
 * Mark a command as executing for a project
 *
 * @param {string} command - The command to mark
 * @param {string|null} projectSlug - Optional project slug
 */
function markCommandAsExecuting(command, projectSlug = null) {
    const key = projectSlug ? `${command}:${projectSlug}` : command;
    isExecuting.add(key);
}

/**
 * Mark a command as no longer executing for a project
 *
 * @param {string} command - The command to unmark
 * @param {string|null} projectSlug - Optional project slug
 */
function markCommandAsNotExecuting(command, projectSlug = null) {
    const key = projectSlug ? `${command}:${projectSlug}` : command;
    isExecuting.delete(key);
}

// Export for use in other modules
if (typeof module !== 'undefined' && module.exports) {
    module.exports = {
        executeCommand,
        executeAndDisplayCommand,
        pollProgress,
        displayResult,
        escapeHtml,
        isCommandExecuting,
        markCommandAsExecuting,
        markCommandAsNotExecuting
    };
}