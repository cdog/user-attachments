<?php

/*
Plugin Name: User Attachments
Plugin URI: https://github.com/cdog/user-attachments
Description: Allows registered users to submit and manage their attachments.
Version: 1.0.2
Author: Cătălin Dogaru
Author URI: http://swarm.cs.pub.ro/~cdogaru/
License: GPLv2 or later
*/

/*
Copyright 2012  Cătălin Dogaru  (email : catalin.dogaru@gmail.com)

This program is free software; you can redistribute it and/or modify it under
the terms of the GNU General Public License, version 2, as  published by the
Free Software Foundation.

This program is distributed in the hope that it will be useful, but WITHOUT ANY
WARRANTY; without even the implied warranty of MERCHANTABILITY or FITNESS FOR A
PARTICULAR PURPOSE.  See the GNU General Public License for more details.

You should have received a copy of the GNU General Public License along with
this program; if not, write to the Free Software Foundation, Inc., 51 Franklin
St, Fifth Floor, Boston, MA  02110-1301  USA
*/

/**
 * Constants
 */
define('MAX_UPLOAD_SIZE', 2097152);
define('TYPE_WHITELIST',  serialize(array(
    'application/pdf'
)));
define('UA_PLUGIN_PATH',  plugin_dir_path(__FILE__));
define('UA_PLUGIN_URL',   plugin_dir_url(__FILE__));

add_action('init', 'ua_init');
add_action('init', 'ua_delete_user_attachments');
add_action('init', 'ua_download_user_attachments');
add_action('init', 'ua_upload_user_attachments');

add_shortcode('manage_user_attachments', 'ua_manage');
add_shortcode('submit_user_attachments', 'ua_submit');

function ua_message() {
    if ($_SESSION['ua_message']) {
        echo '<p>' . $_SESSION['ua_message'] . '</p>';

        unset($_SESSION['ua_message']);
    }
}

function ua_submit() {
    if (!is_user_logged_in()) {
        return '<p>' . __('You need to be logged in to submit an attachment.', 'ua_textdomain') . '</p>';
    }

    global $current_user;

    get_currentuserinfo();

    ua_message();

    $out = ua_get_upload_attachment_form();

    if ($user_attachments_table = ua_get_user_attachments_table($current_user->ID)) {
        $out .= $user_attachments_table;
    }

    return $out;
}

