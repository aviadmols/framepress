<?php
defined( 'ABSPATH' ) || exit;

return [
    'type'     => 'footer-copyright',
    'label'    => 'Copyright Bar',
    'category' => 'footer',
    'contexts' => [ 'footer' ],
    'settings' => [
        [ 'id' => 'text',             'type' => 'text',  'label' => 'Copyright Text',    'default' => '© {year} {site_name}. All rights reserved.' ],
        [ 'id' => 'background_color', 'type' => 'color', 'label' => 'Background Color',  'default' => '#111111' ],
        [ 'id' => 'text_color',       'type' => 'color', 'label' => 'Text Color',         'default' => '#888888' ],
        [
            'id'      => 'text_align',
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
    'blocks' => [ 'allowed' => [], 'max' => 0 ],
];
