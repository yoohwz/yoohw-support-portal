<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Protect support conversations from WordPress core REST endpoints.
 *
 * Support topics use the built-in post and comment data models. Core REST
 * controllers do not know about the portal's topic visibility rules, and the
 * core comments controller does not execute the preprocess_comment and
 * comment_post workflow used by this plugin. Keep reads limited to support
 * administrators and require integrations to use a dedicated write service.
 */
final class YoOhw_Support_REST_Security {

	/**
	 * Core REST route prefixes that can expose support data or identities.
	 *
	 * @var string[]
	 */
	private const PRIVATE_ROUTE_PREFIXES = [
		'/wp/v2/posts',
		'/wp/v2/comments',
		'/wp/v2/media',
		'/wp/v2/search',
		'/wp/v2/users',
	];

	public static function init(): void {
		add_filter( 'rest_pre_dispatch', [ __CLASS__, 'protect_core_routes' ], 10, 3 );
	}

	/**
	 * Require an administrator for support-data reads and block core comment writes.
	 *
	 * @param mixed           $result  Response to replace the requested version with.
	 * @param WP_REST_Server  $server  REST server instance.
	 * @param WP_REST_Request $request Current REST request.
	 * @return mixed|WP_Error
	 */
	public static function protect_core_routes( $result, $server, $request ) {
		if ( ! $request instanceof WP_REST_Request ) {
			return $result;
		}

		$route = $request->get_route();

		if ( ! self::is_private_route( $route ) ) {
			return $result;
		}

		$method = strtoupper( $request->get_method() );

		// Route schemas contain no conversation data and are safe to discover.
		if ( 'OPTIONS' === $method ) {
			return $result;
		}

		if ( self::is_core_comment_route( $route ) && ! in_array( $method, [ 'GET', 'HEAD' ], true ) ) {
			return new WP_Error(
				'yoohw_support_core_rest_comment_write_disabled',
				__( 'Support replies cannot be changed through the WordPress core REST comments endpoint.', 'yoohw-support-portal' ),
				[ 'status' => 403 ]
			);
		}

		if ( current_user_can( YoOhw_Support_Capabilities::VIEW_ALL_TOPICS ) ) {
			return $result;
		}

		if ( ! is_user_logged_in() ) {
			return new WP_Error(
				'yoohw_support_rest_authentication_required',
				__( 'Authentication is required to access support data.', 'yoohw-support-portal' ),
				[ 'status' => rest_authorization_required_code() ]
			);
		}

		return new WP_Error(
			'yoohw_support_rest_forbidden',
			__( 'You are not allowed to access support data through the REST API.', 'yoohw-support-portal' ),
			[ 'status' => 403 ]
		);
	}

	private static function is_private_route( string $route ): bool {
		foreach ( self::PRIVATE_ROUTE_PREFIXES as $prefix ) {
			if ( $route === $prefix || 0 === strpos( $route, $prefix . '/' ) ) {
				return true;
			}
		}

		return false;
	}

	private static function is_core_comment_route( string $route ): bool {
		return '/wp/v2/comments' === $route || 0 === strpos( $route, '/wp/v2/comments/' );
	}
}
