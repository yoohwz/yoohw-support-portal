<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- Variables are intentionally scoped to this rendered template.

$base_url = home_url( '/support/customers/' . absint( $customer->ID ) . '/' );
?>
<section class="yoohw-panel yoohw-author-card">
	<div class="yoohw-author-avatar"><?php echo esc_html( strtoupper( substr( $customer->display_name, 0, 1 ) ) ); ?></div>
	<div>
		<p class="yoohw-kicker"><?php esc_html_e( 'Customer', 'yoohw-support-portal' ); ?></p>
		<h2><?php echo esc_html( $customer->display_name ); ?></h2>
		<p>@<?php echo esc_html( $customer->user_login ); ?> · <?php /* translators: %d: Number of support topics. */ printf( esc_html( _n( '%d topic', '%d topics', $total, 'yoohw-support-portal' ) ), absint( $total ) ); ?></p>
	</div>
</section>

<section class="yoohw-topic-list" aria-label="<?php esc_attr_e( 'Customer topics', 'yoohw-support-portal' ); ?>">
	<?php if ( ! empty( $topics ) ) : ?>
		<?php foreach ( $topics as $topic ) : ?>
			<?php
			$is_resolved = 'resolved' === $topic['status'];
			$topic_url   = YoOhw_Support_Router::topic_url( $topic );
			?>
			<article class="yoohw-topic-card">
				<div class="yoohw-topic-card-main">
					<div class="yoohw-topic-meta-row">
						<span class="yoohw-status-pill <?php echo $is_resolved ? 'is-resolved' : 'is-open'; ?>"><?php echo esc_html( $is_resolved ? __( 'Resolved', 'yoohw-support-portal' ) : __( 'Open', 'yoohw-support-portal' ) ); ?></span>
						<span class="yoohw-topic-meta"><?php echo esc_html( mysql2date( get_option( 'date_format' ), $topic['created_at'] ) ); ?></span>
					</div>
					<h2 class="yoohw-topic-card-title"><a href="<?php echo esc_url( $topic_url ); ?>"><?php echo esc_html( $topic['title'] ); ?></a></h2>
					<div class="yoohw-topic-card-excerpt"><?php echo esc_html( wp_trim_words( wp_strip_all_tags( $topic['content'] ), 28 ) ); ?></div>
				</div>
			</article>
		<?php endforeach; ?>
		<?php
		$pagination = paginate_links(
			[
				'base'      => add_query_arg( 'support_page', '%#%', $base_url ),
				'format'    => '',
				'current'   => $paged,
				'total'     => max( 1, (int) $max_pages ),
				'prev_text' => esc_html__( 'Previous', 'yoohw-support-portal' ),
				'next_text' => esc_html__( 'Next', 'yoohw-support-portal' ),
				'type'      => 'list',
			]
		);
		?>
		<?php if ( $pagination ) : ?><nav class="yoohw-pagination"><?php echo wp_kses_post( $pagination ); ?></nav><?php endif; ?>
	<?php else : ?>
		<div class="yoohw-empty-state"><p><?php esc_html_e( 'This customer has no isolated support topics.', 'yoohw-support-portal' ); ?></p></div>
	<?php endif; ?>
</section>
