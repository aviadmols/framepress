<?php
defined( 'ABSPATH' ) || exit;

$width_map = [ 'narrow' => '680px', 'normal' => '900px', 'wide' => '100%' ];
$max_width  = $width_map[ $settings['content_width'] ?? 'narrow' ] ?? '680px';
$text_align = in_array( $settings['text_align'] ?? 'left', [ 'left', 'center' ], true ) ? $settings['text_align'] : 'left';
$bg         = esc_attr( $settings['background_color'] ?? '' );
$section_style = $bg ? 'background-color:' . $bg . ';' : '';
$content_tag = hero_pick_tag( (string) ( $settings['content_tag'] ?? 'auto' ), 'div' );
?>
<section class="fp-rich-text" <?php echo $section_style ? 'style="' . $section_style . '"' : ''; ?>>
    <div class="fp-container">
        <div class="fp-rich-text__inner" style="max-width:<?php echo esc_attr( $max_width ); ?>; text-align:<?php echo esc_attr( $text_align ); ?>;">
            <<?php echo $content_tag; ?>><?php echo wp_kses_post( $settings['content'] ?? '' ); ?></<?php echo $content_tag; ?>>
        </div>
    </div>
</section>
