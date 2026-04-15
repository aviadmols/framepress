<?php
defined( 'ABSPATH' ) || exit;

$img_url    = esc_url( $settings['image'] ?? '' );
$height     = max( 150, (int) ( $settings['height'] ?? 400 ) );
$overlay    = esc_attr( $settings['overlay_color'] ?? '#000' );
$opacity    = min( 100, max( 0, (int) ( $settings['overlay_opacity'] ?? 30 ) ) ) / 100;
$text_color = esc_attr( $settings['text_color'] ?? '#fff' );
$align      = in_array( $settings['content_align'] ?? 'center', [ 'left', 'center', 'right' ], true )
    ? $settings['content_align'] : 'center';

$style = 'height:' . $height . 'px; color:' . $text_color . ';';
if ( $img_url ) {
    $style .= 'background-image:url(' . $img_url . ');background-size:cover;background-position:center;';
}
?>
<section class="fp-image-banner fp-image-banner--<?php echo esc_attr( $align ); ?>" style="<?php echo $style; // phpcs:ignore ?>">

    <?php if ( $opacity > 0 ) : ?>
    <div class="fp-image-banner__overlay" style="background:<?php echo $overlay; ?>;opacity:<?php echo $opacity; ?>;"></div>
    <?php endif; ?>

    <?php if ( ! empty( $settings['title'] ) || ! empty( $settings['subtitle'] ) || ! empty( $blocks ) ) : ?>
    <div class="fp-container">
        <div class="fp-image-banner__content">
            <?php if ( ! empty( $settings['title'] ) ) : ?>
            <h2 class="fp-image-banner__title"><?php echo esc_html( $settings['title'] ); ?></h2>
            <?php endif; ?>
            <?php if ( ! empty( $settings['subtitle'] ) ) : ?>
            <p class="fp-image-banner__subtitle"><?php echo wp_kses_post( $settings['subtitle'] ); ?></p>
            <?php endif; ?>
            <?php foreach ( $blocks as $block ) :
                if ( $block['type'] !== 'button' ) continue; ?>
            <a href="<?php echo esc_url( $block['settings']['url'] ?? '#' ); ?>"
               class="fp-btn fp-btn--<?php echo esc_attr( $block['settings']['style'] ?? 'primary' ); ?>">
                <?php echo esc_html( $block['settings']['label'] ?? 'Click' ); ?>
            </a>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endif; ?>

</section>
