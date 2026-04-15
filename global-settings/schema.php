<?php
/**
 * FramePress Global Settings Schema
 *
 * Returns the full schema for global design tokens.
 * Fields with a 'var' key are output as CSS custom properties on :root.
 * Fields without 'var' (custom_css group) are output as raw CSS.
 */

defined( 'ABSPATH' ) || exit;

return [
    'groups' => [

        'colors' => [
            'label' => 'Colors',
            'settings' => [
                [ 'id' => 'color_primary',    'type' => 'color',  'label' => 'Primary Color',    'default' => '#0073aa', 'var' => '--fp-color-primary' ],
                [ 'id' => 'color_secondary',  'type' => 'color',  'label' => 'Secondary Color',  'default' => '#005177', 'var' => '--fp-color-secondary' ],
                [ 'id' => 'color_text',       'type' => 'color',  'label' => 'Body Text',        'default' => '#333333', 'var' => '--fp-color-text' ],
                [ 'id' => 'color_background', 'type' => 'color',  'label' => 'Page Background',  'default' => '#ffffff', 'var' => '--fp-color-background' ],
                [ 'id' => 'color_border',     'type' => 'color',  'label' => 'Border Color',     'default' => '#dddddd', 'var' => '--fp-color-border' ],
            ],
        ],

        'typography' => [
            'label' => 'Typography',
            'settings' => [
                [ 'id' => 'font_body',        'type' => 'text',   'label' => 'Body Font Family',    'default' => 'sans-serif', 'var' => '--fp-font-body' ],
                [ 'id' => 'font_heading',     'type' => 'text',   'label' => 'Heading Font Family', 'default' => 'sans-serif', 'var' => '--fp-font-heading' ],
                [ 'id' => 'font_size_base',   'type' => 'number', 'label' => 'Base Font Size (px)', 'default' => 16,           'var' => '--fp-font-size-base', 'unit' => 'px' ],
                [ 'id' => 'line_height',      'type' => 'number', 'label' => 'Line Height',         'default' => 1.6,          'var' => '--fp-line-height' ],
                [
                    'id'      => 'heading_weight',
                    'type'    => 'select',
                    'label'   => 'Heading Weight',
                    'default' => '700',
                    'var'     => '--fp-heading-weight',
                    'options' => [
                        [ 'value' => '400', 'label' => 'Regular' ],
                        [ 'value' => '600', 'label' => 'Semi Bold' ],
                        [ 'value' => '700', 'label' => 'Bold' ],
                        [ 'value' => '800', 'label' => 'Extra Bold' ],
                    ],
                ],
            ],
        ],

        'spacing' => [
            'label' => 'Spacing',
            'settings' => [
                [ 'id' => 'section_padding_v', 'type' => 'number', 'label' => 'Section Vertical Padding (px)',   'default' => 80,   'var' => '--fp-section-padding-v', 'unit' => 'px' ],
                [ 'id' => 'section_padding_h', 'type' => 'number', 'label' => 'Section Horizontal Padding (px)', 'default' => 40,   'var' => '--fp-section-padding-h', 'unit' => 'px' ],
                [ 'id' => 'container_width',   'type' => 'number', 'label' => 'Max Container Width (px)',         'default' => 1200, 'var' => '--fp-container-width',   'unit' => 'px' ],
                [ 'id' => 'gap',               'type' => 'number', 'label' => 'Grid Gap (px)',                    'default' => 24,   'var' => '--fp-gap',               'unit' => 'px' ],
            ],
        ],

        'buttons' => [
            'label' => 'Buttons',
            'settings' => [
                [ 'id' => 'btn_radius',    'type' => 'number', 'label' => 'Border Radius (px)',       'default' => 4,  'var' => '--fp-btn-radius',    'unit' => 'px' ],
                [ 'id' => 'btn_padding_v', 'type' => 'number', 'label' => 'Vertical Padding (px)',    'default' => 12, 'var' => '--fp-btn-padding-v', 'unit' => 'px' ],
                [ 'id' => 'btn_padding_h', 'type' => 'number', 'label' => 'Horizontal Padding (px)',  'default' => 24, 'var' => '--fp-btn-padding-h', 'unit' => 'px' ],
                [
                    'id'      => 'btn_font_weight',
                    'type'    => 'select',
                    'label'   => 'Font Weight',
                    'default' => '600',
                    'var'     => '--fp-btn-font-weight',
                    'options' => [
                        [ 'value' => '400', 'label' => 'Regular' ],
                        [ 'value' => '600', 'label' => 'Semi Bold' ],
                        [ 'value' => '700', 'label' => 'Bold' ],
                    ],
                ],
                [
                    'id'      => 'btn_text_transform',
                    'type'    => 'select',
                    'label'   => 'Text Transform',
                    'default' => 'none',
                    'var'     => '--fp-btn-text-transform',
                    'options' => [
                        [ 'value' => 'none',       'label' => 'None' ],
                        [ 'value' => 'uppercase',  'label' => 'Uppercase' ],
                        [ 'value' => 'capitalize', 'label' => 'Capitalize' ],
                    ],
                ],
            ],
        ],

        'custom_css' => [
            'label' => 'Custom CSS',
            'settings' => [
                [ 'id' => 'custom_css_global', 'type' => 'textarea', 'label' => 'Global CSS',  'default' => '', 'description' => 'Applied to every page. No scoping.' ],
                [ 'id' => 'custom_css_header', 'type' => 'textarea', 'label' => 'Header CSS',  'default' => '', 'description' => 'Scoped inside .framepress-header' ],
                [ 'id' => 'custom_css_footer', 'type' => 'textarea', 'label' => 'Footer CSS',  'default' => '', 'description' => 'Scoped inside .framepress-footer' ],
            ],
        ],

    ],
];
