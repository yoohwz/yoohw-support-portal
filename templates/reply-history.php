<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<div class="yoohw-reply-history" hidden>
	<div class="yoohw-reply-history-summary">
		<?php YoOhw_Support_Icons::output( 'message-square' ); ?>
		<div>
			<strong data-reply-history-count></strong>
			<p data-reply-history-progress></p>
		</div>
	</div>
	<button type="button" class="yoohw-button yoohw-button-secondary" aria-controls="yoohw-reply-list">
		<span class="yoohw-reply-history-spinner" aria-hidden="true"></span>
		<span data-reply-history-label><?php esc_html_e( 'Show older replies', 'yoohw-support-portal' ); ?></span>
	</button>
</div>
<p class="screen-reader-text" role="status" aria-live="polite" data-reply-history-status></p>
