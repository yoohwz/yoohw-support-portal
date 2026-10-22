<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Submission-only HTML normalization. Never run this on stored content at display time. */
class YoOhw_Support_Composer {

	public static function allowed_html(): array {
		$tags = array_fill_keys( [ 'p', 'br', 'strong', 'b', 'em', 'i', 'u', 's', 'ul', 'li', 'blockquote', 'h3', 'h4', 'table', 'thead', 'tbody', 'tfoot', 'tr', 'caption' ], [] );
		$tags['a'] = [ 'href' => true, 'title' => true, 'target' => true, 'rel' => true ];
		$tags['ol'] = [ 'start' => true, 'reversed' => true, 'type' => true ];
		$tags['li'] = [ 'value' => true ];
		$tags['td'] = $tags['th'] = [ 'colspan' => true, 'rowspan' => true, 'scope' => true ];
		$tags['pre'] = [ 'class' => true ];
		$tags['code'] = [];
		return $tags;
	}

	public static function prepare( string $content ) {
		$media = false;
		$html = self::normalize( $content, $media );
		if ( $media ) {
			return new WP_Error( 'yoohw_composer_media', __( 'Pasted images and embedded media are not supported. Remove them and use Attachments where available.', 'yoohw-support-portal' ) );
		}
		if ( ! self::has_text( $html ) ) {
			return new WP_Error( 'yoohw_composer_empty', __( 'Enter a message with visible text or code.', 'yoohw-support-portal' ) );
		}
		return $html;
	}

	public static function has_text( string $html ): bool {
		$text = html_entity_decode( wp_strip_all_tags( $html ), ENT_QUOTES | ENT_HTML5, 'UTF-8' );
		$visible = preg_replace( '/[\s\p{Z}\p{Cf}\x{FE0F}]/u', '', $text );
		return is_string( $visible ) && '' !== $visible;
	}

