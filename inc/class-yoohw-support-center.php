<?php

if (!defined('ABSPATH')) {
    exit;
}

class YoOhw_Support_Center {
    private static $instance = null;

    private function __construct() {
        $this->include_core();
        $this->init_core_hooks();

        if ( YoOhw_Support_Settings::dedicated_portal_mode_enabled() ) {
            $this->include_runtime();
            $this->init_runtime_hooks();
            $this->hide_admin_bar_for_subscribers();
        }

        $this->include_namespace_aliases();
    }

    public static function get_instance() {
        if (self::$instance == null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function include_core() {
        include_once YOOHW_SUPPORT_PORTAL_PATH . 'inc/class-yoohw-support-capabilities.php';
        include_once YOOHW_SUPPORT_PORTAL_PATH . 'inc/class-yoohw-support-privacy.php';
        include_once YOOHW_SUPPORT_PORTAL_PATH . 'inc/class-yoohw-support-setup-wizard.php';
        include_once YOOHW_SUPPORT_PORTAL_PATH . 'inc/class-yoohw-support-router.php';
        include_once YOOHW_SUPPORT_PORTAL_PATH . 'inc/class-yoohw-support-settings.php';
        include_once YOOHW_SUPPORT_PORTAL_PATH . 'inc/class-yoohw-support-database.php';
        include_once YOOHW_SUPPORT_PORTAL_PATH . 'inc/class-yoohw-support-site-check.php';
    }

    private function include_runtime() {
        include_once YOOHW_SUPPORT_PORTAL_PATH . 'inc/class-yoohw-support-icons.php';
        include_once YOOHW_SUPPORT_PORTAL_PATH . 'inc/class-yoohw-support-assets.php';
        include_once YOOHW_SUPPORT_PORTAL_PATH . 'inc/class-yoohw-support-controller.php';
        include_once YOOHW_SUPPORT_PORTAL_PATH . 'inc/class-yoohw-support-rest-security.php';
        include_once YOOHW_SUPPORT_PORTAL_PATH . 'inc/class-yoohw-category-access-code.php';
        include_once YOOHW_SUPPORT_PORTAL_PATH . 'inc/class-yoohw-form-handler.php';
        include_once YOOHW_SUPPORT_PORTAL_PATH . 'inc/class-yoohw-shortcode.php';
        include_once YOOHW_SUPPORT_PORTAL_PATH . 'inc/class-yoohw-login-form.php';
        include_once YOOHW_SUPPORT_PORTAL_PATH . 'inc/class-yoohw-lost-password-form.php';

        if ( ! YoOhw_Support_Settings::uses_isolated_storage() ) {
            include_once YOOHW_SUPPORT_PORTAL_PATH . 'inc/class-yoohw-comment-editor.php';
            include_once YOOHW_SUPPORT_PORTAL_PATH . 'inc/class-yoohw-comment-bubble.php';
            include_once YOOHW_SUPPORT_PORTAL_PATH . 'inc/class-yoohw-post-restriction.php';
            include_once YOOHW_SUPPORT_PORTAL_PATH . 'inc/class-yoohw-post-status.php';
            include_once YOOHW_SUPPORT_PORTAL_PATH . 'inc/class-yoohw-protected-attachments.php';
            include_once YOOHW_SUPPORT_PORTAL_PATH . 'inc/class-yoohw-attachment-cleanup.php';
            include_once YOOHW_SUPPORT_PORTAL_PATH . 'inc/class-yoohw-email-notification.php';
        }
    }

    private function include_namespace_aliases() {
        include_once YOOHW_SUPPORT_PORTAL_PATH . 'inc/namespace-aliases.php';
    }

    private function init_core_hooks() {
        YoOhw_Support_Capabilities::init();
        YoOhw_Support_Privacy::init();
        YoOhw_Support_Setup_Wizard::init();
        YoOhw_Support_Settings::init();
        YoOhw_Support_Site_Check::init();
    }

    private function init_runtime_hooks() {
        YoOhw_Support_Router::init();
        YoOhw_Support_Assets::init();
        YoOhw_Support_REST_Security::init();
        if ( class_exists( 'YoOhw_Protected_Attachments' ) ) {
            YoOhw_Protected_Attachments::init();
        }
        add_action( 'template_redirect', [ 'YoOhw_Support_Controller', 'handle_auth_submissions' ], 0 );
        add_action( 'init', [ 'YoOhw_Support_Controller', 'handle_isolated_reply_submission' ], 0 );
        add_action( 'login_form_rp', [ 'YoOhw_Support_Controller', 'redirect_wp_reset_password' ] );
        add_action( 'login_form_resetpass', [ 'YoOhw_Support_Controller', 'redirect_wp_reset_password' ] );
        add_filter( 'retrieve_password_message', [ 'YoOhw_Support_Controller', 'replace_reset_password_url' ], 20, 4 );
        add_action('init', array('YoOhw_Form_Handler', 'handle_post_submission'));
        add_shortcode('yoohw_post_form', array('YoOhw_Shortcode', 'render_form'));
        add_shortcode('yoohw_login_form', array('YoOhw_Login_Form', 'render_login_form'));
        add_shortcode('yoohw_lost_password_form', array('YoOhw_Lost_Password_Form', 'render_lost_password_form'));
        add_filter( 'the_content', [ 'YoOhw_Shortcode', 'append_post_attachments_to_content' ], 20 );

        if ( class_exists( 'YoOhw_Post_Restriction' ) ) {
            add_action('pre_get_posts', array('YoOhw_Post_Restriction', 'restrict_posts_to_author'));
        }

    }

    public function enqueue_styles() {
        YoOhw_Support_Assets::enqueue_frontend();
    }

    private function hide_admin_bar_for_subscribers() {
        if (is_user_logged_in()) {
            $user = wp_get_current_user();
            if (in_array('subscriber', (array) $user->roles)) {
                add_filter('show_admin_bar', '__return_false');
            }
        }
    }
}
