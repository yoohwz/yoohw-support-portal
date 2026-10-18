<?php

defined( 'ABSPATH' ) || exit;

// phpcs:disable WordPress.WP.AlternativeFunctions.file_system_operations_is_writable,WordPress.WP.AlternativeFunctions.file_system_operations_chmod,WordPress.WP.AlternativeFunctions.file_system_operations_fopen,WordPress.WP.AlternativeFunctions.file_system_operations_fclose,WordPress.WP.AlternativeFunctions.unlink_unlink -- Canonical local paths, restrictive permissions, flock and checked retirement require native filesystem operations; remote filesystem adapters cannot establish these guarantees.
// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Explicit CLI inventory/relocation must read current ownership and metadata, never a cached retirement decision.

/** Local filesystem storage; never publishes a filesystem directory as a media URL. */
final class YoOhw_Support_Private_Storage {
	const JOURNAL = '_yoohw_private_relocation';
	const ERROR_OPTION = 'yoohw_support_private_storage_error';

	public static function init(): void {
		add_action( 'admin_notices', [ __CLASS__, 'notice' ] );
		if ( defined( 'WP_CLI' ) && WP_CLI ) {
			WP_CLI::add_command( 'ysp attachments', [ __CLASS__, 'command' ] );
		}
	}

	private static function error( string $message ) {
		update_option( self::ERROR_OPTION, $message, false );
		return new WP_Error( 'yoohw_private_storage', $message );
	}

	public static function notice(): void {
		$message = get_option( self::ERROR_OPTION );
		if ( $message && current_user_can( YoOhw_Support_Capabilities::MANAGE_SETTINGS ) ) {
			echo '<div class="notice notice-error"><p>' . esc_html( $message ) . '</p></div>';
		}
	}

	private static function within( string $path, string $directory ): bool {
		$path = wp_normalize_path( $path );
		$directory = untrailingslashit( wp_normalize_path( $directory ) );
		return $path === $directory || 0 === strpos( $path, $directory . '/' );
	}

	/** Requires the real HTTP document root, including on CLI. Unknown layouts fail closed. */
	public static function root() {
		$reported_root = sanitize_text_field( wp_unslash( $_SERVER['DOCUMENT_ROOT'] ?? '' ) );
		// realpath('') resolves cwd; an absent, empty or relative root is never evidence of web isolation.
		$document_root = $reported_root && path_is_absolute( $reported_root ) ? realpath( $reported_root ) : false;
		if ( ! $document_root || DIRECTORY_SEPARATOR === $document_root || ! is_dir( $document_root ) ) {
			return self::error( __( 'Support uploads are blocked: the web document root is unknown. Ask your administrator to configure private storage using the Support Portal attachment storage guide.', 'yoohw-support-portal' ) );
		}
		$uploads = wp_get_upload_dir();
		$roots = [ $document_root, realpath( ABSPATH ), realpath( WP_CONTENT_DIR ), realpath( $uploads['basedir'] ) ];
		// The script must actually belong to the reported web root. Do not trust a guessed CLI root.
		if ( ! self::within( (string) realpath( ABSPATH ), $document_root ) ) {
			return self::error( __( 'Support uploads are blocked: the WordPress and web document roots disagree. Ask your administrator to verify the hosting layout.', 'yoohw-support-portal' ) );
		}
		$base = defined( 'YOOHW_SUPPORT_PRIVATE_ROOT' ) ? YOOHW_SUPPORT_PRIVATE_ROOT : dirname( $document_root ) . '/yoohw-support-private';
		if ( ! is_string( $base ) || ! path_is_absolute( $base ) || strpos( wp_normalize_path( $base ), '/../' ) !== false ) {
			return self::error( __( 'Support uploads are blocked: private storage must be an absolute directory outside every web root.', 'yoohw-support-portal' ) );
		}
		// Resolve the existing parent before mkdir, so a symlink cannot create files in public storage.
		$parent = realpath( dirname( $base ) );
		if ( ! $parent ) {
			return self::error( __( 'Support uploads are blocked: the private storage parent directory does not exist.', 'yoohw-support-portal' ) );
		}
		$base = file_exists( $base ) ? realpath( $base ) : $parent . '/' . basename( $base );
		foreach ( $roots as $public_root ) {
			if ( ! $base || ( $public_root && self::within( $base, $public_root ) ) ) {
				return self::error( __( 'Support uploads are blocked: private storage resolves inside a web-served directory.', 'yoohw-support-portal' ) );
			}
		}
		global $wpdb;
		$site = substr( hash( 'sha256', site_url() . '|' . $wpdb->prefix ), 0, 24 );
		$root = $base . '/' . $site;
		if ( ! wp_mkdir_p( $root ) || ! is_writable( $root ) || is_link( $root ) || realpath( $root ) !== $root ) {
			return self::error( __( 'Support uploads are blocked: private storage cannot be created or written safely. Check directory ownership and permissions.', 'yoohw-support-portal' ) );
		}
		return $root;
	}

