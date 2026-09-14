<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class YoOhw_Support_Settings {

	const OPTION_NAME        = 'yoohw_support_portal_settings';
	const LEGACY_OPTION_NAME = 'yoohw_support_center_settings';
	const CREDIT_URL         = 'https://yoohw.com';
	const STORAGE_WORDPRESS  = 'wordpress';
	const STORAGE_ISOLATED   = 'isolated';

	public static function init(): void {
		add_action( 'init', [ __CLASS__, 'maybe_migrate_legacy_option' ], 1 );
		add_action( 'init', [ __CLASS__, 'maybe_cleanup_removed_options' ], 2 );
		add_action( 'init', [ 'YoOhw_Support_Database', 'maybe_upgrade' ], 3 );
		add_action( 'admin_init', [ __CLASS__, 'register_settings' ] );
		add_action( 'admin_menu', [ __CLASS__, 'register_options_page' ] );
		add_action( 'admin_enqueue_scripts', [ __CLASS__, 'enqueue_admin_assets' ] );
		add_action( 'add_option_' . self::OPTION_NAME, [ __CLASS__, 'maybe_install_isolated_storage_on_add' ], 10, 2 );
		add_action( 'update_option_' . self::OPTION_NAME, [ __CLASS__, 'maybe_flush_rewrites_after_mode_change' ], 10, 2 );
	}

	public static function enqueue_admin_assets( string $hook_suffix ): void {
		if ( 'settings_page_yoohw-support-portal' !== $hook_suffix ) {
			return;
		}

		$css_path = YOOHW_SUPPORT_PORTAL_PATH . 'assets/css/admin.css';
		$js_path  = YOOHW_SUPPORT_PORTAL_PATH . 'assets/js/admin.js';

		wp_enqueue_style(
			'yoohw-support-portal-admin',
			YOOHW_SUPPORT_PORTAL_URL . 'assets/css/admin.css',
			[],
			file_exists( $css_path ) ? (string) filemtime( $css_path ) : YOOHW_SUPPORT_PORTAL_VERSION
		);
		wp_enqueue_script(
			'yoohw-support-portal-admin',
			YOOHW_SUPPORT_PORTAL_URL . 'assets/js/admin.js',
			[],
			file_exists( $js_path ) ? (string) filemtime( $js_path ) : YOOHW_SUPPORT_PORTAL_VERSION,
			true
		);
	}

	public static function maybe_migrate_legacy_option(): void {
		if ( false !== get_option( self::OPTION_NAME, false ) ) {
			return;
		}

		$legacy_settings = get_option( self::LEGACY_OPTION_NAME, false );

		if ( is_array( $legacy_settings ) ) {
			update_option( self::OPTION_NAME, array_merge( self::defaults(), $legacy_settings ), false );
		}
	}

	public static function defaults(): array {
		$defaults = [
			'docs_url'                   => self::default_site_url( '/docs/' ),
			'dedicated_portal_mode'      => self::STORAGE_WORDPRESS,
			'enable_page_ui'             => '1',
			'show_footer_credit'         => '0',
			'ui_primary_color'           => '#2563eb',
			'ui_background_color'        => '#f6f7f9',
			'ui_heading_font'            => 'Inter, ui-sans-serif, system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif',
			'ui_content_font'            => 'Inter, ui-sans-serif, system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif',
			'reply_support_badge_label'  => 'Support',
			'reply_customer_badge_label' => 'Customer',
		];

		return apply_filters( 'yoohw_support_settings_defaults', $defaults );
	}

	public static function get(): array {
		$settings = get_option( self::OPTION_NAME, [] );

		if ( ! is_array( $settings ) ) {
			$settings = [];
		}

		if ( [] === $settings ) {
			$legacy_settings = get_option( self::LEGACY_OPTION_NAME, [] );

			if ( is_array( $legacy_settings ) ) {
				$settings = $legacy_settings;
			}
		}

		return array_merge( self::defaults(), $settings );
	}

	public static function get_url( string $key ): string {
		$settings = self::get();
		$defaults = self::defaults();
		$url      = isset( $settings[ $key ] ) ? (string) $settings[ $key ] : '';

		if ( '' === $url && isset( $defaults[ $key ] ) && ! self::url_field_allows_empty( $key ) ) {
			$url = $defaults[ $key ];
		}

		return esc_url_raw( $url );
	}

	public static function page_ui_enabled(): bool {
		if ( ! self::dedicated_portal_mode_enabled() || self::uses_isolated_storage() ) {
			return false;
		}

		$settings = self::get();

		return ! empty( $settings['enable_page_ui'] );
	}

	public static function dedicated_portal_mode_enabled(): bool {
		$settings = self::get();

		return ! empty( $settings['dedicated_portal_mode'] );
	}

	public static function storage_mode(): string {
		$settings = self::get();
		$mode     = isset( $settings['dedicated_portal_mode'] ) ? sanitize_key( (string) $settings['dedicated_portal_mode'] ) : '';

		if ( self::STORAGE_ISOLATED === $mode ) {
			return self::STORAGE_ISOLATED;
		}

		return self::STORAGE_WORDPRESS;
	}

	public static function uses_isolated_storage(): bool {
		return self::STORAGE_ISOLATED === self::storage_mode();
	}

	public static function footer_credit_enabled(): bool {
		$settings = self::get();

		return ! empty( $settings['show_footer_credit'] );
	}

	public static function credit_url(): string {
		return self::CREDIT_URL;
	}

	public static function credit_label(): string {
		return __( 'Power by YoOhw Studio', 'yoohw-support-portal' );
	}

	public static function reply_side_labels(): array {
		$settings = self::get();
		$labels   = [
			'support'  => self::sanitize_badge_label( $settings['reply_support_badge_label'] ?? '' ),
			'customer' => self::sanitize_badge_label( $settings['reply_customer_badge_label'] ?? '' ),
		];

		if ( '' === $labels['support'] ) {
			$labels['support'] = __( 'Support', 'yoohw-support-portal' );
		}

		if ( '' === $labels['customer'] ) {
			$labels['customer'] = __( 'Customer', 'yoohw-support-portal' );
		}

		$labels = apply_filters( 'yoohw_support_reply_side_labels', $labels );
		$labels = is_array( $labels ) ? $labels : [];

		return [
			'support'  => self::sanitize_badge_label( $labels['support'] ?? '' ) ?: __( 'Support', 'yoohw-support-portal' ),
			'customer' => self::sanitize_badge_label( $labels['customer'] ?? '' ) ?: __( 'Customer', 'yoohw-support-portal' ),
		];
	}

	public static function ui_palette(): array {
		$settings = self::get();
		$defaults = self::defaults();
		$base     = self::derive_ui_palette( $settings, $defaults );
		$palette  = apply_filters( 'yoohw_support_ui_palette', $base, $settings, $defaults );

		return is_array( $palette ) ? array_merge( $base, $palette ) : $base;
	}

	public static function register_settings(): void {
		register_setting(
			'yoohw_support_portal',
			self::OPTION_NAME,
			[
				'type'              => 'array',
				'sanitize_callback' => [ __CLASS__, 'sanitize' ],
				'default'           => self::defaults(),
			]
		);

		add_settings_section(
			'yoohw_support_portal_setup',
			__( 'Site purpose', 'yoohw-support-portal' ),
			[ __CLASS__, 'render_setup_section_description' ],
			'yoohw-support-portal'
		);

		add_settings_field(
			'dedicated_portal_mode',
			__( 'Dedicated portal mode', 'yoohw-support-portal' ),
			[ __CLASS__, 'render_storage_mode_field' ],
			'yoohw-support-portal',
			'yoohw_support_portal_setup',
			[ 'key' => 'dedicated_portal_mode' ]
		);

		add_settings_section(
			'yoohw_support_portal_links',
			__( 'Support links', 'yoohw-support-portal' ),
			[ __CLASS__, 'render_section_description' ],
			'yoohw-support-portal'
		);

		foreach ( self::field_labels() as $key => $label ) {
			add_settings_field(
				$key,
				$label,
				[ __CLASS__, 'render_url_field' ],
				'yoohw-support-portal',
				'yoohw_support_portal_links',
				[
					'key'   => $key,
					'label' => $label,
				]
			);
		}

		add_settings_section(
			'yoohw_support_portal_ui',
			__( 'Plugin UI', 'yoohw-support-portal' ),
			[ __CLASS__, 'render_ui_section_description' ],
			'yoohw-support-portal'
		);

		add_settings_field(
			'portal_identity',
			__( 'Portal identity', 'yoohw-support-portal' ),
			[ __CLASS__, 'render_portal_identity_field' ],
			'yoohw-support-portal',
			'yoohw_support_portal_ui'
		);

		if ( ! self::uses_isolated_storage() ) {
			add_settings_field(
				'enable_page_ui',
				__( 'WordPress pages', 'yoohw-support-portal' ),
				[ __CLASS__, 'render_checkbox_field' ],
				'yoohw-support-portal',
				'yoohw_support_portal_ui',
				[
					'key'         => 'enable_page_ui',
					'label'       => __( 'Render standard WordPress pages with the plugin UI.', 'yoohw-support-portal' ),
					'description' => __( 'Support routes always use the plugin UI. This option also applies the same shell, assets, and content styling to regular pages you create in WordPress.', 'yoohw-support-portal' ),
				]
			);
		}

		add_settings_field(
			'show_footer_credit',
			__( 'Footer credit', 'yoohw-support-portal' ),
			[ __CLASS__, 'render_checkbox_field' ],
			'yoohw-support-portal',
			'yoohw_support_portal_ui',
			[
				'key'         => 'show_footer_credit',
				'label'       => __( 'Show "Power by YoOhw Studio" in the portal footer and email footer.', 'yoohw-support-portal' ),
				'description' => __( 'Disabled by default. Enable only when you explicitly want to display plugin credit on your support portal.', 'yoohw-support-portal' ),
			]
		);

		foreach ( self::ui_color_fields() as $key => $field ) {
			add_settings_field(
				$key,
				$field['label'],
				[ __CLASS__, 'render_color_field' ],
				'yoohw-support-portal',
				'yoohw_support_portal_ui',
				[
					'key'         => $key,
					'label'       => $field['label'],
					'description' => $field['description'] ?? '',
				]
			);
		}

		foreach ( self::ui_font_fields() as $key => $field ) {
			add_settings_field(
				$key,
				$field['label'],
				[ __CLASS__, 'render_font_field' ],
				'yoohw-support-portal',
				'yoohw_support_portal_ui',
				[
					'key'         => $key,
					'label'       => $field['label'],
					'description' => $field['description'] ?? '',
				]
			);
		}

		foreach ( self::ui_badge_label_fields() as $key => $field ) {
			add_settings_field(
				$key,
				$field['label'],
				[ __CLASS__, 'render_text_field' ],
				'yoohw-support-portal',
				'yoohw_support_portal_ui',
				[
					'key'         => $key,
					'label'       => $field['label'],
					'description' => $field['description'] ?? '',
					'maxlength'   => 32,
				]
			);
		}
	}

	public static function register_options_page(): void {
		add_options_page(
			__( 'YoOhw Support Portal', 'yoohw-support-portal' ),
			__( 'Support Portal', 'yoohw-support-portal' ),
			YoOhw_Support_Capabilities::MANAGE_SETTINGS,
			'yoohw-support-portal',
			[ __CLASS__, 'render_options_page' ]
		);
	}

	public static function sanitize( $value ): array {
		$clean    = [];
		$value    = is_array( $value ) ? $value : [];
		$defaults = self::defaults();

		foreach ( self::field_labels() as $key => $label ) {
			$default = $defaults[ $key ] ?? '';
			$url = isset( $value[ $key ] ) ? trim( (string) wp_unslash( $value[ $key ] ) ) : '';

			if ( '' === $url ) {
				$clean[ $key ] = self::url_field_allows_empty( $key ) ? '' : $default;
				continue;
			}

			$clean[ $key ] = esc_url_raw( $url );
		}

		$storage_mode = isset( $value['dedicated_portal_mode'] ) ? sanitize_key( (string) $value['dedicated_portal_mode'] ) : '';
		$clean['dedicated_portal_mode'] = in_array( $storage_mode, [ self::STORAGE_WORDPRESS, self::STORAGE_ISOLATED ], true )
			? $storage_mode
			: self::STORAGE_WORDPRESS;
		$clean['enable_page_ui']        = ! empty( $value['enable_page_ui'] ) ? '1' : '0';
		$clean['show_footer_credit']    = ! empty( $value['show_footer_credit'] ) ? '1' : '0';

		foreach ( self::ui_color_fields() as $key => $field ) {
			$default = $defaults[ $key ] ?? '';
			$color   = isset( $value[ $key ] ) ? sanitize_hex_color( wp_unslash( (string) $value[ $key ] ) ) : '';

			$clean[ $key ] = $color ?: $default;
		}

		foreach ( self::ui_font_fields() as $key => $field ) {
			$default = $defaults[ $key ] ?? '';
			$font    = isset( $value[ $key ] ) ? self::sanitize_font_stack( $value[ $key ] ) : '';

			$clean[ $key ] = '' !== $font ? $font : $default;
		}

		foreach ( self::ui_badge_label_fields() as $key => $field ) {
			$default = $defaults[ $key ] ?? '';
			$label   = isset( $value[ $key ] ) ? self::sanitize_badge_label( $value[ $key ] ) : '';

			$clean[ $key ] = '' !== $label ? $label : $default;
		}

		return $clean;
	}

	public static function render_section_description(): void {
		echo '<p>' . esc_html__( 'Configure external URLs used by the plugin UI.', 'yoohw-support-portal' ) . '</p>';
	}

	public static function render_setup_section_description(): void {
		echo '<p>' . esc_html__( 'Choose where support topics and replies are stored. Switching modes does not migrate or delete either dataset.', 'yoohw-support-portal' ) . '</p>';
	}

	public static function render_ui_section_description(): void {
		if ( self::uses_isolated_storage() ) {
			echo '<p>' . esc_html__( 'The isolated support portal uses the plugin layout only within the /support/ namespace. The site homepage, WordPress pages, author archives, and 404 pages continue to use the active theme.', 'yoohw-support-portal' ) . '</p>';
			return;
		}

		echo '<p>' . esc_html__( 'Support routes use the plugin layout instead of the active theme template. You can optionally apply the same layout to standard WordPress pages and 404 pages, then customize its identity, colors, typography, and reply badge labels below.', 'yoohw-support-portal' ) . '</p>';
	}

	public static function render_url_field( array $args ): void {
		$key      = isset( $args['key'] ) ? sanitize_key( $args['key'] ) : '';
		$settings = self::get();
		$value    = isset( $settings[ $key ] ) ? $settings[ $key ] : '';
		$id       = self::OPTION_NAME . '_' . $key;
		$name     = self::OPTION_NAME . '[' . $key . ']';

		?>
		<input
			type="url"
			id="<?php echo esc_attr( $id ); ?>"
			name="<?php echo esc_attr( $name ); ?>"
			value="<?php echo esc_attr( $value ); ?>"
			class="regular-text code"
		>
		<?php
	}

	public static function render_checkbox_field( array $args ): void {
		$key         = isset( $args['key'] ) ? sanitize_key( $args['key'] ) : '';
		$label       = isset( $args['label'] ) ? (string) $args['label'] : '';
		$description = isset( $args['description'] ) ? (string) $args['description'] : '';
		$settings    = self::get();
		$value       = ! empty( $settings[ $key ] );
		$id          = self::OPTION_NAME . '_' . $key;
		$name        = self::OPTION_NAME . '[' . $key . ']';

		?>
		<label for="<?php echo esc_attr( $id ); ?>">
			<input
				type="checkbox"
				id="<?php echo esc_attr( $id ); ?>"
				name="<?php echo esc_attr( $name ); ?>"
				value="1"
				<?php checked( $value ); ?>
			>
			<?php echo esc_html( $label ); ?>
		</label>
		<?php if ( $description ) : ?>
			<p class="description"><?php echo esc_html( $description ); ?></p>
		<?php endif; ?>
		<?php
	}

	public static function render_portal_identity_field(): void {
		$site_title    = get_bloginfo( 'name' ) ?: __( 'Support Portal', 'yoohw-support-portal' );
		$site_tagline  = trim( (string) get_bloginfo( 'description' ) );
		$site_icon_url = get_site_icon_url( 64 );
		$general_url   = admin_url( 'options-general.php' );
		?>
		<div class="yoohw-portal-identity-preview">
			<?php if ( $site_icon_url ) : ?>
				<img src="<?php echo esc_url( $site_icon_url ); ?>" alt="">
			<?php else : ?>
				<span class="dashicons dashicons-admin-site-alt3" aria-hidden="true"></span>
			<?php endif; ?>
			<span>
				<strong><?php echo esc_html( $site_title ); ?></strong>
				<span><?php echo esc_html( '' !== $site_tagline ? $site_tagline : __( 'No tagline configured', 'yoohw-support-portal' ) ); ?></span>
			</span>
		</div>
		<p class="description">
			<?php esc_html_e( 'The portal icon, title, and subtitle use the Site Icon, Site Title, and Tagline from General Settings.', 'yoohw-support-portal' ); ?>
		</p>
		<p>
			<a class="button button-secondary" href="<?php echo esc_url( $general_url ); ?>">
				<?php esc_html_e( 'Open General Settings', 'yoohw-support-portal' ); ?>
			</a>
		</p>
		<?php
	}

	public static function render_storage_mode_field( array $args ): void {
		$key      = isset( $args['key'] ) ? sanitize_key( $args['key'] ) : 'dedicated_portal_mode';
		$mode     = self::storage_mode();
		$name     = self::OPTION_NAME . '[' . $key . ']';
		$choices  = [
			self::STORAGE_ISOLATED  => [
				'label'       => __( 'Create isolated support data', 'yoohw-support-portal' ),
				'description' => __( 'Stores new topics and replies in dedicated plugin tables under the /support/ namespace. These tables are created only after this option is saved.', 'yoohw-support-portal' ),
				'recommended' => false,
			],
			self::STORAGE_WORDPRESS => [
				'label'       => __( 'Use WordPress posts and comments', 'yoohw-support-portal' ),
				'description' => __( 'Stores topics and replies in the native posts and comments tables. Recommended for a new, blank WordPress installation dedicated entirely to the support portal.', 'yoohw-support-portal' ),
				'recommended' => true,
			],
		];
		?>
		<fieldset class="yoohw-storage-mode-options">
			<legend class="screen-reader-text"><?php esc_html_e( 'Dedicated portal data mode', 'yoohw-support-portal' ); ?></legend>
			<?php foreach ( $choices as $value => $choice ) : ?>
				<label class="yoohw-storage-mode-option">
					<input
						type="radio"
						name="<?php echo esc_attr( $name ); ?>"
						value="<?php echo esc_attr( $value ); ?>"
						<?php checked( $mode, $value ); ?>
					>
					<span>
						<strong>
							<?php echo esc_html( $choice['label'] ); ?>
							<?php if ( ! empty( $choice['recommended'] ) ) : ?>
								<span class="yoohw-storage-mode-recommendation"><?php esc_html_e( 'Recommended for new blank sites', 'yoohw-support-portal' ); ?></span>
							<?php endif; ?>
						</strong>
						<span class="description"><?php echo esc_html( $choice['description'] ); ?></span>
					</span>
				</label>
			<?php endforeach; ?>
		</fieldset>
		<?php
	}

	public static function render_color_field( array $args ): void {
		$key         = isset( $args['key'] ) ? sanitize_key( $args['key'] ) : '';
		$description = isset( $args['description'] ) ? (string) $args['description'] : '';
		$settings    = self::get();
		$value       = isset( $settings[ $key ] ) ? sanitize_hex_color( (string) $settings[ $key ] ) : '';
		$id          = self::OPTION_NAME . '_' . $key;
		$name        = self::OPTION_NAME . '[' . $key . ']';

		if ( ! $value ) {
			$defaults = self::defaults();
			$value    = $defaults[ $key ] ?? '#000000';
		}

		?>
		<span class="yoohw-color-field" data-yoohw-color-field>
			<input
				type="color"
				id="<?php echo esc_attr( $id ); ?>"
				name="<?php echo esc_attr( $name ); ?>"
				value="<?php echo esc_attr( $value ); ?>"
				data-yoohw-color-picker
			>
			<input
				type="text"
				value="<?php echo esc_attr( $value ); ?>"
				class="yoohw-color-hex code"
				pattern="^#([A-Fa-f0-9]{3}|[A-Fa-f0-9]{6})$"
				maxlength="7"
				aria-label="<?php /* translators: %s: Color setting label. */ echo esc_attr( sprintf( __( '%s hex color', 'yoohw-support-portal' ), $args['label'] ?? $key ) ); ?>"
				data-yoohw-color-hex
			>
		</span>
		<?php if ( $description ) : ?>
			<p class="description"><?php echo esc_html( $description ); ?></p>
		<?php endif; ?>
		<?php
	}

	public static function render_font_field( array $args ): void {
		$key         = isset( $args['key'] ) ? sanitize_key( $args['key'] ) : '';
		$label       = isset( $args['label'] ) ? (string) $args['label'] : '';
		$description = isset( $args['description'] ) ? (string) $args['description'] : '';
		$settings    = self::get();
		$value       = isset( $settings[ $key ] ) ? (string) $settings[ $key ] : '';
		$id          = self::OPTION_NAME . '_' . $key;
		$name        = self::OPTION_NAME . '[' . $key . ']';
		$options     = self::font_options();

		?>
		<select
			id="<?php echo esc_attr( $id ); ?>"
			name="<?php echo esc_attr( $name ); ?>"
			class="regular-text yoohw-font-source-select"
			data-yoohw-font-select
			data-yoohw-font-label="<?php echo esc_attr( $label ); ?>"
		>
			<?php if ( '' !== $value && ! array_key_exists( $value, $options ) ) : ?>
				<option value="<?php echo esc_attr( $value ); ?>" selected>
					<?php /* translators: %s: Current custom font stack. */ echo esc_html( sprintf( __( 'Current custom font (%s)', 'yoohw-support-portal' ), $value ) ); ?>
				</option>
			<?php endif; ?>
			<?php foreach ( $options as $font_stack => $label ) : ?>
				<option value="<?php echo esc_attr( $font_stack ); ?>" <?php selected( $value, $font_stack ); ?>>
					<?php echo esc_html( $label ); ?>
				</option>
			<?php endforeach; ?>
		</select>
		<?php if ( $description ) : ?>
			<p class="description"><?php echo esc_html( $description ); ?></p>
		<?php endif; ?>
		<?php
	}

	public static function render_text_field( array $args ): void {
		$key         = isset( $args['key'] ) ? sanitize_key( $args['key'] ) : '';
		$description = isset( $args['description'] ) ? (string) $args['description'] : '';
		$maxlength   = isset( $args['maxlength'] ) ? max( 1, (int) $args['maxlength'] ) : 80;
		$settings    = self::get();
		$value       = isset( $settings[ $key ] ) ? (string) $settings[ $key ] : '';
		$id          = self::OPTION_NAME . '_' . $key;
		$name        = self::OPTION_NAME . '[' . $key . ']';

		?>
		<input
			type="text"
			id="<?php echo esc_attr( $id ); ?>"
			name="<?php echo esc_attr( $name ); ?>"
			value="<?php echo esc_attr( $value ); ?>"
			class="regular-text"
			maxlength="<?php echo esc_attr( (string) $maxlength ); ?>"
		>
		<?php if ( $description ) : ?>
			<p class="description"><?php echo esc_html( $description ); ?></p>
		<?php endif; ?>
		<?php
	}

	public static function custom_ui_css(): string {
		$settings = self::get();
		$defaults = self::defaults();
		$palette  = self::ui_palette();
		$vars     = [
			'--yoohw-bg'             => $palette['background'],
			'--yoohw-surface'        => $palette['surface'],
			'--yoohw-border'         => $palette['border'],
			'--yoohw-text'           => $palette['text'],
			'--yoohw-muted'          => $palette['muted'],
			'--yoohw-primary'        => $palette['primary'],
			'--yoohw-primary-strong' => $palette['primary_strong'],
			'--yoohw-primary-soft'   => $palette['primary_soft'],
			'--yoohw-primary-faint'  => $palette['primary_faint'],
			'--yoohw-primary-border' => $palette['primary_border'],
			'--yoohw-primary-shadow' => $palette['primary_shadow'],
			'--yoohw-font-heading'   => self::sanitize_font_stack( $settings['ui_heading_font'] ?? $defaults['ui_heading_font'] ) ?: $defaults['ui_heading_font'],
			'--yoohw-font-content'   => self::sanitize_font_stack( $settings['ui_content_font'] ?? $defaults['ui_content_font'] ) ?: $defaults['ui_content_font'],
		];

		$declarations = '';

		foreach ( $vars as $property => $value ) {
			$declarations .= $property . ':' . $value . ';';
		}

		return '.yoohw-support-body,.yoohw-support{' . $declarations . '}';
	}

	public static function render_options_page(): void {
		if ( ! current_user_can( YoOhw_Support_Capabilities::MANAGE_SETTINGS ) ) {
			return;
		}

		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'YoOhw Support Portal', 'yoohw-support-portal' ); ?></h1>
			<?php // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only success message flag. ?>
			<?php if ( isset( $_GET['yoohw_portal_setup'] ) && 'wizard_complete' === sanitize_key( wp_unslash( $_GET['yoohw_portal_setup'] ) ) ) : ?>
				<div class="notice notice-success is-dismissible">
					<p><?php esc_html_e( 'Support Portal setup is complete. You can fine-tune the portal settings below.', 'yoohw-support-portal' ); ?></p>
				</div>
			<?php endif; ?>
			<?php
			if ( class_exists( 'YoOhw_Support_Site_Check' ) ) {
				YoOhw_Support_Site_Check::render_setup_panel();
			}
			?>
			<form method="post" action="options.php">
				<?php
				settings_fields( 'yoohw_support_portal' );
				do_settings_sections( 'yoohw-support-portal' );
				submit_button();
				?>
			</form>
			</div>
		<?php
	}

	private static function default_site_url( string $path ): string {
		return function_exists( 'home_url' ) ? home_url( $path ) : $path;
	}

	public static function maybe_flush_rewrites_after_mode_change( $old_value, $value ): void {
		$old_value   = is_array( $old_value ) ? $old_value : [];
		$value       = is_array( $value ) ? $value : [];
		$old_mode    = sanitize_key( (string) ( $old_value['dedicated_portal_mode'] ?? '' ) );
		$new_mode    = sanitize_key( (string) ( $value['dedicated_portal_mode'] ?? '' ) );
		$old_enabled = ! empty( $old_mode );
		$new_enabled = ! empty( $new_mode );

		if (
			self::STORAGE_ISOLATED === $new_mode &&
			self::STORAGE_ISOLATED !== $old_mode &&
			class_exists( 'YoOhw_Support_Database' )
		) {
			YoOhw_Support_Database::install();
		}

		if ( $old_enabled === $new_enabled && $old_mode === $new_mode ) {
			return;
		}

		if ( $new_enabled && class_exists( 'YoOhw_Support_Router' ) ) {
			YoOhw_Support_Router::register_rewrite_rules();
			update_option( 'yoohw_support_routes_version', YoOhw_Support_Router::ROUTES_VERSION, false );
		} else {
			delete_option( 'yoohw_support_routes_version' );
		}

		flush_rewrite_rules( false );
	}

	public static function maybe_install_isolated_storage_on_add( $option_name, $value ): void {
		$value = is_array( $value ) ? $value : [];
		$mode  = sanitize_key( (string) ( $value['dedicated_portal_mode'] ?? '' ) );

		if ( self::STORAGE_ISOLATED === $mode && class_exists( 'YoOhw_Support_Database' ) ) {
			YoOhw_Support_Database::install();
		}
	}

	private static function field_labels(): array {
		$labels = [
			'docs_url'          => __( 'Documentation URL', 'yoohw-support-portal' ),
		];

		return apply_filters( 'yoohw_support_settings_url_fields', $labels );
	}

	private static function url_field_allows_empty( string $key ): bool {
		$keys = apply_filters( 'yoohw_support_settings_empty_url_fields', [ 'docs_url' ] );
		$keys = is_array( $keys ) ? array_map( 'sanitize_key', $keys ) : [ 'docs_url' ];

		return in_array( sanitize_key( $key ), $keys, true );
	}

	public static function maybe_cleanup_removed_options(): void {
		$settings = get_option( self::OPTION_NAME, false );

		if ( ! is_array( $settings ) ) {
			return;
		}

		$removed = [ 'contact_url', 'footer_url', 'enable_not_found_ui' ];
		$changed = false;

		foreach ( $removed as $key ) {
			if ( array_key_exists( $key, $settings ) ) {
				unset( $settings[ $key ] );
				$changed = true;
			}
		}

		if ( $changed ) {
			update_option( self::OPTION_NAME, $settings, false );
		}
	}

	private static function render_color_sync_script(): void {
		?>
		<?php
	}

	private static function render_font_select_script(): void {
		?>
		<?php
	}

	private static function ui_color_fields(): array {
		return [
			'ui_primary_color'    => [
				'label'       => __( 'Primary color', 'yoohw-support-portal' ),
				'description' => __( 'Used by primary buttons, links, and active states. Hover states are generated automatically.', 'yoohw-support-portal' ),
			],
			'ui_background_color' => [
				'label'       => __( 'Background color', 'yoohw-support-portal' ),
				'description' => __( 'Main background color. Surface, border, text, and muted colors are generated automatically.', 'yoohw-support-portal' ),
			],
		];
	}

	private static function derive_ui_palette( array $settings, array $defaults ): array {
		$primary    = sanitize_hex_color( (string) ( $settings['ui_primary_color'] ?? '' ) ) ?: $defaults['ui_primary_color'];
		$background = sanitize_hex_color( (string) ( $settings['ui_background_color'] ?? '' ) ) ?: $defaults['ui_background_color'];
		$is_light   = self::hex_luminance( $background ) >= 0.48;

		return [
			'primary'        => $primary,
			'primary_strong' => self::mix_hex_colors( $primary, '#000000', 0.16 ),
			'primary_soft'   => self::mix_hex_colors( $primary, '#ffffff', 0.9 ),
			'primary_faint'  => self::mix_hex_colors( $primary, '#ffffff', 0.96 ),
			'primary_border' => self::mix_hex_colors( $primary, '#ffffff', 0.62 ),
			'primary_shadow' => self::hex_to_rgba( $primary, 0.16 ),
			'primary_text'   => self::hex_luminance( $primary ) >= 0.55 ? '#18212f' : '#ffffff',
			'background'     => $background,
			'surface'        => $is_light ? self::mix_hex_colors( $background, '#ffffff', 0.82 ) : self::mix_hex_colors( $background, '#ffffff', 0.08 ),
			'border'         => $is_light ? self::mix_hex_colors( $background, '#647084', 0.22 ) : self::mix_hex_colors( $background, '#ffffff', 0.2 ),
			'text'           => $is_light ? '#18212f' : '#f8fafc',
			'muted'          => $is_light ? '#647084' : '#cbd5e1',
		];
	}

	private static function mix_hex_colors( string $base, string $target, float $amount ): string {
		$base_rgb   = self::hex_to_rgb( $base );
		$target_rgb = self::hex_to_rgb( $target );
		$amount     = max( 0, min( 1, $amount ) );
		$output     = [];

		foreach ( [ 'r', 'g', 'b' ] as $channel ) {
			$output[ $channel ] = (int) round( $base_rgb[ $channel ] + ( ( $target_rgb[ $channel ] - $base_rgb[ $channel ] ) * $amount ) );
		}

		return self::rgb_to_hex( $output );
	}

	private static function hex_luminance( string $hex ): float {
		$rgb = self::hex_to_rgb( $hex );

		foreach ( $rgb as $channel => $value ) {
			$value           = $value / 255;
			$rgb[ $channel ] = $value <= 0.03928 ? $value / 12.92 : pow( ( $value + 0.055 ) / 1.055, 2.4 );
		}

		return ( 0.2126 * $rgb['r'] ) + ( 0.7152 * $rgb['g'] ) + ( 0.0722 * $rgb['b'] );
	}

	private static function hex_to_rgb( string $hex ): array {
		$hex = ltrim( $hex, '#' );

		if ( 3 === strlen( $hex ) ) {
			$hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2];
		}

		return [
			'r' => hexdec( substr( $hex, 0, 2 ) ),
			'g' => hexdec( substr( $hex, 2, 2 ) ),
			'b' => hexdec( substr( $hex, 4, 2 ) ),
		];
	}

	private static function hex_to_rgba( string $hex, float $alpha ): string {
		$rgb   = self::hex_to_rgb( $hex );
		$alpha = max( 0, min( 1, $alpha ) );

		return 'rgba(' . (int) $rgb['r'] . ', ' . (int) $rgb['g'] . ', ' . (int) $rgb['b'] . ', ' . $alpha . ')';
	}

	private static function rgb_to_hex( array $rgb ): string {
		return sprintf(
			'#%02x%02x%02x',
			max( 0, min( 255, (int) $rgb['r'] ) ),
			max( 0, min( 255, (int) $rgb['g'] ) ),
			max( 0, min( 255, (int) $rgb['b'] ) )
		);
	}

	private static function ui_font_fields(): array {
		return [
			'ui_heading_font' => [
				'label'       => __( 'Heading font', 'yoohw-support-portal' ),
				'description' => __( 'CSS font-family stack used for headings. The plugin does not load external font files automatically.', 'yoohw-support-portal' ),
			],
			'ui_content_font' => [
				'label'       => __( 'Content font', 'yoohw-support-portal' ),
				'description' => __( 'CSS font-family stack used for body text and form controls.', 'yoohw-support-portal' ),
			],
		];
	}

	private static function font_options(): array {
		return [
			'Inter, ui-sans-serif, system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif' => __( 'Inter / System UI', 'yoohw-support-portal' ),
			'ui-sans-serif, system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif'        => __( 'System UI', 'yoohw-support-portal' ),
			'Arial, Helvetica, sans-serif'                                                              => __( 'Arial', 'yoohw-support-portal' ),
			'Helvetica, Arial, sans-serif'                                                              => __( 'Helvetica', 'yoohw-support-portal' ),
			'Verdana, Geneva, sans-serif'                                                               => __( 'Verdana', 'yoohw-support-portal' ),
			'Tahoma, Geneva, sans-serif'                                                                => __( 'Tahoma', 'yoohw-support-portal' ),
			'Trebuchet MS, Arial, sans-serif'                                                           => __( 'Trebuchet MS', 'yoohw-support-portal' ),
			'Georgia, "Times New Roman", serif'                                                         => __( 'Georgia', 'yoohw-support-portal' ),
			'"Times New Roman", Times, serif'                                                           => __( 'Times New Roman', 'yoohw-support-portal' ),
			'Palatino, "Palatino Linotype", "Book Antiqua", serif'                                      => __( 'Palatino', 'yoohw-support-portal' ),
			'ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace'                          => __( 'System Monospace', 'yoohw-support-portal' ),
		];
	}

	private static function ui_badge_label_fields(): array {
		return [
			'reply_support_badge_label'  => [
				'label'       => __( 'Support reply badge', 'yoohw-support-portal' ),
				'description' => __( 'Label shown on replies from support/admin users.', 'yoohw-support-portal' ),
			],
			'reply_customer_badge_label' => [
				'label'       => __( 'Customer reply badge', 'yoohw-support-portal' ),
				'description' => __( 'Label shown on replies from regular customers.', 'yoohw-support-portal' ),
			],
		];
	}

	private static function sanitize_font_stack( $value ): string {
		$value = trim( wp_strip_all_tags( wp_unslash( (string) $value ) ) );
		$value = preg_replace( '/[^a-zA-Z0-9\s,\-_".\']/', '', $value );
		$value = preg_replace( '/\s+/', ' ', (string) $value );

		return trim( (string) $value );
	}

	private static function sanitize_badge_label( $value ): string {
		$value = sanitize_text_field( wp_unslash( (string) $value ) );
		$value = preg_replace( '/\s+/', ' ', $value );
		$value = trim( (string) $value );

		if ( function_exists( 'mb_substr' ) ) {
			return mb_substr( $value, 0, 32 );
		}

		return substr( $value, 0, 32 );
	}
}
