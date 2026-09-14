<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class YoOhw_Support_Setup_Wizard {

	const STATUS_OPTION = 'yoohw_support_setup_wizard_status';
	const STATUS_PENDING = 'pending';
	const STATUS_COMPLETE = 'complete';
	const STATUS_DEFERRED = 'deferred';
	const PAGE_SLUG = 'yoohw-support-setup-wizard';
	const ACTION_COMPLETE = 'yoohw_support_complete_setup_wizard';
	const ACTION_DEFER = 'yoohw_support_defer_setup_wizard';

	public static function init(): void {
		add_action( 'admin_menu', [ __CLASS__, 'register_page' ] );
		add_action( 'admin_enqueue_scripts', [ __CLASS__, 'enqueue_admin_assets' ] );
		add_action( 'admin_post_' . self::ACTION_COMPLETE, [ __CLASS__, 'handle_complete' ] );
		add_action( 'admin_post_' . self::ACTION_DEFER, [ __CLASS__, 'handle_defer' ] );
		add_filter( 'plugin_action_links_' . plugin_basename( YOOHW_SUPPORT_PORTAL_FILE ), [ __CLASS__, 'plugin_action_links' ] );
	}

	public static function mark_pending_on_first_install( bool $is_first_install ): void {
		if ( $is_first_install && false === get_option( self::STATUS_OPTION, false ) ) {
			add_option( self::STATUS_OPTION, self::STATUS_PENDING, '', false );
		}
	}

	public static function register_page(): void {
		$hook = add_submenu_page(
			null,
			__( 'Set up Support Portal', 'yoohw-support-portal' ),
			__( 'Support Portal Setup', 'yoohw-support-portal' ),
			YoOhw_Support_Capabilities::MANAGE_SETTINGS,
			self::PAGE_SLUG,
			[ __CLASS__, 'render_page' ]
		);

		unset( $hook );
	}

	public static function enqueue_admin_assets(): void {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only page routing.
		$page = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : '';

		if ( self::PAGE_SLUG !== $page ) {
			return;
		}

		$path = YOOHW_SUPPORT_PORTAL_PATH . 'assets/css/admin.css';
		wp_enqueue_style( 'yoohw-support-portal-admin', YOOHW_SUPPORT_PORTAL_URL . 'assets/css/admin.css', [], file_exists( $path ) ? (string) filemtime( $path ) : YOOHW_SUPPORT_PORTAL_VERSION );
	}

	public static function render_page(): void {
		if ( ! current_user_can( YoOhw_Support_Capabilities::MANAGE_SETTINGS ) ) {
			wp_die( esc_html__( 'You do not have permission to set up the support portal.', 'yoohw-support-portal' ) );
		}

		$result      = YoOhw_Support_Site_Check::get_result();
		$recommended = ! empty( $result['compatible'] )
			? YoOhw_Support_Settings::STORAGE_WORDPRESS
			: YoOhw_Support_Settings::STORAGE_ISOLATED;
		?>
		<div class="wrap yoohw-setup-wizard">
			<div class="yoohw-setup-shell">
				<header class="yoohw-setup-header">
					<span class="yoohw-setup-step"><?php esc_html_e( 'Initial setup', 'yoohw-support-portal' ); ?></span>
					<h1><?php esc_html_e( 'Choose how your support data is stored', 'yoohw-support-portal' ); ?></h1>
					<p><?php esc_html_e( 'Support Portal can run independently alongside an existing website, or use native WordPress posts and comments on a dedicated support site.', 'yoohw-support-portal' ); ?></p>
				</header>

				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
					<input type="hidden" name="action" value="<?php echo esc_attr( self::ACTION_COMPLETE ); ?>">
					<?php wp_nonce_field( self::ACTION_COMPLETE ); ?>

					<div class="yoohw-setup-options">
						<?php self::render_mode_card(
							YoOhw_Support_Settings::STORAGE_ISOLATED,
							__( 'Create isolated support data', 'yoohw-support-portal' ),
							__( 'Best for an existing website, content site, store, or community where regular posts and comments must remain untouched.', 'yoohw-support-portal' ),
							[
								__( 'Stores support topics and replies in dedicated plugin tables.', 'yoohw-support-portal' ),
								__( 'Runs the support portal under the /support/ namespace.', 'yoohw-support-portal' ),
								__( 'Keeps existing posts, comments, archives, and theme pages separate.', 'yoohw-support-portal' ),
							],
							$recommended
						); ?>

						<?php self::render_mode_card(
							YoOhw_Support_Settings::STORAGE_WORDPRESS,
							__( 'Use WordPress posts and comments', 'yoohw-support-portal' ),
							__( 'Best for a new blank WordPress installation used entirely as a support portal.', 'yoohw-support-portal' ),
							[
								__( 'Stores topics as posts and replies as comments.', 'yoohw-support-portal' ),
								__( 'Works naturally with the native WordPress post and comment tools.', 'yoohw-support-portal' ),
								__( 'The plugin controls the support frontend, so this mode is not recommended for an established content site.', 'yoohw-support-portal' ),
							],
							$recommended
						); ?>
					</div>

					<div class="yoohw-setup-note">
						<strong><?php esc_html_e( 'Before you continue', 'yoohw-support-portal' ); ?></strong>
						<span><?php esc_html_e( 'Switching modes later does not migrate or delete data. Dedicated database tables are created only after you finish setup with the isolated mode selected.', 'yoohw-support-portal' ); ?></span>
					</div>

					<div class="yoohw-setup-actions">
						<button type="submit" class="button button-primary button-hero"><?php esc_html_e( 'Complete setup', 'yoohw-support-portal' ); ?></button>
						<a href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=' . self::ACTION_DEFER ), self::ACTION_DEFER ) ); ?>"><?php esc_html_e( 'Set up later', 'yoohw-support-portal' ); ?></a>
					</div>
				</form>
			</div>
		</div>
		<?php
	}

	private static function render_mode_card( string $value, string $title, string $description, array $benefits, string $recommended ): void {
		$is_recommended = $value === $recommended;
		?>
		<label class="yoohw-setup-option">
			<span class="yoohw-setup-option-heading">
				<input type="radio" name="storage_mode" value="<?php echo esc_attr( $value ); ?>" <?php checked( $is_recommended ); ?> required>
				<strong><?php echo esc_html( $title ); ?></strong>
				<?php if ( $is_recommended ) : ?>
					<span class="yoohw-setup-recommended"><?php esc_html_e( 'Recommended for this site', 'yoohw-support-portal' ); ?></span>
				<?php endif; ?>
			</span>
			<span class="yoohw-setup-description"><?php echo esc_html( $description ); ?></span>
			<ul>
				<?php foreach ( $benefits as $benefit ) : ?>
					<li><?php echo esc_html( $benefit ); ?></li>
				<?php endforeach; ?>
			</ul>
		</label>
		<?php
	}

	public static function handle_complete(): void {
		if ( ! current_user_can( YoOhw_Support_Capabilities::MANAGE_SETTINGS ) ) {
			wp_die( esc_html__( 'You do not have permission to set up the support portal.', 'yoohw-support-portal' ) );
		}

		check_admin_referer( self::ACTION_COMPLETE );

		$mode = isset( $_POST['storage_mode'] ) ? sanitize_key( wp_unslash( $_POST['storage_mode'] ) ) : '';

		if ( ! in_array( $mode, [ YoOhw_Support_Settings::STORAGE_ISOLATED, YoOhw_Support_Settings::STORAGE_WORDPRESS ], true ) ) {
			wp_safe_redirect( add_query_arg( 'setup_error', 'invalid_mode', self::url() ) );
			exit;
		}

		$stored   = get_option( YoOhw_Support_Settings::OPTION_NAME, [] );
		$settings = array_merge( YoOhw_Support_Settings::defaults(), is_array( $stored ) ? $stored : [] );
		$settings['dedicated_portal_mode'] = $mode;
		$settings['enable_page_ui']        = '1';

		update_option( YoOhw_Support_Settings::OPTION_NAME, $settings, false );
		update_option( self::STATUS_OPTION, self::STATUS_COMPLETE, false );

		wp_safe_redirect(
			add_query_arg(
				[
					'page'                => 'yoohw-support-portal',
					'yoohw_portal_setup'  => 'wizard_complete',
				],
				admin_url( 'options-general.php' )
			)
		);
		exit;
	}

	public static function plugin_action_links( array $links ): array {
		$url   = self::STATUS_PENDING === get_option( self::STATUS_OPTION, '' )
			? self::url()
			: admin_url( 'options-general.php?page=yoohw-support-portal' );
		$label = self::STATUS_PENDING === get_option( self::STATUS_OPTION, '' )
			? __( 'Run setup', 'yoohw-support-portal' )
			: __( 'Settings', 'yoohw-support-portal' );

		array_unshift( $links, '<a href="' . esc_url( $url ) . '">' . esc_html( $label ) . '</a>' );

		return $links;
	}

	public static function handle_defer(): void {
		if ( ! current_user_can( YoOhw_Support_Capabilities::MANAGE_SETTINGS ) ) {
			wp_die( esc_html__( 'You do not have permission to set up the support portal.', 'yoohw-support-portal' ) );
		}

		check_admin_referer( self::ACTION_DEFER );
		update_option( self::STATUS_OPTION, self::STATUS_DEFERRED, false );

		wp_safe_redirect( admin_url( 'plugins.php' ) );
		exit;
	}

	public static function url(): string {
		return admin_url( 'admin.php?page=' . self::PAGE_SLUG );
	}

}
