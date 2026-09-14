<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class YoOhw_Latest_Comment_Bubble {

	public static function init() {
		add_action( 'wp_footer', [ __CLASS__, 'render_bubble' ] );
	}

	public static function render_bubble() {
		if ( ! is_singular( 'post' ) || ! comments_open() ) {
			return;
		}

		$post_id = get_queried_object_id();

		if ( ! $post_id ) {
			return;
		}

		$latest_comment = get_comments(
			[
				'post_id' => $post_id,
				'status'  => 'approve',
				'type'    => 'comment',
				'number'  => 1,
				'orderby' => 'comment_date_gmt',
				'order'   => 'DESC',
			]
		);

		if ( empty( $latest_comment ) ) {
			return;
		}

		$comment_id = (int) $latest_comment[0]->comment_ID;
		?>
		<a
			href="#comment-<?php echo esc_attr( $comment_id ); ?>"
			class="yoohw-latest-comment-bubble"
			aria-label="<?php esc_attr_e( 'Jump to latest comment', 'yoohw-support-portal' ); ?>"
			title="<?php esc_attr_e( 'Jump to latest comment', 'yoohw-support-portal' ); ?>"
		>
			<span class="yoohw-latest-comment-bubble-icon" aria-hidden="true"><?php if ( class_exists( 'YoOhw_Support_Icons' ) ) { YoOhw_Support_Icons::output( 'arrow-down' ); } ?></span>
			<span class="yoohw-latest-comment-bubble-text"><?php esc_html_e( 'Latest reply', 'yoohw-support-portal' ); ?></span>
		</a>
		<?php
	}
}

YoOhw_Latest_Comment_Bubble::init();
