<?php
defined( 'ABSPATH' ) || exit;

$link    = esc_url( $settings['link_to'] ?: home_url( '/' ) );
$img     = esc_url( $settings['logo_image'] ?? '' );
$text    = esc_html( $settings['logo_text'] ?: get_bloginfo( 'name' ) );
$height  = max( 20, (int) ( $settings['logo_height'] ?? 40 ) );
$text_tag = hero_pick_tag( (string) ( $settings['logo_text_tag'] ?? 'auto' ), 'span' );
?>
<div class="fp-header-logo">
    <a href="<?php echo $link; ?>" class="fp-header-logo__link">
        <?php if ( $img ) : ?>
        <img src="<?php echo $img; ?>" alt="<?php echo $text; ?>" height="<?php echo $height; ?>" style="height:<?php echo $height; ?>px; width:auto;" loading="eager">
        <?php else : ?>
        <<?php echo $text_tag; ?> class="fp-header-logo__text"><?php echo $text; ?></<?php echo $text_tag; ?>>
        <?php endif; ?>
    </a>
</div>
