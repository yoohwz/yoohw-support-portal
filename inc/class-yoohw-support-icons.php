<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class YoOhw_Support_Icons {

	/**
	 * Print a sanitized icon.
	 *
	 * @param string $name  Icon name.
	 * @param array  $attrs Optional SVG attributes.
	 */
	public static function output( string $name, array $attrs = [] ): void {
		echo self::sanitize( self::render( $name, $attrs ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- sanitize() applies a strict SVG allowlist.
	}

	public static function sanitize( string $svg ): string {
		return wp_kses( $svg, self::allowed_html() );
	}

	/**
	 * Allowed SVG markup for the internal icon set.
	 *
	 * @return array
	 */
	public static function allowed_html(): array {
		return [
			'svg'    => [
				'xmlns'           => true,
				'width'           => true,
				'height'          => true,
				'viewbox'         => true,
				'fill'            => true,
				'stroke'          => true,
				'stroke-width'    => true,
				'stroke-linecap'  => true,
				'stroke-linejoin' => true,
				'class'           => true,
				'aria-hidden'     => true,
				'focusable'       => true,
			],
			'path'   => [
				'd'    => true,
				'fill' => true,
			],
			'circle' => [
				'cx'   => true,
				'cy'   => true,
				'r'    => true,
				'fill' => true,
			],
			'line'   => [
				'x1' => true,
				'x2' => true,
				'y1' => true,
				'y2' => true,
			],
			'rect'   => [
				'x'      => true,
				'y'      => true,
				'width'  => true,
				'height' => true,
				'rx'     => true,
				'ry'     => true,
			],
		];
	}

	public static function render( string $name, array $attrs = [] ): string {
		$name  = sanitize_key( $name );
		$icons = self::icons();

		if ( empty( $icons[ $name ] ) ) {
			$name = 'circle';
		}

		$classes = [ 'yoohw-icon', 'yoohw-icon-' . $name ];

		if ( ! empty( $attrs['class'] ) ) {
			$classes[] = sanitize_html_class( (string) $attrs['class'] );
		}

		$attr_pairs = [
			'xmlns'        => 'http://www.w3.org/2000/svg',
			'width'        => isset( $attrs['size'] ) ? absint( $attrs['size'] ) : 24,
			'height'       => isset( $attrs['size'] ) ? absint( $attrs['size'] ) : 24,
			'viewBox'      => '0 0 24 24',
			'fill'         => 'none',
			'stroke'       => 'currentColor',
			'stroke-width' => '2',
			'stroke-linecap'  => 'round',
			'stroke-linejoin' => 'round',
			'class'        => implode( ' ', array_filter( $classes ) ),
			'aria-hidden'  => 'true',
			'focusable'    => 'false',
		];

		$html_attrs = '';

		foreach ( $attr_pairs as $attr => $value ) {
			$html_attrs .= ' ' . esc_attr( $attr ) . '="' . esc_attr( (string) $value ) . '"';
		}

		return '<svg' . $html_attrs . '>' . $icons[ $name ] . '</svg>';
	}

	private static function icons(): array {
		return [
			'arrow-down'       => '<path d="M12 5v14"></path><path d="m19 12-7 7-7-7"></path>',
			'book-open'        => '<path d="M12 7v14"></path><path d="M3 18a1 1 0 0 1-1-1V4a1 1 0 0 1 1-1h5a4 4 0 0 1 4 4 4 4 0 0 1 4-4h5a1 1 0 0 1 1 1v13a1 1 0 0 1-1 1h-6a3 3 0 0 0-3 3 3 3 0 0 0-3-3z"></path>',
			'briefcase'        => '<path d="M16 20V4a2 2 0 0 0-2-2h-4a2 2 0 0 0-2 2v16"></path><rect width="20" height="14" x="2" y="6" rx="2"></rect>',
			'calendar'         => '<path d="M8 2v4"></path><path d="M16 2v4"></path><rect width="18" height="18" x="3" y="4" rx="2"></rect><path d="M3 10h18"></path>',
			'chevron-left'     => '<path d="m15 18-6-6 6-6"></path>',
			'chevron-right'    => '<path d="m9 18 6-6-6-6"></path>',
			'circle'           => '<circle cx="12" cy="12" r="10"></circle>',
			'circle-alert'     => '<circle cx="12" cy="12" r="10"></circle><line x1="12" x2="12" y1="8" y2="12"></line><line x1="12" x2="12.01" y1="16" y2="16"></line>',
			'circle-check'     => '<circle cx="12" cy="12" r="10"></circle><path d="m9 12 2 2 4-4"></path>',
			'circle-check-big' => '<path d="M21.801 10A10 10 0 1 1 17 3.335"></path><path d="m9 11 3 3L22 4"></path>',
			'circle-x'         => '<circle cx="12" cy="12" r="10"></circle><path d="m15 9-6 6"></path><path d="m9 9 6 6"></path>',
			'clock'            => '<circle cx="12" cy="12" r="10"></circle><path d="M12 6v6l4 2"></path>',
			'external-link'    => '<path d="M15 3h6v6"></path><path d="M10 14 21 3"></path><path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"></path>',
			'eye'              => '<path d="M2.062 12.348a1 1 0 0 1 0-.696 10.75 10.75 0 0 1 19.876 0 1 1 0 0 1 0 .696 10.75 10.75 0 0 1-19.876 0"></path><circle cx="12" cy="12" r="3"></circle>',
			'eye-off'          => '<path d="M10.733 5.076a10.744 10.744 0 0 1 11.205 6.575 1 1 0 0 1 0 .696 10.747 10.747 0 0 1-1.444 2.49"></path><path d="M14.084 14.158a3 3 0 0 1-4.242-4.242"></path><path d="M17.479 17.499a10.75 10.75 0 0 1-15.417-5.151 1 1 0 0 1 0-.696 10.75 10.75 0 0 1 4.446-5.143"></path><path d="m2 2 20 20"></path>',
			'file'             => '<path d="M6 22a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h8a2.4 2.4 0 0 1 1.704.706l3.588 3.588A2.4 2.4 0 0 1 20 8v12a2 2 0 0 1-2 2z"></path><path d="M14 2v5a1 1 0 0 0 1 1h5"></path>',
			'file-archive'     => '<path d="M13.659 22H18a2 2 0 0 0 2-2V8a2.4 2.4 0 0 0-.706-1.706l-3.588-3.588A2.4 2.4 0 0 0 14 2H6a2 2 0 0 0-2 2v11.5"></path><path d="M14 2v5a1 1 0 0 0 1 1h5"></path><path d="M8 12v-1"></path><path d="M8 18v-2"></path><path d="M8 7V6"></path><circle cx="8" cy="20" r="2"></circle>',
			'house'            => '<path d="M15 21v-8a1 1 0 0 0-1-1h-4a1 1 0 0 0-1 1v8"></path><path d="M3 10a2 2 0 0 1 .709-1.528l7-6a2 2 0 0 1 2.582 0l7 6A2 2 0 0 1 21 10v9a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"></path>',
			'image'            => '<rect width="18" height="18" x="3" y="3" rx="2" ry="2"></rect><circle cx="9" cy="9" r="2"></circle><path d="m21 15-3.086-3.086a2 2 0 0 0-2.828 0L6 21"></path>',
			'key-round'        => '<path d="M2.586 17.414A2 2 0 0 0 2 18.828V21a1 1 0 0 0 1 1h3a1 1 0 0 0 1-1v-1a1 1 0 0 1 1-1h1a1 1 0 0 0 1-1v-1a1 1 0 0 1 1-1h.172a2 2 0 0 0 1.414-.586l.814-.814a6.5 6.5 0 1 0-4-4z"></path><circle cx="16.5" cy="7.5" r=".5" fill="currentColor"></circle>',
			'list'             => '<path d="M3 5h.01"></path><path d="M3 12h.01"></path><path d="M3 19h.01"></path><path d="M8 5h13"></path><path d="M8 12h13"></path><path d="M8 19h13"></path>',
			'lock'             => '<rect width="18" height="11" x="3" y="11" rx="2" ry="2"></rect><path d="M7 11V7a5 5 0 0 1 10 0v4"></path>',
			'log-in'           => '<path d="m10 17 5-5-5-5"></path><path d="M15 12H3"></path><path d="M15 3h4a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2h-4"></path>',
			'log-out'          => '<path d="m16 17 5-5-5-5"></path><path d="M21 12H9"></path><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"></path>',
			'menu'             => '<path d="M4 5h16"></path><path d="M4 12h16"></path><path d="M4 19h16"></path>',
			'message-circle'   => '<path d="M2.992 16.342a2 2 0 0 1 .094 1.167l-1.065 3.29a1 1 0 0 0 1.236 1.168l3.413-.998a2 2 0 0 1 1.099.092 10 10 0 1 0-4.777-4.719"></path>',
			'message-square'   => '<path d="M22 17a2 2 0 0 1-2 2H6.828a2 2 0 0 0-1.414.586l-2.202 2.202A.71.71 0 0 1 2 21.286V5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2z"></path>',
			'paperclip'        => '<path d="m16 6-8.414 8.586a2 2 0 0 0 2.829 2.829l8.414-8.586a4 4 0 1 0-5.657-5.657l-8.379 8.551a6 6 0 1 0 8.485 8.485l8.379-8.551"></path>',
			'pause-circle'     => '<circle cx="12" cy="12" r="10"></circle><line x1="10" x2="10" y1="15" y2="9"></line><line x1="14" x2="14" y1="15" y2="9"></line>',
			'plus'             => '<path d="M5 12h14"></path><path d="M12 5v14"></path>',
			'refresh-cw'       => '<path d="M3 12a9 9 0 0 1 9-9 9.75 9.75 0 0 1 6.74 2.74L21 8"></path><path d="M21 3v5h-5"></path><path d="M21 12a9 9 0 0 1-9 9 9.75 9.75 0 0 1-6.74-2.74L3 16"></path><path d="M8 16H3v5"></path>',
			'search'           => '<path d="m21 21-4.34-4.34"></path><circle cx="11" cy="11" r="8"></circle>',
			'send'             => '<path d="M14.536 21.686a.5.5 0 0 0 .937-.024l6.5-19a.496.496 0 0 0-.635-.635l-19 6.5a.5.5 0 0 0-.024.937l7.93 3.18a2 2 0 0 1 1.112 1.11z"></path><path d="m21.854 2.147-10.94 10.939"></path>',
			'shield-check'     => '<path d="M20 13c0 5-3.5 7.5-7.66 8.95a1 1 0 0 1-.67-.01C7.5 20.5 4 18 4 13V6a1 1 0 0 1 1-1c2 0 4.5-1.2 6.24-2.72a1.17 1.17 0 0 1 1.52 0C14.51 3.81 17 5 19 5a1 1 0 0 1 1 1z"></path><path d="m9 12 2 2 4-4"></path>',
			'shopping-cart'    => '<circle cx="8" cy="21" r="1"></circle><circle cx="19" cy="21" r="1"></circle><path d="M2.05 2.05h2l2.66 12.42a2 2 0 0 0 2 1.58h9.78a2 2 0 0 0 1.95-1.57l1.65-7.43H5.12"></path>',
			'square-pen'       => '<path d="M12 3H5a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"></path><path d="M18.375 2.625a1 1 0 0 1 3 3l-9.013 9.014a2 2 0 0 1-.853.505l-2.873.84a.5.5 0 0 1-.62-.62l.84-2.873a2 2 0 0 1 .506-.852z"></path>',
			'tag'              => '<path d="M12.586 2.586A2 2 0 0 0 11.172 2H4a2 2 0 0 0-2 2v7.172a2 2 0 0 0 .586 1.414l8.704 8.704a2.426 2.426 0 0 0 3.42 0l6.58-6.58a2.426 2.426 0 0 0 0-3.42z"></path><circle cx="7.5" cy="7.5" r=".5" fill="currentColor"></circle>',
			'user'             => '<path d="M19 21v-2a4 4 0 0 0-4-4H9a4 4 0 0 0-4 4v2"></path><circle cx="12" cy="7" r="4"></circle>',
			'video'            => '<path d="m16 13 5.223 3.482a.5.5 0 0 0 .777-.416V7.87a.5.5 0 0 0-.752-.432L16 10.5"></path><rect x="2" y="6" width="14" height="12" rx="2"></rect>',
			'x'                => '<path d="M18 6 6 18"></path><path d="m6 6 12 12"></path>',
		];
	}
}
