<?php
defined( 'ABSPATH' ) || exit;

return [
    'type'     => 'testimonial',
    'label'    => 'Testimonial',
    'settings' => [
        [ 'id' => 'quote',  'type' => 'textarea', 'label' => 'Quote',       'default' => '' ],
        [ 'id' => 'author', 'type' => 'text',     'label' => 'Author Name', 'default' => '' ],
        [ 'id' => 'role',   'type' => 'text',     'label' => 'Role / Title','default' => '' ],
        [ 'id' => 'avatar', 'type' => 'image',    'label' => 'Avatar',      'default' => '' ],
    ],
];
