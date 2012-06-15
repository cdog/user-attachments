<?php
/**
 * Define our settings sections
 *
 * array key=$id, array value=$title in: add_settings_section( $id, $title, $callback, $page );
 * @return array
 */
function ua_options_page_sections() {
	$sections = array();

	$sections['checkbox_section'] = __('Categories', 'ua_textdomain');

	return $sections;
}

/**
 * Define our form fields (settings) 
 *
 * @return array
 */
function ua_options_page_fields() {
	$options[] = array(
		"section" => "checkbox_section",
		"id"      => UA_SHORTNAME . "_multicheckbox_inputs",
		"title"   => __( 'Exclude Categories', 'ua_textdomain' ),
		"desc"    => __( 'Selected categories are excluded from the upload form', 'ua_textdomain' ),
		"type"    => "multi-checkbox",
		"std"     => '',
		"choices" => array( __('Checkbox 1','ua_textdomain') . "|chckbx1", __('Checkbox 2','ua_textdomain') . "|chckbx2", __('Checkbox 3','ua_textdomain') . "|chckbx3", __('Checkbox 4','ua_textdomain') . "|chckbx4")	
	);
	
	return $options;	
}

/**
 * Contextual Help
 */
function ua_options_page_contextual_help() {
	$text 	= "<h3>" . __('User Attachments Settings - Contextual Help','ua_textdomain') . "</h3>";
	$text 	.= "<p>" . __('Contextual help goes here. You may want to use different html elements to format your text as you want.','ua_textdomain') . "</p>";
	// must return text! NOT echo
	return $text;
} ?>