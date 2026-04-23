<?php
defined( 'ABSPATH' ) || exit;

$count       = max( 1, min( 12, (int) ( $settings['post_count'] ?? 3 ) ) );
$columns     = in_array( $settings['columns'] ?? '3', [ '2', '3', '4' ], true ) ? $settings['columns'] : '3';
$category    = absint( $settings['category'] ?? 0 );
$show_exc    = ! empty( $settings['show_excerpt'] );
$show_date   = ! empty( $settings['show_date'] );
$show_img    = ! empty( $settings['show_image'] );
$title_tag   = hero_pick_tag( (string) ( $settings['title_tag'] ?? 'auto' ), 'h2' );

$query_args = [
    'post_type'      => 'post',
    'posts_per_page' => $count,
    'post_status'    => 'publish',
    'no_found_rows'  => true,
];
if ( $category ) {
    $query_args['cat'] = $category;
}

$posts_query = new WP_Query( $query_args );
?>
<section class="fp-posts-grid">
    <div class="fp-container">

        <?php if ( ! empty( $settings['title'] ) ) : ?>
        <<?php echo $title_tag; ?> class="fp-posts-grid__title"><?php echo esc_html( $settings['title'] ); ?></<?php echo $title_tag; ?>>
        <?php endif; ?>

        <?php if ( $posts_query->have_posts() ) : ?>
        <div class="fp-posts-grid__grid fp-posts-grid__grid--cols-<?php echo esc_attr( $columns ); ?>">
            <?php while ( $posts_query->have_posts() ) : $posts_query->the_post(); ?>
            <article class="fp-posts-grid__card">

                <?php if ( $show_img && has_post_thumbnail() ) : ?>
                <a href="<?php the_permalink(); ?>" class="fp-posts-grid__card-image" tabindex="-1" aria-hidden="true">
                    <?php the_post_thumbnail( 'medium_large', [ 'loading' => 'lazy' ] ); ?>
                </a>
                <?php endif; ?>

                <div class="fp-posts-grid__card-body">
                    <?php if ( $show_date ) : ?>
                    <time class="fp-posts-grid__date" datetime="<?php echo esc_attr( get_the_date( 'c' ) ); ?>">
                        <?php echo esc_html( get_the_date() ); ?>
                    </time>
                    <?php endif; ?>

                    <h3 class="fp-posts-grid__card-title">
                        <a href="<?php the_permalink(); ?>"><?php the_title(); ?></a>
                    </h3>

                    <?php if ( $show_exc ) : ?>
                    <p class="fp-posts-grid__excerpt"><?php echo esc_html( get_the_excerpt() ); ?></p>
                    <?php endif; ?>
                </div>

            </article>
            <?php endwhile; wp_reset_postdata(); ?>
        </div>
        <?php else : ?>
        <p class="fp-posts-grid__empty"><?php esc_html_e( 'No posts found.', 'hero' ); ?></p>
        <?php endif; ?>

    </div>
</section>
