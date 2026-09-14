<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Dedicated storage for support data.
 *
 * Tables are intentionally installed lazily. Plugin activation and the default
 * WordPress storage mode never call install().
 */
// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- This repository class owns dedicated, prefix-derived tables; live support data is not safely cacheable.
class YoOhw_Support_Database {

	const VERSION        = '2';
	const VERSION_OPTION = 'yoohw_support_database_version';

	public static function topics_table(): string {
		global $wpdb;

		return $wpdb->prefix . 'yoohw_support_topics';
	}

	public static function replies_table(): string {
		global $wpdb;

		return $wpdb->prefix . 'yoohw_support_replies';
	}

	public static function tables_exist(): bool {
		global $wpdb;

		$topics  = self::topics_table();
		$replies = self::replies_table();

		return $topics === $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $wpdb->esc_like( $topics ) ) )
			&& $replies === $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $wpdb->esc_like( $replies ) ) );
	}

	public static function maybe_upgrade(): void {
		if (
			class_exists( 'YoOhw_Support_Settings' ) &&
			YoOhw_Support_Settings::uses_isolated_storage() &&
			self::VERSION !== (string) get_option( self::VERSION_OPTION, '' )
		) {
			self::install();
		}
	}

	public static function install(): void {
		global $wpdb;

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$charset_collate = $wpdb->get_charset_collate();
		$topics_table    = self::topics_table();
		$replies_table   = self::replies_table();

		dbDelta(
			"CREATE TABLE {$topics_table} (
				id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
				public_key varchar(64) NOT NULL,
				author_id bigint(20) unsigned NOT NULL,
				category_id bigint(20) unsigned NOT NULL DEFAULT 0,
				title text NOT NULL,
				slug varchar(200) NOT NULL,
				content longtext NOT NULL,
				status varchar(20) NOT NULL DEFAULT 'open',
				attachment_ids longtext NULL,
				created_at datetime NOT NULL,
				updated_at datetime NOT NULL,
				PRIMARY KEY  (id),
				KEY public_key (public_key),
				KEY author_id (author_id),
				KEY category_id (category_id),
				KEY status (status),
				KEY slug (slug)
			) {$charset_collate};"
		);

		dbDelta(
			"CREATE TABLE {$replies_table} (
				id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
				topic_id bigint(20) unsigned NOT NULL,
				author_id bigint(20) unsigned NOT NULL,
				content longtext NOT NULL,
				attachment_ids longtext NULL,
				created_at datetime NOT NULL,
				updated_at datetime NOT NULL,
				PRIMARY KEY  (id),
				KEY topic_id (topic_id),
				KEY author_id (author_id),
				KEY created_at (created_at)
			) {$charset_collate};"
		);

		if ( self::tables_exist() ) {
			self::backfill_public_keys();
			update_option( self::VERSION_OPTION, self::VERSION, false );
		}
	}

	public static function create_topic( array $data ) {
		global $wpdb;

		if ( ! self::tables_exist() ) {
			return new WP_Error( 'yoohw_support_tables_missing', __( 'The isolated support tables are not available.', 'yoohw-support-portal' ) );
		}

		$now    = current_time( 'mysql', true );
		$title  = sanitize_text_field( (string) ( $data['title'] ?? '' ) );
		$slug   = sanitize_title( $title );
		$public_key = self::generate_public_key();
		$insert = $wpdb->insert(
			self::topics_table(),
			[
				'public_key'     => $public_key,
				'author_id'      => absint( $data['author_id'] ?? 0 ),
				'category_id'    => absint( $data['category_id'] ?? 0 ),
				'title'          => $title,
				'slug'           => $slug,
				'content'        => wp_kses_post( (string) ( $data['content'] ?? '' ) ),
				'status'         => 'open',
				'attachment_ids' => wp_json_encode( [] ),
				'created_at'     => $now,
				'updated_at'     => $now,
			],
			[ '%s', '%d', '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s' ]
		);

		if ( false === $insert ) {
			return new WP_Error( 'yoohw_support_topic_insert_failed', __( 'The topic could not be created.', 'yoohw-support-portal' ) );
		}

		return (int) $wpdb->insert_id;
	}

	public static function update_topic_attachments( int $topic_id, array $attachment_ids ): bool {
		global $wpdb;

		return false !== $wpdb->update(
			self::topics_table(),
			[
				'attachment_ids' => wp_json_encode( array_values( array_filter( array_map( 'absint', $attachment_ids ) ) ) ),
				'updated_at'     => current_time( 'mysql', true ),
			],
			[ 'id' => absint( $topic_id ) ],
			[ '%s', '%s' ],
			[ '%d' ]
		);
	}

	public static function update_topic_status( int $topic_id, string $status ): bool {
		global $wpdb;

		$status = in_array( $status, [ 'open', 'resolved' ], true ) ? $status : 'open';

		return false !== $wpdb->update(
			self::topics_table(),
			[
				'status'     => $status,
				'updated_at' => current_time( 'mysql', true ),
			],
			[ 'id' => absint( $topic_id ) ],
			[ '%s', '%s' ],
			[ '%d' ]
		);
	}

	public static function create_reply( array $data ) {
		global $wpdb;

		if ( ! self::tables_exist() ) {
			return new WP_Error( 'yoohw_support_tables_missing', __( 'The isolated support tables are not available.', 'yoohw-support-portal' ) );
		}

		$now    = current_time( 'mysql', true );
		$insert = $wpdb->insert(
			self::replies_table(),
			[
				'topic_id'       => absint( $data['topic_id'] ?? 0 ),
				'author_id'      => absint( $data['author_id'] ?? 0 ),
				'content'        => wp_kses_post( (string) ( $data['content'] ?? '' ) ),
				'attachment_ids' => wp_json_encode( [] ),
				'created_at'     => $now,
				'updated_at'     => $now,
			],
			[ '%d', '%d', '%s', '%s', '%s', '%s' ]
		);

		if ( false === $insert ) {
			return new WP_Error( 'yoohw_support_reply_insert_failed', __( 'The reply could not be created.', 'yoohw-support-portal' ) );
		}

		return (int) $wpdb->insert_id;
	}

	public static function get_topic( $key ): ?array {
		global $wpdb;

		if ( ! self::tables_exist() ) {
			return null;
		}

		$table = esc_sql( self::topics_table() );
		$row   = $wpdb->get_row(
			$wpdb->prepare( "SELECT * FROM {$table} WHERE public_key = %s LIMIT 1", sanitize_text_field( (string) $key ) ),
			ARRAY_A
		);

		return is_array( $row ) ? self::prepare_topic( $row ) : null;
	}

	public static function get_topic_by_id( int $topic_id ): ?array {
		global $wpdb;

		if ( ! self::tables_exist() ) {
			return null;
		}

		$table = esc_sql( self::topics_table() );
		$row   = $wpdb->get_row(
			$wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d LIMIT 1", absint( $topic_id ) ),
			ARRAY_A
		);

		return is_array( $row ) ? self::prepare_topic( $row ) : null;
	}

	public static function get_replies( int $topic_id ): array {
		global $wpdb;

		if ( ! self::tables_exist() ) {
			return [];
		}

		$table = esc_sql( self::replies_table() );
		$rows  = $wpdb->get_results(
			$wpdb->prepare( "SELECT * FROM {$table} WHERE topic_id = %d ORDER BY created_at ASC, id ASC", absint( $topic_id ) ),
			ARRAY_A
		);

		return array_map( [ __CLASS__, 'prepare_reply' ], is_array( $rows ) ? $rows : [] );
	}

	public static function query_topics( array $args = [] ): array {
		global $wpdb;

		if ( ! self::tables_exist() ) {
			return [
				'items'     => [],
				'total'     => 0,
				'max_pages' => 0,
			];
		}

		$defaults = [
			'page'        => 1,
			'per_page'    => 8,
			'search'      => '',
			'category_id' => 0,
			'status'      => '',
			'author_ids'  => [],
			'author_id'   => 0,
		];
		$args     = wp_parse_args( $args, $defaults );
		$where    = [ '1 = %d' ];
		$values   = [ 1 ];

		if ( '' !== $args['search'] ) {
			$like     = '%' . $wpdb->esc_like( (string) $args['search'] ) . '%';
			$where[]  = '(title LIKE %s OR content LIKE %s)';
			$values[] = $like;
			$values[] = $like;
		}

		if ( absint( $args['category_id'] ) ) {
			$where[]  = 'category_id = %d';
			$values[] = absint( $args['category_id'] );
		}

		if ( '' !== $args['status'] ) {
			$where[]  = 'status = %s';
			$values[] = sanitize_key( (string) $args['status'] );
		}

		if ( absint( $args['author_id'] ) ) {
			$where[]  = 'author_id = %d';
			$values[] = absint( $args['author_id'] );
		} elseif ( ! empty( $args['author_ids'] ) ) {
			$author_ids = array_values( array_filter( array_map( 'absint', (array) $args['author_ids'] ) ) );

			if ( ! empty( $author_ids ) ) {
				$where[] = 'author_id IN (' . implode( ',', array_fill( 0, count( $author_ids ), '%d' ) ) . ')';
				$values  = array_merge( $values, $author_ids );
			}
		}

		$where_sql = implode( ' AND ', $where );
		$table     = esc_sql( self::topics_table() );
		$count_sql = "SELECT COUNT(*) FROM {$table} WHERE {$where_sql}";
		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared,PluginCheck.Security.DirectDB.UnescapedDBParameter -- Table is escaped, clauses are fixed, and values use placeholders.
		$total     = (int) $wpdb->get_var( $wpdb->prepare( $count_sql, $values ) );
		$per_page  = max( 1, min( 100, absint( $args['per_page'] ) ) );
		$page      = max( 1, absint( $args['page'] ) );
		$offset    = ( $page - 1 ) * $per_page;
		$list_sql  = "SELECT * FROM {$table} WHERE {$where_sql} ORDER BY updated_at DESC, id DESC LIMIT %d OFFSET %d";
		$list_args = array_merge( $values, [ $per_page, $offset ] );
		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared,PluginCheck.Security.DirectDB.UnescapedDBParameter -- Table is escaped, clauses are fixed, and values use placeholders.
		$rows      = $wpdb->get_results( $wpdb->prepare( $list_sql, $list_args ), ARRAY_A );

		return [
			'items'     => array_map( [ __CLASS__, 'prepare_topic' ], is_array( $rows ) ? $rows : [] ),
			'total'     => $total,
			'max_pages' => (int) ceil( $total / $per_page ),
		];
	}

	public static function count_replies( int $topic_id ): int {
		global $wpdb;

		if ( ! self::tables_exist() ) {
			return 0;
		}

		$table = esc_sql( self::replies_table() );

		return (int) $wpdb->get_var(
			$wpdb->prepare( "SELECT COUNT(*) FROM {$table} WHERE topic_id = %d", absint( $topic_id ) )
		);
	}

	private static function prepare_topic( array $row ): array {
		$row['id']             = absint( $row['id'] ?? 0 );
		$row['author_id']      = absint( $row['author_id'] ?? 0 );
		$row['category_id']    = absint( $row['category_id'] ?? 0 );
		$row['attachment_ids'] = self::decode_ids( $row['attachment_ids'] ?? '' );

		return $row;
	}

	private static function generate_public_key(): string {
		global $wpdb;

		$table = esc_sql( self::topics_table() );

		do {
			$key    = strtolower( wp_generate_password( 20, false, false ) );
			$exists = $wpdb->get_var(
				$wpdb->prepare( "SELECT id FROM {$table} WHERE public_key = %s LIMIT 1", $key )
			);
		} while ( $exists );

		return $key;
	}

	private static function backfill_public_keys(): void {
		global $wpdb;

		$table     = esc_sql( self::topics_table() );
		$topic_ids = $wpdb->get_col( "SELECT id FROM {$table} WHERE public_key = '' OR public_key IS NULL" );

		foreach ( $topic_ids as $topic_id ) {
			$wpdb->update(
				self::topics_table(),
				[ 'public_key' => self::generate_public_key() ],
				[ 'id' => absint( $topic_id ) ],
				[ '%s' ],
				[ '%d' ]
			);
		}
	}

	private static function prepare_reply( array $row ): array {
		$row['id']             = absint( $row['id'] ?? 0 );
		$row['topic_id']       = absint( $row['topic_id'] ?? 0 );
		$row['author_id']      = absint( $row['author_id'] ?? 0 );
		$row['attachment_ids'] = self::decode_ids( $row['attachment_ids'] ?? '' );

		return $row;
	}

	private static function decode_ids( $value ): array {
		$ids = json_decode( (string) $value, true );

		return is_array( $ids ) ? array_values( array_filter( array_map( 'absint', $ids ) ) ) : [];
	}
}
