<?php
defined( 'ABSPATH' ) || exit;

$raw_text  = $settings['text'] ?? '© {year} {site_name}. All rights reserved.';
$text      = str_replace(
    [ '{year}', '{site_name}' ],
    [ date( 'Y' ), get_bloginfo( 'name' ) ],
    $raw_text
);
$bg_color   = esc_attr( $settings['background_color'] ?? '#111' );
$text_color = esc_attr( $settings['text_color'] ?? '#888' );
$align      = in_array( $settings['text_align'] ?? 'center', [ 'left', 'center', 'right' ], true )
    ? $settings['text_align'] : 'center';
$text_tag   = hero_pick_tag( (string) ( $settings['text_tag'] ?? 'auto' ), 'p' );
?>
<div class="fp-footer-copyright"
     style="background:<?php echo $bg_color; ?>;color:<?php echo $text_color; ?>;text-align:<?php echo esc_attr( $align ); ?>;">
    <div class="fp-container">
        <<?php echo $text_tag; ?> class="fp-footer-copyright__text"><?php echo esc_html( $text ); ?></<?php echo $text_tag; ?>>
    </div>
</div>
