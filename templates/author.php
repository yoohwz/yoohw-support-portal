<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- Variables are intentionally scoped to this rendered template.

$display_name = $author->display_name ?: $author->user_login;
$account      = $author->user_login;
$initial      = strtoupper( substr( $display_name, 0, 1 ) );
$registered   = $author->user_registered ? date_i18n( get_option( 'date_format' ), strtotime( $author->user_registered ) ) : '';
$base_url     = get_author_posts_url( $author->ID, $author->user_nicename );
?>

<section class="yoohw-panel yoohw-author-profile" aria-label="<?php esc_attr_e( 'Author profile', 'yoohw-support-portal' ); ?>">
	<div class="yoohw-author-avatar" aria-hidden="true"><?php echo esc_html( $initial ); ?></div>

	<div class="yoohw-author-profile-main">
		<p class="yoohw-kicker"><?php esc_html_e( 'Author', 'yoohw-support-portal' ); ?></p>
		<h2><?php echo esc_html( $display_name ); ?></h2>
		<div class="yoohw-author-meta">
			<span><?php YoOhw_Support_Icons::output( 'user' ); ?> @<?php echo esc_html( $account ); ?></span>
			<?php if ( $registered ) : ?>
				<span><?php YoOhw_Support_Icons::output( 'calendar' ); ?> <?php /* translators: %s: User registration date. */ echo esc_html( sprintf( __( 'Joined %s', 'yoohw-support-portal' ), $registered ) ); ?></span>
			<?php endif; ?>
		</div>
	</div>

	<div class="yoohw-author-stats" aria-label="<?php esc_attr_e( 'Author support stats', 'yoohw-support-portal' ); ?>">
		<div class="yoohw-author-stat">
			<strong><?php echo esc_html( number_format_i18n( $stats['total'] ) ); ?></strong>
			<span><?php esc_html_e( 'Topics', 'yoohw-support-portal' ); ?></span>
		</div>
		<div class="yoohw-author-stat">
			<strong><?php echo esc_html( number_format_i18n( $stats['open'] ) ); ?></strong>
			<span><?php esc_html_e( 'Open', 'yoohw-support-portal' ); ?></span>
		</div>
		<div class="yoohw-author-stat">
			<strong><?php echo esc_html( number_format_i18n( $stats['resolved'] ) ); ?></strong>
			<span><?php esc_html_e( 'Resolved', 'yoohw-support-portal' ); ?></span>
		</div>
		<div class="yoohw-author-stat">
			<strong><?php echo esc_html( number_format_i18n( $stats['replies'] ) ); ?></strong>
			<span><?php esc_html_e( 'Replies', 'yoohw-support-portal' ); ?></span>
		</div>
	</div>
</section>

<section class="yoohw-panel yoohw-panel-heading yoohw-author-topic-heading">
	<div>
		<p class="yoohw-kicker"><?php esc_html_e( 'Topics', 'yoohw-support-portal' ); ?></p>
		<h2><?php esc_html_e( 'Topics by this author', 'yoohw-support-portal' ); ?></h2>
	</div>
	<a class="yoohw-button yoohw-button-secondary" href="<?php echo esc_url( YoOhw_Support_Router::url( 'topics' ) ); ?>">
		<?php YoOhw_Support_Icons::output( 'chevron-left' ); ?>
		<?php esc_html_e( 'All topics', 'yoohw-support-portal' ); ?>
	</a>
</section>

<section class="yoohw-topic-list yoohw-author-topic-list" aria-label="<?php esc_attr_e( 'Author topics', 'yoohw-support-portal' ); ?>">
	<?php if ( $query->have_posts() ) : ?>
		<?php while ( $query->have_posts() ) : ?>
			<?php
			$query->the_post();
			$post = get_post();
			?>
			<article class="yoohw-topic-card">
				<div class="yoohw-topic-card-main">
					<div class="yoohw-topic-meta-row">
						<span class="yoohw-status-pill <?php echo esc_attr( YoOhw_Support_Controller::topic_status_class( $post ) ); ?>">
							<?php YoOhw_Support_Icons::output( YoOhw_Support_Controller::topic_status_class( $post ) === 'is-resolved' ? 'circle-check' : 'message-circle' ); ?>
							<?php echo esc_html( YoOhw_Support_Controller::topic_status_label( $post ) ); ?>
						</span>
						<?php YoOhw_Support_Controller::output_topic_category_meta( $post ); ?>
						<span class="yoohw-topic-meta"><?php YoOhw_Support_Icons::output( 'calendar' ); ?> <?php echo esc_html( get_the_date() ); ?></span>
					</div>

					<h2 class="yoohw-topic-card-title">
						<a href="<?php echo esc_url( YoOhw_Support_Controller::topic_url( $post ) ); ?>"><?php echo esc_html( $post->post_title ); ?></a>
					</h2>

					<div class="yoohw-topic-card-excerpt">
						<?php echo esc_html( wp_trim_words( wp_strip_all_tags( get_the_content() ), 28 ) ); ?>
					</div>
				</div>

				<a class="yoohw-topic-card-action" href="<?php echo esc_url( YoOhw_Support_Controller::topic_latest_reply_url( $post ) ); ?>">
					<span><?php echo esc_html( get_comments_number( $post->ID ) ); ?></span>
					<?php YoOhw_Support_Icons::output( 'message-square' ); ?>
				</a>
			</article>
		<?php endwhile; ?>
		<?php wp_reset_postdata(); ?>

		<?php
		$pagination = paginate_links(
			[
				'base'      => add_query_arg( 'support_page', '%#%', $base_url ),
				'format'    => '',
				'current'   => $paged,
				'total'     => max( 1, (int) $query->max_num_pages ),
				'prev_text' => YoOhw_Support_Icons::render( 'chevron-left' ) . esc_html__( 'Previous', 'yoohw-support-portal' ),
				'next_text' => esc_html__( 'Next', 'yoohw-support-portal' ) . YoOhw_Support_Icons::render( 'chevron-right' ),
				'type'      => 'list',
			]
		);
		?>

		<?php if ( $pagination ) : ?>
			<nav class="yoohw-pagination" aria-label="<?php esc_attr_e( 'Author topic pagination', 'yoohw-support-portal' ); ?>">
				<?php echo wp_kses_post( $pagination ); ?>
			</nav>
		<?php endif; ?>
	<?php else : ?>
		<div class="yoohw-empty-state">
			<?php YoOhw_Support_Icons::output( 'message-square' ); ?>
			<h2><?php esc_html_e( 'No topics found.', 'yoohw-support-portal' ); ?></h2>
			<p><?php esc_html_e( 'This author has not created any support topics yet.', 'yoohw-support-portal' ); ?></p>
		</div>
	<?php endif; ?>
</section>
