<?php
/**
 * FramePress Global Settings
 *
 * Manages sitewide design tokens (colors, typography, spacing, buttons, custom CSS).
 * Settings are stored as JSON in wp_options and output as CSS custom properties
 * on :root on every frontend page.
 */

defined( 'ABSPATH' ) || exit;

class FramePress_Global_Settings {

    private const OPTION_KEY   = 'framepress_global_settings';
    private string $schema_file;

    public function __construct() {
        $this->schema_file = FRAMEPRESS_DIR . 'global-settings/schema.php';
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
     * Output CSS custom properties + scoped custom CSS blocks.
     * Hooked to wp_head.
     */
    public function output_css_variables(): void {
        $css = $this->build_css_output();
        if ( $css !== '' ) {
            echo '<style id="framepress-global-css">' . "\n" . $css . "\n</style>\n"; // phpcs:ignore
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
                        $custom_lines[] = '.framepress-header { ' . "\n" . $css_value . "\n" . ' }';
                    } elseif ( $id === 'custom_css_footer' ) {
                        $custom_lines[] = '.framepress-footer { ' . "\n" . $css_value . "\n" . ' }';
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
                    case 'checkbox':
                        $clean[ $id ] = (bool) $value;
                        break;
                    case 'textarea':
                        // Custom CSS — strip </style> but otherwise keep raw.
                        $clean[ $id ] = str_ireplace( '</style>', '', wp_unslash( (string) $value ) );
                        break;
                    default:
                        $clean[ $id ] = sanitize_text_field( wp_unslash( (string) $value ) );
                }
            }
        }

        return $clean;
    }
}
