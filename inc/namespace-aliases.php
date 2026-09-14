<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Public namespace facade for new integrations.
 *
 * The legacy global classes remain in place for backward compatibility with
 * existing installs, stored callbacks, snippets, and add-ons.
 */
$yoohw_support_portal_aliases = [
	'Plugin'              => 'YoOhw_Support_Center',
	'Router'              => 'YoOhw_Support_Router',
	'Controller'          => 'YoOhw_Support_Controller',
	'Assets'              => 'YoOhw_Support_Assets',
	'Settings'            => 'YoOhw_Support_Settings',
	'SiteCheck'           => 'YoOhw_Support_Site_Check',
	'Icons'               => 'YoOhw_Support_Icons',
	'Shortcode'           => 'YoOhw_Shortcode',
	'FormHandler'         => 'YoOhw_Form_Handler',
	'LoginForm'           => 'YoOhw_Login_Form',
	'LostPasswordForm'    => 'YoOhw_Lost_Password_Form',
	'CommentEditor'       => 'YoOhw_Comment_Editor',
	'LatestCommentBubble' => 'YoOhw_Latest_Comment_Bubble',
	'PostRestriction'     => 'YoOhw_Post_Restriction',
	'ResolvedPostStatus'  => 'YoOhw_Post_Status_Resolved',
	'AttachmentCleanup'   => 'YoOhw_Attachment_Cleanup',
	'EmailNotification'   => 'YoOhw_Email_Notification',
];

foreach ( $yoohw_support_portal_aliases as $yoohw_support_alias => $yoohw_support_legacy_class ) {
	$yoohw_support_namespaced_class = 'YoOhwSupportPortal\\' . $yoohw_support_alias;

	if ( class_exists( $yoohw_support_legacy_class ) && ! class_exists( $yoohw_support_namespaced_class ) ) {
		class_alias( $yoohw_support_legacy_class, $yoohw_support_namespaced_class );
	}
}

unset( $yoohw_support_alias, $yoohw_support_legacy_class, $yoohw_support_namespaced_class, $yoohw_support_portal_aliases );
