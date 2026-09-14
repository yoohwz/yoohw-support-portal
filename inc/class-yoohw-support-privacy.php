<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Privacy disclosures for support conversations and attachments.
 */
class YoOhw_Support_Privacy {

	public static function init(): void {
		add_action( 'admin_init', [ __CLASS__, 'add_policy_content' ] );
	}

	public static function add_policy_content(): void {
		if ( ! function_exists( 'wp_add_privacy_policy_content' ) ) {
			return;
		}

		$content = '<p class="privacy-policy-tutorial">'
			. esc_html__(
				'YoOhw Support Portal stores information submitted through support topics and replies. Review and adapt the suggested text to match your support and retention practices.',
				'yoohw-support-portal'
			)
			. '</p>'
			. '<strong class="privacy-policy-tutorial">'
			. esc_html__( 'Suggested text:', 'yoohw-support-portal' )
			. '</strong>'
			. '<p>'
			. esc_html__(
				'When you create a support topic or reply, we store the message, uploaded attachments, account identifier, category, timestamps, and conversation status so that we can provide support and maintain the conversation history.',
				'yoohw-support-portal'
			)
			. '</p>'
			. '<p>'
			. esc_html__(
				'Support data is stored on this website. The support portal does not send this data to YoOhw or another external service. Access is limited to the customer who owns the conversation and authorized support staff.',
				'yoohw-support-portal'
			)
			. '</p>'
			. '<p>'
			. esc_html__(
				'Support conversations and attachments are retained according to our support-data retention policy. You may request an export or deletion of your personal data, subject to any information we must retain for administrative, legal, or security purposes.',
				'yoohw-support-portal'
			)
			. '</p>';

		wp_add_privacy_policy_content(
			__( 'YoOhw Support Portal', 'yoohw-support-portal' ),
			wp_kses_post( $content )
		);
	}
}
