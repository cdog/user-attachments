<?php
/**
 * Helper function: Check for pages and return the current page name
 * 
 * @return string
 */
function ua_get_admin_page() {
	global $pagenow;
	
	// read the current page
	$current_page = trim($_GET['page']);
	
	// use a different way to read the current page name when the form submits
	if ($pagenow == 'options.php') {
		// get the page name
		$parts 	= explode('page=', $_POST['_wp_http_referer']); // http://codex.wordpress.org/Function_Reference/wp_referer_field
		$page  	= $parts[1]; 

		// account for the use of tabs (we do not want the tab name to be part of our return value!)
		$t 		= strpos($page,"&");
		
		if($t !== FALSE) {			 
			$page  = substr($parts[1],0,$t); 
		}
		
		$current_page = trim($page);
	}
	
return $current_page;
}

/**
 * Helper function: Creates settings page title
 *
 * @return echos output
 */
function ua_settings_page_header() {
    $settings_output 	= ua_get_settings();
	
	// display the icon and page title
	screen_icon('options-general');
	echo '<h2>' . $settings_output['ua_page_title'] . '</h2>';
}
?>