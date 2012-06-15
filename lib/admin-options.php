<?php
/**
 * Define Constants
 */
define('UA_SHORTNAME', 'ua'); 
define('UA_PAGE_BASENAME', 'ua_settings'); 

/**
 * Specify Hooks/Filters
 */
add_action( 'admin_menu', 'ua_add_menu' );
add_action( 'admin_init', 'ua_register_settings' );

/**
 * Include the required files
 */
require_once('admin-helper-functions.php');
require_once('admin-settings-page.php');

 /**
 * Helper function for defining variables according to current page content
 *
 * @return array
 */
function ua_get_settings() {
	
	$output = array();
	
	/*PAGES*/
	// get current page
	$page = ua_get_admin_page();
	
	/*DEFINE VARS*/
	// define variables according to registered admin menu page: ua_add_menu()
	switch ($page) {
		case UA_PAGE_BASENAME:
			$ua_option_name 		= 'ua_options';
			$ua_settings_page_title = __( 'User Attachments Settings','ua_textdomain');	
			$ua_page_sections 		= ua_options_page_sections();
			$ua_page_fields 		= ua_options_page_fields();
		break;
	}
	
	// put together the output array 
	$output['ua_option_name'] 		= $ua_option_name;
	$output['ua_page_title'] 		= $ua_settings_page_title;
	$output['ua_page_sections'] 	= $ua_page_sections;
	$output['ua_page_fields'] 		= $ua_page_fields;
	
return $output;
}

/**
 * Helper function for registering our form field settings
 *
 * src: http://alisothegeek.com/2011/01/wordpress-settings-api-tutorial-1/
 * @param (array) $args The array of arguments to be used in creating the field
 * @return function call
 */
function ua_create_settings_field( $args = array() ) {
	// default array to overwrite when calling the function
	$defaults = array(
		'id'      => 'default_field', 					// the ID of the setting in our options array, and the ID of the HTML form element
		'title'   => 'Default Field', 					// the label for the HTML form element
		'desc'    => 'This is a default description.', 	// the description displayed under the HTML form element
		'std'     => '', 								// the default value for this setting
		'type'    => 'text', 							// the HTML form element to use
		'section' => 'main_section', 					// the section this setting belongs to — must match the array key of a section in ua_options_page_sections()
		'choices' => array(), 							// (optional): the values in radio buttons or a drop-down menu
		'class'   => '' 								// the HTML form element class. Is used for validation purposes and may be also use for styling if needed.
	);
	
	// "extract" to be able to use the array keys as variables in our function output below
	extract( wp_parse_args( $args, $defaults ) );
	
	// additional arguments for use in form field output in the function ua_form_field_fn!
	$field_args = array(
		'type'      => $type,
		'id'        => $id,
		'desc'      => $desc,
		'std'       => $std,
		'choices'   => $choices,
		'label_for' => $id,
		'class'     => $class
	);

	add_settings_field( $id, $title, 'ua_form_field_fn', __FILE__, $section, $field_args );

}

/**
 * Register our setting, settings sections and settings fields
 */
function ua_register_settings(){
	
	// get the settings sections array
	$settings_output 	= ua_get_settings();
	$ua_option_name = $settings_output['ua_option_name'];
	
	//setting
	register_setting($ua_option_name, $ua_option_name, 'ua_validate_options' );
	
	//sections
	if(!empty($settings_output['ua_page_sections'])){
		// call the "add_settings_section" for each!
		foreach ( $settings_output['ua_page_sections'] as $id => $title ) {
			add_settings_section( $id, $title, 'ua_section_fn', __FILE__);
		}
	}
		
	//fields
	if(!empty($settings_output['ua_page_fields'])){
		// call the "add_settings_field" for each!
		foreach ($settings_output['ua_page_fields'] as $option) {
			ua_create_settings_field($option);
		}
	}
}

/**
 * The admin menu pages
 */
function ua_add_menu(){
	add_submenu_page('edit.php?post_type=user_attachments', __('User Attachments Settings'), __('Settings','ua_textdomain'), 'manage_options', UA_PAGE_BASENAME, 'ua_settings_page_fn');
}

/*
 * Section HTML, displayed before the first option
 * @return echoes output
 */
function  ua_section_fn($desc) {
	echo '<p>' . __('Settings for categories','ua_textdomain') . '</p>';
}

/**
 * Form Fields HTML
 * All form field types share the same function!!
 * @return echoes output
 */
