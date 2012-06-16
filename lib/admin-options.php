<?php

/**
 * Include the required files
 */
require_once 'admin-helper-functions.php';
require_once 'admin-settings-page.php';

/**
 * Add hooks/filters
 */
add_action('admin_init',    'ua_register_settings');
add_action('admin_menu',    'ua_add_menu');
add_action('admin_notices', 'ua_admin_messages');

 /**
 * Returns the settings of the current admin page
 *
 * Source: http://alisothegeek.com/2011/01/wordpress-settings-api-tutorial-1/
 *
 * @return array The settings of the current admin page
 */
function ua_get_settings() {
    // Get the name of the current admin page
    $page = ua_get_admin_page();

    switch ($page) {
    case 'ua_settings':
        $ua_option_name         = 'ua_settings';
        $ua_settings_page_title = __('User Attachments Settings', 'ua_textdomain');
        $ua_page_sections       = ua_options_page_sections();
        $ua_page_fields         = ua_options_page_fields();

        break;
    }

    // Build the settings array
    $settings = array();

    $settings['ua_option_name']   = $ua_option_name;
    $settings['ua_page_title']    = $ua_settings_page_title;
    $settings['ua_page_sections'] = $ua_page_sections;
    $settings['ua_page_fields']   = $ua_page_fields;

    return $settings;
}

/**
 * Registers the form field settings
 *
 * Source: http://alisothegeek.com/2011/01/wordpress-settings-api-tutorial-1/
 *
 * @param array $args The arguments to be used in creating the settings field
 */
function ua_create_settings_field($args = array()) {
    // Default settings
    $defaults = array(
        'choices' => array(),
        'class'   => '',
        'desc'    => 'Default description.',
        'id'      => 'default_field',
        'section' => 'main_section',
        'std'     => '',
        'title'   => 'Default Field',
        'type'    => 'text'
    );
    
    // Import variables into the current symbol table
    extract(wp_parse_args($args, $defaults));
    
    // Additional arguments for the form field output
    $field_args = array(
        'choices'   => $choices,
        'class'     => $class,
        'desc'      => $desc,
        'id'        => $id,
        'label_for' => $id,
        'std'       => $std,
        'type'      => $type
    );

    add_settings_field($id, $title, 'ua_form_field_fn', __FILE__, $section, $field_args);
}

/**
 * Initializes the options to their default values
 */
function ua_initialize_settings() {
    $default_settings = ua_options_page_fields();

    update_option('ua_settings', $default_settings);
}

/**
 * Registers the setting, settings sections and settings fields
 *
 * Source: http://alisothegeek.com/2011/01/wordpress-settings-api-tutorial-1/
 */
function ua_register_settings() {
    // Get the settings sections array
    $settings_sections = ua_get_settings();
    $ua_option_name  = $settings_sections['ua_option_name'];

    // Initialize settings
    if (!get_option($ua_option_name))
        ua_initialize_settings();

    // Register the setting and its sanitization callback
    register_setting($ua_option_name, $ua_option_name, 'ua_validate_options');

    // Add the settings sections
    if (!empty($settings_sections['ua_page_sections'])) {
        foreach ($settings_sections['ua_page_sections'] as $id => $title) {
            add_settings_section($id, $title, 'ua_section_fn', __FILE__);
        }
    }

    // Create the settings fields
    if (!empty($settings_sections['ua_page_fields'])) {
        foreach ($settings_sections['ua_page_fields'] as $option) {
            ua_create_settings_field($option);
        }
    }
}

/**
 * Adds a custom menu for the admin pages
 */
function ua_add_menu(){
    add_submenu_page(
        'edit.php?post_type=user_attachments',
        __('User Attachments Settings'),
        __('Settings','ua_textdomain'),
        'manage_options',
        'ua_settings',
        'ua_settings_page_fn'
    );
}

/**
 * Prints the leading content for a section
 */
function  ua_section_fn($desc) {
    echo '<p>' . __('Section settings', 'ua_textdomain') . '</p>';
}

/**
 * Prints the form field
 *
 * @param array $args The arguments to be used in creating the form field
 */
