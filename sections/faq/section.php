<?php
defined( 'ABSPATH' ) || exit;

$layout = $settings['layout'] ?? 'accordion';
$bg     = esc_attr( $settings['background_color'] ?? '' );
$section_style = $bg ? 'background-color:' . $bg . ';' : '';
$title_tag = hero_pick_tag( (string) ( $settings['title_tag'] ?? 'auto' ), 'h2' );
?>
<section class="fp-faq fp-faq--<?php echo esc_attr( $layout ); ?>" <?php echo $section_style ? 'style="' . $section_style . '"' : ''; ?>>
    <div class="fp-container">

        <?php if ( ! empty( $settings['title'] ) ) : ?>
        <<?php echo $title_tag; ?> class="fp-faq__title"><?php echo esc_html( $settings['title'] ); ?></<?php echo $title_tag; ?>>
        <?php endif; ?>

        <?php if ( ! empty( $blocks ) ) : ?>
        <div class="fp-faq__list" <?php echo $layout === 'accordion' ? 'role="list"' : ''; ?>>
            <?php foreach ( $blocks as $index => $block ) :
                if ( $block['type'] !== 'faq-item' ) continue;
                $item_id = 'faq-item-' . esc_attr( $block['id'] );
                $question_tag = hero_pick_tag( (string) ( $block['settings']['question_tag'] ?? 'auto' ), 'h3' );
                $answer_tag = hero_pick_tag( (string) ( $block['settings']['answer_tag'] ?? 'auto' ), 'div' );
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
                    <<?php echo $answer_tag; ?>><?php echo wp_kses_post( $block['settings']['answer'] ?? '' ); ?></<?php echo $answer_tag; ?>>
                </div>
                <?php else : ?>
                <<?php echo $question_tag; ?> class="fp-faq__question fp-faq__question--static">
                    <?php echo esc_html( $block['settings']['question'] ?? '' ); ?>
                </<?php echo $question_tag; ?>>
                <div class="fp-faq__answer fp-faq__answer--open">
                    <<?php echo $answer_tag; ?>><?php echo wp_kses_post( $block['settings']['answer'] ?? '' ); ?></<?php echo $answer_tag; ?>>
                </div>
                <?php endif; ?>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>

    </div>
</section>
