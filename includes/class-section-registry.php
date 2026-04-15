<?php
/**
 * FramePress Section Registry
 *
 * Scans three directory paths for file-based sections and caches the results.
 * Scan priority (highest → lowest): uploads > child theme > plugin core.
 * A section with the same `type` slug in a higher-priority path overrides lower ones.
 */

defined( 'ABSPATH' ) || exit;

class FramePress_Section_Registry {

    private static ?self $instance = null;

    /** @var array<string, array> Registered sections keyed by type slug. */
    private array $sections = [];

    /** @var bool Whether sections have been loaded for this request. */
    private bool $loaded = false;

    private const TRANSIENT_KEY     = 'framepress_section_registry';
    private const TRANSIENT_EXPIRY  = DAY_IN_SECONDS;

    // ── Singleton ─────────────────────────────────────────────────────────────

    public static function get_instance(): self {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {}

    // ── Public API ────────────────────────────────────────────────────────────

    /** Return all registered sections, loading from cache or disk if needed. */
    public function get_all_sections(): array {
        $this->maybe_load();
        return $this->sections;
    }

    /** Return schemas filtered to a specific context ('page', 'header', 'footer', 'any'). */
    public function get_sections_for_context( string $context ): array {
        $this->maybe_load();
        return array_filter( $this->sections, function ( array $schema ) use ( $context ) {
            $contexts = $schema['contexts'] ?? [ 'page' ];
            return in_array( $context, $contexts, true ) || in_array( 'any', $contexts, true );
        } );
    }

    /** Return a single section schema by type slug, or null if not found. */
    public function get_section( string $type ): ?array {
        $this->maybe_load();
        return $this->sections[ $type ] ?? null;
    }

    /** Return the filesystem path to a section's directory. */
    public function get_section_path( string $type ): ?string {
        $schema = $this->get_section( $type );
        return $schema ? $schema['_path'] : null;
    }

    /** Force-reload all sections and rebuild the transient cache. */
    public function bust_cache(): void {
        delete_transient( self::TRANSIENT_KEY );
        $this->loaded   = false;
        $this->sections = [];
        $this->maybe_load();
    }

    // ── Internal loading ──────────────────────────────────────────────────────

    private function maybe_load(): void {
        if ( $this->loaded ) {
            return;
        }

        $cached = get_transient( self::TRANSIENT_KEY );
        if ( is_array( $cached ) && ! empty( $cached ) ) {
            $this->sections = $cached;
            $this->loaded   = true;
            return;
        }

        $this->scan_all_paths();
        set_transient( self::TRANSIENT_KEY, $this->sections, self::TRANSIENT_EXPIRY );
        $this->loaded = true;
    }

    /**
     * Scan all three paths in increasing priority order so that higher-priority
     * sources overwrite lower-priority ones with the same type slug.
     */
    private function scan_all_paths(): void {
        $paths = $this->get_scan_paths();

        foreach ( $paths as $source => $base_path ) {
            if ( ! is_dir( $base_path ) ) {
                continue;
            }

            $dirs = glob( trailingslashit( $base_path ) . '*', GLOB_ONLYDIR );
            if ( ! $dirs ) {
                continue;
            }

            foreach ( $dirs as $dir ) {
                $schema_file = trailingslashit( $dir ) . 'schema.php';
                if ( ! file_exists( $schema_file ) ) {
                    continue;
                }

                $schema = $this->load_schema( $schema_file );
                if ( null === $schema ) {
                    continue;
                }

                $schema['_path']   = trailingslashit( $dir );
                $schema['_source'] = $source;   // 'plugin' | 'theme' | 'uploads'
                $schema['source']  = $source;   // exposed to REST API

                $type = $schema['type'];
                $this->sections[ $type ] = $schema;
            }
        }
    }

    /**
     * Returns scan paths ordered from lowest to highest priority so that
     * later iterations overwrite earlier ones.
     *
     * @return array<string, string> [ source => absolute_path ]
     */
    private function get_scan_paths(): array {
        $upload_dir = wp_upload_dir();
        return [
            'plugin'  => FRAMEPRESS_SECTIONS,
            'theme'   => get_stylesheet_directory() . '/framepress-sections/',
            'uploads' => trailingslashit( $upload_dir['basedir'] ) . 'framepress/sections/',
        ];
    }

    /**
     * Safely include a schema.php file inside an isolated closure so that
     * any variables inside the file don't pollute our scope.
     */
    private function load_schema( string $schema_file ): ?array {
        $loader = static function ( string $__file ): mixed {
            return include $__file;
        };

        try {
            $schema = $loader( $schema_file );
        } catch ( \Throwable $e ) {
            // Malformed schema file — skip silently in production.
            if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
                trigger_error( 'FramePress: failed to load schema ' . $schema_file . ' — ' . $e->getMessage(), E_USER_WARNING );
            }
            return null;
        }

        if ( ! is_array( $schema ) ) {
            return null;
        }

        return $this->validate_schema( $schema ) ? $schema : null;
    }

    /**
     * Ensure minimum required keys are present in a schema definition.
     */
    private function validate_schema( array $schema ): bool {
        $required = [ 'type', 'label', 'settings' ];
        foreach ( $required as $key ) {
            if ( empty( $schema[ $key ] ) ) {
                return false;
            }
        }

        // Type must be a safe slug.
        if ( ! preg_match( '/^[a-z0-9\-]+$/', $schema['type'] ) ) {
            return false;
        }

        return true;
    }
}
