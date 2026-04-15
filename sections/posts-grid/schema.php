<?php
defined( 'ABSPATH' ) || exit;

return [
    'type'     => 'posts-grid',
    'label'    => 'Posts Grid',
    'category' => 'dynamic',
    'contexts' => [ 'page' ],
    'settings' => [
        [ 'id' => 'title',        'type' => 'text',     'label' => 'Section Title', 'default' => 'Latest Posts' ],
        [ 'id' => 'post_count',   'type' => 'number',   'label' => 'Post Count',    'default' => 3, 'min' => 1, 'max' => 12 ],
        [
            'id'             => 'category',
            'type'           => 'select',
            'label'          => 'Category',
            'default'        => '',
            'options_source' => 'wp_categories',
        ],
        [ 'id' => 'show_excerpt',  'type' => 'checkbox', 'label' => 'Show Excerpt',    'default' => true ],
        [ 'id' => 'show_date',     'type' => 'checkbox', 'label' => 'Show Date',        'default' => true ],
        [ 'id' => 'show_image',    'type' => 'checkbox', 'label' => 'Show Thumbnail',   'default' => true ],
        [
            'id'      => 'columns',
            'type'    => 'select',
            'label'   => 'Columns',
            'default' => '3',
            'options' => [
                [ 'value' => '2', 'label' => '2 Columns' ],
                [ 'value' => '3', 'label' => '3 Columns' ],
                [ 'value' => '4', 'label' => '4 Columns' ],
            ],
        ],
    ],
    'blocks' => [ 'allowed' => [], 'max' => 0 ],
];
