<?php

if (!defined('ABSPATH')) {
    exit;
}

class YoOhw_Lost_Password_Form {
    public static function render_lost_password_form() {
        if ( class_exists( 'YoOhw_Support_Controller' ) ) {
            return YoOhw_Support_Controller::render_lost_password_form();
        }

        if (is_user_logged_in()) {
            return '<p>' . esc_html__( 'You are already logged in.', 'yoohw-support-portal' ) . '</p>';
        } else {
            ob_start();
            $message = '';
            if (
                isset( $_POST['yoohw_lost_password_nonce'], $_POST['user_login'] ) &&
                wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['yoohw_lost_password_nonce'] ) ), 'yoohw_lost_password' ) &&
                ! empty( $_POST['user_login'] )
            ) {
                $user_login = sanitize_text_field( wp_unslash( $_POST['user_login'] ) );
                $user_data = get_user_by('email', $user_login);
                if (!$user_data) {
                    $user_data = get_user_by('login', $user_login);
                }

                if ($user_data) {
                    $reset_key = get_password_reset_key($user_data);
                    $reset_url = class_exists( 'YoOhw_Support_Controller' )
                        ? YoOhw_Support_Controller::reset_password_url( $user_data->user_login, $reset_key )
                        : add_query_arg(
                            [
                                'login' => $user_data->user_login,
                                'key'   => $reset_key,
                            ],
                            home_url( '/reset-password/' )
                        );
                    $email_message = __( 'To reset your password, visit the following address:', 'yoohw-support-portal' ) . "\n\n";
                    $email_message .= $reset_url . "\n\n";
                    if (is_multisite()) {
                        $blogname = $GLOBALS['current_site']->site_name;
                    } else {
                        $blogname = wp_specialchars_decode(get_option('blogname'), ENT_QUOTES);
                    }
                    /* translators: %s: Site name. */
                    $title = sprintf( __( '[%s] Password Reset', 'yoohw-support-portal' ), $blogname );
                    wp_mail($user_data->user_email, $title, $email_message);
                    $message = '<p class="login-error">' . esc_html__( 'A password reset link has been sent to your email address.', 'yoohw-support-portal' ) . '</p>';
                } else {
                    $message = '<p class="login-error">' . esc_html__( 'No user found with that email address or username.', 'yoohw-support-portal' ) . '</p>';
                }
            }
            ?>
            <div class="yoohw-lost-password">
                <?php echo wp_kses_post( $message ); ?>
                <form name="lostpasswordform" id="lostpasswordform" action="" method="post" style="max-width: 280px;">
                    <?php wp_nonce_field( 'yoohw_lost_password', 'yoohw_lost_password_nonce' ); ?>
                    <p>
                        <label for="user_login"><?php esc_html_e( 'Username or Email Address', 'yoohw-support-portal' ); ?><br />
                            <div style="margin-top: -15px; width: 100%;">
                                <input type="text" name="user_login" id="user_login" class="input" value="" size="34" />
                            </div>
                        </label>
                    </p>
                    <p class="submit">
                        <input type="submit" name="wp-submit" id="wp-submit" class="button button-primary button-large" value="<?php esc_attr_e( 'Get New Password', 'yoohw-support-portal' ); ?>" />
                    </p>
                </form>
            </div>
            <?php
            return ob_get_clean();
        }
    }
}
