<?php
/** Run on the Local site: wp eval-file tests/private-storage-tests.php. Synthetic data only. */
if ( ! defined( 'WP_CLI' ) || ! WP_CLI || 'local' !== wp_get_environment_type() || YoOhw_Support_Settings::uses_isolated_storage() ) {
	throw new RuntimeException( 'Private storage tests require Local WordPress posts/comments mode.' );
}
require_once ABSPATH . 'wp-admin/includes/media.php';
require_once ABSPATH . 'wp-admin/includes/image.php';
require_once ABSPATH . 'wp-admin/includes/file.php';
require_once ABSPATH . 'wp-admin/includes/user.php';
add_filter( 'pre_wp_mail', '__return_true' );
$prefix = 'ysp19-storage-' . wp_generate_password( 8, false, false );
$users = $attachments = $temporary = $journals = [];
$post = 0; $checks = 0;
$original_user = get_current_user_id(); $original_cookie = $_COOKIE[ LOGGED_IN_COOKIE ] ?? null;
$original_error = get_option( YoOhw_Support_Private_Storage::ERROR_OPTION, false );
$assert = static function ( $condition, $message ) use ( &$checks ) { if ( ! $condition ) { throw new RuntimeException( $message ); } ++$checks; };
$request = static function ( $url, $cookie = '', $extra = [] ) {
	$response = wp_remote_get( $url, [ 'sslverify' => false, 'timeout' => 10, 'redirection' => 0, 'headers' => array_merge( [ 'Cookie' => $cookie ], $extra ) ] );
	if ( is_wp_error( $response ) ) { throw new RuntimeException( $response->get_error_message() ); }
	return [ wp_remote_retrieve_response_code( $response ), wp_remote_retrieve_body( $response ) ];
};
$session = static function ( $user ) {
	wp_set_current_user( $user );
	if ( ! $user ) { unset( $_COOKIE[ LOGGED_IN_COOKIE ] ); return ''; }
	$expiry = time() + HOUR_IN_SECONDS; $token = WP_Session_Tokens::get_instance( $user )->create( $expiry );
	$_COOKIE[ LOGGED_IN_COOKIE ] = wp_generate_auth_cookie( $user, $expiry, 'logged_in', $token );
	return AUTH_COOKIE . '=' . wp_generate_auth_cookie( $user, $expiry, 'auth', $token ) . '; ' . SECURE_AUTH_COOKIE . '=' . wp_generate_auth_cookie( $user, $expiry, 'secure_auth', $token ) . '; ' . LOGGED_IN_COOKIE . '=' . $_COOKIE[ LOGGED_IN_COOKIE ];
};
$create = static function ( $private, $parent ) use ( &$attachments, &$temporary, $prefix, $assert ) {
	$file = wp_tempnam( $prefix . '.png' ); $temporary[] = $file;
	$image = imagecreatetruecolor( 440, 320 ); imagefill( $image, 0, 0, imagecolorallocate( $image, 31, 82, 149 ) ); imagepng( $image, $file ); imagedestroy( $image );
	if ( $private ) { YoOhw_Protected_Attachments::begin_private_upload(); }
	try { $id = media_handle_sideload( [ 'name' => $prefix . '-' . wp_generate_password( 6, false, false ) . '.png', 'tmp_name' => $file ], $parent ); }
	finally { if ( $private ) { YoOhw_Protected_Attachments::end_private_upload(); } }
	$assert( ! is_wp_error( $id ), 'Create synthetic image and core derivatives.' ); $attachments[] = $id;
	if ( $parent ) { YoOhw_Protected_Attachments::protect( $id ); $ids = (array) get_post_meta( $parent, YoOhw_Protected_Attachments::META_IDS, true ); $ids[] = $id; update_post_meta( $parent, YoOhw_Protected_Attachments::META_IDS, array_filter( $ids ) ); }
	return $id;
};
try {
	$root = YoOhw_Support_Private_Storage::verify( true ); $assert( ! is_wp_error( $root ), 'Filesystem and HTTP storage verification.' );
	foreach ( [ 'owner' => 'contributor', 'other' => 'contributor', 'staff' => 'administrator' ] as $name => $role ) {
		$id = wp_insert_user( [ 'user_login' => $prefix . '-' . $name, 'user_email' => $prefix . '-' . $name . '@example.invalid', 'user_pass' => wp_generate_password(), 'role' => $role ] );
		$assert( ! is_wp_error( $id ), 'Create synthetic viewer.' ); $users[ $name ] = $id;
	}
	$post = wp_insert_post( [ 'post_title' => $prefix, 'post_content' => 'Synthetic storage topic.', 'post_status' => 'publish', 'post_author' => $users['owner'] ] );
	$public = $create( false, 0 ); $public_file = get_attached_file( $public ); $public_url = wp_get_attachment_url( $public );
	$new = $create( true, $post ); $legacy = $create( false, $post );
	$public_root = wp_get_upload_dir(); $old_file = get_attached_file( $legacy ); $old_metadata = wp_get_attachment_metadata( $legacy );
	$old_variant = dirname( $old_file ) . '/' . $old_metadata['sizes']['thumbnail']['file'];
	$old_urls = [ str_replace( $public_root['basedir'], $public_root['baseurl'], $old_file ), str_replace( $public_root['basedir'], $public_root['baseurl'], $old_variant ) ];
	foreach ( $old_urls as $url ) { [ $status ] = $request( $url ); $assert( 200 === $status, 'Synthetic legacy exposure exists before explicit relocation.' ); }
	$hash = hash_file( 'sha256', $old_file ); $variant_hash = hash_file( 'sha256', $old_variant );
	$before = get_post_meta( $legacy, '_wp_attached_file', true );
	$journals[] = $legacy; $stage_result = YoOhw_Support_Private_Storage::secure( $legacy, true );
	$assert( 'staged-not-secured' === $stage_result, 'Stage recoverable copies: ' . ( is_wp_error( $stage_result ) ? $stage_result->get_error_message() : $stage_result ) );
	$assert( get_post_meta( $legacy, '_wp_attached_file', true ) === $before && is_file( $old_file ) && is_file( $old_variant ), 'Staging leaves original links and bytes intact.' );
	$journal = get_post_meta( $legacy, YoOhw_Support_Private_Storage::JOURNAL, true );
	$destination = $root . '/relocated-' . $legacy . '/files/' . basename( $old_file );
	file_put_contents( $destination, 'synthetic conflicting target' );
	$assert( is_wp_error( YoOhw_Support_Private_Storage::secure( $legacy ) ), 'Target conflict fails before source retirement.' );
	$assert( is_file( $old_file ) && get_post_meta( $legacy, '_wp_attached_file', true ) === $before, 'Conflict preserves existing attachment.' );
	copy( $root . '/relocated-' . $legacy . '/backup/' . basename( $old_file ), $destination );
	$fail_metadata = static function ( $value, $object, $key ) use ( $legacy ) { return $legacy === $object && '_wp_attached_file' === $key ? false : $value; };
	add_filter( 'update_post_metadata', $fail_metadata, 10, 3 );
	try { $assert( is_wp_error( YoOhw_Support_Private_Storage::secure( $legacy ) ), 'Failed metadata commit is not reported secured.' ); }
	finally { remove_filter( 'update_post_metadata', $fail_metadata, 10 ); }
	$assert( is_file( $old_file ) && is_file( $old_variant ), 'Metadata failure preserves all public source bytes for recovery.' );
	$assert( 'secured' === YoOhw_Support_Private_Storage::secure( $legacy ), 'Retry completes relocation.' );
	$assert( ! file_exists( $old_file ) && ! file_exists( $old_variant ), 'No residual public original or derivative.' );
	$assert( hash_file( 'sha256', get_attached_file( $legacy ) ) === $hash, 'Original hash preserved.' );
	$assert( hash_file( 'sha256', dirname( get_attached_file( $legacy ) ) . '/' . $old_metadata['sizes']['thumbnail']['file'] ) === $variant_hash, 'Variant hash preserved.' );
	$assert( 'secured' === YoOhw_Support_Private_Storage::secure( $legacy ), 'Completed relocation is safely repeatable.' );
	foreach ( $old_urls as $url ) { [ $status, $body ] = $request( $url ); $assert( in_array( $status, [ 301, 302, 403, 404 ], true ) && ! in_array( hash( 'sha256', $body ), [ $hash, $variant_hash ], true ), 'Anonymous old original/variant URL returns no file bytes.' ); }
	// Resume after an interruption between metadata commit and retirement of the last public variant.
	$partial = $create( false, $post ); $partial_file = get_attached_file( $partial );
	$assert( 'staged-not-secured' === YoOhw_Support_Private_Storage::secure( $partial, true ), 'Stage interruption fixture.' ); $journals[] = $partial;
	$partial_meta = wp_get_attachment_metadata( $partial ); $target = $root . '/relocated-' . $partial . '/files/' . basename( $partial_file );
	update_post_meta( $partial, '_wp_attached_file', $target ); $partial_meta['file'] = $target; update_post_meta( $partial, '_wp_attachment_metadata', $partial_meta ); unlink( $partial_file );
	$assert( 'secured' === YoOhw_Support_Private_Storage::secure( $partial ), 'Resume partial source retirement from hash-verified backup.' );
	foreach ( [ $new, $legacy ] as $id ) {
		$file = get_attached_file( $id ); $meta = wp_get_attachment_metadata( $id );
		$assert( 0 === strpos( $file, $root . '/' ) && is_file( $file ), 'WordPress resolves private absolute file metadata.' );
		foreach ( [ '' => $file, 'thumbnail' => dirname( $file ) . '/' . $meta['sizes']['thumbnail']['file'] ] as $size => $member ) {
			$expected = file_get_contents( $member );
			foreach ( [ 'guest' => 0 ] + $users as $viewer => $user ) {
				$cookie = $session( $user ); [ $status, $body ] = $request( html_entity_decode( YoOhw_Protected_Attachments::url( $id, $size ) ), $cookie );
				$allowed = in_array( $viewer, [ 'owner', 'staff' ], true );
				$assert( $allowed ? 200 === $status && $body === $expected : 403 === $status && $body !== $expected, 'HTTP protected original/preview ACL: ' . $viewer );
			}
			$cookie = $session( $users['owner'] ); [ $status, $body ] = $request( html_entity_decode( YoOhw_Protected_Attachments::url( $id, $size ) ), $cookie, [ 'Range' => 'bytes=2-9' ] );
			$assert( 206 === $status && substr( $expected, 2, 8 ) === $body, 'Protected range delivery retains correct bytes.' );
			$url = home_url( '/yoohw-support-private/' . basename( $root ) . str_replace( $root, '', $member ) );
			[ $status, $body ] = $request( $url ); $assert( in_array( $status, [ 301, 302, 403, 404 ], true ) && $body !== $expected, 'Anonymous new/private original/variant URL is not served.' );
		}
	}
	[ $status, $body ] = $request( $public_url ); $assert( 200 === $status && hash( 'sha256', $body ) === hash_file( 'sha256', $public_file ), 'Unrelated public media remains public.' );
	$icon = static function () use ( $public ) { return $public; }; add_filter( 'pre_option_site_icon', $icon );
	try { $assert( is_wp_error( YoOhw_Support_Private_Storage::secure( $public ) ), 'Site icon is excluded from relocation.' ); }
	finally { remove_filter( 'pre_option_site_icon', $icon ); }
	$shared = $create( false, $post ); $shared_file = get_attached_file( $shared );
	$duplicate = wp_insert_attachment( [ 'post_title' => $prefix . '-unrelated-shared', 'post_mime_type' => 'image/png', 'post_status' => 'inherit' ] ); $attachments[] = $duplicate;
	update_attached_file( $duplicate, $shared_file ); wp_update_attachment_metadata( $duplicate, wp_get_attachment_metadata( $shared ) ); $journals[] = $shared;
	$assert( is_wp_error( YoOhw_Support_Private_Storage::secure( $shared ) ) && is_file( $shared_file ), 'Shared unrelated media are not retired.' );
	delete_post_meta( $duplicate, '_wp_attached_file' ); delete_post_meta( $duplicate, '_wp_attachment_metadata' );
	$assert( 'secured' === YoOhw_Support_Private_Storage::secure( $shared ), 'Retry after resolving shared-media ownership.' );
	$bad_meta = wp_get_attachment_metadata( $new ); $saved_meta = $bad_meta; $bad_meta['sizes']['thumbnail']['file'] = '../outside.png'; wp_update_attachment_metadata( $new, $bad_meta );
	try {
		$cookie = $session( $users['owner'] ); [ $status ] = $request( html_entity_decode( YoOhw_Protected_Attachments::url( $new, 'thumbnail' ) ), $cookie );
		$assert( 404 === $status, 'Unsafe preview variant path is rejected.' );
	} finally { wp_update_attachment_metadata( $new, $saved_meta ); }
	$document_root = $_SERVER['DOCUMENT_ROOT']; $working_directory = getcwd(); chdir( ABSPATH );
	try {
		unset( $_SERVER['DOCUMENT_ROOT'] );
		$assert( is_wp_error( YoOhw_Support_Private_Storage::root() ) && is_wp_error( YoOhw_Support_Private_Storage::verify( true ) ), 'Absent root is rejected even when cwd is WordPress root.' );
		foreach ( [ '', '.', '/' ] as $invalid_root ) {
			$_SERVER['DOCUMENT_ROOT'] = $invalid_root;
			$assert( is_wp_error( YoOhw_Support_Private_Storage::root() ) && is_wp_error( YoOhw_Support_Private_Storage::verify( true ) ), 'Empty, relative and filesystem-root document roots fail closed.' );
		}
	} finally { $_SERVER['DOCUMENT_ROOT'] = $document_root; chdir( $working_directory ); }
	$_SERVER['DOCUMENT_ROOT'] = '/nonexistent-ysp19-root';
	try {
		$assert( is_wp_error( YoOhw_Support_Private_Storage::root() ), 'Unknown HTTP root fails closed.' );
		$die = static function () { return static function () { throw new RuntimeException( 'synthetic storage denial' ); }; };
		add_filter( 'wp_die_handler', $die ); $denied = false;
		YoOhw_Protected_Attachments::begin_private_upload();
		try { wp_upload_dir(); } catch ( RuntimeException $error ) { $denied = 'synthetic storage denial' === $error->getMessage(); }
		finally { YoOhw_Protected_Attachments::end_private_upload(); remove_filter( 'wp_die_handler', $die ); }
		$assert( $denied, 'Core media API stops before a cached public path can overwrite the error.' );
	} finally { $_SERVER['DOCUMENT_ROOT'] = $document_root; }
	$http_failure = static function () { return new WP_Error( 'synthetic_failure', 'Synthetic loopback unavailable.' ); };
	add_filter( 'pre_http_request', $http_failure );
	try { $assert( is_wp_error( YoOhw_Support_Private_Storage::verify( true ) ), 'Inaccessible verification HTTP fails closed.' ); }
	finally { remove_filter( 'pre_http_request', $http_failure ); }
	$http_leak = static function () use ( $root ) { $probes = glob( $root . '/ysp-storage-probe-*.png' ); return [ 'response' => [ 'code' => 200 ], 'body' => file_get_contents( $probes[0] ) ]; };
	add_filter( 'pre_http_request', $http_leak );
	try { $assert( is_wp_error( YoOhw_Support_Private_Storage::verify( true ) ), 'A public mapping exposing canary bytes fails closed.' ); }
	finally { remove_filter( 'pre_http_request', $http_leak ); }
	$assert( ! is_wp_error( YoOhw_Support_Private_Storage::verify( true ) ), 'Storage verification recovers after transient failure.' );
	echo 'private-storage-ok: ' . $checks . ' checks; actual HTTP original/variant/ACL/range, relocation failures and resume' . PHP_EOL;
} finally {
	wp_set_current_user( $original_user );
	if ( null === $original_cookie ) { unset( $_COOKIE[ LOGGED_IN_COOKIE ] ); } else { $_COOKIE[ LOGGED_IN_COOKIE ] = $original_cookie; }
	foreach ( $attachments as $id ) { wp_delete_attachment( $id, true ); }
	if ( $post ) { wp_delete_post( $post, true ); }
	foreach ( $users as $user ) { wp_delete_user( $user ); }
	foreach ( $temporary as $path ) { if ( is_file( $path ) ) { unlink( $path ); } }
	// Remove only the explicit synthetic relocation directories, including their private recovery copies.
	foreach ( $journals as $id ) {
		$directory = $root . '/relocated-' . $id;
		foreach ( [ 'backup', 'files' ] as $folder ) { if ( is_dir( $directory . '/' . $folder ) ) { foreach ( glob( $directory . '/' . $folder . '/*' ) as $path ) { unlink( $path ); } rmdir( $directory . '/' . $folder ); } }
		unlink( $directory . '/lock' ); rmdir( $directory );
	}
	if ( false === $original_error ) { delete_option( YoOhw_Support_Private_Storage::ERROR_OPTION ); } else { update_option( YoOhw_Support_Private_Storage::ERROR_OPTION, $original_error ); }
}
