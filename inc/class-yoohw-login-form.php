<?php

if (!defined('ABSPATH')) {
    exit;
}

class YoOhw_Login_Form {
    public static function render_login_form() {
        if ( class_exists( 'YoOhw_Support_Controller' ) ) {
            return YoOhw_Support_Controller::render_login_form();
        }

        if ( is_user_logged_in() ) {
            return '<p>' . esc_html__( 'You are already logged in.', 'yoohw-support-portal' ) . '</p>';
        } else {
            $lost_password_url = class_exists( 'YoOhw_Support_Router' )
                ? YoOhw_Support_Router::url( 'lost-password' )
                : home_url( '/lost-password/' );
            $lost_password_url = apply_filters( 'yoohw_support_lost_password_url', $lost_password_url, 'legacy_login_shortcode' );
            $lost_password_link_attributes = apply_filters( 'yoohw_support_lost_password_link_attributes', [], 'legacy_login_shortcode', $lost_password_url );
            $error = '';
            if (
                isset( $_POST['yoohw_login_nonce'], $_POST['log'], $_POST['pwd'] ) &&
                wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['yoohw_login_nonce'] ) ), 'yoohw_login' )
            ) {
                $creds = array(
                    'user_login'    => sanitize_user( wp_unslash( $_POST['log'] ) ),
                    // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Passwords must remain byte-for-byte unchanged; nonce is verified above.
                    'user_password' => (string) wp_unslash( $_POST['pwd'] ),
                    'remember'      => isset($_POST['rememberme']) ? true : false,
                );
                $user = wp_signon($creds, false);
                if ( is_wp_error($user) ) {
                    $error = $user->get_error_message();
                } else {
                    wp_set_current_user($user->ID);
                    wp_set_auth_cookie($user->ID, isset($_POST['rememberme']));

                    if ( ! headers_sent() ) {
                        wp_safe_redirect( home_url() );
                        exit;
                    } else {
                        $error = __( 'Redirection failed due to headers already sent.', 'yoohw-support-portal' );
                    }
                }
            }

            ob_start();
            ?>
            <div class="yoohw-login">
                <?php if ( $error ): ?>
                    <div class="login-error" style="color: red;"><?php echo esc_html( $error ); ?></div>
                <?php endif; ?>
                <form name="loginform" id="loginform" action="" method="post" style="max-width: 280px;">
                    <?php wp_nonce_field( 'yoohw_login', 'yoohw_login_nonce' ); ?>
                    <p>
                        <label for="user_login"><?php esc_html_e( 'Username or Email address', 'yoohw-support-portal' ); ?><br />
                            <div style="margin-top: -15px; width: 100%;">
                                <input type="text" name="log" id="user_login" class="input" value="" size="34" />
                            </div>
                        </label>
                    </p>
                    <p>
                        <label for="user_pass"><?php esc_html_e( 'Password', 'yoohw-support-portal' ); ?><br />
                            <div class="password-wrapper" style="margin-top: -15px; position: relative;">
                                <input type="password" name="pwd" id="user_pass" class="input" value="" size="34" />
                                <span class="password-toggle" id="toggle-password" style="position:absolute; right:8px; top:50%; transform:translateY(-50%); cursor:pointer;">
                                    <?php if ( class_exists( 'YoOhw_Support_Icons' ) ) { YoOhw_Support_Icons::output( 'eye' ); } ?>
                                </span>
                            </div>
                        </label>
                    </p>
                    <p class="forgetmenot">
                        <label for="rememberme">
                            <input name="rememberme" type="checkbox" id="rememberme" value="forever" /> <?php esc_html_e( 'Remember Me', 'yoohw-support-portal' ); ?>
                        </label>
                        <a class="forgot-password" href="<?php echo esc_url( $lost_password_url ); ?>"<?php echo self::render_link_attributes( is_array( $lost_password_link_attributes ) ? $lost_password_link_attributes : [] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Method escapes every attribute value. ?>><?php esc_html_e( 'Forgot your password?', 'yoohw-support-portal' ); ?></a>
                    </p>
                    <p class="submit">
                        <input type="submit" name="wp-submit" id="wp-submit" class="button button-primary button-large" value="<?php esc_attr_e( 'Log In', 'yoohw-support-portal' ); ?>" />
                        <input type="hidden" name="redirect_to" value="<?php echo esc_url( home_url() ); ?>" />
                    </p>
                </form>
            </div>
            <?php
            return ob_get_clean();
        }
    }

    private static function render_link_attributes( array $attributes ) {
        $html = '';

        foreach ( $attributes as $name => $value ) {
            $name = is_string( $name ) ? strtolower( trim( $name ) ) : '';

            if ( '' === $name || ! preg_match( '/^[a-z][a-z0-9:_-]*$/', $name ) || false === $value || null === $value ) {
                continue;
            }

            if ( true === $value ) {
                $html .= ' ' . esc_attr( $name );
                continue;
            }

            $html .= ' ' . esc_attr( $name ) . '="' . esc_attr( (string) $value ) . '"';
        }

        return $html;
    }
}
