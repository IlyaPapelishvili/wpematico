<?php
// don't load directly 
if ( !defined('ABSPATH') ) {
	header( 'Status: 403 Forbidden' );
	header( 'HTTP/1.1 403 Forbidden' );
	exit();
}








add_filter(	'plugin_row_meta', 'wpematico_row_meta',10,2);
add_filter(	'plugin_action_links_' . WPEMATICO_BASENAME, 'wpematico_action_links');


/**
* Actions-Links del Plugin
*
* @param   array   $data  Original Links
* @return  array   $data  modified Links
*/
function wpematico_action_links($data)	{
	if ( !current_user_can('manage_options') ) {
		return $data;
	}
	return array_merge(	$data,	array(
		'<a href="edit.php?post_type=wpematico&page=wpematico_settings" title="' . __('Load WPeMatico Settings Page', 'wpematico' ) . '">' . __('Settings', 'wpematico' ) . '</a>',
		'<a href="https://etruel.com/downloads/wpematico-perfect-package/" target="_Blank" title="' . __('Take a look at the all bundled Addons', 'wpematico' ) . '">' . __('Go Perfect', 'wpematico' ) . '</a>',
//		'<a href="https://etruel.com/checkout?edd_action=add_to_cart&download_id=4313&edd_options[price_id]=2" target="_Blank" title="' . __('Buy all bundled Addons', 'wpematico' ) . '">' . __('Go Perfect', 'wpematico' ) . '</a>',
	));
}

/**
* Meta-Links del Plugin
*
* @param   array   $data  Original Links
* @param   string  $page  plugin actual
* @return  array   $data  modified Links
*/

function wpematico_row_meta($data, $page)	{
	if ( $page != WPEMATICO_BASENAME ) {
		return $data;
	}
	return array_merge(	$data,	array(
		//'<a href="http://www.wpematico.com/wpematico/" target="_blank">' . __('Info & comments') . '</a>',
		'<a href="'.  admin_url('plugins.php?page=wpemaddons').'" target="_self">' . __('Extensions') . '</a>',
		'<a href="https://etruel.com/my-account/support/" target="_blank">' . __('Support') . '</a>',
		'<a href="https://wordpress.org/support/view/plugin-reviews/wpematico?filter=5&rate=5#new-post" target="_Blank" title="Rate 5 stars on Wordpress.org">' . __('Rate Plugin', 'wpematico' ) . '</a>',
		'<strong><a href="https://etruel.com/downloads/wpematico-pro/" target="_Blank" title="' . __('Take a look at the PRO version features', 'wpematico' ) . '">' . __('Go PRO', 'wpematico' ) . '</a></strong>'
//		'<a href="https://etruel.com/checkout?edd_action=add_to_cart&download_id=272&edd_options[price_id]=2" target="_Blank" title="' . __('Go to buy PRO version', 'wpematico' ) . '">' . __('Go PRO', 'wpematico' ) . '</a>'
//			'<a href="https://www.paypal.com/cgi-bin/webscr?cmd=_s-xclick&hosted_button_id=B8V39NWK3NFQU" target="_blank">' . __('Donate', 'wpematico' ) . '</a>'
	));
}		



/***************************************************************************************
/***************************************************************************************
 * Activation, Upgrading and uninstall functions
 **************************************************************************************/
register_activation_hook( WPEMATICO_BASENAME, 'wpematico_activate' );
register_deactivation_hook( WPEMATICO_BASENAME, 'wpematico_deactivate' );
register_uninstall_hook( WPEMATICO_BASENAME, 'wpematico_uninstall' );

add_action( 'plugins_loaded', 'wpematico_update_db_check' );

function wpematico_update_db_check() {
	if (version_compare(WPEMATICO_VERSION, get_option( 'wpematico_db_version' ), '>')) { // check if updated (WILL SAVE new version on welcome )
		if ( !get_transient( '_wpematico_activation_redirect' ) ){ //just one time running
	        wpematico_install( false );
		}
    }
}


function wpematico_install( $update_campaigns = false ){
	if($update_campaigns) {
		//tweaks old campaigns data, now saves meta for columns
		$campaigns_data = array();
		$args = array(
			'orderby'         => 'ID',
			'order'           => 'ASC',
			'post_type'       => 'wpematico', 
			'numberposts' => -1
		);
		$campaigns = get_posts( $args );
		foreach( $campaigns as $post ):
			$campaigndata = WPeMatico::get_campaign( $post->ID );	
			WPeMatico::update_campaign($post->ID, $campaigndata);
		endforeach; 
	}

	// Add the transient to redirect 
	set_transient( '_wpematico_activation_redirect', true, 120 ); // After two minutes lost welcome screen

}

/**
 * activation
 * @return void
 */
function wpematico_activate() {
	WPeMatico :: Create_campaigns_page();
	// ATTENTION: This is *only* done during plugin activation hook // You should *NEVER EVER* do this on every page load!!
	flush_rewrite_rules();
	
	// Call installation and update routines
    wpematico_install();
	
	wp_clear_scheduled_hook('wpematico_cron');
	//make schedule
	wp_schedule_event(0, 'wpematico_int', 'wpematico_cron'); 
}

/**
 * deactivation
 * @return void
 */
function wpematico_deactivate() {
	//remove cron job
	wp_clear_scheduled_hook('wpematico_cron');
	// Don't delete options or campaigns
}

/**
 * Uninstallation
 * @global $wpdb, $blog_id
 * @return void
 */