	/** Check known public mappings with a harmless canary as well as canonical root containment. */
	public static function verify( bool $fresh = false ) {
		$root = self::root();
		if ( is_wp_error( $root ) ) { return $root; }
		$key = 'yoohw_private_probe_' . md5( $root . '|' . sanitize_text_field( wp_unslash( $_SERVER['DOCUMENT_ROOT'] ?? '' ) ) . '|' . home_url() );
		if ( ! $fresh && get_transient( $key ) ) { return $root; }
		delete_transient( $key );
		$name = 'ysp-storage-probe-' . wp_generate_password( 24, false, false ) . '.png';
		$marker = wp_generate_password( 48, false, false );
		$probe = $root . '/' . $name;
		if ( false === file_put_contents( $probe, $marker ) ) {
			return self::error( __( 'Support uploads are blocked: private storage write verification failed.', 'yoohw-support-portal' ) );
		}
		try {
			$relative = '/' . rawurlencode( basename( dirname( $root ) ) ) . '/' . rawurlencode( basename( $root ) ) . '/' . $name;
			$uploads = wp_get_upload_dir();
			$urls = array_unique( [ home_url( $relative ), site_url( $relative ), trailingslashit( $uploads['baseurl'] ) . ltrim( $relative, '/' ) ] );
			foreach ( $urls as $url ) {
				$response = wp_remote_get( $url, [ 'timeout' => 5, 'redirection' => 3, 'limit_response_size' => 4096, 'sslverify' => 'local' !== wp_get_environment_type(), 'headers' => [ 'Cache-Control' => 'no-cache, no-store' ] ] );
				if ( is_wp_error( $response ) || ! in_array( wp_remote_retrieve_response_code( $response ), [ 200, 403, 404 ], true ) || false !== strpos( wp_remote_retrieve_body( $response ), $marker ) ) {
					return self::error( __( 'Support uploads are blocked: the private storage HTTP check failed. Verify loopback HTTP access and remove any web-server aliases to private storage; then run wp ysp attachments verify.', 'yoohw-support-portal' ) );
				}
			}
			set_transient( $key, 1, 5 * MINUTE_IN_SECONDS );
			delete_option( self::ERROR_OPTION );
			return $root;
		} finally {
			unlink( $probe );
		}
	}

	public static function upload_directory( array $uploads ): array {
		$root = self::verify();
		if ( is_wp_error( $root ) ) {
			$uploads['error'] = $root->get_error_message();
			return $uploads;
		}
		$subdir = '/' . gmdate( 'Y/m' );
		$path = $root . $subdir;
		if ( ! wp_mkdir_p( $path ) || ! is_writable( $path ) || realpath( $path ) !== $path ) {
			$uploads['error'] = self::error( __( 'Support uploads are blocked: the private upload directory is not writable or resolves through a symlink.', 'yoohw-support-portal' ) )->get_error_message();
			return $uploads;
		}
		$uploads['path'] = $path;
		$uploads['subdir'] = $subdir;
		$uploads['url'] = home_url( '/yoohw-support-private/' . basename( $root ) . $subdir );
		// Keep the real public basedir: WordPress must store an absolute _wp_attached_file.
		return $uploads;
	}

	/** Include every core-generated member of an attachment family; never coerce traversal to basename. */
	private static function family( int $id ): array {
		$file = get_attached_file( $id, true );
		if ( ! $file || ! path_is_absolute( $file ) ) {
			throw new RuntimeException( 'Attachment file path is unavailable.' );
		}
		$metadata = wp_get_attachment_metadata( $id );
		$names = [ basename( $file ) ];
		foreach ( (array) ( $metadata['sizes'] ?? [] ) as $size ) { $names[] = $size['file'] ?? ''; }
		foreach ( [ 'original_image', 'thumb' ] as $key ) {
			if ( ! empty( $metadata[ $key ] ) ) { $names[] = $metadata[ $key ]; }
		}
		$backups = get_post_meta( $id, '_wp_attachment_backup_sizes', true );
		foreach ( is_array( $backups ) ? $backups : [] as $size ) { $names[] = $size['file'] ?? ''; }
		$files = [];
		foreach ( array_unique( $names ) as $name ) {
			if ( ! is_string( $name ) || ! $name || basename( $name ) !== $name || false !== strpos( $name, '\\' ) || in_array( $name, [ '.', '..' ], true ) ) {
				throw new RuntimeException( 'Unsafe attachment variant name.' );
			}
			$files[] = dirname( $file ) . '/' . $name;
		}
		return $files;
	}