function ua_manage() {
    if (!is_user_logged_in()) {
        return '<p>' . __('You need to be logged in to manage the attachments.', 'ua_textdomain') . '</p>';
    }

    global $current_user;

    get_currentuserinfo();

    if ($current_user->user_level == 0) {
        return '<p>' . __('You don\'t have the right permissions to manage the attachments.', 'ua_textdomain') . '</p>';
    }

    ua_message();

    // Print the categories
    $categories = get_categories(array(
        'echo'       => 0,
        'hide_empty' => 0,
        'taxonomy'   => 'ua_attachment_category',
        'type'       => 'user_attachments'
    ));

    if (!$categories) {
        $out = '<p>' . __('No attachments categories found.', 'ua_textdomain') . '</p>';

        return $out;
    }

    $out = '<h2>' . __('Categories', 'ua_textdomain') . '</h2>';

    $url    = get_permalink(get_the_ID());
    $cat_ID = $_GET['ua_cat'];
    $i      = 0;

    foreach ($categories as $cat) {
        if (!$cat_ID) {
            $cat_ID = $cat->cat_ID;
        }

        $out .= '<a href="' . add_query_arg('ua_cat', $cat->cat_ID, $url) . '">';

        if ($cat_ID == $cat->cat_ID) {
            $out .= '<strong>' . $cat->name . '</strong>';
        } else {
            $out .= $cat->name;
        }

        $out .= '</a>';

        if (++$i < count($categories)) {
            $out .= '<br />';
        }
    }

    $args = array(
        'numberposts' => -1,
        'order'       => 'ASC',
        'orderby'     => 'display_name',
        'post_status' => 'pending, publish',
        'post_type'   => 'user_attachments'
    );

    $user_attachments = get_posts($args);

    foreach ($user_attachments as $key => $user_attachment) {
        $user_attachment_cats = get_the_terms($user_attachment->ID, 'ua_attachment_category');

        foreach ($user_attachment_cats as $cat) {
            $user_attachment_cat = $cat->term_id;
        }

        if ($user_attachment_cat != $cat_ID) {
            unset($user_attachments[$key]);
        }
    }

    $out .= '<hr />';
    $out .= '<h2>' . __('Uploaded attachments') . '</h2>';

    if (!$user_attachments) {
        $out .= '<p>' . __('No user attachments found.', 'ua_textdomain') . '</p>';

        return $out;
    }

    $out .= '<form action="" method="post">';
    $out .= wp_nonce_field('ua_form', 'ua_form');
    $out .= '<table id="user_attachments">';
    $out .= '<thead><th>' . __('Attachment', 'ua_textdomain') . '</th><th>' . __('Caption', 'ua_textdomain') . '</th><th>' . __('Posted By', 'ua_textdomain') . '</th><th>' . __('Action', 'ua_textdomain') . '</th></thead>';

    foreach ($user_attachments as $user_attachment) {
        $args = array(
            'numberposts' => -1,
            'post_parent' => $user_attachment->ID,
            'post_type'   => 'attachment'
        );

        $attachments = get_posts($args);

        if ($attachments) {
            $post_attachment_id = $attachments[0]->ID;
        }

        $out .= wp_nonce_field('ua_attachment_action_' . $user_attachment->ID, 'ua_attachment_action_id_' . $user_attachment->ID, false);
        $out .= '<tr>';
        $out .= '<td>' . wp_get_attachment_link($post_attachment_id) . '</td>';
        $out .= '<td>' . $user_attachment->post_title . '</td>';
        $out .= '<td>' . get_the_author_meta('display_name', $user_attachment->post_author) . '</td>';
        $out .= '<td class="align_center"><input name="ua_attachment_action_id[]" type="checkbox" value="' . $user_attachment->ID . '" /></td>';
        $out .= '</tr>';
    }

    $out .= '</table>';
    $out .= '<input name="ua_download" type="submit" value="' . __('Download Selected Attachments', 'ua_textdomain') . '" />';
    $out .= ' ';
    $out .= '<input name="ua_delete" type="submit" value="' . __('Delete Selected Attachments', 'ua_textdomain') . '" />';
    $out .= '</form>';

    return $out;
}

function ua_delete_user_attachments() {
    if (!isset($_POST['ua_form'])
        || !wp_verify_nonce($_POST['ua_form'], 'ua_form')
        || !isset($_POST['ua_delete'])
        || !isset($_POST['ua_attachment_action_id'])
    ) {
        return;
    }

    $attachments = $_POST['ua_attachment_action_id'];
    $attachments_deleted = 0;

    foreach ($attachments as $user_attachment) {
        if (isset($_POST['ua_attachment_action_id_' . $user_attachment])
            && wp_verify_nonce($_POST['ua_attachment_action_id_' . $user_attachment], 'ua_attachment_action_' . $user_attachment)
        ) {
            $args = array(
                'numberposts' => -1,
                'post_parent' => (int)$user_attachment,
                'post_type'   => 'attachment'
            );

            $attachments = get_posts($args);

            if ($attachments) {
                if ($post_attachment_id = $attachments[0]->ID) {
                    wp_delete_attachment($post_attachment_id, true);
                }
            }

            wp_delete_post((int)$user_attachment, true);
            $attachments_deleted++;
        }
    }

    $_SESSION['ua_message'] = $attachments_deleted . __(' attachment(s) deleted!', 'ua_textdomain');
}

