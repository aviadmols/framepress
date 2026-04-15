<?php
defined( 'ABSPATH' ) || exit;

return [
    'type'     => 'hero',
    'label'    => 'Hero',
    'category' => 'content',
    'contexts' => [ 'page' ],
    'settings' => [
        [ 'id' => 'title',           'type' => 'text',     'label' => 'Title',            'default' => 'Welcome to Our Site' ],
        [ 'id' => 'subtitle',        'type' => 'textarea', 'label' => 'Subtitle',         'default' => '' ],
        [ 'id' => 'background_image','type' => 'image',    'label' => 'Background Image', 'default' => '' ],
        [
            'id'      => 'overlay_opacity',
            'type'    => 'range',
            'label'   => 'Overlay Opacity',
            'default' => 40,
            'min'     => 0,
            'max'     => 100,
            'step'    => 5,
        ],
        [ 'id' => 'overlay_color', 'type' => 'color',  'label' => 'Overlay Color',  'default' => '#000000' ],
        [ 'id' => 'text_color',    'type' => 'color',  'label' => 'Text Color',     'default' => '#ffffff' ],
        [ 'id' => 'min_height',    'type' => 'number', 'label' => 'Min Height (px)','default' => 520, 'min' => 200, 'max' => 1200 ],
        [
            'id'      => 'content_align',
            'type'    => 'select',
            'label'   => 'Content Alignment',
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
        'max'     => 3,
    ],
    'presets' => [
        [
            'label'    => 'Default Hero',
            'settings' => [ 'title' => 'Big Headline Here', 'subtitle' => 'Supporting text that describes your value proposition.' ],
        ],
    ],
];
