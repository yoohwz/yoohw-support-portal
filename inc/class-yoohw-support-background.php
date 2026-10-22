<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Small shared primitives for the native topic workers. */
class YoOhw_Support_Background {

	public static function acquire_lock( string $key ): string {
		global $wpdb;
		$name  = 'yoohw_support_lock_' . $key;
		$token = ( time() + 15 * MINUTE_IN_SECONDS ) . ':' . wp_generate_uuid4();
		for ( $attempt = 0; $attempt < 2; ++$attempt ) {
			// add_option() can upsert a concurrent winner; INSERT IGNORE cannot replace it.
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Atomic insert-only lock acquisition.
			$inserted = $wpdb->query( $wpdb->prepare( "INSERT IGNORE INTO {$wpdb->options} (option_name, option_value, autoload) VALUES (%s, %s, 'no')", $name, $token ) );
			if ( 1 === $inserted ) {
				wp_cache_delete( $name, 'options' );
				wp_cache_delete( 'notoptions', 'options' );
				return $token;
			}
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Read current owner without a stale per-request options cache.
			$previous = (string) $wpdb->get_var( $wpdb->prepare( "SELECT option_value FROM {$wpdb->options} WHERE option_name = %s", $name ) );
			if ( ! $previous || (int) $previous >= time() ) {
				return '';
			}
			self::release_lock( $key, $previous );
		}
		return '';
	}

	public static function release_lock( string $key, string $token ): void {
		global $wpdb;
		$name = 'yoohw_support_lock_' . $key;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Atomic ownership check is required for expiring locks.
		$deleted = $wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->options} WHERE option_name = %s AND option_value = %s", $name, $token ) );
		if ( $deleted ) {
			wp_cache_delete( $name, 'options' );
		}
	}

	public static function run_topic_batch( string $job, int $limit, callable $callback ): void {
		$lock = self::acquire_lock( $job );
		if ( ! $lock ) {
			return;
		}

		try {
			global $wpdb;
			$option = 'yoohw_support_cursor_' . $job;
			$state  = get_option( $option, 0 );
			$cursor = is_array( $state ) ? (int) $state['after'] : (int) $state;
			// Freeze the upper ID for each sweep so ongoing inserts cannot prevent a wrap.
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Read-only upper bound for the native-topic sweep.
			$until = is_array( $state ) ? (int) $state['until'] : (int) $wpdb->get_var( "SELECT MAX(ID) FROM {$wpdb->posts} WHERE post_type = 'post' AND post_status = 'publish'" );
			if ( $wpdb->last_error ) {
				return;
			}
			// ID keysets remain stable when callbacks change status or notice metadata.
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Bounded native-topic scan with a persisted ID cursor.
			$ids = $wpdb->get_col( $wpdb->prepare( "SELECT ID FROM {$wpdb->posts} WHERE post_type = 'post' AND post_status = 'publish' AND ID > %d AND ID <= %d ORDER BY ID ASC LIMIT %d", $cursor, $until, $limit ) );
			if ( $wpdb->last_error ) {
				return;
			}
			foreach ( $ids as $id ) {
				wp_cache_delete( (int) $id, 'post_meta' );
				$callback( (int) $id );
				update_option( $option, [ 'after' => (int) $id, 'until' => $until ], false );
			}
			if ( count( $ids ) < $limit || ( $ids && (int) end( $ids ) >= $until ) ) {
				update_option( $option, 0, false );
			}
		} finally {
			self::release_lock( $job, $lock );
		}
	}
}
