<?php
defined( 'ABSPATH' ) || exit;

return [
    'type'     => 'faq-item',
    'label'    => 'FAQ Item',
    'settings' => [
        [ 'id' => 'question', 'type' => 'text',     'label' => 'Question', 'default' => '' ],
        [ 'id' => 'answer',   'type' => 'richtext', 'label' => 'Answer',   'default' => '' ],
    ],
];
