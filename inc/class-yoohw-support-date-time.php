<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Presentation only: stored timestamps and support schedules remain unchanged. */
class YoOhw_Support_Date_Time {

	public static function uses_browser(): bool {
		return 'browser' === ( YoOhw_Support_Settings::get()['frontend_date_time'] ?? 'site' );
	}

	/** Accept UTC MySQL dates only; never ask JavaScript to parse a timezone-less date. */
	public static function html( string $utc, bool $with_time = false, ?string $fallback = null ): string {
		$date = DateTimeImmutable::createFromFormat( '!Y-m-d H:i:s', $utc, new DateTimeZone( 'UTC' ) );
		if ( ! $date || $date->format( 'Y-m-d H:i:s' ) !== $utc || '0000' === substr( $utc, 0, 4 ) ) {
			return esc_html( $fallback ?? '' );
		}
		$format = get_option( 'date_format' ) . ( $with_time ? ' ' . get_option( 'time_format' ) : '' );
		$text = $fallback ?? wp_date( $format, $date->getTimestamp(), wp_timezone() );
		return '<time datetime="' . esc_attr( $date->format( 'Y-m-d\TH:i:s\Z' ) ) . '"'
			. ( self::uses_browser() ? ' data-yoohw-date="' . ( $with_time ? 'datetime' : 'date' ) . '"' : '' )
			. '>' . esc_html( $text ) . '</time>';
	}

	public static function output( string $utc, bool $with_time = false, ?string $fallback = null ): void {
		echo self::html( $utc, $with_time, $fallback ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- html() escapes the text and canonical datetime attribute.
	}

	public static function output_timezone_note(): void {
		if ( self::uses_browser() ) {
			echo '<p class="yoohw-timezone-note" data-yoohw-timezone hidden></p>';
		}
	}
}
