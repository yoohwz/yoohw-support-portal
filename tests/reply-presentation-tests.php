<?php
/** Run with wp eval-file on a Local WordPress site; only synthetic records are written. */
if ( ! defined( 'WP_CLI' ) || ! WP_CLI || 'local' !== wp_get_environment_type() ) {
	throw new RuntimeException( 'Reply tests require WP-CLI and WP_ENVIRONMENT_TYPE=local.' );
}
require_once ABSPATH . 'wp-admin/includes/user.php';
add_filter( 'pre_wp_mail', '__return_true' );
$prefix = 'ysp15-replies-' . wp_generate_password( 8, false, false );
$users = [];
$post_id = 0;
$original_user = get_current_user_id();
$original_post = $GLOBALS['post'] ?? null;
$checks = 0;
$assert = static function ( $condition, $message ) use ( &$checks ) {
	if ( ! $condition ) {
		throw new RuntimeException( $message );
	}
	++$checks;
};
$avatar_filter = static function ( $args, $id ) {
	$args['url'] = includes_url( 'images/w-logo-blue.png' );
	$args['found_avatar'] = true;
	return $args;
};
$avatar_option = static function () { return 1; };
add_filter( 'pre_get_avatar_data', $avatar_filter, 10, 2 );
add_filter( 'pre_option_show_avatars', $avatar_option );
try {
	foreach ( [ 'customer' => 'contributor', 'support' => 'administrator', 'other-support' => 'administrator' ] as $key => $role ) {
		$id = wp_insert_user( [ 'user_login' => $prefix . '-' . $key, 'display_name' => $key, 'user_pass' => wp_generate_password(), 'user_email' => $prefix . '-' . $key . '@example.invalid', 'role' => $role ] );
		$assert( ! is_wp_error( $id ), 'Create synthetic user.' );
		$users[ $key ] = $id;
	}
	$post_id = wp_insert_post( [ 'post_type' => 'post', 'post_status' => 'publish', 'post_author' => $users['customer'], 'post_title' => $prefix, 'post_content' => 'Synthetic topic body.', 'comment_status' => 'closed' ], true );
	$assert( ! is_wp_error( $post_id ), 'Create synthetic topic.' );
	$comments = [];
	$replies = [];
	for ( $i = 0; $i < 12; ++$i ) {
		$key = array_keys( $users )[ $i % 3 ];
		$date = '2026-01-01 00:' . sprintf( '%02d', $i ) . ':00';
		$comment_id = wp_insert_comment( [ 'comment_post_ID' => $post_id, 'user_id' => $users[ $key ], 'comment_author' => $key, 'comment_content' => 'Synthetic reply ' . ( $i + 1 ), 'comment_date' => $date, 'comment_date_gmt' => $date, 'comment_approved' => 1 ] );
		$comments[] = get_comment( $comment_id );
		$replies[] = [ 'id' => $i + 1, 'author_id' => $users[ $key ], 'created_at' => $date, 'content' => 'Synthetic reply ' . ( $i + 1 ) ];
	}
	foreach ( $users as $viewer => $viewer_id ) {
		wp_set_current_user( $viewer_id );
		foreach ( [ 'topic-single', 'topic-single-isolated' ] as $template ) {
			$html = YoOhw_Support_Controller::render_template( $template, [
				'post' => get_post( $post_id ),
				'comments' => $comments,
				'topic' => [ 'id' => 0, 'status' => 'resolved', 'author_id' => $users['customer'], 'category_id' => 0, 'created_at' => '2026-01-01 00:00:00', 'title' => $prefix, 'content' => 'Synthetic topic body.' ],
				'replies' => $replies,
			] );
			$document = new DOMDocument();
			libxml_use_internal_errors( true );
			$document->loadHTML( '<?xml encoding="utf-8" ?>' . $html );
			$xpath = new DOMXPath( $document );
			$assert( 12 === $xpath->query( '//article[contains(concat(" ", @class, " "), " yoohw-reply ")]' )->length, "$template: history remains chronological and complete before JS enhancement." );
			$articles = $xpath->query( '//article[contains(concat(" ", @class, " "), " yoohw-reply ")]' );
			foreach ( $articles as $index => $article ) {
				$expected_author = $replies[ $index ]['author_id'];
				$assert( ( false !== strpos( $article->getAttribute( 'class' ), 'is-own' ) ) === ( $expected_author === $viewer_id ), "$template: reply ownership matches its author, not role." );
			}
			$assert( 4 === $xpath->query( '//article[contains(@class, "is-own")]' )->length, "$template: only viewer-owned replies align right ($viewer)." );
			$assert( 8 === $xpath->query( '//article[contains(@class, "is-support")]' )->length, "$template: support classification is independent of alignment." );
			$assert( 4 === $xpath->query( '//article[contains(@class, "is-customer")]' )->length, "$template: customer classification unchanged." );
			$assert( 12 === $xpath->query( '//img[contains(@class, "yoohw-reply-avatar")]' )->length, "$template: WordPress avatar filters respected." );
			$assert( false !== strpos( $html, 'aria-controls="yoohw-reply-list"' ) && false !== strpos( $html, 'replies are closed' ), "$template: history control and closed reply form present." );
		}
	}
	WP_CLI::log( "reply-presentation-ok: $checks checks (both templates, three viewer perspectives)" );
} finally {
	remove_filter( 'pre_get_avatar_data', $avatar_filter, 10 );
	remove_filter( 'pre_option_show_avatars', $avatar_option );
	wp_set_current_user( $original_user );
	if ( is_int( $post_id ) && $post_id > 0 ) {
		wp_delete_post( $post_id, true );
	}
	foreach ( $users as $id ) {
		wp_delete_user( $id );
	}
	$GLOBALS['post'] = $original_post;
}
