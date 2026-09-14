<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class YoOhw_Support_Router {

	const QUERY_VAR_VIEW  = 'yoohw_support_view';
	const QUERY_VAR_TOPIC = 'yoohw_support_topic';
	const QUERY_VAR_CUSTOMER = 'yoohw_support_customer';
	const ROUTES_VERSION  = '20260727-10';

	public static function init(): void {
		if ( ! self::portal_mode_enabled() ) {
			return;
		}

		add_action( 'init', [ __CLASS__, 'register_rewrite_rules' ], 5 );
		add_action( 'init', [ __CLASS__, 'maybe_flush_rewrite_rules' ], 20 );
		add_filter( 'query_vars', [ __CLASS__, 'register_query_vars' ] );
		add_action( 'template_redirect', [ __CLASS__, 'redirect_legacy_routes' ], 1 );
		add_action( 'template_redirect', [ __CLASS__, 'handle_access' ], 2 );
		add_filter( 'template_include', [ __CLASS__, 'template_include' ] );
		add_filter( 'document_title_parts', [ __CLASS__, 'document_title_parts' ] );
		add_filter( 'comment_post_redirect', [ __CLASS__, 'comment_post_redirect' ], 10, 2 );
	}

	public static function activate(): void {
		if ( ! self::portal_mode_enabled() ) {
			flush_rewrite_rules( false );
			delete_option( 'yoohw_support_routes_version' );
			return;
		}

		self::register_rewrite_rules();
		flush_rewrite_rules( false );
		update_option( 'yoohw_support_routes_version', self::ROUTES_VERSION, false );
	}

	public static function deactivate(): void {
		flush_rewrite_rules( false );
	}

	public static function register_rewrite_rules(): void {
		if ( ! self::portal_mode_enabled() ) {
			return;
		}

		$topic_key_pattern = self::uses_isolated_routes() ? '(?!new/?$)([^/]+)' : '([^/]+)';

		add_rewrite_rule(
			'^' . preg_quote( self::topic_path_base(), '/' ) . '/' . $topic_key_pattern . '/?$',
			'index.php?' . self::QUERY_VAR_VIEW . '=topic&' . self::QUERY_VAR_TOPIC . '=$matches[1]',
			'top'
		);

		if ( self::uses_isolated_routes() ) {
			add_rewrite_rule(
				'^support/customers/([0-9]+)/?$',
				'index.php?' . self::QUERY_VAR_VIEW . '=customer&' . self::QUERY_VAR_CUSTOMER . '=$matches[1]',
				'top'
			);
		}

		foreach ( self::route_paths() as $view => $path ) {
			$view = sanitize_key( $view );
			$path = self::normalize_route_path( (string) $path );

			if ( '' === $view ) {
				continue;
			}

			self::add_view_rewrite_rule( $path, $view );
		}
	}

	public static function maybe_flush_rewrite_rules(): void {
		if ( ! self::portal_mode_enabled() ) {
			return;
		}

		if ( get_option( 'yoohw_support_routes_version' ) === self::ROUTES_VERSION ) {
			return;
		}

		flush_rewrite_rules( false );
		update_option( 'yoohw_support_routes_version', self::ROUTES_VERSION, false );
	}

	public static function register_query_vars( array $vars ): array {
		$vars[] = self::QUERY_VAR_VIEW;
		$vars[] = self::QUERY_VAR_TOPIC;
		$vars[] = self::QUERY_VAR_CUSTOMER;

		return $vars;
	}

	public static function is_support_request(): bool {
		if ( ! self::portal_mode_enabled() ) {
			return false;
		}

		return '' !== self::get_support_view() || '' !== self::get_path_view() || self::is_author_request();
	}

	public static function is_author_request(): bool {
		return ! self::uses_isolated_routes() && ! is_admin() && is_author();
	}

	public static function is_plugin_ui_request(): bool {
		if ( ! self::portal_mode_enabled() ) {
			return false;
		}

		return self::is_support_request() || self::is_content_page_request() || self::is_not_found_request();
	}

	public static function get_view(): string {
		if ( ! self::portal_mode_enabled() ) {
			return '';
		}

		$view = self::get_support_view();

		if ( '' === $view ) {
			$view = self::get_path_view();
		}

		if ( '' === $view && self::is_author_request() ) {
			return 'author';
		}

		if ( '' === $view && self::is_content_page_request() ) {
			return 'page';
		}

		if ( '' === $view && self::is_not_found_request() ) {
			return 'not-found';
		}

		return $view;
	}

	public static function is_not_found_request(): bool {
		if ( is_admin() || is_feed() || is_embed() || ! is_404() ) {
			return false;
		}

		if ( ! class_exists( 'YoOhw_Support_Settings' ) || ! YoOhw_Support_Settings::page_ui_enabled() ) {
			return false;
		}

		return ! self::is_workspace_route_path( self::current_request_path() );
	}

	private static function get_support_view(): string {
		$view = get_query_var( self::QUERY_VAR_VIEW );

		return is_string( $view ) ? sanitize_key( $view ) : '';
	}

	private static function get_path_view(): string {
		if ( is_admin() || is_feed() || is_embed() || is_search() ) {
			return '';
		}

		$path = self::current_request_path();

		foreach ( self::route_paths() as $view => $route_path ) {
			$view       = sanitize_key( $view );
			$route_path = self::normalize_route_path( (string) $route_path );

			if ( '' !== $view && $path === $route_path ) {
				return $view;
			}
		}

		if ( self::is_topic_route_path( $path ) ) {
			return 'topic';
		}

		return '';
	}

	public static function is_content_page_request(): bool {
		if ( is_admin() || is_feed() || is_embed() || is_search() || is_404() || ! is_page() ) {
			return false;
		}

		if ( class_exists( 'YoOhw_Support_Settings' ) && ! YoOhw_Support_Settings::page_ui_enabled() ) {
			return false;
		}

		return ! self::is_legacy_page_request();
	}

	private static function is_legacy_page_request(): bool {
		if ( is_front_page() ) {
			return true;
		}

		foreach ( self::legacy_page_slugs() as $slug ) {
			if ( is_page( $slug ) ) {
				return true;
			}
		}

		return false;
	}

	private static function legacy_page_slugs(): array {
		$slugs = [
			'topics',
			'posts',
			'add-new',
			'login',
			'lost-password',
			'reset-password',
			'topic-published',
		];

		return apply_filters( 'yoohw_support_legacy_page_slugs', $slugs );
	}

	public static function get_topic_key(): string {
		$topic = get_query_var( self::QUERY_VAR_TOPIC );

		if ( is_string( $topic ) && '' !== $topic ) {
			return sanitize_text_field( $topic );
		}

		return self::topic_key_from_request_path();
	}

	public static function get_customer_id(): int {
		return absint( get_query_var( self::QUERY_VAR_CUSTOMER ) );
	}

	public static function uses_editor(): bool {
		return in_array( self::get_view(), [ 'new', 'topic' ], true );
	}

	public static function is_auth_route(): bool {
		return in_array( self::get_view(), [ 'login', 'lost-password', 'reset-password' ], true );
	}

	public static function is_status_route(): bool {
		return in_array( self::get_view(), self::status_views(), true );
	}

	public static function status_views(): array {
		$views = apply_filters( 'yoohw_support_status_views', [ 'topic-published' ] );

		if ( ! is_array( $views ) ) {
			return [ 'topic-published' ];
		}

		return array_values( array_unique( array_filter( array_map( 'sanitize_key', $views ) ) ) );
	}

	public static function handle_access(): void {
		if ( ! self::portal_mode_enabled() ) {
			return;
		}

		if ( ! self::is_support_request() ) {
			return;
		}

		if ( self::is_auth_route() ) {
			return;
		}

		if ( ! is_user_logged_in() ) {
			wp_safe_redirect(
				self::url(
					'login',
					[
						'redirect_to' => self::current_url(),
					]
				)
			);
			exit;
		}

		if ( self::is_author_request() && ! current_user_can( YoOhw_Support_Capabilities::VIEW_CUSTOMERS ) ) {
			wp_safe_redirect( self::url( 'dashboard' ) );
			exit;
		}

		if ( 'customer' === self::get_view() && ! current_user_can( YoOhw_Support_Capabilities::VIEW_CUSTOMERS ) ) {
			wp_safe_redirect( self::url( 'dashboard' ) );
			exit;
		}
	}

	public static function redirect_legacy_routes(): void {
		if ( ! self::portal_mode_enabled() ) {
			return;
		}

		if ( self::uses_isolated_routes() ) {
			return;
		}

		if ( is_admin() || self::is_support_request() ) {
			return;
		}

		$redirect = '';
		$path     = self::current_request_path();

		if ( self::is_workspace_route_path( $path ) ) {
			return;
		}

		$legacy_paths = [
			'support'         => 'dashboard',
			'support/topics'  => 'topics',
			'support/posts'   => 'topics',
			'support/new'     => 'new',
			'support/add-new' => 'new',
			'support/login'   => 'login',
			'support/lost-password' => 'lost-password',
			'support/reset-password' => 'reset-password',
			'support/topic-published' => 'topic-published',
			'posts'           => 'topics',
			'add-new'         => 'new',
		];
		$legacy_paths = apply_filters( 'yoohw_support_legacy_route_redirects', $legacy_paths );

		if ( self::is_legacy_topic_path( $path ) ) {
			$redirect = self::topic_url_by_key( basename( $path ) );
		}

		if ( isset( $legacy_paths[ $path ] ) ) {
			$redirect = self::url( $legacy_paths[ $path ] );
		}

		if ( ! $redirect && is_singular( 'post' ) ) {
			$redirect = self::topic_url( get_queried_object_id() );
		} elseif ( is_home() || is_page( 'posts' ) ) {
			$redirect = self::url( 'topics' );
		} elseif ( is_page( 'add-new' ) ) {
			$redirect = self::url( 'new' );
		} elseif ( is_page( 'login' ) ) {
			$redirect = self::url( 'login' );
		} elseif ( is_page( 'lost-password' ) ) {
			$redirect = self::url( 'lost-password' );
		} elseif ( is_page( 'reset-password' ) ) {
			$redirect = self::url( 'reset-password' );
		} elseif ( is_page( 'topic-published' ) ) {
			$redirect = self::url( 'topic-published' );
		}

		if ( ! $redirect ) {
			foreach ( $legacy_paths as $slug => $view ) {
				if ( is_page( $slug ) ) {
					$redirect = self::url( $view );
					break;
				}
			}
		}

		if ( $redirect && ! self::is_current_path_url( $redirect ) ) {
			wp_safe_redirect( $redirect, 301 );
			exit;
		}
	}

	public static function template_include( string $template ): string {
		if ( ! self::portal_mode_enabled() ) {
			return $template;
		}

		if ( ! self::is_plugin_ui_request() ) {
			return $template;
		}

		self::clear_plugin_route_404();

		$plugin_template = YOOHW_SUPPORT_PORTAL_PATH . 'templates/layout.php';

		return file_exists( $plugin_template ) ? $plugin_template : $template;
	}

	public static function document_title_parts( array $parts ): array {
		if ( ! self::is_plugin_ui_request() || ! class_exists( 'YoOhw_Support_Controller' ) ) {
			return $parts;
		}

		$parts['title'] = YoOhw_Support_Controller::page_title( self::get_view() );

		return $parts;
	}

	public static function comment_post_redirect( string $location, $comment ): string {
		$referer = wp_get_referer();

		if ( ! $referer || ! $comment instanceof WP_Comment || ! self::is_topic_route_path( self::relative_url_path( $referer ) ) ) {
			return $location;
		}

		return self::topic_url( (int) $comment->comment_post_ID ) . '#comment-' . (int) $comment->comment_ID;
	}

	public static function url( string $view = 'dashboard', array $args = [] ): string {
		$paths = self::route_paths();
		$path  = self::normalize_route_path( (string) ( $paths[ $view ] ?? $paths['dashboard'] ) );
		$url   = home_url( '' === $path ? '/' : '/' . $path . '/' );

		return $args ? add_query_arg( $args, $url ) : $url;
	}

	public static function route_paths(): array {
		$paths = [
			'dashboard'      => '',
			'topics'         => 'topics',
			'new'            => 'new',
			'login'          => 'login',
			'lost-password'  => 'lost-password',
			'reset-password' => 'reset-password',
			'topic-published' => 'topic-published',
		];

		$paths = apply_filters( 'yoohw_support_route_paths', $paths );

		if ( ! self::uses_isolated_routes() ) {
			return $paths;
		}

		$isolated = [
			'dashboard'      => 'support',
			'topics'         => 'support/topics',
			'new'            => 'support/topics/new',
			'login'          => 'support/login',
			'lost-password'  => 'support/lost-password',
			'reset-password' => 'support/reset-password',
		];

		foreach ( $paths as $view => $path ) {
			if ( isset( $isolated[ $view ] ) || 'topic-published' === $view ) {
				continue;
			}

			$isolated[ $view ] = 'support/' . self::normalize_route_path( (string) $path );
		}

		return $isolated;
	}

	public static function topic_url( $post ): string {
		if (
			class_exists( 'YoOhw_Support_Settings' ) &&
			YoOhw_Support_Settings::uses_isolated_storage() &&
			is_array( $post ) &&
			! empty( $post['public_key'] )
		) {
			return self::topic_url_by_key( (string) $post['public_key'] );
		}

		$post = $post instanceof WP_Post ? $post : get_post( $post );

		if ( ! $post ) {
			return self::url( 'topics' );
		}

		return self::topic_url_by_key( (string) (int) $post->ID );
	}

	public static function topic_url_by_key( string $topic_key ): string {
		$topic_key = trim( sanitize_text_field( $topic_key ), '/' );

		if ( '' === $topic_key ) {
			return self::url( 'topics' );
		}

		return home_url( '/' . self::topic_path_base() . '/' . rawurlencode( $topic_key ) . '/' );
	}

	public static function current_url(): string {
		$scheme = wp_parse_url( home_url(), PHP_URL_SCHEME );
		$scheme = $scheme ? $scheme : ( is_ssl() ? 'https' : 'http' );
		$host   = isset( $_SERVER['HTTP_HOST'] ) ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_HOST'] ) ) : wp_parse_url( home_url(), PHP_URL_HOST );
		$uri    = isset( $_SERVER['REQUEST_URI'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REQUEST_URI'] ) ) : '/';

		return esc_url_raw( $scheme . '://' . $host . $uri );
	}

	private static function add_view_rewrite_rule( string $path, string $view ): void {
		$regex = '' === $path ? '^$' : '^' . preg_quote( $path, '/' ) . '/?$';

		add_rewrite_rule( $regex, 'index.php?' . self::QUERY_VAR_VIEW . '=' . $view, 'top' );
	}

	private static function topic_path_base(): string {
		if ( self::uses_isolated_routes() ) {
			return 'support/topics';
		}

		$base = apply_filters( 'yoohw_support_topic_route_base', 'topic' );
		$base = self::normalize_route_path( (string) $base );

		return '' !== $base ? $base : 'topic';
	}

	private static function portal_mode_enabled(): bool {
		if ( ! class_exists( 'YoOhw_Support_Settings' ) ) {
			return true;
		}

		return YoOhw_Support_Settings::dedicated_portal_mode_enabled();
	}

	private static function uses_isolated_routes(): bool {
		return class_exists( 'YoOhw_Support_Settings' ) && YoOhw_Support_Settings::uses_isolated_storage();
	}

	private static function normalize_route_path( string $path ): string {
		$path = trim( wp_strip_all_tags( $path ) );
		$path = preg_replace( '#/+#', '/', $path );

		return trim( (string) $path, '/' );
	}

	private static function current_request_path(): string {
		$path = isset( $_SERVER['REQUEST_URI'] ) ? wp_parse_url( sanitize_text_field( wp_unslash( $_SERVER['REQUEST_URI'] ) ), PHP_URL_PATH ) : '';

		return is_string( $path ) ? self::relative_path( $path ) : '';
	}

	private static function relative_url_path( string $url ): string {
		$path = wp_parse_url( $url, PHP_URL_PATH );

		return is_string( $path ) ? self::relative_path( $path ) : '';
	}

	private static function relative_path( string $path ): string {
		$path      = trim( $path, '/' );
		$home_path = wp_parse_url( home_url( '/' ), PHP_URL_PATH );
		$home_path = is_string( $home_path ) ? trim( $home_path, '/' ) : '';

		if ( '' !== $home_path && ( $path === $home_path || 0 === strpos( $path, $home_path . '/' ) ) ) {
			$path = trim( substr( $path, strlen( $home_path ) ), '/' );
		}

		return $path;
	}

	private static function is_topic_route_path( string $path ): bool {
		$path       = trim( $path, '/' );
		$topic_base = preg_quote( self::topic_path_base(), '#' );

		return (bool) preg_match( '#^' . $topic_base . '/[^/]+/?$#', $path );
	}

	private static function is_legacy_topic_path( string $path ): bool {
		$path       = trim( $path, '/' );
		$topic_base = preg_quote( self::topic_path_base(), '#' );

		return (bool) preg_match( '#^support/' . $topic_base . '/[^/]+/?$#', $path );
	}

	private static function is_workspace_route_path( string $path ): bool {
		if ( ! class_exists( '\YoOhwWorkspace\Settings' ) ) {
			return false;
		}

		$workspace_base = self::normalize_route_path( (string) \YoOhwWorkspace\Settings::base_path() );

		return '' !== $workspace_base && ( $path === $workspace_base || 0 === strpos( $path, $workspace_base . '/' ) );
	}

	private static function is_current_path_url( string $url ): bool {
		return self::current_request_path() === self::relative_url_path( $url );
	}

	private static function clear_plugin_route_404(): void {
		if ( in_array( self::get_view(), [ 'topic', 'not-found' ], true ) ) {
			if ( 'not-found' === self::get_view() ) {
				status_header( 404 );
			}

			return;
		}

		global $wp_query;

		if ( $wp_query instanceof WP_Query ) {
			$wp_query->is_404 = false;
		}

		status_header( 200 );
	}

	private static function topic_key_from_request_path(): string {
		$path       = trim( self::current_request_path(), '/' );
		$topic_base = self::topic_path_base();

		if ( '' === $path || 0 !== strpos( $path, $topic_base . '/' ) ) {
			return '';
		}

		$key = substr( $path, strlen( $topic_base ) + 1 );

		return sanitize_text_field( trim( (string) $key, '/' ) );
	}
}
