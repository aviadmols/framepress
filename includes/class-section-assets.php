<?php
/**
 * FramePress Section Assets
 *
 * Discovers and enqueues per-section style.css and script.js files.
 * Only loads assets for section types that are actually used on the current page.
 * Version = filemtime() for automatic cache-busting on file change.
 */

defined( 'ABSPATH' ) || exit;

class FramePress_Section_Assets {

    private FramePress_Section_Registry $registry;
    private FramePress_Section_Renderer $renderer;

    public function __construct(
        FramePress_Section_Registry $registry,
        FramePress_Section_Renderer $renderer
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
        $base_css = FRAMEPRESS_DIR . 'assets/frontend/framepress.css';
        if ( file_exists( $base_css ) ) {
            wp_enqueue_style(
                'framepress-frontend',
                FRAMEPRESS_URL . 'assets/frontend/framepress.css',
                [],
                filemtime( $base_css )
            );
        }

        $active_types = $this->renderer->get_active_section_types();

        foreach ( $active_types as $type ) {
            $schema = $this->registry->get_section( $type );
            if ( ! $schema ) {
                continue;
            }

            $section_path = $schema['_path'];
            $section_url  = $this->path_to_url( $section_path, $schema['_source'] ?? 'plugin' );

            // style.css
            $css_file = $section_path . 'style.css';
            if ( file_exists( $css_file ) ) {
                wp_enqueue_style(
                    'framepress-section-' . $type,
                    $section_url . 'style.css',
                    [ 'framepress-frontend' ],
                    (string) filemtime( $css_file )
                );
            }

            // script.js
            $js_file = $section_path . 'script.js';
            if ( file_exists( $js_file ) ) {
                wp_enqueue_script(
                    'framepress-section-' . $type,
                    $section_url . 'script.js',
                    [],
                    (string) filemtime( $js_file ),
                    true   // in footer
                );
            }
        }
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
                return str_replace( FRAMEPRESS_DIR, FRAMEPRESS_URL, $path );
        }
    }
}
