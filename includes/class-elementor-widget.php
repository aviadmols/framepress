<?php
/**
 * FramePress Elementor widget (loaded only when elementor/widgets/register runs,
 * so Elementor\Widget_Base is guaranteed to exist).
 */

defined( 'ABSPATH' ) || exit;

class FramePress_Elementor_Widget extends \Elementor\Widget_Base {

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
        return [ 'framepress', 'section', 'custom' ];
    }

    protected function register_controls(): void {
        $this->start_controls_section( 'content_section', [
            'label' => __( 'FramePress Section', 'framepress' ),
            'tab'   => \Elementor\Controls_Manager::TAB_CONTENT,
        ] );

        $section_options = $this->get_section_type_options();
        $this->add_control( 'section_type', [
            'label'   => __( 'Section Type', 'framepress' ),
            'type'    => \Elementor\Controls_Manager::SELECT,
            'default' => '',
            'options' => $section_options,
        ] );

        $this->add_control( 'edit_notice', [
            'type'            => \Elementor\Controls_Manager::RAW_HTML,
            'raw'             => $this->render_edit_notice(),
            'content_classes' => 'elementor-descriptor',
        ] );

        $this->end_controls_section();
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

    protected function render(): void {
        $settings     = $this->get_settings_for_display();
        $section_type = sanitize_key( $settings['section_type'] ?? '' );

        if ( empty( $section_type ) ) {
            if ( \Elementor\Plugin::$instance->editor->is_edit_mode() ) {
                echo '<div style="padding:24px;text-align:center;background:#f6f6f7;border:2px dashed #c9cccf;border-radius:6px;color:#6d7175;font-family:sans-serif;">'
                    . '<strong>FramePress Section</strong><br><small>' . esc_html__( 'Select a section type in the panel →', 'framepress' ) . '</small>'
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

    /**
     * @return array<string,mixed>
     */
    private function load_instance( string $section_type ): array {
        $key = $this->get_option_key();
        $raw = get_option( $key, '' );
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

    private function build_edit_url( string $section_type ): string {
        return admin_url(
            add_query_arg(
                [
                    'page'               => 'framepress',
                    'context'            => 'elementor-section',
                    'section_key'        => $this->get_storage_hash(),
                    'section_type'       => $section_type,
                    'elementor_post_id'  => $this->get_main_post_id(),
                ],
                'admin.php'
            )
        );
    }

    private function render_edit_notice(): string {
        $settings     = $this->get_settings();
        $section_type = sanitize_key( $settings['section_type'] ?? '' );

        if ( empty( $section_type ) ) {
            return '<p style="color:#6d7175;font-size:12px;margin:8px 0 0;">'
                . esc_html__( 'Choose a section type, then save the page once and click the button below to edit content in FramePress.', 'framepress' )
                . '</p>';
        }

        $url = $this->build_edit_url( $section_type );
        return '<a href="' . esc_url( $url ) . '" target="_blank" '
            . 'style="display:inline-block;margin-top:8px;padding:7px 16px;background:#2c6ecb;color:#fff;border-radius:5px;font-size:12px;font-weight:600;text-decoration:none;">'
            . esc_html__( 'Edit in FramePress', 'framepress' ) . '</a>';
    }
}
