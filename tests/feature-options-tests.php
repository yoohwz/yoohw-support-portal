<?php
/** Run: wp eval-file tests/feature-options-tests.php (Local only; synthetic data, no settings writes). */
if ( ! defined( 'WP_CLI' ) || ! WP_CLI || 'local' !== wp_get_environment_type() ) {
	throw new RuntimeException( 'Feature tests require WP-CLI and WP_ENVIRONMENT_TYPE=local.' );
}
require_once ABSPATH . 'wp-admin/includes/user.php';
add_filter( 'pre_wp_mail', '__return_true' );
$base = YoOhw_Support_Settings::defaults();
$settings = $base;
$prefix = 'ysp15-options-' . wp_generate_password( 8, false, false );
$users = $posts = $temporary = $isolated_ids = [];
$original_query = $GLOBALS['wp_query'];
$attachment = $category = 0;
$original_user = get_current_user_id();
$original_post = $GLOBALS['post'] ?? null;
$original_files = $_FILES;
$original_request = $_POST;
$original_get = $_GET;
$original_method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$original_uri = $_SERVER['REQUEST_URI'] ?? '/';
$checks = 0;
$settings_filter = static function () use ( &$settings ) { return $settings; };
add_filter( 'pre_option_' . YoOhw_Support_Settings::OPTION_NAME, $settings_filter );
$die_filter = static function () {
	return static function ( $message, $title = '', $args = [] ) {
		throw new RuntimeException( wp_strip_all_tags( (string) $message ), (int) ( $args['response'] ?? 500 ) );
	};
};
add_filter( 'wp_die_handler', $die_filter, 999 );
$assert = static function ( $condition, $message ) use ( &$checks ) {
	if ( ! $condition ) { throw new RuntimeException( $message ); }
	++$checks;
};
$expect_die = static function ( $callback, $text ) use ( $assert ) {
	$caught = null;
	try { $callback(); } catch ( RuntimeException $error ) { $caught = $error; }
	$assert( $caught && false !== strpos( $caught->getMessage(), $text ), 'Expected server rejection: ' . $text );
};
$upload = static function ( $path, $name = 'sample.png' ) {
	return [ 'yoohw_attachments' => [ 'name' => [ $name ], 'type' => [ 'image/png' ], 'tmp_name' => [ $path ], 'error' => [ UPLOAD_ERR_OK ], 'size' => [ filesize( $path ) ] ] ];
};
try {
	$assert( YoOhw_Support_Settings::uploads_enabled( 'topic' ) && YoOhw_Support_Settings::uploads_enabled( 'reply' ), 'Existing upload defaults stay enabled.' );
	$assert( 5 === YoOhw_Support_Settings::attachment_max_count() && min( 5 * MB_IN_BYTES, wp_max_upload_size() ) === YoOhw_Support_Settings::attachment_max_bytes(), 'Default limits stay five files / five MB on this host.' );
	$assert( 8 === YoOhw_Support_Settings::topics_per_page() && 5 === YoOhw_Support_Settings::reply_batch_size() && YoOhw_Support_Settings::feature_enabled( 'collapse_older_replies' ), 'List/conversation defaults unchanged.' );
	$clean = YoOhw_Support_Settings::sanitize( array_merge( $base, [ 'attachment_max_count' => 9999, 'attachment_max_size_mb' => -1, 'reply_batch_size' => [], 'topics_per_page' => 9999, 'attachment_types' => [ 'images', 'php', [], 'images' ], 'attachment_display' => 'garbage', 'reply_avatar_mode' => 'garbage', 'allow_reply_attachments' => '0' ] ) );
	$assert( 20 === $clean['attachment_max_count'] && 1 === $clean['attachment_max_size_mb'] && 5 === $clean['reply_batch_size'] && 100 === $clean['topics_per_page'], 'Numeric settings bounded and malformed values default safely.' );
	$assert( [ 'images' ] === $clean['attachment_types'] && 'preview' === $clean['attachment_display'] && 'wordpress' === $clean['reply_avatar_mode'] && '0' === $clean['allow_reply_attachments'], 'Selections are allowlisted and unchecked booleans persist.' );
	$settings['attachment_types'] = [ 'zip' ];
	$clean = YoOhw_Support_Settings::sanitize( [ 'dedicated_portal_mode' => 'isolated' ] );
	$assert( [ 'zip' ] === $clean['attachment_types'] && '1' === $clean['allow_topic_attachments'], 'Omitted disabled isolated fields retain native preferences.' );
	$settings = $base;
	$server_cap = static function () { return 512 * 1024; };
	add_filter( 'upload_size_limit', $server_cap );
	$assert( 512 * 1024 === YoOhw_Support_Settings::attachment_max_bytes(), 'Effective limit respects a server cap below one MB.' );
	remove_filter( 'upload_size_limit', $server_cap );

	$png = wp_tempnam( $prefix . '.png' ); $temporary[] = $png;
	file_put_contents( $png, base64_decode( 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+aDDsAAAAASUVORK5CYII=' ) );
	$_FILES = $upload( $png );
	YoOhw_Shortcode::validate_attachment_uploads();
	YoOhw_Comment_Editor::validate_comment_attachments( [] );
	$assert( true, 'Valid image accepted by both topic and reply validators.' );
	$settings['allow_topic_attachments'] = '0';
	$expect_die( static function () { YoOhw_Shortcode::validate_attachment_uploads(); }, 'disabled' );
	$expect_die( static function () { YoOhw_Shortcode::handle_attachment_uploads( 0 ); }, 'disabled' );
	YoOhw_Comment_Editor::validate_comment_attachments( [] );
	$assert( true, 'Topic disable does not disable reply uploads.' );
	$settings = $base; $settings['allow_reply_attachments'] = '0';
	$expect_die( static function () { YoOhw_Comment_Editor::validate_comment_attachments( [] ); }, 'disabled' );
	YoOhw_Shortcode::validate_attachment_uploads();
	$assert( true, 'Reply disable does not disable topic uploads.' );
	$settings = $base; $settings['attachment_max_count'] = 1;
	foreach ( $_FILES['yoohw_attachments'] as &$values ) { $values[] = $values[0]; } unset( $values );
	$expect_die( static function () { YoOhw_Shortcode::validate_attachment_uploads(); }, 'maximum of 1' );
	$expect_die( static function () { YoOhw_Comment_Editor::validate_comment_attachments( [] ); }, 'maximum of 1' );
	$settings = $base; $_FILES = $upload( $png ); $settings['attachment_types'] = [ 'zip' ];
	$expect_die( static function () { YoOhw_Shortcode::validate_attachment_uploads(); }, 'Unsupported attachment type' );
	$expect_die( static function () { YoOhw_Comment_Editor::validate_comment_attachments( [] ); }, 'Unsupported attachment type' );
	$zip = wp_tempnam( $prefix . '.zip' ); $temporary[] = $zip;
	file_put_contents( $zip, "PK\005\006" . str_repeat( "\0", 18 ) );
	$_FILES = $upload( $zip, 'sample.zip' );
	YoOhw_Shortcode::validate_attachment_uploads();
	$assert( [ 'zip' => 'application/zip' ] === YoOhw_Support_Settings::attachment_mimes(), 'ZIP-only policy accepts ZIP and contains no other MIME groups.' );
	$settings['attachment_types'] = [ 'images' ];
	$expect_die( static function () { YoOhw_Comment_Editor::validate_comment_attachments( [] ); }, 'Unsupported' );
	$_FILES = $upload( $png );
	$settings['attachment_types'] = [];
	$assert( ! YoOhw_Support_Settings::uploads_enabled( 'topic' ), 'No groups means no new uploads.' );
	$expect_die( static function () { YoOhw_Shortcode::validate_attachment_uploads(); }, 'disabled' );
	$large = wp_tempnam( $prefix ); $temporary[] = $large;
	$handle = fopen( $large, 'w' ); ftruncate( $handle, 2 * MB_IN_BYTES ); fclose( $handle );
	$settings = $base; $settings['attachment_max_size_mb'] = 1; $_FILES = $upload( $large );
	$_FILES['yoohw_attachments']['size'][0] = 1;
	$expect_die( static function () { YoOhw_Shortcode::validate_attachment_uploads(); }, 'too large' );
	$expect_die( static function () { YoOhw_Comment_Editor::validate_comment_attachments( [] ); }, 'too large' );
	$settings = $base; $_FILES = $upload( $png, 'script.php' );
	$expect_die( static function () { YoOhw_Shortcode::validate_attachment_uploads(); }, 'Unsupported' );
	$_FILES = $upload( $png ); $_FILES['yoohw_attachments']['name'] = [ 4 => 'sample.png' ];
	$expect_die( static function () { YoOhw_Shortcode::validate_attachment_uploads(); }, 'Invalid attachment' );
	$_FILES = $upload( $png ); $_FILES['yoohw_attachments']['error'][0] = UPLOAD_ERR_INI_SIZE;
	$expect_die( static function () { YoOhw_Shortcode::validate_attachment_uploads(); }, 'could not be uploaded' );
	$_FILES = [];

	foreach ( [ 'owner' => 'contributor', 'other' => 'contributor', 'staff' => 'administrator' ] as $name => $role ) {
		$id = wp_insert_user( [ 'user_login' => $prefix . '-' . $name, 'user_email' => $prefix . '-' . $name . '@example.invalid', 'user_pass' => wp_generate_password(), 'role' => $role ] );
		$assert( ! is_wp_error( $id ), 'Create synthetic user.' ); $users[ $name ] = $id;
	}
	$term = wp_insert_term( $prefix, 'category' ); $assert( ! is_wp_error( $term ), 'Create public test category.' ); $category = $term['term_id'];
	for ( $i = 0; $i < 4; ++$i ) {
		$posts[] = wp_insert_post( [ 'post_title' => $prefix . '-' . $i, 'post_content' => 'Synthetic options topic.', 'post_author' => $users['owner'], 'post_status' => 'publish', 'comment_status' => 'open', 'post_category' => [ $category ] ] );
	}
	$post_id = $posts[0]; wp_set_current_user( $users['owner'] ); $settings = $base;
	$settings['allow_topic_attachments'] = '0';
	$html = YoOhw_Support_Controller::render_topic_form();
	$assert( false === strpos( $html, 'type="file"' ), 'Portal compose hides disabled upload UI.' );
	$html = do_shortcode( '[yoohw_post_form]' );
	$assert( false === strpos( $html, 'type="file"' ), 'Embedded compose hides disabled upload UI.' );
	$_POST = [ 'yoohw_post_title' => $prefix . '-rejected', 'yoohw_post_category' => $category, 'yoohw_post_content' => 'Rejected forged upload.', 'yoohw_submit_post_nonce' => wp_create_nonce( 'yoohw_submit_post' ) ];
	$_FILES = $upload( $png );
	$expect_die( static function () { YoOhw_Form_Handler::handle_post_submission(); }, 'disabled' );
	$assert( ! get_posts( [ 'title' => $prefix . '-rejected', 'post_status' => 'any', 'author' => $users['owner'] ] ), 'Disabled upload rejected before topic persistence.' );
	$_POST = [ 'yoohw_support_comment_nonce' => wp_create_nonce( 'yoohw_support_comment' ) ]; $_SERVER['REQUEST_METHOD'] = 'POST';
	$settings['allow_reply_attachments'] = '0';
	$expect_die( static function () use ( $post_id ) { apply_filters( 'preprocess_comment', [ 'comment_post_ID' => $post_id, 'comment_content' => 'Rejected upload', 'user_id' => get_current_user_id() ] ); }, 'disabled' );
	$_FILES = [];
	foreach ( [ 0, $users['other'] ] as $user_id ) {
		wp_set_current_user( $user_id ); $_POST['yoohw_support_comment_nonce'] = wp_create_nonce( 'yoohw_support_comment' );
		$expect_die( static function () use ( $post_id ) { YoOhw_Comment_Editor::authorize_support_comment( [ 'comment_post_ID' => $post_id ] ); }, $user_id ? 'permission' : 'signed in' );
	}
	wp_set_current_user( $users['owner'] ); $GLOBALS['post'] = get_post( $post_id );
	$settings = $base; $settings['allow_reply_attachments'] = '0';
	ob_start(); comment_form( [], $post_id ); $html = ob_get_clean();
	$assert( false === strpos( $html, 'type="file"' ), 'Reply form hides disabled upload input.' );
	$settings = $base; $settings['attachment_types'] = [ 'images' ]; $settings['attachment_max_count'] = 2; $settings['attachment_max_size_mb'] = 1;
	$html = YoOhw_Support_Controller::render_topic_form();
	$assert( false !== strpos( $html, 'data-max-files="2"' ) && false !== strpos( $html, 'data-max-size="1048576"' ) && false === strpos( $html, 'application/zip' ), 'Form metadata and picker match effective policy.' );

	$attachment = wp_insert_attachment( [ 'post_title' => $prefix . '-image', 'post_parent' => $post_id, 'post_mime_type' => 'image/png', 'post_status' => 'inherit' ], $png, $post_id );
	YoOhw_Protected_Attachments::protect( $attachment );
	update_post_meta( $post_id, YoOhw_Shortcode::POST_META_ATTACHMENT_IDS, [ $attachment ] );
	$comment_id = wp_insert_comment( [ 'comment_post_ID' => $post_id, 'user_id' => $users['owner'], 'comment_content' => 'Synthetic reply.', 'comment_approved' => 1 ] );
	update_comment_meta( $comment_id, YoOhw_Comment_Editor::COMMENT_META_ATTACHMENT_IDS, [ $attachment ] );
	$settings['allow_topic_attachments'] = $settings['allow_reply_attachments'] = '0';
	foreach ( [ 'preview', 'links' ] as $display ) {
		$settings['attachment_display'] = $display;
		$topic_html = YoOhw_Shortcode::render_post_attachments( $post_id );
		$reply_html = YoOhw_Comment_Editor::append_comment_attachments( '', get_comment( $comment_id ) );
		foreach ( [ $topic_html, $reply_html ] as $html ) {
			$assert( ( 'preview' === $display ) === ( false !== strpos( $html, '<img' ) ), 'Preview/link policy applies to old topic and reply files.' );
			$assert( false !== strpos( $html, 'admin-post.php?action=' . YoOhw_Protected_Attachments::ACTION ), 'Existing file links retain protected delivery after uploads disabled.' );
		}
	}
	$acl = new ReflectionMethod( YoOhw_Protected_Attachments::class, 'current_user_can_access_topic' ); $acl->setAccessible( true );
	foreach ( [ 'owner' => true, 'other' => false, 'staff' => true ] as $name => $allowed ) {
		wp_set_current_user( $users[ $name ] );
		$assert( $allowed === $acl->invoke( null, $post_id ), 'File access remains based on topic authority: ' . $name );
	}
	wp_set_current_user( 0 ); $assert( ! $acl->invoke( null, $post_id ), 'Anonymous file access remains denied.' );

	wp_set_current_user( $users['owner'] ); $settings = $base; $settings['reply_avatar_mode'] = 'initials';
	$html = YoOhw_Support_Controller::render_template( 'topic-single', [ 'post' => get_post( $post_id ), 'comments' => [ get_comment( $comment_id ) ] ] );
	$assert( false === strpos( $html, 'class="avatar ' ) && false !== strpos( $html, 'yoohw-avatar-initial' ), 'Initials-only mode does not request WordPress avatars.' );
	$settings['topics_per_page'] = 2; $_GET = [ 's' => $prefix ];
	$html = YoOhw_Support_Controller::render_topics();
	$assert( 2 === substr_count( $html, 'class="yoohw-topic-card"' ), 'Configured page size applies to native topic list.' );
	$settings['dedicated_portal_mode'] = 'isolated'; $_FILES = $upload( $png );
	$assert( ! YoOhw_Support_Settings::uploads_enabled( 'topic' ) && ! YoOhw_Support_Settings::uploads_enabled( 'reply' ), 'Isolated mode cannot enable attachments.' );
	$expect_die( static function () { YoOhw_Shortcode::validate_attachment_uploads(); }, 'disabled' );
	$expect_die( static function () { YoOhw_Shortcode::validate_attachment_uploads( 'yoohw_attachments', 'reply' ); }, 'disabled' );
	for ( $i = 0; $i < 4; ++$i ) {
		$id = YoOhw_Support_Database::create_topic( [ 'title' => $prefix . '-' . $i, 'content' => 'Synthetic isolated options topic.', 'author_id' => $users['owner'], 'category_id' => $category ] );
		$assert( ! is_wp_error( $id ), 'Create isolated test topic.' ); $isolated_ids[] = $id;
	}
	$settings['topics_per_page'] = 2; $_GET = [ 'yoohw_search' => $prefix ];
	$page_id = wp_insert_post( [ 'post_type' => 'page', 'post_title' => $prefix . '-page', 'post_status' => 'publish' ] ); $posts[] = $page_id;
	$GLOBALS['wp_query'] = new WP_Query( [ 'page_id' => $page_id ] );
	$_SERVER['REQUEST_URI'] = wp_parse_url( get_permalink( $page_id ), PHP_URL_PATH );
	$html = YoOhw_Support_Controller::render_topics( true );
	$assert( 2 === substr_count( $html, 'class="yoohw-topic-card"' ), 'Configured page size applies to isolated embedded topic list.' );
	$_GET = [];
	wp_set_current_user( $users['staff'] );
	$GLOBALS['wp_query']->queried_object = get_user_by( 'id', $users['owner'] );
	$GLOBALS['wp_query']->queried_object_id = $users['owner'];
	set_query_var( YoOhw_Support_Router::QUERY_VAR_CUSTOMER, $users['owner'] );
	$html = YoOhw_Support_Controller::render_isolated_customer_profile();
	$assert( 2 === substr_count( $html, 'class="yoohw-topic-card"' ), 'Configured page size applies to isolated customer profile.' );
	$settings['dedicated_portal_mode'] = 'wordpress';
	$html = YoOhw_Support_Controller::render_author_profile();
	$assert( 2 === substr_count( $html, 'class="yoohw-topic-card"' ), 'Configured page size applies to native author profile.' );
	$settings['dedicated_portal_mode'] = 'isolated';
	wp_set_current_user( $users['owner'] );
	$_FILES = $upload( $png );
	$_POST = [ 'yoohw_isolated_reply' => '1', 'yoohw_isolated_reply_nonce' => wp_create_nonce( 'yoohw_isolated_reply' ), 'topic_id' => $isolated_ids[0], 'comment' => 'Rejected isolated file upload.' ];
	$expect_die( static function () { YoOhw_Support_Controller::handle_isolated_reply_submission(); }, 'disabled' );
	$assert( 0 === YoOhw_Support_Database::count_replies( $isolated_ids[0] ), 'Isolated handler rejects files before reply persistence.' );
	wp_set_current_user( $users['other'] );
	$_POST['yoohw_isolated_reply_nonce'] = wp_create_nonce( 'yoohw_isolated_reply' );
	$expect_die( static function () { YoOhw_Support_Controller::handle_isolated_reply_submission(); }, 'not available' );
	wp_set_current_user( $users['owner'] );
	$settings['reply_avatar_mode'] = 'initials';
	$html = YoOhw_Support_Controller::render_template( 'topic-single-isolated', [ 'topic' => YoOhw_Support_Database::get_topic_by_id( $isolated_ids[0] ), 'replies' => [ [ 'id' => 0, 'author_id' => $users['owner'], 'created_at' => current_time( 'mysql' ), 'content' => 'Synthetic avatar test.' ] ] ] );
	$assert( false === strpos( $html, 'yoohw-reply-avatar' ) && false !== strpos( $html, 'yoohw-avatar-initial' ), 'Isolated replies respect initials-only mode.' );
	ob_start(); YoOhw_Support_Settings::render_attachment_options(); $html = ob_get_clean();
	$assert( false !== strpos( $html, 'disabled=' ), 'Isolated settings clearly disable unsupported attachment controls.' );
	$_FILES = []; $settings['collapse_older_replies'] = '0'; $settings['reply_batch_size'] = 3;
	YoOhw_Support_Assets::enqueue_support_assets();
	$data = wp_scripts()->get_data( 'yoohw-support-portal-ui', 'data' );
	$assert( false !== strpos( $data, '"collapseReplies":false' ) && false !== strpos( $data, '"replyBatchSize":3' ), 'Client receives collapse and batch configuration.' );
	WP_CLI::log( "feature-options-ok: $checks checks" );
} finally {
	$_FILES = []; $_POST = [];
	wp_set_current_user( $original_user );
	if ( $attachment ) { wp_delete_attachment( $attachment, true ); }
	global $wpdb;
	foreach ( $isolated_ids as $id ) { $wpdb->delete( YoOhw_Support_Database::topics_table(), [ 'id' => $id ], [ '%d' ] ); }
	foreach ( $posts as $id ) { wp_delete_post( $id, true ); }
	if ( $category ) { wp_delete_term( $category, 'category' ); }
	foreach ( $users as $id ) { wp_delete_user( $id ); }
	foreach ( $temporary as $file ) { if ( is_file( $file ) ) { unlink( $file ); } }
	remove_filter( 'pre_option_' . YoOhw_Support_Settings::OPTION_NAME, $settings_filter );
	remove_filter( 'wp_die_handler', $die_filter, 999 );
	$_FILES = $original_files; $_POST = $original_request; $_GET = $original_get;
	$_SERVER['REQUEST_METHOD'] = $original_method; $_SERVER['REQUEST_URI'] = $original_uri;
	$GLOBALS['post'] = $original_post;
	$GLOBALS['wp_query'] = $original_query;
}
