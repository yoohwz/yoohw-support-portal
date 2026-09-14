<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- Variables are intentionally scoped to this rendered template.

$base_url = YoOhw_Support_Router::url( 'topics' );
?>
<section class="yoohw-panel yoohw-topic-toolbar" aria-label="<?php esc_attr_e( 'Topic filters', 'yoohw-support-portal' ); ?>">
	<form class="yoohw-filter-form" action="<?php echo esc_url( $base_url ); ?>" method="get">
		<div class="yoohw-search-field">
			<?php YoOhw_Support_Icons::output( 'search' ); ?>
			<input type="search" name="s" value="<?php echo esc_attr( $search ); ?>" placeholder="<?php esc_attr_e( 'Search topics', 'yoohw-support-portal' ); ?>">
		</div>
		<select class="yoohw-filter-status" name="status" aria-label="<?php esc_attr_e( 'Filter by status', 'yoohw-support-portal' ); ?>">
			<option value=""><?php esc_html_e( 'All statuses', 'yoohw-support-portal' ); ?></option>
			<option value="publish" <?php selected( $status, 'publish' ); ?>><?php esc_html_e( 'Open', 'yoohw-support-portal' ); ?></option>
			<option value="resolved" <?php selected( $status, 'resolved' ); ?>><?php esc_html_e( 'Resolved', 'yoohw-support-portal' ); ?></option>
		</select>
		<select class="yoohw-filter-category" name="category" aria-label="<?php esc_attr_e( 'Filter by category', 'yoohw-support-portal' ); ?>">
			<option value="0"><?php esc_html_e( 'All categories', 'yoohw-support-portal' ); ?></option>
			<?php foreach ( $categories as $category ) : ?>
				<?php $term = $category['term'] ?? null; ?>
				<?php if ( $term instanceof WP_Term ) : ?>
					<option value="<?php echo esc_attr( $term->term_id ); ?>" <?php selected( $category_id, $term->term_id ); ?>><?php echo esc_html( $category['label'] ?? $term->name ); ?></option>
				<?php endif; ?>
			<?php endforeach; ?>
		</select>
		<button type="submit" class="yoohw-button yoohw-button-secondary"><?php YoOhw_Support_Icons::output( 'list' ); ?> <?php esc_html_e( 'Apply', 'yoohw-support-portal' ); ?></button>
	</form>
	<a class="yoohw-button yoohw-button-primary" href="<?php echo esc_url( YoOhw_Support_Router::url( 'new' ) ); ?>"><?php YoOhw_Support_Icons::output( 'plus' ); ?> <?php esc_html_e( 'New topic', 'yoohw-support-portal' ); ?></a>
</section>

<section class="yoohw-topic-list" aria-label="<?php esc_attr_e( 'Topics', 'yoohw-support-portal' ); ?>">
	<?php if ( ! empty( $topics ) ) : ?>
		<?php foreach ( $topics as $topic ) : ?>
			<?php
			$is_resolved = 'resolved' === $topic['status'];
			$path        = YoOhw_Support_Controller::isolated_category_path( (int) $topic['category_id'] );
			$topic_url   = YoOhw_Support_Router::topic_url( $topic );
			$reply_count = YoOhw_Support_Database::count_replies( (int) $topic['id'] );
			?>
			<article class="yoohw-topic-card">
				<div class="yoohw-topic-card-main">
					<div class="yoohw-topic-meta-row">
						<span class="yoohw-status-pill <?php echo $is_resolved ? 'is-resolved' : 'is-open'; ?>">
							<?php YoOhw_Support_Icons::output( $is_resolved ? 'circle-check' : 'message-circle' ); ?>
							<?php echo esc_html( $is_resolved ? __( 'Resolved', 'yoohw-support-portal' ) : __( 'Open', 'yoohw-support-portal' ) ); ?>
						</span>
						<span class="yoohw-topic-meta"><?php YoOhw_Support_Icons::output( 'tag' ); ?> <?php echo esc_html( implode( ' / ', $path ) ); ?></span>
						<span class="yoohw-topic-meta"><?php YoOhw_Support_Icons::output( 'calendar' ); ?> <?php echo esc_html( mysql2date( get_option( 'date_format' ), $topic['created_at'] ) ); ?></span>
					</div>
					<h2 class="yoohw-topic-card-title"><a href="<?php echo esc_url( $topic_url ); ?>"><?php echo esc_html( $topic['title'] ); ?></a></h2>
					<div class="yoohw-topic-card-excerpt"><?php echo esc_html( wp_trim_words( wp_strip_all_tags( $topic['content'] ), 28 ) ); ?></div>
				</div>
				<a class="yoohw-topic-card-action" href="<?php echo esc_url( $topic_url . ( $reply_count ? '#reply-list' : '' ) ); ?>">
					<span><?php echo esc_html( $reply_count ); ?></span><?php YoOhw_Support_Icons::output( 'message-square' ); ?>
				</a>
			</article>
		<?php endforeach; ?>
		<?php
		$pagination = paginate_links(
			[
				'base'      => add_query_arg( 'support_page', '%#%', $base_url ),
				'format'    => '',
				'current'   => $paged,
				'total'     => max( 1, (int) $max_pages ),
				'prev_text' => YoOhw_Support_Icons::render( 'chevron-left' ) . esc_html__( 'Previous', 'yoohw-support-portal' ),
				'next_text' => esc_html__( 'Next', 'yoohw-support-portal' ) . YoOhw_Support_Icons::render( 'chevron-right' ),
				'type'      => 'list',
			]
		);
		?>
		<?php if ( $pagination ) : ?><nav class="yoohw-pagination"><?php echo wp_kses_post( $pagination ); ?></nav><?php endif; ?>
	<?php else : ?>
		<div class="yoohw-empty-state">
			<?php YoOhw_Support_Icons::output( 'message-square' ); ?>
			<h2><?php esc_html_e( 'No topics found.', 'yoohw-support-portal' ); ?></h2>
			<p><?php esc_html_e( 'Create a new topic or adjust the filters.', 'yoohw-support-portal' ); ?></p>
		</div>
	<?php endif; ?>
</section>
