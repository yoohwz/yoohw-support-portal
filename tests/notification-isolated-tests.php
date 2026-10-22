<?php
/** Run with wp --skip-plugins=yoohw-support-portal eval-file tests/notification-isolated-tests.php. */
if ( ! defined( 'WP_CLI' ) || ! WP_CLI || 'local' !== wp_get_environment_type() ) {
	throw new RuntimeException( 'Isolated notification tests require WP-CLI and WP_ENVIRONMENT_TYPE=local.' );
}
if ( class_exists( 'YoOhw_Support_Center', false ) ) {
	throw new RuntimeException( 'Skip the plugin during bootstrap for isolated loading tests.' );
}
$mode = 'isolated';
$settings_filter = static function () use ( &$mode ) { return [ 'dedicated_portal_mode' => $mode ]; };
add_filter( 'pre_option_yoohw_support_portal_settings', $settings_filter );
// Fail closed if any code attempts actual email during this loading-only test.
$mail_filter = static function () { throw new RuntimeException( 'Unexpected email in isolated loading test.' ); };
add_filter( 'pre_wp_mail', $mail_filter, PHP_INT_MAX );
try {
	require_once WP_PLUGIN_DIR . '/yoohw-support-portal/yoohw-support-portal.php';
	yoohw_support_portal_init();
	if ( ! YoOhw_Support_Settings::uses_isolated_storage() || class_exists( 'YoOhw_Email_Notification', false ) || class_exists( 'YoOhw_Post_Status_Resolved', false ) || class_exists( 'YoOhw_Support_Background', false ) ) {
		throw new RuntimeException( 'Native notification/resolve workers must stay unloaded in isolated mode.' );
	}
	foreach ( [ 'yoohw_retry_topic_notification', 'yoohw_send_topic_close_notice', 'yoohw_send_admin_followup_notice', 'yoohw_support_auto_resolve_inactive_posts' ] as $hook ) {
		if ( has_action( $hook ) ) { throw new RuntimeException( 'Unexpected native worker: ' . $hook ); }
		do_action( $hook, 'author', 0 );
	}
	if ( has_filter( 'wp_mail', [ 'YoOhw_Email_Notification', 'apply_global_email_template' ] ) ) {
		throw new RuntimeException( 'Native global email template must not load in isolated mode.' );
	}
	WP_CLI::log( 'notification-isolated-ok: native workers remain unloaded; queued hooks are inert' );
} finally {
	remove_filter( 'pre_wp_mail', $mail_filter, PHP_INT_MAX );
	remove_filter( 'pre_option_yoohw_support_portal_settings', $settings_filter );
}