	private static function assert_exclusive( int $id, string $source ): void {
		global $wpdb;
		$others = $wpdb->get_col( $wpdb->prepare( "SELECT DISTINCT post_id FROM {$wpdb->postmeta} WHERE post_id <> %d AND meta_key IN ('_wp_attached_file','_wp_attachment_metadata','_wp_attachment_backup_sizes') AND meta_value LIKE %s", $id, '%' . $wpdb->esc_like( basename( $source ) ) . '%' ) );
		foreach ( $others as $other ) {
			if ( 'attachment' === get_post_type( $other ) && in_array( $source, self::family( (int) $other ), true ) ) {
				throw new RuntimeException( 'A file is shared with another media attachment; resolve ownership before relocation.' );
			}
		}
	}

	private static function verified_copy( string $source, string $target, string $hash ): void {
		if ( is_link( $target ) || ( file_exists( $target ) && ( ! is_file( $target ) || hash_file( 'sha256', $target ) !== $hash ) ) ) {
			throw new RuntimeException( 'Destination conflict; original files and recovery copies are retained.' );
		}
		if ( ! file_exists( $target ) && ( ! copy( $source, $target ) || ! chmod( $target, 0600 ) ) ) {
			throw new RuntimeException( 'Private file copy failed; retry after fixing filesystem permissions.' );
		}
		if ( hash_file( 'sha256', $target ) !== $hash ) { throw new RuntimeException( 'Private file hash verification failed.' ); }
	}

	/** One explicit attachment per invocation; journal survives interruption and is never an activation task. */
	public static function secure( int $id, bool $stage_only = false ) {
		if ( ! YoOhw_Protected_Attachments::topic_id_for_attachment( $id ) ) {
			return new WP_Error( 'yoohw_not_owned', 'Not an owned support attachment; public site media are excluded.' );
		}
		$root = self::verify();
		if ( is_wp_error( $root ) ) { return $root; }
		$directory = $root . '/relocated-' . $id;
		// A per-attachment filesystem lock prevents concurrent CLI invocations changing the journal.
		if ( ! wp_mkdir_p( $directory ) || realpath( $directory ) !== $directory ) { return new WP_Error( 'yoohw_copy', 'Unsafe relocation directory.' ); }
		$lock_path = $directory . '/lock';
		if ( is_link( $lock_path ) ) { return new WP_Error( 'yoohw_lock', 'Unsafe lock path.' ); }
		$lock = fopen( $lock_path, 'c' );
		if ( ! $lock || ! flock( $lock, LOCK_EX | LOCK_NB ) ) {
			if ( $lock ) { fclose( $lock ); }
			return new WP_Error( 'yoohw_busy', 'Attachment relocation is already running.' );
		}
		try {
			$journal = get_post_meta( $id, self::JOURNAL, true );
			if ( ! $journal ) {
				$file = get_attached_file( $id, true );
				if ( self::within( $file, $root ) ) {
					foreach ( self::family( $id ) as $member ) {
						if ( is_link( $member ) || realpath( $member ) !== $member || ! is_readable( $member ) ) { throw new RuntimeException( 'Private attachment family is incomplete or unsafe.' ); }
					}
					return 'already-private';
				}
				$uploads = wp_get_upload_dir();
				$source_root = realpath( $uploads['basedir'] );
				$journal = [ 'backup_sizes' => get_post_meta( $id, '_wp_attachment_backup_sizes', true ), 'attached_file' => get_post_meta( $id, '_wp_attached_file', true ), 'metadata' => wp_get_attachment_metadata( $id ), 'files' => [] ];
				foreach ( self::family( $id ) as $source ) {
					if ( ! $source_root || is_link( $source ) || ! is_file( $source ) || realpath( $source ) !== $source || ! self::within( $source, $source_root ) ) {
						throw new RuntimeException( 'A legacy file is missing, symlinked or outside public uploads; no files were retired.' );
					}
					$journal['files'][] = [ 'source' => $source, 'hash' => hash_file( 'sha256', $source ) ];
				}
				update_post_meta( $id, self::JOURNAL, $journal );
				if ( get_post_meta( $id, self::JOURNAL, true ) !== $journal ) { throw new RuntimeException( 'Could not persist recovery journal.' ); }
			}
			$target = $directory . '/files/' . basename( $journal['files'][0]['source'] );
			$expected_metadata = $journal['metadata'];
			if ( is_array( $expected_metadata ) ) { $expected_metadata['file'] = $target; }
			$current_file = get_post_meta( $id, '_wp_attached_file', true );
			$current_metadata = wp_get_attachment_metadata( $id );
			if ( ! in_array( $current_file, [ $journal['attached_file'], $target ], true ) || ( $current_metadata !== $journal['metadata'] && $current_metadata !== $expected_metadata ) || get_post_meta( $id, '_wp_attachment_backup_sizes', true ) !== $journal['backup_sizes'] ) {
				throw new RuntimeException( 'Attachment metadata changed after staging; resolve the conflict before continuing.' );
			}
			foreach ( $journal['files'] as $entry ) { self::assert_exclusive( $id, $entry['source'] ); }
			foreach ( [ 'backup', 'files' ] as $folder ) {
				$path = $directory . '/' . $folder;
				if ( ! wp_mkdir_p( $path ) || realpath( $path ) !== $path ) { throw new RuntimeException( 'Unsafe recovery directory.' ); }
			}
			foreach ( $journal['files'] as $entry ) {
				$backup = $directory . '/backup/' . basename( $entry['source'] );
				if ( file_exists( $entry['source'] ) && ( is_link( $entry['source'] ) || hash_file( 'sha256', $entry['source'] ) !== $entry['hash'] ) ) { throw new RuntimeException( 'Legacy source changed; no further files were retired.' ); }
				self::verified_copy( file_exists( $entry['source'] ) ? $entry['source'] : $backup, $backup, $entry['hash'] );
				self::verified_copy( $backup, $directory . '/files/' . basename( $entry['source'] ), $entry['hash'] );
			}
			if ( $stage_only ) { return 'staged-not-secured'; }
			$target = $directory . '/files/' . basename( $journal['files'][0]['source'] );
			update_post_meta( $id, '_wp_attached_file', $target );
			$metadata = $journal['metadata'];
			if ( is_array( $metadata ) ) { $metadata['file'] = $target; update_post_meta( $id, '_wp_attachment_metadata', $metadata ); }
			if ( get_attached_file( $id, true ) !== $target || ( is_array( $metadata ) && wp_get_attachment_metadata( $id ) !== $metadata ) ) { throw new RuntimeException( 'Metadata update failed; recovery copies and original files are retained.' ); }
			foreach ( $journal['files'] as $entry ) {
				if ( file_exists( $entry['source'] ) && ! unlink( $entry['source'] ) ) { throw new RuntimeException( 'Public copy could not be retired; relocation remains incomplete. Retry and keep server deny protection in place.' ); }
				clearstatcache( true, $entry['source'] );
				if ( file_exists( $entry['source'] ) ) { throw new RuntimeException( 'A public copy remains; relocation is incomplete.' ); }
			}
			return 'secured';
		} catch ( RuntimeException $error ) {
			return new WP_Error( 'yoohw_relocation', $error->getMessage() );
		} finally {
			flock( $lock, LOCK_UN ); fclose( $lock );
		}
	}

