<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Theme-hosted entry points; portal templates and submission handlers stay shared. */
class YoOhw_Support_Shortcodes {

	const TAGS = [ 'yoohw_post_form', 'yoohw_login_form', 'yoohw_lost_password_form', 'yoohw_support_topics' ];

	private static $rendered = [];

	public static function init(): void {
		foreach ( self::TAGS as $tag ) {
			add_shortcode( $tag, [ __CLASS__, 'render' ] );
		}
		add_action( 'wp', [ __CLASS__, 'prepare_page' ] );
		add_action( 'wp_enqueue_scripts', [ __CLASS__, 'enqueue_page_assets' ] );
	}

	private static function page_has_shortcode(): bool {
		if ( ! is_singular() ) {
			return false;
		}

		$post = get_queried_object();
		if ( ! $post instanceof WP_Post ) {
			return false;
		}

		foreach ( self::TAGS as $tag ) {
			if ( has_shortcode( $post->post_content, $tag ) ) {
				return true;
			}
		}
		return false;
	}

	public static function prepare_page(): void {
		if ( self::page_has_shortcode() ) {
			self::prevent_caching();
		}
	}

	private static function prevent_caching(): void {
		if ( ! defined( 'DONOTCACHEPAGE' ) ) {
			define( 'DONOTCACHEPAGE', true );
		}
		if ( ! headers_sent() ) {
			nocache_headers();
		}
	}

	public static function enqueue_page_assets(): void {
		if ( self::page_has_shortcode() ) {
			YoOhw_Support_Assets::enqueue_support_assets();
		}
	}

	public static function render( $attributes = [], $content = null, string $tag = '' ): string {
		// Never expose account-specific fragments through feeds or repeat fixed form/editor IDs.
		if ( is_feed() || ! in_array( $tag, self::TAGS, true ) || isset( self::$rendered[ $tag ] ) ) {
			return '';
		}
		self::$rendered[ $tag ] = true;
		self::prevent_caching();
		YoOhw_Support_Assets::enqueue_support_assets();

		if ( 'yoohw_support_topics' === $tag ) {
			$html = is_user_logged_in() ? YoOhw_Support_Controller::render_topics( true ) : self::login_notice();
		} elseif ( 'yoohw_post_form' === $tag ) {
			$html = YoOhw_Support_Controller::render_topic_form();
		} elseif ( 'yoohw_login_form' === $tag ) {
			$html = YoOhw_Support_Controller::render_login_form();
		} else {
			$html = YoOhw_Support_Controller::render_lost_password_form();
		}

		// Builders may render after wp_head; print an enqueued, not-yet-printed stylesheet once.
		ob_start();
		if ( did_action( 'wp_head' ) && ! wp_style_is( 'yoohw-support-portal-ui', 'done' ) ) {
			wp_print_styles( 'yoohw-support-portal-ui' );
		}
		$styles = ob_get_clean();

		return $styles . '<div class="yoohw-support yoohw-support-embed">' . $html . '</div>';
	}

	private static function login_notice(): string {
		return '<p class="yoohw-notice">' . esc_html__( 'Log in to view your support topics.', 'yoohw-support-portal' ) . ' <a href="' . esc_url( YoOhw_Support_Router::url( 'login', [ 'redirect_to' => get_permalink( get_queried_object_id() ) ] ) ) . '">' . esc_html__( 'Log in', 'yoohw-support-portal' ) . '</a></p>';
	}
}
