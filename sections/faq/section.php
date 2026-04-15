<?php
defined( 'ABSPATH' ) || exit;

$layout = $settings['layout'] ?? 'accordion';
$bg     = esc_attr( $settings['background_color'] ?? '' );
$section_style = $bg ? 'background-color:' . $bg . ';' : '';
?>
<section class="fp-faq fp-faq--<?php echo esc_attr( $layout ); ?>" <?php echo $section_style ? 'style="' . $section_style . '"' : ''; ?>>
    <div class="fp-container">

        <?php if ( ! empty( $settings['title'] ) ) : ?>
        <h2 class="fp-faq__title"><?php echo esc_html( $settings['title'] ); ?></h2>
        <?php endif; ?>

        <?php if ( ! empty( $blocks ) ) : ?>
        <div class="fp-faq__list" <?php echo $layout === 'accordion' ? 'role="list"' : ''; ?>>
            <?php foreach ( $blocks as $index => $block ) :
                if ( $block['type'] !== 'faq-item' ) continue;
                $item_id = 'faq-item-' . esc_attr( $block['id'] );
            ?>
            <div class="fp-faq__item" <?php echo $layout === 'accordion' ? 'role="listitem"' : ''; ?>>
                <?php if ( $layout === 'accordion' ) : ?>
                <button
                    class="fp-faq__question"
                    aria-expanded="false"
                    aria-controls="<?php echo $item_id; ?>"
                    onclick="fpFaqToggle(this)"
                >
                    <?php echo esc_html( $block['settings']['question'] ?? '' ); ?>
                    <span class="fp-faq__icon" aria-hidden="true">+</span>
                </button>
                <div id="<?php echo $item_id; ?>" class="fp-faq__answer" hidden>
                    <?php echo wp_kses_post( $block['settings']['answer'] ?? '' ); ?>
                </div>
                <?php else : ?>
                <h3 class="fp-faq__question fp-faq__question--static">
                    <?php echo esc_html( $block['settings']['question'] ?? '' ); ?>
                </h3>
                <div class="fp-faq__answer fp-faq__answer--open">
                    <?php echo wp_kses_post( $block['settings']['answer'] ?? '' ); ?>
                </div>
                <?php endif; ?>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>

    </div>
</section>
