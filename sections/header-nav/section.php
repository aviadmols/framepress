<?php
defined( 'ABSPATH' ) || exit;

$menu_id = absint( $settings['menu'] ?? 0 );
$align   = in_array( $settings['align'] ?? 'right', [ 'left', 'center', 'right' ], true ) ? $settings['align'] : 'right';
?>
<nav class="fp-header-nav fp-header-nav--<?php echo esc_attr( $align ); ?>" aria-label="<?php esc_attr_e( 'Main navigation', 'framepress' ); ?>">
    <?php
    if ( $menu_id ) {
        wp_nav_menu( [
            'menu'            => $menu_id,
            'menu_class'      => 'fp-header-nav__list',
            'container'       => false,
            'fallback_cb'     => false,
        ] );
    } else {
        wp_nav_menu( [
            'theme_location'  => 'primary',
            'menu_class'      => 'fp-header-nav__list',
            'container'       => false,
            'fallback_cb'     => false,
        ] );
    }
    ?>
</nav>
