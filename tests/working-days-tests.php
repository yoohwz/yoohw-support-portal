<?php
/** Local WP-CLI: deterministic calendar cases and synthetic lifecycle records, intercepted mail. */
if ( ! defined( 'WP_CLI' ) || ! WP_CLI || 'local' !== wp_get_environment_type() ) {
	throw new RuntimeException( 'Working days tests require Local WP-CLI.' );
}
require_once ABSPATH . 'wp-admin/includes/user.php';
global $wpdb;
$settings = YoOhw_Support_Settings::defaults();
$settings['dedicated_portal_mode'] = 'wordpress';
$zone = 'Asia/Ho_Chi_Minh';
$read = static function () use ( &$settings ) { return $settings; };
$timezone = static function () use ( &$zone ) { return $zone; };
$attempts = $posts = $users = [];
$mail = static function ( $result, $args ) use ( &$attempts ) { $attempts[] = $args; return true; };
$checks = 0;
$assert = static function ( $ok, $message ) use ( &$checks ) { if ( ! $ok ) { throw new RuntimeException( $message ); } ++$checks; };
$stamp = static function ( $date ) use ( &$zone ) { return ( new DateTimeImmutable( $date, new DateTimeZone( $zone ) ) )->getTimestamp(); };
$elapsed = static function ( $from, $to, $days ) use ( $stamp ) { return YoOhw_Support_Calendar::elapsed( $stamp( $from ), $stamp( $to ), $days ) / HOUR_IN_SECONDS; };
$invoke = static function ( $method, $pid ) {
	$class = 'maybe_auto_resolve_post_after_admin_reply' === $method ? 'YoOhw_Post_Status_Resolved' : 'YoOhw_Email_Notification';
	$r = new ReflectionMethod( $class, $method ); $r->setAccessible( true ); $r->invoke( null, $pid );
};
$errors = $GLOBALS['wp_settings_errors'] ?? [];
$original_user = get_current_user_id();
add_filter( 'pre_option_' . YoOhw_Support_Settings::OPTION_NAME, $read );
add_filter( 'pre_option_timezone_string', $timezone );
add_filter( 'pre_wp_mail', $mail, PHP_INT_MAX, 2 );
try {
	$all = [ 0, 1, 2, 3, 4, 5, 6 ]; $week = [ 1, 2, 3, 4, 5 ];
	$assert( $all === $settings['working_days'], 'Default selects seven days.' );
	unset( $settings['working_days'] );
	$assert( $all === YoOhw_Support_Settings::working_days(), 'Old options without key use seven days.' );
	$settings['working_days'] = $week;
	foreach ( [ [], [ '' ], [ 'bad' ], [ 7 ], [ -1 ], [ true ], [ null ], [ 1.0 ], [ [] ], '1' ] as $invalid ) {
		$clean = YoOhw_Support_Settings::sanitize( [ '_tab' => 'general', 'working_days' => $invalid ] );
		$assert( $week === $clean['working_days'], 'Invalid/empty calendar retains previous days.' );
	}
	$clean = YoOhw_Support_Settings::sanitize( [ '_tab' => 'general', 'working_days' => [ '', '6', '0', '6' ] ] );
	$assert( [ 0, 6 ] === $clean['working_days'], 'Sunday zero, sentinel and duplicates sanitized correctly.' );
	foreach ( [ 'general', 'emails' ] as $tab ) {
		$clean = YoOhw_Support_Settings::sanitize( [ '_tab' => $tab ] );
		$assert( $week === $clean['working_days'], 'Omitted calendar survives ' . $tab . ' save.' );
	}
	$assert( 24 == $elapsed( '2026-11-13 15:00', '2026-11-16 15:00', $week ), 'Friday to Monday counts 24 hours.' );
	$assert( 48 == $elapsed( '2026-11-13 15:00', '2026-11-17 15:00', $week ), 'Friday to Tuesday counts 48 hours.' );
	$assert( 9 == $elapsed( '2026-11-13 15:00', '2026-11-16 00:00', $week ), 'Partial Friday only through Monday midnight.' );
	$assert( 0 == $elapsed( '2026-11-14 15:00', '2026-11-16 00:00', $week ), 'Origin during off day waits for next working day.' );
	$assert( 1 == $elapsed( '2026-11-14 15:00', '2026-11-16 01:00', $week ), 'Origin on weekend resumes Monday.' );
	$assert( 0 == $elapsed( '2026-11-17', '2026-11-16', $week ), 'Future origin never accumulates time.' );
	$assert( 24 == $elapsed( '2026-11-09', '2026-11-16', [ 1 ] ), 'Single selected weekday counts once per week.' );
	$assert( 72 == $elapsed( '2026-11-13 15:00', '2026-11-16 15:00', $all ), 'Seven days preserve continuous time.' );
	$assert( ! YoOhw_Support_Calendar::is_due( $stamp( '2026-11-13' ), 1, $stamp( '2026-11-15 12:00' ) ), 'Overdue actions suppressed on Sunday.' );
	$assert( YoOhw_Support_Calendar::is_due( $stamp( '2026-11-13' ), 24, $stamp( '2026-11-16' ) ), 'Overdue action resumes exactly Monday midnight.' );
	$assert( ! YoOhw_Support_Calendar::is_due( $stamp( '2026-11-13 15:00' ), 48, $stamp( '2026-11-17 14:59:59' ) ), 'Not due one second before threshold.' );
	$assert( YoOhw_Support_Calendar::is_due( $stamp( '2026-11-13 15:00' ), 48, $stamp( '2026-11-17 15:00' ) ), 'Due at threshold.' );
	$zone = 'America/New_York';
	$assert( 23 == $elapsed( '2026-03-08', '2026-03-09', [ 0 ] ), 'Selected spring DST date has 23 elapsed hours.' );
	$assert( 25 == $elapsed( '2026-11-01', '2026-11-02', [ 0 ] ), 'Selected fall DST date has 25 elapsed hours.' );
	$assert( 0 == $elapsed( '2026-03-08', '2026-03-09', $week ), 'Excluded DST day contributes nothing.' );
	$assert( 120 == $elapsed( '2026-03-06 15:00', '2026-03-13 15:00', $week ), 'Whole-week optimization excludes weekend DST shift.' );
	$assert( 143 == $elapsed( '2026-03-06 15:00', '2026-03-13 15:00', [ 0, 1, 2, 3, 4, 5 ] ), 'Whole-week optimization counts selected DST shift.' );
	$zone = '+05:30';
	$assert( 24 == $elapsed( '2026-11-13 15:00', '2026-11-16 15:00', $week ), 'Fixed offset timezone works without transition table.' );
	$zone = 'Pacific/Apia';
	$assert( 24 == $elapsed( '2011-12-29', '2011-12-31', $week ), 'Skipped local date is not counted.' );
	$zone = 'UTC';
	$assert( 2400000 == $elapsed( '2000-01-03', '3916-07-17', [ 1 ] ), 'Long history is counted in whole weeks.' );

	// Independent hourly reference: these ranges align to hours, including DST transitions.
	foreach ( [ 'UTC', 'Asia/Ho_Chi_Minh', 'America/New_York', 'Australia/Lord_Howe' ] as $zone ) {
		$from = $stamp( '2026-01-01 00:00' ); $to = $stamp( '2027-01-01 00:00' );
		$days = [ 0, 2, 4 ]; $reference = 0;
		// A 30-minute reference also covers Lord Howe's half-hour DST changes.
		for ( $t = $from; $t < $to; $t += 1800 ) {
			if ( in_array( (int) wp_date( 'w', $t ), $days, true ) ) { $reference += min( 1800, $to - $t ); }
		}
		$assert( $reference === YoOhw_Support_Calendar::elapsed( $from, $to, $days ), 'Optimized annual count matches independent reference: ' . $zone );
	}
	$zone = 'UTC';

	// Runtime cron consumers use the real clock; emulate today as a day off, then resume.
	$today = (int) wp_date( 'w' ); $off = array_values( array_diff( $all, [ $today ] ) );
	$prefix = 'ysp39-' . strtolower( wp_generate_password( 8, false, false ) );
	foreach ( [ 'owner' => 'subscriber', 'support' => 'administrator' ] as $who => $role ) {
		$id = wp_insert_user( [ 'user_login' => $prefix . $who, 'user_pass' => wp_generate_password(), 'user_email' => $prefix . $who . '@example.invalid', 'role' => $role ] );
		$assert( ! is_wp_error( $id ), 'Create synthetic user.' ); $users[$who] = $id;
	}
	$topic = static function ( $who, $hours ) use ( &$posts, $users, $prefix ) {
		$id = wp_insert_post( [ 'post_type' => 'post', 'post_status' => 'draft', 'post_author' => $users['owner'], 'post_title' => $prefix, 'comment_status' => 'open' ], true );
		if ( is_wp_error( $id ) ) { throw new RuntimeException( $id->get_error_message() ); }
		$posts[] = $id;
		global $wpdb;
		// Avoid a real new-topic notification while making the synthetic topic eligible.
		$wpdb->update( $wpdb->posts, [ 'post_status' => 'publish' ], [ 'ID' => $id ] ); clean_post_cache( $id );
		$date = gmdate( 'Y-m-d H:i:s', time() - $hours * HOUR_IN_SECONDS );
		$cid = wp_insert_comment( [ 'comment_post_ID' => $id, 'user_id' => $users[$who], 'comment_author' => $who, 'comment_author_email' => $prefix . $who . '@example.invalid', 'comment_content' => 'Synthetic working days reply.', 'comment_approved' => 1, 'comment_date_gmt' => $date, 'comment_date' => get_date_from_gmt( $date ) ] );
		return [ $id, $cid ];
	};
	$settings['working_days'] = $off;
	$settings['email_close_hours'] = 1; $settings['email_followup_hours'] = 1; $settings['auto_resolve_hours'] = 480;
	list( $warn, $warn_reply ) = $topic( 'support', 72 );
	list( $follow, $follow_reply ) = $topic( 'owner', 72 );
	list( $resolve ) = $topic( 'support', 1000 );
	$attempts = [];
	$invoke( 'maybe_send_topic_close_notice', $warn ); $invoke( 'maybe_send_admin_followup_notice', $follow ); $invoke( 'maybe_auto_resolve_post_after_admin_reply', $resolve );
	$assert( ! $attempts && 'publish' === get_post_status( $resolve ), 'Off day blocks all three overdue consumers.' );
	$assert( ! get_post_meta( $warn, YoOhw_Email_Notification::POST_META_CLOSE_NOTICE_SENT, true ) && ! get_comment_meta( $follow_reply, YoOhw_Email_Notification::COMMENT_META_ADMIN_FOLLOWUP_SENT, true ), 'Off-day skips do not consume reminder markers.' );
	$settings['working_days'] = array_values( array_diff( $all, [ ( $today + 1 ) % 7 ] ) );
	$invoke( 'maybe_send_topic_close_notice', $warn ); $invoke( 'maybe_send_admin_followup_notice', $follow );
	$assert( 2 === count( $attempts ), 'Both reminders resume on working day.' );
	$assert( false !== strpos( $attempts[0]['message'], 'counted on working days' ) && false !== strpos( $attempts[1]['message'], 'counted on working days' ), 'Reminder copy states selected-day units.' );
	$settings['auto_resolve_hours'] = 1;
	$invoke( 'maybe_auto_resolve_post_after_admin_reply', $resolve );
	$assert( 'yoohw_resolved' === get_post_status( $resolve ) && 'closed' === get_post( $resolve )->comment_status, 'Auto-resolve resumes and closes comments.' );
	$settings['auto_resolve_hours'] = 480;
	$settings['working_days'] = $all;
	$invoke( 'maybe_send_topic_close_notice', $warn ); $invoke( 'maybe_send_admin_followup_notice', $follow );
	$assert( 2 === count( $attempts ), 'Calendar edits never replay sent reminders.' );

	// Change policy between initial eligibility and final query boundary.
	list( $race ) = $topic( 'support', 72 ); $queries = 0;
	$change = static function ( $sql ) use ( &$settings, &$queries, $off, $wpdb ) {
		if ( false !== strpos( $sql, $wpdb->comments ) && false !== strpos( $sql, 'SELECT' ) && 2 === ++$queries ) { $settings['working_days'] = $off; }
		return $sql;
	};
	add_filter( 'query', $change, 20 );
	try { $invoke( 'maybe_send_topic_close_notice', $race ); } finally { remove_filter( 'query', $change, 20 ); }
	$assert( $queries >= 2 && 2 === count( $attempts ), 'Final closing-warning recheck observes changed calendar.' );
	$settings['working_days'] = $all; $settings['auto_resolve_hours'] = 1; $queries = 0;
	list( $race ) = $topic( 'support', 72 );
	add_filter( 'query', $change, 20 );
	try { $invoke( 'maybe_auto_resolve_post_after_admin_reply', $race ); } finally { remove_filter( 'query', $change, 20 ); }
	$assert( $queries >= 2 && 'publish' === get_post_status( $race ), 'Final auto-resolve recheck observes changed calendar.' );
	$settings['working_days'] = $all;
	list( $race ) = $topic( 'owner', 72 );
	$recipient = static function ( $result ) use ( &$settings, $off ) { $settings['working_days'] = $off; return 'recipient@example.invalid'; };
	add_filter( 'pre_option_admin_email', $recipient );
	try { $invoke( 'maybe_send_admin_followup_notice', $race ); } finally { remove_filter( 'pre_option_admin_email', $recipient ); }
	$assert( 2 === count( $attempts ), 'Final follow-up recheck observes changed calendar.' );

	$settings['working_days'] = $off;
	list( $immediate, $immediate_reply ) = $topic( 'support', 0 );
	YoOhw_Email_Notification::send_author_notification( $immediate_reply, 1 );
	YoOhw_Email_Notification::send_admin_notification( $immediate, get_post( $immediate ) );
	$assert( 4 === count( $attempts ), 'Immediate reply and new-topic notifications still send on off days.' );

	wp_set_current_user( $users['support'] );
	$settings['working_days'] = $all;
	ob_start(); YoOhw_Support_Settings::render_working_days_field(); $html = ob_get_clean();
	$assert( 7 === substr_count( $html, 'type="checkbox"' ) && 7 === substr_count( $html, "checked='checked'" ) && false !== strpos( $html, 'UTC' ), 'UI renders seven checked days and timezone.' );
	$settings['dedicated_portal_mode'] = 'isolated';
	ob_start(); YoOhw_Support_Settings::render_working_days_field(); $html = ob_get_clean();
	$assert( false !== strpos( $html, 'disabled' ) && false !== strpos( $html, 'retained in isolated storage' ), 'Isolated UI retains disabled preference.' );
	WP_CLI::log( "working-days-ok: $checks checks; deterministic timezone cases and synthetic runtime consumers; mail intercepted" );
} finally {
	foreach ( $posts as $pid ) { wp_delete_post( $pid, true ); }
	wp_set_current_user( $original_user ); foreach ( $users as $uid ) { wp_delete_user( $uid ); }
	$GLOBALS['wp_settings_errors'] = $errors;
	remove_filter( 'pre_option_' . YoOhw_Support_Settings::OPTION_NAME, $read );
	remove_filter( 'pre_option_timezone_string', $timezone );
	remove_filter( 'pre_wp_mail', $mail, PHP_INT_MAX );
}
