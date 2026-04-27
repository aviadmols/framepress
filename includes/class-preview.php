<?php
/**
 * HERO Preview
 *
 * Handles the live preview endpoint: /?hero_preview=1&post_id=X&nonce=Y
 *
 * Renders a real WordPress frontend page (with actual theme, header, footer)
 * but substitutes the page's section data with the draft state supplied via
 * the editor's postMessage bridge.
 *
 * Security:
 * - Nonce validated before any output.
 * - No PHP from POST body — only JSON section data values (rendered by the
 *   normal Section Renderer pipeline which only includes files from disk).
 *
 * Live preview bridge:
 * - Section DOM updates are scoped by builder context (page / header / footer) so
 *   editing one area does not remove sections elsewhere.
 * - After each batch update, `hero:section-mounted` CustomEvent fires per section id
 *   (detail: { sectionId, root }) so section script.js can re-initialize after DOM replace.
 * - Set WP_DEBUG to log HERO_UPDATE handling in the browser console.
 */

defined( 'ABSPATH' ) || exit;

class Hero_Preview {

    private Hero_Section_Renderer $renderer;
    private Hero_Global_Settings  $global_settings;

    /** Preview context from query string (page|header|footer|global|…). */
    private string $preview_context = 'page';

    public function __construct(
        Hero_Section_Renderer $renderer,
        Hero_Global_Settings  $global_settings
    ) {
        $this->renderer        = $renderer;
        $this->global_settings = $global_settings;
    }

    /**
     * Called on template_redirect. Validates the request and, if valid,
     * outputs the preview page and exits.
     */
    public function handle(): void {
        $post_id = absint( $_GET['post_id'] ?? 0 );
        $nonce   = sanitize_text_field( $_GET['nonce'] ?? '' );
        $context = sanitize_text_field( $_GET['context'] ?? 'page' );

        if ( ! $nonce ) {
            return;
        }

        $nonce_action = $post_id ? 'hero_preview_' . $post_id : 'hero_preview_0';
        if ( ! wp_verify_nonce( $nonce, $nonce_action ) ) {
            wp_die( esc_html__( 'Preview link has expired. Please return to the builder and try again.', 'hero' ), 403 );
        }

        $this->preview_context = $context;

        if ( $post_id > 0 ) {
            if ( ! current_user_can( 'edit_post', $post_id ) ) {
                wp_die( esc_html__( 'You do not have permission to preview this page.', 'hero' ), 403 );
            }

            // Override the global query to point at the requested post.
            global $wp_query;
            $post = get_post( $post_id );
            if ( ! $post ) {
                wp_die( esc_html__( 'Post not found.', 'hero' ) );
            }

            $wp_query->is_preview        = true;
            $wp_query->is_singular       = true;
            $wp_query->is_single         = ( $post->post_type === 'post' );
            $wp_query->is_page           = ( $post->post_type === 'page' );
            $wp_query->queried_object    = $post;
            $wp_query->queried_object_id = $post_id;
        } else {
            // Header / footer / global preview: no specific post; use the URL target (usually home).
            if ( ! current_user_can( 'edit_pages' ) ) {
                wp_die( esc_html__( 'You do not have permission to use the preview.', 'hero' ), 403 );
            }
        }

        // Hide the admin bar so it doesn't eat preview space.
        add_filter( 'show_admin_bar', '__return_false' );
        add_action( 'wp_head', function () {
            echo '<style>html{margin-top:0!important}#wpadminbar{display:none!important}</style>';
        }, 99 );

        // Inject preview JS listener at end of body so the editor can push
        // live updates via postMessage.
        add_action( 'wp_footer', [ $this, 'output_preview_listener' ], 99 );

        // Mark this as a preview request so the renderer can skip caching.
        add_filter( 'hero_is_preview', '__return_true' );

        // Proceed with the normal WordPress template loading.
        // The renderer's the_content filter is already hooked — it will
        // use saved sections for the initial load.
    }

