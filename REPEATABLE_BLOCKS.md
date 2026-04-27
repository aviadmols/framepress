# Repeatable Sections with Blocks

Use this pattern for any section that renders a repeated list (cards, features, plans, steps, testimonials).

## Schema Contract

```php
return [
    'type'     => 'products-grid',
    'label'    => 'Products Grid',
    'category' => 'content',
    'contexts' => [ 'page' ],
    'settings' => [
        [ 'id' => 'headline', 'type' => 'text', 'label' => 'Headline', 'default' => '' ],
    ],
    'blocks' => [
        'allowed' => [ 'product-card' ],
        'min'     => 0,
        'max'     => 12,
    ],
    'block_types' => [
        'product-card' => [
            'label'    => 'Product Card',
            'settings' => [
                [ 'id' => 'name',   'type' => 'text',     'label' => 'Name',        'default' => '' ],
                [ 'id' => 'desc',   'type' => 'textarea', 'label' => 'Description', 'default' => '' ],
                [ 'id' => 'cta',    'type' => 'text',     'label' => 'CTA text',    'default' => 'Get Started' ],
                [ 'id' => 'link',   'type' => 'url',      'label' => 'CTA link',    'default' => '#' ],
                [ 'id' => 'icon',   'type' => 'image',    'label' => 'Icon',        'default' => '' ],
                [ 'id' => 'accent', 'type' => 'select',   'label' => 'Accent',      'default' => 'blue',
                    'options' => [
                        [ 'value' => 'blue',  'label' => 'Blue' ],
                        [ 'value' => 'green', 'label' => 'Green' ],
                    ],
                ],
            ],
        ],
    ],
];
```

## section.php Pattern

```php
<?php foreach ( $blocks as $block ) :
    if ( ( $block['type'] ?? '' ) !== 'product-card' ) {
        continue;
    }
    $bs = is_array( $block['settings'] ?? null ) ? $block['settings'] : [];
    $name = (string) ( $bs['name'] ?? '' );
    if ( $name === '' ) {
        continue;
    }
?>
    <article class="fp-products-grid__card">
        <h3><?php echo esc_html( $name ); ?></h3>
    </article>
<?php endforeach; ?>
```

## Optional Legacy Migration

For existing sections that still store numbered settings (for example `card_1_name`, `card_2_name`), add this to schema:

```php
'repeatable_migration' => [
    'prefix'     => 'card',
    'block_type' => 'product-card',
    'field_map'  => [
        'name'   => 'name',
        'desc'   => 'desc',
        'cta'    => 'cta',
        'link'   => 'link',
        'icon'   => 'icon',
        'accent' => 'accent',
    ],
    'max'        => 12,
],
```

The REST sanitization layer will convert those numbered settings into `blocks` on load/save, then persist the normalized `blocks` structure.
