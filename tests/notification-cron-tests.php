<?php
/** Run with wp eval-file on Local. Synthetic records only; all email is intercepted. */
if ( ! defined( 'WP_CLI' ) || ! WP_CLI || 'local' !== wp_get_environment_type() ) {
	throw new RuntimeException( 'Notification tests require WP-CLI and WP_ENVIRONMENT_TYPE=local.' );
}
if ( ! class_exists( 'YoOhw_Email_Notification' ) ) {
	throw new RuntimeException( 'Run the notification suite in native Dedicated Portal mode.' );
}
require_once ABSPATH . 'wp-admin/includes/user.php';
global $wpdb;
$prefix = 'ysp21-' . strtolower( wp_generate_password( 8, false, false ) );
$users = $posts = $comments = $saved_options = [];
$checks = 0;
$attempts = [];
$behavior = null;
$admin_email = 'admin-' . $prefix . '@example.invalid';
$admin_filter = static function () use ( &$admin_email ) { return $admin_email; };
add_filter( 'pre_option_admin_email', $admin_filter );
$mail_filter = static function ( $result, $mail ) use ( &$attempts, &$behavior ) {
	$attempts[] = $mail;
	return $behavior ? $behavior( $mail ) : true;
};
add_filter( 'pre_wp_mail', $mail_filter, PHP_INT_MAX, 2 );
$assert = static function ( $condition, $message ) use ( &$checks ) {
	if ( ! $condition ) {
		throw new RuntimeException( $message );
	}
	++$checks;
};
$topic = static function () use ( &$posts, &$users, $prefix ) {
	remove_action( 'transition_post_status', [ 'YoOhw_Email_Notification', 'maybe_send_admin_notification' ], 10 );
	try {
		$id = wp_insert_post( [ 'post_type' => 'post', 'post_status' => 'publish', 'post_author' => $users['owner'], 'post_title' => $prefix . '-' . count( $posts ), 'post_content' => 'Synthetic private topic.', 'comment_status' => 'open' ], true );
		if ( is_wp_error( $id ) ) { throw new RuntimeException( $id->get_error_message() ); }
		$posts[] = $id;
		return $id;
	} finally {
		add_action( 'transition_post_status', [ 'YoOhw_Email_Notification', 'maybe_send_admin_notification' ], 10, 3 );
	}
};
$reply = static function ( $post_id, $who, $age = 0 ) use ( &$comments, &$users, $prefix ) {
	$date = gmdate( 'Y-m-d H:i:s', time() - $age );
	$id = wp_insert_comment( [ 'comment_post_ID' => $post_id, 'user_id' => $users[$who], 'comment_author' => $who, 'comment_author_email' => $who . '-' . $prefix . '@example.invalid', 'comment_content' => 'Synthetic private reply.', 'comment_date_gmt' => $date, 'comment_date' => get_date_from_gmt( $date ), 'comment_approved' => 1 ] );
	if ( ! $id ) { throw new RuntimeException( 'Cannot create synthetic reply.' ); }
	$comments[] = $id;
	return $id;
};
$retry = static function ( $type, $id ) {
	$args = [ $type, $id ];
	$time = wp_next_scheduled( YoOhw_Email_Notification::CRON_HOOK_RETRY, $args );
	if ( $time ) { wp_unschedule_event( $time, YoOhw_Email_Notification::CRON_HOOK_RETRY, $args ); }
	do_action_ref_array( YoOhw_Email_Notification::CRON_HOOK_RETRY, $args );
};
$call = static function ( $class, $method, $id ) {
	$reflection = new ReflectionMethod( $class, $method );
	$reflection->setAccessible( true );
	$reflection->invoke( null, $id );
};
// Restrict actual cron scans to this suite's synthetic topics, never site data.
$query_filter = static function ( $sql ) use ( &$posts, $wpdb ) {
	if ( 0 === strpos( $sql, "SELECT ID FROM {$wpdb->posts} WHERE post_type = 'post' AND post_status = 'publish' AND ID >" ) ) {
		$sql = str_replace( ' ORDER BY ID ASC', ' AND ID IN (' . implode( ',', array_map( 'intval', $posts ?: [ 0 ] ) ) . ') ORDER BY ID ASC', $sql );
	}
	return $sql;
};
add_filter( 'query', $query_filter );
foreach ( [ 'auto_resolve', 'close_notice', 'admin_followup' ] as $job ) {
	$name = 'yoohw_support_cursor_' . $job;
	$saved_options[$name] = get_option( $name, null );
	update_option( $name, 0, false );
}
try {
	foreach ( [ 'owner', 'support', 'a', 'b', 'late' ] as $who ) {
		$id = wp_insert_user( [ 'user_login' => $prefix . '-' . $who, 'user_email' => $who . '-' . $prefix . '@example.invalid', 'user_pass' => wp_generate_password(), 'role' => 'support' === $who ? 'administrator' : 'subscriber' ] );
		$assert( ! is_wp_error( $id ), 'Create synthetic user.' );
		$users[$who] = $id;
	}

	// Failed immediate mail gets one bounded pending retry; hook replay cannot bypass it.
	$id = $topic();
	$c = $reply( $id, 'support' );
	$attempts = []; $behavior = static function () { return false; };
	YoOhw_Email_Notification::send_author_notification( $c, 1 );
	$assert( ! get_comment_meta( $c, YoOhw_Email_Notification::COMMENT_META_SENT_AUTHOR, true ), 'Failure must not mark author mail sent.' );
	$scheduled = wp_next_scheduled( YoOhw_Email_Notification::CRON_HOOK_RETRY, [ 'author', $c ] );
	$assert( $scheduled >= time() + 14 * MINUTE_IN_SECONDS && $scheduled <= time() + 15 * MINUTE_IN_SECONDS, 'Retry scheduled after 15 minutes.' );
	YoOhw_Email_Notification::send_author_notification( $c, 1 );
	$assert( 1 === count( $attempts ), 'Hook replay respects pending retry.' );
	$behavior = null; $retry( 'author', $c );
	$assert( 2 === count( $attempts ) && get_comment_meta( $c, YoOhw_Email_Notification::COMMENT_META_SENT_AUTHOR, true ), 'Successful retry marks author mail once.' );
	YoOhw_Email_Notification::send_author_notification( $c, 1 );
	$assert( 2 === count( $attempts ) && ! wp_next_scheduled( YoOhw_Email_Notification::CRON_HOOK_RETRY, [ 'author', $c ] ), 'Success cancels retries and replay.' );

	// Reentrant approval/hook/retry calls while inside wp_mail must share the same lock.
	$c2 = $reply( $id, 'support' ); $attempts = [];
	$behavior = static function () use ( $c2 ) {
		YoOhw_Email_Notification::send_author_notification( $c2, 1 );
		YoOhw_Email_Notification::retry_notification( 'author', $c2 );
		YoOhw_Email_Notification::maybe_send_notifications_on_comment_approval( 'approved', 'unapproved', get_comment( $c2 ) );
		return true;
	};
	YoOhw_Email_Notification::send_author_notification( $c2, 1 );
	$assert( 1 === count( $attempts ), 'Reentrant author paths produce one mail; author/support participant exclusions hold.' );
	$behavior = null;

	// A competing request can finish after this request has primed an empty meta cache.
	$cached_reply = $reply( $id, 'support' );
	get_comment_meta( $cached_reply, YoOhw_Email_Notification::COMMENT_META_SENT_AUTHOR, true );
	$wpdb->insert( $wpdb->commentmeta, [ 'comment_id' => $cached_reply, 'meta_key' => YoOhw_Email_Notification::COMMENT_META_SENT_AUTHOR, 'meta_value' => '1' ] );
	$attempts = [];
	YoOhw_Email_Notification::send_author_notification( $cached_reply, 1 );
	$assert( ! $attempts, 'Lock holder reloads sent marker rather than trusting pre-lock metadata cache.' );

	// Mixed recipient results: retain successes; exclude duplicates, owner and current author.
	$id2 = $topic(); $reply( $id2, 'owner', 300 ); $reply( $id2, 'a', 240 ); $reply( $id2, 'a', 180 );
	$b = $reply( $id2, 'b', 120 ); $new = $reply( $id2, 'support' );
	$attempts = []; $b_email = 'b-' . $prefix . '@example.invalid';
	$behavior = static function ( $mail ) use ( $b_email ) { return $b_email !== $mail['to']; };
	YoOhw_Email_Notification::send_reply_notification( $new, 1 );
	$assert( 2 === count( $attempts ) && ! get_comment_meta( $new, YoOhw_Email_Notification::COMMENT_META_SENT_REPLY, true ), 'Only distinct other participants attempted; partial send incomplete.' );
	$reply( $id2, 'late' ); // Must never expand the original notification audience on retry.
	for ( $i = 0; $i < 98; ++$i ) { $reply( $id2, 'support' ); }
	$behavior = null; $retry( 'reply', $new );
	$assert( 3 === count( $attempts ) && $b_email === $attempts[2]['to'], 'Retry sends the failed original recipient even beyond the latest 100 comments.' );
	$assert( get_comment_meta( $new, YoOhw_Email_Notification::COMMENT_META_SENT_REPLY, true ), 'All remaining targets complete.' );
	YoOhw_Email_Notification::send_reply_notification( $new, 1 );
	$assert( 3 === count( $attempts ), 'Successful partial retry is idempotent.' );
	$assert( false === strpos( serialize( get_comment_meta( $new, YoOhw_Email_Notification::COMMENT_META_REPLY_RECIPIENTS, true ) ), '@' ), 'Delivery ledger stores hashes, not extra email addresses.' );

	// A participant removed from the approved set must not receive a queued retry.
	$id3 = $topic(); $old = $reply( $id3, 'b', 60 ); $new3 = $reply( $id3, 'support' );
	$behavior = static function () { return false; }; $attempts = [];
	YoOhw_Email_Notification::send_reply_notification( $new3, 1 );
	wp_set_comment_status( $old, 'hold' ); $behavior = null; $retry( 'reply', $new3 );
	$assert( 1 === count( $attempts ), 'Retry excludes a no-longer-approved participant.' );

	// Invalid original participant address can be repaired without expanding the audience.
	$bad_topic = $topic(); $bad_participant = $reply( $bad_topic, 'b', 60 );
	wp_update_comment( [ 'comment_ID' => $bad_participant, 'comment_author_email' => 'invalid' ] );
	$bad_new = $reply( $bad_topic, 'support' ); $attempts = [];
	YoOhw_Email_Notification::send_reply_notification( $bad_new, 1 );
	$assert( ! $attempts && ! get_comment_meta( $bad_new, YoOhw_Email_Notification::COMMENT_META_SENT_REPLY, true ), 'Invalid participant is not marked delivered.' );
	wp_update_comment( [ 'comment_ID' => $bad_participant, 'comment_author_email' => $b_email ] );
	$retry( 'reply', $bad_new );
	$assert( 1 === count( $attempts ) && $b_email === $attempts[0]['to'], 'Corrected original participant address receives retry.' );

	// Stale immediate retries recheck actual approval and current topic status.
	$c3 = $reply( $id3, 'support' ); $behavior = static function () { return false; }; $attempts = [];
	YoOhw_Email_Notification::send_author_notification( $c3, 1 );
	wp_set_comment_status( $c3, 'hold' ); $behavior = null; $retry( 'author', $c3 );
	$assert( 1 === count( $attempts ), 'Unapproved comment retry sends nothing.' );
	$c4 = $reply( $id3, 'support' ); $behavior = static function () { return false; }; $attempts = [];
	YoOhw_Email_Notification::send_author_notification( $c4, 1 );
	wp_update_post( [ 'ID' => $id3, 'post_status' => 'draft' ] ); $behavior = null; $retry( 'author', $c4 );
	$assert( 1 === count( $attempts ), 'Non-published topic retry sends nothing.' );

	// Retry limit: initial attempt plus three retries, even if replayed afterward.
	$id4 = $topic(); $c5 = $reply( $id4, 'support' ); $attempts = [];
	$behavior = static function () { return false; };
	YoOhw_Email_Notification::send_author_notification( $c5, 1 );
	for ( $i = 0; $i < 5; ++$i ) { $retry( 'author', $c5 ); }
	$assert( 4 === count( $attempts ) && ! wp_next_scheduled( YoOhw_Email_Notification::CRON_HOOK_RETRY, [ 'author', $c5 ] ), 'Three retries maximum; no sent marker on exhaustion.' );
	$assert( ! get_comment_meta( $c5, YoOhw_Email_Notification::COMMENT_META_SENT_AUTHOR, true ), 'Exhausted mail remains unsent.' );
	$behavior = null;

	// Admin new-topic mail also retries and deduplicates; reopen must not start a notification.
	$id5 = $topic(); $attempts = []; $behavior = static function () { return false; };
	YoOhw_Email_Notification::maybe_send_admin_notification( 'publish', 'draft', get_post( $id5 ) );
	$assert( ! get_post_meta( $id5, YoOhw_Email_Notification::POST_META_SENT_ADMIN, true ), 'Failed New Topic mail not marked sent.' );
	$behavior = null; $retry( 'admin', $id5 );
	YoOhw_Email_Notification::maybe_send_admin_notification( 'publish', 'draft', get_post( $id5 ) );
	$assert( 2 === count( $attempts ) && get_post_meta( $id5, YoOhw_Email_Notification::POST_META_SENT_ADMIN, true ), 'New Topic success deduplicates replays.' );
	$reopened = $topic(); $attempts = [];
	wp_update_post( [ 'ID' => $reopened, 'post_status' => 'yoohw_resolved' ] );
	$assert( 'closed' === get_post( $reopened )->comment_status, 'Resolved closes discussion.' );
	wp_update_post( [ 'ID' => $reopened, 'post_status' => 'publish' ] );
	$assert( ! $attempts && 'open' === get_post( $reopened )->comment_status, 'Reopen restores discussion without New Topic mail, even for legacy topic with no admin marker.' );

	// Bad addresses remain unmarked and can recover once fixed.
	$invalid_admin = $topic(); $admin_email = 'invalid'; $attempts = [];
	YoOhw_Email_Notification::send_admin_notification( $invalid_admin, get_post( $invalid_admin ) );
	$assert( ! $attempts && ! get_post_meta( $invalid_admin, YoOhw_Email_Notification::POST_META_SENT_ADMIN, true ), 'Invalid admin email is not a successful delivery.' );
	$admin_email = 'admin-' . $prefix . '@example.invalid'; $retry( 'admin', $invalid_admin );
	$assert( 1 === count( $attempts ), 'Corrected admin address can receive retry.' );

	// Thresholds and failed recurring reminders; auto-resolve remains independent.
	$notice = $topic(); $support_reply = $reply( $notice, 'support', 23 * HOUR_IN_SECONDS ); $attempts = [];
	$call( 'YoOhw_Email_Notification', 'maybe_send_topic_close_notice', $notice );
	$assert( ! $attempts, 'No close notice before 24 hours.' );
	$date = gmdate( 'Y-m-d H:i:s', time() - 25 * HOUR_IN_SECONDS );
	wp_update_comment( [ 'comment_ID' => $support_reply, 'comment_date_gmt' => $date, 'comment_date' => get_date_from_gmt( $date ) ] );
	$behavior = static function () { return false; };
	$call( 'YoOhw_Email_Notification', 'maybe_send_topic_close_notice', $notice );
	$assert( ! get_post_meta( $notice, YoOhw_Email_Notification::POST_META_CLOSE_NOTICE_SENT, true ), 'Failed close notice remains eligible.' );
	$behavior = null; $call( 'YoOhw_Email_Notification', 'maybe_send_topic_close_notice', $notice );
	$call( 'YoOhw_Email_Notification', 'maybe_send_topic_close_notice', $notice );
	$assert( 2 === count( $attempts ) && $support_reply === (int) get_post_meta( $notice, YoOhw_Email_Notification::POST_META_CLOSE_NOTICE_REPLY, true ), 'Close notice success is bound to support reply and deduplicated.' );
	$new_support_reply = $reply( $notice, 'support', 24 * HOUR_IN_SECONDS + 60 );
	$call( 'YoOhw_Email_Notification', 'maybe_send_topic_close_notice', $notice );
	$assert( 3 === count( $attempts ) && $new_support_reply === (int) get_post_meta( $notice, YoOhw_Email_Notification::POST_META_CLOSE_NOTICE_REPLY, true ), 'New support reply starts a distinct reminder cycle.' );
	// Historical owner-hook replay must not erase a later support cycle marker.
	$owner_old = $reply( $notice, 'owner', 40 * HOUR_IN_SECONDS );
	YoOhw_Email_Notification::send_author_notification( $owner_old, 1 );
	$call( 'YoOhw_Email_Notification', 'maybe_send_topic_close_notice', $notice );
	$assert( 3 === count( $attempts ), 'Old owner reply replay cannot erase current reminder marker.' );

	$stale = $topic(); $reply( $stale, 'support', 30 * HOUR_IN_SECONDS );
	$attempts = []; $behavior = static function () { return false; };
	$call( 'YoOhw_Email_Notification', 'maybe_send_topic_close_notice', $stale );
	$reply( $stale, 'owner' ); $behavior = null;
	$call( 'YoOhw_Email_Notification', 'maybe_send_topic_close_notice', $stale );
	$call( 'YoOhw_Post_Status_Resolved', 'maybe_auto_resolve_post_after_admin_reply', $stale );
	$assert( 1 === count( $attempts ) && 'publish' === get_post_status( $stale ), 'Owner response cancels stale reminder and auto-resolve.' );

	$legacy = $topic(); $reply( $legacy, 'support', 30 * HOUR_IN_SECONDS );
	update_post_meta( $legacy, YoOhw_Email_Notification::POST_META_CLOSE_NOTICE_SENT, current_time( 'mysql' ) ); $attempts = [];
	$call( 'YoOhw_Email_Notification', 'maybe_send_topic_close_notice', $legacy );
	$assert( ! $attempts, 'Legacy timestamp prevents duplicate notice for same cycle.' );
	update_post_meta( $legacy, YoOhw_Email_Notification::POST_META_CLOSE_NOTICE_SENT, get_date_from_gmt( gmdate( 'Y-m-d H:i:s', time() - 72 * HOUR_IN_SECONDS ) ) );
	$call( 'YoOhw_Email_Notification', 'maybe_send_topic_close_notice', $legacy );
	$assert( 1 === count( $attempts ), 'Legacy marker predating latest support reply allows new cycle.' );
	$c_legacy = $reply( $legacy, 'support' );
	update_comment_meta( $c_legacy, YoOhw_Email_Notification::COMMENT_META_SENT_AUTHOR, 1 );
	update_comment_meta( $c_legacy, YoOhw_Email_Notification::COMMENT_META_SENT_REPLY, 1 ); $attempts = [];
	YoOhw_Email_Notification::send_author_notification( $c_legacy, 1 ); YoOhw_Email_Notification::send_reply_notification( $c_legacy, 1 );
	$assert( ! $attempts, 'Legacy immediate sent markers remain authoritative.' );

	$follow = $topic(); $customer_reply = $reply( $follow, 'owner', 11 * HOUR_IN_SECONDS ); $attempts = [];
	$call( 'YoOhw_Email_Notification', 'maybe_send_admin_followup_notice', $follow );
	$assert( ! $attempts, 'No followup before 12 hours.' );
	$date = gmdate( 'Y-m-d H:i:s', time() - 13 * HOUR_IN_SECONDS );
	wp_update_comment( [ 'comment_ID' => $customer_reply, 'comment_date_gmt' => $date, 'comment_date' => get_date_from_gmt( $date ) ] );
	$behavior = static function () { return false; };
	$call( 'YoOhw_Email_Notification', 'maybe_send_admin_followup_notice', $follow );
	$assert( ! get_comment_meta( $customer_reply, YoOhw_Email_Notification::COMMENT_META_ADMIN_FOLLOWUP_SENT, true ), 'Failed followup unmarked.' );
	$behavior = null;
	$call( 'YoOhw_Email_Notification', 'maybe_send_admin_followup_notice', $follow );
	$call( 'YoOhw_Email_Notification', 'maybe_send_admin_followup_notice', $follow );
	$assert( 2 === count( $attempts ) && get_comment_meta( $customer_reply, YoOhw_Email_Notification::COMMENT_META_ADMIN_FOLLOWUP_SENT, true ), 'Followup retries and deduplicates success.' );

	$auto = $topic(); $last = $reply( $auto, 'support', 47 * HOUR_IN_SECONDS );
	$call( 'YoOhw_Post_Status_Resolved', 'maybe_auto_resolve_post_after_admin_reply', $auto );
	$assert( 'publish' === get_post_status( $auto ), 'No resolve before 48 hours.' );
	$date = gmdate( 'Y-m-d H:i:s', time() - 49 * HOUR_IN_SECONDS );
	wp_update_comment( [ 'comment_ID' => $last, 'comment_date_gmt' => $date, 'comment_date' => get_date_from_gmt( $date ) ] ); $attempts = [];
	$call( 'YoOhw_Email_Notification', 'maybe_send_topic_close_notice', $auto );
	$assert( ! $attempts, 'No close notice at or beyond 48 hours.' );
	$call( 'YoOhw_Post_Status_Resolved', 'maybe_auto_resolve_post_after_admin_reply', $auto );
	$assert( 'yoohw_resolved' === get_post_status( $auto ) && 'closed' === get_post( $auto )->comment_status && ! get_post_meta( $auto, YoOhw_Email_Notification::POST_META_CLOSE_NOTICE_SENT, true ), 'Resolve after 48 hours without requiring a successful reminder.' );

	// Real checker entrypoints across >50/>100 topics, with ineligible topics before them.
	$bulk = [];
	for ( $i = 0; $i < 105; ++$i ) { $pid = $topic(); $bulk[$pid] = $reply( $pid, 'support', 30 * HOUR_IN_SECONDS ); }
	$attempts = [];
	for ( $i = 0; $i < 5; ++$i ) { YoOhw_Email_Notification::send_topic_close_notice_checker(); }
	foreach ( $bulk as $pid => $cid ) {
		$assert( $cid === (int) get_post_meta( $pid, YoOhw_Email_Notification::POST_META_CLOSE_NOTICE_REPLY, true ), 'Close notice scan reaches every topic beyond first 50.' );
	}
	$before = count( $attempts );
	for ( $i = 0; $i < 5; ++$i ) { YoOhw_Email_Notification::send_topic_close_notice_checker(); }
	$assert( $before === count( $attempts ), 'Next full cursor cycle does not repeat delivered notices.' );
	foreach ( $bulk as $pid => $cid ) {
		$date = gmdate( 'Y-m-d H:i:s', time() - 13 * HOUR_IN_SECONDS );
		wp_update_comment( [ 'comment_ID' => $cid, 'user_id' => $users['owner'], 'comment_date_gmt' => $date, 'comment_date' => get_date_from_gmt( $date ) ] );
	}
	for ( $i = 0; $i < 4; ++$i ) { YoOhw_Email_Notification::send_admin_followup_notice_checker(); }
	foreach ( $bulk as $cid ) { $assert( get_comment_meta( $cid, YoOhw_Email_Notification::COMMENT_META_ADMIN_FOLLOWUP_SENT, true ), 'Followup reaches every topic beyond first 100.' ); }
	foreach ( $bulk as $cid ) {
		$date = gmdate( 'Y-m-d H:i:s', time() - 49 * HOUR_IN_SECONDS );
		wp_update_comment( [ 'comment_ID' => $cid, 'user_id' => $users['support'], 'comment_date_gmt' => $date, 'comment_date' => get_date_from_gmt( $date ) ] );
	}
	for ( $i = 0; $i < 4; ++$i ) { YoOhw_Post_Status_Resolved::auto_resolve_inactive_posts(); }
	foreach ( $bulk as $pid => $cid ) { $assert( 'yoohw_resolved' === get_post_status( $pid ), 'Resolve scan survives status changes and reaches beyond first 100.' ); }

	// A sweep has a fixed upper ID: inserts cannot keep it open indefinitely.
	$sweep_key = 'test_' . $prefix;
	$visited = []; $inserted = [];
	YoOhw_Support_Background::run_topic_batch( $sweep_key, 1, static function ( $pid ) use ( &$visited, &$inserted, $topic ) {
		$visited[] = $pid;
		$inserted[] = $topic(); $inserted[] = $topic();
	} );
	for ( $i = 0; $i < count( $posts ) + 1 && get_option( 'yoohw_support_cursor_' . $sweep_key, 0 ); ++$i ) {
		YoOhw_Support_Background::run_topic_batch( $sweep_key, 10, static function ( $pid ) use ( &$visited ) { $visited[] = $pid; } );
	}
	$assert( ! get_option( 'yoohw_support_cursor_' . $sweep_key, 0 ) && ! array_intersect( $visited, $inserted ), 'Sweep wraps despite later inserts; new IDs wait for next sweep.' );
	$visited = [];
	YoOhw_Support_Background::run_topic_batch( $sweep_key, 500, static function ( $pid ) use ( &$visited ) { $visited[] = $pid; } );
	$assert( 2 === count( array_intersect( $visited, $inserted ) ), 'Next sweep includes topics inserted during the previous sweep.' );
	delete_option( 'yoohw_support_cursor_' . $sweep_key );

	// Deterministically insert a concurrent winner immediately before acquisition hits SQL.
	$contender_key = 'test_contender_' . $prefix;
	$contender_token = ( time() + 15 * MINUTE_IN_SECONDS ) . ':other-owner';
	$inject = static function ( $sql ) use ( $wpdb, $contender_key, $contender_token ) {
		if ( 0 === strpos( $sql, "INSERT IGNORE INTO {$wpdb->options}" ) && false !== strpos( $sql, 'yoohw_support_lock_' . $contender_key ) ) {
			$wpdb->insert( $wpdb->options, [ 'option_name' => 'yoohw_support_lock_' . $contender_key, 'option_value' => $contender_token, 'autoload' => 'no' ] );
		}
		return $sql;
	};
	add_filter( 'query', $inject, 20 );
	try {
		$assert( '' === YoOhw_Support_Background::acquire_lock( $contender_key ), 'Acquisition cannot overwrite a winner inserted between check and insert.' );
	} finally {
		remove_filter( 'query', $inject, 20 );
		YoOhw_Support_Background::release_lock( $contender_key, $contender_token );
	}

	// Lock ownership, expiration recovery, and no cursor advancement by a competing worker.
	$key = 'test_' . $prefix; $first = YoOhw_Support_Background::acquire_lock( $key );
	$assert( $first && '' === YoOhw_Support_Background::acquire_lock( $key ), 'Only one lock owner.' );
	YoOhw_Support_Background::release_lock( $key, 'wrong-owner' );
	$assert( '' === YoOhw_Support_Background::acquire_lock( $key ), 'Wrong owner cannot unlock.' );
	YoOhw_Support_Background::release_lock( $key, $first );
	add_option( 'yoohw_support_lock_' . $key, ( time() - 1 ) . ':expired', '', false );
	$replacement = YoOhw_Support_Background::acquire_lock( $key );
	YoOhw_Support_Background::release_lock( $key, ( time() - 1 ) . ':expired' );
	$assert( $replacement && '' === YoOhw_Support_Background::acquire_lock( $key ), 'Expired lock recovered; stale owner cannot unlock replacement.' );
	YoOhw_Support_Background::release_lock( $key, $replacement );
	$lock = YoOhw_Support_Background::acquire_lock( 'close_notice' );
	$cursor = get_option( 'yoohw_support_cursor_close_notice', 0 ); $attempts = [];
	YoOhw_Email_Notification::send_topic_close_notice_checker();
	$assert( ! $attempts && $cursor === get_option( 'yoohw_support_cursor_close_notice', 0 ), 'Competing checker cannot send or advance cursor.' );
	YoOhw_Support_Background::release_lock( 'close_notice', $lock );

	WP_CLI::log( "notification-cron-ok: $checks checks; intercepted mail, synthetic data only" );
} finally {
	$behavior = null;
	delete_option( 'yoohw_support_cursor_test_' . $prefix );
	delete_option( 'yoohw_support_lock_test_' . $prefix );
	foreach ( $comments as $cid ) {
		foreach ( [ 'author', 'reply' ] as $type ) { wp_clear_scheduled_hook( YoOhw_Email_Notification::CRON_HOOK_RETRY, [ $type, $cid ] ); }
	}
	foreach ( $posts as $pid ) {
		wp_clear_scheduled_hook( YoOhw_Email_Notification::CRON_HOOK_RETRY, [ 'admin', $pid ] );
		wp_delete_post( $pid, true );
	}
	foreach ( $users as $uid ) { wp_delete_user( $uid ); }
	foreach ( $saved_options as $name => $value ) {
		if ( null === $value ) { delete_option( $name ); } else { update_option( $name, $value, false ); }
	}
	remove_filter( 'query', $query_filter );
	remove_filter( 'pre_wp_mail', $mail_filter, PHP_INT_MAX );
	remove_filter( 'pre_option_admin_email', $admin_filter );
}
