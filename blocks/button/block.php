<?php
/**
 * Button block render template.
 * Variables: $settings, $block_id
 */
defined( 'ABSPATH' ) || exit;

$target = ! empty( $settings['open_new_tab'] ) ? ' target="_blank" rel="noopener noreferrer"' : '';
?>
<a href="<?php echo esc_url( $settings['url'] ?? '#' ); ?>"
   class="fp-btn fp-btn--<?php echo esc_attr( $settings['style'] ?? 'primary' ); ?>"
   <?php echo $target; // phpcs:ignore ?>>
    <?php echo esc_html( $settings['label'] ?? 'Click here' ); ?>
</a>
