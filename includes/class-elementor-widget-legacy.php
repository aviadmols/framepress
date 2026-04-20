<?php
/**
 * Legacy Elementor widget `hero-section` — backward compatibility for pages
 * saved before per-section widgets (fp-hero, fp-faq, …). Content is stored in
 * wp_options (hero_el_*) and edited via HERO; panel offers section type
 * selection and link to HERO. For inline text in Elementor, use fp-* widgets.
 */

defined( 'ABSPATH' ) || exit;

class Hero_Elementor_Legacy_Section_Widget extends \Elementor\Widget_Base {

    /**
     * @param array<string, mixed> $data Element data.
     * @param array<string, mixed>|null $args Registration args.
     */
    public function __construct( array $data = [], ?array $args = null ) {
        // Only normalize when `settings` is present (saved element). Do not inject
        // settings into an empty $data[] — that makes $data truthy and Elementor treats
        // the instance as "full" and requires non-null $args (Widget_Base constructor).
        if ( array_key_exists( 'settings', $data ) && ! is_array( $data['settings'] ) ) {
            $data['settings'] = [];
        }
        parent::__construct( $data, $args );
    }

    public function get_name(): string {
        return 'hero-section';
    }

    public function get_title(): string {
        return __( 'HERO Section', 'hero' );
    }

    public function get_icon(): string {
        return 'eicon-layout-settings';
    }

    public function get_categories(): array {
        return [ 'hero' ];
    }

    public function get_keywords(): array {
        return [ 'hero', 'section', 'legacy' ];
    }

    protected function register_controls(): void {
        $this->start_controls_section( 'content_section', [
            'label' => __( 'HERO Section', 'hero' ),
            'tab'   => \Elementor\Controls_Manager::TAB_CONTENT,
        ] );

        $this->add_control( 'section_type', [
            'label'   => __( 'Section Type', 'hero' ),
            'type'    => \Elementor\Controls_Manager::SELECT,
            'default' => '',
            'options' => $this->get_section_type_options(),
        ] );

        $this->add_control( 'fp_legacy_notice', [
            'type'            => \Elementor\Controls_Manager::RAW_HTML,
            'raw'             => $this->render_legacy_notice_html(),
            'content_classes' => 'elementor-descriptor',
        ] );

        $this->add_control( 'edit_notice', [
            'type'            => \Elementor\Controls_Manager::RAW_HTML,
            'raw'             => $this->render_edit_notice(),
            'content_classes' => 'elementor-descriptor',
        ] );

        $this->end_controls_section();
    }

    /**
     * @return array<string, string>
     */
    private function get_section_type_options(): array {
        $options = [ '' => '— ' . __( 'Select section type', 'hero' ) . ' —' ];
        if ( ! function_exists( 'HERO' ) ) {
            return $options;
        }
        try {
            $schemas = HERO::get_instance()->section_registry->get_all_sections();
            foreach ( $schemas as $schema ) {
                $type  = $schema['type'] ?? '';
                $label = $schema['label'] ?? $type;
                if ( $type ) {
                    $options[ $type ] = $label;
                }
            }
        } catch ( \Throwable $e ) {
            return $options;
        }
        return $options;
    }

    private function render_legacy_notice_html(): string {
        return '<p style="color:#6d7175;font-size:12px;margin:0 0 8px;">'
            . esc_html__(
                'To edit texts directly in Elementor, add a section widget by name (Hero, FAQ, …) from the HERO category. This legacy widget loads content from HERO storage.',
                'hero'
            )
            . '</p>';
    }

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
    private function load_instance( string $section_type ): array {
        $key  = $this->get_option_key();
        $raw  = get_option( $key, '' );
        $data = $raw ? json_decode( $raw, true ) : [];

        if ( ! is_array( $data ) ) {
            $data = [];
        }

        $hash = $this->get_storage_hash();
        $defaults = [
            'id'         => sanitize_key( 'fp-el-' . substr( $hash, 0, 12 ) ),
            'type'       => $section_type,
            'settings'   => [],
            'blocks'     => [],
            'custom_css' => '',
            'enabled'    => true,
        ];

        $data = array_merge( $defaults, $data );
        $data['type'] = $section_type;
        if ( empty( $data['id'] ) ) {
            $data['id'] = $defaults['id'];
        }

        return $data;
    }

    /**
     * Panel notice only — must not call get_settings() here: register_controls() runs before
     * the control stack is complete; Elementor 4+ sanitizing settings early triggers
     * Undefined array key "id" in Controls_Stack.
     */
    private function render_edit_notice(): string {
        return '<p style="color:#6d7175;font-size:12px;margin:8px 0 0;">'
            . esc_html__(
                'Choose a section type, then save or update the page. Edit stored content in HERO from the WordPress admin.',
                'hero'
            )
            . '</p>';
    }

    protected function render(): void {
        $settings     = $this->get_settings_for_display();
        $section_type = sanitize_key( $settings['section_type'] ?? '' );

        if ( $section_type === '' ) {
            if ( \Elementor\Plugin::$instance->editor->is_edit_mode() ) {
                echo '<div style="padding:24px;text-align:center;background:#f6f6f7;border:2px dashed #c9cccf;border-radius:6px;color:#6d7175;font-family:sans-serif;">'
                    . '<strong>' . esc_html__( 'HERO Section', 'hero' ) . '</strong><br><small>'
                    . esc_html__( 'Select a section type in the panel, then save.', 'hero' )
                    . '</small></div>';
            }
            return;
        }

        $registry = Hero_Section_Registry::get_instance();
        $schema   = $registry->get_section( $section_type );
        if ( null === $schema || empty( $schema['type'] ) ) {
            if ( \Elementor\Plugin::$instance->editor->is_edit_mode() ) {
                echo '<div style="padding:16px;background:#fff3cd;border:1px solid #ffc107;border-radius:6px;color:#856404;font-family:sans-serif;font-size:13px;">'
                    . esc_html__( 'Unknown section type. Pick another type or reinstall sections.', 'hero' )
                    . '</div>';
            }
            return;
        }

        $fp     = HERO::get_instance();
        $assets = $fp->assets;
        $assets->enqueue_one_section_type( $section_type );

        $instance = $this->load_instance( $section_type );
        $html     = $fp->renderer->render_section( $instance );

        // phpcs:ignore WordPress.Security.EscapeOutput
        echo $html;
    }
}
