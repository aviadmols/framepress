<?php
/**
 * HERO Builder Page
 *
 * Registers the admin pages and enqueues the React builder application.
 * The builder runs as a bare full-viewport page (no WP admin chrome)
 * so the iframe preview can fill the available space cleanly.
 */

defined( 'ABSPATH' ) || exit;

class Hero_Builder_Page {

    public function __construct() {
        add_action( 'admin_menu',              [ $this, 'add_admin_menu' ] );
        add_filter( 'page_row_actions',        [ $this, 'add_page_edit_link' ], 10, 2 );
        add_action( 'admin_bar_menu',          [ $this, 'add_admin_bar_link' ], 100 );
    }

    // ─── Admin menu ───────────────────────────────────────────────────────────

    public function add_admin_menu(): void {
        // Top-level menu.
        add_menu_page(
            __( 'HERO', 'hero' ),
            __( 'HERO', 'hero' ),
            'edit_pages',
            'hero',
            [ $this, 'render_hub_page' ],
            'dashicons-layout',
            59
        );

        // Remove duplicate auto-created submenu.
        add_submenu_page( 'hero', __( 'Pages', 'hero' ),          __( 'Pages', 'hero' ),          'edit_pages',    'hero',              [ $this, 'render_hub_page' ] );
        add_submenu_page( 'hero', __( 'Header Builder', 'hero' ),  __( 'Header', 'hero' ),         'edit_pages',    'hero-header',       [ $this, 'render_header_builder' ] );
        add_submenu_page( 'hero', __( 'Footer Builder', 'hero' ),  __( 'Footer', 'hero' ),         'edit_pages',    'hero-footer',       [ $this, 'render_footer_builder' ] );
        add_submenu_page( 'hero', __( 'Global Settings', 'hero' ), __( 'Global Settings', 'hero' ),'edit_pages',    'hero-global',       [ $this, 'render_global_settings' ] );
        add_submenu_page( 'hero', __( 'Sections', 'hero' ),        __( 'Sections', 'hero' ),       'manage_options','hero-sections-mgr', [ $this, 'render_sections_manager' ] );
        add_submenu_page( 'hero', __( 'AI Settings', 'hero' ),     __( 'AI Settings', 'hero' ),    'manage_options','hero-ai-settings',  [ $this, 'render_ai_settings' ] );
    }

    // ─── Page callbacks ───────────────────────────────────────────────────────

    /** Hub: list of pages with "Edit with HERO" links — or the builder if post_id is set. */
    public function render_hub_page(): void {
        // Elementor widget → HERO editor (single section instance).
        if ( isset( $_GET['context'] ) && $_GET['context'] === 'elementor-section' && ! empty( $_GET['section_key'] ) ) {
            $key     = sanitize_text_field( wp_unslash( $_GET['section_key'] ?? '' ) );
            $type    = sanitize_key( $_GET['section_type'] ?? '' );
            $post_id = absint( $_GET['elementor_post_id'] ?? 0 );
            if ( strlen( $key ) !== 32 || ! ctype_xdigit( $key ) || $type === '' || ! $post_id ) {
                wp_die( esc_html__( 'Invalid HERO link.', 'hero' ), '', [ 'response' => 400 ] );
            }
            if ( ! current_user_can( 'edit_post', $post_id ) ) {
                wp_die( esc_html__( 'You do not have permission to edit this content.', 'hero' ), '', [ 'response' => 403 ] );
            }
            $this->enqueue_builder_assets(
                $post_id,
                'elementor-section',
                [
                    'elementorSectionKey' => $key,
                    'sectionType'         => $type,
                ]
            );
            require_once HERO_DIR . 'templates/builder.php';
            return;
        }

        // If a post_id is present, render the full builder for that page.
        if ( isset( $_GET['post_id'] ) && absint( $_GET['post_id'] ) > 0 ) {
            $context = isset( $_GET['context'] ) ? sanitize_key( $_GET['context'] ) : 'page';
            $this->render_builder( absint( $_GET['post_id'] ), $context );
            return;
        }

        $pages = get_pages( [ 'sort_column' => 'post_title', 'sort_order' => 'ASC' ] );
        echo '<div class="wrap"><h1>' . esc_html__( 'HERO Pages', 'hero' ) . '</h1><table class="wp-list-table widefat fixed">';
        echo '<thead><tr><th>' . esc_html__( 'Page', 'hero' ) . '</th><th>' . esc_html__( 'Actions', 'hero' ) . '</th></tr></thead><tbody>';
        foreach ( $pages as $page ) {
            $edit_url = $this->builder_url( $page->ID, 'page' );
            echo '<tr>';
            echo '<td>' . esc_html( $page->post_title ) . '</td>';
            echo '<td><a href="' . esc_url( $edit_url ) . '" class="button button-primary">' . esc_html__( 'Edit with HERO', 'hero' ) . '</a>';
            echo ' <a href="' . esc_url( get_edit_post_link( $page->ID ) ) . '">' . esc_html__( 'WordPress Editor', 'hero' ) . '</a></td>';
            echo '</tr>';
        }
        echo '</tbody></table></div>';
    }

    /** Full-viewport builder for a specific page. */
    public function render_header_builder(): void {
        $this->render_builder( 0, 'header' );
    }

    public function render_footer_builder(): void {
        $this->render_builder( 0, 'footer' );
    }

