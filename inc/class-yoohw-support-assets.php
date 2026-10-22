<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class YoOhw_Support_Assets {

	const VERSION = '1.2.0';

	public static function init(): void {
		add_action( 'wp_enqueue_scripts', [ __CLASS__, 'enqueue_frontend' ] );
		add_action( 'wp_enqueue_scripts', [ __CLASS__, 'isolate_support_assets' ], 1000 );
		add_action( 'wp_print_styles', [ __CLASS__, 'isolate_support_assets' ], 0 );
		add_action( 'wp_print_scripts', [ __CLASS__, 'isolate_support_assets' ], 0 );
		add_action( 'wp_print_footer_scripts', [ __CLASS__, 'isolate_support_assets' ], 0 );
	}

	public static function enqueue_frontend(): void {
		if ( ! class_exists( 'YoOhw_Support_Router' ) || ! YoOhw_Support_Router::is_plugin_ui_request() ) {
			return;
		}

		self::enqueue_support_assets();
	}

	public static function enqueue_support_assets(): void {
		$css_path = YOOHW_SUPPORT_PORTAL_PATH . 'assets/css/support-center.css';
		$js_path  = YOOHW_SUPPORT_PORTAL_PATH . 'assets/js/support-center.js';
		$script_deps = [];

		if ( class_exists( 'YoOhw_Support_Router' ) && 'reset-password' === YoOhw_Support_Router::get_view() ) {
			wp_enqueue_script( 'password-strength-meter' );
			$script_deps[] = 'password-strength-meter';
		}

		wp_enqueue_style(
			'yoohw-support-portal-ui',
			YOOHW_SUPPORT_PORTAL_URL . 'assets/css/support-center.css',
			[],
			file_exists( $css_path ) ? (string) filemtime( $css_path ) : self::VERSION
		);

		if ( class_exists( 'YoOhw_Support_Settings' ) ) {
			wp_add_inline_style( 'yoohw-support-portal-ui', YoOhw_Support_Settings::custom_ui_css() );
		}

		wp_enqueue_script(
			'yoohw-support-portal-ui',
			YOOHW_SUPPORT_PORTAL_URL . 'assets/js/support-center.js',
			$script_deps,
			file_exists( $js_path ) ? (string) filemtime( $js_path ) : self::VERSION,
			true
		);

		wp_localize_script(
			'yoohw-support-portal-ui',
			'YoOhwSupportPortalConfig',
			[
				'maxFiles' => YoOhw_Support_Settings::attachment_max_count(),
				'maxSize'  => YoOhw_Support_Settings::attachment_max_bytes(),
				'conversation' => [
					'collapseReplies' => YoOhw_Support_Settings::feature_enabled( 'collapse_older_replies' ),
					'replyBatchSize' => YoOhw_Support_Settings::reply_batch_size(),
				],
				'messages' => [
					/* translators: %d: Maximum number of attachments. */
					'tooManyFiles'    => __( 'You can upload a maximum of %d attachments.', 'yoohw-support-portal' ),
					/* translators: %s: Effective maximum file size. */
					'fileTooLarge'    => sprintf( __( 'Each attachment must be %s or smaller.', 'yoohw-support-portal' ), size_format( YoOhw_Support_Settings::attachment_max_bytes() ) ),
					'noFilesSelected' => __( 'No files selected', 'yoohw-support-portal' ),
					'submitting'      => __( 'Submitting. Please wait...', 'yoohw-support-portal' ),
					'copied'          => __( 'Copied', 'yoohw-support-portal' ),
					'copy'            => __( 'Copy', 'yoohw-support-portal' ),
					/* translators: %d: Number of hidden older replies. */
					'olderReply'      => __( '%d older reply available', 'yoohw-support-portal' ),
					/* translators: %d: Number of hidden older replies. */
					'olderReplies'    => __( '%d older replies available', 'yoohw-support-portal' ),
					/* translators: 1: Visible reply count, 2: Total reply count. */
					'replyProgress'   => __( 'Showing %1$d of %2$d replies', 'yoohw-support-portal' ),
					/* translators: %d: Number of older replies revealed by the next click. */
					'showOlderReply'  => __( 'Show %d older reply', 'yoohw-support-portal' ),
					/* translators: %d: Number of older replies revealed by the next click. */
					'showOlderReplies' => __( 'Show %d older replies', 'yoohw-support-portal' ),
					'revealingReplies' => __( 'Showing older replies…', 'yoohw-support-portal' ),
					/* translators: %d: Total reply count. */
					'allRepliesShown' => __( 'All %d replies are now shown.', 'yoohw-support-portal' ),
					'passwordStrengthEmpty'    => __( 'Password strength', 'yoohw-support-portal' ),
					'passwordStrengthShort'    => __( 'Very weak', 'yoohw-support-portal' ),
					'passwordStrengthBad'      => __( 'Weak', 'yoohw-support-portal' ),
					'passwordStrengthGood'     => __( 'Medium', 'yoohw-support-portal' ),
					'passwordStrengthStrong'   => __( 'Strong', 'yoohw-support-portal' ),
					'passwordStrengthMismatch' => __( 'Mismatch', 'yoohw-support-portal' ),
				],
			]
		);

		if ( class_exists( 'YoOhw_Support_Router' ) && YoOhw_Support_Router::uses_editor() ) {
			self::enqueue_editor_assets();
		}
	}

	public static function enqueue_editor_assets(): void {
		wp_enqueue_editor();
		wp_add_inline_style( 'editor-buttons', '.yoohw-composer-code-tool .mce-ico{transform:translateY(2px)}' );
		$js_path = YOOHW_SUPPORT_PORTAL_PATH . 'assets/js/editor.js';

		wp_enqueue_script(
			'yoohw-support-editor',
			YOOHW_SUPPORT_PORTAL_URL . 'assets/js/editor.js',
			[],
			file_exists( $js_path ) ? (string) filemtime( $js_path ) : self::VERSION,
			true
		);
		wp_localize_script( 'yoohw-support-editor', 'YoOhwComposerLabels', [
			'inlineCode' => __( 'Inline code', 'yoohw-support-portal' ),
			'codeBlock' => __( 'Code block', 'yoohw-support-portal' ),
			'removedMedia' => __( 'Pasted images or embedded media were removed. Use Attachments where available.', 'yoohw-support-portal' ),
		] );
	}

	public static function isolate_support_assets(): void {
		if ( ! class_exists( 'YoOhw_Support_Router' ) || ! YoOhw_Support_Router::is_plugin_ui_request() ) {
			return;
		}

		self::dequeue_theme_assets( 'style' );
		self::dequeue_theme_assets( 'script' );

		foreach ( [ 'global-styles', 'classic-theme-styles', 'wp-block-library-theme' ] as $handle ) {
			wp_dequeue_style( $handle );
		}
	}

	private static function dequeue_theme_assets( string $type ): void {
		$registry = 'style' === $type ? wp_styles() : wp_scripts();

		if ( ! $registry || empty( $registry->registered ) ) {
			return;
		}

		$theme_urls = array_filter(
			array_unique(
				[
					self::normalize_asset_base( get_stylesheet_directory_uri() ),
					self::normalize_asset_base( get_template_directory_uri() ),
				]
			)
		);

		foreach ( array_keys( $registry->registered ) as $handle ) {
			$src = $registry->registered[ $handle ]->src ?? '';

			if ( ! is_string( $src ) || '' === $src ) {
				continue;
			}

			$src = self::absolute_asset_url( $src );

			foreach ( $theme_urls as $theme_url ) {
				if ( 0 !== strpos( $src, $theme_url ) ) {
					continue;
				}

				if ( 'style' === $type ) {
					wp_dequeue_style( $handle );
				} else {
					wp_dequeue_script( $handle );
				}

				break;
			}
		}
	}

	private static function normalize_asset_base( string $url ): string {
		return trailingslashit( set_url_scheme( $url ) );
	}

	private static function absolute_asset_url( string $src ): string {
		if ( 0 === strpos( $src, '//' ) ) {
			return set_url_scheme( $src );
		}

		if ( preg_match( '#^[a-z][a-z0-9+.-]*:#i', $src ) ) {
			return set_url_scheme( $src );
		}

		return set_url_scheme( home_url( '/' . ltrim( $src, '/' ) ) );
	}
}
