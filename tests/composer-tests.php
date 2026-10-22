<?php
/** Local WP-CLI: synthetic native/isolated records, intercepted mail and redirects. */
if ( ! defined( 'WP_CLI' ) || ! WP_CLI || 'local' !== wp_get_environment_type() ) { throw new RuntimeException( 'Composer tests require Local WP-CLI.' ); }
require_once ABSPATH . 'wp-admin/includes/user.php';
$checks = 0; $posts = $comments = $users = $isolated = []; $category = 0;
$original_user = get_current_user_id(); $original_post = $_POST; $original_request = $_REQUEST; $original_method = $_SERVER['REQUEST_METHOD'] ?? 'GET'; $original_files = $_FILES;
$settings = YoOhw_Support_Settings::get(); $settings['dedicated_portal_mode'] = 'wordpress';
$settings_read = static function () use ( &$settings ) { return $settings; };
$mail = static function () { return true; };
$redirect = static function () { throw new RuntimeException( 'redirect', 302 ); };
$die = static function () { return static function ( $message, $title = '', $args = [] ) { throw new RuntimeException( wp_strip_all_tags( $message ), (int) ( $args['response'] ?? 403 ) ); }; };
$prefix = 'ysp27-' . strtolower( wp_generate_password( 8, false, false ) );
$track = static function ( $id, $post ) use ( &$posts, $prefix ) { if ( 0 === strpos( $post->post_title, $prefix ) ) { $posts[$id] = $id; } };
add_filter( 'pre_option_' . YoOhw_Support_Settings::OPTION_NAME, $settings_read ); add_filter( 'pre_wp_mail', $mail, PHP_INT_MAX ); add_filter( 'wp_redirect', $redirect, -999 ); add_filter( 'wp_die_handler', $die, 999 ); add_action( 'wp_insert_post', $track, 10, 2 );
$assert = static function ( $ok, $label ) use ( &$checks ) { if ( ! $ok ) { throw new RuntimeException( $label ); } ++$checks; };
$expect_status = static function ( $fn, $status ) use ( $assert ) { try { $fn(); throw new RuntimeException( 'Expected guarded exit.' ); } catch ( RuntimeException $e ) { $assert( $status === $e->getCode(), 'Expected status ' . $status . ': ' . $e->getMessage() ); } };
$code_text = static function ( $html ) { preg_match( '~<pre\b[^>]*>(.*?)</pre>~s', $html, $m ); return html_entity_decode( strip_tags( $m[1] ?? '' ), ENT_QUOTES | ENT_HTML5, 'UTF-8' ); };
try {
	$code = "\tconst path = \"C:\\\\temp\\\\file\";\n\n  <script>alert('literal')</script>\nhttps://example.invalid/code\n&amp; &lt; 😀\n";
	$code_html = '<pre><code>' . htmlspecialchars( $code, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8' ) . '</code></pre>';
	foreach ( [ $code_html, '<div style="margin:900px"><p>Before</p>' . $code_html . '<p>After</p></div>' ] as $source ) {
		$clean = YoOhw_Support_Composer::normalize( $source );
		$assert( $code === $code_text( $clean ), 'Code characters, indentation, blank lines, entities and backslashes unchanged.' );
		$assert( $clean === YoOhw_Support_Composer::normalize( $clean ), 'Normalization is idempotent with protected code.' );
		$assert( false === strpos( $clean, '<script>' ) && false === strpos( $clean, 'href="https://example.invalid/code' ), 'Code never becomes executable markup or an autolink.' );
	}
	$fixtures = [
		'<div><span style="font-size:80px" class="MsoNormal">Hello</span></div><p>&nbsp;<br></p><p><br><br></p>' => [ 'Hello', 'style=', 'class="Mso' ],
		'<div hidden>HIDDEN</div><div style="display:none">HIDDEN</div><!-- HIDDEN --><p>Visible</p>' => [ 'Visible', 'HIDDEN', '<!--' ],
		'<h1 style="height:900px">Heading</h1><blockquote>Quote</blockquote>' => [ '<h3>Heading</h3>', '<h1', 'style=' ],
		'<ol start="4"><li value="8">One<ul><li>Nested</li></ul></li></ol>' => [ 'start="4"', 'style=', 'onclick' ],
		'<table style="width:9999px"><tr><th colspan="2">Header</th></tr><tr><td>A</td><td>B</td></tr></table>' => [ 'colspan="2"', 'style=', 'width=' ],
		'<pre hidden>HIDDEN</pre><code style="display:none">HIDDEN</code><p>Shown</p>' => [ 'Shown', 'HIDDEN', 'hidden' ],
		'<p><a href="https://example.invalid/" target="_blank">Safe</a></p>' => [ 'noopener noreferrer', 'onclick', 'style=' ],
		'<p>A<br><br><br><br>B</p>' => [ 'B', '<br><br><br>', 'style=' ],
		'<p><strong>broken</p>' => [ '<strong>broken</strong>', '<script', 'style=' ],
		'<p>Tiếng Việt 👩‍👩‍👧‍👦 &amp; हिन्दी</p>' => [ '👩‍👩‍👧‍👦', 'style=', 'onclick' ],
		'<p><a href="javascript:alert(1)" onclick="alert(1)">Link</a></p>' => [ 'Link', 'javascript:', 'onclick' ],
		'<p>Use `a  b` and https://example.invalid/page</p>' => [ '<code>a  b</code>', 'style=', '<code>a b</code>' ],
	];
	foreach ( $fixtures as $raw => $expected ) {
		$clean = YoOhw_Support_Composer::normalize( $raw );
		$assert( false !== strpos( $clean, $expected[0] ), 'Meaningful structure retained: ' . $expected[0] );
		$assert( false === strpos( $clean, $expected[1] ) && false === strpos( $clean, $expected[2] ), 'Presentation/unsafe structure removed.' );
		$assert( $clean === YoOhw_Support_Composer::normalize( $clean ), 'Repeated cleanup does not change prose structure: ' . $expected[0] );
	}
	foreach ( [ '<p>&nbsp;<br></p>', "\u{200B}\u{FEFF}", '<span hidden>hidden</span>', '<p><code> </code></p>' ] as $empty ) { $assert( is_wp_error( YoOhw_Support_Composer::prepare( $empty ) ), 'Visually empty rejected.' ); }
	foreach ( [ '<img src="https://example.invalid/pixel">', '<iframe src="https://example.invalid/embed"></iframe>', '<svg><text>x</text></svg>' ] as $media ) { $assert( 'yoohw_composer_media' === YoOhw_Support_Composer::prepare( '<p>Text</p>' . $media )->get_error_code(), 'Unsupported media explicitly rejected without JS.' ); }
	$fenced = "Before\n\n```php\n" . rtrim( $code, "\n" ) . "\n```\n\nAfter";
	$assert( rtrim( $code, "\n" ) === $code_text( YoOhw_Support_Composer::normalize( $fenced ) ), 'Plain fenced code preserves literal tags and spacing.' );
	$assert( "  one\n\n  two" === $code_text( YoOhw_Support_Composer::normalize( '<p>Before</p><p>```js</p><p>  one</p><p></p><p>  two</p><p>```</p><p>After</p>' ) ), 'Paragraph-split fences match client behavior.' );
	$assert( '  a  b' === $code_text( YoOhw_Support_Composer::normalize( '<pre>  a  b' ) ), 'Unclosed code block is safely closed without collapsing its whitespace.' );

	foreach ( [ '<code>literal</code>', '<pre><code>literal</code></pre>', "  <div>literal</div>\n\n  &amp; https://example.invalid/code" ] as $literal ) {
		$normalized = YoOhw_Support_Composer::normalize( "```html\n" . $literal . "\n```" );
		$assert( $literal === $code_text( $normalized ) && ! preg_match( '/YSPCODE[a-f0-9]{32}[0-9]+END/', $normalized ), 'Fenced HTML tags stay literal with no internal marker leakage.' );
		$assert( $normalized === YoOhw_Support_Composer::normalize( $normalized ), 'Fenced literal HTML remains stable on repeat cleanup.' );
	}
	foreach ( [ '<div>literal</div>', '<pre>literal</pre>', 'https://example.invalid/code' ] as $literal ) {
		$normalized = YoOhw_Support_Composer::normalize( 'Use `' . $literal . '` here' );
		$assert( false !== strpos( $normalized, '<code>' . htmlspecialchars( $literal, ENT_NOQUOTES | ENT_SUBSTITUTE, 'UTF-8' ) . '</code>' ), 'Inline literal HTML/URL protected before tag parsing.' );
	}
	$literal = "```html\n<code>literal</code>\n```";
	$assert( $literal === $code_text( YoOhw_Support_Composer::normalize( '<pre>' . htmlspecialchars( $literal, ENT_NOQUOTES | ENT_SUBSTITUTE, 'UTF-8' ) . '</pre>' ) ), 'Explicit pre protects its Markdown-looking contents.' );
	$normalized = YoOhw_Support_Composer::normalize( '<p><a href="https://example.invalid/" title="Use `code`">Link</a></p>' );
	$assert( false !== strpos( $normalized, 'title="Use `code`"' ) && false === strpos( $normalized, 'YSPCODE' ), 'Backticks in an HTML attribute are not code syntax.' );

	$category = wp_insert_term( $prefix, 'category' ); $assert( ! is_wp_error( $category ), 'Synthetic category.' ); $category = (int) $category['term_id'];
	foreach ( [ 'owner', 'other' ] as $who ) { $id = wp_insert_user( [ 'user_login' => $prefix . $who, 'user_email' => $prefix . $who . '@example.invalid', 'user_pass' => wp_generate_password(), 'role' => 'subscriber' ] ); $assert( ! is_wp_error( $id ), 'Synthetic user.' ); $users[$who] = $id; }
	wp_set_current_user( $users['owner'] ); $_SERVER['REQUEST_METHOD'] = 'POST'; $_FILES = [];
	$raw = '<div style="font-size:90px"><p>Before `inline  code`</p><p>&nbsp;</p>' . $code_html . '<p>After</p></div>';
	$expected = YoOhw_Support_Composer::prepare( $raw ); $assert( ! is_wp_error( $expected ), 'Submission sample valid.' );
	$legacy = wp_insert_post( wp_slash( [ 'post_title' => $prefix . '-old', 'post_content' => '<div style="margin:800px"><p>Historic</p><p>&nbsp;</p></div>', 'post_status' => 'publish', 'post_author' => $users['owner'] ] ) );
	$old_content = get_post( $legacy )->post_content;
	foreach ( [ 'wordpress', 'isolated' ] as $mode ) {
		$settings['dedicated_portal_mode'] = $mode;
		$_POST = wp_slash( [ 'yoohw_post_title' => $prefix . '-' . $mode, 'yoohw_post_category' => $category, 'yoohw_post_content' => $raw, 'yoohw_submit_post_nonce' => wp_create_nonce( 'yoohw_submit_post' ) ] );
		$_REQUEST = $_POST;
		$expect_status( [ 'YoOhw_Form_Handler', 'handle_post_submission' ], 302 );
		if ( 'wordpress' === $mode ) {
			$topic_id = (int) end( $posts ); $stored = get_post( $topic_id )->post_content;
		} else {
			global $wpdb;
			$topic_id = (int) $wpdb->get_var( $wpdb->prepare( 'SELECT id FROM ' . YoOhw_Support_Database::topics_table() . ' WHERE title = %s', $prefix . '-' . $mode ) );
			$isolated[] = $topic_id; $stored = YoOhw_Support_Database::get_topic_by_id( $topic_id )['content'];
		}
		$assert( $code === $code_text( $stored ), "$mode topic: code survives actual persistence." );
		$assert( false === strpos( $stored, 'style=' ) && false !== strpos( $stored, '<code>inline  code</code>' ), "$mode topic: normalized prose and inline code persisted." );
		if ( 'wordpress' === $mode ) {
			$_POST = [ 'yoohw_support_comment_nonce' => wp_create_nonce( 'yoohw_support_comment' ) ]; $_REQUEST = $_POST;
			$cid = wp_new_comment( wp_slash( [ 'comment_post_ID' => $topic_id, 'user_id' => $users['owner'], 'comment_author' => $prefix, 'comment_author_email' => $prefix . '@example.invalid', 'comment_content' => $raw, 'comment_author_IP' => '127.0.0.1' ] ), true );
			$assert( ! is_wp_error( $cid ), 'Actual native new reply created.' ); $comments[] = $cid; $reply = get_comment( $cid )->comment_content;
			$rendered = apply_filters( 'comment_text', $reply, get_comment( $cid ), [] );
		} else {
			$_POST = wp_slash( [ 'yoohw_isolated_reply' => '1', 'yoohw_isolated_reply_nonce' => wp_create_nonce( 'yoohw_isolated_reply' ), 'topic_id' => $topic_id, 'comment' => $raw ] ); $_REQUEST = $_POST;
			$expect_status( [ 'YoOhw_Support_Controller', 'handle_isolated_reply_submission' ], 302 );
			$rows = YoOhw_Support_Database::get_replies( $topic_id ); $reply = end( $rows )['content']; $rendered = apply_filters( 'the_content', $reply );
		}
		$assert( $code === $code_text( $reply ), "$mode reply: code survives actual persistence." );
		$assert( $code === $code_text( $rendered ) && false !== strpos( $rendered, '<code>inline  code</code>' ), "$mode reply: code survives actual display filters." );
		wp_set_current_user( $users['other'] );
		if ( 'wordpress' === $mode ) {
			$expect_status( static function () use ( $topic_id ) { YoOhw_Comment_Editor::authorize_support_comment( [ 'comment_post_ID' => $topic_id ] ); }, 403 );
		} else { $expect_status( [ 'YoOhw_Support_Controller', 'handle_isolated_reply_submission' ], 403 ); }
		wp_set_current_user( $users['owner'] );
		if ( 'wordpress' === $mode ) {
			$_POST = [ 'yoohw_support_comment_nonce' => 'invalid' ]; $_REQUEST = $_POST;
			$expect_status( static function () use ( $topic_id ) { YoOhw_Comment_Editor::authorize_support_comment( [ 'comment_post_ID' => $topic_id ] ); }, 403 );
		} else {
			$_POST['yoohw_isolated_reply_nonce'] = 'invalid'; $_REQUEST = $_POST;
			$expect_status( [ 'YoOhw_Support_Controller', 'handle_isolated_reply_submission' ], 403 );
		}
		foreach ( [ '<p>&nbsp;<br></p>', '<p>Text</p><img src="https://example.invalid/pixel">' ] as $bad_content ) {
			$_POST = wp_slash( [ 'yoohw_post_title' => $prefix . '-rejected', 'yoohw_post_category' => $category, 'yoohw_post_content' => $bad_content, 'yoohw_submit_post_nonce' => wp_create_nonce( 'yoohw_submit_post' ) ] ); $_REQUEST = $_POST;
			$expect_status( [ 'YoOhw_Form_Handler', 'handle_post_submission' ], 400 );
		}
		$_POST = [ 'yoohw_post_title' => $prefix, 'yoohw_post_category' => $category, 'yoohw_post_content' => $raw ]; $_REQUEST = $_POST;
		$expect_status( [ 'YoOhw_Form_Handler', 'handle_post_submission' ], 403 );
	}
	$assert( $old_content === get_post( $legacy )->post_content, 'Historic content remains byte-for-byte unchanged.' );
	$assert( false !== strpos( YoOhw_Support_Composer::editor_settings( 'comment' )['tinymce']['toolbar1'], 'yoohw_support_inlinecode' ), 'Shared settings include inline-code action.' );
	WP_CLI::log( "composer-ok: $checks checks; synthetic native/isolated submissions, intercepted mail" );
} finally {
	global $wpdb;
	foreach ( $comments as $id ) { wp_delete_comment( $id, true ); }
	foreach ( $posts as $id ) { wp_delete_post( $id, true ); }
	foreach ( $isolated as $id ) { $wpdb->delete( YoOhw_Support_Database::replies_table(), [ 'topic_id' => $id ], [ '%d' ] ); $wpdb->delete( YoOhw_Support_Database::topics_table(), [ 'id' => $id ], [ '%d' ] ); }
	if ( $category ) { wp_delete_term( $category, 'category' ); }
	wp_set_current_user( $original_user ); foreach ( $users as $id ) { wp_delete_user( $id ); }
	$_POST = $original_post; $_REQUEST = $original_request; $_SERVER['REQUEST_METHOD'] = $original_method; $_FILES = $original_files;
	remove_filter( 'pre_option_' . YoOhw_Support_Settings::OPTION_NAME, $settings_read ); remove_filter( 'pre_wp_mail', $mail, PHP_INT_MAX ); remove_filter( 'wp_redirect', $redirect, -999 ); remove_filter( 'wp_die_handler', $die, 999 ); remove_action( 'wp_insert_post', $track, 10 );
}
