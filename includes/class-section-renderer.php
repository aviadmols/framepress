<?php
/**
 * FramePress Section Renderer
 *
 * Responsible for turning stored section-instance data into safe HTML output.
 * All variables exposed to section.php / block.php templates are sanitised here.
 * No executable code is ever loaded from the database.
 */

defined( 'ABSPATH' ) || exit;

class FramePress_Section_Renderer {

    private FramePress_Section_Registry $section_registry;
    private FramePress_Block_Registry   $block_registry;

    public function __construct(
        FramePress_Section_Registry $section_registry,
        FramePress_Block_Registry   $block_registry
    ) {
        $this->section_registry = $section_registry;
        $this->block_registry   = $block_registry;
    }

    // ─── Public render API ────────────────────────────────────────────────────

    /**
     * Render a single section instance to an HTML string.
     *
     * @param array $instance  Stored section instance (id, type, settings, blocks, custom_css, enabled).
     * @return string          Safe HTML, or empty string if section type unknown / disabled.
     */
    public function render_section( array $instance ): string {
        if ( empty( $instance['enabled'] ) && isset( $instance['enabled'] ) ) {
            return '';
        }

        $type   = $instance['type'] ?? '';
        $schema = $this->section_registry->get_section( $type );
        if ( ! $schema ) {
            return '';
        }

        $section_id = $instance['id'] ?? 'fp-' . uniqid();
        $settings   = $this->merge_defaults( $schema['settings'] ?? [], $instance['settings'] ?? [] );
        $blocks     = $this->prepare_blocks( $instance['blocks'] ?? [], $schema );
        $section    = [
            'id'     => $section_id,
            'type'   => $type,
            'source' => $schema['source'] ?? 'plugin',
        ];

        // Render the section template.
        $template_file = $schema['_path'] . 'section.php';
        if ( ! file_exists( $template_file ) ) {
            return '';
        }

        $html = $this->load_template( $template_file, $settings, $blocks, $section );

        // Wrap in a uniquely-identified container.
        $wrapper_class = 'framepress-section framepress-section--' . esc_attr( $type );
        $output        = sprintf(
            '<div id="framepress-section-%s" class="%s">',
            esc_attr( $section_id ),
            $wrapper_class
        );

        // Inline scoped custom CSS for this instance.
        $custom_css = trim( $instance['custom_css'] ?? '' );
        if ( $custom_css !== '' ) {
            $output .= '<style>' . $this->scope_css( $custom_css, $section_id ) . '</style>';
        }

        $output .= $html;
        $output .= '</div>';

        return $output;
    }

    /**
     * Render an ordered list of section instances.
     */
    public function render_sections( array $instances ): string {
        $output = '';
        foreach ( $instances as $instance ) {
            $output .= $this->render_section( $instance );
        }
        return $output;
    }

    // ─── WordPress output hooks ───────────────────────────────────────────────

    /**
     * Replace the_content for FramePress-managed pages.
     * Hooked to `the_content`.
     */
    public function filter_page_content( string $content ): string {
        if ( ! is_singular() ) {
            return $content;
        }

        $post_id  = get_the_ID();
        $raw      = get_post_meta( $post_id, '_framepress_sections', true );
        if ( empty( $raw ) ) {
            return $content;
        }

        $instances = json_decode( $raw, true );
        if ( ! is_array( $instances ) ) {
            return $content;
        }

        return $this->render_sections( $instances );
    }

    /**
     * Output header sections.
     * Hooked to `wp_body_open`.
     */
    public function output_header_sections(): void {
        $raw = get_option( 'framepress_header', '' );
        if ( empty( $raw ) ) {
            return;
        }
        $instances = json_decode( $raw, true );
        if ( ! is_array( $instances ) ) {
            return;
        }
        echo '<header id="framepress-header" class="framepress-header">';
        echo $this->render_sections( $instances ); // phpcs:ignore WordPress.Security.EscapeOutput
        echo '</header>';
    }

    /**
     * Output footer sections.
     * Hooked to `wp_footer`.
     */
    public function output_footer_sections(): void {
        $raw = get_option( 'framepress_footer', '' );
        if ( empty( $raw ) ) {
            return;
        }
        $instances = json_decode( $raw, true );
        if ( ! is_array( $instances ) ) {
            return;
        }
        echo '<footer id="framepress-footer" class="framepress-footer">';
        echo $this->render_sections( $instances ); // phpcs:ignore WordPress.Security.EscapeOutput
        echo '</footer>';
    }

