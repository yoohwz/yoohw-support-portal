<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- Variables are intentionally scoped to this rendered template.
?>

<section class="yoohw-panel yoohw-compose-panel">
	<div class="yoohw-panel-heading">
		<div>
			<p class="yoohw-kicker"><?php esc_html_e( 'New request', 'yoohw-support-portal' ); ?></p>
			<h2><?php esc_html_e( 'Create a support topic', 'yoohw-support-portal' ); ?></h2>
		</div>
		<a class="yoohw-button yoohw-button-secondary" href="<?php echo esc_url( YoOhw_Support_Router::url( 'topics' ) ); ?>">
			<?php YoOhw_Support_Icons::output( 'message-square' ); ?>
			<?php esc_html_e( 'My topics', 'yoohw-support-portal' ); ?>
		</a>
	</div>

	<?php echo $form; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
</section>
