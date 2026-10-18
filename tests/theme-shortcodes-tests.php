<?php
/** Run on a disposable/local WordPress site: wp eval-file tests/theme-shortcodes-tests.php */
if ( ! defined( 'WP_CLI' ) || ! WP_CLI || 'local' !== wp_get_environment_type() ) {
	throw new RuntimeException( 'These synthetic-data tests require WP-CLI and WP_ENVIRONMENT_TYPE=local.' );
}

require_once ABSPATH . 'wp-admin/includes/user.php';
add_filter( 'pre_wp_mail', '__return_true' );
$prefix = 'ysp15-' . wp_generate_password( 10, false, false );
$users = [];
$posts = [];
$isolated = [];
$term_ids = [];
$checks = 0;
$original_get = $_GET;
$original_uri = $_SERVER['REQUEST_URI'] ?? '/';
$original_user = get_current_user_id();
$original_query = $GLOBALS['wp_query'];
$original_post = $GLOBALS['post'] ?? null;
$settings = YoOhw_Support_Settings::get();
$mode = 'wordpress';
$settings_filter = static function () use ( &$mode, $settings ) {
	return array_merge( $settings, [ 'dedicated_portal_mode' => $mode, 'enable_page_ui' => '0' ] );
};
add_filter( 'pre_option_' . YoOhw_Support_Settings::OPTION_NAME, $settings_filter );
$assert = static function ( $condition, $message ) use ( &$checks ) {
	if ( ! $condition ) {
		throw new RuntimeException( $message );
	}
	++$checks;
};
$render = static function ( $tag ) {
	$property = new ReflectionProperty( YoOhw_Support_Shortcodes::class, 'rendered' );
	$property->setAccessible( true );
	$property->setValue( null, [] );
	return do_shortcode( '[' . $tag . ']' );
};

