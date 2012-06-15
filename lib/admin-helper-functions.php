<?php

/**
 * Returns the name of the current admin page
 *
 * @return string The name of the current admin page
 */
function ua_get_admin_page() {
    global $pagenow;

    // Get the current page name
    if ($pagenow == 'options.php') {
        $parts = explode('page=', $_POST['_wp_http_referer']);
        $page  = $parts[1];
        $pos   = strpos($page, '&');

        if ($pos !== FALSE) {
            $page = substr($parts[1], 0, $pos);
        }

        $current_page = trim($page);
    } else {
        $current_page = trim($_GET['page']);
    }

    return $current_page;
}

/**
 * Prints the settings page header
 */
function ua_settings_page_header() {
    // Get the settings sections array
    $settings_sections = ua_get_settings();

    // Display the screen icon
    screen_icon('options-general');

    // And the page title
    echo '<h2>' . $settings_sections['ua_page_title'] . '</h2>';
}

?>