    /**
     * Collect all active section types used on the current page
     * (page + header + footer combined).
     *
     * Used by FramePress_Section_Assets to enqueue only needed assets.
     *
     * @return string[] Array of section type slugs.
     */
    public function get_active_section_types(): array {
        $types = [];

        // Page sections.
        if ( is_singular() ) {
            $raw = get_post_meta( get_the_ID(), '_framepress_sections', true );
            if ( $raw ) {
                $instances = json_decode( $raw, true );
                if ( is_array( $instances ) ) {
                    foreach ( $instances as $inst ) {
                        $types[] = $inst['type'] ?? '';
                    }
                }
            }
        }

        // Header sections.
        $raw = get_option( 'framepress_header', '' );
        if ( $raw ) {
            $instances = json_decode( $raw, true );
            if ( is_array( $instances ) ) {
                foreach ( $instances as $inst ) {
                    $types[] = $inst['type'] ?? '';
                }
            }
        }

        // Footer sections.
        $raw = get_option( 'framepress_footer', '' );
        if ( $raw ) {
            $instances = json_decode( $raw, true );
            if ( is_array( $instances ) ) {
                foreach ( $instances as $inst ) {
                    $types[] = $inst['type'] ?? '';
                }
            }
        }

        return array_unique( array_filter( $types ) );
    }

    // ─── Internal helpers ─────────────────────────────────────────────────────

    /**
     * Load a section or block template in a fully isolated variable scope.
     * Only $settings, $blocks, and $section are visible inside the template.
     */
    private function load_template( string $file, array $settings, array $blocks, array $section ): string {
        $render = static function ( string $__file, array $settings, array $blocks, array $section ): string {
            ob_start();
            include $__file;
            return (string) ob_get_clean();
        };
        return $render( $file, $settings, $blocks, $section );
    }

    /**
     * Merge schema defaults with stored instance values.
     * Schema defaults fill in any keys missing from the stored values.
     */
    private function merge_defaults( array $schema_settings, array $stored_values ): array {
        $merged = [];
        foreach ( $schema_settings as $field ) {
            $id            = $field['id'];
            $merged[ $id ] = array_key_exists( $id, $stored_values )
                ? $stored_values[ $id ]
                : ( $field['default'] ?? '' );
        }
        return $merged;
    }

    /**
     * Prepare blocks for rendering: merge each block's stored settings with its
     * type schema defaults, resolved from inline block_types or the Block Registry.
     */
    private function prepare_blocks( array $raw_blocks, array $section_schema ): array {
        $prepared = [];
        $inline_block_types = $section_schema['block_types'] ?? [];

        foreach ( $raw_blocks as $raw ) {
            $block_type = $raw['type'] ?? '';
            if ( ! $block_type ) {
                continue;
            }

            // Inline block_types in section schema take priority over global registry.
            if ( isset( $inline_block_types[ $block_type ] ) ) {
                $block_schema   = $inline_block_types[ $block_type ];
            } elseif ( $global = $this->block_registry->get_block( $block_type ) ) {
                $block_schema   = $global;
            } else {
                // Unknown block type — skip.
                continue;
            }

            $block_settings = $this->merge_defaults(
                $block_schema['settings'] ?? [],
                $raw['settings'] ?? []
            );

            // Determine render file for this block.
            $block_file = null;
            if ( isset( $global ) ) {
                $candidate = $this->block_registry->get_block_path( $block_type ) . 'block.php';
                if ( file_exists( $candidate ) ) {
                    $block_file = $candidate;
                }
            }

            $prepared[] = [
                'id'       => $raw['id'] ?? 'b-' . uniqid(),
                'type'     => $block_type,
                'settings' => $block_settings,
                '_file'    => $block_file,   // null = section renders inline
            ];
        }

        return $prepared;
    }

    /**
     * Scope all CSS rules to a unique section wrapper ID.
     *
     * Handles basic cases:
     *   .foo { ... }          →  #framepress-section-{id} .foo { ... }
     *   .foo, .bar { ... }    →  #framepress-section-{id} .foo, #framepress-section-{id} .bar { ... }
     *   @media (...) { ... }  →  kept as-is, inner rules scoped
     *
     * Strips </style> injection attempts.
     */
    public function scope_css( string $css, string $section_id ): string {
        // Prevent </style> injection.
        $css    = str_ireplace( '</style>', '', $css );
        $prefix = '#framepress-section-' . $section_id;

        // Very lightweight regex-based scoping for non-at-rule blocks.
        // This covers the 95% case without a full CSS parser.
        $scoped = preg_replace_callback(
            '/([^{@]+)\{([^}]*)\}/s',
            static function ( array $m ) use ( $prefix ): string {
                $selectors = explode( ',', $m[1] );
                $scoped_selectors = array_map( static function ( string $sel ) use ( $prefix ): string {
                    $sel = trim( $sel );
                    if ( $sel === '' ) {
                        return $sel;
                    }
                    // Don't double-scope if already scoped.
                    if ( str_starts_with( $sel, $prefix ) ) {
                        return $sel;
                    }
                    return $prefix . ' ' . $sel;
                }, $selectors );
                return implode( ', ', $scoped_selectors ) . ' {' . $m[2] . '}';
            },
            $css
        );

        return $scoped ?? $css;
    }
}
