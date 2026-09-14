<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class YoOhw_Post_Status_Resolved {

	const STATUS = 'yoohw_resolved';
	const STATUS_VERSION_OPTION = 'yoohw_support_post_status_version';
	const STATUS_VERSION = '2';

	public static function init(): void {
		add_action( 'init', [ __CLASS__, 'register_status' ] );
		add_action( 'init', [ __CLASS__, 'maybe_migrate_legacy_status' ], 11 );

		// Show label in admin list table.
		add_filter( 'display_post_states', [ __CLASS__, 'display_post_state_label' ], 10, 2 );

		// Row actions in admin list table.
		add_filter( 'post_row_actions', [ __CLASS__, 'add_resolve_row_action' ], 10, 2 );
		add_action( 'admin_post_yoohw_support_post_resolve', [ __CLASS__, 'handle_resolve_row_action' ] );
		add_action( 'admin_post_yoohw_support_post_reopen', [ __CLASS__, 'handle_reopen_row_action' ] );
		add_action( 'admin_post_yoohw_support_post_trash', [ __CLASS__, 'handle_trash_action' ] );

		add_filter( 'bulk_actions-edit-post', [ __CLASS__, 'register_bulk_resolve_action' ] );
		add_filter( 'handle_bulk_actions-edit-post', [ __CLASS__, 'handle_bulk_resolve_action' ], 10, 3 );
		add_action( 'admin_notices', [ __CLASS__, 'bulk_resolve_admin_notice' ] );
		add_action( 'admin_enqueue_scripts', [ __CLASS__, 'enqueue_admin_assets' ] );

		add_action( 'transition_post_status', [ __CLASS__, 'close_discussion_on_resolved' ], 10, 3 );
		add_action( 'transition_post_status', [ __CLASS__, 'reopen_discussion_on_reopened' ], 10, 3 );

		// Front-end: treat resolved like publish in main queries.
		add_action( 'pre_get_posts', [ __CLASS__, 'include_resolved_in_public_queries' ], 20 );

		add_action( 'admin_bar_menu', [ __CLASS__, 'admin_bar_resolve_post_link' ], 81 );

		// Gutenberg UI control.
		add_action( 'enqueue_block_editor_assets', [ __CLASS__, 'enqueue_editor_assets' ] );

		add_filter( 'the_title', [ __CLASS__, 'add_resolved_icon_to_title' ], 10, 2 );
		add_action( 'wp_enqueue_scripts', [ __CLASS__, 'enqueue_frontend_assets' ] );

		add_filter( 'cron_schedules', [ __CLASS__, 'add_cron_schedules' ] );
		add_action( 'init', [ __CLASS__, 'maybe_schedule_auto_resolve_checker' ] );
		add_action( 'yoohw_support_auto_resolve_inactive_posts', [ __CLASS__, 'auto_resolve_inactive_posts' ] );
	}

	public static function register_status(): void {
		register_post_status( self::STATUS, [
			'label'                     => _x( 'Resolved', 'post status', 'yoohw-support-portal' ),
			'public'                    => true,
			'protected'                 => false,
			'private'                   => false,
			'exclude_from_search'       => false,
			'show_in_admin_all_list'    => true,
			'show_in_admin_status_list' => true,
			'publicly_queryable'        => true,
			'date_floating'             => true,

			// Some WP versions will also honor this for /wp/v2/statuses.
			'show_in_rest'              => true,

			/* translators: %s: Number of resolved topics. */
			'label_count'               => _n_noop(
				'Resolved <span class="count">(%s)</span>',
				'Resolved <span class="count">(%s)</span>',
				'yoohw-support-portal'
			),
		] );
	}

	public static function maybe_migrate_legacy_status(): void {
		if ( self::STATUS_VERSION === get_option( self::STATUS_VERSION_OPTION, '' ) ) {
			return;
		}

		$legacy_ids = get_posts(
			[
				'post_type'              => 'post',
				'post_status'            => 'resolved',
				'posts_per_page'         => -1,
				'fields'                 => 'ids',
				'no_found_rows'          => true,
				'update_post_meta_cache' => false,
				'update_post_term_cache' => false,
				'suppress_filters'       => false,
			]
		);

		foreach ( $legacy_ids as $post_id ) {
			wp_update_post(
				[
					'ID'          => (int) $post_id,
					'post_status' => self::STATUS,
				]
			);
		}

		update_option( self::STATUS_VERSION_OPTION, self::STATUS_VERSION, false );
	}

	public static function display_post_state_label( array $states, WP_Post $post ): array {
		if ( self::STATUS === $post->post_status ) {
			$states[ self::STATUS ] = __( 'Resolved', 'yoohw-support-portal' );
		}
		return $states;
	}

	public static function add_resolve_row_action( array $actions, WP_Post $post ): array {
		// Only for Posts list.
		if ( 'post' !== $post->post_type ) {
			return $actions;
		}

		$post_id = (int) $post->ID;

		if ( self::can_resolve_post( $post_id ) ) {
			$url    = self::resolve_post_url( $post_id, esc_url_raw( wp_unslash( $_SERVER['REQUEST_URI'] ?? admin_url( 'edit.php' ) ) ) );
			$key    = 'yoohw_support_resolve';
			$label  = __( 'Resolve', 'yoohw-support-portal' );
		} elseif ( self::can_reopen_post( $post_id ) ) {
			$url    = self::reopen_post_url( $post_id, esc_url_raw( wp_unslash( $_SERVER['REQUEST_URI'] ?? admin_url( 'edit.php' ) ) ) );
			$key    = 'yoohw_support_reopen';
			$label  = __( 'Reopen', 'yoohw-support-portal' );
		} else {
			return $actions;
		}

		$actions = array_slice( $actions, 0, 1, true )
			+ [ $key => '<a href="' . esc_url( $url ) . '">' . esc_html( $label ) . '</a>' ]
			+ array_slice( $actions, 1, null, true );

		return $actions;
	}

	public static function can_resolve_post( int $post_id ): bool {
		if ( ! $post_id || ! current_user_can( YoOhw_Support_Capabilities::MANAGE_TOPICS ) ) {
			return false;
		}

		$post = get_post( $post_id );

		if ( ! $post || 'post' !== $post->post_type ) {
			return false;
		}

		return ! in_array( $post->post_status, [ self::STATUS, 'trash' ], true );
	}

	public static function can_reopen_post( int $post_id ): bool {
		if ( ! $post_id || ! current_user_can( YoOhw_Support_Capabilities::MANAGE_TOPICS ) ) {
			return false;
		}

		$post = get_post( $post_id );

		if ( ! $post || 'post' !== $post->post_type ) {
			return false;
		}

		return self::STATUS === $post->post_status;
	}

	public static function resolve_post_url( int $post_id, string $redirect = '' ): string {
		return self::status_action_url( 'yoohw_support_post_resolve', 'yoohw_support_post_resolve_', $post_id, $redirect );
	}

	public static function reopen_post_url( int $post_id, string $redirect = '' ): string {
		return self::status_action_url( 'yoohw_support_post_reopen', 'yoohw_support_post_reopen_', $post_id, $redirect );
	}

	public static function trash_post_url( int $post_id, string $redirect = '' ): string {
		return self::status_action_url( 'yoohw_support_post_trash', 'yoohw_support_post_trash_', $post_id, $redirect );
	}

	private static function status_action_url( string $action, string $nonce_action_prefix, int $post_id, string $redirect = '' ): string {
		if ( '' === $redirect ) {
			$redirect = get_permalink( $post_id ) ?: admin_url( 'edit.php' );
		}

		if ( 0 === strpos( $redirect, '/' ) ) {
			$redirect = home_url( $redirect );
		}

		return add_query_arg(
			[
				'action'   => $action,
				'post_id'  => $post_id,
				'_wpnonce' => wp_create_nonce( $nonce_action_prefix . $post_id ),
				'redirect' => rawurlencode( $redirect ),
			],
			admin_url( 'admin-post.php' )
		);
	}

	public static function handle_resolve_row_action(): void {
		if ( ! current_user_can( YoOhw_Support_Capabilities::MANAGE_TOPICS ) ) {
			wp_die( esc_html__( 'You are not allowed to do this.', 'yoohw-support-portal' ) );
		}

		$post_id = isset( $_GET['post_id'] ) ? (int) $_GET['post_id'] : 0;
		if ( ! $post_id ) {
			wp_die( esc_html__( 'Missing post ID.', 'yoohw-support-portal' ) );
		}

		check_admin_referer( 'yoohw_support_post_resolve_' . $post_id );

		if ( ! self::can_resolve_post( $post_id ) ) {
			wp_die( esc_html__( 'You are not allowed to resolve this post.', 'yoohw-support-portal' ) );
		}

		// Update status to resolved.
		wp_update_post(
			[
				'ID'          => $post_id,
				'post_status' => self::STATUS,
			]
		);

		wp_safe_redirect( self::validated_action_redirect() );
		exit;
	}

	public static function handle_reopen_row_action(): void {
		if ( ! current_user_can( YoOhw_Support_Capabilities::MANAGE_TOPICS ) ) {
			wp_die( esc_html__( 'You are not allowed to do this.', 'yoohw-support-portal' ) );
		}

		$post_id = isset( $_GET['post_id'] ) ? (int) $_GET['post_id'] : 0;
		if ( ! $post_id ) {
			wp_die( esc_html__( 'Missing post ID.', 'yoohw-support-portal' ) );
		}

		check_admin_referer( 'yoohw_support_post_reopen_' . $post_id );

		if ( ! self::can_reopen_post( $post_id ) ) {
			wp_die( esc_html__( 'You are not allowed to reopen this post.', 'yoohw-support-portal' ) );
		}

		wp_update_post(
			[
				'ID'             => $post_id,
				'post_status'    => 'publish',
				'comment_status' => 'open',
				'ping_status'    => 'open',
			]
		);

		wp_safe_redirect( self::validated_action_redirect() );
		exit;
	}

	public static function handle_trash_action(): void {
		$post_id = isset( $_GET['post_id'] ) ? (int) $_GET['post_id'] : 0;

		if ( ! $post_id ) {
			wp_die( esc_html__( 'Missing post ID.', 'yoohw-support-portal' ) );
		}

		check_admin_referer( 'yoohw_support_post_trash_' . $post_id );

		if ( ! self::can_trash_post( $post_id ) ) {
			wp_die( esc_html__( 'You are not allowed to move this topic to Trash.', 'yoohw-support-portal' ) );
		}

		if ( ! wp_trash_post( $post_id ) ) {
			wp_die( esc_html__( 'This topic could not be moved to Trash.', 'yoohw-support-portal' ) );
		}

		wp_safe_redirect( self::validated_action_redirect() );
		exit;
	}

	private static function validated_action_redirect(): string {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended,WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Callers verify the nonce; the URL is normalized and validated against local origins below.
		$redirect = isset( $_GET['redirect'] ) ? esc_url_raw( rawurldecode( (string) wp_unslash( $_GET['redirect'] ) ) ) : admin_url( 'edit.php' );
		$admin_ok = ( 0 === strpos( $redirect, admin_url() ) );
		$home_ok  = ( 0 === strpos( $redirect, home_url() ) );

		return ( $admin_ok || $home_ok ) ? $redirect : admin_url( 'edit.php' );
	}

	public static function register_bulk_resolve_action( array $actions ): array {
		$actions['yoohw_support_resolve'] = __( 'Resolve', 'yoohw-support-portal' );
		return $actions;
	}

	public static function handle_bulk_resolve_action(
		string $redirect_to,
		string $action,
		array $post_ids
	): string {

		if ( 'yoohw_support_resolve' !== $action ) {
			return $redirect_to;
		}

		if ( ! current_user_can( YoOhw_Support_Capabilities::MANAGE_TOPICS ) ) {
			return $redirect_to;
		}

		$resolved = 0;

		foreach ( $post_ids as $post_id ) {
			$post_id = (int) $post_id;

			if ( ! self::can_resolve_post( $post_id ) ) {
				continue;
			}

			wp_update_post(
				[
					'ID'          => $post_id,
					'post_status' => self::STATUS,
				]
			);

			$resolved++;
		}

		// Add result count to redirect URL
		return add_query_arg(
			[
				'yoohw_support_bulk_resolved' => $resolved,
			],
			$redirect_to
		);
	}

	public static function print_classic_editor_status_script(): void {
		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		if ( ! $screen || 'post' !== $screen->post_type ) {
			return;
		}

		global $post;
		$current_status = $post instanceof WP_Post ? $post->post_status : '';
		?>
		<?php
	}

	public static function print_quick_edit_status_script(): void {
		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		if ( ! $screen || 'post' !== $screen->post_type ) {
			return;
		}
		?>
		<?php
	}

	public static function bulk_resolve_admin_notice(): void {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only result count added after a nonce-protected bulk action.
		if ( ! isset( $_REQUEST['yoohw_support_bulk_resolved'] ) ) {
			return;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only result count added after a nonce-protected bulk action.
		$count = (int) $_REQUEST['yoohw_support_bulk_resolved'];
		if ( $count <= 0 ) {
			return;
		}

		printf(
			'<div class="notice notice-success is-dismissible"><p>%s</p></div>',
				esc_html(
					sprintf(
						/* translators: %d: Number of topics marked resolved. */
						_n(
							'%d post marked as resolved.',
						'%d posts marked as resolved.',
						$count,
						'yoohw-support-portal'
					),
					$count
				)
			)
		);
	}

	public static function close_discussion_on_resolved(
		string $new_status,
		string $old_status,
		WP_Post $post
	): void {

		// Only act when transitioning TO resolved
		if ( self::STATUS !== $new_status ) {
			return;
		}

		// Only for Posts (adjust if needed)
		if ( 'post' !== $post->post_type ) {
			return;
		}

		// Avoid infinite loops
		remove_action( 'transition_post_status', [ __CLASS__, 'close_discussion_on_resolved' ], 10 );

		wp_update_post(
			[
				'ID'             => $post->ID,
				'comment_status' => 'closed',
				'ping_status'    => 'closed',
			]
		);

		// Re-attach hook
		add_action( 'transition_post_status', [ __CLASS__, 'close_discussion_on_resolved' ], 10, 3 );
	}

	public static function reopen_discussion_on_reopened(
		string $new_status,
		string $old_status,
		WP_Post $post
	): void {
		if ( self::STATUS !== $old_status || 'publish' !== $new_status ) {
			return;
		}

		if ( 'post' !== $post->post_type ) {
			return;
		}

		remove_action( 'transition_post_status', [ __CLASS__, 'reopen_discussion_on_reopened' ], 10 );

		wp_update_post(
			[
				'ID'             => $post->ID,
				'comment_status' => 'open',
				'ping_status'    => 'open',
			]
		);

		add_action( 'transition_post_status', [ __CLASS__, 'reopen_discussion_on_reopened' ], 10, 3 );
	}

	public static function include_resolved_in_public_queries( WP_Query $q ): void {
		if ( is_admin() || ! $q->is_main_query() ) {
			return;
		}

		if ( ! $q->is_home() && ! $q->is_archive() && ! $q->is_search() ) {
			return;
		}

		// Respect explicit post_status set by other code.
		$explicit = $q->get( 'post_status', null );
		if ( null !== $explicit && '' !== $explicit ) {
			return;
		}

		// Only affect queries that are basically "public posts".
		$post_type = $q->get( 'post_type', 'post' );
		$is_post_query =
			(empty($post_type) || 'post' === $post_type || (is_array($post_type) && in_array('post', $post_type, true)));

		if ( ! $is_post_query ) {
			return;
		}

		$q->set( 'post_status', [ 'publish', self::STATUS ] );
	}

	public static function admin_bar_resolve_post_link( WP_Admin_Bar $wp_admin_bar ): void {
		if ( is_admin() ) {
			return;
		}

		if ( ! current_user_can( YoOhw_Support_Capabilities::MANAGE_TOPICS ) ) {
			return;
		}

		$post_id = self::get_admin_bar_post_id();
		if ( ! $post_id ) {
			return;
		}

		if ( self::can_resolve_post( (int) $post_id ) ) {
			$redirect = self::get_admin_bar_redirect_url( (int) $post_id );
			$url      = self::resolve_post_url( (int) $post_id, $redirect );
			$title    = __( 'Resolve Topic', 'yoohw-support-portal' );
			$icon     = 'dashicons-yes-alt';
			$class    = 'yo-resolve-post';
			$tooltip  = __( 'Mark this topic as resolved', 'yoohw-support-portal' );
		} elseif ( self::can_reopen_post( (int) $post_id ) ) {
			$redirect = self::get_admin_bar_redirect_url( (int) $post_id );
			$url      = self::reopen_post_url( (int) $post_id, $redirect );
			$title    = __( 'Reopen Topic', 'yoohw-support-portal' );
			$icon     = 'dashicons-controls-repeat';
			$class    = 'yo-reopen-post';
			$tooltip  = __( 'Reopen this topic for replies', 'yoohw-support-portal' );
		} else {
			return;
		}

		$wp_admin_bar->add_node(
			[
				'id'     => 'yo-topic-status-action',
				'parent' => 'root-default',
				'title'  => '<span class="ab-icon dashicons ' . esc_attr( $icon ) . '" aria-hidden="true"></span><span class="ab-label">' . esc_html( $title ) . '</span>',
				'href'   => $url,
				'meta'   => [
					'class' => $class,
					'title' => $tooltip,
				],
			]
		);

		if ( self::can_trash_post( (int) $post_id ) ) {
			$trash_redirect = self::get_admin_bar_trash_redirect_url();
			$trash_url      = self::trash_post_url( (int) $post_id, $trash_redirect );
			$confirm        = __( 'Move this topic to Trash?', 'yoohw-support-portal' );

			$wp_admin_bar->add_node(
				[
					'id'     => 'yo-topic-trash-action',
					'parent' => 'root-default',
					'title'  => '<span class="ab-icon dashicons dashicons-trash" aria-hidden="true"></span><span class="ab-label">' . esc_html__( 'Move to Trash', 'yoohw-support-portal' ) . '</span>',
					'href'   => $trash_url,
					'meta'   => [
						'class'   => 'yo-trash-post',
						'title'   => __( 'Move this topic to Trash', 'yoohw-support-portal' ),
						'onclick' => 'return window.confirm(' . wp_json_encode( $confirm ) . ');',
					],
				]
			);
		}
	}

	private static function can_trash_post( int $post_id ): bool {
		if ( ! $post_id || ! current_user_can( YoOhw_Support_Capabilities::MANAGE_TOPICS ) ) {
			return false;
		}

		$post = get_post( $post_id );

		return $post instanceof WP_Post
			&& 'post' === $post->post_type
			&& 'trash' !== $post->post_status;
	}

	private static function get_admin_bar_post_id(): int {
		if (
			class_exists( 'YoOhw_Support_Router' )
			&& 'topic' === YoOhw_Support_Router::get_view()
			&& class_exists( 'YoOhw_Support_Controller' )
		) {
			$post = YoOhw_Support_Controller::resolve_topic( YoOhw_Support_Router::get_topic_key() );

			return $post instanceof WP_Post ? (int) $post->ID : 0;
		}

		if ( is_singular( 'post' ) ) {
			return (int) get_queried_object_id();
		}

		return 0;
	}

	private static function get_admin_bar_redirect_url( int $post_id ): string {
		if (
			class_exists( 'YoOhw_Support_Router' )
			&& 'topic' === YoOhw_Support_Router::get_view()
			&& class_exists( 'YoOhw_Support_Controller' )
		) {
			return YoOhw_Support_Controller::topic_url( $post_id );
		}

		return get_permalink( $post_id ) ?: admin_url( 'edit.php' );
	}

	private static function get_admin_bar_trash_redirect_url(): string {
		if ( class_exists( 'YoOhw_Support_Router' ) ) {
			return YoOhw_Support_Router::url( 'topics' );
		}

		return admin_url( 'edit.php' );
	}

	public static function enqueue_editor_assets(): void {
		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;

		// Only for Posts. Remove this condition if you want it on other post types.
		if ( ! $screen || 'post' !== $screen->post_type ) {
			return;
		}

		$handle = 'yoohw-support-resolved-editor';

		wp_enqueue_style( 'dashicons' );

		wp_enqueue_script(
			$handle,
			plugins_url( '../assets/js/resolved-editor.js', __FILE__ ),
			[ 'wp-plugins', 'wp-element', 'wp-data', 'wp-edit-post', 'wp-components' ],
			filemtime( plugin_dir_path( __FILE__ ) . '../assets/js/resolved-editor.js' ),
			true
		);

		wp_add_inline_script(
			$handle,
			'window.YoOhwSupportResolvedStatus = ' . wp_json_encode( [
				'status'       => self::STATUS,
				'label'        => __( 'Resolved', 'yoohw-support-portal' ),
				'description'  => __( 'Support topic is resolved and visible.', 'yoohw-support-portal' ),
				'actionLabel'  => __( 'Mark as resolved', 'yoohw-support-portal' ),
				'reopenLabel'  => __( 'Reopen', 'yoohw-support-portal' ),
				'pendingLabel' => __( 'Status will be saved when you update the post.', 'yoohw-support-portal' ),
				'openLabel'    => __( 'Open', 'yoohw-support-portal' ),
			] ) . ';',
			'before'
		);

		wp_add_inline_style(
			'dashicons',
			'.yo-resolved-editor-status{display:block}.yo-resolved-editor-status__body{display:flex;align-items:center;justify-content:space-between;gap:8px;width:100%}.yo-resolved-editor-status__summary{display:inline-flex;align-items:center;gap:8px;min-width:0;font-weight:500}.yo-resolved-editor-status__icon{width:18px;height:18px;font-size:18px;line-height:18px;color:#3858e9}.yo-resolved-editor-status__label{overflow:hidden;text-overflow:ellipsis;white-space:nowrap}.yo-resolved-editor-status__note{margin:8px 0 0;color:#646970;font-size:12px;line-height:1.4}'
		);
	}

	public static function enqueue_admin_assets(): void {
		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;

		if ( ! $screen || 'post' !== $screen->post_type || ! in_array( $screen->base, [ 'post', 'edit' ], true ) ) {
			return;
		}

		$path = YOOHW_SUPPORT_PORTAL_PATH . 'assets/js/admin.js';
		wp_enqueue_script( 'yoohw-support-portal-admin', YOOHW_SUPPORT_PORTAL_URL . 'assets/js/admin.js', [], file_exists( $path ) ? (string) filemtime( $path ) : YOOHW_SUPPORT_PORTAL_VERSION, true );
		wp_localize_script(
			'yoohw-support-portal-admin',
			'YoOhwSupportAdmin',
			[
				'status'        => self::STATUS,
				'label'         => __( 'Resolved', 'yoohw-support-portal' ),
				'currentStatus' => get_post_status() ?: '',
			]
		);
	}

	public static function enqueue_frontend_assets(): void {
		if ( class_exists( 'YoOhw_Support_Router' ) && YoOhw_Support_Router::is_plugin_ui_request() ) {
			return;
		}

		$path = YOOHW_SUPPORT_PORTAL_PATH . 'assets/css/frontend-extras.css';
		wp_enqueue_style( 'yoohw-support-portal-extras', YOOHW_SUPPORT_PORTAL_URL . 'assets/css/frontend-extras.css', [], file_exists( $path ) ? (string) filemtime( $path ) : YOOHW_SUPPORT_PORTAL_VERSION );
	}

	public static function add_resolved_icon_to_title( string $title, int $post_id ): string {

		if ( is_admin() && ! wp_doing_ajax() ) {
			return $title;
		}

		$post = get_post( $post_id );

		if ( ! $post || 'post' !== $post->post_type || self::STATUS !== $post->post_status ) {
			return $title;
		}

		if ( is_feed() || ( defined( 'REST_REQUEST' ) && REST_REQUEST ) ) {
			return $title;
		}

		$icon = class_exists( 'YoOhw_Support_Icons' ) ? YoOhw_Support_Icons::render( 'circle-check' ) : '';
		$badge = '<span class="yoohw-resolved-badge" aria-label="' . esc_attr__( 'Resolved', 'yoohw-support-portal' ) . '" title="' . esc_attr__( 'Resolved', 'yoohw-support-portal' ) . '">' . $icon . '<span>' . esc_html__( 'Resolved', 'yoohw-support-portal' ) . '</span></span>';

		$allowed_html = class_exists( 'YoOhw_Support_Icons' )
			? array_merge( wp_kses_allowed_html( 'post' ), YoOhw_Support_Icons::allowed_html() )
			: wp_kses_allowed_html( 'post' );

		return $title . wp_kses( $badge, $allowed_html );
	}

	public static function add_cron_schedules( array $schedules ): array {
		if ( ! isset( $schedules['yoohw_support_hourly'] ) ) {
			$schedules['yoohw_support_hourly'] = [
				'interval' => HOUR_IN_SECONDS,
				'display'  => __( 'Once Hourly', 'yoohw-support-portal' ),
			];
		}

		return $schedules;
	}

	public static function maybe_schedule_auto_resolve_checker(): void {
		if ( ! wp_next_scheduled( 'yoohw_support_auto_resolve_inactive_posts' ) ) {
			wp_schedule_event( time() + HOUR_IN_SECONDS, 'yoohw_support_hourly', 'yoohw_support_auto_resolve_inactive_posts' );
		}
	}

	public static function auto_resolve_inactive_posts(): void {
		$posts = get_posts(
			[
				'post_type'      => 'post',
				'post_status'    => 'publish',
				'posts_per_page' => 100,
				'fields'         => 'ids',
				'orderby'        => 'modified',
				'order'          => 'ASC',
			]
		);

		if ( empty( $posts ) ) {
			return;
		}

		foreach ( $posts as $post_id ) {
			self::maybe_auto_resolve_post_after_admin_reply( (int) $post_id );
		}
	}

	private static function maybe_auto_resolve_post_after_admin_reply( int $post_id ): void {
		$post = get_post( $post_id );

		if ( ! $post || 'post' !== $post->post_type ) {
			return;
		}

		if ( self::STATUS === $post->post_status || 'trash' === $post->post_status ) {
			return;
		}

		$post_author_id = (int) $post->post_author;

		if ( $post_author_id <= 0 ) {
			return;
		}

		$latest_comments = get_comments(
			[
				'post_id' => $post_id,
				'status'  => 'approve',
				'number'  => 1,
				'orderby' => 'comment_date_gmt',
				'order'   => 'DESC',
				'type'    => 'comment',
			]
		);

		if ( empty( $latest_comments ) ) {
			return;
		}

		$latest_comment = $latest_comments[0];

		// Latest reply must be from support.
		if ( ! self::is_support_comment( $latest_comment ) ) {
			return;
		}

		$latest_comment_time = strtotime( $latest_comment->comment_date_gmt . ' GMT' );

		if ( ! $latest_comment_time ) {
			return;
		}

		// Admin reply must be older than 2 days.
		if ( $latest_comment_time > ( time() - ( 2 * DAY_IN_SECONDS ) ) ) {
			return;
		}

		// Check if the original post author replied after the latest admin reply.
		$author_replies_after_admin = get_comments(
			[
				'post_id'    => $post_id,
				'status'     => 'approve',
				'user_id'    => $post_author_id,
				'number'     => 1,
				'type'       => 'comment',
				'date_query' => [
					[
						'after'     => gmdate( 'Y-m-d H:i:s', $latest_comment_time ),
						'inclusive' => false,
						'column'    => 'comment_date_gmt',
					],
				],
			]
		);

		if ( ! empty( $author_replies_after_admin ) ) {
			return;
		}

		wp_update_post(
			[
				'ID'          => $post_id,
				'post_status' => self::STATUS,
			]
		);
	}

	private static function is_support_comment( $comment ): bool {
		if ( ! $comment instanceof WP_Comment ) {
			return false;
		}

		$is_support = false;

		if ( ! empty( $comment->user_id ) && user_can( (int) $comment->user_id, YoOhw_Support_Capabilities::MANAGE_TOPICS ) ) {
			$is_support = true;
		}

		return (bool) apply_filters( 'yoohw_support_is_support_comment', $is_support, $comment );
	}
}

YoOhw_Post_Status_Resolved::init();
