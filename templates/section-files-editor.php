<?php
/**
 * Full-page section file editor (tabs + live preview). Hidden menu: admin.php?page=hero-section-files&type={slug}
 */
defined( 'ABSPATH' ) || exit;

$section_type = isset( $_GET['type'] ) ? sanitize_title( wp_unslash( $_GET['type'] ) ) : '';
if ( $section_type === '' ) {
	wp_safe_redirect( admin_url( 'admin.php?page=hero-sections-mgr' ) );
	exit;
}

$cm_php = wp_enqueue_code_editor( [ 'type' => 'application/x-httpd-php' ] );
$cm_css = wp_enqueue_code_editor( [ 'type' => 'text/css' ] );
$cm_js  = wp_enqueue_code_editor( [ 'type' => 'text/javascript' ] );
wp_enqueue_script( 'wp-theme-plugin-editor' );
wp_enqueue_style( 'wp-codemirror' );

$list_url   = admin_url( 'admin.php?page=hero-sections-mgr' );
$editor_url = admin_url( 'admin.php?page=hero-section-files' );
?>
<script>
window.heroSMData = <?php echo wp_json_encode( [
	'restUrl'         => rest_url( 'hero/v1' ),
	'nonce'           => wp_create_nonce( 'wp_rest' ),
	'sectionType'     => $section_type,
	'listPageUrl'     => $list_url,
	'sectionEditorUrl' => $editor_url,
	'cmSettings'      => [
		'php' => $cm_php,
		'css' => $cm_css,
		'js'  => $cm_js,
	],
] ); ?>;
</script>

<div class="wrap fps-sfe-wrap">
	<div class="fps-sfe-topbar">
		<a href="<?php echo esc_url( $list_url ); ?>" class="button"><?php esc_html_e( '← Back to Sections', 'hero' ); ?></a>
		<h1 class="fps-sfe-title"><?php echo esc_html( sprintf( /* translators: %s: section slug */ __( 'Edit section: %s', 'hero' ), $section_type ) ); ?></h1>
	</div>

	<div class="fps-sfe-shell" id="fps-sfe-shell">
		<p class="fps-sfe-loading"><?php esc_html_e( 'Loading files…', 'hero' ); ?></p>

		<div id="fps-sfe-editor-root" class="fps-sfe-editor-root" hidden>
			<div class="fps-sfe-editor-head">
				<div class="fps-sfe-meta">
					<span id="fps-sfe-badge" class="fps-sm-badge"></span>
					<code id="fps-sfe-type-slug" class="fps-sfe-slug"></code>
				</div>
				<div class="fps-sfe-actions">
					<span id="fps-sfe-dirty" class="fps-sm-dirty" hidden>● <?php esc_html_e( 'Unsaved changes', 'hero' ); ?></span>
					<span id="fps-sfe-save-msg" class="fps-sm-save-notice" hidden></span>
					<button type="button" id="fps-sfe-save" class="button button-primary"><?php esc_html_e( 'Save Files', 'hero' ); ?></button>
					<span id="fps-sfe-readonly" class="fps-sm-readonly-notice" hidden><?php esc_html_e( 'Read-only', 'hero' ); ?></span>
				</div>
			</div>

			<div class="fps-sfe-preview-panel">
				<div class="fps-sfe-preview-head">
					<div class="fps-sfe-preview-head-left">
						<strong><?php esc_html_e( 'Live preview', 'hero' ); ?></strong>
						<span id="fps-sfe-preview-status" class="fps-sfe-preview-status"></span>
					</div>
					<div class="fps-sfe-device-btns" role="group" aria-label="<?php esc_attr_e( 'Preview width', 'hero' ); ?>">
						<button type="button" class="button fps-sfe-device-btn fps-sfe-device-btn--active" data-device="desktop"><?php esc_html_e( 'Desktop', 'hero' ); ?></button>
						<button type="button" class="button fps-sfe-device-btn" data-device="mobile"><?php esc_html_e( 'Mobile', 'hero' ); ?></button>
					</div>
				</div>
				<div class="fps-sfe-preview-viewport fps-sfe-preview-viewport--desktop" id="fps-sfe-preview-viewport">
					<iframe id="fps-sfe-preview-frame" class="fps-sfe-preview-frame" title="<?php esc_attr_e( 'Section preview', 'hero' ); ?>"></iframe>
				</div>
			</div>

			<div class="fps-sfe-code-panel" id="fps-sfe-code-panel">
				<div class="fps-sfe-code-panel-bar">
					<span class="fps-sfe-code-panel-label"><?php esc_html_e( 'Sources', 'hero' ); ?></span>
					<div class="fps-sm-tabs fps-sfe-code-tabs" id="fps-sfe-tabs"></div>
				</div>
				<div class="fps-sfe-ai-panel" id="fps-sfe-ai-panel">
					<label class="fps-sfe-ai-label" for="fps-sfe-ai-instruction"><?php esc_html_e( 'AI assist', 'hero' ); ?></label>
					<textarea id="fps-sfe-ai-instruction" class="fps-sfe-ai-instruction" rows="2" placeholder="<?php echo esc_attr( __( 'Describe the change for this section only…', 'hero' ) ); ?>"></textarea>
					<div class="fps-sfe-ai-actions">
						<button type="button" class="button button-primary" id="fps-sfe-ai-run"><?php esc_html_e( 'Apply with AI', 'hero' ); ?></button>
						<span class="fps-sfe-ai-status" id="fps-sfe-ai-status" aria-live="polite"></span>
					</div>
				</div>
				<div class="fps-sfe-editor-split">
					<div class="fps-sfe-editor-area" id="fps-sfe-editor-area">
						<textarea id="fps-sfe-cm-textarea" class="fps-sm-textarea"></textarea>
					</div>
				</div>
			</div>
		</div>
	</div>
