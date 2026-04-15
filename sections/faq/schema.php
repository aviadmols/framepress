<?php
defined( 'ABSPATH' ) || exit;

return [
    'type'     => 'faq',
    'label'    => 'FAQ',
    'category' => 'content',
    'contexts' => [ 'page' ],
    'settings' => [
        [ 'id' => 'title',            'type' => 'text',  'label' => 'Section Title',   'default' => 'Frequently Asked Questions' ],
        [ 'id' => 'background_color', 'type' => 'color', 'label' => 'Background Color','default' => '' ],
        [
            'id'      => 'layout',
            'type'    => 'select',
            'label'   => 'Layout',
            'default' => 'accordion',
            'options' => [
                [ 'value' => 'accordion', 'label' => 'Accordion' ],
                [ 'value' => 'open',      'label' => 'All Open' ],
            ],
        ],
    ],
    'blocks' => [
        'allowed' => [ 'faq-item' ],
        'min'     => 1,
    ],
    'block_types' => [
        'faq-item' => [
            'label'    => 'FAQ Item',
            'settings' => [
                [ 'id' => 'question', 'type' => 'text',     'label' => 'Question', 'default' => '' ],
                [ 'id' => 'answer',   'type' => 'richtext', 'label' => 'Answer',   'default' => '' ],
            ],
        ],
    ],
];
