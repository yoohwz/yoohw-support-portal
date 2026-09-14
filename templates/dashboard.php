<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- Variables are intentionally scoped to this rendered template.
?>

<section class="yoohw-dashboard-grid" aria-label="<?php esc_attr_e( 'Support actions', 'yoohw-support-portal' ); ?>">
	<?php foreach ( $cards as $card ) : ?>
		<a class="yoohw-dashboard-card" href="<?php echo esc_url( $card['url'] ); ?>" <?php echo ! empty( $card['external'] ) ? 'target="_blank" rel="noopener noreferrer"' : ''; ?>>
			<span class="yoohw-dashboard-card-icon"><?php YoOhw_Support_Icons::output( $card['icon'] ); ?></span>
			<span class="yoohw-dashboard-card-body">
				<span class="yoohw-dashboard-card-title">
					<?php echo esc_html( $card['title'] ); ?>
					<?php if ( ! empty( $card['external'] ) ) : ?>
						<?php YoOhw_Support_Icons::output( 'external-link', [ 'class' => 'yoohw-card-external-icon' ] ); ?>
					<?php endif; ?>
				</span>
				<span class="yoohw-dashboard-card-description"><?php echo esc_html( $card['description'] ); ?></span>
			</span>
		</a>
	<?php endforeach; ?>
</section>

<?php if ( ! empty( $workspace ) && is_array( $workspace ) ) : ?>
	<section class="yoohw-panel yoohw-workspace-home-section" aria-label="<?php esc_attr_e( 'Workspace', 'yoohw-support-portal' ); ?>">
		<div class="yoohw-workspace-home-main">
			<span class="yoohw-workspace-home-icon"><?php YoOhw_Support_Icons::output( 'briefcase' ); ?></span>
			<div>
				<p class="yoohw-kicker"><?php esc_html_e( 'Workspace', 'yoohw-support-portal' ); ?></p>
				<h2><?php esc_html_e( 'Custom work projects', 'yoohw-support-portal' ); ?></h2>
				<p><?php esc_html_e( 'Create project requests, share requirements, and work with the development team on custom implementation tasks.', 'yoohw-support-portal' ); ?></p>
			</div>
		</div>

		<?php if ( ! empty( $workspace['counts'] ) ) : ?>
			<div class="yoohw-workspace-home-stats" aria-label="<?php esc_attr_e( 'Workspace project summary', 'yoohw-support-portal' ); ?>">
				<div>
					<strong><?php echo esc_html( number_format_i18n( absint( $workspace['counts']['total'] ?? 0 ) ) ); ?></strong>
					<span><?php esc_html_e( 'Projects', 'yoohw-support-portal' ); ?></span>
				</div>
				<div>
					<strong><?php echo esc_html( number_format_i18n( absint( $workspace['counts']['active'] ?? 0 ) ) ); ?></strong>
					<span><?php esc_html_e( 'Active', 'yoohw-support-portal' ); ?></span>
				</div>
				<div>
					<strong><?php echo esc_html( number_format_i18n( absint( $workspace['counts']['completed'] ?? 0 ) ) ); ?></strong>
					<span><?php esc_html_e( 'Completed', 'yoohw-support-portal' ); ?></span>
				</div>
			</div>
		<?php endif; ?>

		<?php if ( ! empty( $workspace['site_counts'] ) ) : ?>
			<?php
			$site_counts              = is_array( $workspace['site_counts'] ) ? $workspace['site_counts'] : [];
			$site_ongoing             = absint( $site_counts['ongoing'] ?? 0 );
			$site_completed           = absint( $site_counts['completed'] ?? 0 );
			$site_in_scoping          = absint( $site_counts['in_scoping'] ?? 0 );
			$site_capacity_max        = max( 1, absint( apply_filters( 'yoohw_workspace_team_readiness_capacity', 5 ) ) );
			$site_capacity_used       = min( $site_ongoing, $site_capacity_max );
			$site_capacity_percent    = (int) round( ( $site_capacity_used / $site_capacity_max ) * 100 );
			$site_capacity_available  = max( 0, $site_capacity_max - $site_ongoing );
			$site_capacity_state      = $site_ongoing >= $site_capacity_max ? 'is-full' : ( $site_ongoing >= ( $site_capacity_max - 1 ) ? 'is-limited' : 'is-ready' );
			$site_capacity_label      = __( 'Ready for new projects', 'yoohw-support-portal' );
			$site_capacity_short_text = sprintf(
				/* translators: 1: ongoing project count, 2: capacity max, 3: available slot count */
				__( '%1$d ongoing projects out of %2$d preferred active slots. %3$d slots currently available.', 'yoohw-support-portal' ),
				$site_ongoing,
				$site_capacity_max,
				$site_capacity_available
			);

			if ( 'is-full' === $site_capacity_state ) {
				$site_capacity_label = __( 'At team capacity', 'yoohw-support-portal' );
			} elseif ( 'is-limited' === $site_capacity_state ) {
				$site_capacity_label = __( 'Limited availability', 'yoohw-support-portal' );
			}
			?>
			<div class="yoohw-workspace-home-readiness <?php echo esc_attr( $site_capacity_state ); ?>" aria-label="<?php esc_attr_e( 'Workspace team readiness', 'yoohw-support-portal' ); ?>">
				<div class="yoohw-workspace-home-readiness-header">
					<div>
						<span><?php esc_html_e( 'Team readiness', 'yoohw-support-portal' ); ?></span>
						<strong><?php echo esc_html( $site_capacity_label ); ?></strong>
					</div>
					<em><?php echo esc_html( sprintf( '%1$d/%2$d', $site_ongoing, $site_capacity_max ) ); ?></em>
				</div>
				<div class="yoohw-workspace-home-readiness-bar" style="<?php echo esc_attr( '--capacity:' . $site_capacity_percent . '%;' ); ?>" aria-hidden="true">
					<span></span>
				</div>
				<p><?php echo esc_html( $site_capacity_short_text ); ?></p>
				<div class="yoohw-workspace-home-site-stats" aria-label="<?php esc_attr_e( 'All workspace project stats', 'yoohw-support-portal' ); ?>">
					<div>
						<strong><?php echo esc_html( number_format_i18n( $site_ongoing ) ); ?></strong>
						<span><?php esc_html_e( 'Ongoing', 'yoohw-support-portal' ); ?></span>
					</div>
					<div>
						<strong><?php echo esc_html( number_format_i18n( $site_completed ) ); ?></strong>
						<span><?php esc_html_e( 'Completed', 'yoohw-support-portal' ); ?></span>
					</div>
					<div>
						<strong><?php echo esc_html( number_format_i18n( $site_in_scoping ) ); ?></strong>
						<span><?php esc_html_e( 'In scoping', 'yoohw-support-portal' ); ?></span>
					</div>
				</div>
			</div>
		<?php endif; ?>

		<div class="yoohw-workspace-home-actions">
			<a class="yoohw-button yoohw-button-primary" href="<?php echo esc_url( $workspace['new_url'] ?? $workspace['url'] ); ?>">
				<?php YoOhw_Support_Icons::output( 'square-pen' ); ?>
				<?php esc_html_e( 'New project', 'yoohw-support-portal' ); ?>
			</a>
			<a class="yoohw-button yoohw-button-secondary" href="<?php echo esc_url( $workspace['url'] ); ?>">
				<?php YoOhw_Support_Icons::output( 'briefcase' ); ?>
				<?php esc_html_e( 'Open workspace', 'yoohw-support-portal' ); ?>
			</a>
		</div>
	</section>
<?php endif; ?>
