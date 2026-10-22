<?php
/** Local WP-CLI only: synthetic topics, intercepted mail and emulated settings/cursor writes. */
if ( ! defined( 'WP_CLI' ) || ! WP_CLI || 'local' !== wp_get_environment_type() ) {
	throw new RuntimeException( 'Auto-resolve tests require Local WP-CLI.' );
}
require_once ABSPATH . 'wp-admin/includes/user.php';
require_once ABSPATH . 'wp-admin/includes/template.php';
global $wpdb;
$settings = YoOhw_Support_Settings::defaults();
$settings['dedicated_portal_mode'] = 'wordpress';
$prefix = 'ysp25-' . strtolower( wp_generate_password( 8, false, false ) );
$posts = $users = $attempts = [];
$checks = 0; $cursor = 0; $change_during_query = null;
$original_user = get_current_user_id(); $original_get = $_GET; $original_request = $_REQUEST;
$original_errors = $GLOBALS['wp_settings_errors'] ?? [];
$settings_read = static function () use ( &$settings ) { return $settings; };
$cursor_read = static function () use ( &$cursor ) { return $cursor; };
$cursor_write = static function ( $value, $old ) use ( &$cursor ) { $cursor = $value; return $old; };
$mail = static function ( $result, $args ) use ( &$attempts ) { $attempts[] = $args; return true; };
$query = static function ( $sql ) use ( &$posts, &$change_during_query, $wpdb ) {
	if ( 0 === strpos( $sql, "SELECT ID FROM {$wpdb->posts} WHERE post_type = 'post' AND post_status = 'publish' AND ID >" ) ) {
		$sql = str_replace( ' ORDER BY ID ASC', ' AND ID IN (' . implode( ',', $posts ?: [ 0 ] ) . ') ORDER BY ID ASC', $sql );
	}
	if ( $change_during_query && false !== strpos( $sql, $wpdb->comments ) ) {
		$callback = $change_during_query; $change_during_query = null; $callback();
	}
	return $sql;
};
add_filter( 'pre_option_' . YoOhw_Support_Settings::OPTION_NAME, $settings_read );
add_filter( 'pre_option_yoohw_support_cursor_auto_resolve', $cursor_read );
add_filter( 'pre_update_option_yoohw_support_cursor_auto_resolve', $cursor_write, 999, 2 );
add_filter( 'pre_wp_mail', $mail, PHP_INT_MAX, 2 ); add_filter( 'query', $query );
$assert = static function ( $ok, $message ) use ( &$checks ) { if ( ! $ok ) { throw new RuntimeException( $message ); } ++$checks; };
$save = static function ( $tab, $patch ) use ( &$settings ) {
	$old = $settings;
	$clean = YoOhw_Support_Settings::sanitize( array_merge( $settings, $patch, [ '_tab' => $tab ] ) );
	$settings = apply_filters( 'pre_update_option_' . YoOhw_Support_Settings::OPTION_NAME, $clean, $old );
};
$reply = static function ( $pid, $who, $hours ) use ( &$users ) {
	$date = gmdate( 'Y-m-d H:i:s', time() - (int) ( $hours * HOUR_IN_SECONDS ) );
	return wp_insert_comment( [ 'comment_post_ID' => $pid, 'user_id' => $users[$who], 'comment_author' => $who, 'comment_author_email' => $who . '@example.invalid', 'comment_content' => 'Synthetic reply.', 'comment_approved' => 1, 'comment_date_gmt' => $date, 'comment_date' => get_date_from_gmt( $date ) ] );
};
$topic = static function ( $hours, $who = 'support' ) use ( &$posts, &$users, $reply, $prefix ) {
	remove_action( 'transition_post_status', [ 'YoOhw_Email_Notification', 'maybe_send_admin_notification' ], 10 );
	try {
		$id = wp_insert_post( [ 'post_type' => 'post', 'post_status' => 'publish', 'post_author' => $users['owner'], 'post_title' => $prefix, 'comment_status' => 'open' ], true );
		if ( is_wp_error( $id ) ) { throw new RuntimeException( $id->get_error_message() ); }
		$posts[] = $id; $reply( $id, $who, $hours ); return $id;
	} finally { add_action( 'transition_post_status', [ 'YoOhw_Email_Notification', 'maybe_send_admin_notification' ], 10, 3 ); }
};
$invoke = static function ( $class, $method, $pid ) { $r = new ReflectionMethod( $class, $method ); $r->setAccessible( true ); $r->invoke( null, $pid ); };
$resolve = static function ( $pid ) use ( $invoke ) { $invoke( 'YoOhw_Post_Status_Resolved', 'maybe_auto_resolve_post_after_admin_reply', $pid ); };
$warn = static function ( $pid ) use ( $invoke ) { $invoke( 'YoOhw_Email_Notification', 'maybe_send_topic_close_notice', $pid ); };
try {
	foreach ( [ 'owner' => 'subscriber', 'other' => 'subscriber', 'support' => 'administrator' ] as $who => $role ) {
		$id = wp_insert_user( [ 'user_login' => $prefix . $who, 'user_pass' => wp_generate_password(), 'user_email' => $prefix . $who . '@example.invalid', 'role' => $role ] );
		$assert( ! is_wp_error( $id ), 'Create synthetic user.' ); $users[$who] = $id;
	}
	$assert( 48 === YoOhw_Support_Settings::auto_resolve_hours(), 'Default remains 48 hours.' );
	$pid = $topic( 49 );
	foreach ( [ '', '0' ] as $off ) {
		$save( 'general', [ 'auto_resolve_hours' => $off ] ); $attempts = [];
		$resolve( $pid ); $warn( $pid );
		$assert( 0 === $settings['auto_resolve_hours'] && 'publish' === get_post_status( $pid ) && ! $attempts, 'Empty/zero suppresses resolve and warning for old open topic.' );
		$assert( 24 === YoOhw_Support_Email_Settings::reminder_hours( 'close' ), 'Turning off auto-resolve retains warning preference.' );
	}
	$save( 'general', [ 'auto_resolve_hours' => 72 ] );
	$assert( 'publish' === get_post_status( $pid ), 'Saving does not itself resolve existing topics.' );
	$resolve( $pid ); $assert( 'publish' === get_post_status( $pid ), 'Extended duration applies to old open topic.' );
	foreach ( [ -1, '1.5', 'bad', [], null, true, '999999999999999999999999999' ] as $invalid ) {
		$save( 'general', [ 'auto_resolve_hours' => $invalid ] );
		$assert( 72 === $settings['auto_resolve_hours'], 'Invalid duration retains previous policy.' );
	}
	$before = $settings;
	$save( 'general', [ 'auto_resolve_hours' => 24, 'docs_url' => 'https://example.invalid/change' ] );
	$assert( $before == $settings, 'Incompatible equal warning/resolve rejects entire General save.' );
	$save( 'general', [ 'auto_resolve_hours' => 12 ] );
	$assert( $before == $settings, 'Warning later than resolve rejects General save.' );
	$save( 'emails', [ 'email_close_hours' => 72, 'email_from_name' => 'Rejected' ] );
	$assert( $before == $settings, 'Incompatible Emails save retains whole old configuration.' );
	$save( 'emails', [ 'email_close_hours' => 36 ] );
	$assert( 72 === $settings['auto_resolve_hours'] && 36 === $settings['email_close_hours'], 'Emails can save warning above former 47-hour cap without changing General.' );
	$save( 'general', [ 'auto_resolve_hours' => 48 ] ); $attempts = [];
	$warn( $pid ); $assert( ! $attempts, 'No warning beyond shortened deadline.' );
	$resolve( $pid );
	$assert( 'yoohw_resolved' === get_post_status( $pid ) && 'closed' === get_post( $pid )->comment_status, 'Shortening resolves existing overdue topic and closes comments on cron.' );
	$save( 'general', [ 'auto_resolve_hours' => 0 ] ); $resolve( $pid );
	$save( 'general', [ 'auto_resolve_hours' => 72 ] ); $resolve( $pid );
	$assert( 'yoohw_resolved' === get_post_status( $pid ), 'Policy changes never reopen resolved topic.' );
	$pid = $topic( 73 ); $save( 'general', [ 'auto_resolve_hours' => 0 ] ); $resolve( $pid );
	$save( 'general', [ 'auto_resolve_hours' => 72 ] );
	$assert( 'publish' === get_post_status( $pid ), 'Re-enable waits for cron.' ); $resolve( $pid );
	$assert( 'yoohw_resolved' === get_post_status( $pid ), 'Re-enable considers existing eligible open topic.' );
	// Manual staff resolution is independent of the automatic timer.
	wp_set_current_user( $users['support'] );
	$stop_redirect = static function ( $location ) { throw new RuntimeException( 'manual-redirect', 302 ); };
	add_filter( 'wp_redirect', $stop_redirect, -999 );
	try {
		foreach ( [ 0, 72 ] as $duration ) {
			$save( 'general', [ 'auto_resolve_hours' => $duration ] );
			$manual = $topic( 0 );
			$_GET = [ 'post_id' => $manual ]; $_REQUEST = [ '_wpnonce' => wp_create_nonce( 'yoohw_support_post_resolve_' . $manual ) ];
			try { YoOhw_Post_Status_Resolved::handle_resolve_row_action(); throw new RuntimeException( 'Manual action returned before redirect.' ); }
			catch ( RuntimeException $error ) { $assert( 302 === $error->getCode(), 'Manual resolve reaches guarded redirect with automatic timer off or enabled.' ); }
			$assert( 'yoohw_resolved' === get_post_status( $manual ) && 'closed' === get_post( $manual )->comment_status, 'Staff manually resolves a new topic regardless of automatic duration.' );
		}
	} finally { remove_filter( 'wp_redirect', $stop_redirect, -999 ); $_GET = $original_get; $_REQUEST = $original_request; }
	$pid = $topic( 71.9 ); $resolve( $pid ); $assert( 'publish' === get_post_status( $pid ), 'No resolve before custom deadline.' );
	$pid = $topic( 72 ); $resolve( $pid ); $assert( 'yoohw_resolved' === get_post_status( $pid ), 'Resolve at custom deadline.' );
	foreach ( [ 'owner', 'other' ] as $who ) {
		$pid = $topic( 90, $who ); $resolve( $pid ); $assert( 'publish' === get_post_status( $pid ), 'Non-support latest reply never triggers resolve.' );
	}
	$pid = $topic( 90 ); $reply( $pid, 'owner', 1 ); $resolve( $pid ); $assert( 'publish' === get_post_status( $pid ), 'Customer response prevents closure.' );
	foreach ( [ 0, 120 ] as $new_duration ) {
		$pid = $topic( 90 );
		$change_during_query = static function () use ( &$settings, $new_duration ) { $settings['auto_resolve_hours'] = $new_duration; };
		$resolve( $pid ); $assert( 'publish' === get_post_status( $pid ), 'A policy change during eligibility reads stops closure.' );
		$save( 'general', [ 'auto_resolve_hours' => 72 ] );
	}
	foreach ( [ 0, 30, 120 ] as $new_duration ) {
		$pid = $topic( 36 ); $attempts = []; $queries = 0;
		$change_on_second_query = static function ( $sql ) use ( &$queries, &$settings, $new_duration, $wpdb ) {
			if ( false !== strpos( $sql, $wpdb->comments ) && false !== strpos( $sql, 'SELECT' ) && 2 === ++$queries ) { $settings['auto_resolve_hours'] = $new_duration; }
			return $sql;
		};
		add_filter( 'query', $change_on_second_query, 20 );
		try { $warn( $pid ); } finally { remove_filter( 'query', $change_on_second_query, 20 ); }
		$assert( $queries >= 2 && ( $new_duration > 36 ? 1 === count( $attempts ) && false !== strpos( $attempts[0]['message'], 'in about 84 hours' ) : ! $attempts ), 'Final warning check observes changed policy and current deadline.' );
		$save( 'general', [ 'auto_resolve_hours' => 72 ] );
	}
	$pid = $topic( 36 ); $attempts = []; $warn( $pid );
	$assert( 1 === count( $attempts ) && false !== strpos( $attempts[0]['message'], 'in about 36 hours' ), 'Warning timing and copy use current 72-hour deadline.' );
	$save( 'general', [ 'auto_resolve_hours' => 0 ] ); $warn( $pid );
	$save( 'general', [ 'auto_resolve_hours' => 96 ] ); $warn( $pid );
	$assert( 1 === count( $attempts ), 'Disabling/re-enabling or extending policy never replays sent warning.' );
	$save( 'general', [ 'auto_resolve_hours' => 0 ] );
	$save( 'emails', [ 'email_close_hours' => 100 ] );
	$assert( 100 === $settings['email_close_hours'], 'Warning preference editable while auto-resolve disabled.' );
	$before = $settings; $save( 'general', [ 'auto_resolve_hours' => 72 ] );
	$assert( $before == $settings, 'Re-enable rejects retained incompatible warning.' );
	$save( 'emails', [ 'email_close_hours' => 0 ] ); $save( 'general', [ 'auto_resolve_hours' => 1 ] );
	$assert( 1 === $settings['auto_resolve_hours'] && ! YoOhw_Support_Email_Settings::enabled( 'close' ), 'One-hour resolve works with warning off.' );

	// Real bounded worker scans >100 topics and revisits those skipped while disabled.
	$batch = []; for ( $i = 0; $i < 102; ++$i ) { $batch[] = $topic( 2 ); }
	$save( 'general', [ 'auto_resolve_hours' => 0 ] ); $cursor = 0;
	YoOhw_Post_Status_Resolved::auto_resolve_inactive_posts();
	$assert( 'publish' === get_post_status( end( $batch ) ), 'Disabled cron leaves all topics open.' );
	$save( 'general', [ 'auto_resolve_hours' => 1 ] );
	for ( $i = 0; $i < 4; ++$i ) { YoOhw_Post_Status_Resolved::auto_resolve_inactive_posts(); }
	foreach ( $batch as $pid ) { $assert( 'yoohw_resolved' === get_post_status( $pid ), 'Cursor sweep does not omit old open topics after policy re-enable.' ); }

	wp_set_current_user( $users['support'] ); $_GET = [ 'tab' => 'general' ];
	YoOhw_Support_Settings::register_settings();
	ob_start(); YoOhw_Support_Settings::render_options_page(); $html = ob_get_clean();
	$assert( false !== strpos( $html, 'Topic lifecycle' ) && false !== strpos( $html, 'Changes apply to existing open topics' ), 'General renders policy and existing-topic impact.' );
	$settings['dedicated_portal_mode'] = 'isolated';
	ob_start(); YoOhw_Support_Settings::render_auto_resolve_field(); $html = ob_get_clean();
	$assert( false !== strpos( $html, 'disabled' ) && false !== strpos( $html, 'retained in isolated storage' ), 'Isolated lifecycle control disabled with explanation.' );
	$clean = YoOhw_Support_Settings::sanitize( [ '_tab' => 'general', 'dedicated_portal_mode' => 'isolated' ] );
	$assert( 1 === $clean['auto_resolve_hours'], 'Omitted isolated control retains preference.' );
	wp_set_current_user( $users['owner'] ); ob_start(); YoOhw_Support_Settings::render_options_page(); $html = ob_get_clean();
	$assert( '' === $html, 'Customer cannot render management controls.' );
	$assert( count( get_settings_errors( 'yoohw_support_portal' ) ) > 0, 'Invalid configuration errors visible.' );
	WP_CLI::log( "auto-resolve-ok: $checks checks; synthetic records, intercepted mail, emulated settings/cursor writes" );
} finally {
	$change_during_query = null;
	foreach ( $posts as $pid ) { wp_delete_post( $pid, true ); }
	wp_set_current_user( $original_user ); foreach ( $users as $uid ) { wp_delete_user( $uid ); }
	$_GET = $original_get; $_REQUEST = $original_request; $GLOBALS['wp_settings_errors'] = $original_errors;
	remove_filter( 'pre_option_' . YoOhw_Support_Settings::OPTION_NAME, $settings_read );
	remove_filter( 'pre_option_yoohw_support_cursor_auto_resolve', $cursor_read ); remove_filter( 'pre_update_option_yoohw_support_cursor_auto_resolve', $cursor_write, 999 );
	remove_filter( 'pre_wp_mail', $mail, PHP_INT_MAX ); remove_filter( 'query', $query );
}
