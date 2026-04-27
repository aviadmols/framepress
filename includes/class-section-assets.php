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
     * Section CSS load order is critical for theme override behavior:
     *   - Default path: depends on the active theme's main stylesheet (when
     *     detectable) so WP topologically prints theme CSS first, section CSS
     *     after. Combined with priority 999 on wp_enqueue_scripts (registered
     *     in framepress.php), this beats theme rules of equal specificity.
     *   - Opt-in: a schema may declare `'css_priority' => 'after-everything'`
     *     to bypass the enqueue queue entirely and emit the <link> tag at the
     *     very end of wp_head (priority 9999), so it lands after every
     *     enqueued style — including late-registered plugin/builder CSS.
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

            if ( $css_priority === 'after-everything' ) {
                $this->emit_late_section_css( $type, $section_url, $css_file );
            } else {
                $deps = [ 'hero-frontend' ];
                $theme_handle = $this->detect_theme_style_handle();
                if ( $theme_handle && $theme_handle !== 'hero-frontend' ) {
                    $deps[] = $theme_handle;
                }

                wp_enqueue_style(
                    'hero-section-' . $type,
                    $section_url . 'style.css',
                    $deps,
                    (string) filemtime( $css_file )
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
     * Emit a hard <link rel="stylesheet"> at the very end of wp_head so the
     * section's CSS is loaded after every other enqueued stylesheet on the
     * page — including late-registered plugin and builder CSS.
     *
     * Triggered by setting `'css_priority' => 'after-everything'` on a
     * section's schema.
     */
    private function emit_late_section_css( string $type, string $section_url, string $css_file ): void {
        $href    = $section_url . 'style.css';
        $version = (string) filemtime( $css_file );

        add_action( 'wp_head', static function () use ( $type, $href, $version ): void {
            printf(
                '<link rel="stylesheet" id="hero-section-%s-late-css" href="%s" />' . "\n",
                esc_attr( $type ),
                esc_url( add_query_arg( 'ver', $version, $href ) )
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
