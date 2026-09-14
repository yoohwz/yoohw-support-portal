<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- Variables are intentionally scoped to this rendered template.

$is_lost_password = 'lost-password' === $mode;
$is_reset_password = 'reset-password' === $mode;
$registration_allowed = ! empty( $registration_allowed ) && ! $is_lost_password && ! $is_reset_password;
$auth_variant = isset( $auth_variant ) ? sanitize_key( $auth_variant ) : 'login';
$is_register = $registration_allowed && 'register' === $auth_variant;
$login_url = YoOhw_Support_Router::url( 'login' );
$register_url = YoOhw_Support_Router::url( 'login', [ 'auth' => 'register' ] );
$login_intro_text = $is_reset_password
	? __( 'Choose a new password for your account.', 'yoohw-support-portal' )
	: ( $is_lost_password
	? __( 'Enter your account email or username and we will send a reset link.', 'yoohw-support-portal' )
	: apply_filters( 'yoohw_support_login_intro_text', __( 'Use your account to access the support portal.', 'yoohw-support-portal' ) ) );
$auth_icon = $is_reset_password ? 'lock' : ( $is_lost_password ? 'key-round' : 'log-in' );
$auth_kicker = $is_reset_password ? __( 'Set password', 'yoohw-support-portal' ) : ( $is_lost_password ? __( 'Account recovery', 'yoohw-support-portal' ) : __( 'Secure access', 'yoohw-support-portal' ) );
$auth_title = $is_reset_password ? __( 'Create a new password', 'yoohw-support-portal' ) : ( $is_lost_password ? __( 'Reset your password', 'yoohw-support-portal' ) : __( 'Log in to Support Portal', 'yoohw-support-portal' ) );
$panel_title = $is_reset_password ? __( 'New password', 'yoohw-support-portal' ) : ( $is_lost_password ? __( 'Password reset', 'yoohw-support-portal' ) : ( $is_register ? __( 'Create account', 'yoohw-support-portal' ) : __( 'Sign in', 'yoohw-support-portal' ) ) );
$panel_text = $is_reset_password ? __( 'Enter and confirm the password you want to use.', 'yoohw-support-portal' ) : ( $is_lost_password ? __( 'We will send reset instructions to your inbox.', 'yoohw-support-portal' ) : ( $is_register ? __( 'Registration is open for this support portal.', 'yoohw-support-portal' ) : __( 'Continue to your support dashboard.', 'yoohw-support-portal' ) ) );
?>

<section class="yoohw-auth-layout <?php echo $registration_allowed ? 'has-register' : ''; ?>">
	<div class="yoohw-auth-intro">
		<span class="yoohw-auth-icon"><?php YoOhw_Support_Icons::output( $auth_icon ); ?></span>
		<p class="yoohw-kicker"><?php echo esc_html( $auth_kicker ); ?></p>
		<h2><?php echo esc_html( $auth_title ); ?></h2>
		<p><?php echo esc_html( $login_intro_text ); ?></p>
		<?php if ( $registration_allowed ) : ?>
			<div class="yoohw-auth-callout">
				<span><?php YoOhw_Support_Icons::output( 'user' ); ?></span>
				<div>
					<strong><?php esc_html_e( 'Need an account?', 'yoohw-support-portal' ); ?></strong>
					<p><?php esc_html_e( 'Create one below, then verify your email before signing in.', 'yoohw-support-portal' ); ?></p>
				</div>
			</div>
		<?php endif; ?>
	</div>

	<div class="yoohw-auth-stack">
		<div class="yoohw-panel yoohw-auth-panel yoohw-auth-panel-primary">
			<?php if ( $registration_allowed ) : ?>
				<div class="yoohw-auth-switch" role="tablist" aria-label="<?php esc_attr_e( 'Account access', 'yoohw-support-portal' ); ?>">
					<a class="<?php echo $is_register ? '' : 'is-active'; ?>" href="<?php echo esc_url( $login_url ); ?>" role="tab" aria-selected="<?php echo $is_register ? 'false' : 'true'; ?>">
						<?php YoOhw_Support_Icons::output( 'log-in' ); ?>
						<?php esc_html_e( 'Log in', 'yoohw-support-portal' ); ?>
					</a>
					<a class="<?php echo $is_register ? 'is-active' : ''; ?>" href="<?php echo esc_url( $register_url ); ?>" role="tab" aria-selected="<?php echo $is_register ? 'true' : 'false'; ?>">
						<?php YoOhw_Support_Icons::output( 'user' ); ?>
						<?php esc_html_e( 'Register', 'yoohw-support-portal' ); ?>
					</a>
				</div>
			<?php endif; ?>
			<div class="yoohw-auth-panel-heading">
				<h3><?php echo esc_html( $panel_title ); ?></h3>
				<p><?php echo esc_html( $panel_text ); ?></p>
			</div>
			<?php echo $form; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
		</div>
	</div>
</section>
