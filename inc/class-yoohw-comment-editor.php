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
			wp_editor(
				'',
				'comment',
				[
					'textarea_name' => 'comment',
					'textarea_rows' => 8,
					'media_buttons' => false,
					'teeny'         => false,
					'quicktags'     => false,
					'tinymce'       => [
						'toolbar1'                => 'bold,italic,underline,bullist,numlist,link,unlink,blockquote,yoohw_support_codeblock',
						'height'                  => 220,
						'extended_valid_elements' => 'pre[class],a[href|target|rel|title]',
						'content_style'           => 'pre.wp-code-block{white-space:pre;tab-size:4;font-family:ui-monospace,SFMono-Regular,Menlo,Monaco,Consolas,monospace;font-size:13px;line-height:1.45;}',
						'setup'                   => 'function (editor) {
							function inPre(){ return editor.dom.getParent(editor.selection.getStart(), "pre"); }
							function setActive(api){ function update(){ api.setActive(!!inPre()); } editor.on("NodeChange", update); return function(){ editor.off("NodeChange", update); }; }
							function htmlEscape(s){ s=(s||""); return s.replace(/&/g,"&amp;").replace(/</g,"&lt;").replace(/>/g,"&gt;"); }

							function togglePre(){
								editor.undoManager.transact(function(){
									var pre = inPre();
									if (pre) {
										var html = pre.textContent.replace(/\\n/g, "<br>");
										var p = editor.dom.create("p", {}, html);
										editor.dom.replace(p, pre);
										editor.selection.setCursorLocation(p, 0);
									} else {
										var content = editor.selection.getContent({format:"text"});
										if (!content) {
											var node = editor.selection.getStart();
											content = (node && node.textContent) ? node.textContent : "";
										}
										content = htmlEscape(content);
										if (content === "") content = "\\n";
										editor.insertContent("<pre class=\\"wp-code-block\\">" + content + "</pre>");
									}
								});
							}

							function looksLikeCode(t, h){
								t = t || ""; h = h || "";
								var multi     = /\\n/.test(t);
								var indented  = /^(?:\\t| {2,})\\S/m.test(t);
								var braces    = /[{};]/.test(t);
								var fnlike    = /^[\\t ]*(?:function|class|if|for|while|switch|return|const|let|var|public|private|protected)\\b/m.test(t);
								var php       = /^[\\t ]*<\\?php/m.test(t);
								var codeHtml  = /<(pre|code)/i.test(h);
								return codeHtml || ((multi || indented) && (braces || fnlike || php));
							}

							editor.on("paste", function(e){
								var html = "", text = "";

								if (e.clipboardData && e.clipboardData.getData) {
									html = e.clipboardData.getData("text/html") || "";
									text = e.clipboardData.getData("text/plain") || "";
								} else if (window.clipboardData && window.clipboardData.getData) {
									text = window.clipboardData.getData("Text") || "";
								}

								if (inPre()) {
									e.preventDefault();
									text = (text || "").replace(/\\r\\n?/g, "\\n");
									editor.insertContent(htmlEscape(text));
									return;
								}

								var hasRichTags = /<(ul|ol|li|b|strong|i|em|a|p|br|h[1-6]|table|tr|td|th|blockquote)/i.test(html);

								if (looksLikeCode(text, html) && !hasRichTags) {
									e.preventDefault();
									text = (text || "").replace(/\\r\\n?/g, "\\n");
									editor.insertContent("<pre class=\\"wp-code-block\\">" + htmlEscape(text) + "</pre>");
									return;
								}
							});

							var enterCount = 0, enterTimer = null;
							editor.on("keydown", function(e){
								var key = e.key || e.keyCode;
								var isEnter = (key === "Enter" || key === 13);
								var pre = inPre();

								if (!pre) {
									if (enterTimer){ clearTimeout(enterTimer); enterTimer=null; }
									enterCount = 0;
									return;
								}

								if (isEnter) {
									if (e.shiftKey) { enterCount = 0; return; }
									enterCount++;
									if (enterTimer){ clearTimeout(enterTimer); }
									enterTimer = setTimeout(function(){ enterCount=0; enterTimer=null; }, 700);

									if (enterCount >= 2) {
										e.preventDefault();
										editor.undoManager.transact(function(){
											var txt = pre.textContent || "";
											if (/\\n$/.test(txt)) { pre.textContent = txt.replace(/\\n$/, ""); }
											var p = editor.dom.create("p", {}, "");
											if (pre.nextSibling) pre.parentNode.insertBefore(p, pre.nextSibling);
											else pre.parentNode.appendChild(p);
											if (!(pre.textContent || "").trim()) { pre.parentNode.removeChild(pre); }
											editor.selection.setCursorLocation(p, 0);
										});
										enterCount = 0;
										if (enterTimer){ clearTimeout(enterTimer); enterTimer=null; }
									}
								} else if (e.keyCode !== 16) {
									if (enterTimer){ clearTimeout(enterTimer); enterTimer=null; }
									enterCount = 0;
								}
							});

							if (editor.ui && editor.ui.registry && editor.ui.registry.addToggleButton) {
								editor.ui.registry.addToggleButton("yoohw_support_codeblock", {
									tooltip: "Code block",
									text: "</>",
									onAction: togglePre,
									onSetup: function(api){ return setActive(api); }
								});
							} else if (editor.addButton) {
								editor.addButton("yoohw_support_codeblock", {
									text: "</>",
									tooltip: "Code block",
									onclick: togglePre,
									onpostrender: function(){
										var ctrl = this;
										editor.on("NodeChange", function(){ ctrl.active(!!inPre()); });
									}
								});
							}
						}',
					],
				]
			);
			?>
			</div>
		</div>

		<?php do_action( 'yoohw_support_reply_form_after_editor' ); ?>

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
						absint( self::MAX_ATTACHMENT_COUNT ),
						esc_html( size_format( self::MAX_ATTACHMENT_BYTES ) )
					);
					?>
				</span>
			</div>
			<label class="yoohw-upload-dropzone" for="yoohw_comment_attachments">
				<span class="yoohw-upload-dropzone-icon"><?php YoOhw_Support_Icons::output( 'paperclip' ); ?></span>
				<span class="yoohw-upload-dropzone-title"><?php esc_html_e( 'Choose or drop screenshots, videos, or ZIP files', 'yoohw-support-portal' ); ?></span>
				<span class="yoohw-upload-dropzone-note"><?php esc_html_e( 'JPG, PNG, WebP, GIF, MP4, WebM, MOV, ZIP', 'yoohw-support-portal' ); ?></span>
			</label>
			<input
				type="file"
				class="yoohw-upload-input"
				id="yoohw_comment_attachments"
				name="yoohw_attachments[]"
				multiple
				accept="image/jpeg,image/png,image/webp,image/gif,video/mp4,video/webm,video/quicktime,application/zip,.zip"
				data-max-files="<?php echo esc_attr( self::MAX_ATTACHMENT_COUNT ); ?>"
				data-max-size="<?php echo esc_attr( self::MAX_ATTACHMENT_BYTES ); ?>"
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
		<?php

		$args['comment_field'] = ob_get_clean();
		$args['format']        = 'xhtml';

		return $args;
	}

	public static function allow_code_tags_in_comments( $tags, $context ) {
		if ( 'comment' === $context ) {
			$tags['pre']          = isset( $tags['pre'] ) ? $tags['pre'] : [];
			$tags['pre']['class'] = true;

			$tags['a']           = isset( $tags['a'] ) ? $tags['a'] : [];
			$tags['a']['href']   = true;
			$tags['a']['title']  = true;
			$tags['a']['target'] = true;
			$tags['a']['rel']    = true;

			if ( isset( $tags['code'] ) ) {
				unset( $tags['code'] );
			}
		}

		return $tags;
	}

	private static function get_allowed_attachment_mimes() {
		return [
			'jpg|jpeg' => 'image/jpeg',
			'png'      => 'image/png',
			'webp'     => 'image/webp',
			'gif'      => 'image/gif',
			'mp4'      => 'video/mp4',
			'webm'     => 'video/webm',
			'mov|qt'   => 'video/quicktime',

			// Archives
			'zip'      => 'application/zip',
		];
	}

	public static function validate_comment_attachments( $commentdata ) {
		self::validate_attachment_uploads();
		return $commentdata;
	}

	private static function validate_attachment_uploads( $field_name = 'yoohw_attachments' ) {
		// phpcs:disable WordPress.Security.NonceVerification.Missing,WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Core verifies the comment nonce/state; WordPress media APIs require the original upload array.
		if ( empty( $_FILES[ $field_name ] ) || empty( $_FILES[ $field_name ]['name'] ) ) {
			return;
		}

		$files = $_FILES[ $field_name ];
		$names = is_array( $files['name'] ) ? $files['name'] : [ $files['name'] ];

		$uploaded_count = 0;

		foreach ( $names as $index => $name ) {
			if ( empty( $name ) ) {
				continue;
			}

			$error = is_array( $files['error'] ) ? (int) $files['error'][ $index ] : (int) $files['error'];

			if ( UPLOAD_ERR_NO_FILE === $error ) {
				continue;
			}

			$uploaded_count++;
		}

		if ( $uploaded_count > self::MAX_ATTACHMENT_COUNT ) {
			wp_die(
				esc_html(
					sprintf(
					/* translators: %d: Maximum number of attachments. */
						__( 'You can upload a maximum of %d attachments per comment.', 'yoohw-support-portal' ),
						self::MAX_ATTACHMENT_COUNT
					)
				)
			);
		}
		// phpcs:enable WordPress.Security.NonceVerification.Missing,WordPress.Security.ValidatedSanitizedInput.InputNotSanitized

		foreach ( $names as $index => $name ) {
			if ( empty( $name ) ) {
				continue;
			}

			$error = is_array( $files['error'] ) ? (int) $files['error'][ $index ] : (int) $files['error'];
			$size  = is_array( $files['size'] ) ? (int) $files['size'][ $index ] : (int) $files['size'];
			$tmp   = is_array( $files['tmp_name'] ) ? $files['tmp_name'][ $index ] : $files['tmp_name'];

			if ( UPLOAD_ERR_NO_FILE === $error ) {
				continue;
			}

			if ( UPLOAD_ERR_OK !== $error ) {
				wp_die( esc_html__( 'One of the attachments could not be uploaded. Please try again.', 'yoohw-support-portal' ) );
			}

			if ( $size > self::MAX_ATTACHMENT_BYTES ) {
				wp_die( esc_html__( 'One of the attachments is too large. Maximum size is 5 MB per file.', 'yoohw-support-portal' ) );
			}

			$check = wp_check_filetype_and_ext( $tmp, $name, self::get_allowed_attachment_mimes() );

			if ( empty( $check['type'] ) ) {
				wp_die(
					esc_html__(
						'Unsupported attachment type. Please upload JPG, PNG, WebP, GIF, MP4, WebM, MOV, or ZIP files only.',
						'yoohw-support-portal'
					)
				);
			}
		}
	}

	private static function generate_safe_attachment_filename( $filename ) {
		$ext = pathinfo( $filename, PATHINFO_EXTENSION );

		$ext = $ext ? strtolower( sanitize_key( $ext ) ) : '';

		$unique = 'yoohw-' . gmdate( 'Ymd-His' ) . '-' . wp_generate_password( 12, false, false );

		return $ext ? $unique . '.' . $ext : $unique;
	}

	private static function handle_attachment_uploads( $parent_id, $field_name = 'yoohw_attachments' ) {
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

			if ( 0 === strpos( $mime, 'image/' ) ) {
				$alt   = get_post_meta( $attachment_id, '_wp_attachment_image_alt', true );
				$image = '<img class="yoohw-attachment-image" src="' . esc_url( $url ) . '" alt="' . esc_attr( $alt ) . '" loading="lazy" decoding="async">';

				if ( $image ) {
					$html .= '<figure class="yoohw-attachment yoohw-attachment--image"><a href="' . esc_url( $url ) . '" target="_blank" rel="noopener noreferrer">' . $image . '</a></figure>';
				}
			} elseif ( 0 === strpos( $mime, 'video/' ) ) {
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
					$html .= ' archive · ' . esc_html( size_format( $file_size ) );
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