function ua_download_user_attachments() {
    if (!isset($_POST['ua_form'])
        || !wp_verify_nonce($_POST['ua_form'], 'ua_form')
        || !isset($_POST['ua_download'])
        || !isset($_POST['ua_attachment_action_id'])
    ) {
        return;
    }

    $zip_filename = UA_PLUGIN_PATH . 'tmp/ua_' . uniqid() . '.zip';
    $zip_archive  = new ZipArchive;

    $zip_archive->open($zip_filename, ZipArchive::CREATE);

    $attachments = $_POST['ua_attachment_action_id'];

    foreach ($attachments as $user_attachment) {
        if (isset($_POST['ua_attachment_action_id_' . $user_attachment])
            && wp_verify_nonce($_POST['ua_attachment_action_id_' . $user_attachment], 'ua_attachment_action_' . $user_attachment)
        ) {
            $args = array(
                'numberposts' => -1,
                'post_parent' => (int)$user_attachment,
                'post_type'   => 'attachment'
            );

            $attachments = get_posts($args);

            if ($attachments) {
                $post_attachment_id = $attachments[0]->ID;
                $post_attachment_author = $attachments[0]->post_author;
            }

            $filename  = get_attached_file($post_attachment_id);
            $localname = '/' . get_the_author_meta('user_login', $post_attachment_author) . '/' . basename($filename);

            $zip_archive->addFile($filename, $localname);
        }
    }

    $zip_archive->close();

    header('Content-Type: application/zip');
    header('Content-disposition: attachment; filename=Attachments_' . date('Y_m_d') . '.zip');
    header('Content-Length: ' . filesize($zip_filename));

    readfile($zip_filename);
    unlink($zip_filename);

    exit();
}

function ua_upload_user_attachments() {
    if (!isset($_POST['ua_upload_attachment_form'])
        || !wp_verify_nonce($_POST['ua_upload_attachment_form'], 'ua_upload_attachment_form')
    ) {
        return;
    }

    global $current_user;
    global $wpdb;

    get_currentuserinfo();

    $result = ua_parse_file_errors($_FILES['ua_attachment_file'], $_POST['ua_attachment_caption']);

    if ($result['error']) {
        $_SESSION['ua_message'] = __('ERROR: ', 'ua_textdomain') . $result['error'];

        return;
    }

    $user_attachment_data = array(
        'post_author' => $current_user->ID,
        'post_status' => 'pending',
        'post_title'  => $result['caption'],
        'post_type'   => 'user_attachments'
    );

    if ($wpdb->insert($wpdb->posts, $user_attachment_data)) {
        $post_id = $wpdb->insert_id;

        ua_process_attachment('ua_attachment_file', $post_id, $result['caption']);
        wp_set_object_terms($post_id, (int)$_POST['ua_attachment_category'], 'ua_attachment_category');
    }
}

function ua_get_user_attachments_table($user_id) {
    // Build the exclude array
    $exclude    = array();
    $ua_options = get_option('ua_settings');

    foreach ($ua_options['ua_exclude_categories'] as $id => $value) {
        if ($value == 'true') {
            $exclude[] = $id;
        }
    }

    $args = array(
        'author'      => $user_id,
        'numberposts' => -1,
        'post_status' => 'pending',
        'post_type'   => 'user_attachments'
    );

    $user_attachments = get_posts($args);

    foreach ($user_attachments as $key => $user_attachment) {
        $user_attachment_cats = get_the_terms($user_attachment->ID, 'ua_attachment_category');

        foreach ($user_attachment_cats as $cat) {
            $user_attachment_cat = $cat->term_id;
        }

        if (in_array($user_attachment_cat, $exclude)) {
            unset($user_attachments[$key]);
        }
    }

    if (!$user_attachments) {
        return 0;
    }

    $out  = '<hr />';
    $out .= '<h2>' . __('Pending attachments', 'ua_textdomain') . '</h2>';
    $out .= '<form action="" method="post">';
    $out .= wp_nonce_field('ua_form', 'ua_form');
    $out .= '<table id="user_attachments">';
    $out .= '<thead><th>' . __('Attachment', 'ua_textdomain') . '</th><th>' . __('Caption', 'ua_textdomain') . '</th><th>' . __('Category', 'ua_textdomain') . '</th><th>' . __('Action', 'ua_textdomain') . '</th></thead>';

    foreach ($user_attachments as $user_attachment) {
        $user_attachment_cats = get_the_terms($user_attachment->ID, 'ua_attachment_category');

        foreach ($user_attachment_cats as $cat) {
            $user_attachment_cat = $cat->name;
        }

        $args = array(
            'numberposts' => -1,
            'post_parent' => $user_attachment->ID,
            'post_type'   => 'attachment'
        );

        $attachments = get_posts($args);

        if ($attachments) {
            $post_attachment_id = $attachments[0]->ID;
        }

        $out .= wp_nonce_field('ua_attachment_action_' . $user_attachment->ID, 'ua_attachment_action_id_' . $user_attachment->ID, false);
        $out .= '<tr>';
        $out .= '<td>' . wp_get_attachment_link($post_attachment_id) . '</td>';
        $out .= '<td>' . $user_attachment->post_title . '</td>';
        $out .= '<td>' . $user_attachment_cat . '</td>';
        //$out .= '<td>' . get_the_author_meta('display_name', $user_id) . '</td>';
        $out .= '<td class="align_center"><input name="ua_attachment_action_id[]" type="checkbox" value="' . $user_attachment->ID . '" /></td>';
        $out .= '</tr>';
    }

    $out .= '</table>';
    $out .= '<input name="ua_delete" type="submit" value="' . __('Delete Selected Attachments', 'ua_textdomain') . '" />';
    $out .= '</form>';

    return $out;
}