	public static function normalize( string $html, bool &$media = false ): string {
		$media = false;
		$code = [];
		$prefix = 'YSPCODE' . str_replace( '-', '', wp_generate_uuid4() );
		$stash = static function ( string $text, bool $block ) use ( &$code, $prefix ): string {
			$key = $prefix . count( $code ) . 'END';
			$code[$key] = $block ? '<pre class="wp-code-block"><code>' . htmlspecialchars( $text, ENT_NOQUOTES | ENT_SUBSTITUTE, 'UTF-8' ) . '</code></pre>' : '<code>' . htmlspecialchars( $text, ENT_NOQUOTES | ENT_SUBSTITUTE, 'UTF-8' ) . '</code>';
			return $block ? "\n\n" . $key . "\n\n" : $key;
		};
		// Isolate code before any whitespace, markup or URL transformations.
		$html = preg_replace_callback( '~<(pre|code)\b([^>]*)>(.*?)(?:</\1\s*>|$)~is', static function ( $m ) use ( $stash ) {
			$attrs = wp_kses_hair( $m[2], wp_allowed_protocols() );
			if ( isset( $attrs['hidden'] ) || 'true' === strtolower( $attrs['aria-hidden']['value'] ?? '' ) || preg_match( '/(?:display\s*:\s*none|visibility\s*:\s*hidden)/i', $attrs['style']['value'] ?? '' ) ) { return ''; }
			$text = preg_replace( '~<br\s*/?>~i', "\n", $m[3] );
			// Boundary sentinels prevent wp_strip_all_tags() from trimming code whitespace.
			$text = substr( wp_strip_all_tags( 'x' . $text . 'x' ), 1, -1 );
			$text = html_entity_decode( $text, ENT_QUOTES | ENT_HTML5, 'UTF-8' );
			return $stash( $text, 'pre' === strtolower( $m[1] ) );
		}, $html );
		// Rich clipboard sources sometimes store Markdown fence lines as paragraphs.
		$html = preg_replace_callback( '~<(p|div)\b[^>]*>[ \t]*(`{3,}|\~{3,})[a-zA-Z0-9_+.#-]*[ \t]*</\1>\s*(.*?)<(?:p|div)\b[^>]*>[ \t]*\2[ \t]*</(?:p|div)>~is', static function ( $m ) use ( $stash ) {
			$text = preg_replace( '~</(?:p|div)>\s*<(?:p|div)\b[^>]*>~i', "\n", $m[3] );
			$text = preg_replace( '~<br\s*/?>~i', "\n", $text );
			$text = substr( wp_strip_all_tags( 'x' . $text . 'x' ), 1, -1 );
			return $stash( html_entity_decode( $text, ENT_QUOTES | ENT_HTML5, 'UTF-8' ), true );
		}, $html );
		$html = preg_replace_callback( '/(?:^|\n)[ \t]*(`{3,}|~{3,})[a-zA-Z0-9_+.#-]*[ \t]*\r?\n(.*?)\r?\n[ \t]*\1[ \t]*(?=\r?\n|$)/s', static function ( $m ) use ( $stash ) { return $stash( $m[2], true ); }, $html );
		$out = '';
		$skip = [];
		$void = [ 'br', 'img', 'hr', 'input', 'meta', 'link', 'source', 'embed', 'wbr', 'area', 'base', 'col', 'param', 'track' ];
		foreach ( wp_html_split( $html ) as $part ) {
			if ( preg_match( '/^<\s*(\/?)\s*([a-z][a-z0-9:-]*)\b([^>]*)>/i', $part, $m ) ) {
				$tag = strtolower( $m[2] ); $closing = '/' === $m[1];
				$single = in_array( $tag, $void, true ) || (bool) preg_match( '~/\s*>$~', $part );
				if ( $skip ) {
					if ( $closing && in_array( $tag, $skip, true ) ) { $skip = array_slice( $skip, 0, (int) array_search( $tag, $skip, true ) ); }
					elseif ( ! $closing && ! $single ) { $skip[] = $tag; }
					continue;
				}
				$is_media = in_array( $tag, [ 'img', 'iframe', 'object', 'embed', 'video', 'audio', 'svg', 'canvas' ], true );
				$attrs = wp_kses_hair( $m[3], wp_allowed_protocols() );
				$hidden = isset( $attrs['hidden'] ) || 'true' === strtolower( $attrs['aria-hidden']['value'] ?? '' ) || preg_match( '/(?:display\s*:\s*none|visibility\s*:\s*hidden)/i', $attrs['style']['value'] ?? '' );
				if ( ! $closing && ( $is_media || $hidden || in_array( $tag, [ 'script', 'style', 'head', 'title', 'template', 'noscript' ], true ) ) ) {
					$media = $media || $is_media;
					if ( ! $single ) { $skip[] = $tag; }
					continue;
				}
				if ( 'div' === $tag ) { $part = $closing ? '</p>' : '<p>'; }
				elseif ( preg_match( '/^h[1-6]$/', $tag ) ) { $part = '<' . ( $closing ? '/' : '' ) . ( (int) substr( $tag, 1 ) <= 3 ? 'h3' : 'h4' ) . '>'; }
				if ( 'a' === $tag && ! $closing && '_blank' === ( $attrs['target']['value'] ?? '' ) ) {
					$part = '<a rel="noopener noreferrer"';
					foreach ( [ 'href', 'title', 'target' ] as $name ) {
						if ( isset( $attrs[$name] ) ) { $part .= ' ' . $name . '="' . esc_attr( $attrs[$name]['value'] ) . '"'; }
					}
					$part .= '>';
				}
				$out .= wp_kses( $part, self::allowed_html() );
			} elseif ( ! $skip && 0 !== strpos( $part, '<!--' ) && 0 !== strpos( $part, '<?' ) ) {
				$part = str_replace( [ "\r\n", "\r" ], "\n", $part );
				$part = preg_replace_callback( '/(?<!`)`([^`\r\n]+)`(?!`)/', static function ( $m ) use ( $stash ) { return $stash( html_entity_decode( $m[1], ENT_QUOTES | ENT_HTML5, 'UTF-8' ), false ); }, $part );
				$part = str_replace( [ '&nbsp;', '&#160;', "\xc2\xa0" ], ' ', $part );
				$out .= preg_replace( '/[\t \f\v]+/', ' ', $part );
			}
		}
		$out = force_balance_tags( wp_kses( $out, self::allowed_html() ) );
		// Remove only empty formatting shells, retaining whitespace inside protected code.
		do {
			$before = $out;
			$out = preg_replace( '~<(p|h3|h4|blockquote|b|strong|i|em|u|s)\b[^>]*>(?:\s|&nbsp;|<br\s*/?>)*</\1>~i', '', $out );
		} while ( $before !== $out );
		$out = preg_replace( '~(?:<br\s*/?>\s*){3,}~i', '<br><br>', $out );
		$out = wpautop( trim( $out ) );
		// Autolink prose only, before restoring code. KSES revalidates protocols/attributes.
		$out = make_clickable( $out );
		$out = wp_kses( $out, self::allowed_html() );
		$out = preg_replace( '/>\n+</', '><', $out );
		foreach ( $code as $key => $value ) {
			$out = str_replace( [ '<p>' . $key . '</p>', $key ], $value, $out );
		}
		return trim( $out );
	}

	public static function editor_settings( string $id ): array {
		YoOhw_Support_Assets::enqueue_editor_assets();
		return [
			'textarea_name' => $id, 'textarea_rows' => 10, 'media_buttons' => false, 'teeny' => false, 'quicktags' => false,
			'tinymce' => [
				'toolbar1' => 'bold,italic,underline,bullist,numlist,link,unlink,blockquote,yoohw_support_inlinecode,yoohw_support_codeblock,removeformat',
				'height' => 220, 'toolbar2' => 'undo,redo,pastetext', 'paste_data_images' => false,
				'extended_valid_elements' => 'pre[class],code,a[href|target|rel|title],ol[start|reversed|type],li[value]',
				'content_style' => 'pre.wp-code-block{white-space:pre;tab-size:4;font-family:monospace;font-size:13px;line-height:1.45}code{font-family:monospace;white-space:pre-wrap}table{max-width:100%}',
				'setup' => 'function(editor){window.YoOhwSupportEditor.setup(editor);}',
			],
		];
	}
}