</div>

<style>
.fps-sfe-wrap { max-width: 100%; margin: 0 20px 0 0; }
.fps-sfe-topbar { display: flex; align-items: center; gap: 16px; margin-bottom: 8px; flex-wrap: wrap; }
.fps-sfe-title { margin: 0; font-size: 20px; }
.fps-sfe-shell { border: 1px solid #c3c4c7; border-radius: 4px; background: #fff; min-height: 100vh; display: flex; flex-direction: column; }
.fps-sfe-loading { padding: 24px; color: #646970; margin: 0; }
/* Reserve space so preview/head are not covered by the fixed code panel. */
.fps-sfe-editor-root { display: flex; flex-direction: column; flex: 1; min-height: 0; height: 100%; padding-bottom: min(50vh, 520px); box-sizing: border-box; }
.fps-sfe-editor-head { flex-shrink: 0; display: flex; justify-content: space-between; align-items: center; padding: 8px 16px; border-bottom: 1px solid #c3c4c7; flex-wrap: wrap; gap: 8px; }
.fps-sfe-meta { display: flex; align-items: center; gap: 10px; }
.fps-sfe-slug { font-size: 12px; background: #f0f0f1; padding: 2px 8px; border-radius: 3px; }
.fps-sfe-actions { display: flex; align-items: center; gap: 10px; }
.fps-sfe-preview-panel { flex: 1 1 auto; min-height: 0; display: flex; flex-direction: column; background: #f0f0f0; border-bottom: 1px solid #c3c4c7; }
.fps-sfe-preview-head { flex-shrink: 0; display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 8px; padding: 6px 12px; font-size: 12px; color: #50575e; background: #f6f7f7; border-bottom: 1px solid #dcdcde; }
.fps-sfe-preview-head-left { display: flex; align-items: center; gap: 8px; flex-wrap: wrap; }
.fps-sfe-preview-status { font-size: 11px; color: #787c82; }
.fps-sfe-device-btns { display: inline-flex; gap: 0; }
.fps-sfe-device-btns .button { border-radius: 0; margin: 0 -1px 0 0; box-shadow: none; }
.fps-sfe-device-btns .button:first-of-type { border-top-left-radius: 3px; border-bottom-left-radius: 3px; }
.fps-sfe-device-btns .button:last-of-type { border-top-right-radius: 3px; border-bottom-right-radius: 3px; margin-right: 0; }
.fps-sfe-device-btns .fps-sfe-device-btn--active { background: #2271b1; color: #fff; border-color: #2271b1; }
.fps-sfe-preview-viewport { flex: 1; min-height: 0; min-width: 0; display: flex; justify-content: center; align-items: flex-start; background: #e8e8e8; }
.fps-sfe-preview-viewport--desktop { align-items: stretch; padding: 0; overflow: hidden; }
.fps-sfe-preview-viewport--mobile { align-items: flex-start; justify-content: center; padding: 12px 8px; overflow: auto; }
.fps-sfe-preview-viewport--desktop .fps-sfe-preview-frame { width: 100%; max-width: none; min-height: 0; height: 100%; flex: 1 1 auto; align-self: stretch; box-shadow: none; border: 0; }
.fps-sfe-preview-viewport--mobile .fps-sfe-preview-frame { width: 390px; max-width: 100%; min-height: 480px; height: min(70vh, 900px); flex: 0 0 auto; background: #fff; border: 0; border-radius: 8px; box-shadow: 0 2px 12px rgba(0,0,0,.12); }
.fps-sfe-preview-frame { display: block; }
/* Sticky to viewport bottom (DevTools-style); stays visible while the admin page scrolls. */
.fps-sfe-code-panel {
	position: fixed;
	z-index: 100050;
	bottom: 0;
	left: 180px; /* #adminmenuwrap 160px + small gutter */
	right: 24px;
	min-width: 0;
	min-height: 280px;
	max-height: 50vh;
	width: auto;
	display: flex;
	flex-direction: column;
	background: #fff;
	border: 1px solid #c3c4c7;
	border-bottom: none;
	border-radius: 6px 6px 0 0;
	box-shadow: 0 -6px 24px rgba(0,0,0,.12);
}
body.folded .fps-sfe-code-panel { left: 56px; }
@media screen and (max-width: 782px) {
	.fps-sfe-code-panel { left: 12px; right: 12px; }
}
.fps-sfe-code-panel-bar { flex-shrink: 0; display: flex; flex-direction: column; background: #2b2b2b; border-top: none; }
.fps-sfe-ai-panel { flex-shrink: 0; padding: 8px 12px 10px; background: #1e1e1e; border-bottom: 1px solid #3c434a; display: flex; flex-direction: column; gap: 6px; }
.fps-sfe-ai-label { font-size: 11px; font-weight: 600; color: #c4c7c5; }
.fps-sfe-ai-instruction { width: 100%; max-width: 100%; box-sizing: border-box; font-size: 12px; padding: 6px 8px; border-radius: 3px; border: 1px solid #50575e; background: #2c3338; color: #f0f0f1; resize: vertical; min-height: 44px; font-family: inherit; }
.fps-sfe-ai-instruction:disabled { opacity: 0.55; cursor: not-allowed; }
.fps-sfe-ai-actions { display: flex; align-items: center; flex-wrap: wrap; gap: 10px; }
.fps-sfe-ai-status { font-size: 12px; color: #9aa0a6; flex: 1; min-width: 120px; }
.fps-sfe-ai-status.fps-sfe-ai-status--error { color: #f0a0a0; }
.fps-sfe-ai-status.fps-sfe-ai-status--ok { color: #9fd69f; }
.fps-sfe-code-panel-label { font-size: 10px; font-weight: 600; text-transform: uppercase; letter-spacing: 0.06em; color: #9aa0a6; padding: 5px 12px 0; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; }
.fps-sfe-code-tabs { display: flex; flex-wrap: wrap; border-bottom: 1px solid #1e1e1e; padding: 0 8px 0; gap: 0; }
.fps-sfe-code-tabs .fps-sm-tab { padding: 8px 14px 7px; border: none; background: none; cursor: pointer; font-size: 12px; font-family: 'SFMono-Regular', Consolas, 'Liberation Mono', Menlo, monospace; color: #9aa0a6; border-bottom: 2px solid transparent; margin-bottom: -1px; }
.fps-sfe-code-tabs .fps-sm-tab:hover { color: #e8eaed; background: rgba(255,255,255,.04); }
.fps-sfe-code-tabs .fps-sm-tab.active { color: #e8eaed; border-bottom-color: #1a73e8; font-weight: 600; background: #252525; }
.fps-sfe-editor-split { flex: 1; display: flex; flex-direction: column; min-height: 0; }
.fps-sfe-editor-area { position: relative; flex: 1; min-height: 120px; overflow: hidden; }
.fps-sfe-editor-area .CodeMirror { position: absolute; top:0; left:0; right:0; bottom:0; height: 100% !important; font-size: 13px; }
.fps-sm-textarea { display: none; }
.fps-sm-badge { font-size: 10px; padding: 2px 7px; border-radius: 3px; font-weight: 700; text-transform: uppercase; letter-spacing: .04em; }
.fps-sm-badge--plugin  { background: #e8eaf6; color: #3949ab; }
.fps-sm-badge--theme   { background: #e8f5e9; color: #2e7d32; }
.fps-sm-badge--uploads { background: #fff3e0; color: #bf5c00; }
.fps-sm-dirty { font-size: 12px; color: #b45309; font-weight: 600; }
.fps-sm-save-notice--success { color: #006505; font-size: 12px; }
.fps-sm-save-notice--error { color: #cc1818; font-size: 12px; }
.fps-sm-readonly-notice { font-size: 12px; color: #50575e; background: #f0f0f1; padding: 4px 10px; border-radius: 3px; }
</style>

<script>
(function () {
	'use strict';
	var DATA = window.heroSMData || {};
	var REST = (DATA.restUrl || '').replace(/\/$/, '');
	var NONCE = DATA.nonce || '';
	var CM_SETTINGS = DATA.cmSettings || {};
	var SECTION_TYPE = DATA.sectionType || '';
	var FILE_ORDER = [ 'schema.php', 'section.php', 'style.css', 'script.js' ];

	var state = {
		fileContents:        {},
		currentFile:         null,
		editable:            false,
		dirtyFiles:          {},
		cmEditor:            null,
		cmMode:              null,
		previewTimer:        null,
		previewUrl:          null,
		previewDevice:       'desktop',
		cmResizeTimer:       null,
		aiAssistAvailable:   false,
	};

	function refreshCodeMirror() {
		if (!state.cmEditor || state.cmEditor.isFallback) return;
		setTimeout(function () {
			try { state.cmEditor.refresh(); } catch (e) { /* ignore */ }
		}, 0);
		setTimeout(function () {
			try { state.cmEditor.refresh(); } catch (e) { /* ignore */ }
		}, 100);
	}

	var els = {
		shell:     document.getElementById('fps-sfe-shell'),
		root:      document.getElementById('fps-sfe-editor-root'),
		codePanel: document.getElementById('fps-sfe-code-panel'),
		loading:   document.querySelector('.fps-sfe-loading'),
		badge:     document.getElementById('fps-sfe-badge'),
		slug:      document.getElementById('fps-sfe-type-slug'),
		tabs:      document.getElementById('fps-sfe-tabs'),
		editorArea: document.getElementById('fps-sfe-editor-area'),
		cmTextarea: document.getElementById('fps-sfe-cm-textarea'),
		saveBtn:   document.getElementById('fps-sfe-save'),
		dirty:     document.getElementById('fps-sfe-dirty'),
		saveMsg:   document.getElementById('fps-sfe-save-msg'),
		readonly:  document.getElementById('fps-sfe-readonly'),
		viewport:  document.getElementById('fps-sfe-preview-viewport'),
		iframe:    document.getElementById('fps-sfe-preview-frame'),
		pvStatus:  document.getElementById('fps-sfe-preview-status'),
		aiInstruction: document.getElementById('fps-sfe-ai-instruction'),
		aiRun:     document.getElementById('fps-sfe-ai-run'),
		aiStatus:  document.getElementById('fps-sfe-ai-status'),
	};

	function setPreviewDevice(device) {
		state.previewDevice = (device === 'mobile') ? 'mobile' : 'desktop';
		if (els.viewport) {
			els.viewport.classList.remove('fps-sfe-preview-viewport--desktop', 'fps-sfe-preview-viewport--mobile');
			els.viewport.classList.add(state.previewDevice === 'mobile' ? 'fps-sfe-preview-viewport--mobile' : 'fps-sfe-preview-viewport--desktop');
		}
		document.querySelectorAll('.fps-sfe-device-btn').forEach(function (btn) {
			var on = (btn.getAttribute('data-device') || '') === state.previewDevice;
			btn.classList.toggle('fps-sfe-device-btn--active', on);
		});
		try {
			if (els.iframe && els.iframe.contentWindow) {
				els.iframe.contentWindow.dispatchEvent(new Event('resize'));
			}
		} catch (e) { /* cross-origin or empty */ }
		refreshCodeMirror();
	}

	function parseJsonResponse(res) {
		return res.text().then(function (text) {
			text = String(text || '').replace(/^\uFEFF+/, '').trim();
			if (!text) return {};
			try { return JSON.parse(text); } catch (e) { return { error: 'Invalid JSON' }; }
		});
	}

	function apiFetch(path, options) {
		var opts = Object.assign({ method: 'GET', headers: {} }, options);
		opts.headers['X-WP-Nonce'] = NONCE;
		opts.headers['Content-Type'] = 'application/json';
		opts.credentials = 'same-origin';
		return fetch(REST + path, opts).then(function (res) {
			return parseJsonResponse(res).then(function (data) {
				if (!res.ok) throw new Error(data.error || data.message || res.statusText);
				return data;
			});
		});
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

	function markDirty() {
		state.dirtyFiles[state.currentFile] = true;
		els.dirty.hidden = false;
	}

	function updateDirtyVisibility() {
		els.dirty.hidden = !Object.keys(state.dirtyFiles).some(function (k) { return state.dirtyFiles[k]; });
	}

	function mergeAiFilesIntoState(files) {
		var before = {};
		FILE_ORDER.forEach(function (f) { before[f] = state.fileContents[f]; });
		Object.keys(files || {}).forEach(function (f) {
			if (FILE_ORDER.indexOf(f) === -1) return;
			state.fileContents[f] = files[f];
			if (before[f] !== files[f]) {
				state.dirtyFiles[f] = true;
			}
		});
		updateDirtyVisibility();
		if (state.currentFile && files[state.currentFile] !== undefined) {
			initOrUpdateCM(state.fileContents[state.currentFile] || '', modeForFile(state.currentFile));
		}
	}

	function hasDirty() {
		return Object.keys(state.dirtyFiles).some(function (k) { return state.dirtyFiles[k]; });
	}

	function modeForFile(file) {
		if (file.indexOf('.php') !== -1) return 'php';
		if (file.indexOf('.css') !== -1) return 'css';
		if (file.indexOf('.js') !== -1)  return 'js';
		return 'php';
	}

	function initOrUpdateCM(content, modeKey) {
		var settings = CM_SETTINGS[modeKey];
		var textarea = els.cmTextarea;
		if (state.cmEditor) {
			if (state.cmEditor.isFallback) {
				var taf = els.editorArea.querySelector('.fps-sm-fallback-textarea');
				if (taf) taf.value = content;
			} else {
				state.cmEditor.setValue(content);
				if (modeKey !== state.cmMode && settings && settings.codemirror) {
					state.cmEditor.setOption('mode', settings.codemirror.mode);
				}
			}
			state.cmEditor.setOption('readOnly', !state.editable ? 'nocursor' : false);
			state.cmMode = modeKey;
			if (!state.cmEditor.isFallback) refreshCodeMirror();
			return;
		}
		if (!settings || !window.wp || !wp.codeEditor) {
			els.editorArea.innerHTML = '';
			var ta = document.createElement('textarea');
			ta.className = 'fps-sm-fallback-textarea';
			ta.value = content;
			ta.readOnly = !state.editable;
			ta.style.minHeight = '300px';
			ta.addEventListener('input', function () { markDirty(); schedulePreview(); });
			els.editorArea.appendChild(ta);
			state.cmEditor = { getValue: function () { return ta.value; }, isFallback: true, setValue: function (v) { ta.value = v; }, setOption: function () {}, refresh: function () {} };
			return;
		}
		var initSettings = {
			codemirror: Object.assign({}, settings.codemirror, {
				readOnly: !state.editable ? 'nocursor' : false,
				lineNumbers: true,
				tabSize: 4,
				indentWithTabs: true,
				extraKeys: { 'Ctrl-S': function () { if (state.editable) saveFiles(); }, 'Cmd-S': function () { if (state.editable) saveFiles(); } },
			}),
		};
		var instance = wp.codeEditor.initialize(textarea, initSettings);
		state.cmEditor = instance.codemirror;
		state.cmMode = modeKey;
		state.cmEditor.setValue(content);
		state.cmEditor.on('change', function () {
			markDirty();
			schedulePreview();
		});
		refreshCodeMirror();
	}

	function switchTab(file) {
		flushCM();
		state.currentFile = file;
		els.tabs.querySelectorAll('.fps-sm-tab').forEach(function (btn) {
			btn.classList.toggle('active', btn.dataset.file === file);
		});
		var content = state.fileContents[file] || '';
		initOrUpdateCM(content, modeForFile(file));
	}

	function onPreviewIframeLoad() {
		try {
			if (els.iframe && els.iframe.contentWindow) {
				els.iframe.contentWindow.dispatchEvent(new Event('resize'));
			}
		} catch (e) { /* ignore */ }
	}

	function schedulePreview() {
		if (state.previewTimer) clearTimeout(state.previewTimer);
		state.previewTimer = setTimeout(runPreview, 500);
	}

	function runPreview() {
		flushCM();
		var sp = state.fileContents['section.php'] !== undefined ? (state.fileContents['section.php'] || '') : '';
		var ss = state.fileContents['style.css'] !== undefined ? (state.fileContents['style.css'] || '') : '';
		els.pvStatus.textContent = '…';
		apiFetch('/sections-manager/' + encodeURIComponent(SECTION_TYPE) + '/draft-preview', {
			method: 'POST',
			body: JSON.stringify({ section_php: sp, style_css: ss }),
		}).then(function (data) {
			var html = data.html || '';
			var css = data.css || '';
			var doc = '<!DOCTYPE html><html><head><meta charset="utf-8">'
				+ '<meta name="viewport" content="width=device-width, initial-scale=1">'
				+ '<style>' + css + '</style></head><body style="margin:0;background:#fff">' + html + '</body></html>';
			var blob = new Blob([ doc ], { type: 'text/html;charset=utf-8' });
			if (state.previewUrl) URL.revokeObjectURL(state.previewUrl);
			state.previewUrl = URL.createObjectURL(blob);
			els.iframe.src = state.previewUrl;
			els.pvStatus.textContent = 'Updated';
		}).catch(function (e) {
			els.pvStatus.textContent = e.message || 'Error';
		});
	}

	async function saveFiles() {
		flushCM();
		els.saveBtn.disabled = true;
		els.saveMsg.hidden = true;
		try {
			await apiFetch('/sections-manager/' + encodeURIComponent(SECTION_TYPE) + '/files', {
				method: 'POST',
				body: JSON.stringify({ files: state.fileContents }),
			});
			state.dirtyFiles = {};
			els.dirty.hidden = true;
			els.saveMsg.textContent = '✓ <?php echo esc_js( __( 'Saved', 'hero' ) ); ?>';
			els.saveMsg.className = 'fps-sm-save-notice fps-sm-save-notice--success';
			els.saveMsg.hidden = false;
			setTimeout(function () { els.saveMsg.hidden = true; }, 3000);
			runPreview();
		} catch (e) {
			els.saveMsg.textContent = e.message;
			els.saveMsg.className = 'fps-sm-save-notice fps-sm-save-notice--error';
			els.saveMsg.hidden = false;
		} finally {
			els.saveBtn.disabled = false;
		}
	}

	function setAiPanelEnabled(on) {
		if (!els.aiInstruction || !els.aiRun) return;
		els.aiInstruction.disabled = !on;
		els.aiRun.disabled = !on;
	}

	function buildUI(meta) {
		var source = (meta && meta.source) || 'plugin';
		els.slug.textContent = SECTION_TYPE;
		els.badge.textContent = source.charAt(0).toUpperCase() + source.slice(1);
		els.badge.className = 'fps-sm-badge fps-sm-badge--' + source;

		els.saveBtn.hidden = !state.editable;
		els.readonly.hidden = !!state.editable;
		els.dirty.hidden = true;

		setAiPanelEnabled(!!state.aiAssistAvailable);
		if (els.aiStatus) {
			els.aiStatus.textContent = state.aiAssistAvailable
				? ''
				: '<?php echo esc_js( __( 'AI is not configured (HERO → AI Settings).', 'hero' ) ); ?>';
			els.aiStatus.className = 'fps-sfe-ai-status' + (state.aiAssistAvailable ? '' : ' fps-sfe-ai-status--error');
		}

		var available = FILE_ORDER.filter(function (f) { return state.fileContents[f] !== undefined; });
		els.tabs.innerHTML = '';
		available.forEach(function (f) {
			var btn = document.createElement('button');
			btn.type = 'button';
			btn.className = 'fps-sm-tab';
			btn.textContent = f;
			btn.dataset.file = f;
			btn.addEventListener('click', function () { switchTab(f); });
			els.tabs.appendChild(btn);
		});
		if (els.loading) els.loading.hidden = true;
		els.root.hidden = false;
		if (available.length) switchTab(available[0]);
		else els.pvStatus.textContent = 'No files';
		runPreview();
		refreshCodeMirror();
	}

	function runAiAssist() {
		if (!state.aiAssistAvailable || !els.aiRun) return;
		flushCM();
		var text = (els.aiInstruction && els.aiInstruction.value) ? String(els.aiInstruction.value).trim() : '';
		if (!text) {
			if (els.aiStatus) {
				els.aiStatus.textContent = '<?php echo esc_js( __( 'Enter an instruction first.', 'hero' ) ); ?>';
				els.aiStatus.className = 'fps-sfe-ai-status fps-sfe-ai-status--error';
			}
			return;
		}
		els.aiRun.disabled = true;
		if (els.aiStatus) {
			els.aiStatus.textContent = '<?php echo esc_js( __( 'Working…', 'hero' ) ); ?>';
			els.aiStatus.className = 'fps-sfe-ai-status';
		}
		apiFetch('/sections-manager/' + encodeURIComponent(SECTION_TYPE) + '/ai-assist', {
			method: 'POST',
			body: JSON.stringify({ instruction: text, files: state.fileContents }),
		}).then(function (data) {
			mergeAiFilesIntoState(data.files);
			refreshCodeMirror();
			schedulePreview();
			if (els.aiStatus) {
				var b = (data.brief && String(data.brief).trim()) ? String(data.brief).trim() : '<?php echo esc_js( __( 'Applied.', 'hero' ) ); ?>';
				els.aiStatus.textContent = b;
				els.aiStatus.className = 'fps-sfe-ai-status fps-sfe-ai-status--ok';
			}
		}).catch(function (e) {
			if (els.aiStatus) {
				els.aiStatus.textContent = e.message || 'Error';
				els.aiStatus.className = 'fps-sfe-ai-status fps-sfe-ai-status--error';
			}
		}).finally(function () {
			els.aiRun.disabled = !state.aiAssistAvailable;
		});
	}

	els.saveBtn.addEventListener('click', saveFiles);
	if (els.aiRun) {
		els.aiRun.addEventListener('click', runAiAssist);
	}
	document.querySelectorAll('.fps-sfe-device-btn').forEach(function (btn) {
		btn.addEventListener('click', function () {
			var d = this.getAttribute('data-device') || 'desktop';
			setPreviewDevice(d);
		});
	});
	els.iframe.addEventListener('load', onPreviewIframeLoad);
	window.addEventListener('beforeunload', function (e) {
		if (hasDirty()) { e.preventDefault(); e.returnValue = ''; }
	});
	window.addEventListener('resize', function () {
		if (state.cmResizeTimer) clearTimeout(state.cmResizeTimer);
		state.cmResizeTimer = setTimeout(function () { refreshCodeMirror(); }, 150);
	});
	if (els.codePanel && typeof ResizeObserver !== 'undefined') {
		var cmResizeObs = new ResizeObserver(function () { refreshCodeMirror(); });
		cmResizeObs.observe(els.codePanel);
	}

	apiFetch('/sections-manager/' + encodeURIComponent(SECTION_TYPE) + '/files').then(function (data) {
		state.fileContents = data.files || {};
		state.editable = !!data.editable;
		state.aiAssistAvailable = !!data.ai_assist_available;
		buildUI({ source: data.source || (data.editable ? 'uploads' : 'plugin') });
	}).catch(function (e) {
		if (els.loading) { els.loading.textContent = e.message; }
	});
})();
</script>
