<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- Variables are intentionally scoped to this rendered template.

setup_postdata( $post );
$is_resolved = YoOhw_Support_Controller::topic_status_class( $post ) === 'is-resolved';
$topic_author = get_userdata( (int) $post->post_author );
$topic_author_name = $topic_author ? $topic_author->display_name : __( 'Unknown user', 'yoohw-support-portal' );
$topic_author_account = $topic_author ? $topic_author->user_login : '';
// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Applies the canonical WordPress author display filter.
$topic_author_html = apply_filters( 'the_author', $topic_author_name );
$topic_author_text = wp_strip_all_tags( $topic_author_html );
$topic_author_link = ( $topic_author && current_user_can( YoOhw_Support_Capabilities::VIEW_CUSTOMERS ) )
	? get_author_posts_url( $topic_author->ID, $topic_author->user_nicename )
	: '';
$allowed_author_html = [
	'span' => [
		'class' => true,
	],
];
$reply_side_labels = YoOhw_Support_Settings::reply_side_labels();
?>

<article class="yoohw-panel yoohw-topic-detail">
	<header class="yoohw-topic-detail-header">
		<div class="yoohw-topic-meta-row">
			<span class="yoohw-status-pill <?php echo esc_attr( YoOhw_Support_Controller::topic_status_class( $post ) ); ?>">
				<?php YoOhw_Support_Icons::output( $is_resolved ? 'circle-check' : 'message-circle' ); ?>
				<?php echo esc_html( YoOhw_Support_Controller::topic_status_label( $post ) ); ?>
			</span>
			<?php YoOhw_Support_Controller::output_topic_category_meta( $post ); ?>
			<span class="yoohw-topic-meta"><?php YoOhw_Support_Icons::output( 'calendar' ); ?> <?php echo esc_html( get_the_date( '', $post ) ); ?></span>

			<div class="yoohw-topic-author">
				<?php YoOhw_Support_Icons::output( 'user' ); ?>
				<span class="yoohw-topic-author-name">
					<?php
					if ( $topic_author_link ) {
						echo '<a class="yoohw-user-link" href="' . esc_url( $topic_author_link ) . '">';
					}

					echo wp_kses( $topic_author_html, $allowed_author_html );

					if ( $topic_author_link ) {
						echo '</a>';
					}
					?>
				</span>
				<?php if ( '' !== $topic_author_account && $topic_author_account !== $topic_author_text ) : ?>
					<span class="yoohw-topic-author-account">@<?php echo esc_html( $topic_author_account ); ?></span>
				<?php endif; ?>
			</div>
		</div>

		<h2><?php echo esc_html( $post->post_title ); ?></h2>
	</header>

	<div class="yoohw-topic-body">
		<?php echo apply_filters( 'the_content', $post->post_content ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped,WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Core content filter produces display HTML. ?>
		<?php
		if ( method_exists( 'YoOhw_Shortcode', 'render_post_attachments' ) ) {
			echo YoOhw_Shortcode::render_post_attachments( $post->ID ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		}
		?>
	</div>
</article>

<section class="yoohw-replies" aria-label="<?php esc_attr_e( 'Replies', 'yoohw-support-portal' ); ?>">
	<div class="yoohw-conversation-heading">
		<div>
			<p class="yoohw-kicker"><?php esc_html_e( 'Conversation', 'yoohw-support-portal' ); ?></p>
			<h2>
				<?php
				printf(
					/* translators: %d: Number of replies. */
					esc_html( _n( '%d reply', '%d replies', count( $comments ), 'yoohw-support-portal' ) ),
					absint( count( $comments ) )
				);
				?>
			</h2>
		</div>
		<a class="yoohw-button yoohw-button-secondary" href="<?php echo esc_url( YoOhw_Support_Router::url( 'topics' ) ); ?>">
			<?php YoOhw_Support_Icons::output( 'chevron-left' ); ?>
			<?php esc_html_e( 'All topics', 'yoohw-support-portal' ); ?>
		</a>
	</div>

	<?php if ( ! empty( $comments ) ) : ?>
		<div class="yoohw-reply-list">
			<?php foreach ( $comments as $comment ) : ?>
				<?php
				$author_html = get_comment_author( $comment );
				$author_text = wp_strip_all_tags( $author_html );
				$reply_author_id = (int) $comment->user_id;
				$reply_author = $reply_author_id > 0 ? get_userdata( $reply_author_id ) : null;
				$reply_author_link = ( $reply_author && current_user_can( YoOhw_Support_Capabilities::VIEW_CUSTOMERS ) )
					? get_author_posts_url( $reply_author->ID, $reply_author->user_nicename )
					: '';
				$reply_is_support = ( $reply_author && user_can( $reply_author->ID, YoOhw_Support_Capabilities::MANAGE_TOPICS ) );
				$reply_is_support = (bool) apply_filters( 'yoohw_support_is_support_comment', $reply_is_support, $comment );
				?>
				<article id="comment-<?php echo esc_attr( $comment->comment_ID ); ?>" class="yoohw-reply <?php echo $reply_is_support ? 'is-support' : 'is-customer'; ?>">
					<div class="yoohw-reply-rail" aria-hidden="true">
						<div class="yoohw-avatar" aria-hidden="true"><?php echo esc_html( strtoupper( substr( $author_text, 0, 1 ) ) ); ?></div>
					</div>

					<div class="yoohw-reply-bubble">
						<header class="yoohw-reply-header">
							<div class="yoohw-reply-author">
								<strong>
									<?php
									if ( $reply_author_link ) {
										echo '<a class="yoohw-user-link" href="' . esc_url( $reply_author_link ) . '">';
									}

									echo wp_kses( $author_html, $allowed_author_html );

									if ( $reply_author_link ) {
										echo '</a>';
									}
									?>
								</strong>
								<span class="yoohw-reply-date"><?php echo esc_html( get_comment_date( '', $comment ) ); ?> <?php echo esc_html( mysql2date( get_option( 'time_format' ), $comment->comment_date ) ); ?></span>
							</div>
							<span class="yoohw-reply-side"><?php echo esc_html( $reply_is_support ? $reply_side_labels['support'] : $reply_side_labels['customer'] ); ?></span>
						</header>
						<div class="yoohw-reply-content">
							<?php echo apply_filters( 'comment_text', $comment->comment_content, $comment, [] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped,WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Core comment filter produces display HTML. ?>
						</div>
					</div>
				</article>
			<?php endforeach; ?>
		</div>
	<?php else : ?>
		<div class="yoohw-empty-state yoohw-empty-state-compact">
			<?php YoOhw_Support_Icons::output( 'message-square' ); ?>
			<p><?php esc_html_e( 'No replies yet.', 'yoohw-support-portal' ); ?></p>
		</div>
	<?php endif; ?>
</section>

<section class="yoohw-panel yoohw-reply-form-panel">
	<?php if ( comments_open( $post->ID ) ) : ?>
		<?php
		comment_form(
			[
				'title_reply'          => __( 'Add a reply', 'yoohw-support-portal' ),
				'title_reply_before'   => '<div class="yoohw-reply-form-header"><span class="yoohw-reply-form-icon">' . YoOhw_Support_Icons::render( 'message-square' ) . '</span><div><p class="yoohw-kicker">' . esc_html__( 'Conversation', 'yoohw-support-portal' ) . '</p><h2 id="reply-title" class="comment-reply-title yoohw-form-title">',
				'title_reply_after'    => '</h2><p class="yoohw-reply-form-description">' . esc_html__( 'Add context, screenshots, videos, or code details so the next step is clear.', 'yoohw-support-portal' ) . '</p></div></div>',
				'label_submit'         => __( 'Submit reply', 'yoohw-support-portal' ),
				'class_form'           => 'comment-form yoohw-reply-form',
				'class_submit'         => 'yoohw-button yoohw-button-primary yoohw-reply-submit',
				'submit_button'        => '<button name="%1$s" type="submit" id="%2$s" class="%3$s" value="%4$s">' . YoOhw_Support_Icons::render( 'send' ) . '<span>%4$s</span></button>',
				'submit_field'         => '<div class="form-submit yoohw-reply-submit-row">%1$s %2$s</div>',
				'comment_notes_before' => '',
				'comment_notes_after'  => '',
				'logged_in_as'         => '',
			],
			$post->ID
		);
		?>
	<?php else : ?>
		<div class="yoohw-empty-state yoohw-empty-state-compact">
			<?php YoOhw_Support_Icons::output( 'circle-check' ); ?>
			<p><?php esc_html_e( 'This topic is resolved and replies are closed.', 'yoohw-support-portal' ); ?></p>
		</div>
	<?php endif; ?>
</section>

<?php wp_reset_postdata(); ?>