    /**
     * Inject the preview postMessage bridge into the preview page footer.
     * This script receives live updates from the builder editor iframe parent.
     */
    public function output_preview_listener(): void {
        $rest_url = esc_js( rest_url( 'hero/v1' ) );
        $nonce    = esc_js( wp_create_nonce( 'wp_rest' ) );
        $ctx      = esc_js( $this->preview_context );
        $debug    = ( defined( 'WP_DEBUG' ) && WP_DEBUG ) ? 'true' : 'false';
        ?>
        <style id="hero-preview-ui">
        .hero-section {
            position: relative;
            outline: 2px solid transparent;
            outline-offset: -2px;
            cursor: pointer;
            transition: outline-color 0.15s;
        }
        .hero-section:hover { outline-color: rgba(44,110,203,0.35); }
        .hero-section--selected { outline-color: #2c6ecb !important; }
        .hero-drag-handle {
            position: absolute;
            top: 8px;
            right: 10px;
            z-index: 9999;
            background: rgba(44,110,203,0.85);
            border-radius: 5px;
            padding: 5px 7px;
            cursor: grab;
            opacity: 0;
            transition: opacity 0.2s;
            display: flex;
            align-items: center;
            backdrop-filter: blur(2px);
        }
        .hero-section:hover .hero-drag-handle,
        .hero-section--selected .hero-drag-handle { opacity: 1; }
        .hero-drag-handle:active { cursor: grabbing; }
        .hero-drag-placeholder {
            background: rgba(44,110,203,0.08);
            border: 2px dashed #2c6ecb;
            border-radius: 4px;
            margin: 4px 0;
            pointer-events: none;
        }
        .hero-section.hero-dragging {
            box-shadow: 0 8px 32px rgba(0,0,0,0.2);
            z-index: 9998;
        }
        </style>
        <script id="hero-preview-bridge">
        (function() {
            var REST_URL = '<?php echo $rest_url; ?>';
            var NONCE    = '<?php echo $nonce; ?>';
            /** Builder context: only sections inside this scope are updated or removed. */
            var PREVIEW_CONTEXT = '<?php echo $ctx; ?>';
            var HERO_PREVIEW_DEBUG = <?php echo $debug; ?>;

            /**
             * Swap or insert a section's HTML in the DOM.
             */
            /**
             * Return (or create) the HERO sections container.
             *
             * Priority:
             *  1. Already-injected .hero-sections-container  (via the_content filter)
             *  2. Create a new one and inject it before the page footer
             *
             * This makes the preview work even when Elementor or the active theme
             * bypasses the WordPress the_content filter entirely.
             */
            function getSectionsContainer() {
                var c = document.querySelector('.hero-sections-container');
                if (c) return c;

                // Create a fresh container and inject it at a sensible location.
                c = document.createElement('div');
                c.className = 'hero-sections-container';
                c.style.cssText = 'position:relative;z-index:1;';

                // Try to inject before site footer; fall back to end of body.
                var anchor = document.querySelector(
                    'footer, #footer, .footer, .site-footer, [class*="footer"]'
                );
                if (anchor && anchor.parentNode) {
                    anchor.parentNode.insertBefore(c, anchor);
                } else {
                    document.body.appendChild(c);
                }
                return c;
            }

            /**
             * Root element for live section updates for a builder context.
             * Page → .hero-sections-container; header/footer → #hero-header / #hero-footer.
             * Global / ai-settings → null (only global CSS updates, no section DOM sync).
             *
             * @param {string} context  From postMessage (preferred) or PREVIEW_CONTEXT from URL.
             */
            function getPreviewScopeRootFor(context) {
                var c = context || PREVIEW_CONTEXT;
                if (c === 'page') {
                    return getSectionsContainer();
                }
                if (c === 'header') {
                    var h = document.getElementById('hero-header');
                    if (!h) {
                        h = document.createElement('header');
                        h.id = 'hero-header';
                        h.className = 'hero-header';
                        if (document.body.firstChild) {
                            document.body.insertBefore(h, document.body.firstChild);
                        } else {
                            document.body.appendChild(h);
                        }
                    }
                    return h;
                }
                if (c === 'footer') {
                    var f = document.getElementById('hero-footer');
                    if (!f) {
                        f = document.createElement('footer');
                        f.id = 'hero-footer';
                        f.className = 'hero-footer';
                        document.body.appendChild(f);
                    }
                    return f;
                }
                if (c === 'global' || c === 'ai-settings') {
                    return null;
                }
                return getSectionsContainer();
            }

            function getInsertContainerFor(context) {
                var root = getPreviewScopeRootFor(context);
                return root || getSectionsContainer();
            }

            function dispatchSectionMounted(sectionId) {
                var el = document.getElementById('hero-section-' + sectionId);
                if (!el) return;
                try {
                    var ev = new CustomEvent('hero:section-mounted', {
                        bubbles: true,
                        detail: { sectionId: sectionId, root: el }
                    });
                    document.dispatchEvent(ev);
                } catch (e) {
                    /* IE / very old engines — ignore */
                }
            }

            function applySection(sectionId, html, context) {
                var existing = document.getElementById('hero-section-' + sectionId);
                if (existing) {
                    var tmp = document.createElement('div');
                    tmp.innerHTML = html;
                    var newNode = tmp.firstElementChild;
                    if (newNode) {
                        existing.replaceWith(newNode);
                    }
                } else {
                    var container = getInsertContainerFor(context);
                    if (container.classList && container.classList.contains('hero-sections-container')) {
                        container.classList.remove('hero-sections-container--empty');
                    }
                    container.insertAdjacentHTML('beforeend', html);
                }
            }

            /**
             * Remove a section from the DOM.
             */
            function removeSection(sectionId) {
                var el = document.getElementById('hero-section-' + sectionId);
                if (el) el.remove();
            }

            /**
             * Reorder sections inside the preview scope root.
             */
            function reorderSections(orderedIds, scopeRoot) {
                if (!scopeRoot) return;
                orderedIds.forEach(function(id) {
                    var el = document.getElementById('hero-section-' + id);
                    if (el && scopeRoot.contains(el)) {
                        scopeRoot.appendChild(el);
                    }
                });
            }

            // Track which section asset URLs have already been injected by the bridge.
            var injectedAssets = new Set();

            /**
             * Inject a <link> or <script> tag once per URL.
             * Also checks if the asset is already present in the DOM (loaded on initial
             * page load by WP's enqueue system) to prevent double-execution of scripts.
             */
            function injectAsset(url, type) {
                if (!url) return;
                // Strip query-string for DOM presence check (WP may use ?ver= while REST uses ?v=).
                var urlBase = url.split('?')[0];
                if (type === 'style') {
                    if (injectedAssets.has(urlBase)) return;
                    // Don't inject if a link with the same base URL is already in the DOM.
                    if (document.querySelector('link[rel="stylesheet"][href^="' + urlBase + '"]')) {
                        injectedAssets.add(urlBase);
                        return;
                    }
                    injectedAssets.add(urlBase);
                    var link = document.createElement('link');
                    link.rel  = 'stylesheet';
                    link.href = url;
                    document.head.appendChild(link);
                } else {
                    if (injectedAssets.has(urlBase)) return;
                    // Don't re-inject a script already present — would cause double-execution.
                    if (document.querySelector('script[src^="' + urlBase + '"]')) {
                        injectedAssets.add(urlBase);
                        return;
                    }
                    injectedAssets.add(urlBase);
                    var script = document.createElement('script');
                    script.src = url;
                    document.body.appendChild(script);
                }
            }

            function injectInlineStyle(id, css) {
                if (!css) return;
                id = id || 'hero-section-scoped-css';
                var style = document.getElementById(id);
                if (!style) {
                    style = document.createElement('style');
                    style.id = id;
                    document.head.appendChild(style);
                }
                style.textContent = css;
            }

            /**
             * Fetch rendered HTML for a section instance from the PHP renderer.
             */
            function fetchSectionHtml(instance, callback) {
                fetch(REST_URL + '/render-section', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-WP-Nonce': NONCE
                    },
                    body: JSON.stringify({ instance: instance })
                })
                .then(function(r) { return r.json(); })
                .then(function(data) {
                    // Inject section CSS / JS if returned and not yet loaded.
                    if (data.assets) {
                        injectInlineStyle(data.assets.style_id, data.assets.style_css);
                        injectAsset(data.assets.style_url,  'style');
                        injectAsset(data.assets.script_url, 'script');
                    }
                    if (data.html !== undefined) callback(data.html);
                })
                .catch(function(e) { console.error('[HERO Preview] render error', e); });
            }

            /**
             * Update global CSS custom properties live — no page reload needed.
             */
            function applyGlobalSettings(globalSettings) {
                fetch(REST_URL + '/global-settings/css', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-WP-Nonce': NONCE
                    },
                    body: JSON.stringify({ settings: globalSettings })
                })
                .then(function(r) { return r.json(); })
                .then(function(data) {
                    if (!data.css) return;
                    var style = document.getElementById('hero-live-global');
                    if (!style) {
                        style = document.createElement('style');
                        style.id = 'hero-live-global';
                        document.head.appendChild(style);
                    }
                    style.textContent = data.css;
                });
            }

