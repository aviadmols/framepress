<?php
/**
 * FramePress Elementor widget — one registered widget type per section schema.
 * Loaded only from elementor/widgets/register so Elementor\Widget_Base exists.
 */

defined( 'ABSPATH' ) || exit;

class FramePress_Elementor_Section_Widget extends \Elementor\Widget_Base {

    /** @var array<string, array> */
    private static array $schemas = [];

    /** Cached widget name from constructor $data (avoid get_data() in get_name() — triggers sanitize_settings on null). */
    private ?string $fp_widget_name = null;

    /**
     * @param array<string, mixed> $data Element data.
     * @param array<string, mixed>|null $args Registration args (e.g. fp_section_type for prototype).
     */
    public function __construct( array $data = [], ?array $args = null ) {
        if ( ! isset( $data['settings'] ) || ! is_array( $data['settings'] ) ) {
            $data['settings'] = [];
        }
        if ( ! empty( $data['widgetType'] ) ) {
            $this->fp_widget_name = (string) $data['widgetType'];
        }
        parent::__construct( $data, $args );
    }

    /**
     * @param array<string, array> $sections Section schemas from FramePress_Section_Registry::get_all_sections().
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
        return __( 'FramePress Section', 'framepress' );
    }

    public function get_icon(): string {
        return 'eicon-layout-settings';
    }

    public function get_categories(): array {
        return [ 'framepress' ];
    }

    public function get_keywords(): array {
        $schema = $this->get_section_schema();
        $type   = $schema['type'] ?? '';
        return array_filter( [ 'framepress', 'section', $type ] );
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
            'label' => $schema['label'] ?? __( 'FramePress Section', 'framepress' ),
            'tab'   => \Elementor\Controls_Manager::TAB_CONTENT,
        ] );

        foreach ( $schema['settings'] ?? [] as $field ) {
            $this->register_field_control( $field );
        }

        if ( ! empty( $schema['blocks']['allowed'] ) ) {
            $this->add_control( 'fp_blocks_notice', [
                'type'            => \Elementor\Controls_Manager::RAW_HTML,
                'raw'             => $this->render_blocks_notice( $schema ),
                'content_classes' => 'elementor-descriptor',
            ] );
        }

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
                    error_log( 'FramePress Elementor: unsupported field type "' . $type . '" for control ' . $id );
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
        return 'framepress_el_' . $this->get_storage_hash();
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
            . esc_html__( 'Block content (buttons, columns, FAQ items, etc.) is edited in FramePress.', 'framepress' )
            . '</p>'
            . '<a href="' . esc_url( $url ) . '" target="_blank" '
            . 'style="display:inline-block;margin-top:8px;padding:7px 16px;background:#2c6ecb;color:#fff;border-radius:5px;font-size:12px;font-weight:600;text-decoration:none;">'
            . esc_html__( 'Edit in FramePress', 'framepress' ) . '</a>';
    }

    private function build_edit_url( string $section_type ): string {
        return admin_url(
            add_query_arg(
                [
                    'page'              => 'framepress',
                    'context'           => 'elementor-section',
                    'section_key'        => $this->get_storage_hash(),
                    'section_type'       => $section_type,
                    'elementor_post_id'  => $this->get_main_post_id(),
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
                    . '<strong>' . esc_html__( 'FramePress', 'framepress' ) . '</strong><br>'
                    . esc_html__(
                        'This block does not match a registered section. Remove it and add a FramePress widget from the panel (e.g. Hero, FAQ), or enable FramePress → Global Settings → Integrations.',
                        'framepress'
                    )
                    . '</div>';
            }
            return;
        }

        $fp     = FramePress::get_instance();
        $assets = $fp->assets;
        $assets->enqueue_one_section_type( $type );

        $instance = $this->build_render_instance();
        $html     = $fp->renderer->render_section( $instance );

        if ( \Elementor\Plugin::$instance->editor->is_edit_mode() ) {
            // phpcs:ignore WordPress.Security.EscapeOutput
            echo '<div style="position:relative;">' . $html;
            $edit_url = $this->build_edit_url( $type );
            echo '<div style="position:absolute;top:8px;right:8px;z-index:999;">'
                . '<a href="' . esc_url( $edit_url ) . '" target="_blank" '
                . 'style="display:inline-block;padding:6px 14px;background:#2c6ecb;color:#fff;border-radius:5px;font-size:12px;font-weight:600;text-decoration:none;font-family:sans-serif;">'
                . esc_html__( 'Edit in FramePress', 'framepress' ) . '</a></div></div>';
        } else {
            // phpcs:ignore WordPress.Security.EscapeOutput
            echo $html;
        }
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

        return $stored;
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
