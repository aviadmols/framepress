<?php
/**
 * FramePress Preview
 *
 * Handles the live preview endpoint: /?framepress_preview=1&post_id=X&nonce=Y
 *
 * Renders a real WordPress frontend page (with actual theme, header, footer)
 * but substitutes the page's section data with the draft state supplied via
 * the editor's postMessage bridge.
 *
 * Security:
 * - Nonce validated before any output.
 * - No PHP from POST body — only JSON section data values (rendered by the
 *   normal Section Renderer pipeline which only includes files from disk).
 */

defined( 'ABSPATH' ) || exit;

class FramePress_Preview {

    private FramePress_Section_Renderer $renderer;
    private FramePress_Global_Settings  $global_settings;

    public function __construct(
        FramePress_Section_Renderer $renderer,
        FramePress_Global_Settings  $global_settings
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

        if ( ! $post_id || ! $nonce ) {
            return;
        }

        if ( ! wp_verify_nonce( $nonce, 'framepress_preview_' . $post_id ) ) {
            wp_die( esc_html__( 'Preview link has expired. Please return to the builder and try again.', 'framepress' ), 403 );
        }

        if ( ! current_user_can( 'edit_post', $post_id ) ) {
            wp_die( esc_html__( 'You do not have permission to preview this page.', 'framepress' ), 403 );
        }

        // Override the global query to point at the requested post.
        global $wp_query;
        $post = get_post( $post_id );
        if ( ! $post ) {
            wp_die( esc_html__( 'Post not found.', 'framepress' ) );
        }

        $wp_query->is_preview        = true;
        $wp_query->is_singular       = true;
        $wp_query->is_single         = ( $post->post_type === 'post' );
        $wp_query->is_page           = ( $post->post_type === 'page' );
        $wp_query->queried_object    = $post;
        $wp_query->queried_object_id = $post_id;

        // Inject preview JS listener at end of body so the editor can push
        // live updates via postMessage.
        add_action( 'wp_footer', [ $this, 'output_preview_listener' ], 99 );

        // Mark this as a preview request so the renderer can skip caching.
        add_filter( 'framepress_is_preview', '__return_true' );

        // Proceed with the normal WordPress template loading.
        // The renderer's the_content filter is already hooked — it will
        // use saved sections for the initial load.
    }

    /**
     * Inject the preview postMessage bridge into the preview page footer.
     * This script receives live updates from the builder editor iframe parent.
     */
    public function output_preview_listener(): void {
        $rest_url = esc_js( rest_url( 'framepress/v1' ) );
        $nonce    = esc_js( wp_create_nonce( 'wp_rest' ) );
        ?>
        <script id="framepress-preview-bridge">
        (function() {
            var REST_URL = '<?php echo $rest_url; ?>';
            var NONCE    = '<?php echo $nonce; ?>';

            /**
             * Swap or insert a section's HTML in the DOM.
             */
            function applySection(sectionId, html) {
                var existing = document.getElementById('framepress-section-' + sectionId);
                if (existing) {
                    // Replace innerHTML to preserve wrapper element.
                    var tmp = document.createElement('div');
                    tmp.innerHTML = html;
                    var newNode = tmp.firstElementChild;
                    if (newNode) {
                        existing.replaceWith(newNode);
                    }
                } else {
                    // New section — append to content container.
                    var container = document.querySelector('.framepress-sections-container, main, #main, #content');
                    if (container) {
                        container.insertAdjacentHTML('beforeend', html);
                    }
                }
            }

            /**
             * Remove a section from the DOM.
             */
            function removeSection(sectionId) {
                var el = document.getElementById('framepress-section-' + sectionId);
                if (el) el.remove();
            }

            /**
             * Reorder sections in the DOM to match the given ordered id array.
             */
            function reorderSections(orderedIds) {
                var container = null;
                var first = orderedIds[0] && document.getElementById('framepress-section-' + orderedIds[0]);
                if (first) container = first.parentElement;
                if (!container) return;

                orderedIds.forEach(function(id) {
                    var el = document.getElementById('framepress-section-' + id);
                    if (el) container.appendChild(el);
                });
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
                    if (data.html !== undefined) callback(data.html);
                })
                .catch(function(e) { console.error('[FramePress Preview] render error', e); });
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
                    var style = document.getElementById('framepress-live-global');
                    if (!style) {
                        style = document.createElement('style');
                        style.id = 'framepress-live-global';
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
                if (!msg || msg.type !== 'FRAMEPRESS_UPDATE') return;

                // Update individual sections.
                var instances = msg.sections || [];
                var orderedIds = instances.map(function(s) { return s.id; });

                // Find current rendered sections.
                var currentSections = document.querySelectorAll('[id^="framepress-section-"]');
                var currentIds = Array.from(currentSections).map(function(el) {
                    return el.id.replace('framepress-section-', '');
                });

                // Remove sections that were deleted.
                currentIds.forEach(function(id) {
                    if (!orderedIds.includes(id)) {
                        removeSection(id);
                    }
                });

                // Render/update each instance.
                instances.forEach(function(instance) {
                    fetchSectionHtml(instance, function(html) {
                        applySection(instance.id, html);
                        // After all individual updates, reorder.
                        reorderSections(orderedIds);
                    });
                });

                // Apply global settings if provided.
                if (msg.globalSettings) {
                    applyGlobalSettings(msg.globalSettings);
                }
            });

            // Signal to the parent editor that the preview is ready.
            window.parent.postMessage({ type: 'FRAMEPRESS_PREVIEW_READY' }, window.location.origin);
        })();
        </script>
        <?php
    }
}
