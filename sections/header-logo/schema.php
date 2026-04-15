<?php
defined( 'ABSPATH' ) || exit;

return [
    'type'     => 'header-logo',
    'label'    => 'Logo',
    'category' => 'header',
    'contexts' => [ 'header' ],
    'settings' => [
        [ 'id' => 'logo_image',  'type' => 'image',  'label' => 'Logo Image',       'default' => '' ],
        [ 'id' => 'logo_text',   'type' => 'text',   'label' => 'Site Name Text',   'default' => '' ],
        [ 'id' => 'logo_height', 'type' => 'number', 'label' => 'Logo Height (px)', 'default' => 40, 'min' => 20, 'max' => 200 ],
        [ 'id' => 'link_to',     'type' => 'url',    'label' => 'Link URL',         'default' => '' ],
    ],
    'blocks' => [ 'allowed' => [], 'max' => 0 ],
];
