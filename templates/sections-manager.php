<?php defined( 'ABSPATH' ) || exit; ?>
<div class="wrap fps-sm-wrap">
    <h1 class="fps-sm-page-title">Sections</h1>

    <div class="fps-sm-layout">

        <!-- ── Left sidebar ────────────────────────────────────────────── -->
        <aside class="fps-sm-sidebar">
            <div class="fps-sm-sidebar-header">
                <button id="fps-new-section-btn" class="button button-primary fps-sm-new-btn">+ New Section</button>
            </div>

            <div id="fps-new-section-form" class="fps-sm-new-form" hidden>
                <label class="fps-sm-new-label">Section slug</label>
                <input id="fps-new-slug"  type="text" class="widefat" placeholder="e.g. my-hero" />
                <label class="fps-sm-new-label" style="margin-top:6px">Label</label>
                <input id="fps-new-label-input" type="text" class="widefat" placeholder="e.g. My Hero" />
                <p id="fps-new-section-error" class="fps-sm-new-error" hidden></p>
                <div class="fps-sm-new-actions">
                    <button id="fps-new-section-submit" class="button button-primary">Create</button>
                    <button id="fps-new-section-cancel" class="button">Cancel</button>
                </div>
            </div>

            <div id="fps-section-list" class="fps-sm-list">
                <p class="fps-sm-list-loading">Loading sections…</p>
            </div>
        </aside>

        <!-- ── Right editor panel ──────────────────────────────────────── -->
        <main class="fps-sm-editor-panel" id="fps-editor-panel">

            <div id="fps-empty-state" class="fps-sm-empty-state">
                <div class="fps-sm-empty-inner">
                    <span class="fps-sm-empty-icon">&#9632;</span>
                    <p>Select a section from the list to view or edit its files.</p>
                </div>
            </div>

            <div id="fps-editor-ui" class="fps-sm-editor-ui" hidden>

                <div class="fps-sm-editor-header">
                    <div class="fps-sm-editor-title-row">
                        <h2 id="fps-editor-section-name"></h2>
                        <span id="fps-editor-source-badge" class="fps-sm-badge"></span>
                        <span id="fps-editor-section-type" class="fps-sm-type-slug"></span>
                    </div>
                    <div class="fps-sm-editor-actions">
                        <span id="fps-save-notice-inline" class="fps-sm-save-notice" hidden></span>
                        <span id="fps-dirty-indicator" class="fps-sm-dirty" hidden>● Unsaved changes</span>
                        <button id="fps-save-btn" class="button button-primary" hidden>Save Files</button>
                        <span id="fps-readonly-notice" class="fps-sm-readonly-notice" hidden>Read-only</span>
                    </div>
                </div>

                <div class="fps-sm-usage-bar" id="fps-usage-bar" hidden>
                    <span class="fps-sm-usage-label">Used in:</span>
                    <span id="fps-usage-links"></span>
                </div>

                <div class="fps-sm-tabs" id="fps-file-tabs"></div>

                <div class="fps-sm-editor-area" id="fps-editor-area">
                    <textarea id="fps-cm-textarea" class="fps-sm-textarea"></textarea>
                </div>

            </div>
        </main>

    </div>
</div>

