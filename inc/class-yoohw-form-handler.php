<?php

if (!defined('ABSPATH')) {
    exit;
}

class YoOhw_Form_Handler {

	public static function handle_post_submission() {
        if (
            empty( $_POST['yoohw_post_title'] ) ||
            empty( $_POST['yoohw_post_category'] )
        ) {
            return;
        }

		if (
			empty( $_POST['yoohw_submit_post_nonce'] ) ||
			! wp_verify_nonce(
				sanitize_text_field( wp_unslash( $_POST['yoohw_submit_post_nonce'] ) ),
				'yoohw_submit_post'
			)
		) {
			wp_die( esc_html__( 'Security check failed.', 'yoohw-support-portal' ) );
		}

		if ( ! is_user_logged_in() ) {
			wp_die( esc_html__( 'You must be logged in to submit a topic.', 'yoohw-support-portal' ) );
		}

        $category_id = isset( $_POST['yoohw_post_category'] ) ? absint( $_POST['yoohw_post_category'] ) : 0;

        if ( ! $category_id ) {
            wp_die( esc_html__( 'Invalid category.', 'yoohw-support-portal' ) );
        }

        $user_id = get_current_user_id();
        $is_admin = current_user_can( YoOhw_Support_Capabilities::MANAGE_CATEGORIES );

        if ( ! YoOhw_Shortcode::topic_category_selectable( $category_id, $user_id, $is_admin ) ) {
            wp_die( esc_html__( 'This category is not available for new topics.', 'yoohw-support-portal' ) );
        }

        if ( ! $is_admin ) {
            $user_codes = YoOhw_Shortcode::get_user_access_codes( $user_id );

            if ( ! YoOhw_Shortcode::user_can_access_category_effective( $user_codes, $category_id, $user_id ) ) {
                wp_die( esc_html__( 'You do not have permission to post in this category.', 'yoohw-support-portal' ) );
            }
        }

		YoOhw_Shortcode::validate_attachment_uploads();

		$title = sanitize_text_field( wp_unslash( $_POST['yoohw_post_title'] ) );

		$category = absint( $_POST['yoohw_post_category'] );

		// phpcs:disable WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Raw editor HTML is unslashed here and passed through wp_kses_post() immediately below.
		$content = isset( $_POST['yoohw_post_content'] )
			? wp_unslash( $_POST['yoohw_post_content'] )
			: '';
		// phpcs:enable WordPress.Security.ValidatedSanitizedInput.InputNotSanitized

		$content = wp_kses_post( $content );
		$content = YoOhw_Shortcode::yoohw_clean_content( $content );
		$content = YoOhw_Shortcode::auto_link_and_target_blank( $content );

		// Check real text content after cleaning HTML.
		$plain_content = trim( wp_strip_all_tags( $content ) );

			if ( '' === $plain_content ) {
				wp_die(
					wp_kses_post( __( '<strong>Error:</strong> Post content cannot be empty.', 'yoohw-support-portal' ) ),
				esc_html__( 'Error', 'yoohw-support-portal' ),
				[
					'response'  => 400,
					'back_link' => true,
				]
			);
		}

		if ( ! $category ) {
			wp_die( esc_html__( 'Error: Invalid category.', 'yoohw-support-portal' ) );
		}

		if ( YoOhw_Support_Settings::uses_isolated_storage() ) {
			$topic_id = YoOhw_Support_Database::create_topic(
				[
					'title'       => $title,
					'content'     => $content,
					'author_id'   => get_current_user_id(),
					'category_id' => $category,
				]
			);

			if ( is_wp_error( $topic_id ) ) {
				wp_die( esc_html( $topic_id->get_error_message() ) );
			}

			$topic = YoOhw_Support_Database::get_topic_by_id( $topic_id );

			if ( ! $topic ) {
				wp_die( esc_html__( 'The topic could not be loaded after it was created.', 'yoohw-support-portal' ) );
			}

			wp_safe_redirect( add_query_arg( 'created', '1', YoOhw_Support_Router::topic_url( $topic ) ) );
			exit;
		}

        $post_data = [
            'post_title'    => $title,
            'post_content'  => $content,
            'post_status'   => 'publish',
            'post_author'   => get_current_user_id(),
            'post_type'     => 'post',
            'post_category' => [ $category ],
        ];

		$post_id = wp_insert_post( $post_data, true );

		if ( is_wp_error( $post_id ) ) {
			wp_die( esc_html( $post_id->get_error_message() ) );
		}

		$attachment_ids = YoOhw_Shortcode::handle_attachment_uploads( $post_id );

		if ( ! empty( $attachment_ids ) ) {
			update_post_meta( $post_id, YoOhw_Shortcode::POST_META_ATTACHMENT_IDS, $attachment_ids );
		}

        $post_url = class_exists( 'YoOhw_Support_Router' )
            ? YoOhw_Support_Router::topic_url( $post_id )
            : get_permalink( $post_id );

        if ( $post_url ) {
            wp_safe_redirect( $post_url );
        } else {
            wp_safe_redirect( home_url() ); // fallback
        }

        exit;
	}
}
