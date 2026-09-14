<?php

defined( 'ABSPATH' ) || exit;

/**
 * Protect support attachments behind a logged-in, topic-aware download route.
 */
final class YoOhw_Protected_Attachments {

	const ACTION         = 'yoohw_support_attachment';
	const META_PROTECTED = '_yoohw_attachment_protected';
	const META_IDS       = '_yoohw_attachment_ids';

	private static $private_upload_depth = 0;
	private static $protected_directories = [];

	public static function init() {
		add_action( 'admin_post_' . self::ACTION, [ __CLASS__, 'serve' ] );
		add_action( 'admin_post_nopriv_' . self::ACTION, [ __CLASS__, 'deny_anonymous' ] );
		add_filter( 'yoohw_extra_support_non_admin_admin_post_actions', [ __CLASS__, 'allow_non_admin_download_action' ] );
		add_filter( 'wp_get_attachment_url', [ __CLASS__, 'filter_attachment_url' ], 20, 2 );
		add_filter( 'image_downsize', [ __CLASS__, 'filter_image_downsize' ], 20, 3 );
	}

	/**
	 * Allow customer download requests through site-level wp-admin redirects.
	 *
	 * Authorization still happens in serve() before any file is streamed.
	 *
	 * @param array $actions Allowed admin-post.php actions for non-admin users.
	 * @return array
	 */
	public static function allow_non_admin_download_action( $actions ) {
		$actions   = (array) $actions;
		$actions[] = self::ACTION;

		return array_values( array_unique( $actions ) );
	}

	public static function protect( $attachment_id ) {
		$attachment_id = absint( $attachment_id );

		if ( $attachment_id ) {
			update_post_meta( $attachment_id, self::META_PROTECTED, 1 );
		}
	}

	/**
	 * Route the next WordPress media upload directly into protected plugin storage.
	 */
	public static function begin_private_upload(): void {
		self::$private_upload_depth++;

		if ( 1 === self::$private_upload_depth ) {
			add_filter( 'upload_dir', [ __CLASS__, 'filter_private_upload_dir' ] );
		}
	}

	/**
	 * Restore the normal WordPress upload destination.
	 */
	public static function end_private_upload(): void {
		self::$private_upload_depth = max( 0, self::$private_upload_depth - 1 );

		if ( 0 === self::$private_upload_depth ) {
			remove_filter( 'upload_dir', [ __CLASS__, 'filter_private_upload_dir' ] );
		}
	}

	/**
	 * Keep support media under uploads/yoohw-support-portal and out of public listings.
	 *
	 * @param array $uploads WordPress upload directory data.
	 * @return array
	 */
	public static function filter_private_upload_dir( $uploads ) {
		if ( ! is_array( $uploads ) || ! empty( $uploads['error'] ) || empty( $uploads['basedir'] ) || empty( $uploads['baseurl'] ) ) {
			return $uploads;
		}

		$site_token = substr(
			hash_hmac( 'sha256', (string) get_current_blog_id(), wp_salt( 'auth' ) ),
			0,
			32
		);
		$subdir = '/yoohw-support-portal/private/' . $site_token . '/' . gmdate( 'Y/m' );
		$path   = trailingslashit( $uploads['basedir'] ) . ltrim( $subdir, '/' );

		$uploads['path']   = $path;
		$uploads['url']    = trailingslashit( $uploads['baseurl'] ) . ltrim( $subdir, '/' );
		$uploads['subdir'] = $subdir;

		self::ensure_direct_access_protection( $path );

		return $uploads;
	}

	public static function url( $attachment_id, $image_size = '' ) {
		$attachment_id = absint( $attachment_id );

		if ( ! self::topic_id_for_attachment( $attachment_id ) ) {
			return (string) wp_get_attachment_url( $attachment_id );
		}

		$args = [
				'action'        => self::ACTION,
				'attachment_id' => $attachment_id,
			];

		if ( is_string( $image_size ) && '' !== $image_size && 'full' !== $image_size ) {
			$args['image_size'] = sanitize_key( $image_size );
		}

		return wp_nonce_url(
			add_query_arg( $args, admin_url( 'admin-post.php' ) ),
			self::ACTION . '_' . $attachment_id
		);
	}

	public static function filter_attachment_url( $url, $attachment_id ) {
		return self::topic_id_for_attachment( $attachment_id ) ? self::url( $attachment_id ) : $url;
	}