function ua_form_field_fn($args = array()) {
    // Import variables into the current symbol table
    extract($args);
    
    // Get the settings sections array
    $settings_sections = ua_get_settings();

    // Get the options array
    $ua_option_name = $settings_sections['ua_option_name'];
    $options        = get_option($ua_option_name);

    // Use the standard value if the option is not yet set in the database
    if (!isset($options[$id]) && 'type' != 'checkbox' ) {
        $options[$id] = $std;
    }
    
    // Additional field class
    $field_class = ($class != '')
        ? ' ' . $class
        : '';

    switch ($type) {
    case 'multi_checkbox':
        $output  = '<fieldset>';
        $output .= '<legend class="screen-reader-text"><span>' . $title . '</span></legend>';

        if ($choices) {
            $i = 0;

            foreach($choices as $item) {
                $item = explode('|', $item);
                $item[0] = esc_html($item[0], 'ua_textdomain');

                $checked = '';

                if (isset($options[$id][$item[1]])) {
                    if ($options[$id][$item[1]] == 'true') {
                        $checked = 'checked="checked"';
                    }
                }

                $output .= '<label for="' . $id . '|' . $item[1] . '">';
                $output .= '<input ' . $checked;
                $output .= ' class="checkbox' . $field_class . '"';
                $output .= ' id="' . $id . '|' . $item[1] . '"';
                $output .= ' name="' . $ua_option_name . '[' . $id . '|' . $item[1] . ']"';
                $output .= ' type="checkbox"';
                $output .= ' value="1"';
                $output .= ' /> ' . $item[0];
                $output .= '</label>';

                if (++$i < count($choices)) {
                    $output .= '<br />';
                }
            }
        } else {
            $output .= '<span>No categories found.</span>';
        }

        $output .= '</fieldset>';

        echo $output;

        echo ($desc != '')
            ? '<br /><span class="description">' . $desc . '</span>'
            : '';

        break;
    }
}

/**
 * Prints the admin settings page
 */
function ua_settings_page_fn() {
    // Get the settings sections array
    $settings_sections = ua_get_settings();

    ?>

    <div class="wrap">
        <?php 
            // Print the settings page header
            ua_settings_page_header();
        ?>
        
        <form action="options.php" method="post">
            <?php 
                settings_fields($settings_sections['ua_option_name']);
                do_settings_sections(__FILE__);
            ?>
            
            <p class="submit">
                <input class="button-primary" name="submit" type="submit" value="<?php _e('Save Changes', 'ua_textdomain'); ?>" />
            </p><!-- .submit -->
        </form>
    </div><!-- .wrap -->

    <?php
}

/**
 * Validates input
 * 
 * @return array The validated input
 */
function ua_validate_options($input) {
    $valid_input = array();

    // Get the settings sections array
    $settings_sections = ua_get_settings();

    // Get the page fields array
    $options = $settings_sections['ua_page_fields'];

    foreach ($options as $option) {
        switch ( $option['type'] ) {
        case 'multi_checkbox':
            unset($multi_checkbox);

            $check_values = array();

            foreach ($option['choices'] as $choice) {
                $pieces         = explode('|', $choice);
                $check_values[] = $pieces[1];
            }

            foreach ($check_values as $value) {
                if (!empty($input[$option['id'] . '|' . $value])) {
                    $multi_checkbox[$value] = 'true';
                } else {
                    $multi_checkbox[$value] = 'false';
                }
            }

            if (!empty($multi_checkbox)) {
                $valid_input[$option['id']] = $multi_checkbox;
            }

            break;
        }
    }

    return $valid_input;
}

/**
 * Prints the specified message in the admin
 *
 * Source: http://www.wprecipes.com/how-to-show-an-urgent-message-in-the-wordpress-admin-area
 *
 * @param string $message The message to display
 * @param string $msgclass The message class
 */
function ua_show_message($message, $class = 'info') {
    echo '<div class="' . $class . '" id="message">' . $message . '</div>';
}

/**
 * Callback function for displaying admin messages
 *
 * Source: http://www.wprecipes.com/how-to-show-an-urgent-message-in-the-wordpress-admin-area
 */
function ua_admin_messages() {
    $ua_settings_page = strpos($_GET['page'], 'ua_settings');
    $settings_errors  = get_settings_errors(); 

    if (current_user_can('manage_options')
        && $ua_settings_page !== FALSE
        && !empty($settings_errors)
    ) {
        if ($settings_errors[0]['code'] == 'settings_updated' && isset($_GET['settings-updated'])) {
            // Updated
            ua_show_message('<p>' . $settings_errors[0]['message'] . '</p>', 'updated');
        } else {
            // Error
            foreach ($settings_errors as $settings_error) {
                ua_show_message('<p class="setting-error-message" title="' . $settings_error['setting'] . '">' . $settings_error['message'] . '</p>', 'error');
            }
        }
    }
}

?>
