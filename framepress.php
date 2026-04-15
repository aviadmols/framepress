<?php
/**
 * Plugin Name: FramePress
 * Plugin URI:  https://github.com/framepress/framepress
 * Description: A file-based, schema-driven page builder for WordPress — built like Shopify Themes.
 * Version:     1.0.0
 * Author:      FramePress
 * License:     GPL-2.0-or-later
 * Text Domain: framepress
 */

defined( 'ABSPATH' ) || exit;

// ─── Constants ────────────────────────────────────────────────────────────────

define( 'FRAMEPRESS_VERSION',  '1.0.0' );
define( 'FRAMEPRESS_DIR',      plugin_dir_path( __FILE__ ) );
define( 'FRAMEPRESS_URL',      plugin_dir_url( __FILE__ ) );
define( 'FRAMEPRESS_SECTIONS', FRAMEPRESS_DIR . 'sections/' );
define( 'FRAMEPRESS_BLOCKS',   FRAMEPRESS_DIR . 'blocks/' );

// ─── Autoloader ───────────────────────────────────────────────────────────────

$includes = [
    'includes/class-section-registry.php',
    'includes/class-block-registry.php',
    'includes/class-section-renderer.php',
    'includes/class-section-assets.php',
    'includes/class-section-zip-manager.php',
    'includes/class-global-settings.php',
    'includes/class-rest-api.php',
    'includes/class-preview.php',
    'includes/class-builder-page.php',
    'includes/class-ai-service.php',
];

foreach ( $includes as $file ) {
    require_once FRAMEPRESS_DIR . $file;
}

// ─── Bootstrap ────────────────────────────────────────────────────────────────

/**
 * Main plugin instance. Holds references to singletons so they are only
 * instantiated once and available throughout the request lifecycle.
 */
final class FramePress {

    private static ?self $instance = null;

    public FramePress_Section_Registry  $section_registry;
    public FramePress_Block_Registry    $block_registry;
    public FramePress_Section_Renderer  $renderer;
    public FramePress_Section_Assets    $assets;
    public FramePress_Global_Settings   $global_settings;

    public static function get_instance(): self {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        add_action( 'init',              [ $this, 'init' ] );
        add_action( 'rest_api_init',     [ $this, 'init_rest_api' ] );
        add_action( 'template_redirect', [ $this, 'init_preview' ], 1 );

        // Instantiate the builder page early (before admin_menu fires)
        // so its internal add_action('admin_menu') can register in time.
        if ( is_admin() ) {
            new FramePress_Builder_Page();
        }
    }

    public function init(): void {
        $this->section_registry = FramePress_Section_Registry::get_instance();
        $this->block_registry   = FramePress_Block_Registry::get_instance();
        $this->renderer         = new FramePress_Section_Renderer(
            $this->section_registry,
            $this->block_registry
        );
        $this->assets           = new FramePress_Section_Assets(
            $this->section_registry,
            $this->renderer
        );
        $this->global_settings  = new FramePress_Global_Settings();

        // Google Fonts — load on both frontend and admin (preview iframe uses admin_head).
        add_action( 'wp_head',    [ $this->global_settings, 'output_google_fonts' ], 1 );
        add_action( 'admin_head', [ $this->global_settings, 'output_google_fonts' ], 1 );

        // Output hooks — frontend only.
        if ( ! is_admin() ) {
            add_action( 'wp_enqueue_scripts', [ $this->assets, 'enqueue_section_assets' ] );
            add_action( 'wp_head',            [ $this->global_settings, 'output_css_variables' ] );
            add_filter( 'the_content',        [ $this->renderer, 'filter_page_content' ] );
            add_action( 'wp_body_open',       [ $this->renderer, 'output_header_sections' ] );
            add_action( 'wp_footer',          [ $this->renderer, 'output_footer_sections' ] );
        }
    }

    public function init_rest_api(): void {
        $api = new FramePress_Rest_API(
            $this->section_registry,
            $this->block_registry,
            $this->renderer,
            $this->global_settings
        );
        $api->register_routes();
    }

    public function init_preview(): void {
        if ( isset( $_GET['framepress_preview'] ) ) {
            $preview = new FramePress_Preview( $this->renderer, $this->global_settings );
            $preview->handle();
        }
    }
}

// ─── Activation / Deactivation ────────────────────────────────────────────────

register_activation_hook( __FILE__, 'framepress_activate' );
register_deactivation_hook( __FILE__, 'framepress_deactivate' );

function framepress_activate(): void {
    // Create uploads directory for user-uploaded sections and blocks.
    $upload_base = wp_upload_dir()['basedir'];
    wp_mkdir_p( $upload_base . '/framepress/sections' );
    wp_mkdir_p( $upload_base . '/framepress/blocks' );

    // Drop a security .htaccess in the uploads folder — prevent direct PHP execution.
    $htaccess = $upload_base . '/framepress/.htaccess';
    if ( ! file_exists( $htaccess ) ) {
        file_put_contents( $htaccess, "Options -Indexes\n<Files *.php>\n  deny from all\n</Files>\n" );
    }

    // Bust registry cache.
    delete_transient( 'framepress_section_registry' );
    delete_transient( 'framepress_block_registry' );

    flush_rewrite_rules();
}

function framepress_deactivate(): void {
    delete_transient( 'framepress_section_registry' );
    delete_transient( 'framepress_block_registry' );
    flush_rewrite_rules();
}

// Bust cache when the active theme changes (child theme sections may differ).
add_action( 'switch_theme', function () {
    delete_transient( 'framepress_section_registry' );
    delete_transient( 'framepress_block_registry' );
} );

// ─── Boot ─────────────────────────────────────────────────────────────────────

add_action( 'plugins_loaded', [ FramePress::class, 'get_instance' ] );

/**
 * Elementor: register widget + category when Elementor is available.
 * If Elementor loads before FramePress, `elementor/loaded` has already fired — hooking only
 * that action would miss registration; we catch that via `did_action` on `plugins_loaded`.
 *
 * Staging/editor issues (SSL on uploads, admin-ajax 500) are documented in elementor-staging-notes.txt.
 */
function framepress_bootstrap_elementor(): void {
    static $done = false;
    if ( $done ) {
        return;
    }
    if ( ! class_exists( '\Elementor\Plugin' ) ) {
        return;
    }
    require_once FRAMEPRESS_DIR . 'includes/class-elementor-integration.php';
    FramePress_Elementor_Integration::init();
    $done = true;
}

add_action(
    'plugins_loaded',
    static function (): void {
        if ( ! class_exists( '\Elementor\Plugin' ) ) {
            return;
        }
        if ( did_action( 'elementor/loaded' ) ) {
            framepress_bootstrap_elementor();
            return;
        }
        add_action( 'elementor/loaded', 'framepress_bootstrap_elementor', 5 );
    },
    20
);
