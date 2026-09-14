<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class YoOhw_Support_Controller {

	private static $auth_error = '';
	private static $auth_message = '';
	private static $register_error = '';
	private static $register_message = '';
	private static $reset_error = '';

	public static function render_current(): void {
		echo self::render_view( YoOhw_Support_Router::get_view() ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	}

	public static function render_view( string $view ): string {
		if ( in_array( $view, YoOhw_Support_Router::status_views(), true ) ) {
			return self::render_status( $view );
		}

		switch ( $view ) {
			case 'topics':
				return self::render_topics();

			case 'new':
				return self::render_new_topic();

			case 'topic':
				return self::render_topic_single();

			case 'author':
				return self::render_author_profile();

			case 'customer':
				return self::render_isolated_customer_profile();

			case 'page':
				return self::render_page();

			case 'not-found':
				return self::render_not_found();

			case 'login':
				return self::render_auth_screen( 'login' );

			case 'lost-password':
				return self::render_auth_screen( 'lost-password' );

			case 'reset-password':
				return self::render_auth_screen( 'reset-password' );

			case 'dashboard':
			default:
				return self::render_dashboard();
		}
	}

	public static function page_title( string $view ): string {
		if ( 'author' === $view ) {
			$author = get_queried_object();

			if ( $author instanceof WP_User ) {
				return $author->display_name;
			}
		}

		if ( 'page' === $view ) {
			$page = get_queried_object();

			if ( $page instanceof WP_Post ) {
				return get_the_title( $page );
			}
		}

		$titles = [
			'dashboard'      => __( 'Support Portal', 'yoohw-support-portal' ),
			'topics'         => __( 'My topics', 'yoohw-support-portal' ),
			'new'            => __( 'Create topic', 'yoohw-support-portal' ),
			'topic'          => __( 'Topic', 'yoohw-support-portal' ),
			'author'         => __( 'Author profile', 'yoohw-support-portal' ),
			'customer'       => __( 'Customer profile', 'yoohw-support-portal' ),
			'page'           => __( 'Page', 'yoohw-support-portal' ),
			'login'          => __( 'Log in', 'yoohw-support-portal' ),
			'lost-password'  => __( 'Lost password', 'yoohw-support-portal' ),
			'reset-password' => __( 'Set password', 'yoohw-support-portal' ),
			'topic-published' => __( 'Topic published', 'yoohw-support-portal' ),
			'not-found'      => __( 'Page Not Found', 'yoohw-support-portal' ),
		];

		$titles = apply_filters( 'yoohw_support_page_titles', $titles );

		return $titles[ $view ] ?? $titles['dashboard'];
	}

	public static function page_kicker( string $view ): string {
		if ( 'page' === $view ) {
			return get_bloginfo( 'name' ) ?: __( 'Page', 'yoohw-support-portal' );
		}

		if ( 'author' === $view ) {
			return __( 'Author', 'yoohw-support-portal' );
		}

		if ( 'not-found' === $view ) {
			return __( 'Error 404', 'yoohw-support-portal' );
		}

		return __( 'Support portal', 'yoohw-support-portal' );
	}

	public static function nav_items(): array {
		$items = [
			[
				'view'  => 'dashboard',
				'label' => __( 'Homepage', 'yoohw-support-portal' ),
				'url'   => YoOhw_Support_Router::url( 'dashboard' ),
				'icon'  => 'house',
			],
			[
				'view'  => 'topics',
				'label' => __( 'My topics', 'yoohw-support-portal' ),
				'url'   => YoOhw_Support_Router::url( 'topics' ),
				'icon'  => 'message-square',
			],
			[
				'view'  => 'new',
				'label' => __( 'New topic', 'yoohw-support-portal' ),
				'url'   => YoOhw_Support_Router::url( 'new' ),
				'icon'  => 'square-pen',
			],
		];

		if ( is_user_logged_in() ) {
			$items[] = [
				'view'  => 'logout',
				'label' => __( 'Log out', 'yoohw-support-portal' ),
				'url'   => wp_logout_url( YoOhw_Support_Router::url( 'login' ) ),
				'icon'  => 'log-out',
			];
		} else {
			$items[] = [
				'view'  => 'login',
				'label' => __( 'Log in', 'yoohw-support-portal' ),
				'url'   => YoOhw_Support_Router::url( 'login' ),
				'icon'  => 'log-in',
			];
		}

		return $items;
	}

	public static function render_template( string $template, array $context = [] ): string {
		$file = YOOHW_SUPPORT_PORTAL_PATH . 'templates/' . $template . '.php';

		if ( ! file_exists( $file ) ) {
			return '';
		}

		ob_start();
		extract( $context, EXTR_SKIP ); // phpcs:ignore WordPress.PHP.DontExtract.extract_extract
		include $file;
		return ob_get_clean();
	}

	public static function render_dashboard(): string {
		$docs_url = YoOhw_Support_Settings::get_url( 'docs_url' );
		$cards    = [];

		if ( '' !== $docs_url ) {
			$cards[] = [
				'icon'        => 'book-open',
				'title'       => __( 'Documents', 'yoohw-support-portal' ),
				'description' => __( 'Read product guides and implementation notes.', 'yoohw-support-portal' ),
				'url'         => $docs_url,
				'external'    => true,
			];
		}

		$cards[] = [
			'icon'        => 'message-square',
			'title'       => __( 'My topics', 'yoohw-support-portal' ),
			'description' => __( 'Review active, waiting, and resolved conversations.', 'yoohw-support-portal' ),
			'url'         => YoOhw_Support_Router::url( 'topics' ),
		];

		$cards[] = [
			'icon'        => 'square-pen',
			'title'       => __( 'Add new', 'yoohw-support-portal' ),
			'description' => __( 'Create a support topic with attachments and context.', 'yoohw-support-portal' ),
			'url'         => YoOhw_Support_Router::url( 'new' ),
		];

		return self::render_template(
			'dashboard',
			[
				'cards'     => $cards,
				'workspace' => self::workspace_dashboard_section(),
			]
		);
	}

	private static function workspace_dashboard_section(): array {
		if ( ! class_exists( '\YoOhwWorkspace\Router' ) ) {
			return [];
		}

		$workspace = [
			'url'          => \YoOhwWorkspace\Router::url(),
			'new_url'      => \YoOhwWorkspace\Router::url( 'project/new/' ),
			'projects_url' => \YoOhwWorkspace\Router::url( 'projects/' ),
			'counts'       => [],
			'site_counts'  => [],
		];

		if ( is_user_logged_in() && class_exists( '\YoOhwWorkspace\Project\Project_Query' ) ) {
			$workspace['counts']      = \YoOhwWorkspace\Project\Project_Query::counts_for_current_user();
			$workspace['site_counts'] = \YoOhwWorkspace\Project\Project_Query::counts_for_site();
		}

		return $workspace;
	}

	public static function render_topics(): string {
		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- Read-only filters used to render the topic list; no state is changed.
		$paged       = max( 1, absint( $_GET['support_page'] ?? 1 ) );
		$search      = isset( $_GET['s'] ) ? sanitize_text_field( wp_unslash( $_GET['s'] ) ) : '';
		$category_id = isset( $_GET['category'] ) ? absint( $_GET['category'] ) : 0;
		$status      = isset( $_GET['status'] ) ? sanitize_key( wp_unslash( $_GET['status'] ) ) : '';
		// phpcs:enable WordPress.Security.NonceVerification.Recommended
		$statuses    = self::public_topic_statuses();
		$user_id     = get_current_user_id();
		$user_codes  = YoOhw_Shortcode::get_user_access_codes( $user_id );
		$categories  = self::get_accessible_category_filter_options( $user_codes, current_user_can( YoOhw_Support_Capabilities::MANAGE_CATEGORIES ), $user_id );
		$category_ids = array_filter(
			array_map(
				static function ( array $category ): int {
					$term = $category['term'] ?? null;

					return $term instanceof WP_Term ? absint( $term->term_id ) : 0;
				},
				$categories
			)
		);

		if ( $category_id && ! in_array( $category_id, $category_ids, true ) ) {
			$category_id = 0;
		}

		if ( $status && in_array( $status, $statuses, true ) ) {
			$post_status = [ $status ];
		} else {
			$post_status = $statuses;
		}

		if ( YoOhw_Support_Settings::uses_isolated_storage() ) {
			$isolated_status = '';

			if ( 'publish' === $status ) {
				$isolated_status = 'open';
			} elseif ( 'resolved' === $status ) {
				$isolated_status = 'resolved';
			}

			$result = YoOhw_Support_Database::query_topics(
				[
					'page'        => $paged,
					'per_page'    => 8,
					'search'      => $search,
					'category_id' => $category_id,
					'status'      => $isolated_status,
					'author_ids'  => current_user_can( YoOhw_Support_Capabilities::VIEW_ALL_TOPICS ) ? [] : self::allowed_author_ids_for_current_user(),
				]
			);

			return self::render_template(
				'topics-isolated',
				[
					'topics'      => $result['items'],
					'max_pages'   => $result['max_pages'],
					'search'      => $search,
					'category_id' => $category_id,
					'status'      => $status,
					'categories'  => $categories,
					'paged'       => $paged,
				]
			);
		}

		$args = [
			'post_type'           => 'post',
			'post_status'         => $post_status,
			'posts_per_page'      => 8,
			'paged'               => $paged,
			's'                   => $search,
			'ignore_sticky_posts' => true,
		];

		if ( $category_id ) {
			$args['cat'] = $category_id;
		}

		if ( ! current_user_can( YoOhw_Support_Capabilities::VIEW_ALL_TOPICS ) ) {
			$args['author__in'] = self::allowed_author_ids_for_current_user();
		}

		$query = new WP_Query( $args );

		return self::render_template(
			'topics',
			[
				'query'       => $query,
				'search'      => $search,
				'category_id' => $category_id,
				'status'      => $status,
				'categories'  => $categories,
				'paged'       => $paged,
			]
		);
	}

	public static function render_new_topic(): string {
		return self::render_template(
			'topic-new',
			[
				'form' => self::render_topic_form(),
			]
		);
	}

	public static function render_topic_single(): string {
		$topic_key = YoOhw_Support_Router::get_topic_key();

		if ( YoOhw_Support_Settings::uses_isolated_storage() ) {
			$topic = YoOhw_Support_Database::get_topic( $topic_key );

			if ( ! $topic || ! self::current_user_can_view_isolated_topic( $topic ) ) {
				status_header( 404 );
				return self::render_template(
					'status',
					[
						'icon'        => 'circle-alert',
						'kicker'      => __( 'Topic unavailable', 'yoohw-support-portal' ),
						'title'       => __( 'We could not find that topic.', 'yoohw-support-portal' ),
						'description' => __( 'The topic may have been removed, or you may not have access to view it.', 'yoohw-support-portal' ),
						'actions'     => [
							[
								'label' => __( 'Back to topics', 'yoohw-support-portal' ),
								'url'   => YoOhw_Support_Router::url( 'topics' ),
								'icon'  => 'message-square',
							],
						],
					]
				);
			}

			return self::render_template(
				'topic-single-isolated',
				[
					'topic'   => $topic,
					'replies' => YoOhw_Support_Database::get_replies( (int) $topic['id'] ),
				]
			);
		}

		$post      = self::resolve_topic( $topic_key );

		if ( ! $post || ! self::current_user_can_view_topic( $post ) ) {
			status_header( 404 );
			return self::render_template(
				'status',
				[
					'icon'        => 'circle-alert',
					'kicker'      => __( 'Topic unavailable', 'yoohw-support-portal' ),
					'title'       => __( 'We could not find that topic.', 'yoohw-support-portal' ),
					'description' => __( 'The topic may have been removed, or you may not have access to view it.', 'yoohw-support-portal' ),
					'actions'     => [
						[
							'label' => __( 'Back to topics', 'yoohw-support-portal' ),
							'url'   => YoOhw_Support_Router::url( 'topics' ),
							'icon'  => 'message-square',
						],
					],
				]
			);
		}

		$comments = get_comments(
			[
				'post_id' => $post->ID,
				'status'  => 'approve',
				'type'    => 'comment',
				'orderby' => 'comment_date_gmt',
				'order'   => 'ASC',
			]
		);

		return self::render_template(
			'topic-single',
			[
				'post'     => $post,
				'comments' => $comments,
			]
		);
	}

	public static function render_author_profile(): string {
		$author = get_queried_object();

		if ( ! $author instanceof WP_User || ! current_user_can( YoOhw_Support_Capabilities::VIEW_CUSTOMERS ) ) {
			status_header( 404 );
			return self::render_template(
				'status',
				[
					'icon'        => 'circle-alert',
					'kicker'      => __( 'Author unavailable', 'yoohw-support-portal' ),
					'title'       => __( 'We could not find that author.', 'yoohw-support-portal' ),
					'description' => __( 'The author may not exist, or you may not have permission to view this profile.', 'yoohw-support-portal' ),
					'actions'     => [
						[
							'label' => __( 'Back to topics', 'yoohw-support-portal' ),
							'url'   => YoOhw_Support_Router::url( 'topics' ),
							'icon'  => 'message-square',
						],
					],
				]
			);
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only pagination parameter.
		$paged    = max( 1, absint( $_GET['support_page'] ?? get_query_var( 'paged', 1 ) ) );
		$statuses = self::public_topic_statuses();
		$query    = new WP_Query(
			[
				'post_type'           => 'post',
				'post_status'         => $statuses,
				'author'              => $author->ID,
				'posts_per_page'      => 8,
				'paged'               => $paged,
				'ignore_sticky_posts' => true,
			]
		);

		return self::render_template(
			'author',
			[
				'author' => $author,
				'query'  => $query,
				'paged'  => $paged,
				'stats'  => self::author_topic_stats( (int) $author->ID ),
			]
		);
	}

	public static function render_isolated_customer_profile(): string {
		if ( ! YoOhw_Support_Settings::uses_isolated_storage() || ! current_user_can( YoOhw_Support_Capabilities::VIEW_CUSTOMERS ) ) {
			status_header( 404 );
			return self::render_not_found();
		}

		$customer_id = YoOhw_Support_Router::get_customer_id();
		$customer    = $customer_id ? get_userdata( $customer_id ) : false;

		if ( ! $customer ) {
			status_header( 404 );
			return self::render_not_found();
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only pagination parameter.
		$paged  = max( 1, absint( $_GET['support_page'] ?? 1 ) );
		$result = YoOhw_Support_Database::query_topics(
			[
				'page'      => $paged,
				'per_page'  => 8,
				'author_id' => $customer_id,
			]
		);

		return self::render_template(
			'customer-isolated',
			[
				'customer'  => $customer,
				'topics'    => $result['items'],
				'total'     => $result['total'],
				'max_pages' => $result['max_pages'],
				'paged'     => $paged,
			]
		);
	}

	public static function render_page(): string {
		$page = get_queried_object();

		if ( ! $page instanceof WP_Post || 'page' !== $page->post_type ) {
			status_header( 404 );
			return self::render_template(
				'status',
				[
					'icon'        => 'circle-alert',
					'kicker'      => __( 'Page unavailable', 'yoohw-support-portal' ),
					'title'       => __( 'We could not find that page.', 'yoohw-support-portal' ),
					'description' => __( 'The page may have been removed or is no longer available.', 'yoohw-support-portal' ),
					'actions'     => [
						[
							'label' => __( 'Open support portal', 'yoohw-support-portal' ),
							'url'   => YoOhw_Support_Router::url( 'dashboard' ),
							'icon'  => 'house',
						],
					],
				]
			);
		}

		$previous_post = $GLOBALS['post'] ?? null;
		$GLOBALS['post'] = $page; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
		setup_postdata( $page );
		// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Applies the canonical WordPress content filter.
		$content = apply_filters( 'the_content', $page->post_content );
		wp_reset_postdata();

		if ( $previous_post instanceof WP_Post ) {
			$GLOBALS['post'] = $previous_post; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
		}

		return self::render_template(
			'page',
			[
				'page'    => $page,
				'content' => $content,
			]
		);
	}

	public static function render_not_found(): string {
		status_header( 404 );
		nocache_headers();

		if ( is_user_logged_in() ) {
			$actions = [
				[
					'label' => __( 'Open support portal', 'yoohw-support-portal' ),
					'url'   => YoOhw_Support_Router::url( 'dashboard' ),
					'icon'  => 'house',
				],
				[
					'label' => __( 'View my topics', 'yoohw-support-portal' ),
					'url'   => YoOhw_Support_Router::url( 'topics' ),
					'icon'  => 'message-square',
				],
			];
		} else {
			$actions = [
				[
					'label' => __( 'Log in to support', 'yoohw-support-portal' ),
					'url'   => YoOhw_Support_Router::url( 'login' ),
					'icon'  => 'log-in',
				],
			];

			$docs_url = YoOhw_Support_Settings::get_url( 'docs_url' );

			if ( '' !== $docs_url ) {
				$actions[] = [
					'label' => __( 'Browse documentation', 'yoohw-support-portal' ),
					'url'   => $docs_url,
					'icon'  => 'book-open',
				];
			}
		}

		return self::render_template(
			'not-found',
			[
				'actions' => $actions,
			]
		);
	}

	public static function render_auth_screen( string $mode ): string {
		$registration_allowed = ( 'login' === $mode && self::registration_allowed() );
		$auth_variant         = $registration_allowed ? self::auth_variant() : 'login';

		if ( 'reset-password' === $mode ) {
			$active_form = self::render_reset_password_form();
		} else {
			$active_form = ( 'register' === $auth_variant ) ? self::render_register_form() : ( 'lost-password' === $mode ? self::render_lost_password_form() : self::render_login_form() );
		}

		return self::render_template(
			'login',
			[
				'mode'                 => $mode,
				'form'                 => $active_form,
				'auth_variant'         => $auth_variant,
				'registration_allowed' => $registration_allowed,
			]
		);
	}

	public static function handle_auth_submissions(): void {
		// phpcs:disable WordPress.Security.NonceVerification.Missing -- This dispatcher only selects a handler; each target handler verifies its action-specific nonce before processing.
		if ( isset( $_POST['yoohw_support_login'] ) ) {
			self::handle_login_submission();
			return;
		}

		if ( isset( $_POST['yoohw_support_lost_password'] ) ) {
			self::handle_lost_password_submission();
			return;
		}

		if ( isset( $_POST['yoohw_support_reset_password'] ) ) {
			self::handle_reset_password_submission();
			return;
		}

		if ( isset( $_POST['yoohw_support_register'] ) ) {
			self::handle_register_submission();
		}
		// phpcs:enable WordPress.Security.NonceVerification.Missing
	}

	public static function redirect_wp_reset_password(): void {
		if ( ! apply_filters( 'yoohw_support_use_custom_reset_password_page', true ) ) {
			return;
		}

		[ $login, $key ] = self::reset_credentials_from_request();

		if ( '' === $login || '' === $key ) {
			wp_safe_redirect(
				YoOhw_Support_Router::url(
					'lost-password',
					[
						'reset' => 'invalid',
					]
				)
			);
			exit;
		}

		wp_safe_redirect( self::reset_password_url( $login, $key ) );
		exit;
	}

	public static function replace_reset_password_url( $message, $key, $user_login, $user_data ) {
		$reset_url = self::reset_password_url( (string) $user_login, (string) $key );
		$message   = (string) $message;
		$pattern   = '#https?://[^\s<>"\']*wp-login\.php\?[^\s<>"\']*action=rp[^\s<>"\']*#i';
		$updated   = preg_replace( $pattern, $reset_url, $message, 1 );

		return is_string( $updated ) ? $updated : $message;
	}

	public static function replace_reset_password_links( string $message ): string {
		$updated = preg_replace_callback(
			'#https?://[^\s<>"\']*wp-login\.php\?[^\s<>"\']*action=rp[^\s<>"\']*#i',
			static function ( array $matches ): string {
				$url   = html_entity_decode( $matches[0], ENT_QUOTES, get_bloginfo( 'charset' ) ?: 'UTF-8' );
				$query = wp_parse_url( $url, PHP_URL_QUERY );

				if ( ! is_string( $query ) ) {
					return $matches[0];
				}

				parse_str( $query, $args );

				$login = isset( $args['login'] ) && is_scalar( $args['login'] ) ? sanitize_user( (string) $args['login'], true ) : '';
				$key   = isset( $args['key'] ) && is_scalar( $args['key'] ) ? sanitize_text_field( (string) $args['key'] ) : '';

				if ( '' === $login || '' === $key ) {
					return $matches[0];
				}

				return self::reset_password_url( $login, $key );
			},
			$message
		);

		return is_string( $updated ) ? $updated : $message;
	}

	public static function reset_password_url( string $login, string $key ): string {
		return YoOhw_Support_Router::url(
			'reset-password',
			[
				'login' => $login,
				'key'   => $key,
			]
		);
	}

	private static function registration_allowed(): bool {
		return (bool) get_option( 'users_can_register' );
	}

	/**
	 * Return the non-empty codes used by categories whose access method is access code.
	 */
	private static function registration_access_codes(): array {
		static $codes = null;

		if ( null !== $codes ) {
			return $codes;
		}

		$codes    = [];
		$term_ids = get_terms(
			[
				'taxonomy'   => 'category',
				'hide_empty' => false,
				'fields'     => 'ids',
			]
		);

		if ( is_wp_error( $term_ids ) ) {
			return $codes;
		}

		foreach ( $term_ids as $term_id ) {
			$term_id     = absint( $term_id );
			$access_type = function_exists( 'yoohw_category_get_access_type' )
				? yoohw_category_get_access_type( $term_id )
				: 'access_code';

			if ( 'access_code' !== $access_type ) {
				continue;
			}

			$code = trim( (string) get_term_meta( $term_id, 'yoohw_support_access_code', true ) );

			if ( '' !== $code ) {
				$codes[] = $code;
			}
		}

		return array_values( array_unique( $codes ) );
	}

	private static function auth_variant(): string {
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Read-only form selector; the registration handler verifies its nonce.
		if ( isset( $_POST['yoohw_support_register'] ) ) {
			return 'register';
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only UI variant selector.
		$variant = isset( $_GET['auth'] ) ? sanitize_key( wp_unslash( $_GET['auth'] ) ) : '';

		return 'register' === $variant ? 'register' : 'login';
	}

	private static function login_redirect_to(): string {
		$fallback = YoOhw_Support_Router::url( 'dashboard' );

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only redirect target; wp_validate_redirect() restricts it to an allowed destination.
		if ( empty( $_REQUEST['redirect_to'] ) || ! is_scalar( $_REQUEST['redirect_to'] ) ) {
			return $fallback;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only redirect target; wp_validate_redirect() restricts it to an allowed destination.
		return wp_validate_redirect( esc_url_raw( wp_unslash( (string) $_REQUEST['redirect_to'] ) ), $fallback );
	}

	private static function reset_credentials_from_request(): array {
		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- Read-only reset credentials are validated by check_password_reset_key() before a password can be changed.
		$login = ( isset( $_REQUEST['login'] ) && is_scalar( $_REQUEST['login'] ) ) ? sanitize_user( wp_unslash( (string) $_REQUEST['login'] ), true ) : '';
		$key   = ( isset( $_REQUEST['key'] ) && is_scalar( $_REQUEST['key'] ) ) ? sanitize_text_field( wp_unslash( (string) $_REQUEST['key'] ) ) : '';

		if ( ( '' === $login || '' === $key ) && isset( $_REQUEST['rp_login'], $_REQUEST['rp_key'] ) ) {
			$login = is_scalar( $_REQUEST['rp_login'] ) ? sanitize_user( wp_unslash( (string) $_REQUEST['rp_login'] ), true ) : '';
			$key   = is_scalar( $_REQUEST['rp_key'] ) ? sanitize_text_field( wp_unslash( (string) $_REQUEST['rp_key'] ) ) : '';
		}

		if ( '' !== $login && '' !== $key ) {
			return [ $login, $key ];
		}

		$rp_cookie = 'wp-resetpass-' . COOKIEHASH;

		$cookie_value = isset( $_COOKIE[ $rp_cookie ] ) && is_scalar( $_COOKIE[ $rp_cookie ] )
			? sanitize_text_field( wp_unslash( (string) $_COOKIE[ $rp_cookie ] ) )
			: '';

		if ( '' === $cookie_value || false === strpos( $cookie_value, ':' ) ) {
			return [ $login, $key ];
		}

		[ $cookie_login, $cookie_key ] = explode( ':', $cookie_value, 2 );

		return [
			sanitize_user( $cookie_login, true ),
			sanitize_text_field( $cookie_key ),
		];
		// phpcs:enable WordPress.Security.NonceVerification.Recommended
	}

	private static function check_reset_password_key( string $login, string $key ) {
		if ( '' === $login || '' === $key ) {
			return new WP_Error( 'invalid_key', __( 'This password reset link is invalid or incomplete.', 'yoohw-support-portal' ) );
		}

		$user = check_password_reset_key( $key, $login );

		return is_wp_error( $user ) ? $user : $user;
	}

	private static function reset_key_error_message( WP_Error $error ): string {
		if ( 'expired_key' === $error->get_error_code() ) {
			return __( 'This password reset link has expired. Please request a new link.', 'yoohw-support-portal' );
		}

		return __( 'This password reset link is invalid. Please request a new link.', 'yoohw-support-portal' );
	}

	private static function handle_login_submission(): void {
		self::$auth_error = '';

		if (
			empty( $_POST['yoohw_support_login_nonce'] ) ||
			! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['yoohw_support_login_nonce'] ) ), 'yoohw_support_login' )
		) {
			self::$auth_error = __( 'Security check failed. Please try again.', 'yoohw-support-portal' );
			return;
		}

		$creds = [
			'user_login'    => isset( $_POST['log'] ) ? sanitize_user( wp_unslash( $_POST['log'] ) ) : '',
			// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Passwords must remain byte-for-byte unchanged; nonce is verified above.
			'user_password' => isset( $_POST['pwd'] ) ? (string) wp_unslash( $_POST['pwd'] ) : '',
			'remember'      => ! empty( $_POST['rememberme'] ),
		];

		$user = wp_signon( $creds, is_ssl() );

		if ( is_wp_error( $user ) ) {
			self::$auth_error = $user->get_error_message();
			return;
		}

		wp_set_current_user( $user->ID );
		wp_safe_redirect( self::login_redirect_to() );
		exit;
	}

	private static function handle_lost_password_submission(): void {
		self::$auth_error   = '';
		self::$auth_message = '';

		if (
			empty( $_POST['yoohw_support_lost_password_nonce'] ) ||
			! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['yoohw_support_lost_password_nonce'] ) ), 'yoohw_support_lost_password' )
		) {
			self::$auth_error = __( 'Security check failed. Please try again.', 'yoohw-support-portal' );
			return;
		}

		$user_login = isset( $_POST['user_login'] ) ? sanitize_text_field( wp_unslash( $_POST['user_login'] ) ) : '';
		$user_data  = get_user_by( 'email', $user_login );

		if ( ! $user_data ) {
			$user_data = get_user_by( 'login', $user_login );
		}

		if ( ! $user_data ) {
			self::$auth_error = __( 'No user found with that email address or username.', 'yoohw-support-portal' );
			return;
		}

		$reset_key = get_password_reset_key( $user_data );

		if ( is_wp_error( $reset_key ) ) {
			self::$auth_error = $reset_key->get_error_message();
			return;
		}

		$reset_url     = self::reset_password_url( $user_data->user_login, $reset_key );
		$blogname      = wp_specialchars_decode( get_option( 'blogname' ), ENT_QUOTES );
		$email_message = "To reset your password, visit the following address:\n\n" . $reset_url . "\n\n";

		wp_mail( $user_data->user_email, sprintf( '[%s] Password Reset', $blogname ), $email_message );

		self::$auth_message = __( 'A password reset link has been sent to your email address.', 'yoohw-support-portal' );
	}

	private static function handle_reset_password_submission(): void {
		self::$reset_error = '';

		if (
			empty( $_POST['yoohw_support_reset_password_nonce'] ) ||
			! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['yoohw_support_reset_password_nonce'] ) ), 'yoohw_support_reset_password' )
		) {
			self::$reset_error = __( 'Security check failed. Please open the reset link again and retry.', 'yoohw-support-portal' );
			return;
		}

		$login = ( isset( $_POST['rp_login'] ) && is_scalar( $_POST['rp_login'] ) ) ? sanitize_user( wp_unslash( (string) $_POST['rp_login'] ), true ) : '';
		$key   = ( isset( $_POST['rp_key'] ) && is_scalar( $_POST['rp_key'] ) ) ? sanitize_text_field( wp_unslash( (string) $_POST['rp_key'] ) ) : '';
		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Passwords must remain unchanged; nonce is verified above.
		$pass1 = ( isset( $_POST['pass1'] ) && is_scalar( $_POST['pass1'] ) ) ? (string) wp_unslash( (string) $_POST['pass1'] ) : '';
		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Password confirmation must be compared byte-for-byte.
		$pass2 = ( isset( $_POST['pass2'] ) && is_scalar( $_POST['pass2'] ) ) ? (string) wp_unslash( (string) $_POST['pass2'] ) : '';
		$user  = self::check_reset_password_key( $login, $key );

		if ( is_wp_error( $user ) ) {
			self::$reset_error = self::reset_key_error_message( $user );
			return;
		}

		$errors = new WP_Error();

		if ( '' !== $pass1 ) {
			$pass1 = trim( $pass1 );

			if ( '' === $pass1 ) {
				$errors->add( 'password_reset_empty_space', __( 'The password cannot be a space or all spaces.', 'yoohw-support-portal' ) );
			}
		}

		if ( '' === $pass1 ) {
			$errors->add( 'password_reset_empty', __( 'Please enter a new password.', 'yoohw-support-portal' ) );
		}

		if ( '' !== $pass1 && trim( $pass2 ) !== $pass1 ) {
			$errors->add( 'password_reset_mismatch', __( 'The passwords do not match.', 'yoohw-support-portal' ) );
		}

		// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Fires the canonical WordPress password validation hook.
		do_action( 'validate_password_reset', $errors, $user );

		if ( $errors->has_errors() ) {
			self::$reset_error = implode( '<br>', array_map( 'wp_kses_post', $errors->get_error_messages() ) );
			return;
		}

		reset_password( $user, $pass1 );

		wp_safe_redirect(
			YoOhw_Support_Router::url(
				'login',
				[
					'password' => 'changed',
				]
			)
		);
		exit;
	}

	private static function handle_register_submission(): void {
		self::$register_error   = '';
		self::$register_message = '';

		if ( ! self::registration_allowed() ) {
			self::$register_error = __( 'Account registration is currently disabled.', 'yoohw-support-portal' );
			return;
		}

		if (
			empty( $_POST['yoohw_support_register_nonce'] ) ||
			! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['yoohw_support_register_nonce'] ) ), 'yoohw_support_register' )
		) {
			self::$register_error = __( 'Security check failed. Please try again.', 'yoohw-support-portal' );
			return;
		}

		$user_login = isset( $_POST['user_login'] ) ? sanitize_user( wp_unslash( $_POST['user_login'] ) ) : '';
		$user_email = isset( $_POST['user_email'] ) ? sanitize_email( wp_unslash( $_POST['user_email'] ) ) : '';
		$access_code = isset( $_POST['yoohw_support_access_code'] ) && is_scalar( $_POST['yoohw_support_access_code'] )
			? sanitize_text_field( wp_unslash( (string) $_POST['yoohw_support_access_code'] ) )
			: '';

		if ( '' !== $access_code && ! in_array( $access_code, self::registration_access_codes(), true ) ) {
			self::$register_error = __( 'The access code is invalid.', 'yoohw-support-portal' );
			return;
		}

		$user_id    = register_new_user( $user_login, $user_email );

		if ( is_wp_error( $user_id ) ) {
			self::$register_error = implode( '<br>', array_map( 'wp_kses_post', $user_id->get_error_messages() ) );
			return;
		}

		if ( '' !== $access_code ) {
			update_user_meta( $user_id, 'yoohw_support_access_codes', [ $access_code ] );
		}

		self::$register_message = __( 'Account created. Please check your email to set your password and finish signing in.', 'yoohw-support-portal' );
	}

	public static function render_status( string $status ): string {
		$config = [
			'topic-published' => [
				'icon'        => 'circle-check-big',
				'kicker'      => __( 'Topic published', 'yoohw-support-portal' ),
				'title'       => __( 'Your topic has been published.', 'yoohw-support-portal' ),
				'description' => __( 'One of our colleagues will be in touch within no more than 24 hours.', 'yoohw-support-portal' ),
				'actions'     => [
					[
						'label' => __( 'Back to My Topics', 'yoohw-support-portal' ),
						'url'   => YoOhw_Support_Router::url( 'topics' ),
						'icon'  => 'message-square',
					],
					[
						'label' => __( 'Create another topic', 'yoohw-support-portal' ),
						'url'   => YoOhw_Support_Router::url( 'new' ),
						'icon'  => 'square-pen',
					],
				],
			],
		];

		$config = apply_filters( 'yoohw_support_status_configs', $config, $status );

		if ( isset( $config[ $status ] ) && is_array( $config[ $status ] ) ) {
			return self::render_template( 'status', $config[ $status ] );
		}

		return self::render_template(
			'status',
			[
				'icon'        => 'circle-alert',
				'kicker'      => __( 'Status unavailable', 'yoohw-support-portal' ),
				'title'       => __( 'We could not load this status page.', 'yoohw-support-portal' ),
				'description' => __( 'The requested support status is not available.', 'yoohw-support-portal' ),
				'actions'     => [
					[
						'label' => __( 'Back to support portal', 'yoohw-support-portal' ),
						'url'   => YoOhw_Support_Router::url( 'dashboard' ),
						'icon'  => 'house',
					],
				],
			]
		);
	}

	public static function render_topic_form(): string {
		if ( ! is_user_logged_in() ) {
			return self::render_template(
				'status',
				[
					'icon'        => 'lock',
					'kicker'      => __( 'Login required', 'yoohw-support-portal' ),
					'title'       => __( 'Log in to submit a topic.', 'yoohw-support-portal' ),
					'description' => __( 'Your account determines which support categories are available.', 'yoohw-support-portal' ),
					'actions'     => [
						[
							'label' => __( 'Log in', 'yoohw-support-portal' ),
							'url'   => YoOhw_Support_Router::url( 'login', [ 'redirect_to' => YoOhw_Support_Router::url( 'new' ) ] ),
							'icon'  => 'log-in',
						],
					],
				]
			);
		}

		YoOhw_Support_Assets::enqueue_editor_assets();

		$user_id             = get_current_user_id();
		$user                = wp_get_current_user();
		$user_access_codes   = YoOhw_Shortcode::get_user_access_codes( $user_id );
		$is_admin            = current_user_can( YoOhw_Support_Capabilities::MANAGE_CATEGORIES );

		$categories = self::get_available_categories( $user_access_codes, $is_admin, $user_id );

		ob_start();
		?>
		<form id="yoohw_post_form" class="yoohw-form yoohw-topic-form" action="" method="post" enctype="multipart/form-data" data-yoohw-form="topic">
			<?php wp_nonce_field( 'yoohw_submit_post', 'yoohw_submit_post_nonce' ); ?>

			<div class="yoohw-field">
				<label class="yoohw-reply-field-label" for="yoohw_post_title">
					<?php YoOhw_Support_Icons::output( 'square-pen' ); ?>
					<?php esc_html_e( 'Topic subject', 'yoohw-support-portal' ); ?>
				</label>
				<input type="text" id="yoohw_post_title" name="yoohw_post_title" required autocomplete="off">
			</div>

			<div class="yoohw-field">
				<label class="yoohw-reply-field-label" for="yoohw_post_category">
					<?php YoOhw_Support_Icons::output( 'tag' ); ?>
					<?php esc_html_e( 'Category', 'yoohw-support-portal' ); ?>
				</label>
				<select id="yoohw_post_category" name="yoohw_post_category" required>
					<option value=""><?php esc_html_e( 'Select category', 'yoohw-support-portal' ); ?></option>
					<?php foreach ( $categories as $category ) : ?>
						<?php if ( ! empty( $category['children'] ) ) : ?>
							<option value="" disabled><?php echo esc_html( $category['term']->name ); ?></option>
							<?php foreach ( $category['children'] as $child ) : ?>
								<option value="<?php echo esc_attr( $child->term_id ); ?>">- <?php echo esc_html( $child->name ); ?></option>
							<?php endforeach; ?>
						<?php else : ?>
							<option value="<?php echo esc_attr( $category['term']->term_id ); ?>"><?php echo esc_html( $category['term']->name ); ?></option>
						<?php endif; ?>
					<?php endforeach; ?>
				</select>
			</div>

			<div class="yoohw-field yoohw-field-editor">
				<label class="yoohw-reply-field-label" for="yoohw_post_content">
					<?php YoOhw_Support_Icons::output( 'message-square' ); ?>
					<?php esc_html_e( 'Content', 'yoohw-support-portal' ); ?>
				</label>
				<div class="yoohw-reply-editor-shell">
					<?php
					wp_editor( '', 'yoohw_post_content', self::editor_settings( 'yoohw_post_content' ) );
					?>
				</div>
			</div>

			<?php if ( ! YoOhw_Support_Settings::uses_isolated_storage() ) : ?>
				<div class="yoohw-field yoohw-attachment-field yoohw-reply-upload">
				<div class="yoohw-reply-upload-heading">
					<span class="yoohw-reply-field-label">
						<?php YoOhw_Support_Icons::output( 'paperclip' ); ?>
						<?php esc_html_e( 'Attachments', 'yoohw-support-portal' ); ?>
					</span>
					<span class="yoohw-upload-limit">
						<?php
						printf(
							/* translators: 1: Maximum attachment count, 2: Maximum size per attachment. */
							esc_html__( 'Up to %1$d files, %2$s each', 'yoohw-support-portal' ),
							absint( YoOhw_Shortcode::MAX_ATTACHMENTS ),
							esc_html( size_format( YoOhw_Shortcode::MAX_ATTACHMENT_BYTES ) )
						);
						?>
					</span>
				</div>
				<label class="yoohw-upload-dropzone" for="yoohw_attachments">
					<span class="yoohw-upload-dropzone-icon"><?php YoOhw_Support_Icons::output( 'paperclip' ); ?></span>
					<span class="yoohw-upload-dropzone-title"><?php esc_html_e( 'Choose or drop screenshots, videos, or ZIP files', 'yoohw-support-portal' ); ?></span>
					<span class="yoohw-upload-dropzone-note"><?php esc_html_e( 'JPG, PNG, WebP, GIF, MP4, WebM, MOV, ZIP', 'yoohw-support-portal' ); ?></span>
				</label>
				<input
					type="file"
					class="yoohw-upload-input"
					id="yoohw_attachments"
					name="yoohw_attachments[]"
					multiple
					accept="image/jpeg,image/png,image/webp,image/gif,video/mp4,video/webm,video/quicktime,application/zip,.zip"
					data-max-files="<?php echo esc_attr( YoOhw_Shortcode::MAX_ATTACHMENTS ); ?>"
					data-max-size="<?php echo esc_attr( YoOhw_Shortcode::MAX_ATTACHMENT_BYTES ); ?>"
					aria-describedby="yoohw_attachments_selected"
				>
				<div
					id="yoohw_attachments_selected"
					class="yoohw-upload-selected is-empty"
					data-upload-selected-for="yoohw_attachments"
					data-empty-label="<?php esc_attr_e( 'No files selected', 'yoohw-support-portal' ); ?>"
				>
					<?php esc_html_e( 'No files selected', 'yoohw-support-portal' ); ?>
				</div>
				</div>
			<?php endif; ?>

			<div class="yoohw-form-actions yoohw-reply-submit-row">
				<button type="submit" class="yoohw-button yoohw-button-primary yoohw-reply-submit">
					<?php YoOhw_Support_Icons::output( 'send' ); ?>
					<?php esc_html_e( 'Submit topic', 'yoohw-support-portal' ); ?>
				</button>
			</div>
		</form>
		<?php
		return ob_get_clean();
	}

	public static function render_login_form(): string {
		if ( is_user_logged_in() ) {
			return self::render_template(
				'status',
				[
					'icon'        => 'circle-check',
					'kicker'      => __( 'Logged in', 'yoohw-support-portal' ),
					'title'       => __( 'You are already logged in.', 'yoohw-support-portal' ),
					'description' => __( 'Continue to your support dashboard or review your current topics.', 'yoohw-support-portal' ),
					'actions'     => [
						[
							'label' => __( 'Open dashboard', 'yoohw-support-portal' ),
							'url'   => YoOhw_Support_Router::url( 'dashboard' ),
							'icon'  => 'house',
						],
					],
				]
			);
		}

		$error       = self::$auth_error;
		$message     = self::$auth_message;
		$redirect_to = self::login_redirect_to();

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only success message flag.
		if ( isset( $_GET['password'] ) && 'changed' === sanitize_key( wp_unslash( $_GET['password'] ) ) ) {
			$message = __( 'Your password has been reset. You can log in now.', 'yoohw-support-portal' );
		}

		ob_start();
		?>
		<form name="loginform" id="loginform" class="yoohw-form yoohw-auth-form" action="" method="post" data-yoohw-form="auth">
			<?php wp_nonce_field( 'yoohw_support_login', 'yoohw_support_login_nonce' ); ?>
			<input type="hidden" name="yoohw_support_login" value="1">
			<input type="hidden" name="redirect_to" value="<?php echo esc_attr( $redirect_to ); ?>">

			<?php if ( $error ) : ?>
				<div class="yoohw-notice yoohw-notice-error"><?php echo wp_kses_post( $error ); ?></div>
			<?php endif; ?>

			<?php if ( $message ) : ?>
				<div class="yoohw-notice yoohw-notice-success"><?php echo esc_html( $message ); ?></div>
			<?php endif; ?>

			<div class="yoohw-field">
				<label for="user_login"><?php esc_html_e( 'Username or email address', 'yoohw-support-portal' ); ?></label>
				<input type="text" name="log" id="user_login" autocomplete="username" required>
			</div>

			<div class="yoohw-field">
				<label for="user_pass"><?php esc_html_e( 'Password', 'yoohw-support-portal' ); ?></label>
				<div class="yoohw-password-field">
					<input type="password" name="pwd" id="user_pass" autocomplete="current-password" required>
					<button class="yoohw-icon-button yoohw-password-toggle" type="button" aria-label="<?php esc_attr_e( 'Show password', 'yoohw-support-portal' ); ?>" data-show-label="<?php esc_attr_e( 'Show password', 'yoohw-support-portal' ); ?>" data-hide-label="<?php esc_attr_e( 'Hide password', 'yoohw-support-portal' ); ?>">
						<span data-icon-on><?php YoOhw_Support_Icons::output( 'eye' ); ?></span>
						<span data-icon-off hidden><?php YoOhw_Support_Icons::output( 'eye-off' ); ?></span>
					</button>
				</div>
			</div>

			<div class="yoohw-auth-row">
				<label class="yoohw-checkbox">
					<input name="rememberme" type="checkbox" value="forever">
					<span><?php esc_html_e( 'Remember me', 'yoohw-support-portal' ); ?></span>
				</label>
				<a href="<?php echo esc_url( self::lost_password_url( 'login_form' ) ); ?>"<?php echo self::lost_password_link_attributes( 'login_form' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Method escapes every attribute value. ?>><?php esc_html_e( 'Forgot password?', 'yoohw-support-portal' ); ?></a>
			</div>

			<button type="submit" class="yoohw-button yoohw-button-primary yoohw-button-full">
				<?php YoOhw_Support_Icons::output( 'log-in' ); ?>
				<?php esc_html_e( 'Log in', 'yoohw-support-portal' ); ?>
			</button>
		</form>
		<?php
		return ob_get_clean();
	}

	public static function lost_password_url( string $context = 'login_form' ): string {
		$url = YoOhw_Support_Router::url( 'lost-password' );
		$url = apply_filters( 'yoohw_support_lost_password_url', $url, $context );

		return esc_url_raw( (string) $url );
	}

	public static function lost_password_link_attributes( string $context = 'login_form' ): string {
		$attributes = apply_filters( 'yoohw_support_lost_password_link_attributes', [], $context, self::lost_password_url( $context ) );

		return self::render_html_attributes( is_array( $attributes ) ? $attributes : [] );
	}

	private static function render_html_attributes( array $attributes ): string {
		$html = '';

		foreach ( $attributes as $name => $value ) {
			$name = is_string( $name ) ? strtolower( trim( $name ) ) : '';

			if ( '' === $name || ! preg_match( '/^[a-z][a-z0-9:_-]*$/', $name ) || false === $value || null === $value ) {
				continue;
			}

			if ( true === $value ) {
				$html .= ' ' . esc_attr( $name );
				continue;
			}

			$html .= ' ' . esc_attr( $name ) . '="' . esc_attr( (string) $value ) . '"';
		}

		return $html;
	}

	public static function render_register_form(): string {
		if ( is_user_logged_in() || ! self::registration_allowed() ) {
			return '';
		}

		$error      = self::$register_error;
		$message    = self::$register_message;
		// phpcs:disable WordPress.Security.NonceVerification.Missing -- Values are only repopulated into escaped fields after the nonce-verifying registration handler reports an error.
		$user_login = ( $error && isset( $_POST['user_login'] ) ) ? sanitize_user( wp_unslash( $_POST['user_login'] ) ) : '';
		$user_email = ( $error && isset( $_POST['user_email'] ) ) ? sanitize_email( wp_unslash( $_POST['user_email'] ) ) : '';
		$access_codes_available = ! empty( self::registration_access_codes() );
		$access_code = ( $error && isset( $_POST['yoohw_support_access_code'] ) && is_scalar( $_POST['yoohw_support_access_code'] ) )
			? sanitize_text_field( wp_unslash( (string) $_POST['yoohw_support_access_code'] ) )
			: '';
		// phpcs:enable WordPress.Security.NonceVerification.Missing

		ob_start();
		?>
		<form name="registerform" id="registerform" class="yoohw-form yoohw-auth-form yoohw-register-form" action="" method="post" data-yoohw-form="auth">
			<?php wp_nonce_field( 'yoohw_support_register', 'yoohw_support_register_nonce' ); ?>
			<input type="hidden" name="yoohw_support_register" value="1">

			<?php if ( $message ) : ?>
				<div class="yoohw-notice yoohw-notice-success"><?php echo esc_html( $message ); ?></div>
			<?php endif; ?>

			<?php if ( $error ) : ?>
				<div class="yoohw-notice yoohw-notice-error"><?php echo wp_kses_post( $error ); ?></div>
			<?php endif; ?>

			<div class="yoohw-field">
				<label for="register_user_login"><?php esc_html_e( 'Username', 'yoohw-support-portal' ); ?></label>
				<input type="text" name="user_login" id="register_user_login" value="<?php echo esc_attr( $user_login ); ?>" autocomplete="username" required>
			</div>

			<div class="yoohw-field">
				<label for="register_user_email"><?php esc_html_e( 'Email address', 'yoohw-support-portal' ); ?></label>
				<input type="email" name="user_email" id="register_user_email" value="<?php echo esc_attr( $user_email ); ?>" autocomplete="email" required>
			</div>

			<?php if ( $access_codes_available ) : ?>
				<div class="yoohw-field">
					<label for="yoohw_support_register_access_code"><?php esc_html_e( 'Access code', 'yoohw-support-portal' ); ?></label>
					<input type="text" name="yoohw_support_access_code" id="yoohw_support_register_access_code" value="<?php echo esc_attr( $access_code ); ?>" autocomplete="off">
					<p class="yoohw-field-description"><?php esc_html_e( 'Optional. Enter a valid code to access its support category.', 'yoohw-support-portal' ); ?></p>
				</div>
			<?php endif; ?>

			<p class="yoohw-auth-hint">
				<?php esc_html_e( 'You will receive an email with a link to set your password.', 'yoohw-support-portal' ); ?>
			</p>

			<button type="submit" class="yoohw-button yoohw-button-secondary yoohw-button-full">
				<?php YoOhw_Support_Icons::output( 'user' ); ?>
				<?php esc_html_e( 'Create account', 'yoohw-support-portal' ); ?>
			</button>
		</form>
		<?php
		return ob_get_clean();
	}

	public static function render_lost_password_form(): string {
		if ( is_user_logged_in() ) {
			return self::render_login_form();
		}

		$message = self::$auth_message;
		$error   = self::$auth_error;

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only error message flag.
		if ( ! $error && isset( $_GET['reset'] ) && 'invalid' === sanitize_key( wp_unslash( $_GET['reset'] ) ) ) {
			$error = __( 'That password reset link is invalid or incomplete. Please request a new link.', 'yoohw-support-portal' );
		}

		ob_start();
		?>
		<form name="lostpasswordform" id="lostpasswordform" class="yoohw-form yoohw-auth-form" action="" method="post" data-yoohw-form="auth">
			<?php wp_nonce_field( 'yoohw_support_lost_password', 'yoohw_support_lost_password_nonce' ); ?>
			<input type="hidden" name="yoohw_support_lost_password" value="1">

			<?php if ( $message ) : ?>
				<div class="yoohw-notice yoohw-notice-success"><?php echo esc_html( $message ); ?></div>
			<?php endif; ?>

			<?php if ( $error ) : ?>
				<div class="yoohw-notice yoohw-notice-error"><?php echo wp_kses_post( $error ); ?></div>
			<?php endif; ?>

			<div class="yoohw-field">
				<label for="user_login"><?php esc_html_e( 'Username or email address', 'yoohw-support-portal' ); ?></label>
				<input type="text" name="user_login" id="user_login" autocomplete="username" required>
			</div>

			<button type="submit" class="yoohw-button yoohw-button-primary yoohw-button-full">
				<?php YoOhw_Support_Icons::output( 'key-round' ); ?>
				<?php esc_html_e( 'Get new password', 'yoohw-support-portal' ); ?>
			</button>

			<p class="yoohw-auth-alt">
				<a href="<?php echo esc_url( YoOhw_Support_Router::url( 'login' ) ); ?>"><?php esc_html_e( 'Back to login', 'yoohw-support-portal' ); ?></a>
			</p>
		</form>
		<?php
		return ob_get_clean();
	}

	public static function render_reset_password_form(): string {
		[ $login, $key ] = self::reset_credentials_from_request();
		$user            = self::check_reset_password_key( $login, $key );
		$error           = self::$reset_error;

		if ( is_wp_error( $user ) && '' === $error ) {
			$error = self::reset_key_error_message( $user );
		}

		ob_start();
		?>
		<form name="resetpassform" id="resetpassform" class="yoohw-form yoohw-auth-form yoohw-reset-password-form" action="<?php echo esc_url( YoOhw_Support_Router::url( 'reset-password' ) ); ?>" method="post" data-yoohw-form="auth" autocomplete="off">
			<?php wp_nonce_field( 'yoohw_support_reset_password', 'yoohw_support_reset_password_nonce' ); ?>
			<input type="hidden" name="yoohw_support_reset_password" value="1">
			<input type="hidden" name="rp_login" value="<?php echo esc_attr( $login ); ?>">
			<input type="hidden" name="rp_key" value="<?php echo esc_attr( $key ); ?>">

			<?php if ( $error ) : ?>
				<div class="yoohw-notice yoohw-notice-error"><?php echo wp_kses_post( $error ); ?></div>
			<?php endif; ?>

			<?php if ( ! is_wp_error( $user ) ) : ?>
				<div class="yoohw-field">
					<label for="pass1"><?php esc_html_e( 'New password', 'yoohw-support-portal' ); ?></label>
					<div class="yoohw-password-field">
						<input type="password" name="pass1" id="pass1" autocomplete="new-password" spellcheck="false" aria-describedby="pass-strength-result" data-yoohw-password-strength data-user-input="<?php echo esc_attr( $login ); ?>" required>
						<button class="yoohw-icon-button yoohw-password-toggle" type="button" aria-label="<?php esc_attr_e( 'Show password', 'yoohw-support-portal' ); ?>" data-show-label="<?php esc_attr_e( 'Show password', 'yoohw-support-portal' ); ?>" data-hide-label="<?php esc_attr_e( 'Hide password', 'yoohw-support-portal' ); ?>">
							<span data-icon-on><?php YoOhw_Support_Icons::output( 'eye' ); ?></span>
							<span data-icon-off hidden><?php YoOhw_Support_Icons::output( 'eye-off' ); ?></span>
						</button>
					</div>
					<div id="pass-strength-result" class="yoohw-password-strength is-empty" aria-live="polite" data-yoohw-password-strength-result>
						<?php esc_html_e( 'Password strength', 'yoohw-support-portal' ); ?>
					</div>
				</div>

				<div class="yoohw-field">
					<label for="pass2"><?php esc_html_e( 'Confirm new password', 'yoohw-support-portal' ); ?></label>
					<div class="yoohw-password-field">
						<input type="password" name="pass2" id="pass2" autocomplete="new-password" spellcheck="false" required>
						<button class="yoohw-icon-button yoohw-password-toggle" type="button" aria-label="<?php esc_attr_e( 'Show password', 'yoohw-support-portal' ); ?>" data-show-label="<?php esc_attr_e( 'Show password', 'yoohw-support-portal' ); ?>" data-hide-label="<?php esc_attr_e( 'Hide password', 'yoohw-support-portal' ); ?>">
							<span data-icon-on><?php YoOhw_Support_Icons::output( 'eye' ); ?></span>
							<span data-icon-off hidden><?php YoOhw_Support_Icons::output( 'eye-off' ); ?></span>
						</button>
					</div>
				</div>

				<p class="yoohw-auth-hint"><?php echo esc_html( wp_get_password_hint() ); ?></p>

				<button type="submit" class="yoohw-button yoohw-button-primary yoohw-button-full">
					<?php YoOhw_Support_Icons::output( 'lock' ); ?>
					<?php esc_html_e( 'Save password', 'yoohw-support-portal' ); ?>
				</button>
			<?php else : ?>
				<a class="yoohw-button yoohw-button-primary yoohw-button-full" href="<?php echo esc_url( YoOhw_Support_Router::url( 'lost-password' ) ); ?>">
					<?php YoOhw_Support_Icons::output( 'key-round' ); ?>
					<?php esc_html_e( 'Request a new link', 'yoohw-support-portal' ); ?>
				</a>
			<?php endif; ?>

			<p class="yoohw-auth-alt">
				<a href="<?php echo esc_url( YoOhw_Support_Router::url( 'login' ) ); ?>"><?php esc_html_e( 'Back to login', 'yoohw-support-portal' ); ?></a>
			</p>
		</form>
		<?php
		return ob_get_clean();
	}

	public static function editor_settings( string $editor_id ): array {
		return [
			'textarea_name' => $editor_id,
			'textarea_rows' => 10,
			'media_buttons' => false,
			'teeny'         => false,
			'quicktags'     => false,
			'tinymce'       => [
				'toolbar1'                => 'bold,italic,underline,bullist,numlist,link,unlink,blockquote,yoohw_support_codeblock',
				'height'                  => 220,
				'extended_valid_elements' => 'pre[class],a[href|target|rel|title]',
				'content_style'           => 'pre.wp-code-block{white-space:pre;tab-size:4;font-family:ui-monospace,SFMono-Regular,Menlo,Monaco,Consolas,monospace;font-size:13px;line-height:1.45;}',
				'setup'                   => 'function(editor){ if (window.YoOhwSupportEditor && window.YoOhwSupportEditor.setup) { window.YoOhwSupportEditor.setup(editor); } }',
			],
		];
	}

	public static function topic_url( $post ): string {
		$post = $post instanceof WP_Post ? $post : get_post( $post );

		if ( ! $post ) {
			return YoOhw_Support_Router::url( 'topics' );
		}

		return YoOhw_Support_Router::topic_url( $post );
	}

	public static function topic_latest_reply_url( $post ): string {
		$post = $post instanceof WP_Post ? $post : get_post( $post );

		if ( ! $post ) {
			return YoOhw_Support_Router::url( 'topics' );
		}

		$topic_url = self::topic_url( $post );
		$comments  = get_comments(
			[
				'post_id' => $post->ID,
				'status'  => 'approve',
				'type'    => 'comment',
				'orderby' => 'comment_date_gmt',
				'order'   => 'DESC',
				'number'  => 1,
			]
		);

		if ( empty( $comments ) || ! $comments[0] instanceof WP_Comment ) {
			return $topic_url;
		}

		return $topic_url . '#comment-' . (int) $comments[0]->comment_ID;
	}

	private static function author_topic_stats( int $author_id ): array {
		$author_id = absint( $author_id );

		if ( ! $author_id ) {
			return [
				'total'    => 0,
				'open'     => 0,
				'resolved' => 0,
				'replies'  => 0,
			];
		}

		$resolved_status = class_exists( 'YoOhw_Post_Status_Resolved' ) ? YoOhw_Post_Status_Resolved::STATUS : '';
		$topic_ids       = get_posts(
			[
				'post_type'        => 'post',
				'post_status'      => self::public_topic_statuses(),
				'author'           => $author_id,
				'posts_per_page'   => -1,
				'fields'           => 'ids',
				'suppress_filters' => false,
			]
		);

		$open_query = new WP_Query(
			[
				'post_type'      => 'post',
				'post_status'    => [ 'publish' ],
				'author'         => $author_id,
				'posts_per_page' => 1,
				'fields'         => 'ids',
			]
		);

		$resolved_count = 0;

		if ( $resolved_status ) {
			$resolved_query = new WP_Query(
				[
					'post_type'      => 'post',
					'post_status'    => [ $resolved_status ],
					'author'         => $author_id,
					'posts_per_page' => 1,
					'fields'         => 'ids',
				]
			);
			$resolved_count = (int) $resolved_query->found_posts;
		}

		$reply_count = empty( $topic_ids ) ? 0 : get_comments(
			[
				'post__in' => array_map( 'absint', $topic_ids ),
				'status'   => 'approve',
				'type'     => 'comment',
				'count'    => true,
			]
		);

		return [
			'total'    => count( $topic_ids ),
			'open'     => (int) $open_query->found_posts,
			'resolved' => $resolved_count,
			'replies'  => (int) $reply_count,
		];
	}

	public static function topic_status_label( WP_Post $post ): string {
		if ( class_exists( 'YoOhw_Post_Status_Resolved' ) && YoOhw_Post_Status_Resolved::STATUS === $post->post_status ) {
			return __( 'Resolved', 'yoohw-support-portal' );
		}

		return __( 'Open', 'yoohw-support-portal' );
	}

	public static function topic_status_class( WP_Post $post ): string {
		if ( class_exists( 'YoOhw_Post_Status_Resolved' ) && YoOhw_Post_Status_Resolved::STATUS === $post->post_status ) {
			return 'is-resolved';
		}

		return 'is-open';
	}

	public static function topic_category_meta( WP_Post $post ): string {
		$path = self::topic_category_path( $post );

		if ( empty( $path ) ) {
			$path = [ __( 'Uncategorized', 'yoohw-support-portal' ) ];
		}

		$html = '<span class="yoohw-topic-meta yoohw-topic-category-meta">';
		$html .= YoOhw_Support_Icons::render( 'tag' );
		$html .= '<span class="yoohw-topic-category-path">';

		foreach ( $path as $index => $label ) {
			if ( $index > 0 ) {
				$html .= '<span class="yoohw-topic-category-separator">' . YoOhw_Support_Icons::render( 'chevron-right', [ 'class' => 'yoohw-topic-category-separator-icon' ] ) . '</span>';
			}

			$html .= '<span class="yoohw-topic-category-name">' . esc_html( $label ) . '</span>';
		}

		$html .= '</span>';
		$html .= '</span>';

		return $html;
	}

	public static function output_topic_category_meta( WP_Post $post ): void {
		$allowed         = YoOhw_Support_Icons::allowed_html();
		$allowed['span'] = [ 'class' => true ];

		echo wp_kses( self::topic_category_meta( $post ), $allowed );
	}

	private static function topic_category_path( WP_Post $post ): array {
		$terms = get_the_terms( $post, 'category' );

		if ( is_wp_error( $terms ) || empty( $terms ) ) {
			return [];
		}

		$term = self::primary_topic_category_term( $terms );

		if ( ! $term ) {
			return [];
		}

		$path      = [];
		$ancestors = array_reverse( get_ancestors( $term->term_id, 'category', 'taxonomy' ) );

		foreach ( $ancestors as $ancestor_id ) {
			$ancestor = get_term( $ancestor_id, 'category' );

			if ( $ancestor instanceof WP_Term ) {
				$path[] = $ancestor->name;
			}
		}

		$path[] = $term->name;

		return $path;
	}

	private static function primary_topic_category_term( array $terms ): ?WP_Term {
		$terms = array_values(
			array_filter(
				$terms,
				static function ( $term ) {
					return $term instanceof WP_Term;
				}
			)
		);

		if ( empty( $terms ) ) {
			return null;
		}

		usort(
			$terms,
			static function ( WP_Term $a, WP_Term $b ) {
				$a_depth = count( get_ancestors( $a->term_id, 'category', 'taxonomy' ) );
				$b_depth = count( get_ancestors( $b->term_id, 'category', 'taxonomy' ) );

				if ( $a_depth === $b_depth ) {
					return $a->term_id <=> $b->term_id;
				}

				return $b_depth <=> $a_depth;
			}
		);

		return $terms[0];
	}

	public static function public_topic_statuses(): array {
		$statuses = [ 'publish' ];

		if ( class_exists( 'YoOhw_Post_Status_Resolved' ) ) {
			$statuses[] = YoOhw_Post_Status_Resolved::STATUS;
		}

		return $statuses;
	}

	public static function allowed_author_ids_for_current_user(): array {
		return [ get_current_user_id() ];
	}

	public static function current_user_can_view_topic( WP_Post $post ): bool {
		if ( current_user_can( YoOhw_Support_Capabilities::VIEW_ALL_TOPICS ) ) {
			return true;
		}

		return in_array( (int) $post->post_author, self::allowed_author_ids_for_current_user(), true );
	}

	public static function current_user_can_view_isolated_topic( array $topic ): bool {
		if ( current_user_can( YoOhw_Support_Capabilities::VIEW_ALL_TOPICS ) ) {
			return true;
		}

		return in_array( absint( $topic['author_id'] ?? 0 ), self::allowed_author_ids_for_current_user(), true );
	}

	public static function isolated_category_path( int $category_id ): array {
		$term = get_term( $category_id, 'category' );

		if ( ! $term instanceof WP_Term ) {
			return [ __( 'Uncategorized', 'yoohw-support-portal' ) ];
		}

		$path = [];

		foreach ( array_reverse( get_ancestors( $term->term_id, 'category', 'taxonomy' ) ) as $ancestor_id ) {
			$ancestor = get_term( $ancestor_id, 'category' );

			if ( $ancestor instanceof WP_Term ) {
				$path[] = $ancestor->name;
			}
		}

		$path[] = $term->name;

		return $path;
	}

	public static function handle_isolated_reply_submission(): void {
		if ( ! YoOhw_Support_Settings::uses_isolated_storage() ) {
			return;
		}

		if ( ! empty( $_POST['yoohw_isolated_status'] ) ) {
			self::handle_isolated_status_submission();
			return;
		}

		if ( empty( $_POST['yoohw_isolated_reply'] ) ) {
			return;
		}

		if ( ! is_user_logged_in() ) {
			wp_die( esc_html__( 'You must be logged in to reply.', 'yoohw-support-portal' ) );
		}

		if (
			empty( $_POST['yoohw_isolated_reply_nonce'] ) ||
			! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['yoohw_isolated_reply_nonce'] ) ), 'yoohw_isolated_reply' )
		) {
			wp_die( esc_html__( 'Security check failed.', 'yoohw-support-portal' ) );
		}

		$topic_id = absint( $_POST['topic_id'] ?? 0 );
		$topic    = YoOhw_Support_Database::get_topic_by_id( $topic_id );
		$content  = isset( $_POST['comment'] ) ? wp_kses_post( wp_unslash( $_POST['comment'] ) ) : '';
		$content  = YoOhw_Shortcode::yoohw_clean_content( $content );

		if ( ! $topic || ! self::current_user_can_view_isolated_topic( $topic ) || 'resolved' === $topic['status'] ) {
			wp_die( esc_html__( 'This topic is not available for replies.', 'yoohw-support-portal' ) );
		}

		if ( '' === trim( wp_strip_all_tags( $content ) ) ) {
			wp_die( esc_html__( 'Reply content cannot be empty.', 'yoohw-support-portal' ) );
		}

		$reply_id = YoOhw_Support_Database::create_reply(
			[
				'topic_id'  => $topic_id,
				'author_id' => get_current_user_id(),
				'content'   => $content,
			]
		);

		if ( is_wp_error( $reply_id ) ) {
			wp_die( esc_html( $reply_id->get_error_message() ) );
		}

		wp_safe_redirect( YoOhw_Support_Router::topic_url( $topic ) . '#reply-' . absint( $reply_id ) );
		exit;
	}

	private static function handle_isolated_status_submission(): void {
		if ( ! current_user_can( YoOhw_Support_Capabilities::MANAGE_TOPICS ) ) {
			wp_die( esc_html__( 'You do not have permission to change topic status.', 'yoohw-support-portal' ) );
		}

		if (
			empty( $_POST['yoohw_isolated_status_nonce'] ) ||
			! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['yoohw_isolated_status_nonce'] ) ), 'yoohw_isolated_status' )
		) {
			wp_die( esc_html__( 'Security check failed.', 'yoohw-support-portal' ) );
		}

		$topic_id = absint( $_POST['topic_id'] ?? 0 );
		$status   = isset( $_POST['topic_status'] ) ? sanitize_key( wp_unslash( $_POST['topic_status'] ) ) : 'open';
		$topic    = YoOhw_Support_Database::get_topic_by_id( $topic_id );

		if ( ! $topic || ! YoOhw_Support_Database::update_topic_status( $topic_id, $status ) ) {
			wp_die( esc_html__( 'The topic status could not be updated.', 'yoohw-support-portal' ) );
		}

		wp_safe_redirect( YoOhw_Support_Router::topic_url( $topic ) );
		exit;
	}

	public static function resolve_topic( string $topic_key ) {
		$topic_key = trim( $topic_key );

		if ( '' === $topic_key ) {
			return null;
		}

		if ( ctype_digit( $topic_key ) ) {
			$post = get_post( absint( $topic_key ) );
		} else {
			$post = get_page_by_path( sanitize_title( $topic_key ), OBJECT, 'post' );
		}

		if ( ! $post || 'post' !== $post->post_type || ! in_array( $post->post_status, self::public_topic_statuses(), true ) ) {
			return null;
		}

		return $post;
	}

	private static function get_available_categories( array $user_codes, bool $is_admin, int $user_id = 0 ): array {
		$output     = [];
		$categories = get_terms(
			[
				'taxonomy'   => 'category',
				'hide_empty' => false,
				'parent'     => 0,
			]
		);

		if ( is_wp_error( $categories ) || empty( $categories ) ) {
			return [];
		}

		foreach ( $categories as $parent_category ) {
			if ( ! YoOhw_Shortcode::topic_category_selectable( (int) $parent_category->term_id, $user_id, $is_admin ) ) {
				continue;
			}

			$parent_allowed = $is_admin || YoOhw_Shortcode::user_can_access_category_effective( $user_codes, (int) $parent_category->term_id, $user_id );

			if ( ! $parent_allowed ) {
				continue;
			}

			$children = get_terms(
				[
					'taxonomy'   => 'category',
					'hide_empty' => false,
					'parent'     => $parent_category->term_id,
				]
			);

			$allowed_children = [];

			if ( ! is_wp_error( $children ) && ! empty( $children ) ) {
				foreach ( $children as $child ) {
					if ( ! YoOhw_Shortcode::topic_category_selectable( (int) $child->term_id, $user_id, $is_admin ) ) {
						continue;
					}

					if ( $is_admin || YoOhw_Shortcode::user_can_access_category_effective( $user_codes, (int) $child->term_id, $user_id ) ) {
						$allowed_children[] = $child;
					}
				}
			}

			if ( ! empty( $children ) && empty( $allowed_children ) ) {
				continue;
			}

			$output[] = [
				'term'     => $parent_category,
				'children' => $allowed_children,
			];
		}

		return $output;
	}

	private static function get_accessible_category_filter_options( array $user_codes, bool $is_admin, int $user_id ): array {
		$options            = [];
		$allowed_by_id      = [];
		$children_by_parent = [];
		$categories         = get_terms(
			[
				'taxonomy'   => 'category',
				'hide_empty' => false,
				'orderby'    => 'name',
				'order'      => 'ASC',
			]
		);

		if ( is_wp_error( $categories ) || empty( $categories ) ) {
			return [];
		}

		foreach ( $categories as $category ) {
			if ( ! $category instanceof WP_Term ) {
				continue;
			}

			if ( $category->parent > 0 ) {
				$children_by_parent[ (int) $category->parent ][] = (int) $category->term_id;
			}

			$allowed_by_id[ (int) $category->term_id ] = $is_admin || YoOhw_Shortcode::user_can_access_category_effective(
				$user_codes,
				(int) $category->term_id,
				$user_id
			);
		}

		foreach ( $categories as $category ) {
			if ( ! $category instanceof WP_Term ) {
				continue;
			}

			$term_id = (int) $category->term_id;

			if ( empty( $allowed_by_id[ $term_id ] ) ) {
				continue;
			}

			if ( self::category_has_allowed_children( $term_id, $children_by_parent, $allowed_by_id ) ) {
				continue;
			}

			$options[] = [
				'term'  => $category,
				'label' => self::category_filter_label( $category ),
			];
		}

		usort(
			$options,
			static function ( array $a, array $b ): int {
				return strcasecmp( (string) ( $a['label'] ?? '' ), (string) ( $b['label'] ?? '' ) );
			}
		);

		return $options;
	}

	private static function category_has_allowed_children( int $term_id, array $children_by_parent, array $allowed_by_id ): bool {
		foreach ( $children_by_parent[ $term_id ] ?? [] as $child_id ) {
			if ( ! empty( $allowed_by_id[ (int) $child_id ] ) ) {
				return true;
			}
		}

		return false;
	}

	private static function category_filter_label( WP_Term $term ): string {
		$labels    = [];
		$ancestors = array_reverse( get_ancestors( $term->term_id, 'category', 'taxonomy' ) );

		foreach ( $ancestors as $ancestor_id ) {
			$ancestor = get_term( $ancestor_id, 'category' );

			if ( $ancestor instanceof WP_Term ) {
				$labels[] = $ancestor->name;
			}
		}

		$labels[] = $term->name;

		return implode( ' > ', array_filter( array_map( 'trim', $labels ) ) );
	}
}
