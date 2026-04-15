<?php
defined( 'ABSPATH' ) || exit;

$bg_color   = esc_attr( $settings['background_color'] ?? '#1a1a1f' );
$text_color = esc_attr( $settings['text_color'] ?? '#ccc' );
$columns    = in_array( $settings['columns'] ?? '4', [ '2', '3', '4' ], true ) ? $settings['columns'] : '4';
?>
<div class="fp-footer-columns fp-footer-columns--cols-<?php echo esc_attr( $columns ); ?>"
     style="background:<?php echo $bg_color; ?>;color:<?php echo $text_color; ?>;">
    <div class="fp-container">
        <div class="fp-footer-columns__grid">
            <?php foreach ( $blocks as $block ) :
                if ( $block['type'] !== 'footer-column' ) continue; ?>
            <div class="fp-footer-columns__col">
                <?php if ( ! empty( $block['settings']['heading'] ) ) : ?>
                <h4 class="fp-footer-columns__heading"><?php echo esc_html( $block['settings']['heading'] ); ?></h4>
                <?php endif; ?>
                <div class="fp-footer-columns__content">
                    <?php echo wp_kses_post( $block['settings']['content'] ?? '' ); ?>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>
