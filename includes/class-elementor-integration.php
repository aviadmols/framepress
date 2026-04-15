<?php
/**
 * FramePress Elementor Integration
 *
 * Registers a "FramePress Section" Elementor widget. Each widget instance is stored
 * in wp_options as framepress_el_{md5(post_id|elementor_element_id)} with JSON
 * instance data (same shape as native FramePress sections).
 *
 * The widget class lives in class-elementor-widget.php and is loaded only from
 * elementor/widgets/register so Elementor\Widget_Base exists (avoids fatals on wp-admin).
 *
 * Editing: open FramePress builder from the widget panel (FramePress → Elementor section).
 */

defined( 'ABSPATH' ) || exit;

class FramePress_Elementor_Integration {

    private const WIDGET_NAME = 'framepress-section';

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
     * Custom panel group so the widget appears under "FramePress" in the library.
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
        if ( ! is_object( $manager ) || ! method_exists( $manager, 'get_widget_types' ) ) {
            return;
        }

        // Avoid duplicate registration if hook runs more than once (e.g. Elementor 4 editor flows).
        $existing = $manager->get_widget_types( self::WIDGET_NAME );
        if ( null !== $existing ) {
            return;
        }

        require_once FRAMEPRESS_DIR . 'includes/class-elementor-widget.php';
        $manager->register( new FramePress_Elementor_Widget() );
    }
}
