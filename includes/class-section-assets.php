<?php
/**
 * HERO Section Assets
 *
 * Discovers and enqueues per-section style.css and script.js files.
 * Only loads assets for section types that are actually used on the current page.
 * Version = filemtime() for automatic cache-busting on file change.
 */

defined( 'ABSPATH' ) || exit;

class Hero_Section_Assets {

    private Hero_Section_Registry $registry;
    private Hero_Section_Renderer $renderer;

    public function __construct(
        Hero_Section_Registry $registry,
        Hero_Section_Renderer $renderer
    ) {
        $this->registry = $registry;
        $this->renderer = $renderer;
    }

    /**
     * Enqueue style.css and script.js for every section type active on this page.
     * Hooked to wp_enqueue_scripts.
     */
    public function enqueue_section_assets(): void {
        // Also enqueue the base frontend stylesheet.
        $base_css = HERO_DIR . 'assets/frontend/hero.css';
        if ( file_exists( $base_css ) ) {
            wp_enqueue_style(
                'hero-frontend',
                HERO_URL . 'assets/frontend/hero.css',
                [],
                filemtime( $base_css )
            );
        }

        $active_types = $this->renderer->get_active_section_types();

        foreach ( $active_types as $type ) {
            $this->enqueue_one_section_type( $type );
        }
    }

    /**
     * Enqueue style/script for a single section type (e.g. Elementor widget output).
     *
     * Section CSS is scoped to the section wrapper class before output. That
     * keeps section files from leaking globally and gives them enough
     * specificity to beat broad theme reset rules such as `input[type=text]`.
     * A schema may still declare `'css_priority' => 'after-everything'` to
     * emit the scoped CSS at the very end of wp_head.
     */
    public function enqueue_one_section_type( string $type ): void {
        $schema = $this->registry->get_section( $type );
        if ( ! $schema ) {
            return;
        }

        if ( ! wp_style_is( 'hero-frontend', 'enqueued' ) ) {
            $base_css = HERO_DIR . 'assets/frontend/hero.css';
            if ( file_exists( $base_css ) ) {
                wp_enqueue_style(
                    'hero-frontend',
                    HERO_URL . 'assets/frontend/hero.css',
                    [],
                    filemtime( $base_css )
                );
            }
        }

        $section_path = $schema['_path'];
        $section_url  = $this->path_to_url( $section_path, $schema['_source'] ?? 'plugin' );

        $css_file = $section_path . 'style.css';
        if ( file_exists( $css_file ) ) {
            $css_priority = (string) ( $schema['css_priority'] ?? '' );

            $scoped_css = $this->get_scoped_section_css( $type, $css_file );

            if ( $scoped_css === '' ) {
                // Keep going so script.js can still be enqueued for this section.
            } elseif ( $css_priority === 'after-everything' ) {
                $this->emit_late_section_css( $type, $scoped_css );
            } else {
                $deps = [ 'hero-frontend' ];
                $theme_handle = $this->detect_theme_style_handle();
                if ( $theme_handle && $theme_handle !== 'hero-frontend' ) {
                    $deps[] = $theme_handle;
                }

                $handle = 'hero-section-' . $type;
                wp_register_style(
                    $handle,
                    false,
                    $deps,
                    (string) filemtime( $css_file )
                );
                wp_enqueue_style( $handle );
                wp_add_inline_style(
                    $handle,
                    $scoped_css
                );
            }
        }

        $js_file = $section_path . 'script.js';
        if ( file_exists( $js_file ) ) {
            wp_enqueue_script(
                'hero-section-' . $type,
                $section_url . 'script.js',
                [],
                (string) filemtime( $js_file ),
                true
            );
        }
    }

    public function get_scoped_section_css( string $type, string $css_file ): string {
        if ( ! file_exists( $css_file ) ) {
            return '';
        }

        $css = (string) file_get_contents( $css_file );
        if ( trim( $css ) === '' ) {
            return '';
        }

        return $this->renderer->scope_css_to_selector(
            $css,
            '.hero-section--' . sanitize_html_class( $type )
        );
    }

    /**
     * Resolve the public URL for a section script.js file.
     */
    public function get_section_script_url( string $type ): ?string {
        $schema = $this->registry->get_section( $type );
        if ( ! $schema ) {
            return null;
        }

        $section_path = trailingslashit( $schema['_path'] );
        $script_file  = $section_path . 'script.js';
        if ( ! file_exists( $script_file ) ) {
            return null;
        }

        $section_url = $this->path_to_url( $section_path, $schema['_source'] ?? 'plugin' );

        return $section_url . 'script.js?v=' . filemtime( $script_file );
    }

    /**
     * Resolve scoped CSS and script URLs for preview REST responses.
     */
    public function get_section_preview_assets( string $type ): array {
        $schema = $this->registry->get_section( $type );
        if ( ! $schema ) {
            return [
                'style_css'  => '',
                'style_id'   => '',
                'script_url' => null,
            ];
        }

        $section_path = trailingslashit( $schema['_path'] );
        $css_file     = $section_path . 'style.css';

        return [
            'style_css'  => $this->get_scoped_section_css( $type, $css_file ),
            'style_id'   => 'hero-section-' . sanitize_key( $type ) . '-scoped-css',
            'script_url' => $this->get_section_script_url( $type ),
        ];
    }

    /**
     * Detect the active theme's enqueued stylesheet handle so we can declare
     * it as a dependency for section CSS. Most themes register a handle that
     * matches `get_stylesheet()` (the active/child theme slug) or
     * `get_template()` (parent theme slug). We probe both plus the theme's
     * TextDomain. Returns null when nothing matches — callers should treat
     * that as "no dep" rather than hard-fail.
     */
    private function detect_theme_style_handle(): ?string {
        $candidates = [
            (string) get_stylesheet(),
            (string) get_template(),
            (string) ( wp_get_theme()->get( 'TextDomain' ) ?: '' ),
        ];
        foreach ( $candidates as $handle ) {
            if ( $handle !== '' && wp_style_is( $handle, 'enqueued' ) ) {
                return $handle;
            }
        }
        return null;
    }

    /**
     * Emit scoped CSS at the very end of wp_head so the section styles land
     * after every other enqueued stylesheet on the page.
     *
     * Triggered by setting `'css_priority' => 'after-everything'` on a
     * section's schema.
     */
    private function emit_late_section_css( string $type, string $scoped_css ): void {
        add_action( 'wp_head', static function () use ( $type, $scoped_css ): void {
            printf(
                '<style id="hero-section-%s-late-css">%s</style>' . "\n",
                esc_attr( $type ),
                $scoped_css // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- CSS is sanitized by scoping and loaded from trusted section files.
            );
        }, 9999 );
    }

    /**
     * Convert an absolute filesystem path to a public URL based on section source.
     */
    private function path_to_url( string $path, string $source ): string {
        $path = trailingslashit( $path );

        switch ( $source ) {
            case 'theme':
                $theme_dir = trailingslashit( get_stylesheet_directory() );
                $theme_url = trailingslashit( get_stylesheet_directory_uri() );
                return str_replace( $theme_dir, $theme_url, $path );

            case 'uploads':
                $upload     = wp_upload_dir();
                $upload_dir = trailingslashit( $upload['basedir'] );
                $upload_url = trailingslashit( $upload['baseurl'] );
                return str_replace( $upload_dir, $upload_url, $path );

            default: // 'plugin'
                return str_replace( HERO_DIR, HERO_URL, $path );
        }
    }
}
