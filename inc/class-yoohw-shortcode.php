<?php

if (!defined('ABSPATH')) {
    exit;
}

class YoOhw_Shortcode {

	const POST_META_ATTACHMENT_IDS = '_yoohw_attachment_ids';
	const MAX_ATTACHMENTS          = 5;
	const MAX_ATTACHMENT_BYTES     = 5242880; // 5 MB.


	/**
	 * Clean up inline styles + empty paragraphs from arbitrary HTML.
	 */
	public static function yoohw_clean_content( $content ) {
		// 1) Remove ALL inline style attributes
        $content = preg_replace( '/\sstyle\s*=\s*(["\']).*?\1/isu', '', $content );

		// 2) Remove ANY <p> that contains only whitespace, &nbsp;, <br>
		$content = preg_replace(
			'/<p[^>]*>(?:\s|&nbsp;|&#160;|<br\s*\/?>)*<\/p>/iu',
			'',
			$content
		);

		return $content;
	}

    public static function auto_link_and_target_blank( $content ) {

        // 1) Convert plain URLs to links
        $content = make_clickable( $content );

        $site_host = wp_parse_url( home_url(), PHP_URL_HOST );

        // 2) Modify only external <a> tags
        $content = preg_replace_callback(
            '/<a\s[^>]+href=("|\')(.*?)\1[^>]*>/i',
            function ( $matches ) use ( $site_host ) {

                $tag  = $matches[0];
                $url  = $matches[2];

                $link_host = wp_parse_url( $url, PHP_URL_HOST );

                // Skip if:
                // - relative link
                // - same domain
                if ( empty( $link_host ) || $link_host === $site_host ) {
                    return $tag;
                }

                // Remove existing target
                $tag = preg_replace( '/\s*target=("|\').*?\1/i', '', $tag );

                // Remove existing rel
                $tag = preg_replace( '/\s*rel=("|\').*?\1/i', '', $tag );

                // Add new attributes
                $tag = str_replace(
                    '<a ',
                    '<a target="_blank" rel="noopener noreferrer" ',
                    $tag
                );

                return $tag;
            },
            $content
        );

        return $content;
    }

	/**
	 * Access rule:
	 * - Access code mode: no access_code means public; otherwise user must have it.
	 * - User role mode: no role means public; otherwise user must have one selected role.
	 */
	public static function user_can_access_category( array $user_codes, int $term_id, int $user_id = 0 ) : bool {
		$access_type = function_exists( 'yoohw_category_get_access_type' )
			? yoohw_category_get_access_type( $term_id )
			: 'access_code';

		if ( 'user_role' === $access_type ) {
			$allowed_roles = function_exists( 'yoohw_category_get_access_roles' )
				? yoohw_category_get_access_roles( $term_id )
				: [];

			if ( empty( $allowed_roles ) ) {
				return true;
			}

			$user_id = $user_id > 0 ? $user_id : get_current_user_id();
			$user    = $user_id > 0 ? get_userdata( $user_id ) : null;

			if ( ! $user ) {
				return false;
			}

			return (bool) array_intersect( $allowed_roles, (array) $user->roles );
		}

		$cat_code = get_term_meta( $term_id, 'yoohw_support_access_code', true );
		$cat_code = is_string( $cat_code ) ? trim( $cat_code ) : '';

		// Public category if no code configured
		if ( $cat_code === '' ) {
			return true;
		}

		return in_array( $cat_code, $user_codes, true );
	}

	public static function user_can_access_category_effective( array $user_codes, int $term_id, int $user_id = 0 ) : bool {
		$term_id = absint( $term_id );

		if ( ! $term_id ) {
			return false;
		}

		$lineage = array_merge(
			array_reverse( get_ancestors( $term_id, 'category', 'taxonomy' ) ),
			[ $term_id ]
		);

		foreach ( $lineage as $lineage_term_id ) {
			if ( ! self::user_can_access_category( $user_codes, (int) $lineage_term_id, $user_id ) ) {
				return false;
			}
		}

		return true;
	}

	/**
	 * Allow extensions to reserve categories for non-portal intake workflows.
	 */
	public static function topic_category_selectable( int $term_id, int $user_id = 0, bool $is_admin = false ): bool {
		$term_id = absint( $term_id );

		if ( ! $term_id ) {
			return false;
		}

		return (bool) apply_filters(
			'yoohw_support_topic_category_selectable',
			true,
			$term_id,
			$user_id > 0 ? $user_id : get_current_user_id(),
			$is_admin
		);
	}

	public static function get_user_access_codes( int $user_id ) : array {
		$codes = get_user_meta( $user_id, 'yoohw_support_access_codes', true );
		$codes = is_array( $codes ) ? $codes : [];
		$codes = apply_filters( 'yoohw_support_user_access_codes', $codes, $user_id );
		$codes = is_array( $codes ) ? $codes : [];
		$codes = array_map( 'strval', $codes );

		return array_values( array_unique( array_filter( $codes ) ) );
	}