	public static function filter_image_downsize( $downsize, $attachment_id, $size ) {
		if ( ! self::topic_id_for_attachment( $attachment_id ) || ! wp_attachment_is_image( $attachment_id ) ) {
			return $downsize;
		}

		$metadata = wp_get_attachment_metadata( $attachment_id );
		$width    = absint( $metadata['width'] ?? 0 );
		$height   = absint( $metadata['height'] ?? 0 );

		if ( is_string( $size ) && 'full' !== $size && ! empty( $metadata['sizes'][ $size ] ) ) {
			$width  = absint( $metadata['sizes'][ $size ]['width'] ?? $width );
			$height = absint( $metadata['sizes'][ $size ]['height'] ?? $height );
		}

		return [ self::url( $attachment_id, $size ), $width, $height, false ];
	}

	public static function deny_anonymous() {
		status_header( 403 );
		nocache_headers();
		wp_die(
			esc_html__( 'You must be signed in and related to this support topic to access this attachment.', 'yoohw-support-portal' ),
			esc_html__( 'Attachment access denied', 'yoohw-support-portal' ),
			[ 'response' => 403 ]
		);
	}

	public static function serve() {
		$attachment_id = isset( $_GET['attachment_id'] ) ? absint( wp_unslash( $_GET['attachment_id'] ) ) : 0;
		$nonce         = isset( $_GET['_wpnonce'] ) ? sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ) : '';

		if ( ! $attachment_id || ! wp_verify_nonce( $nonce, self::ACTION . '_' . $attachment_id ) ) {
			status_header( 403 );
			nocache_headers();
			wp_die(
				esc_html__( 'This attachment link is invalid or has expired.', 'yoohw-support-portal' ),
				esc_html__( 'Attachment access denied', 'yoohw-support-portal' ),
				[ 'response' => 403 ]
			);
		}

		$topic_id      = self::topic_id_for_attachment( $attachment_id );

		if ( ! $topic_id || ! self::current_user_can_access_topic( $topic_id ) ) {
			self::deny_anonymous();
		}

		$image_size = isset( $_GET['image_size'] ) ? sanitize_key( wp_unslash( $_GET['image_size'] ) ) : '';
		$file       = self::file_for_image_size( $attachment_id, $image_size );

		if ( ! $file || ! is_file( $file ) || ! is_readable( $file ) ) {
			status_header( 404 );
			nocache_headers();
			wp_die( esc_html__( 'Attachment not found.', 'yoohw-support-portal' ), '', [ 'response' => 404 ] );
		}

