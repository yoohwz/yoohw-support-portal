<?php
/** Local WP-CLI only. Synthetic records, intercepted mail, in-memory settings/cron writes. */
if ( ! defined( 'WP_CLI' ) || ! WP_CLI || 'local' !== wp_get_environment_type() ) {
	throw new RuntimeException( 'Email settings tests require Local WP-CLI.' );
}
require_once ABSPATH . 'wp-admin/includes/user.php';
require_once ABSPATH . 'wp-admin/includes/template.php';
global $wpdb;
$prefix = 'ysp23-' . strtolower( wp_generate_password( 8, false, false ) );
$settings = YoOhw_Support_Settings::get();
$settings = array_merge( $settings, YoOhw_Support_Email_Settings::defaults() );
$cron = [ 'version' => 2 ];
$users = $posts = $comments = $attempts = [];
$original_user = get_current_user_id();
$original_post = $_POST; $original_get = $_GET; $original_request = $_REQUEST;
$original_method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$original_uri = $_SERVER['REQUEST_URI'] ?? '/';
$original_errors = $GLOBALS['wp_settings_errors'] ?? [];
$checks = 0; $fail = false; $during_mail = null;
$settings_filter = static function () use ( &$settings ) { return $settings; };
$cron_read = static function () use ( &$cron ) { return $cron; };
$cron_write = static function ( $value, $old ) use ( &$cron ) { $cron = $value; return $old; };
$mail_filter = static function ( $result, $mail ) use ( &$attempts, &$fail, &$during_mail ) { $attempts[] = $mail; if ( $during_mail ) { $callback = $during_mail; $during_mail = null; $callback(); } return ! $fail; };
add_filter( 'pre_option_' . YoOhw_Support_Settings::OPTION_NAME, $settings_filter );
add_filter( 'pre_option_cron', $cron_read );
add_filter( 'pre_update_option_cron', $cron_write, 999, 2 );
add_filter( 'pre_wp_mail', $mail_filter, PHP_INT_MAX, 2 );
$assert = static function ( $ok, $message ) use ( &$checks ) { if ( ! $ok ) { throw new RuntimeException( $message ); } ++$checks; };
$set = static function ( array $patch ) use ( &$settings ) {
	$old = $settings;
	$value = YoOhw_Support_Settings::sanitize( array_merge( $settings, $patch, [ '_tab' => 'emails' ] ) );
	$settings = apply_filters( 'pre_update_option_' . YoOhw_Support_Settings::OPTION_NAME, $value, $old );
	YoOhw_Support_Email_Settings::cancel_disabled_retries( $old, $settings );
};
$topic = static function () use ( &$posts, &$users, $prefix ) {
	remove_action( 'transition_post_status', [ 'YoOhw_Email_Notification', 'maybe_send_admin_notification' ], 10 );
	try {
		$id = wp_insert_post( [ 'post_type' => 'post', 'post_status' => 'publish', 'post_author' => $users['owner'], 'post_title' => $prefix . '-' . count( $posts ), 'post_content' => 'PRIVATE-SYNTHETIC-' . $prefix, 'comment_status' => 'open' ], true );
		if ( is_wp_error( $id ) ) { throw new RuntimeException( $id->get_error_message() ); }
		$posts[] = $id; return $id;
	} finally { add_action( 'transition_post_status', [ 'YoOhw_Email_Notification', 'maybe_send_admin_notification' ], 10, 3 ); }
};
$reply = static function ( $pid, $who = 'support', $age = 0 ) use ( &$comments, &$users, $prefix ) {
	$date = gmdate( 'Y-m-d H:i:s', time() - $age );
	$id = wp_insert_comment( [ 'comment_post_ID' => $pid, 'user_id' => $users[$who], 'comment_author' => $who, 'comment_author_email' => $who . '-' . $prefix . '@example.invalid', 'comment_content' => 'PRIVATE-REPLY-' . $prefix, 'comment_approved' => 1, 'comment_date_gmt' => $date, 'comment_date' => get_date_from_gmt( $date ) ] );
	if ( ! $id ) { throw new RuntimeException( 'Cannot create synthetic comment.' ); }
	$comments[] = $id; return $id;
};
$invoke = static function ( $class, $method, ...$args ) { $r = new ReflectionMethod( $class, $method ); $r->setAccessible( true ); return $r->invokeArgs( null, $args ); };
$deliver = static function ( $type, $id ) {
	if ( 'admin' === $type ) { YoOhw_Email_Notification::send_admin_notification( $id, get_post( $id ) ); }
	elseif ( 'author' === $type ) { YoOhw_Email_Notification::send_author_notification( $id, 1 ); }
	else { YoOhw_Email_Notification::send_reply_notification( $id, 1 ); }
};
$die_filter = static function () { return static function ( $message, $title = '', $args = [] ) { throw new RuntimeException( 'denied', (int) ( $args['response'] ?? 403 ) ); }; };
$redirect_filter = static function ( $location ) { throw new RuntimeException( $location, 302 ); };
add_filter( 'wp_die_handler', $die_filter, 999 );
add_filter( 'wp_redirect', $redirect_filter, -999 );
try {
	foreach ( [ 'owner' => 'subscriber', 'participant' => 'subscriber', 'other' => 'subscriber', 'support' => 'administrator', 'manager' => 'subscriber' ] as $who => $role ) {
		$id = wp_insert_user( [ 'user_login' => $prefix . '-' . $who, 'user_pass' => wp_generate_password(), 'user_email' => $who . '-' . $prefix . '@example.invalid', 'role' => $role ] );
		$assert( ! is_wp_error( $id ), 'Create synthetic user.' ); $users[$who] = $id;
	}
	get_user_by( 'id', $users['manager'] )->add_cap( YoOhw_Support_Capabilities::MANAGE_SETTINGS );

	// Tab sanitization is intentionally isolated: no unsubmitted checkbox resets.
	$settings['docs_url'] = 'https://example.invalid/guide'; $settings['topics_per_page'] = 17; $settings['ui_primary_color'] = '#123456';
	$set( [ 'email_from_name' => 'Example Support', 'email_from_address' => 'sender@example.invalid', 'email_admin_recipient' => 'support@example.invalid', 'docs_url' => 'https://evil.invalid/', 'dedicated_portal_mode' => 'isolated' ] );
	$assert( 'https://example.invalid/guide' === $settings['docs_url'] && 17 === $settings['topics_per_page'] && 'wordpress' === $settings['dedicated_portal_mode'], 'Saving Emails preserves General, including storage mode.' );
	$clean = YoOhw_Support_Settings::sanitize( array_merge( $settings, [ '_tab' => 'general', 'topics_per_page' => 21, 'email_from_name' => 'Ignored' ] ) );
	$assert( 21 === $clean['topics_per_page'] && 'Example Support' === $clean['email_from_name'] && 'support@example.invalid' === $clean['email_admin_recipient'], 'Saving General preserves Emails.' );
	$old = $settings;
	$set( [ 'email_from_address' => "bad@example.invalid\r\nBcc: other@example.invalid", 'email_admin_recipient' => 'a@example.invalid,b@example.invalid', 'email_from_name' => [] ] );
	$assert( $old['email_from_address'] === $settings['email_from_address'] && $old['email_admin_recipient'] === $settings['email_admin_recipient'] && $old['email_from_name'] === $settings['email_from_name'], 'Reject malformed, multi-recipient and header injection values while retaining saved values.' );
	$assert( count( get_settings_errors( 'yoohw_support_portal' ) ) >= 3, 'Validation errors are visible.' );
	$set( [ 'email_resume_after' => [ 'close' => -1 ], 'email_from_name' => "<b>Example</b>\r\nSupport" ] );
	$assert( [] === $settings['email_resume_after'] && false === strpos( $settings['email_from_name'], "\n" ) && false === strpos( $settings['email_from_name'], '<b>' ), 'Internal cutoffs protected; sender name is safe header text.' );

	// Each immediate notification: cancel only its pending retry, suppress old events, allow new ones.
	foreach ( [ 'admin', 'author', 'reply' ] as $type ) {
		$pid = $topic(); $reply( $pid, 'participant', 60 ); $cid = $reply( $pid ); $id = 'admin' === $type ? $pid : $cid;
		$attempts = []; $fail = true; $deliver( $type, $id );
		$assert( 1 === count( $attempts ) && wp_next_scheduled( YoOhw_Support_Email_Settings::RETRY_HOOK, [ $type, $id ] ), "$type: failed send queues retry." );
		$other_type = 'admin' === $type ? 'author' : 'admin';
		wp_schedule_single_event( time() + 900, YoOhw_Support_Email_Settings::RETRY_HOOK, [ $other_type, $id ] );
		$set( [ 'email_notify_' . $type => '0' ] );
		$assert( ! wp_next_scheduled( YoOhw_Support_Email_Settings::RETRY_HOOK, [ $type, $id ] ), "$type: disabling cancels queued retry." );
		$assert( wp_next_scheduled( YoOhw_Support_Email_Settings::RETRY_HOOK, [ $other_type, $id ] ), "$type: other notification queues retained." );
		wp_clear_scheduled_hook( YoOhw_Support_Email_Settings::RETRY_HOOK, [ $other_type, $id ] );
		$blocked_pid = $topic(); $reply( $blocked_pid, 'participant', 60 ); $blocked_cid = $reply( $blocked_pid ); $blocked = 'admin' === $type ? $blocked_pid : $blocked_cid;
		$fail = false; $attempts = []; $deliver( $type, $blocked );
		$assert( ! $attempts, "$type: events while disabled do not send." );
		$set( [ 'email_notify_' . $type => '1' ] );
		YoOhw_Email_Notification::retry_notification( $type, $id ); $deliver( $type, $blocked );
		$assert( ! $attempts, "$type: re-enable cannot backfill canceled retries or disabled events." );
		$fresh_pid = $topic(); $reply( $fresh_pid, 'participant', 60 ); $fresh_cid = $reply( $fresh_pid );
		$deliver( $type, 'admin' === $type ? $fresh_pid : $fresh_cid );
		$assert( 1 === count( $attempts ), "$type: new events send normally after re-enable." );
	}

	// A settings change during transport must not recreate a retry or continue a participant fanout.
	$pid = $topic(); $cid = $reply( $pid ); $fail = true; $attempts = [];
	$during_mail = static function () use ( $set ) { $set( [ 'email_notify_author' => '0' ] ); };
	YoOhw_Email_Notification::send_author_notification( $cid, 1 );
	$assert( ! wp_next_scheduled( YoOhw_Support_Email_Settings::RETRY_HOOK, [ 'author', $cid ] ), 'Disabling during a failed send cannot resurrect a pending retry.' );
	$set( [ 'email_notify_author' => '1' ] ); $fail = false; $attempts = [];
	YoOhw_Email_Notification::retry_notification( 'author', $cid );
	$assert( ! $attempts, 'Canceled in-flight failure cannot backfill after re-enable.' );
	$pid = $topic(); $reply( $pid, 'participant', 120 ); $reply( $pid, 'other', 60 ); $cid = $reply( $pid ); $attempts = [];
	$during_mail = static function () use ( $set ) { $set( [ 'email_notify_reply' => '0' ] ); };
	YoOhw_Email_Notification::send_reply_notification( $cid, 1 );
	$assert( 1 === count( $attempts ), 'Disabling during participant fanout stops before another recipient.' );
	$set( [ 'email_notify_reply' => '1' ] ); YoOhw_Email_Notification::retry_notification( 'reply', $cid );
	$assert( 1 === count( $attempts ), 'Re-enabling does not resume an explicitly canceled fanout.' );

	// Real get_option cache path: emulate a different request changing the backing row.
	// Only SELECT is intercepted; no site settings are written and cron stays in memory.
	$race_pid = $topic(); $race_cid = $reply( $race_pid );
	$backing = array_merge( $settings, [ 'email_notify_author' => '1' ] );
	$uncache_autoload = static function ( $all ) { unset( $all[YoOhw_Support_Settings::OPTION_NAME] ); return $all; };
	$backing_query = static function ( $sql ) use ( $wpdb, &$backing ) {
		if ( 0 === strpos( $sql, "SELECT option_value FROM {$wpdb->options} WHERE option_name =" ) && false !== strpos( $sql, YoOhw_Support_Settings::OPTION_NAME ) ) {
			return $wpdb->prepare( 'SELECT %s AS option_value', maybe_serialize( $backing ) );
		}
		return $sql;
	};
	remove_filter( 'pre_option_' . YoOhw_Support_Settings::OPTION_NAME, $settings_filter );
	add_filter( 'alloptions', $uncache_autoload ); add_filter( 'query', $backing_query, 20 );
	try {
		wp_cache_set( YoOhw_Support_Settings::OPTION_NAME, $backing, 'options' );
		wp_cache_delete( 'notoptions', 'options' );
		$assert( YoOhw_Support_Email_Settings::enabled( 'author' ), 'Race fixture begins enabled through real get_option.' );
		$fail = true; $attempts = [];
		$during_mail = static function () use ( &$backing ) {
			$old = $backing; $backing['email_notify_author'] = '0';
			YoOhw_Support_Email_Settings::cancel_disabled_retries( $old, $backing );
		};
		YoOhw_Email_Notification::send_author_notification( $race_cid, 1 );
		$assert( 1 === count( $attempts ) && ! wp_next_scheduled( YoOhw_Support_Email_Settings::RETRY_HOOK, [ 'author', $race_cid ] ), 'Fresh backing setting prevents retry resurrection after cross-request disable.' );
		$assert( get_comment_meta( $race_cid, '_yoohw_email_suppressed_author_v1', true ), 'Cross-request disable permanently suppresses the old failed event.' );
		$backing['email_notify_author'] = '1'; $fail = false;
		YoOhw_Email_Notification::retry_notification( 'author', $race_cid );
		$assert( 1 === count( $attempts ), 'Re-enable cannot backfill event canceled by another request.' );
		$fan_pid = $topic(); $reply( $fan_pid, 'participant', 120 ); $reply( $fan_pid, 'other', 60 ); $fan_cid = $reply( $fan_pid );
		$backing['email_notify_reply'] = '1'; $attempts = [];
		$during_mail = static function () use ( &$backing ) {
			$old = $backing; $backing['email_notify_reply'] = '0'; YoOhw_Support_Email_Settings::cancel_disabled_retries( $old, $backing );
		};
		YoOhw_Email_Notification::send_reply_notification( $fan_cid, 1 );
		$assert( 1 === count( $attempts ), 'Fresh backing settings stop fanout after another request disables it.' );
		$backing['email_notify_reply'] = '1'; YoOhw_Email_Notification::retry_notification( 'reply', $fan_cid );
		$assert( 1 === count( $attempts ), 'Cross-request canceled fanout cannot backfill.' );
		$cached = $backing; $cached['email_resume_after'] = []; wp_cache_set( YoOhw_Support_Settings::OPTION_NAME, $cached, 'options' );
		$backing['email_resume_after']['close'] = $fan_cid;
		$assert( ! YoOhw_Support_Email_Settings::reminder_allowed( 'close', $fan_cid ), 'Reminder eligibility refreshes resume cutoff before reading it.' );
	} finally {
		remove_filter( 'alloptions', $uncache_autoload ); remove_filter( 'query', $backing_query, 20 );
		wp_cache_delete( YoOhw_Support_Settings::OPTION_NAME, 'options' ); wp_cache_delete( 'alloptions', 'options' ); wp_cache_delete( 'notoptions', 'options' );
		add_filter( 'pre_option_' . YoOhw_Support_Settings::OPTION_NAME, $settings_filter );
		$during_mail = null; $fail = false;
	}

	// A disable request must also reload cron cached before another request queued a retry.
	$queue_pid = $topic(); $queue_cid = $reply( $queue_pid ); $saved_cron = $cron;
	$queue_time = time() + 900; wp_schedule_single_event( $queue_time, YoOhw_Support_Email_Settings::RETRY_HOOK, [ 'author', $queue_cid ] );
	$backing_cron = $cron;
	$uncache_cron = static function ( $all ) { unset( $all['cron'] ); return $all; };
	$cron_query = static function ( $sql ) use ( $wpdb, $backing_cron ) {
		if ( 0 === strpos( $sql, "SELECT option_value FROM {$wpdb->options} WHERE option_name = 'cron'" ) ) { return $wpdb->prepare( 'SELECT %s AS option_value', maybe_serialize( $backing_cron ) ); }
		return $sql;
	};
	remove_filter( 'pre_option_cron', $cron_read ); add_filter( 'alloptions', $uncache_cron ); add_filter( 'query', $cron_query, 20 );
	wp_cache_set( 'cron', [ 'version' => 2 ], 'options' ); wp_cache_delete( 'notoptions', 'options' );
	try {
		YoOhw_Support_Email_Settings::cancel_disabled_retries( $settings, array_merge( $settings, [ 'email_notify_author' => '0' ] ) );
		$assert( get_comment_meta( $queue_cid, '_yoohw_email_suppressed_author_v1', true ) && empty( $cron[$queue_time][YoOhw_Support_Email_Settings::RETRY_HOOK] ), 'Disabling reloads a stale cron cache and cancels newly queued retry.' );
	} finally {
		remove_filter( 'alloptions', $uncache_cron ); remove_filter( 'query', $cron_query, 20 ); add_filter( 'pre_option_cron', $cron_read ); $cron = $saved_cron;
		wp_cache_delete( 'cron', 'options' ); wp_cache_delete( 'alloptions', 'options' ); wp_cache_delete( 'notoptions', 'options' );
	}

	// First save uses add_option, not update_option: pending default-on mail must also be canceled.
	$first_pid = $topic(); $first_cid = $reply( $first_pid ); $fail = true;
	YoOhw_Email_Notification::send_author_notification( $first_cid, 1 );
	$settings['email_notify_author'] = '0';
	do_action( 'add_option_' . YoOhw_Support_Settings::OPTION_NAME, YoOhw_Support_Settings::OPTION_NAME, $settings );
	$assert( ! wp_next_scheduled( YoOhw_Support_Email_Settings::RETRY_HOOK, [ 'author', $first_cid ] ), 'First-save disable cancels retry too.' );
	$set( [ 'email_notify_author' => '1' ] ); $fail = false;

	// A draft created earlier but only published after re-enable is a genuinely new event.
	$draft = $topic(); wp_update_post( [ 'ID' => $draft, 'post_status' => 'draft' ] );
	$set( [ 'email_notify_admin' => '0' ] ); $set( [ 'email_notify_admin' => '1' ] ); $attempts = [];
	wp_update_post( [ 'ID' => $draft, 'post_status' => 'publish' ] );
	$assert( 1 === count( $attempts ) && 'support@example.invalid' === $attempts[0]['to'], 'New publication uses configured single admin recipient, even for an older draft.' );
	$assert( false !== strpos( implode( "\n", (array) $attempts[0]['headers'] ), 'sender@example.invalid' ), 'Support notifications use configured From header.' );
	$set( [ 'email_admin_recipient' => '' ] );
	$assert( get_option( 'admin_email' ) === YoOhw_Support_Email_Settings::admin_recipient(), 'Blank admin recipient follows current site admin.' );
	$set( [ 'email_admin_recipient' => 'support@example.invalid' ] );

	// Recurring reminders skip the disabled interval; no change to 12/24/48-hour semantics.
	foreach ( [ 'followup' => [ 'owner', 13, 'maybe_send_admin_followup_notice' ], 'close' => [ 'support', 25, 'maybe_send_topic_close_notice' ] ] as $type => $config ) {
		[$who, $hours, $method] = $config;
		$pid = $topic(); $reply( $pid, $who, $hours * HOUR_IN_SECONDS );
		$set( [ 'email_notify_' . $type => '0' ] ); $attempts = [];
		$invoke( 'YoOhw_Email_Notification', $method, $pid );
		$assert( ! $attempts, "$type: disabled reminder does not send." );
		$set( [ 'email_notify_' . $type => '1' ] );
		$invoke( 'YoOhw_Email_Notification', $method, $pid );
		$assert( ! $attempts, "$type: re-enable does not backfill old eligible reply." );
		$reply( $pid, $who, ( $hours - 1 ) * HOUR_IN_SECONDS );
		$invoke( 'YoOhw_Email_Notification', $method, $pid );
		$assert( 1 === count( $attempts ), "$type: later reply remains eligible at unchanged threshold." );
	}
	$set( [ 'email_notify_admin' => '0', 'email_notify_author' => '0', 'email_notify_reply' => '0', 'email_notify_followup' => '0', 'email_notify_close' => '0' ] );
	$pid = $topic(); $reply( $pid, 'support', 49 * HOUR_IN_SECONDS ); $attempts = [];
	$invoke( 'YoOhw_Post_Status_Resolved', 'maybe_auto_resolve_post_after_admin_reply', $pid );
	$assert( 'yoohw_resolved' === get_post_status( $pid ) && 'closed' === get_post( $pid )->comment_status && ! $attempts, 'All email off does not stop 48-hour auto-resolve or send closure mail.' );

	// Account email remains independent; global wrapping is a separate preference.
	$set( [ 'email_global_template' => '0' ] ); $attempts = [];
	wp_mail( 'owner-' . $prefix . '@example.invalid', 'Registration test', 'Synthetic account text' );
	$assert( 1 === count( $attempts ) && 'Synthetic account text' === $attempts[0]['message'], 'Topic switches do not block account mail; disabled global template leaves message unchanged.' );
	wp_set_current_user( 0 ); $_POST = [ 'yoohw_support_lost_password_nonce' => wp_create_nonce( 'yoohw_support_lost_password' ), 'user_login' => $prefix . '-owner' ];
	$invoke( 'YoOhw_Support_Controller', 'handle_lost_password_submission' );
	$assert( 2 === count( $attempts ) && false !== strpos( $attempts[1]['subject'], 'Password Reset' ), 'Actual password reset flow still sends with all topic switches off.' );
	$set( [ 'email_global_template' => '1' ] ); $attempts = [];
	wp_mail( 'owner-' . $prefix . '@example.invalid', 'Account notification', 'Synthetic account text' );
	$assert( false !== strpos( $attempts[0]['message'], YoOhw_Support_Email_Template::EMAIL_TEMPLATE_MARKER ), 'Enabled global template wraps WordPress mail.' );
	$sample = YoOhw_Support_Email_Template::sample_html();
	$assert( false === strpos( $sample, 'PRIVATE-' ) && false !== strpos( $sample, 'No support conversation data' ), 'Preview content is fixed sample without fixture conversation data.' );

	// Endpoint guards: anonymous/customer denied even with valid nonce; manager authorized by capability.
	foreach ( [ 0, $users['owner'] ] as $viewer ) {
		wp_set_current_user( $viewer ); $_SERVER['REQUEST_METHOD'] = 'POST'; $_REQUEST = [ '_wpnonce' => wp_create_nonce( 'yoohw_support_email_test' ) ];
		$_POST = [ 'recipient' => 'test@example.invalid' ]; $attempts = [];
		try { YoOhw_Support_Email_Settings::handle_test(); throw new RuntimeException( 'Expected denial.' ); } catch ( RuntimeException $error ) { $assert( 403 === $error->getCode() && ! $attempts, 'Unauthorized test send denied.' ); }
		$_SERVER['REQUEST_METHOD'] = 'GET'; $_REQUEST = [ '_wpnonce' => wp_create_nonce( 'yoohw_support_email_preview' ) ];
		try { YoOhw_Support_Email_Settings::handle_preview(); throw new RuntimeException( 'Expected preview denial.' ); } catch ( RuntimeException $error ) { $assert( 403 === $error->getCode(), 'Unauthorized preview denied.' ); }
	}
	wp_set_current_user( $users['manager'] );
	foreach ( [ [ 'GET', 'bad', 403 ], [ 'POST', wp_create_nonce( 'yoohw_support_email_preview' ), 405 ] ] as $case ) {
		[$method, $nonce, $code] = $case; $_SERVER['REQUEST_METHOD'] = $method; $_REQUEST = [ '_wpnonce' => $nonce ];
		try { YoOhw_Support_Email_Settings::handle_preview(); throw new RuntimeException( 'Expected preview denial.' ); } catch ( RuntimeException $error ) { $assert( $code === $error->getCode(), 'Preview validates method and nonce for authorized manager too.' ); }
	}
	foreach ( [ [ 'GET', 'valid', 'test@example.invalid', 405 ], [ 'POST', 'invalid', 'test@example.invalid', 403 ], [ 'POST', 'valid', "a@example.invalid,b@example.invalid", 302 ] ] as $case ) {
		[$method, $nonce, $recipient, $code] = $case;
		$_SERVER['REQUEST_METHOD'] = $method; $_REQUEST = [ '_wpnonce' => 'valid' === $nonce ? wp_create_nonce( 'yoohw_support_email_test' ) : 'bad' ]; $_POST = [ 'recipient' => $recipient ]; $attempts = [];
		try { YoOhw_Support_Email_Settings::handle_test(); throw new RuntimeException( 'Expected guarded response.' ); } catch ( RuntimeException $error ) { $assert( $code === $error->getCode() && ! $attempts, 'Method, nonce and recipient validated before mail.' ); }
	}
	foreach ( [ false => 'accepted', true => 'failed' ] as $failure => $status ) {
		$fail = (bool) $failure; $_SERVER['REQUEST_METHOD'] = 'POST'; $_REQUEST = [ '_wpnonce' => wp_create_nonce( 'yoohw_support_email_test' ) ]; $_POST = [ 'recipient' => 'test@example.invalid', 'message' => 'UNTRUSTED-PRIVATE' ]; $attempts = [];
		try { YoOhw_Support_Email_Settings::handle_test(); throw new RuntimeException( 'Expected redirect.' ); } catch ( RuntimeException $error ) {
			$assert( 302 === $error->getCode() && false !== strpos( $error->getMessage(), 'email_test=' . $status ) && 1 === count( $attempts ), 'Authorized test reports transport result.' );
		}
		$assert( false === strpos( $attempts[0]['message'], 'UNTRUSTED-PRIVATE' ) && false !== strpos( $attempts[0]['message'], 'No support conversation data' ), 'Test endpoint ignores posted content and sends sample only.' );
	}
	$fail = false;
	$assert( YoOhw_Support_Capabilities::MANAGE_SETTINGS === apply_filters( 'option_page_capability_yoohw_support_portal', 'manage_options' ), 'Settings save uses existing manage-settings capability.' );

	// Render both tabs and disabled isolated controls; forms must not nest.
	YoOhw_Support_Settings::register_settings();
	$_SERVER['REQUEST_URI'] = '/wp-admin/options-general.php?page=yoohw-support-portal&tab=emails'; $_GET = [ 'tab' => 'emails' ];
	ob_start(); YoOhw_Support_Settings::render_options_page(); $html = ob_get_clean();
	$doc = new DOMDocument(); libxml_use_internal_errors( true ); $doc->loadHTML( $html ); $xpath = new DOMXPath( $doc );
	$assert( 2 === $xpath->query( '//form' )->length && 0 === $xpath->query( '//form//form' )->length, 'Emails has separate settings/test forms.' );
	$assert( 1 === $xpath->query( '//a[@aria-current="page" and contains(., "Emails")]' )->length && false === strpos( $html, 'attachment_max_count' ), 'Emails tab shows its controls, not General fields.' );
	$settings['dedicated_portal_mode'] = 'isolated';
	ob_start(); YoOhw_Support_Settings::render_options_page(); $isolated_html = ob_get_clean(); $doc->loadHTML( $isolated_html ); $xpath = new DOMXPath( $doc );
	$assert( 1 === $xpath->query( '//fieldset[@disabled]' )->length && false !== strpos( $isolated_html, 'require WordPress posts/comments storage' ), 'Isolated mode disables topic controls with explanation.' );
	$before = $settings;
	$isolated_clean = YoOhw_Support_Settings::sanitize( [ '_tab' => 'emails', 'email_from_name' => 'Changed sender' ] );
	$assert( $before['email_notify_close'] === $isolated_clean['email_notify_close'] && $before['email_admin_recipient'] === $isolated_clean['email_admin_recipient'], 'Omitted disabled controls retain saved preferences.' );
	$settings['dedicated_portal_mode'] = 'wordpress'; $_GET = [ 'tab' => 'general' ];
	ob_start(); YoOhw_Support_Settings::render_options_page(); $general_html = ob_get_clean();
	$assert( false !== strpos( $general_html, 'attachment_max_count' ) && false !== strpos( $general_html, 'Support links' ) && false === strpos( $general_html, 'yoohw-test-recipient' ), 'Existing settings remain in General.' );
	wp_set_current_user( $users['owner'] ); ob_start(); YoOhw_Support_Settings::render_options_page(); $denied_html = ob_get_clean();
	$assert( '' === $denied_html, 'Customer cannot render settings or tool nonces.' );
	WP_CLI::log( "email-settings-ok: $checks checks; synthetic records, intercepted mail, settings/cron writes emulated" );
} finally {
	$_POST = $original_post; $_GET = $original_get; $_REQUEST = $original_request; $_SERVER['REQUEST_METHOD'] = $original_method; $_SERVER['REQUEST_URI'] = $original_uri;
	$fail = false;
	foreach ( $posts as $pid ) { wp_delete_post( $pid, true ); }
	wp_set_current_user( $original_user );
	foreach ( $users as $uid ) { wp_delete_user( $uid ); }
	remove_filter( 'wp_die_handler', $die_filter, 999 ); remove_filter( 'wp_redirect', $redirect_filter, -999 );
	remove_filter( 'pre_option_' . YoOhw_Support_Settings::OPTION_NAME, $settings_filter ); remove_filter( 'pre_option_cron', $cron_read ); remove_filter( 'pre_update_option_cron', $cron_write, 999 ); remove_filter( 'pre_wp_mail', $mail_filter, PHP_INT_MAX );
	$GLOBALS['wp_settings_errors'] = $original_errors;
}