<!-- ── Styles ─────────────────────────────────────────────────────────── -->
<style>
/* Layout */
.fps-sm-wrap { max-width: 100%; padding: 20px 20px 20px 0; color: #1d2327; }
.fps-sm-page-title { margin-bottom: 16px !important; }

.fps-sm-layout {
    display: flex;
    border: 1px solid #c3c4c7;
    border-radius: 4px;
    overflow: hidden;
    background: #fff;
    min-height: 680px;
}

/* ── Sidebar ── */
.fps-sm-sidebar {
    width: 260px;
    flex-shrink: 0;
    border-right: 1px solid #c3c4c7;
    background: #f6f7f7;
    display: flex;
    flex-direction: column;
    overflow-y: auto;
}
.fps-sm-sidebar-header {
    padding: 14px 14px 12px;
    border-bottom: 1px solid #c3c4c7;
}
.fps-sm-new-btn { width: 100%; justify-content: center; }

.fps-sm-new-form {
    padding: 12px 14px;
    border-bottom: 1px solid #c3c4c7;
    background: #fff;
    display: flex;
    flex-direction: column;
    gap: 4px;
}
.fps-sm-new-label { font-size: 11px; font-weight: 600; color: #50575e; }
.fps-sm-new-error { font-size: 12px; color: #cc1818; margin: 4px 0 0; }
.fps-sm-new-actions { display: flex; gap: 6px; margin-top: 6px; }

.fps-sm-list { flex: 1; overflow-y: auto; padding-bottom: 12px; }
.fps-sm-list-loading { padding: 16px 14px; font-size: 13px; color: #777; margin: 0; }

.fps-sm-group-label {
    padding: 12px 14px 4px;
    font-size: 10px;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: .07em;
    color: #8c9196;
}
.fps-sm-item {
    padding: 9px 14px;
    cursor: pointer;
    display: flex;
    align-items: center;
    gap: 7px;
    font-size: 13px;
    border-left: 3px solid transparent;
    transition: background .1s;
    line-height: 1.3;
}
.fps-sm-item:hover { background: #ededee; }
.fps-sm-item.active {
    background: #e8f0fe;
    border-left-color: #2271b1;
}
.fps-sm-item-name { flex: 1; font-weight: 500; color: #1d2327; }
.fps-sm-item-usage { font-size: 11px; color: #8c9196; }

/* Source badges */
.fps-sm-badge {
    font-size: 10px;
    padding: 2px 7px;
    border-radius: 3px;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: .04em;
    flex-shrink: 0;
}
.fps-sm-badge--plugin  { background: #e8eaf6; color: #3949ab; }
.fps-sm-badge--theme   { background: #e8f5e9; color: #2e7d32; }
.fps-sm-badge--uploads { background: #fff3e0; color: #bf5c00; }

/* ── Editor panel ── */
.fps-sm-editor-panel {
    flex: 1;
    display: flex;
    flex-direction: column;
    background: #fff;
    overflow: hidden;
    min-width: 0;
}
.fps-sm-empty-state {
    flex: 1;
    display: flex;
    align-items: center;
    justify-content: center;
}
.fps-sm-empty-inner { text-align: center; color: #8c9196; }
.fps-sm-empty-icon { font-size: 32px; display: block; margin-bottom: 10px; opacity: .3; }

.fps-sm-editor-ui { display: flex; flex-direction: column; height: 100%; }

.fps-sm-editor-header {
    padding: 12px 20px;
    border-bottom: 1px solid #c3c4c7;
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 12px;
    flex-wrap: wrap;
}
.fps-sm-editor-title-row { display: flex; align-items: center; gap: 10px; flex-wrap: wrap; }
.fps-sm-editor-header h2 { margin: 0; font-size: 16px; font-weight: 600; }
.fps-sm-type-slug { font-size: 11px; font-family: 'SFMono-Regular', Consolas, monospace; background: #f0f0f1; color: #50575e; padding: 2px 8px; border-radius: 3px; }

.fps-sm-editor-actions { display: flex; align-items: center; gap: 10px; flex-shrink: 0; }
.fps-sm-dirty { font-size: 12px; color: #b45309; font-weight: 600; }
.fps-sm-save-notice { font-size: 12px; font-weight: 600; }
.fps-sm-save-notice--success { color: #006505; }
.fps-sm-save-notice--error   { color: #cc1818; }
.fps-sm-readonly-notice {
    font-size: 12px;
    color: #50575e;
    background: #f0f0f1;
    padding: 4px 10px;
    border-radius: 3px;
}

.fps-sm-usage-bar {
    padding: 6px 20px;
    border-bottom: 1px solid #c3c4c7;
    background: #f6f7f7;
    font-size: 12px;
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    gap: 6px;
}
.fps-sm-usage-label { font-weight: 600; color: #50575e; }
.fps-sm-usage-bar a { color: #2271b1; text-decoration: none; }
.fps-sm-usage-bar a:hover { text-decoration: underline; }

/* File tabs */
.fps-sm-tabs {
    display: flex;
    border-bottom: 1px solid #c3c4c7;
    background: #f6f7f7;
    padding: 0 20px;
    gap: 0;
    flex-shrink: 0;
}
.fps-sm-tab {
    padding: 9px 14px;
    border: none;
    background: none;
    cursor: pointer;
    font-size: 12px;
    font-family: 'SFMono-Regular', Consolas, monospace;
    color: #50575e;
    border-bottom: 2px solid transparent;
    margin-bottom: -1px;
    transition: color .12s, border-color .12s;
}
.fps-sm-tab:hover { color: #2271b1; }
.fps-sm-tab.active { color: #2271b1; border-bottom-color: #2271b1; font-weight: 600; }

/* CodeMirror editor area */
.fps-sm-editor-area {
    flex: 1;
    overflow: hidden;
    position: relative;
    min-height: 520px;
}
.fps-sm-textarea { display: none; }

/* Ensure CodeMirror fills the area */
.fps-sm-editor-area .CodeMirror {
    position: absolute;
    top: 0; left: 0; right: 0; bottom: 0;
    height: 100% !important;
    font-family: 'SFMono-Regular', Consolas, 'Liberation Mono', Menlo, monospace;
    font-size: 13px;
    line-height: 1.65;
    border: none;
    border-radius: 0;
}
.fps-sm-editor-area .CodeMirror-scroll { min-height: 520px; }

/* Fallback plain textarea */
.fps-sm-fallback-textarea {
    display: block;
    width: 100%;
    height: 100%;
    min-height: 520px;
    padding: 16px;
    font-family: 'SFMono-Regular', Consolas, monospace;
    font-size: 13px;
    line-height: 1.65;
    border: none;
    outline: none;
    resize: none;
    background: #1e1e2e;
    color: #cdd6f4;
}
</style>

<!-- ── Scripts ────────────────────────────────────────────────────────── -->
<script>
(function () {
    'use strict';

    var DATA = window.framepressSMData || {};
    var REST = (DATA.restUrl || '').replace(/\/$/, '');
    var NONCE = DATA.nonce || '';
    var CM_SETTINGS = DATA.cmSettings || {};

    // ── State ──────────────────────────────────────────────────────────────
    var state = {
        sections:     [],
        currentType:  null,
        currentFile:  null,
        fileContents: {},
        dirtyFiles:   {},
        editable:     false,
        usages:       [],
        cmEditor:     null,
        cmMode:       null,
        creating:     false,
    };

    // ── DOM refs ───────────────────────────────────────────────────────────
    var els = {
        list:         document.getElementById('fps-section-list'),
        emptyState:   document.getElementById('fps-empty-state'),
        editorUi:     document.getElementById('fps-editor-ui'),
        editorName:   document.getElementById('fps-editor-section-name'),
        editorBadge:  document.getElementById('fps-editor-source-badge'),
        editorType:   document.getElementById('fps-editor-section-type'),
        fileTabs:     document.getElementById('fps-file-tabs'),
        editorArea:   document.getElementById('fps-editor-area'),
        cmTextarea:   document.getElementById('fps-cm-textarea'),
        saveBtn:      document.getElementById('fps-save-btn'),
        dirtyBadge:   document.getElementById('fps-dirty-indicator'),
        readonlyNote: document.getElementById('fps-readonly-notice'),
        saveNotice:   document.getElementById('fps-save-notice-inline'),
        usageBar:     document.getElementById('fps-usage-bar'),
        usageLinks:   document.getElementById('fps-usage-links'),
        newBtn:       document.getElementById('fps-new-section-btn'),
        newForm:      document.getElementById('fps-new-section-form'),
        newSlug:      document.getElementById('fps-new-slug'),
        newLabel:     document.getElementById('fps-new-label-input'),
        newSubmit:    document.getElementById('fps-new-section-submit'),
        newCancel:    document.getElementById('fps-new-section-cancel'),
        newError:     document.getElementById('fps-new-section-error'),
    };

    // ── API helper ─────────────────────────────────────────────────────────
    async function apiFetch(path, options) {
        var opts = Object.assign({ method: 'GET', headers: {} }, options);
        opts.headers['X-WP-Nonce'] = NONCE;
        opts.headers['Content-Type'] = 'application/json';
        opts.credentials = 'same-origin';
        var res = await fetch(REST + path, opts);
        var data = await res.json().catch(function () { return {}; });
        if (!res.ok) throw new Error(data.error || data.message || res.statusText);
        return data;
    }

    // ── Fetch + render section list ────────────────────────────────────────
    async function fetchSections() {
        els.list.innerHTML = '<p class="fps-sm-list-loading">Loading…</p>';
        try {
            var data = await apiFetch('/sections-manager/list');
            state.sections = Array.isArray(data) ? data : [];
            renderSidebar();
        } catch (e) {
            els.list.innerHTML = '<p class="fps-sm-list-loading" style="color:#cc1818">Failed to load: ' + escHtml(e.message) + '</p>';
        }
    }

    function renderSidebar() {
        var groups = { plugin: [], theme: [], uploads: [] };
        state.sections.forEach(function (s) {
            var src = s.source || 'plugin';
            (groups[src] = groups[src] || []).push(s);
        });
        var order = ['uploads', 'theme', 'plugin'];
        var labels = { plugin: 'Core (Plugin)', theme: 'Theme', uploads: 'Custom (Yours)' };

        var html = '';
        order.forEach(function (src) {
            var items = groups[src];
            if (!items || !items.length) return;
            html += '<div class="fps-sm-group">';
            html += '<div class="fps-sm-group-label">' + escHtml(labels[src] || src) + '</div>';
            items.forEach(function (s) {
                var active = state.currentType === s.type ? ' active' : '';
                var usageCount = (s.usage || []).length;
                var usageTxt = usageCount > 0 ? usageCount + ' use' + (usageCount > 1 ? 's' : '') : '';
                html += '<div class="fps-sm-item' + active + '" data-type="' + escAttr(s.type) + '">'
                    + '<span class="fps-sm-item-name">' + escHtml(s.label || s.type) + '</span>'
                    + '<span class="fps-sm-badge fps-sm-badge--' + escAttr(src) + '">' + escHtml(labels[src] ? src.charAt(0).toUpperCase() + src.slice(1) : src) + '</span>'
                    + (usageTxt ? '<span class="fps-sm-item-usage">' + escHtml(usageTxt) + '</span>' : '')
                    + '</div>';
            });
            html += '</div>';
        });

        if (!html) html = '<p class="fps-sm-list-loading">No sections found.</p>';
        els.list.innerHTML = html;

        els.list.querySelectorAll('.fps-sm-item').forEach(function (el) {
            el.addEventListener('click', function () {
                selectSection(this.dataset.type);
            });
        });
    }

    // ── Select a section ───────────────────────────────────────────────────
    function selectSection(type) {
        if (state.currentType === type) return;
        if (hasDirty()) {
            if (!confirm('You have unsaved changes. Discard and switch section?')) return;
        }
        state.dirtyFiles = {};
        loadSectionFiles(type);
    }

    async function loadSectionFiles(type) {
        markActive(type);
        els.editorUi.hidden = true;
        els.emptyState.hidden = false;
        els.emptyState.querySelector('p').textContent = 'Loading files…';

        try {
            var data = await apiFetch('/sections-manager/' + encodeURIComponent(type) + '/files');
            state.currentType  = type;
            state.fileContents = data.files   || {};
            state.editable     = !!data.editable;
            state.usages       = [];

            // Grab usages from sidebar data
            var sectionMeta = state.sections.find(function (s) { return s.type === type; });
            if (sectionMeta) state.usages = sectionMeta.usage || [];

            renderEditorUI(sectionMeta);
        } catch (e) {
            els.emptyState.querySelector('p').textContent = 'Error: ' + e.message;
        }
    }

    // ── Render editor UI ───────────────────────────────────────────────────
    var FILE_ORDER = ['schema.php', 'section.php', 'style.css', 'script.js'];

    function renderEditorUI(meta) {
        els.editorUi.hidden  = false;
        els.emptyState.hidden = true;

        var label  = (meta && meta.label)  || state.currentType;
        var source = (meta && meta.source) || 'plugin';

        els.editorName.textContent  = label;
        els.editorType.textContent  = state.currentType;
        els.editorBadge.textContent = source.charAt(0).toUpperCase() + source.slice(1);
        els.editorBadge.className   = 'fps-sm-badge fps-sm-badge--' + source;

        // Save / read-only
        els.saveBtn.hidden      = !state.editable;
        els.readonlyNote.hidden = !!state.editable;
        els.dirtyBadge.hidden   = true;
        els.saveNotice.hidden   = true;

        // Usage bar
        if (state.usages && state.usages.length) {
            var linksHtml = state.usages.map(function (u) {
                return '<a href="' + escAttr(u.edit_url) + '" target="_blank">' + escHtml(u.title) + '</a>';
            }).join('<span style="color:#c3c4c7"> · </span>');
            els.usageLinks.innerHTML = linksHtml;
            els.usageBar.hidden = false;
        } else {
            els.usageBar.hidden = true;
        }

        // Build file tabs
        var available = FILE_ORDER.filter(function (f) { return state.fileContents[f] !== undefined; });
        els.fileTabs.innerHTML = '';
        available.forEach(function (f) {
            var btn = document.createElement('button');
            btn.className = 'fps-sm-tab';
            btn.textContent = f;
            btn.dataset.file = f;
            btn.addEventListener('click', function () { switchTab(f); });
            els.fileTabs.appendChild(btn);
        });

        // Select first tab
        if (available.length) switchTab(available[0]);
    }

    // ── Switch file tab ────────────────────────────────────────────────────
    function switchTab(file) {
        // Flush current CM content before switching
        flushCM();

        state.currentFile = file;

        // Highlight active tab
        els.fileTabs.querySelectorAll('.fps-sm-tab').forEach(function (btn) {
            btn.classList.toggle('active', btn.dataset.file === file);
        });

        var content = state.fileContents[file] || '';
        var mode    = modeForFile(file);
        initOrUpdateCM(content, mode);
    }

    function modeForFile(file) {
        if (file.endsWith('.php')) return 'php';
        if (file.endsWith('.css')) return 'css';
        if (file.endsWith('.js'))  return 'js';
        return 'php';
    }

    // ── CodeMirror ─────────────────────────────────────────────────────────
    function initOrUpdateCM(content, modeKey) {
        var settings = CM_SETTINGS[modeKey];
        var textarea = els.cmTextarea;

        if (state.cmEditor) {
            // Update existing instance
            state.cmEditor.setValue(content);
            if (modeKey !== state.cmMode && settings && settings.codemirror) {
                state.cmEditor.setOption('mode', settings.codemirror.mode);
            }
            state.cmEditor.setOption('readOnly', !state.editable ? 'nocursor' : false);
            state.cmMode = modeKey;
            setTimeout(function () { state.cmEditor.refresh(); }, 10);
            return;
        }

        // First-time init
        if (!settings || !window.wp || !wp.codeEditor) {
            // Fallback textarea
            var ta = document.createElement('textarea');
            ta.className = 'fps-sm-fallback-textarea';
            ta.value = content;
            ta.readOnly = !state.editable;
            ta.addEventListener('input', function () { markDirty(state.currentFile); });
            els.editorArea.innerHTML = '';
            els.editorArea.appendChild(ta);
            state.cmEditor = { getValue: function () { return ta.value; }, isFallback: true };
            return;
        }

        // Initialize CodeMirror via WP wrapper
        var initSettings = {
            codemirror: Object.assign({}, settings.codemirror, {
                readOnly: !state.editable ? 'nocursor' : false,
                lineNumbers: true,
                tabSize: 4,
                indentWithTabs: true,
                lineWrapping: false,
                extraKeys: {
                    'Tab': function (cm) {
                        if (cm.somethingSelected()) { cm.indentSelection('add'); }
                        else { cm.replaceSelection('\t', 'end'); }
                    },
                    'Ctrl-S': function () { if (state.editable) saveFiles(); },
                    'Cmd-S':  function () { if (state.editable) saveFiles(); },
                },
            }),
        };

        var instance    = wp.codeEditor.initialize(textarea, initSettings);
        state.cmEditor  = instance.codemirror;
        state.cmMode    = modeKey;

        state.cmEditor.setValue(content);
        state.cmEditor.on('change', function () {
            markDirty(state.currentFile);
        });

        setTimeout(function () { state.cmEditor.refresh(); }, 50);
    }

    function flushCM() {
        if (!state.cmEditor || !state.currentFile) return;
        if (state.cmEditor.isFallback) {
            var ta = els.editorArea.querySelector('.fps-sm-fallback-textarea');
            if (ta) state.fileContents[state.currentFile] = ta.value;
        } else {
            state.fileContents[state.currentFile] = state.cmEditor.getValue();
        }
    }

    // ── Dirty state ────────────────────────────────────────────────────────
    function markDirty(file) {
        state.dirtyFiles[file] = true;
        els.dirtyBadge.hidden = false;
    }

    function hasDirty() {
        return Object.keys(state.dirtyFiles).some(function (k) { return state.dirtyFiles[k]; });
    }

    // ── Save files ─────────────────────────────────────────────────────────
    async function saveFiles() {
        flushCM();
        els.saveBtn.disabled   = true;
        els.saveBtn.textContent = 'Saving…';
        els.saveNotice.hidden  = true;

        try {
            await apiFetch('/sections-manager/' + encodeURIComponent(state.currentType) + '/files', {
                method: 'POST',
                body:   JSON.stringify({ files: state.fileContents }),
            });

            state.dirtyFiles = {};
            els.dirtyBadge.hidden = true;
            showSaveNotice('✓ Saved', 'success');
        } catch (e) {
            showSaveNotice('Error: ' + e.message, 'error');
        } finally {
            els.saveBtn.disabled   = false;
            els.saveBtn.textContent = 'Save Files';
        }
    }

    function showSaveNotice(msg, type) {
        els.saveNotice.textContent = msg;
        els.saveNotice.className   = 'fps-sm-save-notice fps-sm-save-notice--' + type;
        els.saveNotice.hidden      = false;
        if (type === 'success') {
            setTimeout(function () { els.saveNotice.hidden = true; }, 3000);
        }
    }

    // ── Active sidebar item ────────────────────────────────────────────────
    function markActive(type) {
        var items = els.list.querySelectorAll('.fps-sm-item');
        items.forEach(function (el) {
            el.classList.toggle('active', el.dataset.type === type);
        });
    }

    // ── New section form ───────────────────────────────────────────────────
    els.newBtn.addEventListener('click', function () {
        els.newForm.hidden = false;
        els.newSlug.focus();
        els.newError.hidden = true;
        els.newBtn.hidden = true;
    });

    els.newCancel.addEventListener('click', function () {
        resetNewForm();
    });

    function resetNewForm() {
        els.newForm.hidden  = true;
        els.newBtn.hidden   = false;
        els.newSlug.value   = '';
        els.newLabel.value  = '';
        els.newError.hidden = true;
    }

    els.newSubmit.addEventListener('click', async function () {
        var slug  = els.newSlug.value.trim().toLowerCase();
        var label = els.newLabel.value.trim();

        els.newError.hidden = true;

        if (!/^[a-z0-9\-]+$/.test(slug)) {
            showNewError('Slug must be lowercase letters, numbers and hyphens only.');
            return;
        }
        if (!label) {
            showNewError('Label is required.');
            return;
        }

        els.newSubmit.disabled   = true;
        els.newSubmit.textContent = 'Creating…';

        try {
            var res = await apiFetch('/sections-manager/create', {
                method: 'POST',
                body:   JSON.stringify({ slug: slug, label: label }),
            });

            resetNewForm();
            await fetchSections();
            selectSection(res.type || slug);
        } catch (e) {
            showNewError(e.message || 'Could not create section.');
        } finally {
            els.newSubmit.disabled   = false;
            els.newSubmit.textContent = 'Create';
        }
    });

    function showNewError(msg) {
        els.newError.textContent = msg;
        els.newError.hidden = false;
    }

    // ── Save button ────────────────────────────────────────────────────────
    els.saveBtn.addEventListener('click', saveFiles);

    // ── Warn on page leave with unsaved changes ────────────────────────────
    window.addEventListener('beforeunload', function (e) {
        if (hasDirty()) {
            e.preventDefault();
            e.returnValue = '';
        }
    });

    // ── Helpers ────────────────────────────────────────────────────────────
    function escHtml(str) {
        return String(str).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
    }
    function escAttr(str) { return escHtml(str); }

    // ── Boot ───────────────────────────────────────────────────────────────
    document.addEventListener('DOMContentLoaded', function () {
        // Check for ?type= in URL to auto-select
        var urlParams = new URLSearchParams(window.location.search);
        var autoType  = urlParams.get('type');

        fetchSections().then(function () {
            if (autoType) selectSection(autoType);
        });
    });

    // Needed because DOMContentLoaded may have already fired in WP admin
    if (document.readyState !== 'loading') {
        var urlParams = new URLSearchParams(window.location.search);
        var autoType  = urlParams.get('type');
        fetchSections().then(function () {
            if (autoType) selectSection(autoType);
        });
    }

})();
</script>
