<?php
defined( 'ABSPATH' ) || exit;

return [
    'type'     => 'header-cta',
    'label'    => 'Header CTA Button',
    'category' => 'header',
    'contexts' => [ 'header' ],
    'settings' => [
        [ 'id' => 'label', 'type' => 'text',   'label' => 'Button Text', 'default' => 'Get Started' ],
        [ 'id' => 'url',   'type' => 'url',    'label' => 'Button URL',  'default' => '' ],
        [
            'id'      => 'style',
            'type'    => 'select',
            'label'   => 'Button Style',
            'default' => 'primary',
            'options' => [
                [ 'value' => 'primary', 'label' => 'Primary' ],
                [ 'value' => 'outline', 'label' => 'Outline' ],
            ],
        ],
        [ 'id' => 'open_new_tab', 'type' => 'checkbox', 'label' => 'Open in New Tab', 'default' => false ],
    ],
    'blocks' => [ 'allowed' => [], 'max' => 0 ],
];
