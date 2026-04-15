<?php
defined( 'ABSPATH' ) || exit;

return [
    'type'     => 'rich-text',
    'label'    => 'Rich Text',
    'category' => 'content',
    'contexts' => [ 'page' ],
    'settings' => [
        [ 'id' => 'content',          'type' => 'richtext', 'label' => 'Content',           'default' => '' ],
        [ 'id' => 'background_color', 'type' => 'color',    'label' => 'Background Color',  'default' => '' ],
        [
            'id'      => 'content_width',
            'type'    => 'select',
            'label'   => 'Content Width',
            'default' => 'narrow',
            'options' => [
                [ 'value' => 'narrow', 'label' => 'Narrow (680px)' ],
                [ 'value' => 'normal', 'label' => 'Normal (900px)' ],
                [ 'value' => 'wide',   'label' => 'Full Container' ],
            ],
        ],
        [
            'id'      => 'text_align',
            'type'    => 'select',
            'label'   => 'Text Alignment',
            'default' => 'left',
            'options' => [
                [ 'value' => 'left',   'label' => 'Left' ],
                [ 'value' => 'center', 'label' => 'Center' ],
            ],
        ],
    ],
    'blocks' => [ 'allowed' => [], 'max' => 0 ],
];