function ua_process_attachment($file, $post_id, $caption) {
    require_once ABSPATH . "wp-admin" . '/includes/image.php';
    require_once ABSPATH . 'wp-admin' . '/includes/file.php';
    require_once ABSPATH . 'wp-admin' . '/includes/media.php';

    global $wpdb;

    $attachment_id = media_handle_upload($file, $post_id);

    $attachment_data = array(
        'post_excerpt' => $caption
    );

    $wpdb->update($wpdb->posts, $attachment_data, array('ID' => $attachment_id));

    return $attachment_id;
}

function ua_parse_file_errors($file = '', $attachment_caption) {
    $result = array();
    $result['error'] = 0;

    if ($file['error']) {
        $result['error'] = __('Error uploading file!', 'ua_textdomain');

        return $result;
    }

    $attachment_caption = sanitize_text_field($attachment_caption);

    if($attachment_caption == '') {
        $result['error'] = __('Invalid attachment caption!', 'ua_textdomain');

        return $result;
    }

    $result['caption'] = $attachment_caption;

    if (!in_array($file['type'], unserialize(TYPE_WHITELIST))) {
        $result['error'] = __('File type not allowed!', 'ua_textdomain');
    } elseif (($file['size'] > MAX_UPLOAD_SIZE)) {
        $result['error'] = __('File size too large!', 'ua_textdomain');
    }

    return $result;
}

function ua_get_upload_attachment_form() {
    // Build the exclude array
    $exclude    = array();
    $ua_options = get_option('ua_settings');

    foreach ($ua_options['ua_exclude_categories'] as $id => $value) {
        if ($value == 'true') {
            $exclude[] = $id;
        }
    }

    $categories = get_categories(array(
        'echo'       => 0,
        'exclude'    => $exclude,
        'hide_empty' => 0,
        'taxonomy'   => 'ua_attachment_category'
    ));

    if (!$categories) {
        return '<p>' . __('The upload form is currently disabled (no upload categoires are available).', 'ua_textdomain') . '</p>';
    }

    $out  = '<form action="" enctype="multipart/form-data" id="ua_upload_attachment_form" method="post">';
    $out .= wp_nonce_field('ua_upload_attachment_form', 'ua_upload_attachment_form');
    $out .= '<label for="ua_attachment_caption">' . __('Attachment Caption', 'ua_textdomain') . '</label><br />';
    $out .= '<input id="ua_attachment_caption" name="ua_attachment_caption" type="text" /><br />';
    $out .= '<label for="ua_attachment_category">' . __('Attachment Category', 'ua_textdomain') . '</label><br />';
    $out .= ua_get_attachment_categories_dropdown('ua_attachment_category') . '<br />';
    $out .= '<label for="ua_attachment_file">' . __('Select Your Attachment', 'ua_textdomain') . '</label><br />';
    $out .= '<input id="ua_attachment_file" name="ua_attachment_file" type="file" /><br />';
    $out .= '<input id="ua_submit" name="ua_submit" type="submit" value="' . __('Upload Attachment', 'ua_textdomain') . '" />';
    $out .= '</form>';

    return $out;
}

