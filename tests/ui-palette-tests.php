<?php
/** Run with: php tests/ui-palette-tests.php */
define( 'ABSPATH', __DIR__ );

// The palette only needs WordPress's hex-color validation, not a database.
function sanitize_hex_color( $value ) {
	return preg_match( '/^#(?:[0-9a-f]{3}|[0-9a-f]{6})$/i', $value ) ? $value : null;
}

require dirname( __DIR__ ) . '/inc/class-yoohw-support-settings.php';

function ysp_test_luminance( string $hex ): float {
	$hex = ltrim( $hex, '#' );
	if ( 3 === strlen( $hex ) ) {
		$hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2];
	}
	$channels = array_map(
		static function ( string $channel ): float {
			$value = hexdec( $channel ) / 255;
			return $value <= 0.04045 ? $value / 12.92 : pow( ( $value + 0.055 ) / 1.055, 2.4 );
		},
		str_split( $hex, 2 )
	);
	return 0.2126 * $channels[0] + 0.7152 * $channels[1] + 0.0722 * $channels[2];
}

function ysp_test_contrast( string $foreground, string $background, string $context ): void {
	$a     = ysp_test_luminance( $foreground );
	$b     = ysp_test_luminance( $background );
	$ratio = ( max( $a, $b ) + 0.05 ) / ( min( $a, $b ) + 0.05 );
	if ( $ratio < 4.5 ) {
		throw new RuntimeException( "$context: $foreground on $background has contrast $ratio" );
	}
}

$derive = new ReflectionMethod( YoOhw_Support_Settings::class, 'derive_ui_palette' );
$derive->setAccessible( true );
$defaults = [ 'ui_primary_color' => '#2563eb', 'ui_background_color' => '#f6f7f9' ];
$checks   = 0;
foreach ( [ '#000000', '#0f172a', '#374151', '#737373', '#777777', '#888888', '#f6f7f9', '#ffffff' ] as $background ) {
	foreach ( [ '#ffffff', '#fef08a', '#93c5fd', '#22c55e', '#2563eb', '#8caf31', '#808080', '#000000' ] as $primary ) {
		$palette = $derive->invoke( null, [ 'ui_background_color' => $background, 'ui_primary_color' => $primary ], $defaults );
		if ( $palette['primary'] !== $primary || $palette['background'] !== $background ) {
			throw new RuntimeException( 'The chosen primary/background color must be retained.' );
		}
		foreach ( [ 'background', 'surface', 'surface_muted', 'primary_soft', 'primary_faint', 'customer_surface' ] as $surface ) {
			foreach ( [ 'text', 'muted', 'primary_link' ] as $text ) {
				ysp_test_contrast( $palette[ $text ], $palette[ $surface ], "$background/$primary $text/$surface" );
				$checks++;
			}
		}
		foreach ( [ 'primary' => 'primary_text', 'primary_strong' => 'primary_hover_text' ] as $fill => $text ) {
			ysp_test_contrast( $palette[ $text ], $palette[ $fill ], "$background/$primary $text/$fill" );
			$checks++;
		}
	}
}
$fallback = $derive->invoke( null, [ 'ui_background_color' => 'invalid', 'ui_primary_color' => '' ], $defaults );
if ( $fallback['primary'] !== '#2563eb' || $fallback['background'] !== '#f6f7f9' ) {
	throw new RuntimeException( 'Invalid colors must use the existing defaults.' );
}
echo "ui-palette-tests-ok ($checks contrast pairs)\n";
