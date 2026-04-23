<?php
/**
 * HERO Elementor widget — one registered widget type per section schema.
 * Loaded only from elementor/widgets/register so Elementor\Widget_Base exists.
 */

defined( 'ABSPATH' ) || exit;

class Hero_Elementor_Section_Widget extends \Elementor\Widget_Base {

    /** @var array<string, array> */
    private static array $schemas = [];

    /** Cached widget name from constructor $data (avoid get_data() in get_name() — triggers sanitize_settings on null). */
    private ?string $fp_widget_name = null;

    /**
     * @param array<string, mixed> $data Element data.
     * @param array<string, mixed>|null $args Registration args (e.g. fp_section_type for prototype).
     */
    public function __construct( array $data = [], ?array $args = null ) {
        // Only coerce settings when the key exists (saved JSON). Empty $data for
        // registration prototype must stay empty so Elementor keeps type-instance mode.
        if ( array_key_exists( 'settings', $data ) && ! is_array( $data['settings'] ) ) {
            $data['settings'] = [];
        }
        if ( ! empty( $data['widgetType'] ) ) {
            $this->fp_widget_name = (string) $data['widgetType'];
        }
        parent::__construct( $data, $args );
    }

    /**
     * @param array<string, array> $sections Section schemas from Hero_Section_Registry::get_all_sections().
     */
    public static function set_schemas( array $sections ): void {
        self::$schemas = [];
        foreach ( $sections as $schema ) {
            $type = isset( $schema['type'] ) ? sanitize_key( (string) $schema['type'] ) : '';
            if ( $type !== '' ) {
                self::$schemas[ 'fp-' . $type ] = $schema;
            }
        }
    }

    public function get_name(): string {
        if ( $this->fp_widget_name !== null && $this->fp_widget_name !== '' ) {
            return $this->fp_widget_name;
        }
        $type = $this->get_default_args( 'fp_section_type' );
        if ( $type ) {
            return 'fp-' . sanitize_key( (string) $type );
        }
        return 'fp-unknown';
    }

    public function get_title(): string {
        $schema = $this->get_section_schema();
        $label  = $schema['label'] ?? '';
        if ( $label !== '' ) {
            return (string) $label;
        }
        return __( 'HERO Section', 'hero' );
    }

    public function get_icon(): string {
        return 'eicon-layout-settings';
    }

    public function get_categories(): array {
        return [ 'hero' ];
    }

    public function get_keywords(): array {
        $schema = $this->get_section_schema();
        $type   = $schema['type'] ?? '';
        return array_filter( [ 'hero', 'section', $type ] );
    }

    /**
     * @return array<string, mixed>
     */
    private function get_section_schema(): array {
        $name = $this->get_name();
        return self::$schemas[ $name ] ?? [];
    }

    protected function register_controls(): void {
        $schema = $this->get_section_schema();
        if ( empty( $schema['type'] ) ) {
            return;
        }

        $this->start_controls_section( 'content_section', [
            'label' => $schema['label'] ?? __( 'HERO Section', 'hero' ),
            'tab'   => \Elementor\Controls_Manager::TAB_CONTENT,
        ] );

        foreach ( $schema['settings'] ?? [] as $field ) {
            $this->register_field_control( $field );
        }

        $this->add_control( 'fp_edit_code_btn', [
            'type' => \Elementor\Controls_Manager::RAW_HTML,
            'raw'  => '<a href="' . esc_url( $this->build_edit_url( $schema['type'] ) ) . '" target="_blank" '
                . 'style="display:inline-block;margin-top:12px;padding:7px 16px;background:#1e1e2d;color:#fff;'
                . 'border-radius:5px;font-size:12px;font-weight:600;text-decoration:none;border:1px solid #444;">'
                . esc_html__( 'Edit Code', 'hero' ) . '</a>',
            'content_classes' => 'elementor-descriptor',
        ] );

        if ( ! empty( $schema['blocks']['allowed'] ) ) {
            $this->add_control( 'fp_blocks_notice', [
                'type'            => \Elementor\Controls_Manager::RAW_HTML,
                'raw'             => $this->render_blocks_notice( $schema ),
                'content_classes' => 'elementor-descriptor',
            ] );
        }

        $this->end_controls_section();
        $this->register_typography_controls();
    }

