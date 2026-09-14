<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class YoOhw_Support_Capabilities {

	const VIEW_ALL_TOPICS   = 'yoohw_support_view_all_topics';
	const MANAGE_TOPICS     = 'yoohw_support_manage_topics';
	const MANAGE_CATEGORIES = 'yoohw_support_manage_categories';
	const VIEW_CUSTOMERS    = 'yoohw_support_view_customers';
	const MANAGE_SETTINGS   = 'yoohw_support_manage_settings';

	const VERSION_OPTION = 'yoohw_support_capabilities_version';
	const VERSION        = '1';

	public static function init() {
		if ( self::VERSION !== get_option( self::VERSION_OPTION ) ) {
			self::install();
		}
	}

	public static function install() {
		self::add_caps_to_role(
			'administrator',
			[
				self::VIEW_ALL_TOPICS,
				self::MANAGE_TOPICS,
				self::MANAGE_CATEGORIES,
				self::VIEW_CUSTOMERS,
				self::MANAGE_SETTINGS,
			]
		);

		self::add_caps_to_role(
			'editor',
			[
				self::VIEW_ALL_TOPICS,
				self::MANAGE_TOPICS,
				self::MANAGE_CATEGORIES,
				self::VIEW_CUSTOMERS,
			]
		);

		update_option( self::VERSION_OPTION, self::VERSION );
	}

	public static function all(): array {
		return [
			self::VIEW_ALL_TOPICS,
			self::MANAGE_TOPICS,
			self::MANAGE_CATEGORIES,
			self::VIEW_CUSTOMERS,
			self::MANAGE_SETTINGS,
		];
	}

	private static function add_caps_to_role( string $role_name, array $capabilities ) {
		$role = get_role( $role_name );

		if ( ! $role ) {
			return;
		}

		foreach ( $capabilities as $capability ) {
			$role->add_cap( $capability );
		}
	}
}
