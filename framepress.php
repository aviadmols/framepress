<?php
/**
 * Plugin Name: HERO
 * Plugin URI:  https://github.com/hero/hero
 * Description: A file-based, schema-driven page builder for WordPress — built like Shopify Themes.
 * Version:     1.0.0
 * Author:      HERO
 * License:     GPL-2.0-or-later
 * Text Domain: hero
 */

defined( 'ABSPATH' ) || exit;

// Prevent PHP notices/warnings from being printed into admin-ajax JSON (Elementor, REST, etc.).
// Must run on every load — not only when Elementor integration bootstrap runs.
if ( defined( 'DOING_AJAX' ) && DOING_AJAX ) {
    // phpcs:ignore WordPress.PHP.IniSet.display_errors_Disallowed -- AJAX JSON must stay clean; log still works.
    @ini_set( 'display_errors', '0' );
}

// ─── Constants ────────────────────────────────────────────────────────────────

define( 'HERO_VERSION',  '1.0.0' );
define( 'HERO_DIR',      plugin_dir_path( __FILE__ ) );
define( 'HERO_URL',      plugin_dir_url( __FILE__ ) );
define( 'HERO_SECTIONS', HERO_DIR . 'sections/' );
define( 'HERO_BLOCKS',   HERO_DIR . 'blocks/' );

// ─── Autoloader ───────────────────────────────────────────────────────────────

$includes = [
    'includes/schema-safety.php',
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
    require_once HERO_DIR . $file;
}

// ─── Bootstrap ────────────────────────────────────────────────────────────────

/**
 * Main plugin instance. Holds references to singletons so they are only
 * instantiated once and available throughout the request lifecycle.
 */
final class HERO {

    private static ?self $instance = null;

    public Hero_Section_Registry  $section_registry;
    public Hero_Block_Registry    $block_registry;
    public Hero_Section_Renderer  $renderer;
    public Hero_Section_Assets    $assets;
    public Hero_Global_Settings   $global_settings;

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
            new Hero_Builder_Page();
        }
    }

    public function init(): void {
        $this->section_registry = Hero_Section_Registry::get_instance();
        $this->block_registry   = Hero_Block_Registry::get_instance();
        $this->renderer         = new Hero_Section_Renderer(
            $this->section_registry,
            $this->block_registry
        );
        $this->assets           = new Hero_Section_Assets(
            $this->section_registry,
            $this->renderer
        );
        $this->global_settings  = new Hero_Global_Settings();

        // Google Fonts — load on both frontend and admin (preview iframe uses admin_head).
        add_action( 'wp_head',    [ $this->global_settings, 'output_google_fonts' ], 1 );
        add_action( 'admin_head', [ $this->global_settings, 'output_google_fonts' ], 1 );

        // Section assets + global design tokens: hook outside is_admin() so Elementor preview iframe
        // (runs wp_enqueue_scripts / wp_head as frontend) still loads CSS/JS and :root variables.
        // Priority 999 ensures section stylesheets enqueue after typical theme stylesheets, so
        // for equal-specificity rules the section's CSS wins on source order.
        add_action( 'wp_enqueue_scripts', [ $this->assets, 'enqueue_section_assets' ], 999 );
        add_action( 'wp_head',            [ $this->global_settings, 'output_css_variables' ] );

        // Output hooks — frontend only (not Elementor editor shell).
        if ( ! is_admin() ) {
            add_filter( 'the_content',  [ $this->renderer, 'filter_page_content' ] );
            add_action( 'wp_body_open', [ $this->renderer, 'output_header_sections' ] );
            add_action( 'wp_footer',    [ $this->renderer, 'output_footer_sections' ] );
        }
    }

    public function init_rest_api(): void {
        $api = new Hero_Rest_API(
            $this->section_registry,
            $this->block_registry,
            $this->renderer,
            $this->global_settings
        );
        $api->register_routes();
    }

    public function init_preview(): void {
        if ( isset( $_GET['hero_preview'] ) ) {
            $preview = new Hero_Preview( $this->renderer, $this->global_settings );
            $preview->handle();
        }
    }
}

// ─── Activation / Deactivation ────────────────────────────────────────────────

register_activation_hook( __FILE__, 'hero_activate' );
register_deactivation_hook( __FILE__, 'hero_deactivate' );

function hero_activate(): void {
    // Create uploads directory for user-uploaded sections and blocks.
    $upload_base = wp_upload_dir()['basedir'];
    wp_mkdir_p( $upload_base . '/hero/sections' );
    wp_mkdir_p( $upload_base . '/hero/blocks' );

    // Drop a security .htaccess in the uploads folder — prevent direct PHP execution.
    $htaccess = $upload_base . '/hero/.htaccess';
    if ( ! file_exists( $htaccess ) ) {
        file_put_contents( $htaccess, "Options -Indexes\n<Files *.php>\n  deny from all\n</Files>\n" );
    }

    // Bust registry cache.
    delete_transient( 'hero_section_registry' );
    delete_transient( 'hero_block_registry' );

    flush_rewrite_rules();
}