		self::stream_file( $attachment_id, $file );
	}

	private static function topic_id_for_attachment( $attachment_id ) {
		if ( self::is_public_site_media( $attachment_id ) ) {
			return 0;
		}

		$attachment = get_post( absint( $attachment_id ) );

		if ( ! $attachment || 'attachment' !== $attachment->post_type ) {
			return 0;
		}

		$topic = get_post( $attachment->post_parent );

		if ( ! $topic || 'post' !== $topic->post_type ) {
			return 0;
		}

		$topic_ids = get_post_meta( $topic->ID, self::META_IDS, true );

		if ( is_array( $topic_ids ) && in_array( (int) $attachment->ID, array_map( 'absint', $topic_ids ), true ) ) {
			return (int) $topic->ID;
		}

		// phpcs:disable WordPress.DB.SlowDBQuery.slow_db_query_meta_key -- Required fallback lookup for legacy reply attachments; scoped to one topic.
		$comments = get_comments(
			[
				'post_id'    => $topic->ID,
				'status'     => 'all',
				'meta_key'   => self::META_IDS,
				'number'     => 0,
			]
		);
		// phpcs:enable WordPress.DB.SlowDBQuery.slow_db_query_meta_key

		foreach ( $comments as $comment ) {
			$ids = get_comment_meta( $comment->comment_ID, self::META_IDS, true );

			if ( is_array( $ids ) && in_array( (int) $attachment->ID, array_map( 'absint', $ids ), true ) ) {
				return (int) $topic->ID;
			}
		}

		return get_post_meta( $attachment->ID, self::META_PROTECTED, true ) ? (int) $topic->ID : 0;
	}

	private static function ensure_direct_access_protection( string $directory ): void {
		$directory = wp_normalize_path( $directory );

		if ( isset( self::$protected_directories[ $directory ] ) ) {
			return;
		}

		self::$protected_directories[ $directory ] = true;

		if ( ! wp_mkdir_p( $directory ) ) {
			return;
		}

		require_once ABSPATH . 'wp-admin/includes/file.php';

		global $wp_filesystem;

		if ( ! WP_Filesystem() || ! $wp_filesystem ) {
			return;
		}

		$protection_files = [
			'index.php'   => "<?php\n// Silence is golden.\n",
			'.htaccess'   => "Options -Indexes\n<IfModule mod_authz_core.c>\nRequire all denied\n</IfModule>\n<IfModule !mod_authz_core.c>\nDeny from all\n</IfModule>\n",
			'web.config'  => '<?xml version="1.0" encoding="UTF-8"?><configuration><system.webServer><security><authorization><remove users="*" roles="" verbs=""/><add accessType="Deny" users="*"/></authorization></security></system.webServer></configuration>',
		];

		foreach ( $protection_files as $file_name => $contents ) {
			$file = trailingslashit( $directory ) . $file_name;

			if ( ! $wp_filesystem->exists( $file ) ) {
				$wp_filesystem->put_contents( $file, $contents, FS_CHMOD_FILE );
			}
		}
	}

	private static function is_public_site_media( $attachment_id ) {
		$attachment_id = absint( $attachment_id );
		$public_ids    = [
			absint( get_option( 'site_icon' ) ),
			absint( get_option( 'site_logo' ) ),
			absint( get_theme_mod( 'custom_logo' ) ),
		];

		return $attachment_id && in_array( $attachment_id, array_filter( $public_ids ), true );
	}

	private static function file_for_image_size( $attachment_id, $image_size ) {
		$file = get_attached_file( $attachment_id );

		if ( ! $file || ! is_string( $image_size ) || '' === $image_size || 'full' === $image_size ) {
			return $file;
		}

		$metadata = wp_get_attachment_metadata( $attachment_id );
		$variant  = $metadata['sizes'][ $image_size ]['file'] ?? '';

		return $variant ? trailingslashit( dirname( $file ) ) . basename( $variant ) : $file;
	}

	private static function current_user_can_access_topic( $topic_id ) {
		$user_id = get_current_user_id();

		if ( ! $user_id ) {
			return false;
		}

		$manager_caps = (array) apply_filters(
			'yoohw_support_attachment_manager_capabilities',
			[ YoOhw_Support_Capabilities::MANAGE_TOPICS ]
		);

		foreach ( $manager_caps as $capability ) {
			if ( $capability && user_can( $user_id, $capability ) ) {
				return true;
			}
		}

		$topic = get_post( $topic_id );

		if ( $topic && (int) $topic->post_author === $user_id ) {
			return true;
		}

		$reply_ids = get_comments(
			[
				'post_id' => $topic_id,
				'user_id' => $user_id,
				'status'  => 'all',
				'fields'  => 'ids',
				'number'  => 1,
			]
		);

		return ! empty( $reply_ids );
	}

	private static function stream_file( $attachment_id, $file ) {
		$size  = (int) filesize( $file );
		$start = 0;
		$end   = max( 0, $size - 1 );
		$mime  = get_post_mime_type( $attachment_id );
		$mime  = $mime ? $mime : 'application/octet-stream';
		$name  = get_post_meta( $attachment_id, '_yoohw_original_filename', true );
		$name  = $name ? sanitize_file_name( $name ) : sanitize_file_name( basename( $file ) );

		if ( isset( $_SERVER['HTTP_RANGE'] ) && preg_match( '/bytes=(\d*)-(\d*)/', sanitize_text_field( wp_unslash( $_SERVER['HTTP_RANGE'] ) ), $matches ) ) {
			$start = '' !== $matches[1] ? min( (int) $matches[1], $end ) : 0;
			$end   = '' !== $matches[2] ? min( (int) $matches[2], $end ) : $end;

			if ( $start > $end ) {
				status_header( 416 );
				exit;
			}

			status_header( 206 );
			header( sprintf( 'Content-Range: bytes %d-%d/%d', $start, $end, $size ) );
		}

		while ( ob_get_level() ) {
			ob_end_clean();
		}

		nocache_headers();
		header( 'X-Content-Type-Options: nosniff' );
		header( 'Accept-Ranges: bytes' );
		header( 'Content-Type: ' . $mime );
		header( 'Content-Length: ' . ( ( $end - $start ) + 1 ) );
		header( 'Content-Disposition: inline; filename="' . str_replace( '"', '', $name ) . '"; filename*=UTF-8\'\'' . rawurlencode( $name ) );

		$handle    = fopen( $file, 'rb' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen -- Byte-range streaming requires a seekable handle.
		$remaining = ( $end - $start ) + 1;
		fseek( $handle, $start );

		while ( $remaining > 0 && ! feof( $handle ) ) {
			$chunk = fread( $handle, min( 8192, $remaining ) ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fread -- Streams bounded chunks without loading the full file.

			if ( false === $chunk ) {
				break;
			}

			echo $chunk; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			$remaining -= strlen( $chunk );
		}

		fclose( $handle ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose -- Closes the stream opened above.
		exit;
	}
}