try {
	foreach ( [ 'owner', 'other', 'staff' ] as $role ) {
		$id = wp_insert_user( [ 'user_login' => $prefix . '-' . $role, 'user_pass' => wp_generate_password(), 'user_email' => $prefix . '-' . $role . '@example.invalid', 'role' => 'subscriber' ] );
		$assert( ! is_wp_error( $id ), 'Create synthetic user.' );
		$users[ $role ] = $id;
	}
	$staff = get_user_by( 'id', $users['staff'] );
	$staff->add_cap( YoOhw_Support_Capabilities::VIEW_ALL_TOPICS );
	foreach ( [ 'a', 'b' ] as $name ) {
		$term = wp_insert_term( $prefix . '-' . $name, 'category' );
		$assert( ! is_wp_error( $term ), 'Create synthetic category.' );
		$term_ids[] = $term['term_id'];
	}
	$page = wp_insert_post( [ 'post_type' => 'page', 'post_status' => 'publish', 'post_title' => $prefix, 'post_content' => '[yoohw_support_topics]' ] );
	$posts[] = $page;
	$_SERVER['REQUEST_URI'] = wp_parse_url( get_permalink( $page ), PHP_URL_PATH );
	$GLOBALS['wp_query'] = new WP_Query( [ 'page_id' => $page ] );
	$GLOBALS['post'] = get_post( $page );

	foreach ( [ 'wordpress', 'isolated' ] as $mode ) {
		$assert( 'wordpress' === $mode || YoOhw_Support_Database::tables_exist(), 'Isolated tables must already exist.' );
		for ( $i = 0; $i < 11; ++$i ) {
			$author = $i < 10 ? $users['owner'] : $users['other'];
			$title = $prefix . '-' . ( $i < 10 ? 'owner' : 'other' ) . '-' . $i;
			$category = $term_ids[ $i % 2 ];
			if ( 'wordpress' === $mode ) {
				$id = wp_insert_post( [ 'post_title' => $title, 'post_content' => 'Synthetic support content.', 'post_author' => $author, 'post_status' => 'publish', 'post_category' => [ $category ] ], true );
				$assert( ! is_wp_error( $id ), 'Create synthetic native topic.' );
				$posts[] = $id;
			} else {
				$id = YoOhw_Support_Database::create_topic( [ 'title' => $title, 'content' => 'Synthetic support content.', 'author_id' => $author, 'category_id' => $category ] );
				$assert( ! is_wp_error( $id ), 'Create synthetic isolated topic.' );
				$isolated[] = $id;
			}
		}
		$_GET = [ 'yoohw_search' => $prefix ];
		wp_set_current_user( 0 );
		$html = $render( 'yoohw_support_topics' );
		$assert( false === strpos( $html, $prefix . '-owner' ) && false !== strpos( $html, 'Log in to view' ), "$mode: guest cannot read topics." );
		wp_set_current_user( $users['owner'] );
		$html = $render( 'yoohw_support_topics' );
		$assert( 8 === substr_count( $html, 'class="yoohw-topic-card"' ), "$mode: first page has eight topics." );
		$assert( false === strpos( $html, $prefix . '-other' ), "$mode: owner cannot read another customer's topic." );
		$assert( false !== strpos( $html, 'name="yoohw_search"' ) && false === strpos( $html, 'name="s"' ), "$mode: search does not use WordPress s." );
		$assert( false !== strpos( html_entity_decode( $html ), 'yoohw_search=' . $prefix ), "$mode: pagination retains search." );
		$assert( false !== strpos( $html, 'action="' . esc_url( get_permalink( $page ) ) . '"' ), "$mode: filter stays on containing page." );
		$_GET['yoohw_page'] = 2;
		$html = $render( 'yoohw_support_topics' );
		$assert( 2 === substr_count( $html, 'class="yoohw-topic-card"' ), "$mode: second page has remaining topics." );
		$_GET = [ 'yoohw_search' => $prefix, 'yoohw_category' => $term_ids[0] ];
		$html = $render( 'yoohw_support_topics' );
		$assert( 5 === substr_count( $html, 'class="yoohw-topic-card"' ), "$mode: category filter applies." );
		$_GET = [ 'yoohw_search' => $prefix, 'yoohw_status' => 'wordpress' === $mode ? YoOhw_Post_Status_Resolved::STATUS : 'resolved' ];
		$html = $render( 'yoohw_support_topics' );
		$assert( 0 === substr_count( $html, 'class="yoohw-topic-card"' ), "$mode: resolved filter excludes open topics." );
		$_GET = [ 'yoohw_search' => $prefix ];
		wp_set_current_user( $users['other'] );
		$html = $render( 'yoohw_support_topics' );
		$assert( false === strpos( $html, $prefix . '-owner' ) && false !== strpos( $html, $prefix . '-other' ), "$mode: unrelated customer sees only own topic." );
		wp_set_current_user( $users['staff'] );
		$html = $render( 'yoohw_support_topics' );
		$_GET['yoohw_page'] = 2;
		$staff_next = $render( 'yoohw_support_topics' );
		$assert( 8 === substr_count( $html, 'class="yoohw-topic-card"' ) && 3 === substr_count( $staff_next, 'class="yoohw-topic-card"' ) && false !== strpos( $html . $staff_next, $prefix . '-other' ), "$mode: capability grants staff visibility." );
		wp_set_current_user( $users['owner'] );
		$html = $render( 'yoohw_post_form' );
		$assert( false !== strpos( $html, 'yoohw_submit_post_nonce' ) && false !== strpos( $html, 'yoohw_post_content' ), "$mode: compose includes nonce and editor." );
		$assert( ( 'wordpress' === $mode ) === ( false !== strpos( $html, 'name="yoohw_attachments[]"' ) ), "$mode: attachment availability unchanged." );
		wp_set_current_user( 0 );
		foreach ( [ 'yoohw_login_form' => 'yoohw_support_login_nonce', 'yoohw_lost_password_form' => 'yoohw_support_lost_password_nonce' ] as $tag => $nonce ) {
			$html = $render( $tag );
			$assert( false !== strpos( $html, $nonce ) && false !== strpos( $html, 'yoohw-support-embed' ), "$mode: $tag retains handler nonce and wrapper." );
		}
		$assert( '' === do_shortcode( '[yoohw_lost_password_form]' ), "$mode: repeated fixed IDs omitted." );
		$assert( defined( 'DONOTCACHEPAGE' ) && DONOTCACHEPAGE, "$mode: rendering disables page cache." );
	}

	$mode = 'isolated';
	wp_set_current_user( $users['owner'] );
	$_GET = [ 'yoohw_search' => $prefix ];
	$plain_permalink = static function () { return ''; };
	add_filter( 'pre_option_permalink_structure', $plain_permalink );
	$original_rewrite = clone $GLOBALS['wp_rewrite'];
	$GLOBALS['wp_rewrite']->permalink_structure = '';
	$GLOBALS['wp_rewrite']->page_structure = '';
	try {
		$_SERVER['REQUEST_URI'] = '/?page_id=' . $page;
		$assert( ! YoOhw_Support_Router::is_plugin_ui_request(), 'Isolated plain permalink page remains theme-hosted.' );
		$html = $render( 'yoohw_support_topics' );
		$assert( false !== strpos( $html, 'name="page_id" value="' . $page . '"' ), 'Plain permalink form retains page_id.' );
		$assert( false !== strpos( html_entity_decode( $html ), 'page_id=' . $page ), 'Plain permalink pagination retains page_id.' );
	} finally {
		remove_filter( 'pre_option_permalink_structure', $plain_permalink );
		$GLOBALS['wp_rewrite'] = $original_rewrite;
		$_SERVER['REQUEST_URI'] = wp_parse_url( get_permalink( $page ), PHP_URL_PATH );
	}
	wp_enqueue_style( 'ysp15-synthetic-theme', get_stylesheet_directory_uri() . '/ysp15-synthetic.css' );
	YoOhw_Support_Assets::isolate_support_assets();
	$assert( wp_style_is( 'ysp15-synthetic-theme', 'enqueued' ), 'Theme assets stay active on embedded pages.' );
	wp_dequeue_style( 'ysp15-synthetic-theme' );
	$GLOBALS['wp_query']->is_feed = true;
	$assert( '' === $render( 'yoohw_support_topics' ), 'Feeds omit private embedded components.' );
	$GLOBALS['wp_query']->is_feed = false;
	$assert( wp_style_is( 'yoohw-support-portal-ui', 'enqueued' ) && wp_script_is( 'yoohw-support-portal-ui', 'enqueued' ), 'Shortcodes enqueue UI assets.' );
	WP_CLI::log( "theme-shortcodes-ok: $checks checks (both storage modes)" );
} finally {
	wp_set_current_user( $original_user );
	remove_filter( 'pre_option_' . YoOhw_Support_Settings::OPTION_NAME, $settings_filter );
	global $wpdb;
	foreach ( $isolated as $id ) {
		$wpdb->delete( YoOhw_Support_Database::topics_table(), [ 'id' => $id ], [ '%d' ] );
	}
	foreach ( array_reverse( $posts ) as $id ) {
		wp_delete_post( $id, true );
	}
	foreach ( $term_ids as $id ) {
		wp_delete_term( $id, 'category' );
	}
	foreach ( $users as $id ) {
		wp_delete_user( $id );
	}
	$_GET = $original_get;
	$_SERVER['REQUEST_URI'] = $original_uri;
	$GLOBALS['wp_query'] = $original_query;
	$GLOBALS['post'] = $original_post;
}
