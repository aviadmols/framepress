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

    public static function init(): void {
        add_action( 'elementor/elements/categories_registered', [ __CLASS__, 'register_category' ] );
        add_action( 'elementor/widgets/register', [ __CLASS__, 'register_widget' ] );
    }

    /**
     * Custom panel group so the widget appears under "FramePress" in the library.
     *
     * @param mixed $elements_manager \Elementor\Elements_Manager
     */
    public static function register_category( $elements_manager ): void {
        $elements_manager->add_category(
            'framepress',
            [
                'title' => __( 'FramePress', 'framepress' ),
                'icon'  => 'fa fa-plug',
            ]
        );
    }

    /**
     * @param mixed $manager \Elementor\Widgets_Manager
     */
    public static function register_widget( $manager ): void {
        require_once FRAMEPRESS_DIR . 'includes/class-elementor-widget.php';
        $manager->register( new FramePress_Elementor_Widget() );
    }
}