function hero_deactivate(): void {
    delete_transient( 'hero_section_registry' );
    delete_transient( 'hero_block_registry' );
    flush_rewrite_rules();
}

// Bust cache when the active theme changes (child theme sections may differ).
add_action( 'switch_theme', function () {
    delete_transient( 'hero_section_registry' );
    delete_transient( 'hero_block_registry' );
} );

// ─── DB Migration ─────────────────────────────────────────────────────────────

function hero_run_migration(): void {
    if ( get_option( 'hero_migration_v1_done' ) ) {
        return;
    }

    global $wpdb;

    // Rename simple options.
    $simple_options = [
        'framepress_global_settings' => 'hero_global_settings',
        'framepress_header'          => 'hero_header',
        'framepress_footer'          => 'hero_footer',
        'framepress_ai_provider'     => 'hero_ai_provider',
        'framepress_ai_key'          => 'hero_ai_key',
        'framepress_ai_model'        => 'hero_ai_model',
        'framepress_ai_enabled'      => 'hero_ai_enabled',
    ];
    foreach ( $simple_options as $old => $new ) {
        $val = get_option( $old );
        if ( $val !== false ) {
            update_option( $new, $val );
            delete_option( $old );
        }
    }

    // Rename framepress_el_* options → hero_el_*.
    $rows = $wpdb->get_results(
        "SELECT option_name FROM {$wpdb->options} WHERE option_name LIKE 'framepress_el_%'"
    );
    foreach ( $rows as $row ) {
        $new_name = 'hero_el_' . substr( $row->option_name, strlen( 'framepress_el_' ) );
        $wpdb->update(
            $wpdb->options,
            [ 'option_name' => $new_name ],
            [ 'option_name' => $row->option_name ]
        );
    }

    // Rename _framepress_sections postmeta → _hero_sections.
    $wpdb->update(
        $wpdb->postmeta,
        [ 'meta_key' => '_hero_sections' ],
        [ 'meta_key' => '_framepress_sections' ]
    );

    // Rename uploads/framepress/ → uploads/hero/ (physical directory).
    $upload_base = wp_upload_dir()['basedir'];
    $old_dir     = $upload_base . '/framepress/';
    $new_dir     = $upload_base . '/hero/';
    if ( is_dir( $old_dir ) && ! is_dir( $new_dir ) ) {
        rename( $old_dir, $new_dir );
    }

    // Delete old transients.
    delete_transient( 'framepress_section_registry' );
    delete_transient( 'framepress_block_registry' );

    update_option( 'hero_migration_v1_done', true );
}

add_action( 'plugins_loaded', 'hero_run_migration', 5 );

// ─── Boot ─────────────────────────────────────────────────────────────────────

add_action( 'plugins_loaded', [ HERO::class, 'get_instance' ] );

/**
 * Elementor: register widget + category when Elementor is available.
 * If Elementor loads before HERO, `elementor/loaded` has already fired — hooking only
 * that action would miss registration; we catch that via `did_action` on `plugins_loaded`.
 *
 * Staging/editor issues (SSL on uploads, admin-ajax 500) are documented in elementor-staging-notes.txt.
 */
function hero_bootstrap_elementor(): void {
    static $done = false;
    if ( $done ) {
        return;
    }
    if ( ! class_exists( '\Elementor\Plugin' ) ) {
        return;
    }
    $raw = get_option( 'hero_global_settings', '{}' );
    $gs  = is_string( $raw ) ? json_decode( $raw, true ) : (array) $raw;
    if ( ! is_array( $gs ) ) {
        $gs = [];
    }
    if ( empty( $gs['elementor_widgets_enabled'] ) ) {
        return;
    }
    require_once HERO_DIR . 'includes/class-elementor-integration.php';
    Hero_Elementor_Integration::init();
    $done = true;
}

add_action(
    'plugins_loaded',
    static function (): void {
        if ( ! class_exists( '\Elementor\Plugin' ) ) {
            return;
        }
        if ( did_action( 'elementor/loaded' ) ) {
            hero_bootstrap_elementor();
            return;
        }
        add_action( 'elementor/loaded', 'hero_bootstrap_elementor', 5 );
    },
    20
);

if ( ! function_exists( 'hero_pick_tag' ) ) {
    /**
     * Resolve a safe HTML tag from user selection.
     */
    function hero_pick_tag( string $selected, string $fallback = 'div' ): string {
        $allowed = [ 'h1', 'h2', 'h3', 'h4', 'h5', 'p', 'span', 'div' ];
        $selected = strtolower( sanitize_key( $selected ) );
        if ( $selected === '' || $selected === 'auto' ) {
            $selected = strtolower( sanitize_key( $fallback ) );
        }
        return in_array( $selected, $allowed, true ) ? $selected : 'div';
    }
}
