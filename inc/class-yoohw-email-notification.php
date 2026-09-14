<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class YoOhw_Email_Notification {

	const COMMENT_META_SENT_AUTHOR = '_yoohw_email_sent_author_v1';
	const COMMENT_META_SENT_REPLY  = '_yoohw_email_sent_reply_v1';

	const POST_META_CLOSE_NOTICE_SENT = '_yoohw_close_notice_sent_v1';
	const CRON_HOOK_CLOSE_NOTICE      = 'yoohw_send_topic_close_notice';

	const COMMENT_META_ADMIN_FOLLOWUP_SENT = '_yoohw_admin_followup_sent_v1';
	const CRON_HOOK_ADMIN_FOLLOWUP         = 'yoohw_send_admin_followup_notice';

	const FROM_EMAIL = '';
	const FROM_NAME  = '';
	const EMAIL_TEMPLATE_MARKER = 'yoohw-support-email-template';

	public static function init() {
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

		if ( 'publish' !== $new_status || 'publish' === $old_status ) {
			return;
		}

		if ( wp_is_post_autosave( $post->ID ) || wp_is_post_revision( $post->ID ) ) {
			return;
		}

		self::send_admin_notification( $post->ID, $post );
	}

	public static function send_admin_notification( $post_id, $post ) {
		$admin_email = sanitize_email( get_option( 'admin_email' ) );

		if ( ! is_email( $admin_email ) ) {
			return;
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

		self::send_email(
			$admin_email,
			$subject,
			self::build_email_html( 'New Topic Published', $content )
		);
	}

	public static function send_author_notification( $comment_id, $comment_approved ) {
		if ( 1 !== (int) $comment_approved ) {
			return;
		}

		if ( get_comment_meta( $comment_id, self::COMMENT_META_SENT_AUTHOR, true ) ) {
			return;
		}

		$comment = get_comment( $comment_id );

		if ( ! $comment ) {
			return;
		}

		$post = get_post( $comment->comment_post_ID );

		if ( ! self::is_valid_target_post( $post ) ) {
			return;
		}

		if ( (int) $comment->user_id === (int) $post->post_author ) {
			delete_post_meta( $post->ID, self::POST_META_CLOSE_NOTICE_SENT );
		}

		$author_email         = self::normalize_email( get_the_author_meta( 'user_email', $post->post_author ) );
		$comment_author_email = self::normalize_email( $comment->comment_author_email );

		if ( ! is_email( $author_email ) ) {
			update_comment_meta( $comment_id, self::COMMENT_META_SENT_AUTHOR, 1 );
			return;
		}

		if ( $author_email && $author_email === $comment_author_email ) {
			update_comment_meta( $comment_id, self::COMMENT_META_SENT_AUTHOR, 1 );
			return;
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

		self::send_email(
			$author_email,
			$subject,
			self::build_email_html( 'New Reply on Your Topic', $content )
		);

		update_comment_meta( $comment_id, self::COMMENT_META_SENT_AUTHOR, 1 );
	}

	public static function send_reply_notification( $comment_id, $comment_approved ) {
		if ( 1 !== (int) $comment_approved ) {
			return;
		}

		if ( get_comment_meta( $comment_id, self::COMMENT_META_SENT_REPLY, true ) ) {
			return;
		}

		$comment = get_comment( $comment_id );

		if ( ! $comment ) {
			return;
		}

		$post = get_post( $comment->comment_post_ID );

		if ( ! self::is_valid_target_post( $post ) ) {
			return;
		}

		$comment_author_email = self::normalize_email( $comment->comment_author_email );
		$post_author_email    = self::normalize_email( get_the_author_meta( 'user_email', $post->post_author ) );

		$comments = get_comments(
			[
				'post_id' => $post->ID,
				'status'  => 'approve',
				'type'    => 'comment',
				'number'  => 100,
			]
		);

		$notified_emails = [];
		$reply_preview   = self::get_content_preview( $comment->comment_content );
		$subject         = '[New Reply] ' . wp_strip_all_tags( $post->post_title );

		foreach ( $comments as $existing_comment ) {
			if ( (int) $existing_comment->comment_ID === (int) $comment_id ) {
				continue;
			}

			$existing_email = self::normalize_email( $existing_comment->comment_author_email );

			if ( ! is_email( $existing_email ) ) {
				continue;
			}

			if (
				$existing_email === $comment_author_email ||
				$existing_email === $post_author_email ||
				in_array( $existing_email, $notified_emails, true )
			) {
				continue;
			}

			$content = '
				<p>A new reply has been posted on a post you replied to.</p>
				<p><strong>Topic title:</strong> ' . esc_html( $post->post_title ) . '</p>
				<p><strong>Reply (preview):</strong><br>' . $reply_preview . '</p>
				<p><strong>Reply author:</strong> ' . esc_html( $comment->comment_author ) . '</p>
				<p><strong>View reply:</strong> <a href="' . esc_url( get_comment_link( $comment_id ) ) . '">Click here to view the reply</a></p>
			';

			self::send_email(
				$existing_email,
				$subject,
				self::build_email_html( 'New Reply on Topic', $content )
			);

			$notified_emails[] = $existing_email;
		}

		update_comment_meta( $comment_id, self::COMMENT_META_SENT_REPLY, 1 );
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

	public static function apply_global_email_template( $mail ) {
		if ( ! is_array( $mail ) ) {
			return $mail;
		}

		$enabled = (bool) apply_filters( 'yoohw_support_apply_global_email_template', true, $mail );

		if ( ! $enabled ) {
			return $mail;
		}

		$message = isset( $mail['message'] ) ? (string) $mail['message'] : '';

		if ( '' === trim( $message ) ) {
			return $mail;
		}

		if ( self::is_template_applied( $message ) ) {
			$mail['headers'] = self::ensure_html_header( $mail['headers'] ?? [] );
			return $mail;
		}

		$subject         = isset( $mail['subject'] ) ? wp_strip_all_tags( (string) $mail['subject'] ) : '';
		$heading         = '' !== trim( $subject ) ? trim( $subject ) : __( 'Notification', 'yoohw-support-portal' );
		$content         = self::prepare_global_email_content( $message );
		$mail['message'] = self::build_email_html( $heading, $content );
		$mail['headers'] = self::ensure_html_header( $mail['headers'] ?? [] );

		return $mail;
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

	private static function build_email_html( $heading, $content ) {
		$site_name         = self::site_title();
		$show_credit       = class_exists( 'YoOhw_Support_Settings' ) && YoOhw_Support_Settings::footer_credit_enabled();
		$credit_url        = class_exists( 'YoOhw_Support_Settings' ) ? YoOhw_Support_Settings::credit_url() : 'https://yoohw.com';
		$credit_label      = class_exists( 'YoOhw_Support_Settings' ) ? YoOhw_Support_Settings::credit_label() : __( 'Power by YoOhw Studio', 'yoohw-support-portal' );
		$palette           = self::get_email_palette();
		$link_style        = self::email_style(
			[
				'color'           => $palette['primary_strong'],
				'font-weight'     => '700',
				'text-decoration' => 'none',
			]
		);
		$content           = self::apply_email_link_styles( $content, $link_style );
		$body_style        = self::email_style(
			[
				'margin'           => '0',
				'padding'          => '0',
				'background-color' => $palette['background'],
				'font-family'      => 'Arial,Helvetica,sans-serif',
				'color'            => $palette['text'],
			]
		);
		$outer_table_style = self::email_style(
			[
				'background-color' => $palette['background'],
				'margin'           => '0',
				'padding'          => '32px 12px',
			]
		);
		$card_style        = self::email_style(
			[
				'max-width'        => '680px',
				'background-color' => $palette['surface'],
				'border-radius'    => '14px',
				'overflow'         => 'hidden',
				'border'           => '1px solid ' . $palette['border'],
				'box-shadow'       => '0 8px 28px rgba(15,23,42,0.08)',
			]
		);
		$header_style      = self::email_style(
			[
				'background-color' => $palette['primary'],
				'background'       => 'linear-gradient(135deg,' . $palette['primary'] . ' 0%,' . $palette['primary_strong'] . ' 100%)',
				'padding'          => '28px 30px',
				'text-align'       => 'left',
			]
		);
		$kicker_style      = self::email_style(
			[
				'font-size'      => '13px',
				'font-weight'    => '700',
				'letter-spacing' => '.08em',
				'text-transform' => 'uppercase',
				'color'          => $palette['primary_soft'],
				'margin-bottom'  => '8px',
			]
		);
		$heading_style     = self::email_style(
			[
				'font-size'   => '24px',
				'line-height' => '1.3',
				'font-weight' => '800',
				'color'       => $palette['primary_text'],
			]
		);
		$content_style     = self::email_style(
			[
				'padding'     => '30px',
				'font-size'   => '15px',
				'line-height' => '1.7',
				'color'       => $palette['text'],
			]
		);
		$notice_style      = self::email_style(
			[
				'border-top'  => '1px solid ' . $palette['border'],
				'padding-top' => '18px',
				'font-size'   => '13px',
				'line-height' => '1.6',
				'color'       => $palette['muted'],
			]
		);
		$footer_style      = self::email_style(
			[
				'background-color' => $palette['primary_faint'],
				'padding'          => '18px 30px',
				'text-align'       => 'center',
				'font-size'        => '12px',
				'line-height'      => '1.6',
				'color'            => $palette['muted'],
			]
		);
		$strong_style      = self::email_style( [ 'color' => $palette['text'] ] );
		$footer_row        = $show_credit ? '
							<tr>
								<td style="' . $footer_style . '">
									<a href="' . esc_url( $credit_url ) . '" style="' . $link_style . '">' . esc_html( $credit_label ) . '</a>
								</td>
							</tr>' : '';

		return '
		<!DOCTYPE html>
		<html>
		<head>
			<meta http-equiv="Content-Type" content="text/html; charset=UTF-8" />
			<meta name="viewport" content="width=device-width, initial-scale=1.0" />
		</head>
		<body style="' . $body_style . '">
			<!-- ' . self::EMAIL_TEMPLATE_MARKER . ' -->
			<table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" style="' . $outer_table_style . '">
				<tr>
					<td align="center">

						<table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" style="' . $card_style . '">

							<tr>
								<td style="' . $header_style . '">
									<div style="' . $kicker_style . '">
										' . esc_html( $site_name ) . '
									</div>
									<div style="' . $heading_style . '">
										' . esc_html( $heading ) . '
									</div>
								</td>
							</tr>

							<tr>
								<td style="' . $content_style . '">
									' . $content . '
								</td>
							</tr>

							<tr>
								<td style="padding:0 30px 26px;">
									<div style="' . $notice_style . '">
										This notification was sent from <strong style="' . $strong_style . '">' . esc_html( $site_name ) . '</strong>.<br>
										Please do not reply directly to this automated email.
									</div>
								</td>
							</tr>

							' . $footer_row . '

						</table>

					</td>
				</tr>
			</table>
		</body>
		</html>';
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
			// phpcs:disable WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- Scheduled bounded lookup for topics that have not received the one-time notice.
			$posts = get_posts(
			[
				'post_type'      => 'post',
				'post_status'    => 'publish',
				'posts_per_page' => 50,
				'fields'         => 'ids',
				'meta_query'     => [
					'relation' => 'OR',
					[
						'key'     => self::POST_META_CLOSE_NOTICE_SENT,
						'compare' => 'NOT EXISTS',
					],
					[
						'key'     => self::POST_META_CLOSE_NOTICE_SENT,
						'value'   => '',
						'compare' => '=',
					],
				],
				]
			);
			// phpcs:enable WordPress.DB.SlowDBQuery.slow_db_query_meta_query

		if ( empty( $posts ) ) {
			return;
		}

		foreach ( $posts as $post_id ) {
			self::maybe_send_topic_close_notice( (int) $post_id );
		}
	}

	private static function maybe_send_topic_close_notice( $post_id ) {
		$post = get_post( $post_id );

		if ( ! self::is_valid_target_post( $post ) ) {
			return;
		}

		if ( get_post_meta( $post_id, self::POST_META_CLOSE_NOTICE_SENT, true ) ) {
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

		// Latest reply must be from support.
		if ( ! self::is_support_comment( $latest_comment ) ) {
			return;
		}

		$latest_comment_time = strtotime( $latest_comment->comment_date_gmt . ' GMT' );

		if ( ! $latest_comment_time ) {
			return;
		}

		$notice_time = $latest_comment_time + DAY_IN_SECONDS;

		// Send after 1 day, which is 24 hours before the 2-day auto-resolve.
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
			update_post_meta( $post_id, self::POST_META_CLOSE_NOTICE_SENT, current_time( 'mysql' ) );
			return;
		}

		$subject = '[Topic Closing Soon] ' . wp_strip_all_tags( $post->post_title );

		$content = '
			<p>Your support topic is scheduled to be automatically marked as resolved in about 24 hours because our latest reply has not received a response from you.</p>
			<p><strong>Topic title:</strong> ' . esc_html( $post->post_title ) . '</p>
			<p>If you still need help, please reply to the topic before it is closed.</p>
			<p><strong>View topic:</strong> <a href="' . esc_url( get_permalink( $post_id ) ) . '">Click here to view and reply</a></p>
		';

		self::send_email(
			$author_email,
			$subject,
			self::build_email_html( 'Your topic will close soon', $content )
		);

		update_post_meta( $post_id, self::POST_META_CLOSE_NOTICE_SENT, current_time( 'mysql' ) );
	}

	public static function send_admin_followup_notice_checker() {
		$posts = get_posts(
			[
				'post_type'      => 'post',
				'post_status'    => 'publish',
				'posts_per_page' => 100,
				'fields'         => 'ids',
				'orderby'        => 'modified',
				'order'          => 'DESC',
			]
		);

		if ( empty( $posts ) ) {
			return;
		}

		foreach ( $posts as $post_id ) {
			self::maybe_send_admin_followup_notice( (int) $post_id );
		}
	}

	private static function maybe_send_admin_followup_notice( $post_id ) {
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

		// Send only after 12 hours from the latest user reply.
		if ( time() < ( $latest_comment_time + ( 12 * HOUR_IN_SECONDS ) ) ) {
			return;
		}

		$admin_email = sanitize_email( get_option( 'admin_email' ) );

		if ( ! is_email( $admin_email ) ) {
			update_comment_meta( $latest_comment->comment_ID, self::COMMENT_META_ADMIN_FOLLOWUP_SENT, 1 );
			return;
		}

		$subject = '[Waiting for Support Reply] ' . wp_strip_all_tags( $post->post_title );
		$preview = self::get_content_preview( $latest_comment->comment_content );
		$button_style = self::email_button_style();

		$content = '
			<p>A support topic has been waiting for an administrator reply for more than 12 hours.</p>
			<p><strong>Topic title:</strong> ' . esc_html( $post->post_title ) . '</p>
			<p><strong>Latest user reply:</strong><br>' . $preview . '</p>
			<p><strong>Reply author:</strong> ' . esc_html( $latest_comment->comment_author ) . '</p>
			<p style="margin:24px 0 0;">
				<a href="' . esc_url( get_comment_link( $latest_comment->comment_ID ) ) . '" style="' . $button_style . '">
					View Topic
				</a>
			</p>
		';

		self::send_email(
			$admin_email,
			$subject,
			self::build_email_html( 'Topic waiting for support reply', $content )
		);

		update_comment_meta( $latest_comment->comment_ID, self::COMMENT_META_ADMIN_FOLLOWUP_SENT, 1 );
	}

	private static function get_email_palette(): array {
		$defaults = [
			'primary'        => '#2563eb',
			'primary_strong' => '#1d4ed8',
			'primary_soft'   => '#eef2ff',
			'primary_faint'  => '#f8fbff',
			'primary_border' => '#bfdbfe',
			'primary_text'   => '#ffffff',
			'background'     => '#f6f7f9',
			'surface'        => '#ffffff',
			'border'         => '#e5e7eb',
			'text'           => '#18212f',
			'muted'          => '#647084',
		];

		if ( class_exists( 'YoOhw_Support_Settings' ) && method_exists( 'YoOhw_Support_Settings', 'ui_palette' ) ) {
			$palette = YoOhw_Support_Settings::ui_palette();

			if ( is_array( $palette ) ) {
				return array_merge( $defaults, $palette );
			}
		}

		return $defaults;
	}

	private static function email_button_style(): string {
		$palette = self::get_email_palette();

		return self::email_style(
			[
				'display'          => 'inline-block',
				'background-color' => $palette['primary'],
				'color'            => $palette['primary_text'],
				'text-decoration'  => 'none',
				'font-weight'      => '700',
				'border-radius'    => '8px',
				'padding'          => '12px 18px',
			]
		);
	}

	private static function email_style( array $declarations ): string {
		$style = '';

		foreach ( $declarations as $property => $value ) {
			$property = preg_replace( '/[^a-zA-Z0-9\-]/', '', (string) $property );
			$value    = trim( (string) $value );

			if ( '' === $property || '' === $value ) {
				continue;
			}

			$style .= $property . ':' . $value . ';';
		}

		return esc_attr( $style );
	}

	private static function apply_email_link_styles( $content, string $link_style ): string {
		$content = (string) $content;
		$styled  = preg_replace( '/<a\b(?![^>]*\bstyle=)/i', '<a style="' . $link_style . '"', $content );

		return is_string( $styled ) ? $styled : $content;
	}

	private static function is_template_applied( string $message ): bool {
		return false !== strpos( $message, self::EMAIL_TEMPLATE_MARKER );
	}

	private static function is_complete_html_document( string $message ): bool {
		return (bool) preg_match( '/<(?:!doctype\s+html|html|body)\b/i', $message );
	}

	private static function prepare_global_email_content( string $message ): string {
		if ( class_exists( 'YoOhw_Support_Controller' ) && method_exists( 'YoOhw_Support_Controller', 'replace_reset_password_links' ) ) {
			$message = YoOhw_Support_Controller::replace_reset_password_links( $message );
		}

		if ( self::is_complete_html_document( $message ) ) {
			$message = self::extract_email_body_content( $message );
		}

		if ( self::looks_like_html_fragment( $message ) ) {
			return wp_kses_post( $message );
		}

		$message = make_clickable( esc_html( $message ) );

		return wpautop( $message );
	}

	private static function looks_like_html_fragment( string $message ): bool {
		return (bool) preg_match( '/<(?:p|br|div|span|strong|em|b|i|a|ul|ol|li|table|tr|td|th|h[1-6]|blockquote|pre|code)\b/i', $message );
	}

	private static function extract_email_body_content( string $message ): string {
		if ( preg_match( '/<body\b[^>]*>(.*)<\/body>/is', $message, $matches ) ) {
			return (string) $matches[1];
		}

		$message = preg_replace( '/<!doctype[^>]*>/i', '', $message );
		$message = preg_replace( '/<\/?(?:html|head|body)\b[^>]*>/i', '', (string) $message );

		return trim( (string) $message );
	}

	private static function ensure_html_header( $headers ): array {
		$headers_array = self::normalize_headers( $headers );
		$has_type      = false;

		foreach ( $headers_array as $index => $header ) {
			if ( 0 === stripos( trim( (string) $header ), 'content-type:' ) ) {
				$headers_array[ $index ] = 'Content-Type: text/html; charset=UTF-8';
				$has_type                = true;
			}
		}

		if ( ! $has_type ) {
			$headers_array[] = 'Content-Type: text/html; charset=UTF-8';
		}

		return $headers_array;
	}

	private static function normalize_headers( $headers ): array {
		$normalized = [];

		if ( is_array( $headers ) ) {
			foreach ( $headers as $header ) {
				$header = trim( (string) $header );

				if ( '' !== $header ) {
					$normalized[] = $header;
				}
			}

			return $normalized;
		}

		if ( is_string( $headers ) && '' !== trim( $headers ) ) {
			$headers = preg_split( "/\r\n|\n|\r/", $headers );

			if ( ! is_array( $headers ) ) {
				return [];
			}

			foreach ( $headers as $header ) {
				$header = trim( (string) $header );

				if ( '' !== $header ) {
					$normalized[] = $header;
				}
			}
		}

		return $normalized;
	}

	private static function get_headers() {
		$default_from_name = self::site_title();
		$from_name         = sanitize_text_field( apply_filters( 'yoohw_support_from_name', $default_from_name ) );
		$from_email        = self::get_from_email();

		if ( '' === $from_name ) {
			$from_name = $default_from_name;
		}

		return [
			'Content-Type: text/html; charset=UTF-8',
			'From: ' . $from_name . ' <' . $from_email . '>',
		];
	}

	private static function site_title(): string {
		$site_title = wp_specialchars_decode( (string) get_bloginfo( 'name' ), ENT_QUOTES );
		$site_title = trim( wp_strip_all_tags( $site_title ) );

		return '' !== $site_title ? $site_title : __( 'Support Portal', 'yoohw-support-portal' );
	}

	private static function get_from_email(): string {
		$from_email = sanitize_email( apply_filters( 'yoohw_support_from_email', self::FROM_EMAIL ) );

		if ( is_email( $from_email ) ) {
			return $from_email;
		}

		$admin_email = sanitize_email( get_option( 'admin_email' ) );

		if ( is_email( $admin_email ) ) {
			return $admin_email;
		}

		$host = wp_parse_url( home_url( '/' ), PHP_URL_HOST );
		$host = $host ? preg_replace( '/^www\./', '', $host ) : '';
		$fallback = $host ? 'wordpress@' . $host : 'wordpress@example.com';

		return is_email( $fallback ) ? $fallback : 'wordpress@example.com';
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
