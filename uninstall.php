<?php
/**
 * Uninstall YoOhw Support Portal.
 *
 * The plugin intentionally does not delete posts, comments, media, users,
 * categories, or access metadata because those are site-owned support records.
 *
 * @package YoOhwSupportPortal
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

delete_option( 'yoohw_support_portal_settings' );
delete_option( 'yoohw_support_center_settings' );
delete_option( 'yoohw_support_portal_site_check' );
delete_option( 'yoohw_support_portal_site_check_notice_dismissed' );
delete_option( 'yoohw_support_routes_version' );
delete_option( 'yoohw_support_capabilities_version' );
delete_option( 'yoohw_support_setup_wizard_status' );
delete_option( 'yoohw_support_post_status_version' );

$yoohw_support_capabilities = [
	'yoohw_support_view_all_topics',
	'yoohw_support_manage_topics',
	'yoohw_support_manage_categories',
	'yoohw_support_view_customers',
	'yoohw_support_manage_settings',
];

foreach ( wp_roles()->roles as $yoohw_support_role_name => $yoohw_support_role_data ) {
	$yoohw_support_role = get_role( $yoohw_support_role_name );

	if ( ! $yoohw_support_role ) {
		continue;
	}

	foreach ( $yoohw_support_capabilities as $yoohw_support_capability ) {
		$yoohw_support_role->remove_cap( $yoohw_support_capability );
	}
}

wp_clear_scheduled_hook( 'yoohw_support_auto_resolve_inactive_posts' );
wp_clear_scheduled_hook( 'yoohw_send_topic_close_notice' );
wp_clear_scheduled_hook( 'yoohw_send_admin_followup_notice' );
