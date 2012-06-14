<?php

/*
Plugin Name: User Attachments
Plugin URI: https://github.com/cdog/user-attachments
Description: Allows registered users to submit and manage their attachments.
Version: 1.0.1
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

define('UA_PLUGIN_URL', plugin_dir_url(__FILE__));
define('MAX_UPLOAD_SIZE', 2097152);
define('TYPE_WHITELIST', serialize(array(
    'application/pdf'
)));

add_shortcode('user_attachments', 'ua_shortcode');

function ua_shortcode() {
    if (!is_user_logged_in()) {
        return '<p>' . __('You need to be logged in to submit an attachment.') . '</p>';
    }

    global $current_user;

    if (isset($_POST['ua_upload_attachment_form'])
        && wp_verify_nonce($_POST['ua_upload_attachment_form'], 'ua_upload_attachment_form')
    ) {
        $result = ua_parse_file_errors($_FILES['ua_attachment_file'], $_POST['ua_attachment_caption']);

        if ($result['error']) {
            echo '<p>' . __('ERROR: ') . $result['error'] . '</p>';
        } else {
            $user_attachment_data = array(
                'post_title'  => $result['caption'],
                'post_status' => 'pending',
                'post_author' => $current_user->ID,
                'post_type'   => 'user_attachments'
            );

            if ($post_id = wp_insert_post($user_attachment_data)) {
                ua_process_attachment('ua_attachment_file', $post_id, $result['caption']);
                wp_set_object_terms($post_id, (int)$_POST['ua_attachment_category'], 'ua_attachment_category');
            }
        }
    }

    if (isset($_POST['ua_form_delete'])
        && wp_verify_nonce($_POST['ua_form_delete'], 'ua_form_delete')
    ) {
        if (isset($_POST['ua_attachment_delete_id'])) {
            if ($user_attachments_deleted = ua_delete_user_attachments($_POST['ua_attachment_delete_id'])) {
                echo '<p>' . $user_attachments_deleted . __(' attachment(s) deleted!') . '</p>';
            }
        }
    }

    echo ua_get_upload_attachment_form($ua_attachment_caption = $_POST['ua_attachment_caption'], $ua_attachment_category = $_POST['ua_attachment_category']);

    if ($user_attachments_table = ua_get_user_attachments_table($current_user->ID)) {
        echo $user_attachments_table;
    }
}

function ua_delete_user_attachments($attachments) {
    $attachments_deleted = 0;

    foreach ($attachments as $user_attachment) {
        if (isset($_POST['ua_attachment_delete_id_' . $user_attachment])
            && wp_verify_nonce($_POST['ua_attachment_delete_id_' . $user_attachment], 'ua_attachment_delete_' . $user_attachment)
        ) {
            $args = array(
                'post_type'   => 'attachment',
                'post_parent' => (int)$user_attachment
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

    return $attachments_deleted;
}

function ua_get_user_attachments_table($user_id) {
    $args = array(
        'author'      => $user_id,
        'post_type'   => 'user_attachments',
        'post_status' => 'pending'
    );

    $user_attachments = get_posts($args);

    if (!$user_attachments) {
        return 0;
    }

    $out  = '<hr />';
    $out .= '<h2>' . __('Pending attachments') . '</h2>';
    $out .= '<form action="" method="post">';
    $out .= wp_nonce_field('ua_form_delete', 'ua_form_delete');
    $out .= '<table id="user_attachments">';
    $out .= '<thead><th>' . __('Attachment') . '</th><th>' . __('Caption') . '</th><th>' . __('Category') . '</th><th>' . __('Posted By') . '</th><th>' . __('Delete') . '</th></thead>';

    foreach ($user_attachments as $user_attachment) {
        $user_attachment_cats = get_the_terms($user_attachment->ID, 'ua_attachment_category');

        foreach ($user_attachment_cats as $cat) {
            $user_attachment_cat = $cat->name;
        }

        $args = array(
            'post_type'   => 'attachment',
            'post_parent' => $user_attachment->ID
        );

        $attachments = get_posts($args);

        if ($attachments) {
            $post_attachment_id = $attachments[0]->ID;
        }

        $out .= wp_nonce_field('ua_attachment_delete_' . $user_attachment->ID, 'ua_attachment_delete_id_' . $user_attachment->ID, false);
        $out .= '<tr>';
        $out .= '<td>' . wp_get_attachment_link($post_attachment_id) . '</td>';
        $out .= '<td>' . $user_attachment->post_title . '</td>';
        $out .= '<td>' . $user_attachment_cat . '</td>';
        $out .= '<td>' . get_the_author_meta('display_name', $user_id) . '</td>';
        $out .= '<td class="align_center"><input name="ua_attachment_delete_id[]" type="checkbox" value="' . $user_attachment->ID . '" /></td>';
        $out .= '</tr>';
    }

    $out .= '</table>';
    $out .= '<input name="ua_delete" type="submit" value="' . __('Delete Selected Attachments') . '" />';
    $out .= '</form>';  

    return $out;
}

function ua_process_attachment($file, $post_id, $caption) {
    require_once(ABSPATH . "wp-admin" . '/includes/image.php');
    require_once(ABSPATH . 'wp-admin' . '/includes/file.php');
    require_once(ABSPATH . 'wp-admin' . '/includes/media.php');

    $attachment_id = media_handle_upload($file, $post_id);

    $attachment_data = array(
        'ID'           => $attachment_id,
        'post_excerpt' => $caption
    );

    wp_update_post($attachment_data);

    return $attachment_id;
}

function ua_parse_file_errors($file = '', $attachment_caption) {
    $result = array();
    $result['error'] = 0;

    if ($file['error']) {
        $result['error'] = __('Error uploading file!');

        return $result;
    }

    $attachment_caption = sanitize_text_field($attachment_caption);

    if($attachment_caption == '') {
        $result['error'] = __('Invalid attachment caption!');

        return $result;
    }

    $result['caption'] = $attachment_caption;

    if (!in_array($file['type'], unserialize(TYPE_WHITELIST))) {
        $result['error'] = __('File type not allowed!');
    } elseif (($file['size'] > MAX_UPLOAD_SIZE)) {
        $result['error'] = __('File size too large!');
    }

    return $result;
}

function ua_get_upload_attachment_form($ua_attachment_caption = '', $ua_attachment_category = 0) {
    $out  = '<form action="" enctype="multipart/form-data" id="ua_upload_attachment_form" method="post">';
    $out .= wp_nonce_field('ua_upload_attachment_form', 'ua_upload_attachment_form');
    $out .= '<label for="ua_attachment_caption">' . __('Attachment Caption') . '</label><br />';
    $out .= '<input id="ua_attachment_caption" name="ua_attachment_caption" type="text" value="' . $ua_attachment_caption . '"/><br />';
    $out .= '<label for="ua_attachment_category">' . __('Attachment Category') . '</label><br />';
    $out .= ua_get_attachment_categories_dropdown('ua_attachment_category', $ua_attachment_category) . '<br />';
    $out .= '<label for="ua_attachment_file">' . __('Select Your Attachment') . '</label><br />';
    $out .= '<input id="ua_attachment_file" name="ua_attachment_file" type="file"><br />';
    $out .= '<input id="ua_submit" name="ua_submit" type="submit" value="' . __('Upload Attachment') . '">';
    $out .= '</form>';

    return $out;
}

function ua_get_attachment_categories_dropdown($taxonomy, $selected) {
    return wp_dropdown_categories(array(
        'taxonomy'   => $taxonomy,
        'name'       => 'ua_attachment_category',
        'selected'   => $selected,
        'hide_empty' => 0,
        'echo'       => 0
    ));
}

add_action('init', 'ua_init');

function ua_init() {
    wp_register_style('user_attachments.css', UA_PLUGIN_URL . 'user-attachments.css');
    wp_enqueue_style('user_attachments.css');

    $attachment_type_labels = array(
        'name'               => _x('Attachments', 'post type general name'),
        'singular_name'      => _x('Attachment', 'post type singular name'),
        'add_new'            => _x('Add New', 'attachment'),
        'add_new_item'       => __('Add New'),
        'edit_item'          => __('Edit'),
        'new_item'           => __('Add New'),
        'all_items'          => __('All Attachments'),
        'view_item'          => __('View'),
        'search_items'       => __('Search Attachments'),
        'not_found'          => __('No Attachments found'),
        'not_found_in_trash' => __('No Attachments found in Trash'),
        'parent_item_colon'  => '',
        'menu_name'          => 'Attachments'
    );

    $attachment_type_args = array(
        'labels'          => $attachment_type_labels,
        'public'          => true,
        'query_var'       => true,
        'rewrite'         => true,
        'capability_type' => 'post',
        'has_archive'     => true,
        'hierarchical'    => false,
        'menu_position'   => null,
        'supports'        => array('title', 'editor', 'author')
    );

    register_post_type('user_attachments', $attachment_type_args);

    $attachment_category_labels = array(
        'name'              => _x('Categories', 'taxonomy general name'),
        'singular_name'     => _x('Attachment', 'taxonomy singular name'),
        'search_items'      => __('Search Categories'),
        'all_items'         => __('All Categories'),
        'parent_item'       => __('Parent Category'),
        'parent_item_colon' => __('Parent Category:'),
        'edit_item'         => __('Edit Category'),
        'update_item'       => __('Update Category'),
        'add_new_item'      => __('Add New Category'),
        'new_item_name'     => __('New Attachment Name'),
        'menu_name'         => __('Categories')
    );

    $attachment_category_args = array(
        'hierarchical' => true,
        'labels'       => $attachment_category_labels,
        'show_ui'      => true,
        'query_var'    => true,
        'rewrite'      => array('slug' => 'user_attachment_category')
    );

    register_taxonomy('ua_attachment_category', array('user_attachments'), $attachment_category_args);

    /*$default_attachment_cats = array(__('Uncategorized'));

    foreach ($default_attachment_cats as $cat) {
        if (!term_exists($cat, 'ua_attachment_category')) {
            wp_insert_term($cat, 'ua_attachment_category');
        }
    }*/
}

?>