            // ── Message listener ────────────────────────────────────────────

            window.addEventListener('message', function(event) {
                // Only accept messages from the same origin (same WP install).
                if (event.origin !== window.location.origin) return;

                var msg = event.data;
                if (!msg || msg.type !== 'HERO_UPDATE') return;

                var activeContext = (msg.context && String(msg.context)) || PREVIEW_CONTEXT;

                if (HERO_PREVIEW_DEBUG) {
                    console.log('[HERO Preview] HERO_UPDATE', activeContext, msg.sections && msg.sections.length, msg.globalSettings ? 'globalSettings' : '');
                }

                // Global CSS can be updated even when there are no section instances.
                if (msg.globalSettings) {
                    applyGlobalSettings(msg.globalSettings);
                }

                // Only sync section DOM for contexts that edit sections in the iframe.
                if (activeContext === 'global' || activeContext === 'ai-settings') {
                    return;
                }

                var scopeRoot = getPreviewScopeRootFor(activeContext);
                if (!scopeRoot) {
                    return;
                }

                var instances = msg.sections || [];
                var orderedIds = instances.map(function(s) { return s.id; });

                // Sections currently in this scope only (do not touch header/footer when editing page).
                var currentSections = scopeRoot.querySelectorAll('[id^="hero-section-"]');
                var currentIds = Array.from(currentSections).map(function(el) {
                    return el.id.replace('hero-section-', '');
                });

                if (instances.length === 0) {
                    currentIds.forEach(function(id) {
                        removeSection(id);
                    });
                    if (scopeRoot.classList && scopeRoot.classList.contains('hero-sections-container')) {
                        scopeRoot.classList.add('hero-sections-container--empty');
                    }
                    return;
                }

                // Remove sections that were deleted within this scope.
                currentIds.forEach(function(id) {
                    if (orderedIds.indexOf(id) === -1) {
                        removeSection(id);
                    }
                });

                var pending = instances.length;
                instances.forEach(function(instance) {
                    fetchSectionHtml(instance, function(html) {
                        applySection(instance.id, html, activeContext);
                        pending--;
                        if (pending === 0) {
                            reorderSections(orderedIds, scopeRoot);
                            orderedIds.forEach(function(sid) {
                                dispatchSectionMounted(sid);
                            });
                            // Re-attach drag handles after DOM update
                            if (typeof addDragHandles === 'function') addDragHandles();
                        }
                    });
                });
            });

