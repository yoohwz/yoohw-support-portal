<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Shared rendering only; native notification hooks are registered separately. */
class YoOhw_Support_Email_Template {

	const FROM_EMAIL = '';
	const FROM_NAME  = '';
	const EMAIL_TEMPLATE_MARKER = 'yoohw-support-email-template';

	public static function sample_html(): string {
		return self::build_email_html( __( 'Example support notification', 'yoohw-support-portal' ), '<p>' . esc_html__( 'This is a sample notification. No support conversation data is included.', 'yoohw-support-portal' ) . '</p><p><a href="' . esc_url( home_url( '/support/' ) ) . '">' . esc_html__( 'Open support portal', 'yoohw-support-portal' ) . '</a></p>' );
	}

	public static function send_test( string $recipient ): bool {
		return is_email( $recipient ) && wp_mail( $recipient, __( 'Support Portal test email', 'yoohw-support-portal' ), self::sample_html(), self::get_headers() );
	}

	public static function apply_global_email_template( $mail ) {
		if ( ! is_array( $mail ) ) {
			return $mail;
		}

		$enabled = (bool) apply_filters( 'yoohw_support_apply_global_email_template', YoOhw_Support_Settings::feature_enabled( 'email_global_template' ), $mail );

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

	protected static function build_email_html( $heading, $content ) {
		$site_name         = self::site_title();
		$show_credit       = class_exists( 'YoOhw_Support_Settings' ) && YoOhw_Support_Settings::footer_credit_enabled();
		$credit_url        = class_exists( 'YoOhw_Support_Settings' ) ? YoOhw_Support_Settings::credit_url() : 'https://yoohw.com';
		$credit_label      = class_exists( 'YoOhw_Support_Settings' ) ? YoOhw_Support_Settings::credit_label() : __( 'Power by YoOhw Studio', 'yoohw-support-portal' );
		$palette           = self::get_email_palette();
		$link_style        = self::email_style(
			[
				'color'           => $palette['primary_link'],
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
				'color'          => $palette['primary_text'],
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

	private static function get_email_palette(): array {
		$defaults = [
			'primary'        => '#2563eb',
			'primary_strong' => '#1d4ed8',
			'primary_link'   => '#1d4ed8',
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

	protected static function email_button_style(): string {
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

	protected static function get_headers() {
		$configured_name   = YoOhw_Support_Settings::get()['email_from_name'];
		$default_from_name = '' !== $configured_name ? $configured_name : self::site_title();
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
		$from_email = sanitize_email( apply_filters( 'yoohw_support_from_email', YoOhw_Support_Settings::get()['email_from_address'] ?: self::FROM_EMAIL ) );

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

}
