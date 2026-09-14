<?php

if (!defined('ABSPATH')) {
    exit;
}

class YoOhw_Post_Restriction {
    public static function restrict_posts_to_author($query) {
        if ( is_admin() || ! ( $query instanceof WP_Query ) || ! $query->is_main_query() ) {
            return;
        }

        if ( class_exists( 'YoOhw_Support_Router' ) && YoOhw_Support_Router::is_support_request() ) {
            return;
        }

        if ( ! is_user_logged_in() || current_user_can( YoOhw_Support_Capabilities::VIEW_ALL_TOPICS ) ) {
            return;
        }

        $post_type = $query->get( 'post_type' );

        if ( $post_type && 'post' !== $post_type && ! ( is_array( $post_type ) && in_array( 'post', $post_type, true ) ) ) {
            return;
        }

        $query->set( 'author__in', [ get_current_user_id() ] );
    }
}
