<?php
/** Local WP-CLI: settings and server presentation, no support-data writes. */
if ( ! defined( 'WP_CLI' ) || ! WP_CLI || 'local' !== wp_get_environment_type() ) {
	throw new RuntimeException( 'Frontend date/time tests require Local WP-CLI.' );
}
$settings = YoOhw_Support_Settings::defaults();
$read = static function () use ( &$settings ) { return $settings; };
$zone = 'Asia/Ho_Chi_Minh';
$timezone = static function () use ( &$zone ) { return $zone; };
$date_format = static function () { return 'd/m/Y'; };
$time_format = static function () { return 'H:i'; };
$errors = $GLOBALS['wp_settings_errors'] ?? [];
$checks = 0;
$assert = static function ( $ok, $message ) use ( &$checks ) { if ( ! $ok ) { throw new RuntimeException( $message ); } ++$checks; };
add_filter( 'pre_option_' . YoOhw_Support_Settings::OPTION_NAME, $read );
add_filter( 'pre_option_timezone_string', $timezone );
add_filter( 'pre_option_date_format', $date_format );
add_filter( 'pre_option_time_format', $time_format );
try {
	$assert( 'site' === $settings['frontend_date_time'], 'Default is Site settings.' );
	unset( $settings['frontend_date_time'] );
	$assert( ! YoOhw_Support_Date_Time::uses_browser(), 'Old options missing key remain Site settings.' );
	foreach ( [ 'wordpress', 'isolated' ] as $mode ) {
		$settings['dedicated_portal_mode'] = $mode;
		foreach ( [ 'site', 'browser' ] as $display ) {
			$settings['frontend_date_time'] = $display;
			$clean = YoOhw_Support_Settings::sanitize( [ '_tab' => 'general', 'frontend_date_time' => $display, 'dedicated_portal_mode' => $mode ] );
			$assert( $display === $clean['frontend_date_time'], "$mode: accepts display choice." );
			foreach ( [ 'general', 'emails' ] as $tab ) {
				$clean = YoOhw_Support_Settings::sanitize( [ '_tab' => $tab ] );
				$assert( $display === $clean['frontend_date_time'], "$mode: omitted $tab field retains display mode." );
			}
			ob_start(); YoOhw_Support_Settings::render_frontend_date_time_field(); $field = ob_get_clean();
			$assert( 2 === substr_count( $field, 'type="radio"' ) && 1 === substr_count( $field, "checked='checked'" ) && false === strpos( $field, 'disabled' ), "$mode: both choices enabled, one selected." );
			$html = YoOhw_Support_Date_Time::html( '2026-11-15 23:30:00', true );
			$assert( false !== strpos( $html, 'datetime="2026-11-15T23:30:00Z"' ) && false !== strpos( $html, '>16/11/2026 06:30</time>' ), "$mode: UTC canonical attribute and site-timezone fallback." );
			$assert( ( 'browser' === $display ) === ( false !== strpos( $html, 'data-yoohw-date="datetime"' ) ), "$mode: only browser selection activates JS conversion." );
			$html = YoOhw_Support_Date_Time::html( '2026-11-15 23:30:00' );
			$assert( false !== strpos( $html, '>16/11/2026</time>' ), "$mode: date-only fallback stays date-only." );
			ob_start(); YoOhw_Support_Date_Time::output_timezone_note(); $note = ob_get_clean();
			$assert( ( 'browser' === $display ) === ( false !== strpos( $note, 'data-yoohw-timezone hidden' ) ), "$mode: timezone note initially hidden, browser only." );
		}
	}
	foreach ( [ null, [], true, 1, 'unknown' ] as $invalid ) {
		$clean = YoOhw_Support_Settings::sanitize( [ '_tab' => 'general', 'frontend_date_time' => $invalid ] );
		$assert( 'browser' === $clean['frontend_date_time'], 'Invalid choice retains previous selection.' );
	}
	foreach ( [ '', '0000-00-00 00:00:00', '2026-02-30 00:00:00', 'not a date', '2026-11-15T23:30:00Z' ] as $invalid ) {
		$assert( '&lt;fallback&gt;' === YoOhw_Support_Date_Time::html( $invalid, true, '<fallback>' ), 'Malformed UTC leaves escaped fallback and no conversion marker.' );
	}
	$assert( false !== strpos( YoOhw_Support_Date_Time::html( '2026-11-15 23:30:00', false, '<filtered date>' ), '&lt;filtered date&gt;' ), 'Native filtered date text preserved and escaped.' );
	$zone = 'America/New_York';
	$assert( false !== strpos( YoOhw_Support_Date_Time::html( '2026-03-08 06:30:00', true ), '08/03/2026 01:30' ), 'Site fallback before DST transition.' );
	$assert( false !== strpos( YoOhw_Support_Date_Time::html( '2026-03-08 07:30:00', true ), '08/03/2026 03:30' ), 'Site fallback after DST transition.' );
	$zone = '+05:30';
	$assert( false !== strpos( YoOhw_Support_Date_Time::html( '2026-11-15 23:30:00', true ), '16/11/2026 05:00' ), 'Fixed-offset site timezone.' );
	WP_CLI::log( "frontend-date-time-ok: $checks checks; settings and UTC/site fallback in both modes, no support-data writes" );
} finally {
	$GLOBALS['wp_settings_errors'] = $errors;
	remove_filter( 'pre_option_' . YoOhw_Support_Settings::OPTION_NAME, $read );
	remove_filter( 'pre_option_timezone_string', $timezone );
	remove_filter( 'pre_option_date_format', $date_format );
	remove_filter( 'pre_option_time_format', $time_format );
}
