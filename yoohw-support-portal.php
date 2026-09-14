<?php
/*
Plugin Name: Support Portal
Description: A dedicated support portal for customer conversations, private topics, replies, attachments, and support workflows.
Version: 1.0.0
Requires at least: 6.2
Requires PHP: 7.4
Author: YoOhw
Author URI: https://yoohw.com
License: GPL-2.0-or-later
License URI: https://www.gnu.org/licenses/gpl-2.0.html
Text Domain: yoohw-support-portal
Domain Path: /languages
*/

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

// Define constants.
define( 'YOOHW_SUPPORT_PORTAL_FILE', __FILE__ );
define( 'YOOHW_SUPPORT_PORTAL_VERSION', '1.0.0' );
define( 'YOOHW_SUPPORT_PORTAL_PATH', plugin_dir_path( __FILE__ ) );
define( 'YOOHW_SUPPORT_PORTAL_URL', plugin_dir_url( __FILE__ ) );

// Include the main class
include_once YOOHW_SUPPORT_PORTAL_PATH . 'inc/class-yoohw-support-center.php';

function yoohw_support_portal_activate() {
	$is_first_install = false === get_option( 'yoohw_support_portal_settings', false )
		&& false === get_option( 'yoohw_support_center_settings', false )
		&& false === get_option( 'yoohw_support_portal_site_check', false );

	include_once YOOHW_SUPPORT_PORTAL_PATH . 'inc/class-yoohw-support-capabilities.php';
	include_once YOOHW_SUPPORT_PORTAL_PATH . 'inc/class-yoohw-support-setup-wizard.php';
	include_once YOOHW_SUPPORT_PORTAL_PATH . 'inc/class-yoohw-support-settings.php';
	include_once YOOHW_SUPPORT_PORTAL_PATH . 'inc/class-yoohw-support-site-check.php';
	include_once YOOHW_SUPPORT_PORTAL_PATH . 'inc/class-yoohw-support-router.php';

	YoOhw_Support_Capabilities::install();
	YoOhw_Support_Setup_Wizard::mark_pending_on_first_install( $is_first_install );
	YoOhw_Support_Site_Check::activate();
	YoOhw_Support_Router::activate();
}
register_activation_hook( __FILE__, 'yoohw_support_portal_activate' );

function yoohw_support_portal_deactivate() {
	include_once YOOHW_SUPPORT_PORTAL_PATH . 'inc/class-yoohw-support-router.php';
	YoOhw_Support_Router::deactivate();
}
register_deactivation_hook( __FILE__, 'yoohw_support_portal_deactivate' );

// Initialize the plugin
function yoohw_support_portal_init() {
    YoOhw_Support_Center::get_instance();
}
add_action('plugins_loaded', 'yoohw_support_portal_init');
