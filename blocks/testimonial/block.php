<?php
defined( 'ABSPATH' ) || exit;
?>
<blockquote class="fp-testimonial">
    <?php if ( ! empty( $settings['avatar'] ) ) : ?>
    <img class="fp-testimonial__avatar" src="<?php echo esc_url( $settings['avatar'] ); ?>" alt="<?php echo esc_attr( $settings['author'] ?? '' ); ?>" loading="lazy">
    <?php endif; ?>
    <?php if ( ! empty( $settings['quote'] ) ) : ?>
    <p class="fp-testimonial__quote">"<?php echo esc_html( $settings['quote'] ); ?>"</p>
    <?php endif; ?>
    <footer class="fp-testimonial__footer">
        <?php if ( ! empty( $settings['author'] ) ) : ?>
        <cite class="fp-testimonial__author"><?php echo esc_html( $settings['author'] ); ?></cite>
        <?php endif; ?>
        <?php if ( ! empty( $settings['role'] ) ) : ?>
        <span class="fp-testimonial__role"><?php echo esc_html( $settings['role'] ); ?></span>
        <?php endif; ?>
    </footer>
</blockquote>
