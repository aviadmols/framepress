<?php
defined( 'ABSPATH' ) || exit;

return [
    'type'     => 'image-banner',
    'label'    => 'Image Banner',
    'category' => 'content',
    'contexts' => [ 'page' ],
    'settings' => [
        [ 'id' => 'image',           'type' => 'image',    'label' => 'Image',              'default' => '' ],
        [ 'id' => 'title',           'type' => 'text',     'label' => 'Overlay Title',      'default' => '' ],
        [ 'id' => 'subtitle',        'type' => 'textarea', 'label' => 'Overlay Subtitle',   'default' => '' ],
        [ 'id' => 'overlay_color',   'type' => 'color',    'label' => 'Overlay Color',      'default' => '#000000' ],
        [ 'id' => 'overlay_opacity', 'type' => 'range',    'label' => 'Overlay Opacity',    'default' => 30, 'min' => 0, 'max' => 100 ],
        [ 'id' => 'text_color',      'type' => 'color',    'label' => 'Text Color',         'default' => '#ffffff' ],
        [ 'id' => 'height',          'type' => 'number',   'label' => 'Height (px)',         'default' => 400, 'min' => 150 ],
        [
            'id'      => 'content_align',
            'type'    => 'select',
            'label'   => 'Alignment',
            'default' => 'center',
            'options' => [
                [ 'value' => 'left',   'label' => 'Left' ],
                [ 'value' => 'center', 'label' => 'Center' ],
                [ 'value' => 'right',  'label' => 'Right' ],
            ],
        ],
    ],
    'blocks' => [
        'allowed' => [ 'button' ],
        'max'     => 2,
    ],
];
