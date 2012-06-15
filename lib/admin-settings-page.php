<?php

/**
 * Returns the settings sections
 *
 * @return array The settings sections
 */
function ua_options_page_sections() {
    $sections = array();
    $sections['categories_section'] = __('Categories', 'ua_textdomain');

    return $sections;
}

/**
 * Returns the form fields
 *
 * @return array The form fields
 */
function ua_options_page_fields() {
    // Get the categories
    $args = array(
        'taxonomy'   => 'ua_attachment_category',
        'name'       => 'ua_attachment_category',
        'hide_empty' => 0
    );

    $categories = get_categories($args);

    // Build the choices array
    $choices = array();

    if ($categories) {
        foreach ($categories as $cat) {
            $choices[] = $cat->cat_name . '|' . $cat->cat_ID;
        }
    }

    $options[] = array(
        'section' => 'categories_section',
        'id'      => 'ua_exclude_categories',
        'title'   => __('Exclude Categories', 'ua_textdomain'),
        'desc'    => __('Select the categories you want to exclude from the upload form.', 'ua_textdomain'),
        'type'    => 'multi_checkbox',
        'std'     => '',
        'choices' => $choices
    );

    return $options;
}

?>