	public static function get_allowed_attachment_mimes() {
		return [
			'jpg|jpeg' => 'image/jpeg',
			'png'      => 'image/png',
			'webp'     => 'image/webp',
			'gif'      => 'image/gif',
			'mp4'      => 'video/mp4',
			'webm'     => 'video/webm',
			'mov|qt'   => 'video/quicktime',

			// ZIP archives
			'zip'      => 'application/zip',
		];
	}

	public static function validate_attachment_uploads( $field_name = 'yoohw_attachments' ) {
		// phpcs:disable WordPress.Security.NonceVerification.Missing,WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- The submission handler verifies the nonce; WordPress media APIs require the original upload array.
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

		if ( $uploaded_count > self::MAX_ATTACHMENTS ) {
				wp_die(
					esc_html(
						sprintf(
						/* translators: %d: Maximum number of attachments. */
							__( 'You can upload a maximum of %d attachments per topic.', 'yoohw-support-portal' ),
							self::MAX_ATTACHMENTS
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

	public static function handle_attachment_uploads( $parent_id, $field_name = 'yoohw_attachments' ) {
		// phpcs:disable WordPress.Security.NonceVerification.Missing,WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- The submission handler verifies the nonce; media_handle_sideload() requires the original upload fields.
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

	public static function render_post_attachments( int $post_id ): string {
		$attachment_ids = get_post_meta( $post_id, self::POST_META_ATTACHMENT_IDS, true );

		if ( ! is_array( $attachment_ids ) || empty( $attachment_ids ) ) {
			return '';
		}

		return self::render_attachments_html( $attachment_ids );
	}

	public static function append_post_attachments_to_content( $content ) {
		if ( ! is_singular( 'post' ) || ! in_the_loop() || ! is_main_query() ) {
			return $content;
		}

		$attachment_ids = get_post_meta( get_the_ID(), self::POST_META_ATTACHMENT_IDS, true );

		if ( ! is_array( $attachment_ids ) || empty( $attachment_ids ) ) {
			return $content;
		}

		return $content . self::render_attachments_html( $attachment_ids );
	}

	public static function render_form() {
		if ( class_exists( 'YoOhw_Support_Controller' ) ) {
			return YoOhw_Support_Controller::render_topic_form();
		}

		if ( ! is_user_logged_in() ) {
			return '<p>' . wp_kses_post(
				sprintf(
					/* translators: %s: Login page URL. */
					__( 'You need to <a href="%s">log in</a> to submit a topic.', 'yoohw-support-portal' ),
					esc_url( wp_login_url( get_permalink() ) )
				)
			) . '</p>';
		}

		$user_id = get_current_user_id();
		$user    = wp_get_current_user();

		$user_access_codes = self::get_user_access_codes( $user_id );

		$is_admin = current_user_can( YoOhw_Support_Capabilities::MANAGE_CATEGORIES );

		ob_start();
		?>
		<form id="yoohw_post_form" action="" method="post" enctype="multipart/form-data">
            <?php wp_nonce_field( 'yoohw_submit_post', 'yoohw_submit_post_nonce' ); ?>

			<div class="yoohw-form-row">
				<label for="yoohw_post_title" class="styled-label"><?php esc_html_e( 'Topic subject', 'yoohw-support-portal' ); ?></label>
				<div><input type="text" id="yoohw_post_title" name="yoohw_post_title" class="styled-text" required></div>
			</div>

			<div class="yoohw-form-row">
				<label for="yoohw_post_category" class="styled-label"><?php esc_html_e( 'Category', 'yoohw-support-portal' ); ?></label>
				<div class="styled-select">
					<select id="yoohw_post_category" name="yoohw_post_category" required>
						<option value=""><?php esc_html_e( 'Select category', 'yoohw-support-portal' ); ?></option>
						<?php
						$categories = get_terms( [
							'taxonomy'   => 'category',
							'hide_empty' => false,
							'parent'     => 0,
						] );

						if ( ! is_wp_error( $categories ) && ! empty( $categories ) ) {
							foreach ( $categories as $parent_category ) {

								if ( ! self::topic_category_selectable( (int) $parent_category->term_id, $user_id, $is_admin ) ) {
									continue;
								}

								$parent_allowed = $is_admin ? true : self::user_can_access_category_effective(
									$user_access_codes,
									(int) $parent_category->term_id,
									$user_id
								);

								if ( ! $parent_allowed ) {
									continue;
								}

								$child_categories = get_terms( [
									'taxonomy'   => 'category',
									'hide_empty' => false,
									'parent'     => $parent_category->term_id,
								] );

								// If parent has no subcategory, allow selecting the parent category.
								if ( is_wp_error( $child_categories ) || empty( $child_categories ) ) {
									echo '<option value="' . esc_attr( $parent_category->term_id ) . '">' . esc_html( $parent_category->name ) . '</option>';
									continue;
								}

								$selectable_children = [];
								foreach ( $child_categories as $child_category ) {
									if ( ! self::topic_category_selectable( (int) $child_category->term_id, $user_id, $is_admin ) ) {
										continue;
									}

									$child_allowed = $is_admin ? true : self::user_can_access_category_effective(
										$user_access_codes,
										(int) $child_category->term_id,
										$user_id
									);

									if ( ! $child_allowed ) {
										continue;
									}

									$selectable_children[] = $child_category;
								}

								if ( empty( $selectable_children ) ) {
									continue;
								}

								// If parent has selectable subcategories, keep parent as disabled group label.
								echo '<option value="" disabled>' . esc_html( $parent_category->name ) . '</option>';

								foreach ( $selectable_children as $child_category ) {
									echo '<option value="' . esc_attr( $child_category->term_id ) . '" data-parent="' . esc_attr( $parent_category->name ) . '">- ' . esc_html( $child_category->name ) . '</option>';
								}
							}
						}
						?>
					</select>
				</div>
			</div>

			<div class="yoohw-form-row">
				<label for="yoohw_post_content" class="styled-label"><?php esc_html_e( 'Content', 'yoohw-support-portal' ); ?></label>
				<?php
				// === this part is now aligned with your comment editor ===
				$content   = '';
				$editor_id = 'yoohw_post_content';

				$settings = [
					'textarea_name' => 'yoohw_post_content',
					'textarea_rows' => 10,
					'media_buttons' => false,
					'teeny'         => false,
					'quicktags'     => false,
					'tinymce'       => [
						'toolbar1' => 'bold,italic,underline,bullist,numlist,link,unlink,blockquote,yoohw_support_codeblock',
						'height'   => 220,
						'extended_valid_elements' => 'pre[class]',
						'content_style' => 'pre.wp-code-block{white-space:pre;tab-size:4;font-family:ui-monospace,SFMono-Regular,Menlo,Monaco,Consolas,monospace;font-size:13px;line-height:1.45;}',
						'setup'    => 'function (editor) {
							function inPre(){ return editor.dom.getParent(editor.selection.getStart(), "pre"); }
							function setActive(api){ function update(){ api.setActive(!!inPre()); } editor.on("NodeChange", update); return function(){ editor.off("NodeChange", update); }; }
							function htmlEscape(s){ s=(s||""); return s.replace(/&/g,"&amp;").replace(/</g,"&lt;").replace(/>/g,"&gt;"); }

							function togglePre(){
								editor.undoManager.transact(function(){
									var pre = inPre();
									if (pre) {
										// unwrap <pre> → <p>
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

								// inside <pre>: force plain escaped
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

							// double-Enter to exit <pre>
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

							// Toolbar button
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
				];

				wp_editor( $content, $editor_id, $settings );
				?>
			</div>

			<div class="yoohw-form-row yoohw-attachment-upload-field" data-upload-dropzone>
				<label for="yoohw_attachments" class="styled-label"><?php esc_html_e( 'Attachments', 'yoohw-support-portal' ); ?></label>
				<p><?php esc_html_e( 'Choose files below or drop them anywhere in this attachment area.', 'yoohw-support-portal' ); ?></p>
				<input
					type="file"
					id="yoohw_attachments"
					name="yoohw_attachments[]"
					multiple
					accept="image/jpeg,image/png,image/webp,image/gif,video/mp4,video/webm,video/quicktime,application/zip,.zip"
					data-max-files="<?php echo esc_attr( self::MAX_ATTACHMENTS ); ?>"
					data-max-size="<?php echo esc_attr( self::MAX_ATTACHMENT_BYTES ); ?>"
				>
				<p class="yoohw-upload-note">
					<?php
					echo esc_html(
						sprintf(
							/* translators: 1: Maximum attachment count, 2: Maximum size per attachment. */
							__( 'Attach up to %1$d files. Allowed file types: JPG, PNG, WebP, GIF, MP4, WebM, MOV, ZIP. Max %2$s per file.', 'yoohw-support-portal' ),
							self::MAX_ATTACHMENTS,
							size_format( self::MAX_ATTACHMENT_BYTES )
						)
					);
					?>
				</p>
			</div>

			<p>
				<input type="submit" value="<?php esc_attr_e( 'Submit', 'yoohw-support-portal' ); ?>">
			</p>
		</form>
		<?php
		return ob_get_clean();
	}
}