            // ── Click-to-select ───────────────────────────────────────────────

            function setSelectedHighlight(id) {
                document.querySelectorAll('.hero-section').forEach(function(el) {
                    el.classList.remove('hero-section--selected');
                });
                if (id) {
                    var el = document.getElementById('hero-section-' + id);
                    if (el) el.classList.add('hero-section--selected');
                }
            }

            // Capture-phase click listener: tells parent which section was clicked.
            document.addEventListener('click', function(event) {
                var el = event.target;
                // Don't interfere with drag handle
                if (el.closest && el.closest('.hero-drag-handle')) return;
                while (el && el !== document.body) {
                    if (el.classList && el.classList.contains('hero-section')) {
                        var sid = el.id.replace('hero-section-', '');
                        window.parent.postMessage({ type: 'HERO_SECTION_CLICK', sectionId: sid }, window.location.origin);
                        break;
                    }
                    el = el.parentElement;
                }
            }, true);

            // ── Drag-to-reorder ───────────────────────────────────────────────

            var _drag = null;

            function addDragHandles() {
                var scopeRoot = getPreviewScopeRootFor(PREVIEW_CONTEXT);
                if (!scopeRoot) return;
                scopeRoot.querySelectorAll('.hero-section').forEach(function(section) {
                    if (section.querySelector('.hero-drag-handle')) return;
                    var h = document.createElement('div');
                    h.className = 'hero-drag-handle';
                    h.title = 'Drag to reorder';
                    h.innerHTML = '<svg width="14" height="14" viewBox="0 0 14 14" fill="none"><circle cx="4.5" cy="3.5" r="1.5" fill="#fff"/><circle cx="9.5" cy="3.5" r="1.5" fill="#fff"/><circle cx="4.5" cy="7" r="1.5" fill="#fff"/><circle cx="9.5" cy="7" r="1.5" fill="#fff"/><circle cx="4.5" cy="10.5" r="1.5" fill="#fff"/><circle cx="9.5" cy="10.5" r="1.5" fill="#fff"/></svg>';
                    h.addEventListener('mousedown', onDragStart);
                    section.prepend(h);
                });
            }

