<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_action( 'init', [ 'YoOhw_Attachment_Cleanup', 'init' ] );

class YoOhw_Attachment_Cleanup {

	const POST_META_ATTACHMENT_IDS    = '_yoohw_attachment_ids';
	const COMMENT_META_ATTACHMENT_IDS = '_yoohw_attachment_ids';

	public static function init() {
		add_action( 'before_delete_post', [ __CLASS__, 'delete_post_and_comment_attachments' ], 10, 2 );
		add_action( 'delete_comment', [ __CLASS__, 'delete_comment_attachments' ], 10, 2 );
	}

	public static function delete_post_and_comment_attachments( $post_id, $post ) {
		$post_id = absint( $post_id );

		if ( ! $post_id ) {
			return;
		}

		self::delete_post_attachments( $post_id );
		self::delete_all_comment_attachments_for_post( $post_id );
	}

	public static function delete_post_attachments( $post_id ) {
		$attachment_ids = get_post_meta( $post_id, self::POST_META_ATTACHMENT_IDS, true );

		self::delete_attachment_ids( $attachment_ids );

		delete_post_meta( $post_id, self::POST_META_ATTACHMENT_IDS );
	}

	public static function delete_all_comment_attachments_for_post( $post_id ) {
		$comments = get_comments(
			[
				'post_id' => absint( $post_id ),
				'status'  => 'all',
				'type'    => 'comment',
				'fields'  => 'ids',
				'number'  => 0,
			]
		);

		if ( empty( $comments ) || ! is_array( $comments ) ) {
			return;
		}

		foreach ( $comments as $comment_id ) {
			self::delete_comment_attachments( $comment_id );
		}
	}

	public static function delete_comment_attachments( $comment_id, $comment = null ) {
		$comment_id = absint( $comment_id );

		if ( ! $comment_id ) {
			return;
		}

		$attachment_ids = get_comment_meta( $comment_id, self::COMMENT_META_ATTACHMENT_IDS, true );

		self::delete_attachment_ids( $attachment_ids );

		delete_comment_meta( $comment_id, self::COMMENT_META_ATTACHMENT_IDS );
	}

	private static function delete_attachment_ids( $attachment_ids ) {
		if ( empty( $attachment_ids ) || ! is_array( $attachment_ids ) ) {
			return;
		}

		$attachment_ids = array_values(
			array_unique(
				array_filter(
					array_map( 'absint', $attachment_ids )
				)
			)
		);

		foreach ( $attachment_ids as $attachment_id ) {
			if ( ! $attachment_id ) {
				continue;
			}

			if ( 'attachment' !== get_post_type( $attachment_id ) ) {
				continue;
			}

			wp_delete_attachment( $attachment_id, true );
		}
	}
}