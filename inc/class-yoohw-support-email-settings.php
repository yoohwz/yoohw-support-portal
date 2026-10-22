<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class YoOhw_Support_Email_Settings {

	const RETRY_HOOK = 'yoohw_retry_topic_notification';

	public static function init(): void {
		add_filter( 'pre_update_option_' . YoOhw_Support_Settings::OPTION_NAME, [ __CLASS__, 'prepare_update' ], 10, 2 );
		add_action( 'update_option_' . YoOhw_Support_Settings::OPTION_NAME, [ __CLASS__, 'cancel_disabled_retries' ], 10, 2 );
		add_action( 'add_option_' . YoOhw_Support_Settings::OPTION_NAME, static function ( $name, $value ) { self::cancel_disabled_retries( [], $value ); }, 10, 2 );
		add_filter( 'option_page_capability_yoohw_support_portal', static function () { return YoOhw_Support_Capabilities::MANAGE_SETTINGS; } );
		add_action( 'admin_post_yoohw_support_email_preview', [ __CLASS__, 'handle_preview' ] );
		add_action( 'admin_post_yoohw_support_email_test', [ __CLASS__, 'handle_test' ] );
	}

	public static function defaults(): array {
		return [
			'email_from_name'       => '',
			'email_from_address'    => '',
			'email_admin_recipient' => '',
			'email_notify_admin'   => '1',
			'email_notify_author'  => '1',
			'email_notify_reply'   => '1',
			'email_notify_followup' => '1',
			'email_notify_close'    => '1',
			'email_global_template' => '1',
			'email_resume_after'    => [],
		];
	}

	public static function enabled( string $type ): bool {
		return in_array( $type, [ 'admin', 'author', 'reply', 'followup', 'close' ], true ) && YoOhw_Support_Settings::feature_enabled( 'email_notify_' . $type );
	}

	public static function reminder_allowed( string $type, int $comment_id ): bool {
		$cutoffs = YoOhw_Support_Settings::get()['email_resume_after'];
		return self::enabled( $type ) && $comment_id > (int) ( $cutoffs[$type] ?? 0 );
	}

	public static function admin_recipient(): string {
		return YoOhw_Support_Settings::get()['email_admin_recipient'] ?: sanitize_email( get_option( 'admin_email' ) );
	}

	public static function sanitize( array $value, array $current ): array {
		$clean = array_intersect_key( $current, self::defaults() );
		foreach ( [ 'email_from_name', 'email_from_address', 'email_admin_recipient' ] as $key ) {
			if ( ! array_key_exists( $key, $value ) ) {
				continue;
			}
			$raw = is_string( $value[$key] ) ? trim( wp_unslash( $value[$key] ) ) : null;
			if ( null === $raw || ( 'email_from_name' !== $key && '' !== $raw && ! is_email( $raw ) ) ) {
				add_settings_error( 'yoohw_support_portal', $key, __( 'Enter one valid email address per field. Invalid values were not saved.', 'yoohw-support-portal' ) );
				continue;
			}
			$clean[$key] = 'email_from_name' === $key ? sanitize_text_field( $raw ) : sanitize_email( $raw );
		}
		foreach ( [ 'admin', 'author', 'reply', 'followup', 'close', 'global_template' ] as $type ) {
			$key = 'global_template' === $type ? 'email_global_template' : 'email_notify_' . $type;
			if ( array_key_exists( $key, $value ) ) {
				$clean[$key] = in_array( $value[$key], [ '1', 1, true ], true ) ? '1' : '0';
			}
		}
		return $clean;
	}

	public static function prepare_update( $value, $old ): array {
		$value = is_array( $value ) ? $value : [];
		$old   = array_merge( self::defaults(), is_array( $old ) ? $old : [] );
		// Internal resume points are never accepted from a submitted form.
		$value['email_resume_after'] = $old['email_resume_after'];
		foreach ( [ 'followup', 'close' ] as $type ) {
			$key = 'email_notify_' . $type;
			if ( empty( $old[$key] ) && ! empty( $value[$key] ) ) {
				global $wpdb;
				// phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Single bounded watermark; re-enabling must not backfill old reminders.
				$last = $wpdb->get_var( "SELECT MAX(comment_ID) FROM {$wpdb->comments}" );
				if ( $wpdb->last_error ) {
					$value[$key] = '0';
					add_settings_error( 'yoohw_support_portal', $key, __( 'The reminder could not be enabled. Please try saving again.', 'yoohw-support-portal' ) );
				} else {
					$value['email_resume_after'][$type] = (int) $last;
				}
			}
		}
		return $value;
	}

	public static function suppress( string $type, int $id ): void {
		update_metadata( 'admin' === $type ? 'post' : 'comment', $id, '_yoohw_email_suppressed_' . $type . '_v1', 1 );
		wp_clear_scheduled_hook( self::RETRY_HOOK, [ $type, $id ] );
	}

	public static function cancel_disabled_retries( $old, $value ): void {
		$value = array_merge( self::defaults(), is_array( $value ) ? $value : [] );
		foreach ( _get_cron_array() as $events ) {
			foreach ( $events[self::RETRY_HOOK] ?? [] as $event ) {
				$type = $event['args'][0] ?? '';
				$id   = (int) ( $event['args'][1] ?? 0 );
				if ( in_array( $type, [ 'admin', 'author', 'reply' ], true ) && empty( $value['email_notify_' . $type] ) ) {
					self::suppress( $type, $id );
				}
			}
		}
	}

	private static function authorize( string $action, string $method ): void {
		if ( ! current_user_can( YoOhw_Support_Capabilities::MANAGE_SETTINGS ) ) {
			wp_die( esc_html__( 'You are not allowed to manage support email settings.', 'yoohw-support-portal' ), '', [ 'response' => 403 ] );
		}
		if ( $method !== ( $_SERVER['REQUEST_METHOD'] ?? '' ) ) {
			wp_die( esc_html__( 'Invalid request method.', 'yoohw-support-portal' ), '', [ 'response' => 405 ] );
		}
		check_admin_referer( $action );
	}

	public static function handle_preview(): void {
		self::authorize( 'yoohw_support_email_preview', 'GET' );
		nocache_headers();
		header( 'Content-Type: text/html; charset=UTF-8' );
		header( 'X-Content-Type-Options: nosniff' );
		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Renderer escapes the fixed sample and site settings.
		echo YoOhw_Support_Email_Template::sample_html();
		exit;
	}

	public static function handle_test(): void {
		self::authorize( 'yoohw_support_email_test', 'POST' );
		$raw = isset( $_POST['recipient'] ) && is_string( $_POST['recipient'] ) ? trim( wp_unslash( $_POST['recipient'] ) ) : '';
		$status = 'invalid';
		if ( is_email( $raw ) ) {
			$status = YoOhw_Support_Email_Template::send_test( sanitize_email( $raw ) ) ? 'accepted' : 'failed';
		}
		wp_safe_redirect( add_query_arg( [ 'page' => 'yoohw-support-portal', 'tab' => 'emails', 'email_test' => $status ], admin_url( 'options-general.php' ) ) );
		exit;
	}

	private static function text_field( string $key, string $label, string $placeholder, bool $email = false ): void {
		$settings = YoOhw_Support_Settings::get();
		$id = 'yoohw-' . $key;
		echo '<tr><th scope="row"><label for="' . esc_attr( $id ) . '">' . esc_html( $label ) . '</label></th><td><input class="regular-text" id="' . esc_attr( $id ) . '" type="' . ( $email ? 'email' : 'text' ) . '" name="' . esc_attr( YoOhw_Support_Settings::OPTION_NAME . '[' . $key . ']' ) . '" value="' . esc_attr( $settings[$key] ) . '" placeholder="' . esc_attr( $placeholder ) . '"><p class="description">' . esc_html__( 'Leave blank to use the default shown above.', 'yoohw-support-portal' ) . '</p></td></tr>';
	}

	private static function checkbox( string $key, string $label ): void {
		$name = YoOhw_Support_Settings::OPTION_NAME . '[' . $key . ']';
		echo '<p><label><input type="hidden" name="' . esc_attr( $name ) . '" value="0"><input type="checkbox" name="' . esc_attr( $name ) . '" value="1" ' . checked( YoOhw_Support_Settings::feature_enabled( $key ), true, false ) . '> ' . esc_html( $label ) . '</label></p>';
	}

	public static function render_fields(): void {
		?>
		<h2><?php esc_html_e( 'Sender', 'yoohw-support-portal' ); ?></h2>
		<p><?php esc_html_e( 'Used for Support Portal notifications and test messages. Your mail transport may override the sender.', 'yoohw-support-portal' ); ?></p>
		<table class="form-table" role="presentation">
		<?php
		self::text_field( 'email_from_name', __( 'From name', 'yoohw-support-portal' ), get_bloginfo( 'name' ) );
		self::text_field( 'email_from_address', __( 'From email', 'yoohw-support-portal' ), get_option( 'admin_email' ), true );
		?>
		</table>
		<h2><?php esc_html_e( 'Topic notifications', 'yoohw-support-portal' ); ?></h2>
		<?php if ( YoOhw_Support_Settings::uses_isolated_storage() ) : ?>
			<p class="description"><?php esc_html_e( 'Topic notifications and reminders require WordPress posts/comments storage. Your saved preferences are retained while isolated storage is active.', 'yoohw-support-portal' ); ?></p>
		<?php endif; ?>
		<fieldset <?php disabled( YoOhw_Support_Settings::uses_isolated_storage() ); ?>>
			<legend class="screen-reader-text"><?php esc_html_e( 'Topic notification controls', 'yoohw-support-portal' ); ?></legend>
			<table class="form-table" role="presentation">
			<?php self::text_field( 'email_admin_recipient', __( 'Admin notification recipient', 'yoohw-support-portal' ), get_option( 'admin_email' ), true ); ?>
			</table>
			<?php
			self::checkbox( 'email_notify_admin', __( 'Notify the administrator when a new topic is published', 'yoohw-support-portal' ) );
			self::checkbox( 'email_notify_author', __( 'Notify the topic owner about new replies', 'yoohw-support-portal' ) );
			self::checkbox( 'email_notify_reply', __( 'Notify previous participants about new replies', 'yoohw-support-portal' ) );
			?>
			<h3><?php esc_html_e( 'Reminders', 'yoohw-support-portal' ); ?></h3>
			<?php
			self::checkbox( 'email_notify_followup', __( 'Remind support after a customer reply has waited 12 hours', 'yoohw-support-portal' ) );
			self::checkbox( 'email_notify_close', __( 'Warn the topic owner 24 hours after the latest support reply', 'yoohw-support-portal' ) );
			?>
		</fieldset>
		<p class="description"><?php esc_html_e( 'Topics automatically resolve after 48 hours without a customer response to the latest support reply. Disabling email does not disable auto-resolve. Re-enabling a notification applies to new events only; old events are not backfilled. Failed immediate notifications retry after 15 minutes, up to 3 times.', 'yoohw-support-portal' ); ?></p>
		<h2><?php esc_html_e( 'Email appearance', 'yoohw-support-portal' ); ?></h2>
		<?php self::checkbox( 'email_global_template', __( 'Apply the Support Portal template to all WordPress emails', 'yoohw-support-portal' ) ); ?>
		<p class="description"><?php esc_html_e( 'The global template applies in WordPress posts/comments mode. Support notifications and sample messages always use the portal template. Topic notification switches do not disable registration or password reset emails.', 'yoohw-support-portal' ); ?></p>
		<?php
	}

	public static function render_tools(): void {
		$messages = [
			'accepted' => __( 'The mail transport accepted the test email. This does not confirm inbox delivery.', 'yoohw-support-portal' ),
			'failed'   => __( 'The test email could not be sent. Check your WordPress mail transport.', 'yoohw-support-portal' ),
			'invalid'  => __( 'Enter one valid test recipient email address.', 'yoohw-support-portal' ),
		];
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only status enum; no message or recipient is accepted from the URL.
		$status = isset( $_GET['email_test'] ) && is_string( $_GET['email_test'] ) ? sanitize_key( wp_unslash( $_GET['email_test'] ) ) : '';
		if ( isset( $messages[$status] ) ) {
			echo '<div class="notice notice-' . ( 'accepted' === $status ? 'success' : 'error' ) . '"><p>' . esc_html( $messages[$status] ) . '</p></div>';
		}
		?>
		<h2><?php esc_html_e( 'Preview and test', 'yoohw-support-portal' ); ?></h2>
		<p><?php esc_html_e( 'Save changes before previewing or testing. These tools use fixed sample content and never include support conversations.', 'yoohw-support-portal' ); ?></p>
		<p><a class="button" target="_blank" rel="noopener" href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=yoohw_support_email_preview' ), 'yoohw_support_email_preview' ) ); ?>"><?php esc_html_e( 'Preview email', 'yoohw-support-portal' ); ?></a></p>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<input type="hidden" name="action" value="yoohw_support_email_test">
			<?php wp_nonce_field( 'yoohw_support_email_test' ); ?>
			<label for="yoohw-test-recipient"><?php esc_html_e( 'Test recipient', 'yoohw-support-portal' ); ?></label>
			<input type="email" class="regular-text" id="yoohw-test-recipient" name="recipient" required value="<?php echo esc_attr( get_option( 'admin_email' ) ); ?>">
			<?php submit_button( __( 'Send test email', 'yoohw-support-portal' ), 'secondary', 'yoohw_email_test_submit', false ); ?>
		</form>
		<?php
	}
}
