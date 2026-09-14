<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- Variables are intentionally scoped to this rendered template.

$is_resolved = 'resolved' === $topic['status'];
$author      = get_userdata( (int) $topic['author_id'] );
$author_name = $author ? $author->display_name : __( 'Unknown user', 'yoohw-support-portal' );
$author_url  = ( $author && current_user_can( YoOhw_Support_Capabilities::VIEW_CUSTOMERS ) )
	? home_url( '/support/customers/' . absint( $author->ID ) . '/' )
	: '';
$path        = YoOhw_Support_Controller::isolated_category_path( (int) $topic['category_id'] );
$labels      = YoOhw_Support_Settings::reply_side_labels();
?>
<?php // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only success message flag after a protected create action. ?>
<?php if ( ! empty( $_GET['created'] ) ) : ?>
	<div class="yoohw-notice yoohw-notice-success"><?php esc_html_e( 'Your topic has been created.', 'yoohw-support-portal' ); ?></div>
<?php endif; ?>
<article class="yoohw-panel yoohw-topic-detail">
	<header class="yoohw-topic-detail-header">
		<div class="yoohw-topic-meta-row">
			<span class="yoohw-status-pill <?php echo $is_resolved ? 'is-resolved' : 'is-open'; ?>">
				<?php YoOhw_Support_Icons::output( $is_resolved ? 'circle-check' : 'message-circle' ); ?>
				<?php echo esc_html( $is_resolved ? __( 'Resolved', 'yoohw-support-portal' ) : __( 'Open', 'yoohw-support-portal' ) ); ?>
			</span>
			<span class="yoohw-topic-meta"><?php YoOhw_Support_Icons::output( 'tag' ); ?> <?php echo esc_html( implode( ' / ', $path ) ); ?></span>
			<span class="yoohw-topic-meta"><?php YoOhw_Support_Icons::output( 'calendar' ); ?> <?php echo esc_html( mysql2date( get_option( 'date_format' ), $topic['created_at'] ) ); ?></span>
			<div class="yoohw-topic-author">
				<?php YoOhw_Support_Icons::output( 'user' ); ?>
				<span class="yoohw-topic-author-name">
					<?php if ( $author_url ) : ?><a class="yoohw-user-link" href="<?php echo esc_url( $author_url ); ?>"><?php endif; ?>
					<?php echo esc_html( $author_name ); ?>
					<?php if ( $author_url ) : ?></a><?php endif; ?>
				</span>
			</div>
		</div>
		<h2><?php echo esc_html( $topic['title'] ); ?></h2>
		<?php if ( current_user_can( YoOhw_Support_Capabilities::MANAGE_TOPICS ) ) : ?>
			<form method="post" class="yoohw-topic-status-form">
				<?php wp_nonce_field( 'yoohw_isolated_status', 'yoohw_isolated_status_nonce' ); ?>
				<input type="hidden" name="yoohw_isolated_status" value="1">
				<input type="hidden" name="topic_id" value="<?php echo esc_attr( $topic['id'] ); ?>">
				<input type="hidden" name="topic_status" value="<?php echo $is_resolved ? 'open' : 'resolved'; ?>">
				<button type="submit" class="yoohw-button yoohw-button-secondary">
					<?php YoOhw_Support_Icons::output( $is_resolved ? 'message-circle' : 'circle-check' ); ?>
					<?php echo esc_html( $is_resolved ? __( 'Reopen topic', 'yoohw-support-portal' ) : __( 'Mark as resolved', 'yoohw-support-portal' ) ); ?>
				</button>
			</form>
		<?php endif; ?>
	</header>
	<div class="yoohw-topic-body"><?php echo apply_filters( 'the_content', $topic['content'] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped,WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Core content filter produces display HTML. ?></div>
</article>

<section class="yoohw-replies" id="reply-list" aria-label="<?php esc_attr_e( 'Replies', 'yoohw-support-portal' ); ?>">
	<div class="yoohw-conversation-heading">
		<div><p class="yoohw-kicker"><?php esc_html_e( 'Conversation', 'yoohw-support-portal' ); ?></p><h2><?php /* translators: %d: Number of replies. */ printf( esc_html( _n( '%d reply', '%d replies', count( $replies ), 'yoohw-support-portal' ) ), absint( count( $replies ) ) ); ?></h2></div>
		<a class="yoohw-button yoohw-button-secondary" href="<?php echo esc_url( YoOhw_Support_Router::url( 'topics' ) ); ?>"><?php YoOhw_Support_Icons::output( 'chevron-left' ); ?> <?php esc_html_e( 'All topics', 'yoohw-support-portal' ); ?></a>
	</div>
	<?php if ( ! empty( $replies ) ) : ?>
		<div class="yoohw-reply-list">
			<?php foreach ( $replies as $reply ) : ?>
				<?php
				$reply_author  = get_userdata( (int) $reply['author_id'] );
				$reply_name    = $reply_author ? $reply_author->display_name : __( 'Unknown user', 'yoohw-support-portal' );
				$is_support    = $reply_author && user_can( $reply_author->ID, YoOhw_Support_Capabilities::MANAGE_TOPICS );
				?>
				<article id="reply-<?php echo esc_attr( $reply['id'] ); ?>" class="yoohw-reply <?php echo $is_support ? 'is-support' : 'is-customer'; ?>">
					<div class="yoohw-reply-rail"><div class="yoohw-avatar"><?php echo esc_html( strtoupper( substr( $reply_name, 0, 1 ) ) ); ?></div></div>
					<div class="yoohw-reply-bubble">
						<header class="yoohw-reply-header"><div class="yoohw-reply-author"><strong><?php echo esc_html( $reply_name ); ?></strong><span class="yoohw-reply-date"><?php echo esc_html( mysql2date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $reply['created_at'] ) ); ?></span></div><span class="yoohw-reply-side"><?php echo esc_html( $is_support ? $labels['support'] : $labels['customer'] ); ?></span></header>
						<div class="yoohw-reply-content"><?php echo apply_filters( 'the_content', $reply['content'] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped,WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Core content filter produces display HTML. ?></div>
					</div>
				</article>
			<?php endforeach; ?>
		</div>
	<?php else : ?>
		<div class="yoohw-empty-state yoohw-empty-state-compact"><p><?php esc_html_e( 'No replies yet.', 'yoohw-support-portal' ); ?></p></div>
	<?php endif; ?>
</section>

<section class="yoohw-panel yoohw-reply-form-panel">
	<?php if ( ! $is_resolved ) : ?>
		<form class="yoohw-form yoohw-reply-form" method="post">
			<?php wp_nonce_field( 'yoohw_isolated_reply', 'yoohw_isolated_reply_nonce' ); ?>
			<input type="hidden" name="yoohw_isolated_reply" value="1">
			<input type="hidden" name="topic_id" value="<?php echo esc_attr( $topic['id'] ); ?>">
			<div class="yoohw-reply-form-header"><span class="yoohw-reply-form-icon"><?php YoOhw_Support_Icons::output( 'message-square' ); ?></span><div><p class="yoohw-kicker"><?php esc_html_e( 'Conversation', 'yoohw-support-portal' ); ?></p><h2><?php esc_html_e( 'Add a reply', 'yoohw-support-portal' ); ?></h2></div></div>
			<textarea name="comment" rows="8" required></textarea>
			<div class="form-submit yoohw-reply-submit-row"><button type="submit" class="yoohw-button yoohw-button-primary"><?php YoOhw_Support_Icons::output( 'send' ); ?><span><?php esc_html_e( 'Submit reply', 'yoohw-support-portal' ); ?></span></button></div>
		</form>
	<?php else : ?>
		<div class="yoohw-empty-state yoohw-empty-state-compact"><p><?php esc_html_e( 'This topic is resolved and replies are closed.', 'yoohw-support-portal' ); ?></p></div>
	<?php endif; ?>
</section>