function ua_form_field_fn($args = array()) {
	
	extract( $args );
	
	// get the settings sections array
	$settings_output 	= ua_get_settings();
	
	$ua_option_name = $settings_output['ua_option_name'];
	$options 			= get_option($ua_option_name);
	
	// pass the standard value if the option is not yet set in the database
	if ( !isset( $options[$id] ) && 'type' != 'checkbox' ) {
		$options[$id] = $std;
	}
	
	// additional field class. output only if the class is defined in the create_setting arguments
	$field_class = ($class != '') ? ' ' . $class : '';
	
	// switch html display based on the setting type.	
	switch ( $type ) {
		
		case "multi-checkbox":
			foreach($choices as $item) {
				
				$item = explode("|",$item);
				$item[0] = esc_html($item[0], 'ua_textdomain');
				
				$checked = '';
				
			    if ( isset($options[$id][$item[1]]) ) {
					if ( $options[$id][$item[1]] == 'true') {
			   			$checked = 'checked="checked"';
					}
				}
				
				echo "<input class='checkbox$field_class' type='checkbox' id='$id|$item[1]' name='" . $ua_option_name . "[$id|$item[1]]' value='1' $checked /> $item[0] <br/>";
			}
			echo ($desc != '') ? "<br /><span class='description'>$desc</span>" : "";
		break;
	}
}

/*
 * Admin Settings Page HTML
 * 
 * @return echoes output
 */
function ua_settings_page_fn() {
	// get the settings sections array
	$settings_output = ua_get_settings();
?>
	<div class="wrap">
		<?php 
		// dislays the page title
		ua_settings_page_header(); 
		?>
		
		<form action="options.php" method="post">
			<?php 
			// http://codex.wordpress.org/Function_Reference/settings_fields
			settings_fields($settings_output['ua_option_name']); 
			// http://codex.wordpress.org/Function_Reference/do_settings_sections
			do_settings_sections(__FILE__); 
			?>
			
			<p class="submit">
				<input name="Submit" type="submit" class="button-primary" value="<?php esc_attr_e('Save Changes','ua_textdomain'); ?>" />
			</p>
			
		</form>
	</div><!-- wrap -->
<?php }

/**
 * Validate input
 * 
 * @return array
 */
function ua_validate_options($input) {
	
	// for enhanced security, create a new empty array
	$valid_input = array();
	
	// collect only the values we expect and fill the new $valid_input array i.e. whitelist our option IDs
	
		// get the settings sections array
		$settings_output = ua_get_settings();
		
		$options = $settings_output['ua_page_fields'];
		
		// run a foreach and switch on option type
		foreach ($options as $option) {
		
			switch ( $option['type'] ) {
				
				case 'multi-checkbox':
					unset($checkboxarray);
					$check_values = array();
					foreach ($option['choices'] as $k => $v ) {
						// explode the connective
						$pieces = explode("|", $v);
						
						$check_values[] = $pieces[1];
					}
					
					foreach ($check_values as $v ) {		
						
						// Check that the option isn't null
						if (!empty($input[$option['id'] . '|' . $v])) {
							// If it's not null, make sure it's true, add it to an array
							$checkboxarray[$v] = 'true';
						}
						else {
							$checkboxarray[$v] = 'false';
						}
					}
					// Take all the items that were checked, and set them as the main option
					if (!empty($checkboxarray)) {
						$valid_input[$option['id']] = $checkboxarray;
					}
				break;
				
			}
		}
return $valid_input; // return validated input
}

/**
 * Helper function for creating admin messages
 * src: http://www.wprecipes.com/how-to-show-an-urgent-message-in-the-wordpress-admin-area
 *
 * @param (string) $message The message to echo
 * @param (string) $msgclass The message class
 * @return echoes the message
 */
function ua_show_msg($message, $msgclass = 'info') {
	echo "<div id='message' class='$msgclass'>$message</div>";
}

/**
 * Callback function for displaying admin messages
 *
 * @return calls ua_show_msg()
 */
function ua_admin_msgs() {
	
	// check for our settings page - need this in conditional further down
	$ua_settings_pg = strpos($_GET['page'], UA_PAGE_BASENAME);
	// collect setting errors/notices: //http://codex.wordpress.org/Function_Reference/get_settings_errors
	$set_errors = get_settings_errors(); 
	
	//display admin message only for the admin to see, only on our settings page and only when setting errors/notices are returned!	
	if(current_user_can ('manage_options') && $ua_settings_pg !== FALSE && !empty($set_errors)){

		// have our settings succesfully been updated? 
		if($set_errors[0]['code'] == 'settings_updated' && isset($_GET['settings-updated'])){
			ua_show_msg("<p>" . $set_errors[0]['message'] . "</p>", 'updated');
		
		// have errors been found?
		}else{
			// there maybe more than one so run a foreach loop.
			foreach($set_errors as $set_error){
				// set the title attribute to match the error "setting title" - need this in js file
				ua_show_msg("<p class='setting-error-message' title='" . $set_error['setting'] . "'>" . $set_error['message'] . "</p>", 'error');
			}
		}
	}
}

// admin messages hook!
add_action('admin_notices', 'ua_admin_msgs');
?>