    private function register_typography_controls(): void {
        $this->start_controls_section( 'hero_style_typography', [
            'label' => __( 'Typography', 'hero' ),
            'tab'   => \Elementor\Controls_Manager::TAB_STYLE,
        ] );

        $this->add_control( 'fp_typo_note', [
            'type'            => \Elementor\Controls_Manager::RAW_HTML,
            'raw'             => '<p style="margin:0;color:#6d7175;font-size:12px;">'
                . esc_html__( 'Applies to the section text in preview and frontend. Use responsive controls per device.', 'hero' )
                . '</p>',
            'content_classes' => 'elementor-descriptor',
        ] );

        $this->add_control( 'fp_typo_font_family', [
            'label'       => __( 'Font Family', 'hero' ),
            'type'        => \Elementor\Controls_Manager::TEXT,
            'placeholder' => 'Inter, Arial, sans-serif',
            'default'     => '',
        ] );

        $this->add_control( 'fp_typo_font_weight', [
            'label'   => __( 'Font Weight', 'hero' ),
            'type'    => \Elementor\Controls_Manager::SELECT,
            'default' => '',
            'options' => [
                ''    => __( 'Default', 'hero' ),
                '300' => '300',
                '400' => '400',
                '500' => '500',
                '600' => '600',
                '700' => '700',
                '800' => '800',
            ],
        ] );

        $this->add_responsive_control( 'fp_typo_font_size', [
            'label'      => __( 'Font Size', 'hero' ),
            'type'       => \Elementor\Controls_Manager::SLIDER,
            'size_units' => [ 'px', 'em', 'rem' ],
            'range'      => [
                'px'  => [ 'min' => 10, 'max' => 120 ],
                'em'  => [ 'min' => 0.5, 'max' => 8, 'step' => 0.05 ],
                'rem' => [ 'min' => 0.5, 'max' => 8, 'step' => 0.05 ],
            ],
        ] );

        $this->add_responsive_control( 'fp_typo_line_height', [
            'label'      => __( 'Line Height', 'hero' ),
            'type'       => \Elementor\Controls_Manager::SLIDER,
            'size_units' => [ 'px', 'em', 'rem' ],
            'range'      => [
                'px'  => [ 'min' => 10, 'max' => 120 ],
                'em'  => [ 'min' => 1, 'max' => 5, 'step' => 0.05 ],
                'rem' => [ 'min' => 1, 'max' => 5, 'step' => 0.05 ],
            ],
        ] );

        $this->end_controls_section();
    }

    /**
     * @param array<string, mixed> $field Section schema field.
     */
    private function register_field_control( array $field ): void {
        $id    = isset( $field['id'] ) ? (string) $field['id'] : '';
        $type  = isset( $field['type'] ) ? (string) $field['type'] : 'text';
        $label = isset( $field['label'] ) ? (string) $field['label'] : $id;

        if ( $id === '' ) {
            return;
        }

        $control = [
            'label' => $label,
        ];

        switch ( $type ) {
            case 'text':
                $control['type']    = \Elementor\Controls_Manager::TEXT;
                $control['default'] = (string) ( $field['default'] ?? '' );
                break;
            case 'textarea':
                $control['type']    = \Elementor\Controls_Manager::TEXTAREA;
                $control['default'] = (string) ( $field['default'] ?? '' );
                break;
            case 'richtext':
                $control['type']    = \Elementor\Controls_Manager::WYSIWYG;
                $control['default'] = (string) ( $field['default'] ?? '' );
                break;
            case 'image':
                $control['type'] = \Elementor\Controls_Manager::MEDIA;
                $control['default'] = [
                    'url' => $this->default_as_string( $field['default'] ?? '' ),
                ];
                break;
            case 'color':
                $control['type']    = \Elementor\Controls_Manager::COLOR;
                $control['default'] = (string) ( $field['default'] ?? '' );
                break;
            case 'number':
                $control['type'] = \Elementor\Controls_Manager::NUMBER;
                if ( isset( $field['min'] ) ) {
                    $control['min'] = (float) $field['min'];
                }
                if ( isset( $field['max'] ) ) {
                    $control['max'] = (float) $field['max'];
                }
                $def = $field['default'] ?? 0;
                $control['default'] = is_numeric( $def ) ? (float) $def + 0 : 0;
                break;
            case 'range':
                $control['type']        = \Elementor\Controls_Manager::SLIDER;
                $control['size_units']  = [ 'px' ];
                $control['range']       = [
                    'px' => [
                        'min'  => (float) ( $field['min'] ?? 0 ),
                        'max'  => (float) ( $field['max'] ?? 100 ),
                        'step' => (float) ( $field['step'] ?? 1 ),
                    ],
                ];
                $def = $field['default'] ?? 0;
                $control['default'] = [
                    'unit' => 'px',
                    'size' => is_numeric( $def ) ? (float) $def + 0 : 0,
                ];
                break;
            case 'select':
                // Builder uses options_source (wp_menus, wp_categories) — no static options for Elementor SELECT.
                if ( ! empty( $field['options_source'] ) && empty( $field['options'] ) ) {
                    return;
                }
                $control['type']    = \Elementor\Controls_Manager::SELECT;
                $control['options'] = $this->select_options_for_elementor( $field['options'] ?? [] );
                $control['default'] = (string) ( $field['default'] ?? '' );
                break;
            case 'checkbox':
                $control['type']    = \Elementor\Controls_Manager::SWITCHER;
                $control['default'] = ! empty( $field['default'] ) ? 'yes' : '';
                break;
            case 'url':
                $control['type']    = \Elementor\Controls_Manager::URL;
                $def_url            = (string) ( $field['default'] ?? '' );
                $control['default'] = [
                    'url'         => $def_url,
                    'is_external' => false,
                    'nofollow'    => false,
                ];
                break;
            default:
                if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
                    // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
                    error_log( 'HERO Elementor: unsupported field type "' . $type . '" for control ' . $id );
                }
                return;
        }