    public function render_global_settings(): void {
        $this->render_builder( 0, 'global' );
    }

    public function render_sections_manager(): void {
        // Data is injected inline in the template via wp_json_encode — no separate enqueue needed.
        require_once HERO_DIR . 'templates/sections-manager.php';
    }

    public function render_ai_settings(): void {
        $this->render_builder( 0, 'ai-settings' );
    }

    // ─── Core builder renderer ────────────────────────────────────────────────

    private function render_builder( int $post_id = 0, string $context = 'page' ): void {
        // Allow post_id override via GET param (for page builder links).
        if ( isset( $_GET['post_id'] ) ) {
            $post_id = absint( $_GET['post_id'] );
        }

        $this->enqueue_builder_assets( $post_id, $context, [] );

        // Output bare page — no WP admin header/footer.
        require_once HERO_DIR . 'templates/builder.php';
    }

    // ─── Asset enqueueing ─────────────────────────────────────────────────────

    private function enqueue_builder_assets( int $post_id, string $context, array $extra = [] ): void {
        // Required for WP media picker.
        wp_enqueue_media();

        $dist = HERO_DIR . 'assets/builder/dist/';

        // Vite outputs a manifest.json — read it to get hashed filenames.
        $manifest_path = $dist . '.vite/manifest.json';
        $js_file  = 'assets/builder/dist/main.js';
        $css_file = 'assets/builder/dist/main.css';

        if ( ! file_exists( $manifest_path ) ) {
            add_action( 'admin_notices', function () use ( $manifest_path ) {
                echo '<div class="notice notice-error"><p><strong>HERO:</strong> Builder assets not found. '
                    . 'Upload the <code>assets/builder/dist/</code> folder to the server. '
                    . 'Expected manifest at: <code>' . esc_html( $manifest_path ) . '</code></p></div>';
            } );
        }

        if ( file_exists( $manifest_path ) ) {
            $manifest = json_decode( file_get_contents( $manifest_path ), true );
            if ( isset( $manifest['src/index.jsx']['file'] ) ) {
                $js_file = 'assets/builder/dist/' . $manifest['src/index.jsx']['file'];
            }
            if ( isset( $manifest['src/index.jsx']['css'][0] ) ) {
                $css_file = 'assets/builder/dist/' . $manifest['src/index.jsx']['css'][0];
            }
        }

        if ( file_exists( HERO_DIR . $css_file ) ) {
            wp_enqueue_style( 'hero-builder', HERO_URL . $css_file, [], HERO_VERSION );
        }

        if ( file_exists( HERO_DIR . $js_file ) ) {
            wp_enqueue_script( 'hero-builder', HERO_URL . $js_file, [], HERO_VERSION, true );
        }

        // Pass data to React. Live preview iframe should load the real page (Elementor + widget), not elementor-section bridge scope.
        $preview_context = $context === 'elementor-section' ? 'page' : $context;
        $preview_nonce   = $post_id ? wp_create_nonce( 'hero_preview_' . $post_id ) : '';
        $preview_url     = $post_id
            ? add_query_arg( [
                'hero_preview' => 1,
                'post_id'            => $post_id,
                'context'            => $preview_context,
                'nonce'              => $preview_nonce,
            ], get_permalink( $post_id ) ?: home_url( '/' ) )
            : add_query_arg( [
                'hero_preview' => 1,
                'post_id'            => 0,
                'context'            => $preview_context,
                'nonce'              => wp_create_nonce( 'hero_preview_0' ),
            ], home_url( '/' ) );

        $hero_data = array_merge(
            [
                'restUrl'             => rest_url( 'hero/v1' ),
                'nonce'               => wp_create_nonce( 'wp_rest' ),
                'postId'              => $post_id,
                'context'             => $context,
                'previewUrl'          => $preview_url,
                'adminUrl'            => admin_url(),
                'version'             => HERO_VERSION,
                'aiEnabled'           => (bool) get_option( 'hero_ai_enabled', false ),
                'elementorSectionKey' => '',
                'sectionType'         => '',
            ],
            $extra
        );

        wp_localize_script( 'hero-builder', 'heroData', $hero_data );
    }

    // ─── Utility ──────────────────────────────────────────────────────────────

    public function builder_url( int $post_id, string $context = 'page' ): string {
        return admin_url( 'admin.php?page=hero&post_id=' . $post_id . '&context=' . $context );
    }

    /** Add "Edit with HERO" to page row actions. */
    public function add_page_edit_link( array $actions, \WP_Post $post ): array {
        if ( $post->post_type === 'page' && current_user_can( 'edit_page', $post->ID ) ) {
            $actions['hero'] = '<a href="' . esc_url( $this->builder_url( $post->ID ) ) . '">'
                . esc_html__( 'Edit with HERO', 'hero' )
                . '</a>';
        }
        return $actions;
    }

    /** Add "HERO" link to admin bar when viewing a page on the frontend. */
    public function add_admin_bar_link( \WP_Admin_Bar $bar ): void {
        if ( ! is_singular() || ! current_user_can( 'edit_pages' ) ) {
            return;
        }
        $bar->add_node( [
            'id'    => 'hero-edit',
            'title' => '🖼 HERO',
            'href'  => $this->builder_url( get_the_ID() ),
            'meta'  => [ 'title' => __( 'Edit with HERO', 'hero' ) ],
        ] );
    }
}
