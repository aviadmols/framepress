<?php
defined( 'ABSPATH' ) || exit;

return [
    'type'     => 'footer-columns',
    'label'    => 'Footer Columns',
    'category' => 'footer',
    'contexts' => [ 'footer' ],
    'settings' => [
        [ 'id' => 'background_color', 'type' => 'color',  'label' => 'Background Color', 'default' => '#1a1a1f' ],
        [ 'id' => 'text_color',       'type' => 'color',  'label' => 'Text Color',        'default' => '#cccccc' ],
        [
            'id'      => 'columns',
            'type'    => 'select',
            'label'   => 'Columns',
            'default' => '4',
            'options' => [
                [ 'value' => '2', 'label' => '2 Columns' ],
                [ 'value' => '3', 'label' => '3 Columns' ],
                [ 'value' => '4', 'label' => '4 Columns' ],
            ],
        ],
    ],
    'blocks' => [
        'allowed' => [ 'footer-column' ],
        'max'     => 4,
    ],
    'block_types' => [
        'footer-column' => [
            'label'    => 'Footer Column',
            'settings' => [
                [ 'id' => 'heading', 'type' => 'text',     'label' => 'Column Heading', 'default' => '' ],
                [ 'id' => 'content', 'type' => 'richtext', 'label' => 'Content',        'default' => '' ],
            ],
        ],
    ],
];
