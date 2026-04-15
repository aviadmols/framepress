<?php
/**
 * FramePress Elementor Integration
 *
 * Registers a single "FramePress Section" Elementor widget.
 * Each widget instance stores a unique section_id; settings are saved in
 * wp_options keyed by that ID and rendered by the FramePress renderer.
 *
 * Editing flow:
 *  1. User drags "FramePress Section" widget into Elementor.
 *  2. Selects a section type from the dropdown → unique ID auto-assigned.
 *  3. Clicks "Edit in FramePress" → opens FramePress builder (elementor-section context).
 *  4. User edits settings, saves → data stored in wp_options('framepress_el_{id}').
 *  5. Elementor widget reads that option and renders the HTML on the frontend.
 */

defined( 'ABSPATH' ) || exit;

class FramePress_Elementor_Integration {

    public static function init(): void {
        add_action( 'elementor/widgets/register', [ __CLASS__, 'register_widget' ] );
    }

    public static function register_widget( \Elementor\Widgets_Manager $manager ): void {
        $manager->register( new FramePress_Elementor_Widget() );
    }
}

// ─── Widget ───────────────────────────────────────────────────────────────────

class FramePress_Elementor_Widget extends \Elementor\Widget_Base {

    public function get_name(): string  { return 'framepress-section'; }
    public function get_title(): string { return 'FramePress Section'; }
    public function get_icon(): string  { return 'eicon-layout-settings'; }
    public function get_categories(): array { return [ 'general' ]; }
    public function get_keywords(): array   { return [ 'framepress', 'section', 'custom' ]; }

    // ─── Controls ─────────────────────────────────────────────────────────────

    protected function register_controls(): void {
        $this->start_controls_section( 'content_section', [
            'label' => __( 'FramePress Section', 'framepress' ),
            'tab'   => \Elementor\Controls_Manager::TAB_CONTENT,
        ] );

        // Hidden auto-generated section instance ID.
        $this->add_control( 'section_id', [
            'label'       => __( 'Section ID', 'framepress' ),
            'type'        => \Elementor\Controls_Manager::HIDDEN,
            'default'     => '',
        ] );

        // Section type selector — options populated from FramePress registry.
        $section_options = $this->get_section_type_options();
        $this->add_control( 'section_type', [
            'label'   => __( 'Section Type', 'framepress' ),
            'type'    => \Elementor\Controls_Manager::SELECT,
            'default' => '',
            'options' => $section_options,
        ] );

        // "Edit in FramePress" button — rendered as a raw HTML notice in the panel.
        $this->add_control( 'edit_notice', [
            'type'            => \Elementor\Controls_Manager::RAW_HTML,
            'raw'             => $this->render_edit_notice(),
            'content_classes' => 'elementor-descriptor',
        ] );

        $this->end_controls_section();
    }

    // ─── Render ───────────────────────────────────────────────────────────────

    protected function render(): void {
        $settings     = $this->get_settings_for_display();
        $section_type = sanitize_key( $settings['section_type'] ?? '' );
        $section_id   = sanitize_key( $settings['section_id']   ?? '' );

        // Auto-generate section_id if missing (first use).
        if ( empty( $section_id ) ) {
            $section_id = 'el-' . wp_generate_uuid4();
        }

        if ( empty( $section_type ) ) {
            if ( \Elementor\Plugin::$instance->editor->is_edit_mode() ) {
                echo '<div style="padding:24px;text-align:center;background:#f6f6f7;border:2px dashed #c9cccf;border-radius:6px;color:#6d7175;font-family:sans-serif;">'
                    . '<strong>FramePress Section</strong><br><small>Select a section type in the panel →</small>'
                    . '</div>';
            }
            return;
        }

        // Load saved section data.
        $raw      = get_option( 'framepress_el_' . $section_id, '' );
        $instance = $raw ? json_decode( $raw, true ) : [];

        // Ensure instance has required keys.
        $instance['id']   = $section_id;
        $instance['type'] = $section_type;
        if ( ! isset( $instance['settings'] ) ) $instance['settings'] = [];
        if ( ! isset( $instance['blocks'] ) )   $instance['blocks']   = [];
        if ( ! isset( $instance['enabled'] ) )  $instance['enabled']  = true;

        $fp = FramePress::get_instance();

        if ( \Elementor\Plugin::$instance->editor->is_edit_mode() ) {
            // In editor mode: show rendered section + edit overlay.
            $html     = $fp->renderer->render_section( $instance );
            $edit_url = $this->build_edit_url( $section_id, $section_type );
            echo $html; // phpcs:ignore WordPress.Security.EscapeOutput
            echo '<div style="position:absolute;top:8px;right:8px;z-index:999;">'
                . '<a href="' . esc_url( $edit_url ) . '" target="_blank" '
                . 'style="display:inline-block;padding:6px 14px;background:#2c6ecb;color:#fff;border-radius:5px;font-size:12px;font-weight:600;text-decoration:none;font-family:sans-serif;">'
                . '✏ Edit in FramePress</a></div>';
        } else {
            // Frontend: render normally.
            echo $fp->renderer->render_section( $instance ); // phpcs:ignore WordPress.Security.EscapeOutput
        }
    }

    // ─── Helpers ──────────────────────────────────────────────────────────────

    private function get_section_type_options(): array {
        $options = [ '' => '— Select section type —' ];
        if ( ! function_exists( 'FramePress' ) ) {
            return $options;
        }
        try {
            $schemas = FramePress::get_instance()->section_registry->get_all_sections();
            foreach ( $schemas as $schema ) {
                $type  = $schema['type']  ?? '';
                $label = $schema['label'] ?? $type;
                if ( $type ) {
                    $options[ $type ] = $label;
                }
            }
        } catch ( \Throwable $e ) {
            // Registry not ready yet — return empty list.
        }
        return $options;
    }

    private function build_edit_url( string $section_id, string $section_type ): string {
        return admin_url( add_query_arg( [
            'page'         => 'framepress',
            'context'      => 'elementor-section',
            'section_id'   => $section_id,
            'section_type' => $section_type,
        ], 'admin.php' ) );
    }

    private function render_edit_notice(): string {
        $settings     = $this->get_settings();
        $section_id   = sanitize_key( $settings['section_id']   ?? '' );
        $section_type = sanitize_key( $settings['section_type'] ?? '' );

        if ( empty( $section_type ) ) {
            return '<p style="color:#6d7175;font-size:12px;margin:8px 0 0;">Choose a section type above, then save the page once to generate an ID, and click <strong>Edit in FramePress</strong>.</p>';
        }

        if ( empty( $section_id ) ) {
            return '<p style="color:#e68000;font-size:12px;margin:8px 0 0;">Save the page once to generate the section ID, then you can edit it.</p>';
        }

        $url = $this->build_edit_url( $section_id, $section_type );
        return '<a href="' . esc_url( $url ) . '" target="_blank" '
            . 'style="display:inline-block;margin-top:8px;padding:7px 16px;background:#2c6ecb;color:#fff;border-radius:5px;font-size:12px;font-weight:600;text-decoration:none;">'
            . '✏ Edit in FramePress</a>';
    }
}
