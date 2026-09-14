<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- Variables are intentionally scoped to this rendered template.
?>

<article class="yoohw-panel yoohw-content-page">
	<?php if ( has_post_thumbnail( $page ) ) : ?>
		<figure class="yoohw-content-page-media">
			<?php echo get_the_post_thumbnail( $page, 'large', [ 'class' => 'yoohw-content-page-image' ] ); ?>
		</figure>
	<?php endif; ?>

	<div class="yoohw-content-page-body yoohw-topic-body">
		<?php echo $content; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
	</div>
</article>
