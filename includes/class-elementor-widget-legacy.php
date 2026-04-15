<?php
/**
 * Legacy Elementor widget `framepress-section` — backward compatibility for pages
 * saved before per-section widgets (fp-hero, fp-faq, …). Content is stored in
 * wp_options (framepress_el_*) and edited via FramePress; panel offers section type
 * selection and link to FramePress. For inline text in Elementor, use fp-* widgets.
 */

defined( 'ABSPATH' ) || exit;

class FramePress_Elementor_Legacy_Section_Widget extends \Elementor\Widget_Base {

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
        return 'framepress-section';
    }

    public function get_title(): string {
        return __( 'FramePress Section', 'framepress' );
    }

    public function get_icon(): string {
        return 'eicon-layout-settings';
    }

    public function get_categories(): array {
        return [ 'framepress' ];
    }

    public function get_keywords(): array {
        return [ 'framepress', 'section', 'legacy' ];
    }

    protected function register_controls(): void {
        $this->start_controls_section( 'content_section', [
            'label' => __( 'FramePress Section', 'framepress' ),
            'tab'   => \Elementor\Controls_Manager::TAB_CONTENT,
        ] );

        $this->add_control( 'section_type', [
            'label'   => __( 'Section Type', 'framepress' ),
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
        $options = [ '' => '— ' . __( 'Select section type', 'framepress' ) . ' —' ];
        if ( ! function_exists( 'FramePress' ) ) {
            return $options;
        }
        try {
            $schemas = FramePress::get_instance()->section_registry->get_all_sections();
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
                'To edit texts directly in Elementor, add a section widget by name (Hero, FAQ, …) from the FramePress category. This legacy widget loads content from FramePress storage.',
                'framepress'
            )
            . '</p>';
    }

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

    private function build_edit_url( string $section_type ): string {
        return admin_url(
            add_query_arg(
                [
                    'page'              => 'framepress',
                    'context'           => 'elementor-section',
                    'section_key'       => $this->get_storage_hash(),
                    'section_type'      => $section_type,
                    'elementor_post_id' => $this->get_main_post_id(),
                ],
                'admin.php'
            )
        );
    }

    /**
     * Panel notice only — must not call get_settings() here: register_controls() runs before
     * the control stack is complete; Elementor 4+ sanitizing settings early triggers
     * Undefined array key "id" in Controls_Stack. The canvas overlay still shows Edit in FramePress.
     */
    private function render_edit_notice(): string {
        return '<p style="color:#6d7175;font-size:12px;margin:8px 0 0;">'
            . esc_html__(
                'Choose a section type, then save or update the page. Use the “Edit in FramePress” button on the live preview below.',
                'framepress'
            )
            . '</p>';
    }

    protected function render(): void {
        $settings     = $this->get_settings_for_display();
        $section_type = sanitize_key( $settings['section_type'] ?? '' );

        if ( $section_type === '' ) {
            if ( \Elementor\Plugin::$instance->editor->is_edit_mode() ) {
                echo '<div style="padding:24px;text-align:center;background:#f6f6f7;border:2px dashed #c9cccf;border-radius:6px;color:#6d7175;font-family:sans-serif;">'
                    . '<strong>' . esc_html__( 'FramePress Section', 'framepress' ) . '</strong><br><small>'
                    . esc_html__( 'Select a section type in the panel, then save.', 'framepress' )
                    . '</small></div>';
            }
            return;
        }

        $registry = FramePress_Section_Registry::get_instance();
        $schema   = $registry->get_section( $section_type );
        if ( null === $schema || empty( $schema['type'] ) ) {
            if ( \Elementor\Plugin::$instance->editor->is_edit_mode() ) {
                echo '<div style="padding:16px;background:#fff3cd;border:1px solid #ffc107;border-radius:6px;color:#856404;font-family:sans-serif;font-size:13px;">'
                    . esc_html__( 'Unknown section type. Pick another type or reinstall sections.', 'framepress' )
                    . '</div>';
            }
            return;
        }

        $fp     = FramePress::get_instance();
        $assets = $fp->assets;
        $assets->enqueue_one_section_type( $section_type );

        $instance = $this->load_instance( $section_type );
        $html     = $fp->renderer->render_section( $instance );

        if ( \Elementor\Plugin::$instance->editor->is_edit_mode() ) {
            // phpcs:ignore WordPress.Security.EscapeOutput
            echo '<div style="position:relative;">' . $html;
            $edit_url = $this->build_edit_url( $section_type );
            echo '<div style="position:absolute;top:8px;right:8px;z-index:999;">'
                . '<a href="' . esc_url( $edit_url ) . '" target="_blank" '
                . 'style="display:inline-block;padding:6px 14px;background:#2c6ecb;color:#fff;border-radius:5px;font-size:12px;font-weight:600;text-decoration:none;font-family:sans-serif;">'
                . esc_html__( 'Edit in FramePress', 'framepress' ) . '</a></div></div>';
        } else {
            // phpcs:ignore WordPress.Security.EscapeOutput
            echo $html;
        }
    }
}
