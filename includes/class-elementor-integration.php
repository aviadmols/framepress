<?php
/**
 * FramePress Elementor Integration
 *
 * Registers one Elementor widget per FramePress section type (e.g. fp-hero, fp-faq)
 * plus the legacy widget `framepress-section` for older saved pages.
 * Optional instance data for blocks / legacy JSON is stored in wp_options as
 * framepress_el_{md5(post_id|elementor_element_id)}.
 *
 * The widget class lives in class-elementor-widget.php and is loaded only from
 * elementor/widgets/register so Elementor\Widget_Base exists (avoids fatals on wp-admin).
 *
 * Enable under FramePress → Global Settings → Integrations → Enable Elementor Widgets.
 */

defined( 'ABSPATH' ) || exit;

class FramePress_Elementor_Integration {

    /** @var bool */
    private static $initialized = false;

    public static function init(): void {
        if ( self::$initialized ) {
            return;
        }
        self::$initialized = true;

        add_action( 'elementor/elements/categories_registered', [ __CLASS__, 'register_category' ] );
        add_action( 'elementor/widgets/register', [ __CLASS__, 'register_widget' ] );
    }

    /**
     * Custom panel group so widgets appear under "FramePress" in the library.
     *
     * @param mixed $elements_manager \Elementor\Elements_Manager
     */
    public static function register_category( $elements_manager ): void {
        if ( ! is_object( $elements_manager ) || ! method_exists( $elements_manager, 'add_category' ) ) {
            return;
        }

        try {
            $elements_manager->add_category(
                'framepress',
                [
                    'title' => __( 'FramePress', 'framepress' ),
                    'icon'  => 'fa fa-plug',
                ]
            );
        } catch ( \Throwable $e ) {
            if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
                // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
                error_log( 'FramePress: Elementor add_category failed: ' . $e->getMessage() );
            }
        }
    }

    /**
     * @param \Elementor\Widgets_Manager $manager
     */
    public static function register_widget( $manager ): void {
        if ( ! is_object( $manager ) || ! method_exists( $manager, 'get_widget_types' ) || ! method_exists( $manager, 'register' ) ) {
            return;
        }

        require_once FRAMEPRESS_DIR . 'includes/class-elementor-widget.php';
        require_once FRAMEPRESS_DIR . 'includes/class-elementor-widget-legacy.php';

        $sections = FramePress_Section_Registry::get_instance()->get_all_sections();
        FramePress_Elementor_Section_Widget::set_schemas( $sections );

        foreach ( $sections as $schema ) {
            $type = isset( $schema['type'] ) ? sanitize_key( (string) $schema['type'] ) : '';
            if ( $type === '' ) {
                continue;
            }
            $name = 'fp-' . $type;
            if ( null !== $manager->get_widget_types( $name ) ) {
                continue;
            }
            $manager->register( new FramePress_Elementor_Section_Widget( [], [ 'fp_section_type' => $type ] ) );
        }

        if ( null === $manager->get_widget_types( 'framepress-section' ) ) {
            $manager->register( new FramePress_Elementor_Legacy_Section_Widget() );
        }
    }
}
