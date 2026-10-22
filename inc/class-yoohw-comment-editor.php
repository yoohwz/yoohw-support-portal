<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_action( 'init', [ 'YoOhw_Comment_Editor', 'init' ] );

class YoOhw_Comment_Editor {

	const COMMENT_META_ATTACHMENT_IDS = '_yoohw_attachment_ids';
	const MAX_ATTACHMENT_COUNT        = 5;
	const MAX_ATTACHMENT_BYTES        = 5242880; // 5 MB.

	public static function init() {
		add_filter( 'preprocess_comment', [ __CLASS__, 'authorize_support_comment' ], 5 );
		add_filter( 'comment_form_defaults', [ __CLASS__, 'filter_comment_form_defaults' ] );
		add_action( 'wp_enqueue_scripts', [ __CLASS__, 'enqueue_comment_code_assets' ] );

		add_filter( 'wp_kses_allowed_html', [ __CLASS__, 'allow_code_tags_in_comments' ], 10, 2 );

		// Convert plain URLs and add target blank for external links.
		add_filter( 'preprocess_comment', [ __CLASS__, 'preprocess_comment_links' ], 20 );

		// Also support older existing comments.
		add_filter( 'comment_text', [ __CLASS__, 'filter_comment_text_links' ], 9 );

		add_filter( 'preprocess_comment', [ __CLASS__, 'validate_comment_attachments' ], 8 );
		add_filter( 'preprocess_comment', [ __CLASS__, 'normalize_new_support_reply' ], 9 );
		add_action( 'comment_post', [ __CLASS__, 'store_comment_attachments' ], 20, 2 );
		add_filter( 'comment_text', [ __CLASS__, 'append_comment_attachments' ], 20, 3 );
	}

	public static function authorize_support_comment( $commentdata ) {
		$post_id = absint( $commentdata['comment_post_ID'] ?? 0 );
		$post    = $post_id ? get_post( $post_id ) : null;

		if ( ! $post instanceof WP_Post || 'post' !== $post->post_type ) {
			return $commentdata;
		}

		$is_frontend_submission = ! is_admin()
			&& ! wp_doing_ajax()
			&& ! ( defined( 'REST_REQUEST' ) && REST_REQUEST )
			&& isset( $_SERVER['REQUEST_METHOD'] )
			&& 'POST' === strtoupper( sanitize_text_field( wp_unslash( $_SERVER['REQUEST_METHOD'] ) ) );

		if (
			$is_frontend_submission &&
			(
				empty( $_POST['yoohw_support_comment_nonce'] ) ||
				! wp_verify_nonce(
					sanitize_text_field( wp_unslash( $_POST['yoohw_support_comment_nonce'] ) ),
					'yoohw_support_comment'
				)
			)
		) {
			wp_die(
				esc_html__( 'Security check failed.', 'yoohw-support-portal' ),
				esc_html__( 'Reply not allowed', 'yoohw-support-portal' ),
				[ 'response' => 403 ]
			);
		}

		if ( ! is_user_logged_in() ) {
			wp_die(
				esc_html__( 'You must be signed in to reply to this topic.', 'yoohw-support-portal' ),
				esc_html__( 'Reply not allowed', 'yoohw-support-portal' ),
				[ 'response' => 403 ]
			);
		}

		if (
			current_user_can( YoOhw_Support_Capabilities::MANAGE_TOPICS ) ||
			(int) $post->post_author === get_current_user_id()
		) {
			return $commentdata;
		}

		wp_die(
			esc_html__( 'You do not have permission to reply to this topic.', 'yoohw-support-portal' ),
			esc_html__( 'Reply not allowed', 'yoohw-support-portal' ),
			[ 'response' => 403 ]
		);
	}

