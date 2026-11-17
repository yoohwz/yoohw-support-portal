<?php
/** Synthetic template characterization; no WordPress bootstrap, DB or network. */
define( 'ABSPATH', __DIR__ );
$GLOBALS['topic_action_listeners'] = [];
function do_action( $name, ...$args ) { foreach ( $GLOBALS['topic_action_listeners'][ $name ] ?? [] as $callback ) { $callback( ...$args ); } }
function setup_postdata( $post ) {}
function wp_reset_postdata() {}
function get_userdata( $id ) { return $id ? (object) [ 'ID' => $id, 'display_name' => 'User ' . $id, 'user_login' => 'user-' . $id ] : false; }
function __( $text, $domain = '' ) { return $text; }
function esc_html__( $text, $domain = '' ) { return esc_html( $text ); }
function esc_html( $text ) { return htmlspecialchars( (string) $text, ENT_QUOTES ); }
function esc_attr( $text ) { return esc_html( $text ); }
function esc_url( $text ) { return esc_html( $text ); }
function esc_attr_e( $text, $domain = '' ) { echo esc_attr( $text ); }
function esc_html_e( $text, $domain = '' ) { echo esc_html( $text ); }
function apply_filters( $name, $value, ...$args ) { return $value; }
function wp_strip_all_tags( $text ) { return strip_tags( $text ); }
function current_user_can( $cap ) { return false; }
function user_can( $id, $cap ) { return 7 === $id; }
function wp_kses( $text, $allowed ) { return $text; }
function get_the_date( $format, $post ) { return '2026-01-01'; }
function _n( $one, $many, $count, $domain ) { return 1 === $count ? $one : $many; }
function absint( $value ) { return abs( (int) $value ); }
function get_comment_author( $comment ) { return $comment->comment_author; }
function get_current_user_id() { return 7; }
function get_comment_date( $format, $comment ) { return '2026-01-01'; }
function mysql2date( $format, $date ) { return '12:00'; }
function get_option( $name ) { return ''; }
function comments_open( $id ) { return false; }
class YoOhw_Support_Controller {
	static function topic_status_class( $post ) { return 'is-open'; }
	static function topic_status_label( $post ) { return 'Open'; }
	static function output_topic_category_meta( $post ) {}
	static function render_template( $name ) { return ''; }
}
class YoOhw_Support_Settings {
	static function reply_side_labels() { return [ 'support' => 'Support', 'customer' => 'Customer' ]; }
	static function wordpress_reply_avatars_enabled() { return false; }
}
class YoOhw_Support_Capabilities { const VIEW_CUSTOMERS = 'view-customers'; const MANAGE_TOPICS = 'manage-topics'; }
class YoOhw_Support_Date_Time { static function output_timezone_note() {} static function output( ...$args ) {} }
class YoOhw_Support_Icons { static function output( $icon ) {} }
class YoOhw_Support_Router { static function url( $view ) { return '/topics/'; } }
$checks = 0;
function check( $condition, $message ) {
	global $checks;
	if ( ! $condition ) { throw new RuntimeException( $message ); }
	++$checks;
}
function render_fixture( $source, $post, $comments ) {
	ob_start();
	eval( '?>' . $source );
	return ob_get_clean();
}
$root = dirname( __DIR__ );
$source = file_get_contents( $root . '/templates/topic-single.php' );
$post = (object) [ 'ID' => 101, 'post_author' => 3, 'post_title' => 'Synthetic topic', 'post_content' => 'Synthetic body', 'post_date_gmt' => '2026-01-01 00:00:00' ];
$comments = [];
foreach ( [ 3, 7, 0 ] as $index => $author ) {
	$comments[] = (object) [ 'comment_ID' => 201 + $index, 'comment_post_ID' => 101, 'user_id' => $author, 'comment_author' => 'Author ' . $author, 'comment_approved' => '1', 'comment_content' => 'Synthetic reply ' . $index, 'comment_date_gmt' => '2026-01-01 12:00:00', 'comment_date' => '2026-01-01 12:00:00' ];
}
$baseline = preg_replace( '/^\h*<\?php do_action\( \'yoohw_support_(?:topic|reply)_actions\'.*?\?>\R/m', '', $source );
$normalize = static function ( $html ) { return preg_replace( '/>\s+</', '><', trim( $html ) ); };
check( $normalize( render_fixture( $source, $post, $comments ) ) === $normalize( render_fixture( $baseline, $post, $comments ) ), 'No listeners: native output unchanged except whitespace.' );
$seen = [];
$GLOBALS['topic_action_listeners']['yoohw_support_topic_actions'][] = static function ( $topic ) use ( &$seen ) { $seen[] = [ 'topic', $topic ]; echo '<span data-topic-action="1"></span>'; };
$GLOBALS['topic_action_listeners']['yoohw_support_reply_actions'][] = static function ( $reply, $topic ) use ( &$seen ) { $seen[] = [ 'reply', $reply, $topic ]; echo '<span data-reply-action="' . esc_attr( $reply->comment_ID ) . '"></span>'; };
$html = render_fixture( $source, $post, $comments );
check( count( $seen ) === 4 && $seen[0] === [ 'topic', $post ], 'Topic hook runs once with the exact topic object before replies.' );
foreach ( $comments as $index => $reply ) {
	check( $seen[ $index + 1 ] === [ 'reply', $reply, $post ], 'Reply hook receives the exact reply and topic, including staff and guest aliases.' );
	check( false !== strpos( $html, 'data-reply-action="' . $reply->comment_ID . '"' ), 'Each source can render its own action.' );
}
check( 1 === substr_count( $html, 'data-topic-action="1"' ), 'Topic action is rendered once.' );
check( preg_match( '/Synthetic body.*?data-topic-action="1".*?<\/article>/s', $html ), 'Topic action stays inside its article after the body.' );
check( preg_match( '/yoohw-reply-content.*?Synthetic reply 0.*?data-reply-action="201".*?<\/article>/s', $html ), 'Reply action stays inside its bubble after its content.' );
$isolated = file_get_contents( $root . '/templates/topic-single-isolated.php' );
check( false === strpos( $isolated, 'yoohw_support_topic_actions' ) && false === strpos( $isolated, 'yoohw_support_reply_actions' ), 'Isolated storage exposes no native source action hook.' );
$controller = file_get_contents( $root . '/inc/class-yoohw-support-controller.php' );
$single = substr( $controller, strpos( $controller, 'public static function render_topic_single()' ) );
$single = strstr( $single, 'public static function render_author_profile()', true );
check( strpos( $single, "'topic-single-isolated'" ) < strpos( $single, '$post      = self::resolve_topic' ), 'Isolated mode returns through its independent renderer.' );
check( strpos( $single, 'current_user_can_view_topic( $post )' ) < strpos( $single, '$comments = get_comments' ), 'Native authorization precedes comment loading and rendering.' );
check( false !== strpos( $single, "'status'  => 'approve'" ) && false !== strpos( $single, "'post_id' => \$post->ID" ), 'Native route supplies only approved replies from this topic.' );
echo "topic-action-hooks-ok: $checks checks (synthetic template/source characterization)\n";
