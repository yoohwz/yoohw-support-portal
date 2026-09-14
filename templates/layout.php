<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- Variables are intentionally scoped to this rendered template.

$view          = YoOhw_Support_Router::get_view();
$page_title    = YoOhw_Support_Controller::page_title( $view );
$site_title    = get_bloginfo( 'name' ) ?: 'Support Portal';
$site_tagline  = trim( (string) get_bloginfo( 'description' ) );
$site_icon_url = get_site_icon_url( 96 );
$show_credit   = YoOhw_Support_Settings::footer_credit_enabled();
$credit_url    = YoOhw_Support_Settings::credit_url();
$credit_label  = YoOhw_Support_Settings::credit_label();
?>
<!doctype html>
<html <?php language_attributes(); ?>>
<head>
	<meta charset="<?php bloginfo( 'charset' ); ?>">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<?php wp_head(); ?>
</head>
<body <?php body_class( 'yoohw-support-body' ); ?>>
<?php wp_body_open(); ?>
<a class="yoohw-skip-link" href="#yoohw-support-main"><?php esc_html_e( 'Skip to support content', 'yoohw-support-portal' ); ?></a>

<div class="yoohw-support" data-view="<?php echo esc_attr( $view ); ?>">
	<header class="yoohw-shell-header">
		<a class="yoohw-brand" href="<?php echo esc_url( YoOhw_Support_Router::url( 'dashboard' ) ); ?>">
			<?php if ( $site_icon_url ) : ?>
				<span class="yoohw-brand-mark">
					<img class="yoohw-brand-icon" src="<?php echo esc_url( $site_icon_url ); ?>" alt="">
				</span>
			<?php endif; ?>
			<span class="yoohw-brand-text">
				<span class="yoohw-brand-name"><?php echo esc_html( $site_title ); ?></span>
				<?php if ( '' !== $site_tagline ) : ?>
					<span class="yoohw-brand-subtitle"><?php echo esc_html( $site_tagline ); ?></span>
				<?php endif; ?>
			</span>
		</a>

		<button class="yoohw-nav-toggle" type="button" aria-expanded="false" aria-controls="yoohw-support-nav">
			<?php YoOhw_Support_Icons::output( 'menu' ); ?>
			<span class="screen-reader-text"><?php esc_html_e( 'Toggle navigation', 'yoohw-support-portal' ); ?></span>
		</button>

		<nav id="yoohw-support-nav" class="yoohw-shell-nav" aria-label="<?php esc_attr_e( 'Support navigation', 'yoohw-support-portal' ); ?>">
			<?php foreach ( YoOhw_Support_Controller::nav_items() as $item ) : ?>
				<a class="yoohw-shell-nav-link <?php echo $view === $item['view'] ? 'is-current' : ''; ?>" href="<?php echo esc_url( $item['url'] ); ?>">
					<?php YoOhw_Support_Icons::output( $item['icon'] ); ?>
					<span><?php echo esc_html( $item['label'] ); ?></span>
				</a>
			<?php endforeach; ?>
		</nav>
	</header>

	<main class="yoohw-shell-main" id="yoohw-support-main">
		<div class="yoohw-page-heading">
			<p class="yoohw-kicker"><?php echo esc_html( YoOhw_Support_Controller::page_kicker( $view ) ); ?></p>
			<h1><?php echo esc_html( $page_title ); ?></h1>
		</div>

		<?php YoOhw_Support_Controller::render_current(); ?>
	</main>

	<?php if ( $show_credit ) : ?>
		<footer class="yoohw-shell-footer">
			<a href="<?php echo esc_url( $credit_url ); ?>" rel="nofollow"><?php YoOhw_Support_Icons::output( 'external-link' ); ?> <?php echo esc_html( $credit_label ); ?></a>
		</footer>
	<?php endif; ?>
</div>

<?php wp_footer(); ?>
</body>
</html>
