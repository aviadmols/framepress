<?php
/**
 * Hero Section — render template.
 *
 * Available variables (injected by HERO_Section_Renderer):
 *   @var array  $settings  Merged field values.
 *   @var array  $blocks    Prepared block instances.
 *   @var array  $section   [ 'id' => string, 'type' => string, 'source' => string ]
 */

defined( 'ABSPATH' ) || exit;

$min_height     = max( 200, (int) ( $settings['min_height'] ?? 520 ) );
$text_color     = esc_attr( $settings['text_color'] ?? '#ffffff' );
$overlay_color  = esc_attr( $settings['overlay_color'] ?? '#000000' );
$opacity        = min( 100, max( 0, (int) ( $settings['overlay_opacity'] ?? 40 ) ) ) / 100;
$content_align  = in_array( $settings['content_align'] ?? 'center', [ 'left', 'center', 'right' ], true )
    ? $settings['content_align']
    : 'center';

$bg_url = esc_url( $settings['background_image'] ?? '' );
$style  = 'min-height:' . $min_height . 'px; color:' . $text_color . ';';
$title_tag = hero_pick_tag( (string) ( $settings['title_tag'] ?? 'auto' ), 'h1' );
$subtitle_tag = hero_pick_tag( (string) ( $settings['subtitle_tag'] ?? 'auto' ), 'p' );
if ( $bg_url ) {
    $style .= ' background-image:url(' . $bg_url . '); background-size:cover; background-position:center;';
}
?>
<section class="fp-hero fp-hero--align-<?php echo esc_attr( $content_align ); ?>" style="<?php echo $style; // phpcs:ignore ?>">

    <?php if ( $bg_url && $opacity > 0 ) : ?>
    <div class="fp-hero__overlay" style="background:<?php echo $overlay_color; ?>; opacity:<?php echo $opacity; ?>;"></div>
    <?php endif; ?>

    <div class="fp-container">
        <div class="fp-hero__content">

            <?php if ( ! empty( $settings['title'] ) ) : ?>
            <<?php echo $title_tag; ?> class="fp-hero__title"><?php echo esc_html( $settings['title'] ); ?></<?php echo $title_tag; ?>>
            <?php endif; ?>

            <?php if ( ! empty( $settings['subtitle'] ) ) : ?>
            <<?php echo $subtitle_tag; ?> class="fp-hero__subtitle"><?php echo wp_kses_post( $settings['subtitle'] ); ?></<?php echo $subtitle_tag; ?>>
            <?php endif; ?>

            <?php if ( ! empty( $blocks ) ) : ?>
            <div class="fp-hero__actions">
                <?php foreach ( $blocks as $block ) : ?>
                    <?php if ( $block['type'] === 'button' ) : ?>
                    <a href="<?php echo esc_url( $block['settings']['url'] ?? '#' ); ?>"
                       class="fp-btn fp-btn--<?php echo esc_attr( $block['settings']['style'] ?? 'primary' ); ?>">
                        <?php echo esc_html( $block['settings']['label'] ?? 'Click here' ); ?>
                    </a>
                    <?php endif; ?>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>

        </div>
    </div>
</section>
