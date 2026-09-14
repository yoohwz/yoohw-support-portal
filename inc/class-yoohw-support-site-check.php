<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class YoOhw_Support_Site_Check {

	const OPTION_NAME             = 'yoohw_support_portal_site_check';
	const NOTICE_DISMISSED_OPTION = 'yoohw_support_portal_site_check_notice_dismissed';
	const PANEL_HIDDEN_USER_META  = 'yoohw_support_portal_setup_panel_hidden';
	const SCAN_VERSION            = '20260709-1';

	const ACTION_ENABLE        = 'yoohw_support_portal_enable_dedicated_mode';
	const ACTION_RESCAN        = 'yoohw_support_portal_rescan_site';
	const ACTION_CLEAN_STARTER = 'yoohw_support_portal_clean_starter_content';
	const ACTION_DISMISS       = 'yoohw_support_portal_dismiss_site_notice';
	const ACTION_HIDE_PANEL    = 'yoohw_support_portal_hide_setup_panel';
	const ACTION_SHOW_PANEL    = 'yoohw_support_portal_show_setup_panel';

	public static function init(): void {
		add_action( 'admin_notices', [ __CLASS__, 'admin_notice' ] );
		add_action( 'admin_post_' . self::ACTION_ENABLE, [ __CLASS__, 'handle_enable_dedicated_mode' ] );
		add_action( 'admin_post_' . self::ACTION_RESCAN, [ __CLASS__, 'handle_rescan' ] );
		add_action( 'admin_post_' . self::ACTION_CLEAN_STARTER, [ __CLASS__, 'handle_clean_starter_content' ] );
		add_action( 'admin_post_' . self::ACTION_DISMISS, [ __CLASS__, 'handle_dismiss_notice' ] );
		add_action( 'admin_post_' . self::ACTION_HIDE_PANEL, [ __CLASS__, 'handle_hide_setup_panel' ] );
		add_action( 'admin_post_' . self::ACTION_SHOW_PANEL, [ __CLASS__, 'handle_show_setup_panel' ] );
	}

	public static function activate(): array {
		$result = self::scan();

		self::store_result( $result );
		self::initialize_settings_for_install( $result );
		delete_option( self::NOTICE_DISMISSED_OPTION );

		return $result;
	}

	public static function get_result( bool $force = false ): array {
		$result = get_option( self::OPTION_NAME, [] );

		if (
			$force ||
			! is_array( $result ) ||
			( $result['scan_version'] ?? '' ) !== self::SCAN_VERSION
		) {
			$result = self::scan();
			self::store_result( $result );
		}

		return $result;
	}

	public static function render_setup_panel(): void {
		if ( ! current_user_can( YoOhw_Support_Capabilities::MANAGE_SETTINGS ) ) {
			return;
		}

		if ( class_exists( 'YoOhw_Support_Settings' ) && YoOhw_Support_Settings::uses_isolated_storage() ) {
			return;
		}

		if ( get_user_meta( get_current_user_id(), self::PANEL_HIDDEN_USER_META, true ) ) {
			?>
			<div class="notice notice-info inline yoohw-site-check-hidden">
				<div style="display:flex;align-items:center;gap:8px;padding:10px 0;">
					<strong><?php esc_html_e( 'Dedicated portal setup is hidden.', 'yoohw-support-portal' ); ?></strong>
					<?php self::render_action_button( self::ACTION_SHOW_PANEL, __( 'Show setup', 'yoohw-support-portal' ), 'link' ); ?>
				</div>
			</div>
			<?php
			return;
		}

		$result        = self::get_result();
		$mode_enabled  = class_exists( 'YoOhw_Support_Settings' ) && YoOhw_Support_Settings::dedicated_portal_mode_enabled();
		$starter_items = self::result_items( $result, 'starter_content' );
		$status_class  = ! empty( $result['compatible'] ) ? 'is-compatible' : 'has-risks';
		$status_label  = self::status_label( $result, $starter_items );
		$checked_at    = ! empty( $result['checked_at'] ) ? (int) $result['checked_at'] : 0;
		$checked_label = $checked_at ? date_i18n( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $checked_at ) : '';
		$issues        = self::result_items( $result, 'issues' );
		$warnings      = self::result_items( $result, 'warnings' );
		$counts        = isset( $result['counts'] ) && is_array( $result['counts'] ) ? $result['counts'] : [];

		?>
		<div class="yoohw-site-check-panel <?php echo esc_attr( $status_class ); ?>">
			<div class="yoohw-site-check-header">
				<div>
					<h2><?php esc_html_e( 'Dedicated portal setup', 'yoohw-support-portal' ); ?></h2>
					<p>
						<?php
						echo esc_html__(
							'This plugin is designed to turn a WordPress install into a dedicated support portal. New WordPress sites may include sample content; the scan separates that from real site content.',
							'yoohw-support-portal'
						);
						?>
					</p>
				</div>
				<div class="yoohw-site-check-header-actions">
					<span class="yoohw-site-check-badge">
						<?php echo esc_html( $status_label ); ?>
					</span>
					<?php self::render_action_button( self::ACTION_HIDE_PANEL, __( 'Hide setup', 'yoohw-support-portal' ), 'link' ); ?>
				</div>
			</div>

			<div class="yoohw-site-check-grid">
				<div class="yoohw-site-check-summary">
					<p>
						<strong><?php esc_html_e( 'Portal mode:', 'yoohw-support-portal' ); ?></strong>
						<?php echo esc_html( $mode_enabled ? __( 'Enabled', 'yoohw-support-portal' ) : __( 'Disabled', 'yoohw-support-portal' ) ); ?>
					</p>
					<?php if ( $checked_label ) : ?>
						<p>
							<strong><?php esc_html_e( 'Last scan:', 'yoohw-support-portal' ); ?></strong>
							<?php echo esc_html( $checked_label ); ?>
						</p>
					<?php endif; ?>
					<p><?php echo esc_html( $result['summary'] ?? '' ); ?></p>

					<div class="yoohw-site-check-actions">
						<?php self::render_action_button( self::ACTION_RESCAN, __( 'Scan again', 'yoohw-support-portal' ), 'secondary' ); ?>
						<?php if ( $starter_items ) : ?>
							<?php
							self::render_action_button(
								self::ACTION_CLEAN_STARTER,
								__( 'Remove sample content', 'yoohw-support-portal' ),
								'secondary',
								__( 'Remove the WordPress starter post, sample page, default comment, and privacy policy draft detected by this scan?', 'yoohw-support-portal' )
							);
							?>
						<?php endif; ?>
						<?php if ( ! $mode_enabled ) : ?>
							<?php self::render_action_button( self::ACTION_ENABLE, __( 'Enable portal mode', 'yoohw-support-portal' ), 'primary' ); ?>
						<?php endif; ?>
					</div>
				</div>

				<div class="yoohw-site-check-counts" aria-label="<?php esc_attr_e( 'Site scan counts', 'yoohw-support-portal' ); ?>">
					<?php foreach ( self::count_labels() as $key => $label ) : ?>
						<div>
							<span><?php echo esc_html( $label ); ?></span>
							<strong><?php echo esc_html( (string) ( $counts[ $key ] ?? 0 ) ); ?></strong>
						</div>
					<?php endforeach; ?>
				</div>
			</div>

			<?php if ( $starter_items ) : ?>
				<div class="yoohw-site-check-list yoohw-site-check-starter">
					<h3><?php esc_html_e( 'WordPress starter content', 'yoohw-support-portal' ); ?></h3>
					<p>
						<?php
						echo esc_html__(
							'These items are created by WordPress during installation and are safe to remove before launching a dedicated support portal.',
							'yoohw-support-portal'
						);
						?>
					</p>
					<ul>
						<?php foreach ( $starter_items as $item ) : ?>
							<li><?php echo esc_html( $item['message'] ); ?></li>
						<?php endforeach; ?>
					</ul>
				</div>
			<?php endif; ?>

			<?php if ( $issues ) : ?>
				<div class="yoohw-site-check-list">
					<h3><?php esc_html_e( 'Issues to review', 'yoohw-support-portal' ); ?></h3>
					<ul>
						<?php foreach ( $issues as $issue ) : ?>
							<li><?php echo esc_html( $issue['message'] ); ?></li>
						<?php endforeach; ?>
					</ul>
				</div>
			<?php endif; ?>

			<?php if ( $warnings ) : ?>
				<div class="yoohw-site-check-list">
					<h3><?php esc_html_e( 'Warnings', 'yoohw-support-portal' ); ?></h3>
					<ul>
						<?php foreach ( $warnings as $warning ) : ?>
							<li><?php echo esc_html( $warning['message'] ); ?></li>
						<?php endforeach; ?>
					</ul>
				</div>
			<?php endif; ?>
		</div>

		<?php
	}

	public static function admin_notice(): void {
		global $pagenow;

		if ( ! current_user_can( YoOhw_Support_Capabilities::MANAGE_SETTINGS ) || self::is_setup_page() || 'plugins.php' !== $pagenow ) {
			return;
		}

		if ( class_exists( 'YoOhw_Support_Settings' ) && YoOhw_Support_Settings::uses_isolated_storage() ) {
			return;
		}

		if ( get_option( self::NOTICE_DISMISSED_OPTION, false ) ) {
			return;
		}

		$result       = self::get_result();
		$mode_enabled = class_exists( 'YoOhw_Support_Settings' ) && YoOhw_Support_Settings::dedicated_portal_mode_enabled();
		$starter_items = self::result_items( $result, 'starter_content' );

		if ( $mode_enabled && ! empty( $result['compatible'] ) && ! $starter_items ) {
			return;
		}

		$setup_url = self::settings_url();

		if ( ! $mode_enabled ) {
			$message = __(
				'YoOhw Support Portal is active, but Dedicated Portal Mode is disabled. Review the site scan before allowing the plugin to take over the frontend.',
				'yoohw-support-portal'
			);
			$class = 'notice notice-warning';
		} elseif ( $starter_items && ! empty( $result['compatible'] ) ) {
			$message = __(
				'YoOhw Support Portal found WordPress starter content. This is normal on a new install; open setup if you want to remove it.',
				'yoohw-support-portal'
			);
			$class = 'notice notice-info';
		} else {
			$message = __(
				'YoOhw Support Portal detected existing site content. This plugin is intended for a dedicated support portal site, so review the setup scan.',
				'yoohw-support-portal'
			);
			$class = 'notice notice-info';
		}

		?>
		<div class="<?php echo esc_attr( $class ); ?>">
			<p><strong><?php esc_html_e( 'YoOhw Support Portal', 'yoohw-support-portal' ); ?>:</strong> <?php echo esc_html( $message ); ?></p>
			<div class="yoohw-site-check-notice-actions" style="display:flex;align-items:center;gap:6px;margin:0 0 12px;">
				<a class="button button-primary" href="<?php echo esc_url( $setup_url ); ?>">
					<?php esc_html_e( 'Open setup', 'yoohw-support-portal' ); ?>
				</a>
				<?php if ( ! $mode_enabled ) : ?>
					<?php self::render_action_button( self::ACTION_ENABLE, __( 'Enable anyway', 'yoohw-support-portal' ), 'secondary' ); ?>
				<?php endif; ?>
				<?php if ( $starter_items ) : ?>
					<?php
					self::render_action_button(
						self::ACTION_CLEAN_STARTER,
						__( 'Remove sample content', 'yoohw-support-portal' ),
						'secondary',
						__( 'Remove the WordPress starter post, sample page, default comment, and privacy policy draft detected by this scan?', 'yoohw-support-portal' )
					);
					?>
				<?php endif; ?>
				<?php self::render_action_button( self::ACTION_DISMISS, __( 'Dismiss', 'yoohw-support-portal' ), 'link' ); ?>
			</div>
		</div>
		<?php
	}

	public static function handle_enable_dedicated_mode(): void {
		self::verify_action_request( self::ACTION_ENABLE );

		if ( ! class_exists( 'YoOhw_Support_Settings' ) ) {
			include_once YOOHW_SUPPORT_PORTAL_PATH . 'inc/class-yoohw-support-settings.php';
		}

		$settings = YoOhw_Support_Settings::get();
		$settings['dedicated_portal_mode'] = in_array(
			YoOhw_Support_Settings::storage_mode(),
			[ YoOhw_Support_Settings::STORAGE_WORDPRESS, YoOhw_Support_Settings::STORAGE_ISOLATED ],
			true
		)
			? YoOhw_Support_Settings::storage_mode()
			: YoOhw_Support_Settings::STORAGE_WORDPRESS;
		$settings['enable_page_ui']        = '1';

		update_option( YoOhw_Support_Settings::OPTION_NAME, $settings, false );
		delete_option( self::NOTICE_DISMISSED_OPTION );

		if ( ! class_exists( 'YoOhw_Support_Router' ) ) {
			include_once YOOHW_SUPPORT_PORTAL_PATH . 'inc/class-yoohw-support-router.php';
		}

		YoOhw_Support_Router::activate();

		wp_safe_redirect( add_query_arg( 'yoohw_portal_setup', 'enabled', self::settings_url() ) );
		exit;
	}

	public static function handle_rescan(): void {
		self::verify_action_request( self::ACTION_RESCAN );
		self::get_result( true );
		delete_option( self::NOTICE_DISMISSED_OPTION );

		wp_safe_redirect( add_query_arg( 'yoohw_portal_setup', 'rescanned', self::settings_url() ) );
		exit;
	}

	public static function handle_clean_starter_content(): void {
		self::verify_action_request( self::ACTION_CLEAN_STARTER );

		$result        = self::get_result( true );
		$starter_items = self::result_items( $result, 'starter_content' );
		$deleted       = self::delete_starter_content( $starter_items );

		self::store_result( self::scan() );
		delete_option( self::NOTICE_DISMISSED_OPTION );

		wp_safe_redirect(
			add_query_arg(
				[
					'yoohw_portal_setup'   => 'starter_cleaned',
					'yoohw_starter_removed' => $deleted,
				],
				self::settings_url()
			)
		);
		exit;
	}

	public static function handle_dismiss_notice(): void {
		self::verify_action_request( self::ACTION_DISMISS );
		update_option( self::NOTICE_DISMISSED_OPTION, time(), false );

		wp_safe_redirect( wp_get_referer() ?: self::settings_url() );
		exit;
	}

	public static function handle_hide_setup_panel(): void {
		self::verify_action_request( self::ACTION_HIDE_PANEL );
		update_user_meta( get_current_user_id(), self::PANEL_HIDDEN_USER_META, '1' );

		wp_safe_redirect( self::settings_url() );
		exit;
	}

	public static function handle_show_setup_panel(): void {
		self::verify_action_request( self::ACTION_SHOW_PANEL );
		delete_user_meta( get_current_user_id(), self::PANEL_HIDDEN_USER_META );

		wp_safe_redirect( self::settings_url() );
		exit;
	}

	public static function scan(): array {
		$issues   = [];
		$warnings = [];

		$post_ids       = self::content_post_ids( 'post' );
		$page_ids       = self::content_post_ids( 'page' );
		$attachment_ids = self::content_post_ids( 'attachment' );
		$comment_ids    = self::content_comment_ids();

		$starter_content      = array_merge(
			self::starter_post_items( $post_ids, 'post' ),
			self::starter_post_items( $page_ids, 'page' ),
			self::starter_comment_items( $comment_ids )
		);
		$non_default_posts    = self::filter_default_posts( $post_ids, 'post' );
		$non_default_pages    = self::filter_default_posts( $page_ids, 'page' );
		$non_default_comments = self::filter_default_comments( $comment_ids );
		$route_conflicts      = self::route_conflicts();
		$cpt_counts           = self::public_custom_post_type_counts();

		if ( $non_default_posts ) {
			$issues[] = self::item(
				'existing_posts',
				sprintf(
					/* translators: %d: number of existing posts. */
					_n( '%d existing post was found.', '%d existing posts were found.', count( $non_default_posts ), 'yoohw-support-portal' ),
					count( $non_default_posts )
				)
			);
		}

		if ( $non_default_pages ) {
			$issues[] = self::item(
				'existing_pages',
				sprintf(
					/* translators: %d: number of existing pages. */
					_n( '%d existing page was found.', '%d existing pages were found.', count( $non_default_pages ), 'yoohw-support-portal' ),
					count( $non_default_pages )
				)
			);
		}

		if ( $non_default_comments ) {
			$issues[] = self::item(
				'existing_comments',
				sprintf(
					/* translators: %d: number of existing comments. */
					_n( '%d existing comment was found.', '%d existing comments were found.', count( $non_default_comments ), 'yoohw-support-portal' ),
					count( $non_default_comments )
				)
			);
		}

		if ( $attachment_ids ) {
			$issues[] = self::item(
				'existing_media',
				sprintf(
					/* translators: %d: number of media attachments. */
					_n( '%d media attachment was found.', '%d media attachments were found.', count( $attachment_ids ), 'yoohw-support-portal' ),
					count( $attachment_ids )
				)
			);
		}

		foreach ( $cpt_counts as $post_type => $count ) {
			if ( $count > 0 ) {
				$issues[] = self::item(
					'custom_post_type_' . $post_type,
					sprintf(
						/* translators: 1: post type key, 2: number of posts. */
						__( 'Public custom post type "%1$s" contains %2$d item(s).', 'yoohw-support-portal' ),
						$post_type,
						$count
					)
				);
			}
		}

		if ( $route_conflicts ) {
			$issues[] = self::item(
				'route_conflicts',
				sprintf(
					/* translators: %s: comma-separated page paths. */
					__( 'Existing pages conflict with portal routes: %s.', 'yoohw-support-portal' ),
					implode( ', ', $route_conflicts )
				)
			);
		}

		if ( '' === (string) get_option( 'permalink_structure', '' ) ) {
			$warnings[] = self::item(
				'plain_permalinks',
				__( 'Pretty permalinks are disabled. The portal can still run, but clean URLs require a permalink structure.', 'yoohw-support-portal' )
			);
		}

		$user_count = function_exists( 'count_users' ) ? count_users() : [ 'total_users' => 0 ];
		$user_total = isset( $user_count['total_users'] ) ? (int) $user_count['total_users'] : 0;

		if ( $user_total > 1 ) {
			$warnings[] = self::item(
				'multiple_users',
				sprintf(
					/* translators: %d: number of users. */
					__( '%d user accounts exist. Confirm they should belong to this support portal.', 'yoohw-support-portal' ),
					$user_total
				)
			);
		}

		$active_plugins = self::active_plugin_slugs();

		if ( count( $active_plugins ) > 1 ) {
			$warnings[] = self::item(
				'active_plugins',
				sprintf(
					/* translators: %d: number of active plugins. */
					__( '%d active plugins were found. Confirm they are intended for the support portal.', 'yoohw-support-portal' ),
					count( $active_plugins )
				)
			);
		}

		$compatible = empty( $issues );
		$summary    = self::scan_summary( $compatible, ! empty( $starter_content ) );

		return [
			'scan_version' => self::SCAN_VERSION,
			'checked_at'   => time(),
			'compatible'   => $compatible,
			'summary'      => $summary,
			'counts'       => [
				'posts'       => count( $non_default_posts ),
				'pages'       => count( $non_default_pages ),
				'comments'    => count( $non_default_comments ),
				'starter'     => count( $starter_content ),
				'media'       => count( $attachment_ids ),
				'users'       => $user_total,
				'plugins'     => count( $active_plugins ),
				'cpt_items'   => array_sum( $cpt_counts ),
				'conflicts'   => count( $route_conflicts ),
			],
			'starter_content' => $starter_content,
			'issues'       => $issues,
			'warnings'     => $warnings,
		];
	}

	private static function initialize_settings_for_install( array $result ): void {
		if ( ! class_exists( 'YoOhw_Support_Settings' ) ) {
			return;
		}

		$existing = get_option( YoOhw_Support_Settings::OPTION_NAME, false );

		if ( ! is_array( $existing ) ) {
			$settings = YoOhw_Support_Settings::defaults();
			$settings['dedicated_portal_mode'] = '0';
			update_option( YoOhw_Support_Settings::OPTION_NAME, $settings, false );
			return;
		}

		if ( ! array_key_exists( 'dedicated_portal_mode', $existing ) ) {
			$existing['dedicated_portal_mode'] = YoOhw_Support_Settings::STORAGE_WORDPRESS;
			update_option( YoOhw_Support_Settings::OPTION_NAME, $existing, false );
		}
	}

	private static function content_post_ids( string $post_type ): array {
		$statuses = 'attachment' === $post_type
			? [ 'inherit', 'private' ]
			: [ 'publish', 'future', 'draft', 'pending', 'private' ];

		$query = new WP_Query(
			[
				'post_type'              => $post_type,
				'post_status'            => $statuses,
				'posts_per_page'         => -1,
				'fields'                 => 'ids',
				'no_found_rows'          => true,
				'ignore_sticky_posts'    => true,
				'update_post_meta_cache' => false,
				'update_post_term_cache' => false,
			]
		);

		return array_map( 'absint', $query->posts );
	}

	private static function content_comment_ids(): array {
		$comments = get_comments(
			[
				'status'  => 'all',
				'type'    => 'comment',
				'fields'  => 'ids',
				'number'  => 0,
				'orderby' => 'comment_ID',
				'order'   => 'ASC',
			]
		);

		return is_array( $comments ) ? array_map( 'absint', $comments ) : [];
	}

	private static function filter_default_posts( array $post_ids, string $post_type ): array {
		$output = [];

		foreach ( $post_ids as $post_id ) {
			$post = get_post( $post_id );

			if ( ! $post || self::is_default_wordpress_post( $post, $post_type ) ) {
				continue;
			}

			$output[] = $post_id;
		}

		return $output;
	}

	private static function filter_default_comments( array $comment_ids ): array {
		$output = [];

		foreach ( $comment_ids as $comment_id ) {
			$comment = get_comment( $comment_id );

			if ( ! $comment || self::is_default_wordpress_comment( $comment ) ) {
				continue;
			}

			$output[] = $comment_id;
		}

		return $output;
	}

	private static function starter_post_items( array $post_ids, string $post_type ): array {
		$items = [];

		foreach ( $post_ids as $post_id ) {
			$post = get_post( $post_id );

			if ( ! $post ) {
				continue;
			}

			$starter_type = self::wordpress_starter_post_type( $post, $post_type );

			if ( '' === $starter_type ) {
				continue;
			}

			$items[] = self::item(
				$starter_type,
				self::starter_post_message( $post, $starter_type ),
				[
					'object_type'  => 'post',
					'post_id'      => (int) $post->ID,
					'post_type'    => $post->post_type,
					'starter_type' => $starter_type,
				]
			);
		}

		return $items;
	}

	private static function starter_comment_items( array $comment_ids ): array {
		$items = [];

		foreach ( $comment_ids as $comment_id ) {
			$comment = get_comment( $comment_id );

			if ( ! $comment || ! self::is_default_wordpress_comment( $comment ) ) {
				continue;
			}

			$items[] = self::item(
				'default_comment',
				__( 'Default WordPress comment.', 'yoohw-support-portal' ),
				[
					'object_type' => 'comment',
					'comment_id'  => (int) $comment->comment_ID,
				]
			);
		}

		return $items;
	}

	private static function is_default_wordpress_post( WP_Post $post, string $post_type ): bool {
		return '' !== self::wordpress_starter_post_type( $post, $post_type );
	}

	private static function wordpress_starter_post_type( WP_Post $post, string $post_type ): string {
		if ( $post->post_type !== $post_type ) {
			return '';
		}

		if ( 'post' === $post_type && self::is_default_first_post( $post ) ) {
			return 'default_post';
		}

		if ( 'page' === $post_type && self::is_default_sample_page( $post ) ) {
			return 'sample_page';
		}

		if ( 'page' === $post_type && self::is_default_privacy_policy_page( $post ) ) {
			return 'privacy_policy';
		}

		return '';
	}

	private static function is_default_first_post( WP_Post $post ): bool {
		if ( 'post' !== $post->post_type || 'publish' !== $post->post_status ) {
			return false;
		}

		$title          = self::normalize_content_match_value( $post->post_title );
		$content        = self::normalize_content_match_value( $post->post_content );
		$post_name      = (string) $post->post_name;
		$core_title     = 'hello world!';
		$core_content   = 'welcome to wordpress this is your first post edit or delete it then start writing';
		$is_known_slug  = 'hello-world' === $post_name;
		$is_known_title = in_array( $title, array_unique( [ 'hello world', 'hello world!', $core_title ] ), true );
		$has_core_copy  = false !== strpos( $content, 'welcome to wordpress' ) || false !== strpos( $content, 'this is your first post' ) || ( '' !== $core_content && false !== strpos( $content, $core_content ) );

		if ( $is_known_slug && ( $is_known_title || $has_core_copy || self::post_dates_match( $post ) ) ) {
			return true;
		}

		return false;
	}

	private static function is_default_sample_page( WP_Post $post ): bool {
		if ( 'page' !== $post->post_type || 'publish' !== $post->post_status || self::page_has_site_assignment( (int) $post->ID ) ) {
			return false;
		}

		$title          = self::normalize_content_match_value( $post->post_title );
		$content        = self::normalize_content_match_value( $post->post_content );
		$post_name      = (string) $post->post_name;
		$core_slug      = 'sample-page';
		$core_title     = 'sample page';
		$is_known_slug  = in_array( $post_name, array_unique( [ 'sample-page', $core_slug, sanitize_title( $core_slug ) ] ), true );
		$is_known_title = in_array( $title, array_unique( [ 'sample page', $core_title ] ), true );
		$has_core_copy  = false !== strpos( $content, 'this is an example page' ) || false !== strpos( $content, 'as a new wordpress user' );

		if ( $is_known_slug && ( $is_known_title || $has_core_copy || self::post_dates_match( $post ) ) ) {
			return true;
		}

		return false;
	}

	private static function is_default_privacy_policy_page( WP_Post $post ): bool {
		if ( 'page' !== $post->post_type || 'draft' !== $post->post_status || ! self::post_dates_match( $post ) ) {
			return false;
		}

		$privacy_page_id = (int) get_option( 'wp_page_for_privacy_policy', 0 );

		if ( $privacy_page_id === (int) $post->ID ) {
			return true;
		}

		$core_slug = 'privacy-policy';

		return self::fresh_site_enabled() && 3 === (int) $post->ID && in_array( (string) $post->post_name, array_unique( [ 'privacy-policy', $core_slug, sanitize_title( $core_slug ) ] ), true );
	}

	private static function is_default_wordpress_comment( WP_Comment $comment ): bool {
		$content = strtolower( wp_strip_all_tags( $comment->comment_content ) );
		$is_default_location = 1 === (int) $comment->comment_ID || 1 === (int) $comment->comment_post_ID;
		$core_author = 'a wordpress commenter';

		if ( 'wapuu@wordpress.example' === strtolower( (string) $comment->comment_author_email ) && $is_default_location ) {
			return true;
		}

		if (
			in_array( self::normalize_content_match_value( (string) $comment->comment_author ), array_unique( [ 'a wordpress commenter', $core_author ] ), true ) &&
			false !== strpos( $content, 'this is a comment' )
		) {
			return true;
		}

		return false;
	}

	private static function route_conflicts(): array {
		$conflicts = [];
		$paths     = class_exists( 'YoOhw_Support_Router' ) ? YoOhw_Support_Router::route_paths() : [];

		foreach ( $paths as $path ) {
			$path = trim( (string) $path, '/' );

			if ( '' === $path ) {
				continue;
			}

			if ( get_page_by_path( $path ) ) {
				$conflicts[] = '/' . $path . '/';
			}
		}

		if ( get_page_by_path( 'topic' ) ) {
			$conflicts[] = '/topic/';
		}

		return array_values( array_unique( $conflicts ) );
	}

	private static function public_custom_post_type_counts(): array {
		$post_types = get_post_types( [ 'public' => true ], 'names' );
		$post_types = array_diff( $post_types, [ 'post', 'page', 'attachment' ] );
		$counts     = [];

		foreach ( $post_types as $post_type ) {
			$count = wp_count_posts( $post_type );

			if ( ! $count ) {
				continue;
			}

			$total = 0;

			foreach ( [ 'publish', 'future', 'draft', 'pending', 'private' ] as $status ) {
				$total += isset( $count->{$status} ) ? (int) $count->{$status} : 0;
			}

			if ( $total > 0 ) {
				$counts[ $post_type ] = $total;
			}
		}

		return $counts;
	}

	private static function active_plugin_slugs(): array {
		$active = get_option( 'active_plugins', [] );
		$active = is_array( $active ) ? $active : [];

		return array_values(
			array_filter(
				array_map(
					static function ( $plugin ) {
						if ( ! is_string( $plugin ) ) {
							return '';
						}

						$plugin = sanitize_text_field( $plugin );

						if ( defined( 'WP_PLUGIN_DIR' ) && ! file_exists( trailingslashit( WP_PLUGIN_DIR ) . $plugin ) ) {
							return '';
						}

						return $plugin;
					},
					$active
				)
			)
		);
	}

	private static function item( string $code, string $message, array $extra = [] ): array {
		return array_merge(
			[
				'code'    => sanitize_key( $code ),
				'message' => $message,
			],
			$extra
		);
	}

	private static function scan_summary( bool $compatible, bool $has_starter_content ): string {
		if ( $compatible && $has_starter_content ) {
			return __( 'Only WordPress starter content was found. This is normal on a new install; you can remove it before launching the support portal.', 'yoohw-support-portal' );
		}

		if ( $compatible ) {
			return __( 'The site looks clean enough for a dedicated support portal install.', 'yoohw-support-portal' );
		}

		return __( 'The site has existing content or route conflicts. Portal mode can still be enabled manually, but review the data first.', 'yoohw-support-portal' );
	}

	private static function status_label( array $result, array $starter_items ): string {
		if ( empty( $result['compatible'] ) ) {
			return __( 'Review before enabling portal mode', 'yoohw-support-portal' );
		}

		if ( $starter_items ) {
			return __( 'Ready with WordPress starter content', 'yoohw-support-portal' );
		}

		return __( 'Ready for dedicated portal mode', 'yoohw-support-portal' );
	}

	private static function result_items( array $result, string $key ): array {
		$items = isset( $result[ $key ] ) && is_array( $result[ $key ] ) ? $result[ $key ] : [];

		return array_values(
			array_filter(
				$items,
				static function ( $item ) {
					return is_array( $item ) && ! empty( $item['message'] );
				}
			)
		);
	}

	private static function count_labels(): array {
		return [
			'posts'     => __( 'Posts', 'yoohw-support-portal' ),
			'pages'     => __( 'Pages', 'yoohw-support-portal' ),
			'comments'  => __( 'Comments', 'yoohw-support-portal' ),
			'starter'   => __( 'Starter content', 'yoohw-support-portal' ),
			'media'     => __( 'Media', 'yoohw-support-portal' ),
			'users'     => __( 'Users', 'yoohw-support-portal' ),
			'plugins'   => __( 'Active plugins', 'yoohw-support-portal' ),
			'cpt_items' => __( 'CPT items', 'yoohw-support-portal' ),
			'conflicts' => __( 'Route conflicts', 'yoohw-support-portal' ),
		];
	}

	private static function render_action_button( string $action, string $label, string $style, string $confirm = '' ): void {
		$class = 'primary' === $style ? 'button button-primary' : ( 'link' === $style ? 'button-link' : 'button' );

		?>
		<form class="yoohw-inline-action" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="display:inline-flex;margin:0 6px 0 0;">
			<input type="hidden" name="action" value="<?php echo esc_attr( $action ); ?>">
			<?php wp_nonce_field( $action ); ?>
			<button
				type="submit"
				class="<?php echo esc_attr( $class ); ?>"
				<?php if ( '' !== $confirm ) : ?>
					onclick="return confirm('<?php echo esc_js( $confirm ); ?>');"
				<?php endif; ?>
			>
				<?php echo esc_html( $label ); ?>
			</button>
		</form>
		<?php
	}

	private static function verify_action_request( string $action ): void {
		if ( ! current_user_can( YoOhw_Support_Capabilities::MANAGE_SETTINGS ) ) {
			wp_die( esc_html__( 'You are not allowed to do this.', 'yoohw-support-portal' ) );
		}

		check_admin_referer( $action );
	}

	private static function settings_url(): string {
		return admin_url( 'options-general.php?page=yoohw-support-portal' );
	}

	private static function is_setup_page(): bool {
		if ( ! is_admin() ) {
			return false;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only admin page detection.
		$page = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : '';

		return 'yoohw-support-portal' === $page;
	}

	private static function store_result( array $result ): void {
		update_option( self::OPTION_NAME, $result, false );
	}

	private static function starter_post_message( WP_Post $post, string $starter_type ): string {
		$title = get_the_title( $post );
		$title = '' !== $title ? $title : sprintf( '#%d', (int) $post->ID );

		if ( 'default_post' === $starter_type ) {
			return sprintf(
				/* translators: %s: post title. */
				__( 'Default WordPress post: "%s".', 'yoohw-support-portal' ),
				$title
			);
		}

		if ( 'sample_page' === $starter_type ) {
			return sprintf(
				/* translators: %s: page title. */
				__( 'Default WordPress sample page: "%s".', 'yoohw-support-portal' ),
				$title
			);
		}

		return sprintf(
			/* translators: %s: page title. */
			__( 'Default Privacy Policy draft: "%s".', 'yoohw-support-portal' ),
			$title
		);
	}

	private static function delete_starter_content( array $items ): int {
		$deleted = 0;

		foreach ( $items as $item ) {
			if ( ( $item['object_type'] ?? '' ) !== 'comment' ) {
				continue;
			}

			$comment_id = isset( $item['comment_id'] ) ? absint( $item['comment_id'] ) : 0;
			$comment    = $comment_id ? get_comment( $comment_id ) : null;

			if ( $comment && self::is_default_wordpress_comment( $comment ) && wp_delete_comment( $comment_id, true ) ) {
				$deleted++;
			}
		}

		foreach ( $items as $item ) {
			if ( ( $item['object_type'] ?? '' ) !== 'post' ) {
				continue;
			}

			$post_id = isset( $item['post_id'] ) ? absint( $item['post_id'] ) : 0;
			$post    = $post_id ? get_post( $post_id ) : null;

			if ( ! $post ) {
				continue;
			}

			$starter_type = self::wordpress_starter_post_type( $post, $post->post_type );

			if ( '' === $starter_type ) {
				continue;
			}

			if ( 'privacy_policy' === $starter_type && (int) get_option( 'wp_page_for_privacy_policy', 0 ) === $post_id ) {
				update_option( 'wp_page_for_privacy_policy', 0, false );
			}

			if ( wp_delete_post( $post_id, true ) ) {
				$deleted++;
			}
		}

		return $deleted;
	}

	private static function normalize_content_match_value( string $value ): string {
		return strtolower( trim( wp_strip_all_tags( $value ) ) );
	}

	private static function fresh_site_enabled(): bool {
		return '1' === (string) get_option( 'fresh_site', '0' );
	}

	private static function post_dates_match( WP_Post $post ): bool {
		return (string) $post->post_date_gmt === (string) $post->post_modified_gmt;
	}

	private static function page_has_site_assignment( int $post_id ): bool {
		return $post_id > 0 && in_array(
			$post_id,
			[
				(int) get_option( 'page_on_front', 0 ),
				(int) get_option( 'page_for_posts', 0 ),
			],
			true
		);
	}

}