function ua_get_attachment_categories_dropdown($taxonomy) {
    // Build the exclude array
    $exclude    = array();
    $ua_options = get_option('ua_settings');

    foreach ($ua_options['ua_exclude_categories'] as $id => $value) {
        if ($value == 'true') {
            $exclude[] = $id;
        }
    }

    return wp_dropdown_categories(array(
        'echo'       => 0,
        'exclude'    => $exclude,
        'hide_empty' => 0,
        'name'       => 'ua_attachment_category',
        'taxonomy'   => $taxonomy
    ));
}

function ua_init() {
    // Start session
    if (!session_id()) {
        session_start();
    }

    // Register Attachment post type
    $attachment_type_labels = array(
        'add_new'            => _x('Add New', 'attachment', 'ua_textdomain'),
        'add_new_item'       => __('Add New', 'ua_textdomain'),
        'all_items'          => __('All Attachments', 'ua_textdomain'),
        'edit_item'          => __('Edit', 'ua_textdomain'),
        'menu_name'          => 'Attachments',
        'name'               => _x('Attachments', 'post type general name', 'ua_textdomain'),
        'new_item'           => __('Add New', 'ua_textdomain'),
        'not_found'          => __('No Attachments found', 'ua_textdomain'),
        'not_found_in_trash' => __('No Attachments found in Trash', 'ua_textdomain'),
        'parent_item_colon'  => '',
        'search_items'       => __('Search Attachments', 'ua_textdomain'),
        'singular_name'      => _x('Attachment', 'post type singular name', 'ua_textdomain'),
        'view_item'          => __('View'), 'ua_textdomain'
    );

    $attachment_type_args = array(
        'capability_type' => 'post',
        'has_archive'     => true,
        'hierarchical'    => false,
        'labels'          => $attachment_type_labels,
        'menu_position'   => null,
        'public'          => true,
        'query_var'       => true,
        'rewrite'         => true,
        'supports'        => array('title', 'editor', 'author')
    );

    register_post_type('user_attachments', $attachment_type_args);

    // Register ua_attachment_category taxonomy
    $attachment_category_labels = array(
        'add_new_item'      => __('Add New Category', 'ua_textdomain'),
        'all_items'         => __('All Categories', 'ua_textdomain'),
        'edit_item'         => __('Edit Category', 'ua_textdomain'),
        'menu_name'         => __('Categories', 'ua_textdomain'),
        'name'              => _x('Categories', 'taxonomy general name', 'ua_textdomain'),
        'new_item_name'     => __('New Attachment Name', 'ua_textdomain'),
        'parent_item'       => __('Parent Category', 'ua_textdomain'),
        'parent_item_colon' => __('Parent Category:', 'ua_textdomain'),
        'search_items'      => __('Search Categories', 'ua_textdomain'),
        'singular_name'     => _x('Attachment', 'taxonomy singular name', 'ua_textdomain'),
        'update_item'       => __('Update Category', 'ua_textdomain')
    );

    $attachment_category_args = array(
        'hierarchical' => true,
        'labels'       => $attachment_category_labels,
        'query_var'    => true,
        'rewrite'      => array('slug' => 'user_attachment_category'),
        'show_ui'      => true
    );

    register_taxonomy('ua_attachment_category', array('user_attachments'), $attachment_category_args);

    if (is_admin()) {
        require_once UA_PLUGIN_PATH . 'lib/admin-options.php';
    } else {
        wp_register_style('user-attachments.css', UA_PLUGIN_URL . 'user-attachments.css');
        wp_enqueue_style('user-attachments.css');
    }
}

?>