            function onDragStart(event) {
                event.preventDefault();
                event.stopPropagation();
                var section = event.currentTarget.parentElement;
                var scopeRoot = getPreviewScopeRootFor(PREVIEW_CONTEXT);
                if (!scopeRoot) return;

                var rect = section.getBoundingClientRect();
                var ph = document.createElement('div');
                ph.className = 'hero-drag-placeholder';
                ph.style.height = rect.height + 'px';

                _drag = {
                    el: section,
                    placeholder: ph,
                    scopeRoot: scopeRoot,
                    startY: event.clientY,
                    origTop: rect.top,
                };

                section.classList.add('hero-dragging');
                section.parentNode.insertBefore(ph, section.nextSibling);
                section.style.cssText = [
                    'position:fixed',
                    'z-index:9998',
                    'width:' + rect.width + 'px',
                    'top:' + rect.top + 'px',
                    'left:' + rect.left + 'px',
                    'opacity:0.92',
                    'pointer-events:none',
                    'margin:0',
                ].join(';');

                document.addEventListener('mousemove', onDragMove);
                document.addEventListener('mouseup', onDragEnd);
            }

            function onDragMove(event) {
                if (!_drag) return;
                var dy = event.clientY - _drag.startY;
                _drag.startY = event.clientY;
                var cur = parseFloat(_drag.el.style.top) || _drag.origTop;
                _drag.el.style.top = (cur + dy) + 'px';

                var sections = Array.from(_drag.scopeRoot.querySelectorAll('.hero-section:not(.hero-dragging)'));
                var insertBefore = null;
                for (var i = 0; i < sections.length; i++) {
                    var r = sections[i].getBoundingClientRect();
                    if (event.clientY < r.top + r.height / 2) {
                        insertBefore = sections[i];
                        break;
                    }
                }
                if (insertBefore) {
                    _drag.scopeRoot.insertBefore(_drag.placeholder, insertBefore);
                } else {
                    _drag.scopeRoot.appendChild(_drag.placeholder);
                }
            }

            function onDragEnd() {
                if (!_drag) return;
                document.removeEventListener('mousemove', onDragMove);
                document.removeEventListener('mouseup', onDragEnd);

                _drag.scopeRoot.insertBefore(_drag.el, _drag.placeholder);
                _drag.placeholder.remove();
                _drag.el.style.cssText = '';
                _drag.el.classList.remove('hero-dragging');

                var newOrder = Array.from(_drag.scopeRoot.querySelectorAll('.hero-section')).map(function(el) {
                    return el.id.replace('hero-section-', '');
                });
                window.parent.postMessage({ type: 'HERO_REORDER', orderedIds: newOrder }, window.location.origin);
                _drag = null;
            }

            // ── Incoming messages from parent ─────────────────────────────────

            window.addEventListener('message', function(event) {
                if (event.origin !== window.location.origin) return;
                var msg = event.data;
                if (!msg || !msg.type) return;

                if (msg.type === 'HERO_SELECT_SECTION') {
                    setSelectedHighlight(msg.sectionId || null);
                }

                if (msg.type === 'HERO_DRAFT_PREVIEW' && msg.sectionType) {
                    var cls = 'hero-section--' + msg.sectionType;
                    document.querySelectorAll('.hero-section').forEach(function(wrapper) {
                        if (wrapper.classList.contains(cls)) {
                            wrapper.innerHTML = msg.html;
                            addDragHandles(); // re-attach handles after DOM replace
                        }
                    });
                }
            });

            // Signal to the parent editor that the preview is ready.
            window.parent.postMessage({ type: 'HERO_PREVIEW_READY' }, window.location.origin);

            // Add drag handles once DOM is ready (sections already in DOM from initial load)
            if (document.readyState === 'loading') {
                document.addEventListener('DOMContentLoaded', addDragHandles);
            } else {
                addDragHandles();
            }
        })();
        </script>
        <?php
    }
}
