<?php
/**
 * HERO Global Settings
 *
 * Manages sitewide design tokens (colors, typography, spacing, buttons, custom CSS).
 * Settings are stored as JSON in wp_options and output as CSS custom properties
 * on :root on every frontend page.
 */

defined( 'ABSPATH' ) || exit;

class Hero_Global_Settings {

    private const OPTION_KEY   = 'hero_global_settings';
    private string $schema_file;
    /** @var list<array{slug:string,label:string,family:string}>|null */
    private static ?array $google_fonts_catalog = null;

    public function __construct() {
        $this->schema_file = HERO_DIR . 'global-settings/schema.php';
    }

    /**
     * Curated Google Fonts for the builder dropdown (slug, label, CSS family name).
     *
     * @return list<array{slug:string,label:string,family:string}>
     */
    public function get_google_fonts_catalog(): array {
        if ( self::$google_fonts_catalog !== null ) {
            return self::$google_fonts_catalog;
        }
        $file = HERO_DIR . 'global-settings/google-fonts.php';
        if ( ! is_readable( $file ) ) {
            self::$google_fonts_catalog = [];
            return self::$google_fonts_catalog;
        }
        /** @var mixed $loaded */
        $loaded = include $file;
        self::$google_fonts_catalog = is_array( $loaded ) ? $loaded : [];
        return self::$google_fonts_catalog;
    }

    /**
     * Build Google Fonts CSS v2 URL for a catalog slug.
     */
    public function build_google_fonts_css_url_for_slug( string $slug ): string {
        $slug = sanitize_title( $slug );
        foreach ( $this->get_google_fonts_catalog() as $font ) {
            if ( ( $font['slug'] ?? '' ) === $slug ) {
                $family = (string) ( $font['family'] ?? '' );
                if ( $family === '' ) {
                    return '';
                }
                $family_q = str_replace( ' ', '+', $family );
                return 'https://fonts.googleapis.com/css2?family=' . $family_q . ':wght@400;600;700&display=swap';
            }
        }
        return '';
    }

    /**
     * @return array{slug:string,label:string,family:string}|null
     */
    public function get_google_font_by_slug( string $slug ): ?array {
        $slug = sanitize_title( $slug );
        foreach ( $this->get_google_fonts_catalog() as $font ) {
            if ( ( $font['slug'] ?? '' ) === $slug ) {
                return $font;
            }
        }
        return null;
    }

    /**
     * Infer dropdown slug from a saved Google Fonts URL (legacy / custom).
     */
    public function infer_google_font_pick( array $settings ): string {
        $url = trim( (string) ( $settings['google_fonts_url'] ?? '' ) );
        if ( $url === '' ) {
            return '';
        }
        foreach ( $this->get_google_fonts_catalog() as $font ) {
            $expected = $this->build_google_fonts_css_url_for_slug( (string) ( $font['slug'] ?? '' ) );
            if ( $expected !== '' && $expected === $url ) {
                return (string) $font['slug'];
            }
        }
        $parsed = wp_parse_url( $url );
        if ( ! empty( $parsed['query'] ) ) {
            parse_str( (string) $parsed['query'], $q );
            if ( ! empty( $q['family'] ) ) {
                $fam = (string) $q['family'];
                $fam = preg_replace( '/:.*/', '', $fam );
                $fam = str_replace( '+', ' ', $fam );
                foreach ( $this->get_google_fonts_catalog() as $font ) {
                    if ( strcasecmp( (string) ( $font['family'] ?? '' ), $fam ) === 0 ) {
                        return (string) $font['slug'];
                    }
                }
            }
        }
        return '__custom__';
    }

    // ─── Public API ───────────────────────────────────────────────────────────

    /** Return the full schema definition for the global settings. */
    public function get_schema(): array {
        static $schema = null;
        if ( $schema === null ) {
            $loader = static fn( string $f ) => include $f;
            $schema = $loader( $this->schema_file );
        }
        return is_array( $schema ) ? $schema : [];
    }

    /** Return current settings merged with schema defaults. */
    public function get_settings(): array {
        $raw    = get_option( self::OPTION_KEY, '' );
        $saved  = $raw ? (array) json_decode( $raw, true ) : [];
        return $this->merge_defaults( $saved );
    }

    /** Sanitise and persist new settings values. */
    public function save_settings( array $raw ): bool {
        $clean = $this->sanitize_settings( $raw );
        return update_option( self::OPTION_KEY, wp_json_encode( $clean ) );
    }

    // ─── Frontend output ──────────────────────────────────────────────────────

    /**
     * Output Google Fonts <link> tag if a URL is configured.
     * Hooked to wp_head and admin_head (priority 1, before styles).
     * Only allows fonts.googleapis.com URLs for security.
     */
    public function output_google_fonts(): void {
        $settings = $this->get_settings();
        $url      = trim( $settings['google_fonts_url'] ?? '' );
        if ( empty( $url ) ) {
            return;
        }
        // Security: only allow the official Google Fonts CDN.
        if ( ! preg_match( '#^https://fonts\.googleapis\.com/#', $url ) ) {
            return;
        }
        echo '<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>' . "\n"; // phpcs:ignore
        echo '<link rel="stylesheet" href="' . esc_url( $url ) . '">' . "\n"; // phpcs:ignore
    }

    /**
     * Output CSS custom properties + scoped custom CSS blocks.
     * Hooked to wp_head.
     */
    public function output_css_variables(): void {
        $css = $this->build_css_output();
        if ( $css !== '' ) {
            echo '<style id="hero-global-css">' . "\n" . $css . "\n</style>\n"; // phpcs:ignore
        }
    }

