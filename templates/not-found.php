<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- Variables are intentionally scoped to this rendered template.

$actions = isset( $actions ) && is_array( $actions ) ? $actions : [];
?>

<section class="yoohw-panel yoohw-not-found" aria-labelledby="yoohw-not-found-title">
	<div class="yoohw-not-found-code" aria-hidden="true">404</div>
	<span class="yoohw-not-found-icon" aria-hidden="true"><?php YoOhw_Support_Icons::output( 'circle-alert' ); ?></span>
	<p class="yoohw-kicker"><?php esc_html_e( 'Page Not Found', 'yoohw-support-portal' ); ?></p>
	<h2 id="yoohw-not-found-title"><?php esc_html_e( 'We could not find that page.', 'yoohw-support-portal' ); ?></h2>
	<p class="yoohw-not-found-description">
		<?php esc_html_e( 'The address may be incorrect, or the page may have been moved. Use one of the links below to continue.', 'yoohw-support-portal' ); ?>
	</p>

	<?php if ( $actions ) : ?>
		<div class="yoohw-status-actions">
			<?php foreach ( $actions as $index => $action ) : ?>
				<a class="yoohw-button <?php echo 0 === $index ? 'yoohw-button-primary' : 'yoohw-button-secondary'; ?>" href="<?php echo esc_url( $action['url'] ); ?>">
					<?php YoOhw_Support_Icons::output( $action['icon'] ?? 'external-link' ); ?>
					<?php echo esc_html( $action['label'] ); ?>
				</a>
			<?php endforeach; ?>
		</div>
	<?php endif; ?>
</section>
