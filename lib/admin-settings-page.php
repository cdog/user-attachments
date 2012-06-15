<?php

/**
 * Defines the settings sections
 *
 * @return array
 */
function ua_options_page_sections() {
    $sections = array();
    $sections['checkbox_section'] = __('Categories', 'ua_textdomain');

    return $sections;
}

/**
 * Defines the form fields
 *
 * @return array
 */
function ua_options_page_fields() {
    $options[] = array(
        'section' => 'checkbox_section',
        'id'      => 'ua_multicheckbox_inputs',
        'title'   => __('Exclude Categories', 'ua_textdomain'),
        'desc'    => __('Selected categories are excluded from the upload form', 'ua_textdomain'),
        'type'    => 'multi-checkbox',
        'std'     => '',
        'choices' => array(
            __('Category 1','ua_textdomain') . '|cat1',
            __('Category 2','ua_textdomain') . '|cat2',
            __('Category 3','ua_textdomain') . '|cat3',
            __('Category 4','ua_textdomain') . '|cat4'
        )
    );

    return $options;
}

?>
