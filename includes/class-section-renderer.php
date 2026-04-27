<?php
/**
 * HERO Section Renderer
 *
 * Responsible for turning stored section-instance data into safe HTML output.
 * All variables exposed to section.php / block.php templates are sanitised here.
 * No executable code is ever loaded from the database.
 */

defined( 'ABSPATH' ) || exit;

class Hero_Section_Renderer {

    private Hero_Section_Registry $section_registry;
    private Hero_Block_Registry   $block_registry;

    public function __construct(
        Hero_Section_Registry $section_registry,
        Hero_Block_Registry   $block_registry
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
        $wrapper_class = 'hero-section hero-section--' . esc_attr( $type );
        $output        = sprintf(
            '<div id="hero-section-%s" class="%s">',
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
     * Render a section from raw section.php source (editor draft) using the registered schema.
     * Writes a temp file, includes it, then deletes the file.
     *
     * @param array $instance_for_preview Keys: id, settings, blocks, enabled, custom_css (optional).
     */
    public function render_draft_from_section_php( string $type, string $section_php, array $instance_for_preview ): string {
        $schema = $this->section_registry->get_section( $type );
        if ( ! $schema ) {
            return '';
        }
        $trimmed = trim( $section_php );
        if ( $trimmed === '' ) {
            return '';
        }

        $section_id = isset( $instance_for_preview['id'] ) && is_string( $instance_for_preview['id'] ) && $instance_for_preview['id'] !== ''
            ? preg_replace( '/[^a-zA-Z0-9_\-]/', '', $instance_for_preview['id'] )
            : 'fp-sm-live';
        if ( $section_id === '' ) {
            $section_id = 'fp-sm-live';
        }

        $settings = $this->merge_defaults( $schema['settings'] ?? [], $instance_for_preview['settings'] ?? [] );
        $blocks   = $this->prepare_blocks( $instance_for_preview['blocks'] ?? [], $schema );
        $section  = [
            'id'     => $section_id,
            'type'   => $type,
            'source' => $schema['source'] ?? 'plugin',
        ];

        $upload = wp_upload_dir();
        if ( $upload['error'] ) {
            $dir = trailingslashit( sys_get_temp_dir() );
        } else {
            $dir = trailingslashit( $upload['basedir'] ) . 'hero/section-draft-preview/' . (string) get_current_user_id() . '/';
        }
        if ( ! wp_mkdir_p( $dir ) ) {
            $dir = trailingslashit( sys_get_temp_dir() );
        }

        $tmp = $dir . 'draft-' . wp_hash( (string) microtime( true ) . $trimmed . (string) wp_rand() ) . '.php';
        if ( ! file_put_contents( $tmp, $section_php ) ) {
            return '';
        }

        try {
            $html = $this->load_template( $tmp, $settings, $blocks, $section );
        } finally {
            if ( is_file( $tmp ) ) {
                unlink( $tmp );
            }
        }

        $wrapper_class = 'hero-section hero-section--' . esc_attr( $type );
        $output        = sprintf(
            '<div id="hero-section-%s" class="%s">',
            esc_attr( $section_id ),
            $wrapper_class
        );

        $custom_css = trim( (string) ( $instance_for_preview['custom_css'] ?? '' ) );
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
     * Replace the_content for HERO-managed pages.
     * Hooked to `the_content`.
     */
    public function filter_page_content( string $content ): string {
        if ( ! is_singular() ) {
            return $content;
        }

        $is_preview = (bool) apply_filters( 'hero_is_preview', false );
        $post_id    = get_the_ID();
        $raw        = get_post_meta( $post_id, '_hero_sections', true );

        if ( empty( $raw ) ) {
            // In preview mode inject an empty container so the JS bridge
            // has a reliable anchor point for newly added sections.
            if ( $is_preview ) {
                return '<div class="hero-sections-container hero-sections-container--empty"></div>';
            }
            return $content;
        }

        $instances = json_decode( $raw, true );
        if ( ! is_array( $instances ) ) {
            return $is_preview
                ? '<div class="hero-sections-container hero-sections-container--empty"></div>'
                : $content;
        }

        $html = $this->render_sections( $instances );

        // Always wrap in named container so the preview bridge can find it.
        return '<div class="hero-sections-container">' . $html . '</div>';
    }

    /**
     * Output header sections.
     * Hooked to `wp_body_open`.
     */
    public function output_header_sections(): void {
        $raw = get_option( 'hero_header', '' );
        if ( empty( $raw ) ) {
            return;
        }
        $instances = json_decode( $raw, true );
        if ( ! is_array( $instances ) ) {
            return;
        }
        echo '<header id="hero-header" class="hero-header">';
        echo $this->render_sections( $instances ); // phpcs:ignore WordPress.Security.EscapeOutput
        echo '</header>';
    }

    /**
     * Output footer sections.
     * Hooked to `wp_footer`.
     */
    public function output_footer_sections(): void {
        $raw = get_option( 'hero_footer', '' );
        if ( empty( $raw ) ) {
            return;
        }
        $instances = json_decode( $raw, true );
        if ( ! is_array( $instances ) ) {
            return;
        }
        echo '<footer id="hero-footer" class="hero-footer">';
        echo $this->render_sections( $instances ); // phpcs:ignore WordPress.Security.EscapeOutput
        echo '</footer>';
    }

    /**
     * Collect all active section types used on the current page
     * (page + header + footer combined).
     *
     * Used by Hero_Section_Assets to enqueue only needed assets.
     *
     * @return string[] Array of section type slugs.
     */
    public function get_active_section_types(): array {
        $types = [];

        // Page sections.
        if ( is_singular() ) {
            $raw = get_post_meta( get_the_ID(), '_hero_sections', true );
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
        $raw = get_option( 'hero_header', '' );
        if ( $raw ) {
            $instances = json_decode( $raw, true );
            if ( is_array( $instances ) ) {
                foreach ( $instances as $inst ) {
                    $types[] = $inst['type'] ?? '';
                }
            }
        }

        // Footer sections.
        $raw = get_option( 'hero_footer', '' );
        if ( $raw ) {
            $instances = json_decode( $raw, true );
            if ( is_array( $instances ) ) {
                foreach ( $instances as $inst ) {
                    $types[] = $inst['type'] ?? '';
                }
            }
        }

        /**
         * Allow integrations (e.g. Elementor) to add section types used on this request
         * so assets enqueue during `wp_enqueue_scripts` — before `wp_head` prints styles.
         *
         * @param string[] $types Section type slugs.
         */
        return array_unique( array_filter( apply_filters( 'hero_active_section_types', $types ) ) );
    }

    // ─── Internal helpers ─────────────────────────────────────────────────────

    /**
     * Load a section or block template in a fully isolated variable scope.
     * Only $settings, $blocks, and $section are visible inside the template.
     */
    private function load_template( string $file, array $settings, array $blocks, array $section ): string {
        $render = static function ( string $__file, array $settings, array $blocks, array $section ): string {
            ob_start();
            try {
                include $__file;
            } catch ( \Throwable $e ) {
                ob_end_clean();
                // Always show inline error — section errors must be visible so they can be fixed.
                return '<div style="border:2px solid #d63638;padding:12px 16px;margin:8px 0;font-family:monospace;font-size:13px;color:#d63638;background:#fff0f0;direction:ltr;text-align:left">'
                    . '<strong>HERO section error</strong> in <em>' . esc_html( basename( $__file ) ) . '</em>:<br><br>'
                    . esc_html( $e->getMessage() ) . '<br>'
                    . '<small style="color:#999">Line ' . esc_html( (string) $e->getLine() ) . ' · ' . esc_html( $e->getFile() ) . '</small>'
                    . '</div>';
            }
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
     * Strips </style> injection attempts.
     */
    public function scope_css( string $css, string $section_id ): string {
        return $this->scope_css_to_selector( $css, '#hero-section-' . $section_id );
    }

    /**
     * Scope CSS rules to a wrapper selector (for example `.hero-section--faq`).
     *
     * This keeps per-section style.css rules inside the section wrapper and
     * increases specificity enough to beat broad theme reset rules such as
     * `input[type=text]`.
     */
    public function scope_css_to_selector( string $css, string $scope_selector ): string {
        $css            = str_ireplace( '</style>', '', $css );
        $scope_selector = trim( $scope_selector );

        if ( $css === '' || $scope_selector === '' ) {
            return $css;
        }

        return $this->scope_css_blocks( $css, $scope_selector );
    }

    private function scope_css_blocks( string $css, string $scope_selector ): string {
        $out    = '';
        $offset = 0;
        $len    = strlen( $css );

        while ( $offset < $len ) {
            $open = strpos( $css, '{', $offset );
            if ( $open === false ) {
                $out .= substr( $css, $offset );
                break;
            }

            $close = $this->find_matching_css_brace( $css, $open );
            if ( $close === null ) {
                $out .= substr( $css, $offset );
                break;
            }

            $prelude = substr( $css, $offset, $open - $offset );
            $body    = substr( $css, $open + 1, $close - $open - 1 );
            [ $leading, $selector ] = $this->split_css_leading_noise( $prelude );
            $trimmed_selector       = trim( $selector );

            if ( $trimmed_selector !== '' && str_starts_with( $trimmed_selector, '@' ) ) {
                $at_rule = strtolower( strtok( substr( $trimmed_selector, 1 ), " \t\r\n(" ) ?: '' );
                if ( in_array( $at_rule, [ 'media', 'supports', 'container', 'layer' ], true ) ) {
                    $body = $this->scope_css_blocks( $body, $scope_selector );
                }
                $out .= $leading . $selector . '{' . $body . '}';
            } elseif ( $trimmed_selector !== '' ) {
                $out .= $leading . $this->scope_selector_list( $selector, $scope_selector ) . '{' . $body . '}';
            } else {
                $out .= $prelude . '{' . $body . '}';
            }

            $offset = $close + 1;
        }

        return $out;
    }

    private function find_matching_css_brace( string $css, int $open ): ?int {
        $depth = 0;
        $len   = strlen( $css );

        for ( $i = $open; $i < $len; $i++ ) {
            if ( substr( $css, $i, 2 ) === '/*' ) {
                $comment_end = strpos( $css, '*/', $i + 2 );
                if ( $comment_end === false ) {
                    return null;
                }
                $i = $comment_end + 1;
                continue;
            }

            $char = $css[ $i ];
            if ( $char === '"' || $char === "'" ) {
                $quote = $char;
                for ( $i++; $i < $len; $i++ ) {
                    if ( $css[ $i ] === '\\' ) {
                        $i++;
                        continue;
                    }
                    if ( $css[ $i ] === $quote ) {
                        break;
                    }
                }
                continue;
            }

            if ( $char === '{' ) {
                $depth++;
            } elseif ( $char === '}' ) {
                $depth--;
                if ( $depth === 0 ) {
                    return $i;
                }
            }
        }

        return null;
    }

    /**
     * Keep whitespace/comments before a selector untouched.
     *
     * @return array{0:string,1:string}
     */
    private function split_css_leading_noise( string $prelude ): array {
        $offset = 0;
        $len    = strlen( $prelude );

        while ( $offset < $len ) {
            if ( preg_match( '/\G\s+/A', $prelude, $m, 0, $offset ) ) {
                $offset += strlen( $m[0] );
                continue;
            }

            if ( substr( $prelude, $offset, 2 ) === '/*' ) {
                $comment_end = strpos( $prelude, '*/', $offset + 2 );
                if ( $comment_end === false ) {
                    break;
                }
                $offset = $comment_end + 2;
                continue;
            }

            break;
        }

        return [ substr( $prelude, 0, $offset ), substr( $prelude, $offset ) ];
    }

    private function scope_selector_list( string $selector_list, string $scope_selector ): string {
        $selectors = $this->split_css_selector_list( $selector_list );
        $scoped    = array_map(
            fn ( string $selector ): string => $this->scope_single_selector( $selector, $scope_selector ),
            $selectors
        );

        return implode( ', ', $scoped );
    }

    /**
     * Split selector lists without breaking commas inside :is(), :not(), etc.
     *
     * @return string[]
     */
    private function split_css_selector_list( string $selector_list ): array {
        $parts       = [];
        $current     = '';
        $paren_depth = 0;
        $bracket_depth = 0;
        $quote       = null;
        $len         = strlen( $selector_list );

        for ( $i = 0; $i < $len; $i++ ) {
            $char = $selector_list[ $i ];

            if ( $quote !== null ) {
                $current .= $char;
                if ( $char === '\\' && $i + 1 < $len ) {
                    $current .= $selector_list[ ++$i ];
                    continue;
                }
                if ( $char === $quote ) {
                    $quote = null;
                }
                continue;
            }

            if ( $char === '"' || $char === "'" ) {
                $quote = $char;
                $current .= $char;
                continue;
            }

            if ( $char === '(' ) {
                $paren_depth++;
            } elseif ( $char === ')' && $paren_depth > 0 ) {
                $paren_depth--;
            } elseif ( $char === '[' ) {
                $bracket_depth++;
            } elseif ( $char === ']' && $bracket_depth > 0 ) {
                $bracket_depth--;
            }

            if ( $char === ',' && $paren_depth === 0 && $bracket_depth === 0 ) {
                $parts[] = $current;
                $current = '';
                continue;
            }

            $current .= $char;
        }

        $parts[] = $current;

        return $parts;
    }

    private function scope_single_selector( string $selector, string $scope_selector ): string {
        $leading = '';
        $trailing = '';

        if ( preg_match( '/^(\s*)(.*?)(\s*)$/s', $selector, $m ) ) {
            $leading  = $m[1];
            $selector = $m[2];
            $trailing = $m[3];
        }

        if ( $selector === '' || str_starts_with( $selector, $scope_selector ) ) {
            return $leading . $selector . $trailing;
        }

        if ( $selector === ':root' || $selector === 'html' || $selector === 'body' ) {
            return $leading . $scope_selector . $trailing;
        }

        if ( str_starts_with( $selector, '&' ) ) {
            return $leading . $scope_selector . substr( $selector, 1 ) . $trailing;
        }

        return $leading . $scope_selector . ' ' . $selector . $trailing;
    }
}
