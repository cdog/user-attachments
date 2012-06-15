<?php

/**
 * Defines the settings sections
 *
 * @return array
 */
function ua_options_page_sections() {
    $sections = array();
    $sections['categories_section'] = __('Categories', 'ua_textdomain');

    return $sections;
}

/**
 * Defines the form fields
 *
 * @return array
 */
function ua_options_page_fields() {
    $args = array(
        'taxonomy'   => 'ua_attachment_category',
        'name'       => 'ua_attachment_category',
        'hide_empty' => 0
    );


    $choices = array();
    $categories = get_categories($args);

    if ($categories) {
        foreach ($categories as $cat) {
            $choices[] = $cat->cat_name . '|' . $cat->cat_ID;
        }
    }

    $options[] = array(
        'section' => 'categories_section',
        'id'      => 'ua_exclude_categories',
        'title'   => __('Exclude Categories', 'ua_textdomain'),
        'desc'    => __('Selected categories are excluded from the upload form', 'ua_textdomain'),
        'type'    => 'multi_checkbox',
        'std'     => '',
        'choices' => $choices
    );

    return $options;
}

?>