	public static function normalize_new_support_reply( $data ) {
		// Only the portal's new-reply submission; never normalize edits or historic reads.
		if ( empty( $_POST['yoohw_support_comment_nonce'] ) ) { return $data; }
		if ( ! is_string( $_POST['yoohw_support_comment_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['yoohw_support_comment_nonce'] ) ), 'yoohw_support_comment' ) ) {
			wp_die( esc_html__( 'Security check failed.', 'yoohw-support-portal' ), '', [ 'response' => 403 ] );
		}
		$content = YoOhw_Support_Composer::prepare( wp_unslash( $data['comment_content'] ?? '' ) );
		if ( is_wp_error( $content ) ) { wp_die( esc_html( $content->get_error_message() ), '', [ 'response' => 400 ] ); }
		$data['comment_content'] = wp_slash( $content );
		return $data;
	}

	public static function preprocess_comment_links( $commentdata ) {
		if ( ! empty( $commentdata['comment_content'] ) ) {
			$commentdata['comment_content'] = self::auto_link_and_target_blank( $commentdata['comment_content'] );
		}

		return $commentdata;
	}

	public static function filter_comment_text_links( $comment_text ) {
		return self::auto_link_and_target_blank( $comment_text );
	}

	public static function auto_link_and_target_blank( $content ) {
		if ( empty( $content ) || ! is_string( $content ) ) {
			return $content;
		}

		$parts = preg_split( '/(<pre\b[^>]*>.*?<\/pre>)/is', $content, -1, PREG_SPLIT_DELIM_CAPTURE );

		if ( empty( $parts ) ) {
			return $content;
		}

		foreach ( $parts as $index => $part ) {
			if ( preg_match( '/^<pre\b[^>]*>.*?<\/pre>$/is', $part ) ) {
				continue;
			}

			$part = make_clickable( $part );
			$part = self::add_target_blank_to_external_links( $part );

			$parts[ $index ] = $part;
		}

		return implode( '', $parts );
	}

	private static function add_target_blank_to_external_links( $content ) {
		$site_host = wp_parse_url( home_url(), PHP_URL_HOST );
		$site_host = self::normalize_host( $site_host );

		return preg_replace_callback(
			'/<a\s[^>]*href=("|\')(.*?)\1[^>]*>/i',
			function ( $matches ) use ( $site_host ) {
				$tag = $matches[0];
				$url = $matches[2];

				$link_host = wp_parse_url( $url, PHP_URL_HOST );
				$link_host = self::normalize_host( $link_host );

				// Relative link or same-domain link.
				if ( empty( $link_host ) || $link_host === $site_host ) {
					return $tag;
				}

				$tag = preg_replace( '/\s*target=("|\').*?\1/i', '', $tag );
				$tag = preg_replace( '/\s*rel=("|\').*?\1/i', '', $tag );

				return str_replace(
					'<a ',
					'<a target="_blank" rel="noopener noreferrer" ',
					$tag
				);
			},
			$content
		);
	}

	private static function normalize_host( $host ) {
		$host = is_string( $host ) ? strtolower( trim( $host ) ) : '';
		return preg_replace( '/^www\./', '', $host );
	}

	public static function filter_comment_form_defaults( $args ) {
		if ( ! is_user_logged_in() ) {
			return $args;
		}

		ob_start();
		?>
		<div class="comment-form-comment yoohw-reply-editor-field">
			<?php wp_nonce_field( 'yoohw_support_comment', 'yoohw_support_comment_nonce' ); ?>
			<label class="yoohw-reply-field-label" for="comment">
				<?php YoOhw_Support_Icons::output( 'message-square' ); ?>
				<?php esc_html_e( 'Message', 'yoohw-support-portal' ); ?>
			</label>
			<div class="yoohw-reply-editor-shell">
			<?php
			wp_editor( '', 'comment', YoOhw_Support_Composer::editor_settings( 'comment' ) );
			?>
			</div>
		</div>

		<?php do_action( 'yoohw_support_reply_form_after_editor' ); ?>

		<?php if ( YoOhw_Support_Settings::uploads_enabled( 'reply' ) ) : ?>
		<div class="comment-form-yoohw-attachments yoohw-reply-upload">
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
						absint( YoOhw_Support_Settings::attachment_max_count() ),
						esc_html( size_format( YoOhw_Support_Settings::attachment_max_bytes() ) )
					);
					?>
				</span>
			</div>
			<label class="yoohw-upload-dropzone" for="yoohw_comment_attachments">
				<span class="yoohw-upload-dropzone-icon"><?php YoOhw_Support_Icons::output( 'paperclip' ); ?></span>
				<span class="yoohw-upload-dropzone-title"><?php esc_html_e( 'Choose or drop files', 'yoohw-support-portal' ); ?></span>
				<span class="yoohw-upload-dropzone-note"><?php echo esc_html( YoOhw_Support_Settings::attachment_type_label() ); ?></span>
			</label>
			<input
				type="file"
				class="yoohw-upload-input"
				id="yoohw_comment_attachments"
				name="yoohw_attachments[]"
				multiple
				accept="<?php echo esc_attr( YoOhw_Support_Settings::attachment_accept() ); ?>"
				data-max-files="<?php echo esc_attr( YoOhw_Support_Settings::attachment_max_count() ); ?>"
				data-max-size="<?php echo esc_attr( YoOhw_Support_Settings::attachment_max_bytes() ); ?>"
				aria-describedby="yoohw_comment_attachments_selected"
			>
			<div
				id="yoohw_comment_attachments_selected"
				class="yoohw-upload-selected is-empty"
				data-upload-selected-for="yoohw_comment_attachments"
				data-empty-label="<?php esc_attr_e( 'No files selected', 'yoohw-support-portal' ); ?>"
			>
				<?php esc_html_e( 'No files selected', 'yoohw-support-portal' ); ?>
			</div>
		</div>
		<?php endif; ?>
		<?php

		$args['comment_field'] = ob_get_clean();
		$args['format']        = 'xhtml';

		return $args;
	}

	public static function allow_code_tags_in_comments( $tags, $context ) {
		if ( in_array( $context, [ 'comment', 'pre_comment_content' ], true ) ) {
			$tags['pre']          = isset( $tags['pre'] ) ? $tags['pre'] : [];
			$tags['pre']['class'] = true;

			$tags['a']           = isset( $tags['a'] ) ? $tags['a'] : [];
			$tags['a']['href']   = true;
			$tags['a']['title']  = true;
			$tags['a']['target'] = true;
			$tags['a']['rel']    = true;

			$tags = array_merge( $tags, YoOhw_Support_Composer::allowed_html() );
		}

		return $tags;
	}

	private static function get_allowed_attachment_mimes() {
		return YoOhw_Support_Settings::attachment_mimes();
	}

	public static function validate_comment_attachments( $commentdata ) {
		self::validate_attachment_uploads();
		return $commentdata;
	}

	private static function validate_attachment_uploads( $field_name = 'yoohw_attachments' ) {
		YoOhw_Shortcode::validate_attachment_uploads( $field_name, 'reply' );
	}

	private static function generate_safe_attachment_filename( $filename ) {
		$ext = pathinfo( $filename, PATHINFO_EXTENSION );

		$ext = $ext ? strtolower( sanitize_key( $ext ) ) : '';

		$unique = 'yoohw-' . gmdate( 'Ymd-His' ) . '-' . wp_generate_password( 12, false, false );

		return $ext ? $unique . '.' . $ext : $unique;
	}

	private static function handle_attachment_uploads( $parent_id, $field_name = 'yoohw_attachments' ) {
		self::validate_attachment_uploads( $field_name );
		// phpcs:disable WordPress.Security.NonceVerification.Missing,WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Core verifies the comment submission; media_handle_sideload() requires original upload fields.
		if ( empty( $_FILES[ $field_name ] ) || empty( $_FILES[ $field_name ]['name'] ) ) {
			return [];
		}

		require_once ABSPATH . 'wp-admin/includes/file.php';
		require_once ABSPATH . 'wp-admin/includes/media.php';
		require_once ABSPATH . 'wp-admin/includes/image.php';

		$attachment_ids = [];
		$files          = $_FILES[ $field_name ];
		$file_count     = is_array( $files['name'] ) ? count( $files['name'] ) : 1;

		for ( $i = 0; $i < $file_count; $i++ ) {
			$name = is_array( $files['name'] ) ? $files['name'][ $i ] : $files['name'];

			if ( empty( $name ) ) {
				continue;
			}

			$original_name = $name;
			$safe_name     = self::generate_safe_attachment_filename( $original_name );

			$file = [
				'name'     => $safe_name,
				'type'     => is_array( $files['type'] ) ? $files['type'][ $i ] : $files['type'],
				'tmp_name' => is_array( $files['tmp_name'] ) ? $files['tmp_name'][ $i ] : $files['tmp_name'],
				'error'    => is_array( $files['error'] ) ? $files['error'][ $i ] : $files['error'],
				'size'     => is_array( $files['size'] ) ? $files['size'][ $i ] : $files['size'],
			];

			$_FILES['yoohw_single_attachment'] = $file;

			YoOhw_Protected_Attachments::begin_private_upload();

			try {
				$attachment_id = media_handle_upload(
					'yoohw_single_attachment',
					$parent_id,
					[],
					[
						'test_form' => false,
						'mimes'     => self::get_allowed_attachment_mimes(),
					]
				);
			} finally {
				YoOhw_Protected_Attachments::end_private_upload();
			}

			unset( $_FILES['yoohw_single_attachment'] );

			if ( is_wp_error( $attachment_id ) ) {
				update_option( YoOhw_Support_Private_Storage::ERROR_OPTION, $attachment_id->get_error_message(), false );
				wp_die( esc_html__( 'The private attachment could not be stored. Contact the site administrator before retrying.', 'yoohw-support-portal' ), '', [ 'response' => 503 ] );
			}
			if ( ! is_wp_error( $attachment_id ) ) {
				update_post_meta( $attachment_id, '_yoohw_original_filename', sanitize_file_name( $original_name ) );
				YoOhw_Protected_Attachments::protect( $attachment_id );
				$attachment_ids[] = (int) $attachment_id;
			}
		}

		// phpcs:enable WordPress.Security.NonceVerification.Missing,WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		return $attachment_ids;
	}

	public static function store_comment_attachments( $comment_id, $comment_approved ) {
		$comment = get_comment( $comment_id );

		if ( ! $comment ) {
			return;
		}

		$attachment_ids = self::handle_attachment_uploads( (int) $comment->comment_post_ID );

		if ( ! empty( $attachment_ids ) ) {
			update_comment_meta( $comment_id, self::COMMENT_META_ATTACHMENT_IDS, $attachment_ids );
		}
	}

	public static function append_comment_attachments( $comment_text, $comment = null, $args = [] ) {
		if ( ! $comment instanceof WP_Comment ) {
			return $comment_text;
		}

		$attachment_ids = get_comment_meta( $comment->comment_ID, self::COMMENT_META_ATTACHMENT_IDS, true );

		if ( ! is_array( $attachment_ids ) || empty( $attachment_ids ) ) {
			return $comment_text;
		}

		return $comment_text . self::render_attachments_html( $attachment_ids );
	}

	private static function render_attachments_html( array $attachment_ids ) {
		$attachment_ids = array_values( array_filter( array_map( 'absint', $attachment_ids ) ) );

		if ( empty( $attachment_ids ) ) {
			return '';
		}

		$html = '<div class="yoohw-attachments">';

		foreach ( $attachment_ids as $attachment_id ) {
			$url  = YoOhw_Protected_Attachments::url( $attachment_id );
			$mime = get_post_mime_type( $attachment_id );

			if ( ! $url || ! $mime ) {
				continue;
			}

			if ( YoOhw_Support_Settings::attachment_previews_enabled() && 0 === strpos( $mime, 'image/' ) ) {
				$alt   = get_post_meta( $attachment_id, '_wp_attachment_image_alt', true );
				$image = '<img class="yoohw-attachment-image" src="' . esc_url( $url ) . '" alt="' . esc_attr( $alt ) . '" loading="lazy" decoding="async">';

				if ( $image ) {
					$html .= '<figure class="yoohw-attachment yoohw-attachment--image"><a href="' . esc_url( $url ) . '" target="_blank" rel="noopener noreferrer">' . $image . '</a></figure>';
				}
			} elseif ( YoOhw_Support_Settings::attachment_previews_enabled() && 0 === strpos( $mime, 'video/' ) ) {
				$html .= '<figure class="yoohw-attachment yoohw-attachment--video"><video class="yoohw-attachment-video" controls preload="metadata" src="' . esc_url( $url ) . '"></video></figure>';
			} else {
				$original_name = get_post_meta( $attachment_id, '_yoohw_original_filename', true );
				$file_name     = $original_name ? $original_name : get_the_title( $attachment_id );
				$attached_file = get_attached_file( $attachment_id );
				$file_size     = $attached_file && is_readable( $attached_file ) ? (int) filesize( $attached_file ) : 0;
				$file_ext      = strtoupper( pathinfo( $file_name, PATHINFO_EXTENSION ) );

				if ( ! $file_ext ) {
					$file_ext = esc_html__( 'FILE', 'yoohw-support-portal' );
				}

				$html .= '<a class="yoohw-attachment-file-card" href="' . esc_url( $url ) . '" target="_blank" rel="noopener noreferrer">';
				$html .= '<span class="yoohw-attachment-file-icon" aria-hidden="true">';
				$html .= class_exists( 'YoOhw_Support_Icons' ) ? YoOhw_Support_Icons::sanitize( YoOhw_Support_Icons::render( 'file-archive' ) ) : '';
				$html .= '</span>';
				$html .= '<span class="yoohw-attachment-file-info">';
				$html .= '<span class="yoohw-attachment-file-name">' . esc_html( $file_name ) . '</span>';
				$html .= '<span class="yoohw-attachment-file-meta">' . esc_html( $file_ext );

				if ( $file_size ) {
					$html .= ( 'application/zip' === $mime ? ' archive' : '' ) . ' · ' . esc_html( size_format( $file_size ) );
				}

				$html .= '</span>';
				$html .= '</span>';
				$html .= '</a>';
			}
		}

		$html .= '</div>';

		$allowed_html = array_merge( wp_kses_allowed_html( 'post' ), YoOhw_Support_Icons::allowed_html() );

		return wp_kses( $html, $allowed_html );
	}

	public static function enqueue_comment_code_assets() {
		if ( ! is_singular() || ( class_exists( 'YoOhw_Support_Router' ) && YoOhw_Support_Router::is_plugin_ui_request() ) ) {
			return;
		}

		$css_path = YOOHW_SUPPORT_PORTAL_PATH . 'assets/css/frontend-extras.css';
		$js_path  = YOOHW_SUPPORT_PORTAL_PATH . 'assets/js/frontend-extras.js';

		wp_enqueue_style( 'yoohw-support-portal-extras', YOOHW_SUPPORT_PORTAL_URL . 'assets/css/frontend-extras.css', [], file_exists( $css_path ) ? (string) filemtime( $css_path ) : YOOHW_SUPPORT_PORTAL_VERSION );
		wp_enqueue_script( 'yoohw-support-portal-extras', YOOHW_SUPPORT_PORTAL_URL . 'assets/js/frontend-extras.js', [], file_exists( $js_path ) ? (string) filemtime( $js_path ) : YOOHW_SUPPORT_PORTAL_VERSION, true );
	}

}
