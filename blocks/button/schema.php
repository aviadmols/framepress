<?php
defined( 'ABSPATH' ) || exit;

return [
    'type'     => 'button',
    'label'    => 'Button',
    'settings' => [
        [ 'id' => 'label',        'type' => 'text',     'label' => 'Button Text', 'default' => 'Click here' ],
        [ 'id' => 'url',          'type' => 'url',      'label' => 'URL',         'default' => '' ],
        [
            'id'      => 'style',
            'type'    => 'select',
            'label'   => 'Style',
            'default' => 'primary',
            'options' => [
                [ 'value' => 'primary', 'label' => 'Primary' ],
                [ 'value' => 'outline', 'label' => 'Outline' ],
                [ 'value' => 'ghost',   'label' => 'Ghost' ],
            ],
        ],
        [ 'id' => 'open_new_tab', 'type' => 'checkbox', 'label' => 'Open in New Tab', 'default' => false ],
    ],
];
