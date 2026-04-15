<?php
defined( 'ABSPATH' ) || exit;

return [
    'type'     => 'header-nav',
    'label'    => 'Navigation Menu',
    'category' => 'header',
    'contexts' => [ 'header' ],
    'settings' => [
        [
            'id'             => 'menu',
            'type'           => 'select',
            'label'          => 'Menu',
            'default'        => '',
            'options_source' => 'wp_menus',
        ],
        [
            'id'      => 'align',
            'type'    => 'select',
            'label'   => 'Alignment',
            'default' => 'right',
            'options' => [
                [ 'value' => 'left',   'label' => 'Left' ],
                [ 'value' => 'center', 'label' => 'Center' ],
                [ 'value' => 'right',  'label' => 'Right' ],
            ],
        ],
    ],
    'blocks' => [ 'allowed' => [], 'max' => 0 ],
];