        $this->add_control( $id, $control );
    }

    /**
     * @param list<array{value:string,label:string}>|array<int, mixed> $options
     * @return array<string, string>
     */
    private function select_options_for_elementor( array $options ): array {
        $out = [];
        foreach ( $options as $opt ) {
            if ( ! is_array( $opt ) ) {
                continue;
            }
            $v = isset( $opt['value'] ) ? (string) $opt['value'] : '';
            $l = isset( $opt['label'] ) ? (string) $opt['label'] : $v;
            if ( $v !== '' ) {
                $out[ $v ] = $l;
            }
        }
        return $out;
    }

    private function default_as_string( mixed $default ): string {
        return is_scalar( $default ) ? (string) $default : '';
    }

    /**
     * Stable storage key for this widget instance (same post + same Elementor element id).
     */
    private function get_storage_hash(): string {
        return md5( $this->get_main_post_id() . '|' . $this->get_id() );
    }

    private function get_option_key(): string {
        return 'hero_el_' . $this->get_storage_hash();
    }

    private function get_main_post_id(): int {
        if ( class_exists( '\Elementor\Plugin' ) ) {
            $doc = \Elementor\Plugin::$instance->documents->get_current();
            if ( $doc ) {
                return (int) $doc->get_main_id();
            }
        }
        return (int) get_the_ID();
    }

    /**
     * @return array<string, mixed>
     */
    private function load_stored_instance(): array {
        $schema = $this->get_section_schema();
        $type   = $schema['type'] ?? '';

        $key  = $this->get_option_key();
        $raw  = get_option( $key, '' );
        $data = $raw ? json_decode( $raw, true ) : [];

        if ( ! is_array( $data ) ) {
            $data = [];
        }

        $hash = $this->get_storage_hash();
        $defaults = [
            'id'         => sanitize_key( 'fp-el-' . substr( $hash, 0, 12 ) ),
            'type'       => $type,
            'settings'   => [],
            'blocks'     => [],
            'custom_css' => '',
            'enabled'    => true,
        ];

        $data = array_merge( $defaults, $data );
        $data['type'] = $type;
        if ( empty( $data['id'] ) ) {
            $data['id'] = $defaults['id'];
        }

        return $data;
    }

    /**
     * @param array<string, mixed> $schema
     */
    private function render_blocks_notice( array $schema ): string {
        $type = $schema['type'] ?? '';
        if ( $type === '' ) {
            return '';
        }
        $url = $this->build_edit_url( $type );
        return '<p style="color:#6d7175;font-size:12px;margin:8px 0 0;">'
            . esc_html__( 'Block content (buttons, columns, FAQ items, etc.) is edited in HERO.', 'hero' )
            . '</p>'
            . '<a href="' . esc_url( $url ) . '" target="_blank" '
            . 'style="display:inline-block;margin-top:8px;padding:7px 16px;background:#2c6ecb;color:#fff;border-radius:5px;font-size:12px;font-weight:600;text-decoration:none;">'
            . esc_html__( 'Edit in HERO', 'hero' ) . '</a>';
    }

    private function build_edit_url( string $section_type ): string {
        return admin_url(
            add_query_arg(
                [
                    'page' => 'hero-sections-mgr',
                    // sections-manager.php reads `?type=` and auto-opens that section files.
                    'type' => sanitize_key( $section_type ),
                ],
                'admin.php'
            )
        );
    }

    protected function render(): void {
        $schema = $this->get_section_schema();
        $type   = $schema['type'] ?? '';

        if ( $type === '' ) {
            if ( \Elementor\Plugin::$instance->editor->is_edit_mode() ) {
                echo '<div style="padding:20px;background:#f6f6f7;border:2px dashed #c9cccf;border-radius:6px;color:#6d7175;font-family:sans-serif;font-size:13px;line-height:1.5;">'
                    . '<strong>' . esc_html__( 'HERO', 'hero' ) . '</strong><br>'
                    . esc_html__(
                        'This block does not match a registered section. Remove it and add a HERO widget from the panel (e.g. Hero, FAQ), or enable HERO → Global Settings → Integrations.',
                        'hero'
                    )
                    . '</div>';
            }
            return;
        }

        $fp     = HERO::get_instance();
        $assets = $fp->assets;
        $assets->enqueue_one_section_type( $type );

        $instance = $this->build_render_instance();
        $html     = $fp->renderer->render_section( $instance );

        // phpcs:ignore WordPress.Security.EscapeOutput
        echo $html;
    }

    /**
     * Merge Elementor control values with stored option data (blocks, custom CSS).
     *
     * @return array<string, mixed>
     */
    private function build_render_instance(): array {
        $schema = $this->get_section_schema();
        $type   = $schema['type'] ?? '';

        $stored   = $this->load_stored_instance();
        $display  = $this->get_settings_for_display();
        $settings = is_array( $stored['settings'] ?? null ) ? $stored['settings'] : [];

        foreach ( $schema['settings'] ?? [] as $field ) {
            $id = $field['id'] ?? '';
            if ( $id === '' ) {
                continue;
            }
            $fid = (string) $id;
            if ( array_key_exists( $fid, $display ) ) {
                $settings[ $fid ] = $this->normalize_setting_for_fp( $field, $display[ $fid ] );
            }
        }

        $stored['type']     = $type;
        $stored['settings'] = $settings;
        $stored['fp_typography'] = $this->extract_typography_settings( $display );

        $custom_css = trim( (string) ( $stored['custom_css'] ?? '' ) );
        $typo_css   = $this->build_typography_css( $stored['id'], $stored['fp_typography'] );
        if ( $typo_css !== '' ) {
            $stored['custom_css'] = $custom_css !== '' ? ( $custom_css . "\n\n" . $typo_css ) : $typo_css;
        } else {
            $stored['custom_css'] = $custom_css;
        }

        return $stored;
    }

    /**
     * @param array<string, mixed> $display
     * @return array<string, mixed>
     */
    private function extract_typography_settings( array $display ): array {
        $keys = [
            'fp_typo_font_family',
            'fp_typo_font_weight',
            'fp_typo_font_size',
            'fp_typo_font_size_tablet',
            'fp_typo_font_size_mobile',
            'fp_typo_line_height',
            'fp_typo_line_height_tablet',
            'fp_typo_line_height_mobile',
        ];
        $out = [];
        foreach ( $keys as $key ) {
            if ( array_key_exists( $key, $display ) ) {
                $out[ $key ] = $display[ $key ];
            }
        }
        return $out;
    }

    /**
     * @param array<string, mixed> $typo
     */
    private function build_typography_css( string $section_id, array $typo ): string {
        $selector = '#hero-section-' . sanitize_key( $section_id );
        $family   = trim( (string) ( $typo['fp_typo_font_family'] ?? '' ) );
        $family   = preg_replace( '/[^a-zA-Z0-9,\-\s"\']/', '', $family ) ?? '';
        $weight   = trim( (string) ( $typo['fp_typo_font_weight'] ?? '' ) );
        $weight   = preg_replace( '/[^0-9]/', '', $weight ) ?? '';

        $desktop_size  = $this->slider_to_css_value( $typo['fp_typo_font_size'] ?? null );
        $tablet_size   = $this->slider_to_css_value( $typo['fp_typo_font_size_tablet'] ?? null );
        $mobile_size   = $this->slider_to_css_value( $typo['fp_typo_font_size_mobile'] ?? null );
        $desktop_lh    = $this->slider_to_css_value( $typo['fp_typo_line_height'] ?? null );
        $tablet_lh     = $this->slider_to_css_value( $typo['fp_typo_line_height_tablet'] ?? null );
        $mobile_lh     = $this->slider_to_css_value( $typo['fp_typo_line_height_mobile'] ?? null );

        $base_rules = [];
        if ( $family !== '' ) {
            $base_rules[] = 'font-family:' . $family;
        }
        if ( $weight !== '' ) {
            $base_rules[] = 'font-weight:' . $weight;
        }
        if ( $desktop_size !== '' ) {
            $base_rules[] = 'font-size:' . $desktop_size;
        }
        if ( $desktop_lh !== '' ) {
            $base_rules[] = 'line-height:' . $desktop_lh;
        }

        $css = '';
        if ( ! empty( $base_rules ) ) {
            $css .= $selector . ', ' . $selector . ' p, ' . $selector . ' li, ' . $selector . ' a, ' . $selector . ' span'
                . '{' . implode( ';', $base_rules ) . ';}';
        }
        if ( $tablet_size !== '' || $tablet_lh !== '' ) {
            $tablet_rules = [];
            if ( $tablet_size !== '' ) {
                $tablet_rules[] = 'font-size:' . $tablet_size;
            }
            if ( $tablet_lh !== '' ) {
                $tablet_rules[] = 'line-height:' . $tablet_lh;
            }
            $css .= '@media (max-width:1024px){'
                . $selector . ', ' . $selector . ' p, ' . $selector . ' li, ' . $selector . ' a, ' . $selector . ' span'
                . '{' . implode( ';', $tablet_rules ) . ';}}';
        }
        if ( $mobile_size !== '' || $mobile_lh !== '' ) {
            $mobile_rules = [];
            if ( $mobile_size !== '' ) {
                $mobile_rules[] = 'font-size:' . $mobile_size;
            }
            if ( $mobile_lh !== '' ) {
                $mobile_rules[] = 'line-height:' . $mobile_lh;
            }
            $css .= '@media (max-width:767px){'
                . $selector . ', ' . $selector . ' p, ' . $selector . ' li, ' . $selector . ' a, ' . $selector . ' span'
                . '{' . implode( ';', $mobile_rules ) . ';}}';
        }
        return $css;
    }

    private function slider_to_css_value( mixed $slider ): string {
        if ( ! is_array( $slider ) || ! array_key_exists( 'size', $slider ) ) {
            return '';
        }
        $size = $slider['size'];
        if ( ! is_numeric( $size ) ) {
            return '';
        }
        $unit = isset( $slider['unit'] ) ? (string) $slider['unit'] : 'px';
        if ( $unit === '' ) {
            return (string) ( 0 + $size );
        }
        return (string) ( 0 + $size ) . $unit;
    }

    /**
     * @param array<string, mixed> $field
     */
    private function normalize_setting_for_fp( array $field, mixed $value ): mixed {
        $type    = $field['type'] ?? 'text';
        $default = $field['default'] ?? null;

        switch ( $type ) {
            case 'image':
                if ( is_array( $value ) && isset( $value['url'] ) ) {
                    return (string) $value['url'];
                }
                return is_string( $value ) ? $value : (string) ( $default ?? '' );
            case 'range':
                if ( is_array( $value ) && isset( $value['size'] ) ) {
                    return (float) $value['size'];
                }
                return is_numeric( $value ) ? (float) $value : (float) ( $default ?? 0 );
            case 'checkbox':
                return $value === 'yes' || $value === true || $value === '1' || $value === 1;
            case 'url':
                if ( is_array( $value ) && isset( $value['url'] ) ) {
                    return (string) $value['url'];
                }
                return is_string( $value ) ? $value : '';
            case 'number':
                if ( $value === '' || $value === null ) {
                    return is_numeric( $default ) ? ( $default + 0 ) : 0;
                }
                return is_numeric( $value ) ? ( $value + 0 ) : (float) ( $default ?? 0 );
            case 'select':
                return $value !== null && $value !== '' ? (string) $value : (string) ( $default ?? '' );
            case 'richtext':
            case 'textarea':
            case 'text':
                if ( $value === null || $value === '' ) {
                    return (string) ( $default ?? '' );
                }
                return is_string( $value ) ? $value : (string) $value;
            case 'color':
                if ( $value === null || $value === '' ) {
                    return (string) ( $default ?? '' );
                }
                return is_string( $value ) ? $value : (string) $value;
            default:
                return $value;
        }
    }
}