function wpematico_uninstall() {
	global $wpdb, $blog_id;
	$danger = get_option( 'WPeMatico_danger');
	$danger['wpemdeleoptions']	 = (isset($danger['wpemdeleoptions']) && !empty($danger['wpemdeleoptions']) ) ? $danger['wpemdeleoptions'] : false;
	$danger['wpemdelecampaigns'] = (isset($danger['wpemdelecampaigns']) && !empty($danger['wpemdelecampaigns']) ) ? $danger['wpemdelecampaigns'] : false;
	if ( is_network_admin() && $danger['wpemdeleoptions'] ) {
		if ( isset ( $wpdb->blogs ) ) {
			$blogs = $wpdb->get_results(
				$wpdb->prepare(
					'SELECT blog_id ' .
					'FROM ' . $wpdb->blogs . ' ' .
					"WHERE blog_id <> '%s'",
					$blog_id
				)
			);
			foreach ( $blogs as $blog ) {
				delete_blog_option( $blog->blog_id, WPeMatico :: OPTION_KEY );
			}
		}
	}
	if ($danger['wpemdeleoptions']) {
		delete_option( WPeMatico :: OPTION_KEY );
		delete_option( 'wpematico_db_version' );
	}
	//delete campaigns
	if($danger['wpemdelecampaigns']) {
		$args = array( 'post_type' => 'wpematico', 'orderby' => 'ID', 'order' => 'ASC' );
		$campaigns = get_posts( $args );
		foreach( $campaigns as $post ) {
			wp_delete_post( $post->ID, true);  // forces delete to avoid trash
		}
	}
}

function wpematico_convert_to_utf8($string) {
	$from = mb_detect_encoding($string, "auto");
	if ($from && $from != 'UTF-8') {
		$string = mb_convert_encoding($string, 'UTF-8', $from);
	}
	return $string;
}

//function for PHP error handling
function wpematico_joberrorhandler($errno, $errstr, $errfile, $errline) {
	global $campaign_log_message, $jobwarnings, $joberrors;
    
	//genrate timestamp
	if (!version_compare(phpversion(), '6.9.0', '>')) { // PHP Version < 5.7 dirname 2nd 
		if (!function_exists('memory_get_usage')) { // test if memory functions compiled in
			$timestamp="<span style=\"background-color:c3c3c3;\" title=\"[Line: ".$errline."|File: ".trailingslashit(dirname($errfile)).basename($errfile)."\">".date_i18n('Y-m-d H:i.s').":</span> ";
		} else  {
			$timestamp="<span style=\"background-color:c3c3c3;\" title=\"[Line: ".$errline."|File: ".trailingslashit(dirname($errfile)).basename($errfile)."|Mem: ". WPeMatico :: formatBytes(@memory_get_usage(true))."|Mem Max: ". WPeMatico :: formatBytes( @memory_get_peak_usage(true))."|Mem Limit: ".ini_get('memory_limit')."]\">".date_i18n('Y-m-d H:i.s').":</span> ";
		}
	}else{
		if (!function_exists('memory_get_usage')) { // test if memory functions compiled in
			$timestamp="<span style=\"background-color:c3c3c3;\" title=\"[Line: ".$errline."|File: ".trailingslashit(dirname($errfile,2)).basename($errfile)."\">".date_i18n('Y-m-d H:i.s').":</span> ";
		} else  {
			$timestamp="<span style=\"background-color:c3c3c3;\" title=\"[Line: ".$errline."|File: ".trailingslashit(dirname($errfile,2)).basename($errfile)."|Mem: ". WPeMatico :: formatBytes(@memory_get_usage(true))."|Mem Max: ". WPeMatico :: formatBytes( @memory_get_peak_usage(true))."|Mem Limit: ".ini_get('memory_limit')."]\">".date_i18n('Y-m-d H:i.s').":</span> ";
		}
	}

	switch ($errno) {
    case E_NOTICE:
	case E_USER_NOTICE:
		$massage=$timestamp."<span>".$errstr."</span>";
        break;
    case E_WARNING:
    case E_USER_WARNING:
		$jobwarnings += 1;
		$massage=$timestamp."<span style=\"background-color:yellow;\">".__('[WARNING]', 'wpematico' )." ".$errstr."</span>";
        break;
	case E_ERROR: 
    case E_USER_ERROR:
		$joberrors += 1;
		$massage=$timestamp."<span style=\"background-color:red;\">".__('[ERROR]', 'wpematico' )." ".$errstr."</span>";
        break;
	case E_DEPRECATED:
	case E_USER_DEPRECATED:
		$massage=$timestamp."<span>".__('[DEPRECATED]', 'wpematico' )." ".$errstr."</span>";
		break;
	case E_STRICT:
		$massage=$timestamp."<span>".__('[STRICT NOTICE]', 'wpematico' )." ".$errstr."</span>";
		break;
	case E_RECOVERABLE_ERROR:
		$massage=$timestamp."<span>".__('[RECOVERABLE ERROR]', 'wpematico' )." ".$errstr."</span>";
		break;
	default:
		$massage=$timestamp."<span>[".$errno."] ".$errstr."</span>";
        break;
    }

	if (!empty($massage)) {

		$campaign_log_message .= $massage."<br />\n";

		if ($errno==E_ERROR or $errno==E_CORE_ERROR or $errno==E_COMPILE_ERROR) {//Die on fatal php errors.
			die("Fatal Error:" . $errno);
		}
		//300 is most webserver time limit. 0= max time! Give script 5 min. more to work.
		@set_time_limit(300); 
		//true for no more php error hadling.
		return true;
	} else {
		return false;
	}

	
}