	/** wp ysp attachments inventory [--after=<id>] [--limit=<n>] | secure <id> [--stage-only] */
	public static function command( array $args, array $options ): void {
		if ( YoOhw_Support_Settings::uses_isolated_storage() ) { WP_CLI::error( 'Select WordPress posts/comments storage; isolated mode does not migrate attachments.' ); }
		if ( 'verify' === ( $args[0] ?? '' ) ) {
			$result = self::verify( true );
			if ( is_wp_error( $result ) ) { WP_CLI::error( $result->get_error_message() ); }
			WP_CLI::success( 'Private storage filesystem and HTTP checks passed. Hosting aliases must also be audited by the operator.' ); return;
		}
		if ( 'secure' === ( $args[0] ?? '' ) && ! empty( $args[1] ) && ctype_digit( $args[1] ) ) {
			$result = self::secure( (int) $args[1], isset( $options['stage-only'] ) );
			if ( is_wp_error( $result ) ) { WP_CLI::error( $result->get_error_message() ); }
			WP_CLI::success( $result ); return;
		}
		if ( 'inventory' !== ( $args[0] ?? '' ) ) { WP_CLI::error( 'Use inventory [--after=<id>] [--limit=100] or secure <id> [--stage-only].' ); }
		global $wpdb;
		$ids = $wpdb->get_col( $wpdb->prepare( "SELECT ID FROM {$wpdb->posts} WHERE post_type = 'attachment' AND ID > %d ORDER BY ID LIMIT %d", absint( $options['after'] ?? 0 ), min( 100, max( 1, absint( $options['limit'] ?? 100 ) ) ) ) );
		foreach ( $ids as $id ) {
			if ( YoOhw_Protected_Attachments::topic_id_for_attachment( $id ) ) { WP_CLI::line( 'owned-attachment ' . $id ); }
		}
		WP_CLI::line( 'next-after=' . ( $ids ? end( $ids ) : 'end' ) );
	}
}
