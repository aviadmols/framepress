<?php
defined( 'ABSPATH' ) || exit;

$target = ! empty( $settings['open_new_tab'] ) ? ' target="_blank" rel="noopener noreferrer"' : '';
?>
<div class="fp-header-cta">
    <a href="<?php echo esc_url( $settings['url'] ?? '#' ); ?>"
       class="fp-btn fp-btn--<?php echo esc_attr( $settings['style'] ?? 'primary' ); ?>"
       <?php echo $target; // phpcs:ignore ?>>
        <?php echo esc_html( $settings['label'] ?? 'Get Started' ); ?>
    </a>
</div>