    /** Build the full CSS output string (used by output_css_variables and live preview). */
    public function build_css_output(): string {
        $settings = $this->get_settings();
        $schema   = $this->get_schema();

        $root_vars    = [];
        $custom_lines = [];

        foreach ( $schema['groups'] ?? [] as $group_id => $group ) {
            foreach ( $group['settings'] ?? [] as $field ) {
                $id    = $field['id'];
                $value = $settings[ $id ] ?? $field['default'] ?? '';

                if ( isset( $field['var'] ) ) {
                    // Append unit if numeric and unit defined.
                    $unit  = $field['unit'] ?? '';
                    $out   = is_numeric( $value ) && $unit ? $value . $unit : $value;
                    $root_vars[] = '  ' . esc_attr( $field['var'] ) . ': ' . esc_attr( $out ) . ';';
                } elseif ( $group_id === 'custom_css' ) {
                    // Raw CSS — sanitise injection before output.
                    $css_value = str_ireplace( '</style>', '', (string) $value );
                    if ( trim( $css_value ) === '' ) {
                        continue;
                    }
                    if ( $id === 'custom_css_header' ) {
                        $custom_lines[] = '.hero-header { ' . "\n" . $css_value . "\n" . ' }';
                    } elseif ( $id === 'custom_css_footer' ) {
                        $custom_lines[] = '.hero-footer { ' . "\n" . $css_value . "\n" . ' }';
                    } else {
                        // custom_css_global — no scope wrapper.
                        $custom_lines[] = $css_value;
                    }
                }
            }
        }

        $output = '';
        if ( ! empty( $root_vars ) ) {
            $output .= ':root {' . "\n" . implode( "\n", $root_vars ) . "\n" . '}';
        }
        if ( ! empty( $custom_lines ) ) {
            $output .= "\n" . implode( "\n", $custom_lines );
        }

        return $output;
    }

    // ─── Internal helpers ─────────────────────────────────────────────────────

    private function merge_defaults( array $saved ): array {
        $merged = [];
        foreach ( ( $this->get_schema()['groups'] ?? [] ) as $group ) {
            foreach ( $group['settings'] ?? [] as $field ) {
                $id             = $field['id'];
                $merged[ $id ]  = array_key_exists( $id, $saved ) ? $saved[ $id ] : ( $field['default'] ?? '' );
            }
        }
        if ( ( $merged['google_font_pick'] ?? '' ) === '' ) {
            $merged['google_font_pick'] = $this->infer_google_font_pick( $merged );
        }
        return $merged;
    }

    private function sanitize_settings( array $raw ): array {
        $clean  = [];
        $schema = $this->get_schema();

        foreach ( $schema['groups'] ?? [] as $group ) {
            foreach ( $group['settings'] ?? [] as $field ) {
                $id = $field['id'];
                if ( ! array_key_exists( $id, $raw ) ) {
                    continue;
                }

                $value = $raw[ $id ];

                switch ( $field['type'] ) {
                    case 'color':
                        $clean[ $id ] = sanitize_hex_color( $value ) ?? ( $field['default'] ?? '' );
                        break;
                    case 'number':
                    case 'range':
                        $clean[ $id ] = is_numeric( $value ) ? (float) $value : ( $field['default'] ?? 0 );
                        break;
                    case 'select':
                        $allowed      = wp_list_pluck( $field['options'] ?? [], 'value' );
                        $clean[ $id ] = in_array( $value, $allowed, true ) ? $value : ( $field['default'] ?? '' );
                        break;
                    case 'google_font':
                        $allowed_slugs = array_map(
                            static fn( array $f ): string => (string) ( $f['slug'] ?? '' ),
                            $this->get_google_fonts_catalog()
                        );
                        $allowed_slugs = array_merge( [ '', '__custom__' ], $allowed_slugs );
                        $v             = sanitize_text_field( wp_unslash( (string) $value ) );
                        $clean[ $id ]  = in_array( $v, $allowed_slugs, true ) ? $v : '';
                        break;
                    case 'checkbox':
                        $clean[ $id ] = (bool) $value;
                        break;
                    case 'textarea':
                        // Custom CSS — strip </style> but otherwise keep raw.
                        $clean[ $id ] = str_ireplace( '</style>', '', wp_unslash( (string) $value ) );
                        break;
                    case 'hidden':
                        if ( $id === 'google_fonts_url' ) {
                            $clean[ $id ] = esc_url_raw( trim( wp_unslash( (string) $value ) ) );
                        } else {
                            $clean[ $id ] = sanitize_text_field( wp_unslash( (string) $value ) );
                        }
                        break;
                    default:
                        $clean[ $id ] = sanitize_text_field( wp_unslash( (string) $value ) );
                }
            }
        }

        return $this->sync_google_font_after_sanitize( $clean );
    }

    /**
     * Sync google_fonts_url with google_font_pick after sanitise.
     *
     * @param array<string,mixed> $clean
     * @return array<string,mixed>
     */
    private function sync_google_font_after_sanitize( array $clean ): array {
        $pick = (string) ( $clean['google_font_pick'] ?? '' );
        if ( $pick === '' ) {
            $clean['google_fonts_url'] = '';
            return $clean;
        }
        if ( $pick === '__custom__' ) {
            $url = trim( (string) ( $clean['google_fonts_url'] ?? '' ) );
            if ( $url !== '' && ! preg_match( '#^https://fonts\.googleapis\.com/#', $url ) ) {
                $clean['google_fonts_url'] = '';
            } else {
                $clean['google_fonts_url'] = esc_url_raw( $url );
            }
            return $clean;
        }
        if ( $this->get_google_font_by_slug( $pick ) ) {
            $clean['google_fonts_url'] = $this->build_google_fonts_css_url_for_slug( $pick );
        }
        return $clean;
    }
}
