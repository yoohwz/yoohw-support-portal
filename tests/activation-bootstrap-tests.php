<?php
/** Local only: skip plugin at bootstrap; run separately for wordpress/isolated. All DB writes roll back. */
if ( ! defined( 'WP_CLI' ) || ! WP_CLI || 'local' !== wp_get_environment_type() || wp_using_ext_object_cache() ) {
	throw new RuntimeException( 'Cold activation test requires Local WP-CLI without a persistent object cache.' );
}
if ( class_exists( 'YoOhw_Support_Settings', false ) || class_exists( 'YoOhw_Support_Center', false ) ) {
	throw new RuntimeException( 'Run with --skip-plugins=yoohw-support-portal for a cold activation.' );
}
global $wpdb;
$mode = getenv( 'YSP_BOOTSTRAP_MODE' ) ?: 'wordpress';
if ( ! in_array( $mode, [ 'wordpress', 'isolated', 'fresh' ], true ) ) { throw new RuntimeException( 'Unknown test storage mode.' ); }
$engine = $wpdb->get_row( $wpdb->prepare( 'SHOW TABLE STATUS LIKE %s', $wpdb->esc_like( $wpdb->options ) ) );
if ( ! $engine || 'InnoDB' !== $engine->Engine ) { throw new RuntimeException( 'Rollback requires InnoDB options.' ); }
$before = $wpdb->get_results( "SELECT option_name, option_value, autoload FROM {$wpdb->options} ORDER BY option_name", ARRAY_A );
$settings_read = static function () use ( $mode ) { return [ 'dedicated_portal_mode' => $mode ]; };
$mail = static function () { throw new RuntimeException( 'Activation/bootstrap must not send email.' ); };
// Activation may update options/roles only. Reject schema or support-data changes before executing them.
$query_guard = static function ( $sql ) use ( $wpdb ) {
	if ( preg_match( '/^\s*(?:INSERT|UPDATE|DELETE|REPLACE|ALTER|CREATE|DROP|TRUNCATE)\b/i', $sql ) && false === strpos( $sql, $wpdb->options ) ) {
		throw new RuntimeException( 'Unexpected non-option write during activation test.' );
	}
	return $sql;
};
if ( 'fresh' !== $mode ) { add_filter( 'pre_option_yoohw_support_portal_settings', $settings_read ); }
add_filter( 'pre_wp_mail', $mail, PHP_INT_MAX ); add_filter( 'query', $query_guard );
$wpdb->query( 'START TRANSACTION' );
try {
	if ( 'fresh' === $mode ) {
		foreach ( [ 'yoohw_support_portal_settings', 'yoohw_support_center_settings', 'yoohw_support_portal_site_check' ] as $option ) { delete_option( $option ); }
	}
	require WP_PLUGIN_DIR . '/yoohw-support-portal/yoohw-support-portal.php';
	// Same activation hook used by wp plugin activate, before plugins_loaded initialization.
	do_action( 'activate_' . plugin_basename( YOOHW_SUPPORT_PORTAL_FILE ), false );
	if ( ! class_exists( 'YoOhw_Support_Email_Settings', false ) || class_exists( 'YoOhw_Email_Notification', false ) || class_exists( 'YoOhw_Support_Background', false ) ) {
		throw new RuntimeException( 'Activation must load email defaults without native workers.' );
	}
	$defaults = YoOhw_Support_Settings::defaults();
	if ( '1' !== $defaults['email_notify_admin'] || 12 !== YoOhw_Support_Email_Settings::reminder_hours( 'followup', $defaults ) || 24 !== YoOhw_Support_Email_Settings::reminder_hours( 'close', $defaults ) ) {
		throw new RuntimeException( 'Cold activation changed email defaults.' );
	}
	yoohw_support_portal_init();
	YoOhw_Support_Settings::register_settings();
	if ( ( 'isolated' === $mode ) !== YoOhw_Support_Settings::uses_isolated_storage() || ( 'wordpress' === $mode ) !== class_exists( 'YoOhw_Email_Notification', false ) ) {
		throw new RuntimeException( 'Runtime loading must respect native/isolated mode after activation.' );
	}
	if ( 'isolated' === $mode ) {
		foreach ( [ 'yoohw_retry_topic_notification', 'yoohw_send_topic_close_notice', 'yoohw_send_admin_followup_notice', 'yoohw_support_auto_resolve_inactive_posts' ] as $hook ) {
			if ( has_action( $hook ) ) { throw new RuntimeException( 'Native hook loaded in isolated mode.' ); }
		}
	}
	ob_start(); YoOhw_Support_Email_Settings::render_fields(); $html = ob_get_clean();
	if ( false === strpos( $html, 'yoohw-email_followup_hours' ) || ( 'isolated' === $mode && ! preg_match( '/<fieldset\s+disabled\b/', $html ) ) ) {
		throw new RuntimeException( 'Cold email settings render failed.' );
	}
	if ( false === strpos( YoOhw_Support_Email_Template::sample_html(), 'No support conversation data' ) ) {
		throw new RuntimeException( 'Shared sample renderer unavailable after activation.' );
	}
	WP_CLI::log( "activation-bootstrap-ok: $mode; cold activation, settings registration/render, shared template, storage boundary" );
} finally {
	$wpdb->query( 'ROLLBACK' );
	remove_filter( 'query', $query_guard ); remove_filter( 'pre_wp_mail', $mail, PHP_INT_MAX ); remove_filter( 'pre_option_yoohw_support_portal_settings', $settings_read );
	wp_cache_flush();
	$after = $wpdb->get_results( "SELECT option_name, option_value, autoload FROM {$wpdb->options} ORDER BY option_name", ARRAY_A );
	if ( $before !== $after ) { throw new RuntimeException( 'Activation test did not restore option state.' ); }
	WP_CLI::log( 'activation-bootstrap-rollback-ok: database options unchanged' );
}
