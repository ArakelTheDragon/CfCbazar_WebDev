<?php
// /diy/organizer/index.php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

$reusablePath = __DIR__ . '/../../includes/reusable.php';
if (!is_file($reusablePath)) {
    http_response_code(500);
    exit('Error: /includes/reusable.php library missing.');
}
require_once $reusablePath;
require_once __DIR__ . '/../../includes/settings_global.php';

$url        = "/diy/organizer/index.php";
$parent_url = "/diy";
$title      = "DIY Organizer";
$template   = "Generic";
$meta_title = "DIY Organizer | CfCbazar";
$meta_desc  = "Issue-based task organizer with inline edit, drag-and-drop and editable summary.";

// Absolute URL for canonical / share links
$full_url   = build_full_url($url);

setReturnUrl($url);

// ------------------------
// System
// ------------------------
enforce_https();
checkSystemFlags();
if (bootstrap() !== 0) {
    exit(1);
}

trackVisit(
    slug:      $url,
    parentUrl: $parent_url,
    title:     $title,
    template:  $template,
    metaTitle: $meta_title,
    metaDesc:  $meta_desc
);render_header($title);
?>
<style>
.org-wrap { max-width: 720px; margin: 0 auto; }
.org-wrap > h1 { text-align: center; margin-bottom: 8px; }
.org-intro {
    text-align: center;
    color: var(--text-light);
    margin: 0 auto 24px;
    max-width: 520px;
    font-size: 0.95rem;
}
.org-add-issue {
    display: flex;
    gap: 10px;
    flex-wrap: wrap;
}
.org-add-issue input {
    flex: 1 1 200px;
    width: auto;
    margin: 0;
    font-size: 16px;
}
.org-add-issue .btn { width: auto; margin: 0; }
.issue-card { margin-bottom: 20px; }
.issue-head {
    display: flex;
    align-items: center;
    gap: 10px;
    margin-bottom: 12px;
}
.issue-title {
    flex: 1;
    font-size: 1.15rem;
    font-weight: 700;
    color: var(--primary-dark);
    cursor: text;
    padding: 4px 6px;
    border-radius: 6px;
    border: 1px solid transparent;
    min-width: 0;
    word-break: break-word;
}
.issue-title:hover { background: #f0f7f2; }
.issue-title.editing {
    background: #fff;
    border-color: var(--primary);
    outline: none;
}
.issue-progress {
    font-size: 0.78rem;
    font-weight: 600;
    color: var(--primary-dark);
    background: var(--success-bg);
    padding: 3px 9px;
    border-radius: 999px;
    white-space: nowrap;
}
.issue-del, .task-del {
    background: none;
    border: none;
    color: #9ca3af;
    cursor: pointer;
    font-size: 15px;
    padding: 4px 8px;
    border-radius: 4px;
    width: auto;
    margin: 0;
    box-shadow: none;
}
.issue-del:hover, .task-del:hover {
    color: var(--danger);
    background: var(--danger-bg);
    transform: none;
}
.task-list {
    list-style: none;
    padding: 0;
    margin: 0 0 12px;
    min-height: 8px;
}
.task-item {
    display: flex;
    align-items: flex-start;
    gap: 10px;
    padding: 11px 12px;
    background: #fff;
    border: 1px solid var(--border);
    border-radius: var(--radius-small);
    margin-bottom: 6px;
    cursor: grab;
    transition: border-color .15s, background .15s, box-shadow .15s, opacity .15s;
    user-select: none;
}
.task-item:active { cursor: grabbing; }
.task-item.dragging { opacity: 0.45; box-shadow: var(--shadow); }
.task-item.drag-over {
    border-color: var(--primary);
    background: #f0f9f3;
}
.task-item.done { background: #f8faf9; }
.task-item.done .task-text {
    text-decoration: line-through;
    color: var(--text-light);
}
.task-check {
    width: 18px;
    height: 18px;
    flex-shrink: 0;
    margin-top: 2px;
    cursor: pointer;
    accent-color: var(--primary);
}
.task-text {
    flex: 1;
    min-width: 0;
    word-break: break-word;
    line-height: 1.4;
    font-size: 0.93rem;
    padding: 1px 4px;
    border-radius: 4px;
    border: 1px solid transparent;
    cursor: text;
}
.task-text:hover { background: #f4f8f5; }
.task-text.editing {
    background: #fff;
    border-color: var(--primary);
    outline: none;
}
.add-task-row {
    display: flex;
    gap: 8px;
}
.add-task-row input {
    flex: 1;
    margin: 0;
    font-size: 15px;
    padding: 9px 12px;
}
.add-task-row .btn {
    width: auto;
    margin: 0;
    padding: 9px 14px;
    font-size: 0.9rem;
}
.org-empty {
    text-align: center;
    color: var(--text-light);
    padding: 32px 0;
    font-style: italic;
}
.org-summary-head {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 12px;
    margin-bottom: 12px;
    flex-wrap: wrap;
}
.org-summary-head h2 {
    margin: 0;
    font-size: 1.15rem;
}
.org-summary {
    width: 100%;
    padding: 12px;
    border: 1px solid var(--border);
    border-radius: var(--radius-small);
    font-family: ui-monospace, SFMono-Regular, Menlo, Consolas, monospace;
    font-size: 0.85rem;
    line-height: 1.5;
    background: #f8fafc;
    resize: vertical;
    margin: 0;
    min-height: 140px;
}
.org-summary:focus {
    border-color: var(--primary);
    background: #fff;
    box-shadow: 0 0 0 4px rgba(40, 167, 69, 0.15);
}
.org-copy-status {
    font-size: 0.85rem;
    color: var(--success);
    margin-top: 8px;
    min-height: 1.3em;
}
.org-actions {
    display: flex;
    gap: 10px;
    margin-top: 16px;
    flex-wrap: wrap;
}
.org-actions .btn { width: auto; margin: 0; }
.btn-secondary {
    background: #fff !important;
    color: var(--primary) !important;
    border: 1px solid var(--primary);
}
.btn-secondary:hover {
    background: var(--success-bg) !important;
    transform: translateY(-2px);
}
.btn-danger {
    background: linear-gradient(135deg, var(--danger), #b02a37) !important;
}
.btn-danger:hover {
    background: linear-gradient(135deg, #b02a37, #8b1e28) !important;
}
@media (max-width: 480px) {
    .org-add-issue, .add-task-row { flex-direction: column; }
    .org-add-issue .btn, .add-task-row .btn,
    .org-actions .btn, .org-summary-head .btn { width: 100%; }
    .org-summary-head { flex-direction: column; align-items: stretch; }
}
</style>

<main class="content">
<div class="container org-wrap">
    <h1>🗂️ DIY Organizer</h1>
    <p class="org-intro">
        Create an issue, then add tasks under it. Double-click to edit.
        Drag tasks to reorder. Summary is editable before copy.
    </p>

    <div class="card">
        <form id="add-issue-form" class="org-add-issue" autocomplete="off">
            <input type="text" id="issue-input" placeholder="New issue title…" maxlength="120" aria-label="New issue">
            <button type="submit" class="btn">Add issue</button>
        </form>
    </div>

    <div id="issues-root"></div>
    <div id="org-empty" class="org-empty" hidden>No issues yet. Create one above.</div>

    <div class="card">
        <div class="org-summary-head">
            <h2>Summary</h2>
            <button type="button" id="org-copy" class="btn">📋 Copy summary</button>
        </div>
        <textarea id="org-summary" class="org-summary" rows="8" aria-label="Summary"></textarea>
        <div id="org-copy-status" class="org-copy-status" role="status"></div>
        <div class="org-actions">
            <button type="button" id="clear-done" class="btn btn-secondary">Clear completed tasks</button>
            <button type="button" id="clear-all" class="btn btn-danger">Delete everything</button>
        </div>
    </div>
</div>
</main>

<script>
(function () {
    'use strict';
    const STORAGE_KEY = 'cfc_organizer_issues_v2';

    const root         = document.getElementById('issues-root');
    const emptyEl      = document.getElementById('org-empty');
    const addIssueForm = document.getElementById('add-issue-form');
    const issueInput   = document.getElementById('issue-input');
    const summaryEl    = document.getElementById('org-summary');
    const copyBtn      = document.getElementById('org-copy');
    const copyStat     = document.getElementById('org-copy-status');

    let issues = load();
    let summaryTouched = false; // prevents overwrite while user is editing

    function uid() {
        return (crypto.randomUUID ? crypto.randomUUID() : 'id_' + Date.now() + '_' + Math.random().toString(36).slice(2, 9));
    }

    function load() {
        try {
            const raw = localStorage.getItem(STORAGE_KEY);
            if (!raw) return [];
            const data = JSON.parse(raw);
            return Array.isArray(data) ? data : [];
        } catch { return []; }
    }

    function save() {
        try { localStorage.setItem(STORAGE_KEY, JSON.stringify(issues)); } catch {}
    }

    function buildSummary() {
        if (!issues.length) return '';
        const lines = [];
        issues.forEach(issue => {
            lines.push('## ' + issue.title);
            if (!issue.tasks.length) {
                lines.push('  (no tasks)');
            } else {
                issue.tasks.forEach(t => {
                    lines.push('  ' + (t.done ? '[x] ' : '[ ] ') + t.text);
                });
            }
            lines.push('');
        });
        return lines.join('\n').trim();
    }

    function updateSummary(force = false) {
        if (summaryTouched && !force) return;
        summaryEl.value = buildSummary();
    }

    function render() {
        root.innerHTML = '';
        emptyEl.hidden = issues.length > 0;

        issues.forEach(issue => {
            const card = document.createElement('div');
            card.className = 'card issue-card';
            card.dataset.issueId = issue.id;

            const doneCount = issue.tasks.filter(t => t.done).length;
            const total = issue.tasks.length;

            const head = document.createElement('div');
            head.className = 'issue-head';

            const title = document.createElement('div');
            title.className = 'issue-title';
            title.textContent = issue.title;
            title.title = 'Double-click to edit';
            title.addEventListener('dblclick', () => startEdit(title, issue, 'title'));

            const progress = document.createElement('span');
            progress.className = 'issue-progress';
            progress.textContent = total ? `${doneCount}/${total}` : '0';

            const delIssue = document.createElement('button');
            delIssue.className = 'issue-del';
            delIssue.textContent = '✕';
            delIssue.title = 'Delete issue';
            delIssue.addEventListener('click', () => {
                if (!confirm('Delete this issue and all its tasks?')) return;
                issues = issues.filter(i => i.id !== issue.id);
                save(); render();
            });

            head.append(title, progress, delIssue);
            card.appendChild(head);

            const ul = document.createElement('ul');
            ul.className = 'task-list';
            ul.dataset.issueId = issue.id;

            issue.tasks.forEach(task => {
                const li = document.createElement('li');
                li.className = 'task-item' + (task.done ? ' done' : '');
                li.draggable = true;
                li.dataset.taskId = task.id;
                li.dataset.issueId = issue.id;

                const cb = document.createElement('input');
                cb.type = 'checkbox';
                cb.className = 'task-check';
                cb.checked = !!task.done;
                cb.addEventListener('change', () => {
                    task.done = cb.checked;
                    save(); render();
                });

                const text = document.createElement('div');
                text.className = 'task-text';
                text.textContent = task.text;
                text.title = 'Double-click to edit';
                text.addEventListener('dblclick', () => startEdit(text, task, 'text'));

                const del = document.createElement('button');
                del.className = 'task-del';
                del.textContent = '✕';
                del.addEventListener('click', () => {
                    issue.tasks = issue.tasks.filter(t => t.id !== task.id);
                    save(); render();
                });

                li.append(cb, text, del);
                ul.appendChild(li);

                li.addEventListener('dragstart', onDragStart);
                li.addEventListener('dragend', onDragEnd);
                li.addEventListener('dragover', onDragOver);
                li.addEventListener('dragleave', onDragLeave);
                li.addEventListener('drop', onDrop);
            });

            card.appendChild(ul);

            const addRow = document.createElement('form');
            addRow.className = 'add-task-row';
            addRow.autocomplete = 'off';
            const taskInput = document.createElement('input');
            taskInput.type = 'text';
            taskInput.placeholder = 'Add task to this issue…';
            taskInput.maxLength = 200;
            const addBtn = document.createElement('button');
            addBtn.type = 'submit';
            addBtn.className = 'btn';
            addBtn.textContent = 'Add';
            addRow.append(taskInput, addBtn);

            addRow.addEventListener('submit', e => {
                e.preventDefault();
                const val = taskInput.value.trim();
                if (!val) return;
                issue.tasks.push({ id: uid(), text: val, done: false, created: Date.now() });
                taskInput.value = '';
                save(); render();
            });

            card.appendChild(addRow);
            root.appendChild(card);
        });

        updateSummary();
    }

    function startEdit(el, obj, key) {
        if (el.classList.contains('editing')) return;
        el.classList.add('editing');
        el.contentEditable = 'true';
        el.focus();

        const range = document.createRange();
        range.selectNodeContents(el);
        const sel = window.getSelection();
        sel.removeAllRanges();
        sel.addRange(range);

        const finish = () => {
            el.classList.remove('editing');
            el.contentEditable = 'false';
            const newVal = el.textContent.trim();
            if (newVal && newVal !== obj[key]) {
                obj[key] = newVal;
                save();
            } else {
                el.textContent = obj[key];
            }
            render();
        };

        el.addEventListener('blur', finish, { once: true });
        el.addEventListener('keydown', e => {
            if (e.key === 'Enter') { e.preventDefault(); el.blur(); }
            if (e.key === 'Escape') {
                el.textContent = obj[key];
                el.blur();
            }
        });
    }

    let dragTaskId = null;
    let dragIssueId = null;

    function onDragStart(e) {
        dragTaskId = e.currentTarget.dataset.taskId;
        dragIssueId = e.currentTarget.dataset.issueId;
        e.currentTarget.classList.add('dragging');
        e.dataTransfer.effectAllowed = 'move';
        e.dataTransfer.setData('text/plain', dragTaskId);
    }

    function onDragEnd(e) {
        e.currentTarget.classList.remove('dragging');
        document.querySelectorAll('.drag-over').forEach(el => el.classList.remove('drag-over'));
        dragTaskId = null;
        dragIssueId = null;
    }

    function onDragOver(e) {
        e.preventDefault();
        e.dataTransfer.dropEffect = 'move';
        const li = e.currentTarget;
        if (li.dataset.taskId !== dragTaskId) {
            li.classList.add('drag-over');
        }
    }

    function onDragLeave(e) {
        e.currentTarget.classList.remove('drag-over');
    }

    function onDrop(e) {
        e.preventDefault();
        const targetLi = e.currentTarget;
        targetLi.classList.remove('drag-over');

        const targetTaskId = targetLi.dataset.taskId;
        const targetIssueId = targetLi.dataset.issueId;

        if (!dragTaskId || dragTaskId === targetTaskId) return;

        const srcIssue = issues.find(i => i.id === dragIssueId);
        const dstIssue = issues.find(i => i.id === targetIssueId);
        if (!srcIssue || !dstIssue) return;

        const taskIdx = srcIssue.tasks.findIndex(t => t.id === dragTaskId);
        if (taskIdx === -1) return;
        const [task] = srcIssue.tasks.splice(taskIdx, 1);

        const targetIdx = dstIssue.tasks.findIndex(t => t.id === targetTaskId);
        dstIssue.tasks.splice(targetIdx, 0, task);

        save();
        render();
    }

    // Track manual edits to summary
    summaryEl.addEventListener('input', () => {
        summaryTouched = true;
    });

    addIssueForm.addEventListener('submit', e => {
        e.preventDefault();
        const title = issueInput.value.trim();
        if (!title) return;
        issues.unshift({
            id: uid(),
            title,
            created: Date.now(),
            tasks: []
        });
        issueInput.value = '';
        summaryTouched = false; // allow refresh after structural change
        save();
        render();
    });

    copyBtn.addEventListener('click', async () => {
        const text = summaryEl.value;
        if (!text) {
            copyStat.textContent = 'Nothing to copy yet.';
            setTimeout(() => copyStat.textContent = '', 2000);
            return;
        }
        let ok = false;
        try {
            if (navigator.clipboard?.writeText) {
                await navigator.clipboard.writeText(text);
                ok = true;
            } else {
                summaryEl.select();
                ok = document.execCommand('copy');
                summaryEl.setSelectionRange(0, 0);
                summaryEl.blur();
            }
        } catch { ok = false; }
        copyStat.textContent = ok ? '✓ Copied to clipboard.' : 'Copy failed — select and press Ctrl+C.';
        setTimeout(() => copyStat.textContent = '', 2500);
    });

    document.getElementById('clear-done').addEventListener('click', () => {
        let changed = false;
        issues.forEach(i => {
            const before = i.tasks.length;
            i.tasks = i.tasks.filter(t => !t.done);
            if (i.tasks.length !== before) changed = true;
        });
        if (changed) {
            summaryTouched = false;
            save();
            render();
        }
    });

    document.getElementById('clear-all').addEventListener('click', () => {
        if (!issues.length) return;
        if (!confirm('Delete all issues and tasks?')) return;
        issues = [];
        summaryTouched = false;
        save();
        render();
    });

    render();
    issueInput.focus();
})();
</script>

<?php
cfc_footer(
    "https://github.com/ArakelTheDragon/CfCbazar_WebDev/tree/main/diy/organizer",
    "DIY Organizer Source Code"
);
include_footer();
close_database();
?>
