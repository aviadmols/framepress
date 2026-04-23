<?php
/**
 * HERO Section Registry
 *
 * Scans three directory paths for file-based sections and caches the results.
 * Scan priority (highest → lowest): uploads > child theme > plugin core.
 * A section with the same `type` slug in a higher-priority path overrides lower ones.
 */

defined( 'ABSPATH' ) || exit;

class Hero_Section_Registry {

    private static ?self $instance = null;

    /** @var array<string, array> Registered sections keyed by type slug. */
    private array $sections = [];

    /** @var bool Whether sections have been loaded for this request. */
    private bool $loaded = false;

    private const TRANSIENT_KEY     = 'hero_section_registry';
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
        $disabled = $this->get_disabled_types();
        return array_filter( $this->sections, function ( array $schema ) use ( $context, $disabled ) {
            $type = sanitize_key( (string) ( $schema['type'] ?? '' ) );
            if ( in_array( $type, $disabled, true ) ) {
                return false;
            }
            $contexts = $schema['contexts'] ?? [ 'page' ];
            return in_array( $context, $contexts, true ) || in_array( 'any', $contexts, true );
        } );
    }

    /**
     * Return section type slugs explicitly disabled in admin.
     *
     * @return string[]
     */
    public function get_disabled_types(): array {
        $raw = get_option( 'hero_disabled_sections', [] );
        if ( is_string( $raw ) ) {
            $decoded = json_decode( $raw, true );
            $raw = is_array( $decoded ) ? $decoded : [];
        }
        if ( ! is_array( $raw ) ) {
            return [];
        }
        $types = array_map(
            static fn( mixed $type ): string => sanitize_key( (string) $type ),
            $raw
        );
        return array_values( array_unique( array_filter( $types ) ) );
    }

    public function is_section_enabled( string $type ): bool {
        return ! in_array( sanitize_key( $type ), $this->get_disabled_types(), true );
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

                $schema = $this->augment_schema_with_tag_controls( $schema );

                $schema['_path']   = trailingslashit( $dir );
                $schema['_source'] = $source;   // 'plugin' | 'theme' | 'uploads'
                $schema['source']  = $source;   // exposed to REST API
                $schema = $this->normalize_image_defaults_for_source( $schema );

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
            'plugin'  => HERO_SECTIONS,
            'theme'   => get_stylesheet_directory() . '/hero-sections/',
            'uploads' => trailingslashit( $upload_dir['basedir'] ) . 'hero/sections/',
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
                trigger_error( 'HERO: failed to load schema ' . $schema_file . ' — ' . $e->getMessage(), E_USER_WARNING );
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

    /**
     * Adds a companion "{field_id}_tag" select control for textual fields so users
     * can choose rendered HTML tags in Builder/Elementor.
     *
     * @param array<string,mixed> $schema
     * @return array<string,mixed>
     */
    private function augment_schema_with_tag_controls( array $schema ): array {
        $schema['settings'] = $this->augment_field_list_with_tag_controls( is_array( $schema['settings'] ?? null ) ? $schema['settings'] : [] );

        if ( isset( $schema['block_types'] ) && is_array( $schema['block_types'] ) ) {
            foreach ( $schema['block_types'] as $block_type => $block_schema ) {
                if ( ! is_array( $block_schema ) ) {
                    continue;
                }
                $block_settings = is_array( $block_schema['settings'] ?? null ) ? $block_schema['settings'] : [];
                $block_schema['settings'] = $this->augment_field_list_with_tag_controls( $block_settings );
                $schema['block_types'][ $block_type ] = $block_schema;
            }
        }

        return $schema;
    }

    /**
     * @param array<int,mixed> $fields
     * @return array<int,mixed>
     */
    private function augment_field_list_with_tag_controls( array $fields ): array {
        $out = [];
        $existing_ids = [];
        foreach ( $fields as $field ) {
            if ( is_array( $field ) && isset( $field['id'] ) ) {
                $existing_ids[] = sanitize_key( (string) $field['id'] );
            }
        }

        foreach ( $fields as $field ) {
            $out[] = $field;
            if ( ! is_array( $field ) ) {
                continue;
            }
            $id = isset( $field['id'] ) ? sanitize_key( (string) $field['id'] ) : '';
            $type = isset( $field['type'] ) ? (string) $field['type'] : '';
            if ( $id === '' || ! in_array( $type, [ 'text', 'textarea', 'richtext' ], true ) ) {
                continue;
            }

            $tag_id = $id . '_tag';
            if ( in_array( $tag_id, $existing_ids, true ) ) {
                continue;
            }

            $out[] = [
                'id'      => $tag_id,
                'type'    => 'select',
                'label'   => (string) ( $field['label'] ?? ucfirst( $id ) ) . ' HTML Tag',
                'default' => 'auto',
                'options' => [
                    [ 'value' => 'auto', 'label' => 'Auto (template default)' ],
                    [ 'value' => 'h1', 'label' => 'H1' ],
                    [ 'value' => 'h2', 'label' => 'H2' ],
                    [ 'value' => 'h3', 'label' => 'H3' ],
                    [ 'value' => 'h4', 'label' => 'H4' ],
                    [ 'value' => 'h5', 'label' => 'H5' ],
                    [ 'value' => 'p', 'label' => 'P' ],
                    [ 'value' => 'span', 'label' => 'SPAN' ],
                    [ 'value' => 'div', 'label' => 'DIV' ],
                ],
            ];
        }

        return $out;
    }

    /**
     * Runtime fallback: for uploads sections, convert relative image defaults
     * (media/...) to absolute uploads URLs so editor previews resolve on first load.
     *
     * @param array<string,mixed> $schema
     * @return array<string,mixed>
     */
    private function normalize_image_defaults_for_source( array $schema ): array {
        if ( (string) ( $schema['_source'] ?? '' ) !== 'uploads' ) {
            return $schema;
        }

        $path = (string) ( $schema['_path'] ?? '' );
        $type = sanitize_key( (string) ( $schema['type'] ?? '' ) );
        if ( $path === '' || $type === '' ) {
            return $schema;
        }

        $upload = wp_upload_dir();
        $base_url = trailingslashit( $upload['baseurl'] ) . 'hero/sections/' . $type . '/';

        if ( isset( $schema['settings'] ) && is_array( $schema['settings'] ) ) {
            foreach ( $schema['settings'] as $idx => $field ) {
                if ( ! is_array( $field ) ) {
                    continue;
                }
                $schema['settings'][ $idx ] = $this->normalize_image_default_field( $field, $base_url );
            }
        }

        if ( isset( $schema['block_types'] ) && is_array( $schema['block_types'] ) ) {
            foreach ( $schema['block_types'] as $block_type => $block_schema ) {
                if ( ! is_array( $block_schema ) ) {
                    continue;
                }
                if ( isset( $block_schema['settings'] ) && is_array( $block_schema['settings'] ) ) {
                    foreach ( $block_schema['settings'] as $idx => $field ) {
                        if ( ! is_array( $field ) ) {
                            continue;
                        }
                        $block_schema['settings'][ $idx ] = $this->normalize_image_default_field( $field, $base_url );
                    }
                }
                $schema['block_types'][ $block_type ] = $block_schema;
            }
        }

        return $schema;
    }

    /**
     * @param array<string,mixed> $field
     * @return array<string,mixed>
     */
    private function normalize_image_default_field( array $field, string $base_url ): array {
        $is_image = (string) ( $field['type'] ?? '' ) === 'image';
        $default  = (string) ( $field['default'] ?? '' );
        if ( ! $is_image || $default === '' ) {
            return $field;
        }
        if ( preg_match( '#^https?://#i', $default ) ) {
            return $field;
        }

        $normalized = ltrim( $default, '/' );
        if ( str_starts_with( $normalized, './' ) ) {
            $normalized = substr( $normalized, 2 );
        }
        if ( ! str_starts_with( $normalized, 'media/' ) ) {
            $normalized = 'media/' . $normalized;
        }

        $field['default'] = $base_url . $normalized;
        return $field;
    }
}
