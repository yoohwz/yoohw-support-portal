<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class YoOhw_Email_Notification extends YoOhw_Support_Email_Template {

	const COMMENT_META_SENT_AUTHOR = '_yoohw_email_sent_author_v1';
	const COMMENT_META_SENT_REPLY  = '_yoohw_email_sent_reply_v1';
	const COMMENT_META_REPLY_RECIPIENTS = '_yoohw_email_reply_recipients_v1';
	const COMMENT_META_REPLY_TARGETS = '_yoohw_email_reply_targets_v1';
	const POST_META_SENT_ADMIN = '_yoohw_email_sent_admin_v1';
	const POST_META_CLOSE_NOTICE_REPLY = '_yoohw_close_notice_reply_v1';
	const CRON_HOOK_RETRY = 'yoohw_retry_topic_notification';

	const POST_META_CLOSE_NOTICE_SENT = '_yoohw_close_notice_sent_v1';
	const CRON_HOOK_CLOSE_NOTICE      = 'yoohw_send_topic_close_notice';

	const COMMENT_META_ADMIN_FOLLOWUP_SENT = '_yoohw_admin_followup_sent_v1';
	const CRON_HOOK_ADMIN_FOLLOWUP         = 'yoohw_send_admin_followup_notice';

	public static function init() {
		add_action( self::CRON_HOOK_RETRY, [ __CLASS__, 'retry_notification' ], 10, 2 );
		add_action( 'transition_post_status', [ __CLASS__, 'maybe_send_admin_notification' ], 10, 3 );

		add_action( 'comment_post', [ __CLASS__, 'send_author_notification' ], 10, 2 );
		add_action( 'comment_post', [ __CLASS__, 'send_reply_notification' ], 10, 2 );

		add_action( 'transition_comment_status', [ __CLASS__, 'maybe_send_notifications_on_comment_approval' ], 10, 3 );

		add_filter( 'notify_post_author', [ __CLASS__, 'disable_default_author_notification' ], 10, 2 );
		add_filter( 'wp_mail', [ __CLASS__, 'apply_global_email_template' ], 20 );

		add_filter( 'cron_schedules', [ __CLASS__, 'add_cron_schedules' ] );
		add_action( 'init', [ __CLASS__, 'maybe_schedule_close_notice_checker' ] );
		add_action( self::CRON_HOOK_CLOSE_NOTICE, [ __CLASS__, 'send_topic_close_notice_checker' ] );

		add_action( self::CRON_HOOK_ADMIN_FOLLOWUP, [ __CLASS__, 'send_admin_followup_notice_checker' ] );
	}

	public static function maybe_send_admin_notification( $new_status, $old_status, $post ) {
		if ( ! $post instanceof WP_Post ) {
			return;
		}

		if ( 'post' !== $post->post_type ) {
			return;
		}

		if ( 'publish' !== $new_status || in_array( $old_status, [ 'publish', 'yoohw_resolved' ], true ) ) {
			return;
		}

		if ( wp_is_post_autosave( $post->ID ) || wp_is_post_revision( $post->ID ) ) {
			return;
		}

		self::send_admin_notification( $post->ID, $post );
	}

	public static function send_admin_notification( $post_id, $post ) {
		self::send_notification( 'admin', (int) $post_id );
	}

	public static function send_author_notification( $comment_id, $comment_approved ) {
		if ( 1 === (int) $comment_approved ) {
			self::send_notification( 'author', (int) $comment_id );
		}
	}

	public static function send_reply_notification( $comment_id, $comment_approved ) {
		if ( 1 === (int) $comment_approved ) {
			self::send_notification( 'reply', (int) $comment_id );
		}
	}

	public static function retry_notification( $type, $id ) {
		if ( in_array( $type, [ 'admin', 'author', 'reply' ], true ) ) {
			self::send_notification( $type, (int) $id, true );
		}
	}

	private static function send_notification( string $type, int $id, bool $retry = false ): void {
		$key  = 'email_' . $type . '_' . $id;
		$lock = YoOhw_Support_Background::acquire_lock( $key );
		if ( ! $lock ) {
			return;
		}
		try {
			$args = [ $type, $id ];
			$meta_type = 'admin' === $type ? 'post' : 'comment';
			wp_cache_delete( $id, $meta_type . '_meta' );
			if ( ! YoOhw_Support_Email_Settings::enabled( $type ) ) {
				YoOhw_Support_Email_Settings::suppress( $type, $id );
				return;
			}
			if ( get_metadata( $meta_type, $id, '_yoohw_email_suppressed_' . $type . '_v1', true ) ) {
				wp_clear_scheduled_hook( self::CRON_HOOK_RETRY, $args );
				return;
			}
			if ( ! $retry && wp_next_scheduled( self::CRON_HOOK_RETRY, $args ) ) {
				return;
			}
			$meta_key  = '_yoohw_email_failures_' . $type . '_v1';
			$failures  = (int) get_metadata( $meta_type, $id, $meta_key, true );
			if ( $failures >= 4 ) {
				return;
			}
			$method = 'deliver_' . $type . '_notification';
			$complete = self::$method( $id );
			if ( ! YoOhw_Support_Email_Settings::enabled( $type ) ) {
				YoOhw_Support_Email_Settings::suppress( $type, $id );
				return;
			}
			if ( $complete ) {
				delete_metadata( $meta_type, $id, $meta_key );
				wp_clear_scheduled_hook( self::CRON_HOOK_RETRY, $args );
				return;
			}
			++$failures;
			update_metadata( $meta_type, $id, $meta_key, $failures );
			if ( $failures < 4 ) {
				if ( ! wp_next_scheduled( self::CRON_HOOK_RETRY, $args ) ) {
					wp_schedule_single_event( time() + 15 * MINUTE_IN_SECONDS, self::CRON_HOOK_RETRY, $args );
				}
			} else {
				// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- No private content or recipient addresses are logged.
				error_log( sprintf( 'Support Portal: %s notification for ID %d failed after 3 retries.', $type, $id ) );
			}
		} finally {
			YoOhw_Support_Background::release_lock( $key, $lock );
		}
	}

	private static function deliver_admin_notification( int $post_id ): bool {
		$post = get_post( $post_id );
		if ( ! self::is_valid_target_post( $post ) || get_post_meta( $post_id, self::POST_META_SENT_ADMIN, true ) ) {
			return true;
		}
		$admin_email = YoOhw_Support_Email_Settings::admin_recipient();

		if ( ! is_email( $admin_email ) ) {
			return false;
		}

		$subject = '[New Topic] ' . wp_strip_all_tags( $post->post_title );
		$preview = self::get_content_preview( $post->post_content );
		$author  = get_the_author_meta( 'display_name', $post->post_author );

		$content = '
			<p>A new post has been published on your website.</p>
			<p><strong>Title:</strong> ' . esc_html( $post->post_title ) . '</p>
			<p><strong>Content (preview):</strong><br>' . $preview . '</p>
			<p><strong>Author:</strong> ' . esc_html( $author ) . '</p>
			<p><strong>View topic:</strong> <a href="' . esc_url( get_permalink( $post_id ) ) . '">Click here to view and reply the post</a></p>
		';

		$sent = self::send_email(
			$admin_email,
			$subject,
			self::build_email_html( 'New Topic Published', $content )
		);
		if ( $sent ) {
			update_post_meta( $post_id, self::POST_META_SENT_ADMIN, 1 );
		}
		return (bool) $sent;
	}

	private static function deliver_author_notification( int $comment_id ): bool {
		if ( get_comment_meta( $comment_id, self::COMMENT_META_SENT_AUTHOR, true ) ) {
			return true;
		}

		$comment = get_comment( $comment_id );

		if ( ! $comment || 'approved' !== wp_get_comment_status( $comment ) ) {
			return true;
		}

		$post = get_post( $comment->comment_post_ID );

		if ( ! self::is_valid_target_post( $post ) ) {
			return true;
		}

		$author_email         = self::normalize_email( get_the_author_meta( 'user_email', $post->post_author ) );
		$comment_author_email = self::normalize_email( $comment->comment_author_email );

		if ( ! is_email( $author_email ) ) {
			return false;
		}

		if ( $author_email === $comment_author_email ) {
			return true;
		}

		$subject = '[New Reply] ' . wp_strip_all_tags( $post->post_title );
		$preview = self::get_content_preview( $comment->comment_content );

		$content = '
			<p>A new reply has been posted on your post.</p>
			<p><strong>Topic title:</strong> ' . esc_html( $post->post_title ) . '</p>
			<p><strong>Reply (preview):</strong><br>' . $preview . '</p>
			<p><strong>Reply author:</strong> ' . esc_html( $comment->comment_author ) . '</p>
			<p><strong>View reply:</strong> <a href="' . esc_url( get_comment_link( $comment_id ) ) . '">Click here to view and reply</a></p>
		';

		$sent = self::send_email(
			$author_email,
			$subject,
			self::build_email_html( 'New Reply on Your Topic', $content )
		);

		if ( $sent ) {
			update_comment_meta( $comment_id, self::COMMENT_META_SENT_AUTHOR, 1 );
		}
		return (bool) $sent;
	}

	private static function deliver_reply_notification( int $comment_id ): bool {
		if ( get_comment_meta( $comment_id, self::COMMENT_META_SENT_REPLY, true ) ) {
			return true;
		}

		$comment = get_comment( $comment_id );

		if ( ! $comment || 'approved' !== wp_get_comment_status( $comment ) ) {
			return true;
		}

		$post = get_post( $comment->comment_post_ID );

		if ( ! self::is_valid_target_post( $post ) ) {
			return true;
		}

		$comment_author_email = self::normalize_email( $comment->comment_author_email );
		$post_author_email    = self::normalize_email( get_the_author_meta( 'user_email', $post->post_author ) );

		// Original approved comment IDs bound retries; later participants cannot join an old notification.
		$targets = get_comment_meta( $comment_id, self::COMMENT_META_REPLY_TARGETS, true );
		$args = [
			'post_id' => $post->ID,
			'status'  => 'approve',
			'type'    => 'comment',
			'number'  => 100,
		];
		if ( is_array( $targets ) ) {
			if ( ! $targets ) {
				return true;
			}
			// Keep original recipients in view even after another 100 comments arrive.
			$args['comment__in'] = $targets;
		}
		$comments = get_comments( $args );
		if ( ! is_array( $targets ) ) {
			$targets = [];
			foreach ( $comments as $existing_comment ) {
				$targets[] = (int) $existing_comment->comment_ID;
			}
			update_comment_meta( $comment_id, self::COMMENT_META_REPLY_TARGETS, array_unique( $targets ) );
		}

		$notified_emails = [];
		$sent_recipients = (array) get_comment_meta( $comment_id, self::COMMENT_META_REPLY_RECIPIENTS, true );
		$complete = true;
		$reply_preview   = self::get_content_preview( $comment->comment_content );
		$subject         = '[New Reply] ' . wp_strip_all_tags( $post->post_title );

		foreach ( $comments as $existing_comment ) {
			if ( (int) $existing_comment->comment_ID === $comment_id || ! in_array( (int) $existing_comment->comment_ID, $targets, true ) ) {
				continue;
			}

			$existing_email = self::normalize_email( $existing_comment->comment_author_email );

			if ( ! is_email( $existing_email ) ) {
				$complete = false;
				continue;
			}

			if (
				$existing_email === $comment_author_email ||
				$existing_email === $post_author_email ||
				in_array( $existing_email, $notified_emails, true )
			) {
				continue;
			}

			$notified_emails[] = $existing_email;
			$recipient_key = hash( 'sha256', $existing_email );
			if ( in_array( $recipient_key, $sent_recipients, true ) ) {
				continue;
			}

			$content = '
				<p>A new reply has been posted on a post you replied to.</p>
				<p><strong>Topic title:</strong> ' . esc_html( $post->post_title ) . '</p>
				<p><strong>Reply (preview):</strong><br>' . $reply_preview . '</p>
				<p><strong>Reply author:</strong> ' . esc_html( $comment->comment_author ) . '</p>
				<p><strong>View reply:</strong> <a href="' . esc_url( get_comment_link( $comment_id ) ) . '">Click here to view the reply</a></p>
			';

			if ( ! YoOhw_Support_Email_Settings::enabled( 'reply' ) ) {
				YoOhw_Support_Email_Settings::suppress( 'reply', $comment_id );
				return true;
			}
			$sent = self::send_email(
				$existing_email,
				$subject,
				self::build_email_html( 'New Reply on Topic', $content )
			);

			if ( $sent ) {
				$sent_recipients[] = $recipient_key;
				update_comment_meta( $comment_id, self::COMMENT_META_REPLY_RECIPIENTS, $sent_recipients );
			} else {
				$complete = false;
			}
		}

		if ( $complete && $notified_emails ) {
			update_comment_meta( $comment_id, self::COMMENT_META_SENT_REPLY, 1 );
		}
		return $complete;
	}

	public static function maybe_send_notifications_on_comment_approval( $new_status, $old_status, $comment ) {
		if ( 'approved' !== $new_status || 'approved' === $old_status ) {
			return;
		}

		if ( ! $comment instanceof WP_Comment ) {
			return;
		}

		self::send_author_notification( $comment->comment_ID, 1 );
		self::send_reply_notification( $comment->comment_ID, 1 );
	}

	public static function disable_default_author_notification( $maybe_notify, $comment_id ) {
		$comment = get_comment( $comment_id );

		if ( ! $comment ) {
			return $maybe_notify;
		}

		$post = get_post( $comment->comment_post_ID );

		if ( $post && 'post' === $post->post_type ) {
			return false;
		}

		return $maybe_notify;
	}

	private static function is_valid_target_post( $post ) {
		return (
			$post instanceof WP_Post &&
			'post' === $post->post_type &&
			'publish' === $post->post_status
		);
	}

	private static function normalize_email( $email ) {
		return strtolower( sanitize_email( $email ) );
	}

	private static function get_content_preview( $content, $length = 200 ) {
		$plain = wp_strip_all_tags( $content, true );
		$plain = trim( preg_replace( '/\s+/', ' ', $plain ) );

		if ( function_exists( 'mb_strlen' ) && function_exists( 'mb_substr' ) ) {
			if ( mb_strlen( $plain ) > $length ) {
				$plain = mb_substr( $plain, 0, $length ) . '…';
			}
		} elseif ( strlen( $plain ) > $length ) {
			$plain = substr( $plain, 0, $length ) . '…';
		}

		return nl2br( esc_html( $plain ) );
	}

	public static function add_cron_schedules( $schedules ) {
		if ( ! isset( $schedules['yoohw_hourly'] ) ) {
			$schedules['yoohw_hourly'] = [
				'interval' => HOUR_IN_SECONDS,
				'display'  => __( 'Once Hourly', 'yoohw-support-portal' ),
			];
		}

		return $schedules;
	}

	public static function maybe_schedule_close_notice_checker() {
		if ( ! wp_next_scheduled( self::CRON_HOOK_CLOSE_NOTICE ) ) {
			wp_schedule_event( time() + HOUR_IN_SECONDS, 'yoohw_hourly', self::CRON_HOOK_CLOSE_NOTICE );
		}

		if ( ! wp_next_scheduled( self::CRON_HOOK_ADMIN_FOLLOWUP ) ) {
			wp_schedule_event( time() + HOUR_IN_SECONDS, 'yoohw_hourly', self::CRON_HOOK_ADMIN_FOLLOWUP );
		}
	}

	public static function send_topic_close_notice_checker() {
		YoOhw_Support_Background::run_topic_batch( 'close_notice', 50, Closure::fromCallable( [ __CLASS__, 'maybe_send_topic_close_notice' ] ) );
	}

	private static function maybe_send_topic_close_notice( $post_id ) {
		if ( ! YoOhw_Support_Email_Settings::enabled( 'close' ) ) {
			return;
		}

		$post = get_post( $post_id );

		if ( ! self::is_valid_target_post( $post ) ) {
			return;
		}

		$post_author_id = (int) $post->post_author;

		if ( $post_author_id <= 0 ) {
			return;
		}

		$latest_comments = get_comments(
			[
				'post_id' => $post_id,
				'status'  => 'approve',
				'type'    => 'comment',
				'number'  => 1,
				'orderby' => 'comment_date_gmt',
				'order'   => 'DESC',
			]
		);

		if ( empty( $latest_comments ) ) {
			return;
		}

		$latest_comment = $latest_comments[0];
		if ( ! YoOhw_Support_Email_Settings::reminder_allowed( 'close', (int) $latest_comment->comment_ID ) ) {
			return;
		}

		// Latest reply must be from support.
		if ( ! self::is_support_comment( $latest_comment ) ) {
			return;
		}

		$latest_comment_time = strtotime( $latest_comment->comment_date_gmt . ' GMT' );

		if ( ! $latest_comment_time ) {
			return;
		}

		$sent_at = get_post_meta( $post_id, self::POST_META_CLOSE_NOTICE_SENT, true );
		$sent_reply = (int) get_post_meta( $post_id, self::POST_META_CLOSE_NOTICE_REPLY, true );
		if ( $sent_at ) {
			if ( $sent_reply === (int) $latest_comment->comment_ID ) {
				return;
			}
			// Legacy markers are local MySQL dates; only a later support reply starts a new cycle.
			if ( ! $sent_reply && strtotime( get_gmt_from_date( $sent_at ) . ' GMT' ) >= $latest_comment_time ) {
				return;
			}
		}

		$notice_time = $latest_comment_time + ( YoOhw_Support_Email_Settings::reminder_hours( 'close' ) * HOUR_IN_SECONDS );

		// The warning time is configurable; the auto-resolve deadline stays at 48 hours.
		if ( time() < $notice_time ) {
			return;
		}

		// If already inside/past the auto-resolve window, skip reminder.
		if ( time() >= ( $latest_comment_time + ( 2 * DAY_IN_SECONDS ) ) ) {
			return;
		}

		// Make sure the post author has not replied after the latest admin reply.
		$author_replies_after_admin = get_comments(
			[
				'post_id'    => $post_id,
				'status'     => 'approve',
				'type'       => 'comment',
				'user_id'    => $post_author_id,
				'number'     => 1,
				'date_query' => [
					[
						'after'     => gmdate( 'Y-m-d H:i:s', $latest_comment_time ),
						'inclusive' => false,
						'column'    => 'comment_date_gmt',
					],
				],
			]
		);

		if ( ! empty( $author_replies_after_admin ) ) {
			return;
		}

		$author_email = self::normalize_email( get_the_author_meta( 'user_email', $post_author_id ) );

		if ( ! is_email( $author_email ) ) {
			return;
		}

		$subject = '[Topic Closing Soon] ' . wp_strip_all_tags( $post->post_title );

		$remaining_hours = (int) ceil( ( $latest_comment_time + ( 2 * DAY_IN_SECONDS ) - time() ) / HOUR_IN_SECONDS );
		$content = '
			<p>Your support topic is scheduled to be automatically marked as resolved in about ' . esc_html( $remaining_hours ) . ' hours because our latest reply has not received a response from you.</p>
			<p><strong>Topic title:</strong> ' . esc_html( $post->post_title ) . '</p>
			<p>If you still need help, please reply to the topic before it is closed.</p>
			<p><strong>View topic:</strong> <a href="' . esc_url( get_permalink( $post_id ) ) . '">Click here to view and reply</a></p>
		';

		$sent = self::send_email(
			$author_email,
			$subject,
			self::build_email_html( 'Your topic will close soon', $content )
		);

		if ( $sent ) {
			update_post_meta( $post_id, self::POST_META_CLOSE_NOTICE_REPLY, (int) $latest_comment->comment_ID );
			update_post_meta( $post_id, self::POST_META_CLOSE_NOTICE_SENT, current_time( 'mysql' ) );
		}
	}

	public static function send_admin_followup_notice_checker() {
		YoOhw_Support_Background::run_topic_batch( 'admin_followup', 100, Closure::fromCallable( [ __CLASS__, 'maybe_send_admin_followup_notice' ] ) );
	}

	private static function maybe_send_admin_followup_notice( $post_id ) {
		if ( ! YoOhw_Support_Email_Settings::enabled( 'followup' ) ) {
			return;
		}

		$post = get_post( $post_id );

		if ( ! self::is_valid_target_post( $post ) ) {
			return;
		}

		$latest_comments = get_comments(
			[
				'post_id' => $post_id,
				'status'  => 'approve',
				'type'    => 'comment',
				'number'  => 1,
				'orderby' => 'comment_date_gmt',
				'order'   => 'DESC',
			]
		);

		if ( empty( $latest_comments ) ) {
			return;
		}

		$latest_comment = $latest_comments[0];
		if ( ! YoOhw_Support_Email_Settings::reminder_allowed( 'followup', (int) $latest_comment->comment_ID ) ) {
			return;
		}

		wp_cache_delete( (int) $latest_comment->comment_ID, 'comment_meta' );
		if ( get_comment_meta( $latest_comment->comment_ID, self::COMMENT_META_ADMIN_FOLLOWUP_SENT, true ) ) {
			return;
		}

		// Latest reply must be from a normal user/customer, not support.
		if ( self::is_support_comment( $latest_comment ) ) {
			return;
		}

		$latest_comment_time = strtotime( $latest_comment->comment_date_gmt . ' GMT' );

		if ( ! $latest_comment_time ) {
			return;
		}

		$followup_hours = YoOhw_Support_Email_Settings::reminder_hours( 'followup' );
		if ( time() < ( $latest_comment_time + ( $followup_hours * HOUR_IN_SECONDS ) ) ) {
			return;
		}

		$admin_email = YoOhw_Support_Email_Settings::admin_recipient();

		if ( ! is_email( $admin_email ) ) {
			return;
		}

		$subject = '[Waiting for Support Reply] ' . wp_strip_all_tags( $post->post_title );
		$preview = self::get_content_preview( $latest_comment->comment_content );
		$button_style = self::email_button_style();

		$content = '
			<p>A support topic has been waiting for an administrator reply for at least ' . esc_html( $followup_hours ) . ' hours.</p>
			<p><strong>Topic title:</strong> ' . esc_html( $post->post_title ) . '</p>
			<p><strong>Latest user reply:</strong><br>' . $preview . '</p>
			<p><strong>Reply author:</strong> ' . esc_html( $latest_comment->comment_author ) . '</p>
			<p style="margin:24px 0 0;">
				<a href="' . esc_url( get_comment_link( $latest_comment->comment_ID ) ) . '" style="' . $button_style . '">
					View Topic
				</a>
			</p>
		';

		$sent = self::send_email(
			$admin_email,
			$subject,
			self::build_email_html( 'Topic waiting for support reply', $content )
		);

		if ( $sent ) {
			update_comment_meta( $latest_comment->comment_ID, self::COMMENT_META_ADMIN_FOLLOWUP_SENT, 1 );
		}
	}

	private static function send_email( $to, $subject, $message ) {
		if ( ! is_email( $to ) ) {
			return false;
		}

		return wp_mail( $to, $subject, $message, self::get_headers() );
	}

	private static function is_support_comment( $comment ) {
		if ( ! $comment instanceof WP_Comment ) {
			return false;
		}

		$is_support = false;

		if ( ! empty( $comment->user_id ) && user_can( (int) $comment->user_id, YoOhw_Support_Capabilities::MANAGE_TOPICS ) ) {
			$is_support = true;
		}

		return (bool) apply_filters( 'yoohw_support_is_support_comment', $is_support, $comment );
	}
}

YoOhw_Email_Notification::init();
