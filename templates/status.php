<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- Variables are intentionally scoped to this rendered template.

$actions = isset( $actions ) && is_array( $actions ) ? $actions : [];
?>

<section class="yoohw-panel yoohw-status-panel">
	<span class="yoohw-status-hero-icon"><?php YoOhw_Support_Icons::output( $icon ?? 'circle-alert' ); ?></span>
	<?php if ( ! empty( $kicker ) ) : ?>
		<p class="yoohw-kicker"><?php echo esc_html( $kicker ); ?></p>
	<?php endif; ?>
	<h2><?php echo esc_html( $title ?? __( 'Support Portal', 'yoohw-support-portal' ) ); ?></h2>
	<?php if ( ! empty( $description ) ) : ?>
		<p><?php echo esc_html( $description ); ?></p>
	<?php endif; ?>

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
