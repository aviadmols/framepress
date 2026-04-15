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
        add_filter( 'framepress_active_section_types', [ __CLASS__, 'filter_active_section_types' ] );
    }

    /**
     * Merge FramePress section types referenced by Elementor document data so
     * `wp_enqueue_scripts` can load `sections/{type}/style.css` before `wp_head`.
     * (Widget `render()` runs in the body — too late for `wp_enqueue_style` in the head.)
     *
     * @param string[] $types Existing types from post meta / header / footer.
     * @return string[]
     */
    public static function filter_active_section_types( array $types ): array {
        $post_id = (int) get_the_ID();
        if ( ! $post_id && class_exists( '\Elementor\Plugin' ) ) {
            $doc = \Elementor\Plugin::$instance->documents->get_current();
            if ( $doc ) {
                $post_id = (int) $doc->get_main_id();
            }
        }
        if ( $post_id <= 0 ) {
            return $types;
        }

        $from_el = self::collect_section_types_from_elementor_data( $post_id );
        if ( empty( $from_el ) ) {
            return $types;
        }

        return array_merge( $types, $from_el );
    }

    /**
     * Parse `_elementor_data` for fp-* widgets and legacy `framepress-section`.
     *
     * @return string[]
     */
    private static function collect_section_types_from_elementor_data( int $post_id ): array {
        $raw = get_post_meta( $post_id, '_elementor_data', true );
        if ( $raw === '' || $raw === null ) {
            return [];
        }
        if ( is_string( $raw ) ) {
            $data = json_decode( $raw, true );
        } else {
            $data = $raw;
        }
        if ( ! is_array( $data ) ) {
            return [];
        }

        return array_unique( array_filter( self::walk_elementor_elements_for_fp_types( $data ) ) );
    }

    /**
     * @param array<int, mixed> $elements
     * @return string[]
     */
    private static function walk_elementor_elements_for_fp_types( array $elements ): array {
        $out = [];
        foreach ( $elements as $el ) {
            if ( ! is_array( $el ) ) {
                continue;
            }
            $el_type     = isset( $el['elType'] ) ? (string) $el['elType'] : '';
            $widget_type = isset( $el['widgetType'] ) ? (string) $el['widgetType'] : '';

            if ( $el_type === 'widget' && $widget_type !== '' ) {
                if ( str_starts_with( $widget_type, 'fp-' ) ) {
                    $slug = substr( $widget_type, 3 );
                    if ( $slug !== '' ) {
                        $out[] = sanitize_key( $slug );
                    }
                } elseif ( $widget_type === 'framepress-section' ) {
                    $settings = isset( $el['settings'] ) && is_array( $el['settings'] ) ? $el['settings'] : [];
                    $st       = isset( $settings['section_type'] ) ? sanitize_key( (string) $settings['section_type'] ) : '';
                    if ( $st !== '' ) {
                        $out[] = $st;
                    }
                }
            }

            if ( ! empty( $el['elements'] ) && is_array( $el['elements'] ) ) {
                $out = array_merge( $out, self::walk_elementor_elements_for_fp_types( $el['elements'] ) );
            }
        }
        return $out;
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
            // Non-null $args keeps Elementor happy if prototype detection ever changes.
            $manager->register( new FramePress_Elementor_Legacy_Section_Widget( [], [ 'fp_legacy_prototype' => true ] ) );
        }
    }
}
