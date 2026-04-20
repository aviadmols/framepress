<?php
/**
 * HERO Block Registry
 *
 * Scans three directory paths for file-based block types and caches results.
 * Mirrors the Section Registry pattern exactly.
 * Scan priority (highest → lowest): uploads > child theme > plugin core.
 */

defined( 'ABSPATH' ) || exit;

class Hero_Block_Registry {

    private static ?self $instance = null;

    /** @var array<string, array> Registered blocks keyed by type slug. */
    private array $blocks = [];

    private bool $loaded = false;

    private const TRANSIENT_KEY    = 'hero_block_registry';
    private const TRANSIENT_EXPIRY = DAY_IN_SECONDS;

    // ── Singleton ─────────────────────────────────────────────────────────────

    public static function get_instance(): self {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {}

    // ── Public API ────────────────────────────────────────────────────────────

    public function get_all_blocks(): array {
        $this->maybe_load();
        return $this->blocks;
    }

    public function get_block( string $type ): ?array {
        $this->maybe_load();
        return $this->blocks[ $type ] ?? null;
    }

    public function get_block_path( string $type ): ?string {
        $block = $this->get_block( $type );
        return $block ? $block['_path'] : null;
    }

    public function bust_cache(): void {
        delete_transient( self::TRANSIENT_KEY );
        $this->loaded = false;
        $this->blocks = [];
        $this->maybe_load();
    }

    // ── Internal loading ──────────────────────────────────────────────────────

    private function maybe_load(): void {
        if ( $this->loaded ) {
            return;
        }

        $cached = get_transient( self::TRANSIENT_KEY );
        if ( is_array( $cached ) && ! empty( $cached ) ) {
            $this->blocks = $cached;
            $this->loaded = true;
            return;
        }

        $this->scan_all_paths();
        set_transient( self::TRANSIENT_KEY, $this->blocks, self::TRANSIENT_EXPIRY );
        $this->loaded = true;
    }

    private function scan_all_paths(): void {
        foreach ( $this->get_scan_paths() as $source => $base_path ) {
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
                $schema['_source'] = $source;
                $schema['source']  = $source;

                $this->blocks[ $schema['type'] ] = $schema;
            }
        }
    }

    private function get_scan_paths(): array {
        $upload_dir = wp_upload_dir();
        return [
            'plugin'  => HERO_BLOCKS,
            'theme'   => get_stylesheet_directory() . '/hero-blocks/',
            'uploads' => trailingslashit( $upload_dir['basedir'] ) . 'hero/blocks/',
        ];
    }

    private function load_schema( string $schema_file ): ?array {
        $loader = static function ( string $__file ): mixed {
            return include $__file;
        };

        try {
            $schema = $loader( $schema_file );
        } catch ( \Throwable $e ) {
            if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
                trigger_error( 'HERO Block Registry: failed to load ' . $schema_file . ' — ' . $e->getMessage(), E_USER_WARNING );
            }
            return null;
        }

        if ( ! is_array( $schema ) ) {
            return null;
        }

        return $this->validate_schema( $schema ) ? $schema : null;
    }

    private function validate_schema( array $schema ): bool {
        if ( empty( $schema['type'] ) || empty( $schema['label'] ) || ! isset( $schema['settings'] ) ) {
            return false;
        }
        if ( ! preg_match( '/^[a-z0-9\-]+$/', $schema['type'] ) ) {
            return false;
        }
        return true;
    }
}
