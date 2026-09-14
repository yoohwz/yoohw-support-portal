#!/usr/bin/env python3
"""High-leverage source characterizations for existing Support Portal safety guards.

These tests intentionally do not claim full WordPress runtime coverage. They freeze
critical structural guards until focused runtime tests are added by tasks touching
each boundary.
"""

import re
from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]


def read(path: str) -> str:
    return (ROOT / path).read_text(encoding="utf-8")


def compact(source: str) -> str:
    return re.sub(r"\s+", " ", source).strip()


def require(path: str, *needles: str) -> None:
    source = read(path)
    missing = [needle for needle in needles if needle not in source]
    assert not missing, f"{path}: missing safety contract fragments: {missing}"


def require_compact(source: str, label: str, *snippets: str) -> None:
    haystack = compact(source)
    missing = [snippet for snippet in snippets if compact(snippet) not in haystack]
    assert not missing, f"{label}: missing structural safety relationships: {missing}"


def forbid(path: str, *needles: str) -> None:
    source = read(path)
    present = [needle for needle in needles if needle in source]
    assert not present, f"{path}: forbidden destructive fragments present: {present}"


def function_body(path: str, name: str) -> str:
    source = read(path)
    match = re.search(rf"function\s+{re.escape(name)}\s*\([^)]*\)[^{{]*{{", source)
    assert match, f"{path}: function {name} not found"

    opening = source.find("{", match.start())
    depth = 0
    for index in range(opening, len(source)):
        char = source[index]
        if char == "{":
            depth += 1
        elif char == "}":
            depth -= 1
            if depth == 0:
                return source[opening + 1 : index]

    raise AssertionError(f"{path}: function {name} has no closing brace")


def protected_attachment_contract() -> None:
    path = "inc/class-yoohw-protected-attachments.php"
    require(
        path,
        "admin_post_" + "' . self::ACTION",
        "admin_post_nopriv_" + "' . self::ACTION",
        "wp_mkdir_p",
        "'.htaccess'",
        "'web.config'",
        "X-Content-Type-Options: nosniff",
    )

    serve = function_body(path, "serve")
    require_compact(
        serve,
        "serve()",
        """
        if ( ! $attachment_id || ! wp_verify_nonce( $nonce, self::ACTION . '_' . $attachment_id ) ) {
        """,
        """
        if ( ! $topic_id || ! self::current_user_can_access_topic( $topic_id ) ) {
            self::deny_anonymous();
        }
        """,
    )

    access = function_body(path, "current_user_can_access_topic")
    require_compact(
        access,
        "current_user_can_access_topic()",
        """
        if ( ! $user_id ) {
            return false;
        }
        """,
        """
        if ( $capability && user_can( $user_id, $capability ) ) {
            return true;
        }
        """,
        """
        if ( $topic && (int) $topic->post_author === $user_id ) {
            return true;
        }
        """,
        "return ! empty( $reply_ids );",
    )
    for fragment in (
        "YoOhw_Support_Capabilities::MANAGE_TOPICS",
        "'post_id' => $topic_id",
        "'user_id' => $user_id",
        "'status'  => 'all'",
        "'fields'  => 'ids'",
    ):
        assert fragment in access, f"current_user_can_access_topic(): missing {fragment}"


def private_topic_authorization_contract() -> None:
    path = "inc/class-yoohw-support-controller.php"
    source = read(path)

    allowed = function_body(path, "allowed_author_ids_for_current_user")
    require_compact(
        allowed,
        "allowed_author_ids_for_current_user()",
        "return [ get_current_user_id() ];",
    )

    native = function_body(path, "current_user_can_view_topic")
    require_compact(
        native,
        "current_user_can_view_topic()",
        """
        if ( current_user_can( YoOhw_Support_Capabilities::VIEW_ALL_TOPICS ) ) {
            return true;
        }
        """,
        "return in_array( (int) $post->post_author, self::allowed_author_ids_for_current_user(), true );",
    )

    isolated = function_body(path, "current_user_can_view_isolated_topic")
    require_compact(
        isolated,
        "current_user_can_view_isolated_topic()",
        """
        if ( current_user_can( YoOhw_Support_Capabilities::VIEW_ALL_TOPICS ) ) {
            return true;
        }
        """,
        "return in_array( absint( $topic['author_id'] ?? 0 ), self::allowed_author_ids_for_current_user(), true );",
    )

    require_compact(
        source,
        path,
        """
        if ( ! $topic || ! self::current_user_can_view_isolated_topic( $topic ) ) {
            status_header( 404 );
        """,
        """
        if ( ! $post || ! self::current_user_can_view_topic( $post ) ) {
            status_header( 404 );
        """,
        """
        if ( ! $topic || ! self::current_user_can_view_isolated_topic( $topic ) || 'resolved' === $topic['status'] ) {
            wp_die(
        """,
    )


def rest_privacy_contract() -> None:
    path = "inc/class-yoohw-support-rest-security.php"
    source = read(path)
    require(
        path,
        "'/wp/v2/posts'",
        "'/wp/v2/comments'",
        "'/wp/v2/media'",
        "'/wp/v2/search'",
        "'/wp/v2/users'",
    )

    protect = function_body(path, "protect_core_routes")
    require_compact(
        protect,
        "protect_core_routes()",
        """
        if ( ! self::is_private_route( $route ) ) {
            return $result;
        }
        """,
        """
        if ( 'OPTIONS' === $method ) {
            return $result;
        }
        """,
        """
        if ( self::is_core_comment_route( $route ) && ! in_array( $method, [ 'GET', 'HEAD' ], true ) ) {
            return new WP_Error(
                'yoohw_support_core_rest_comment_write_disabled',
        """,
        """
        if ( current_user_can( YoOhw_Support_Capabilities::VIEW_ALL_TOPICS ) ) {
            return $result;
        }
        """,
        """
        if ( ! is_user_logged_in() ) {
            return new WP_Error(
                'yoohw_support_rest_authentication_required',
        """,
        """
        return new WP_Error(
            'yoohw_support_rest_forbidden',
        """,
    )

    private_route = function_body(path, "is_private_route")
    require_compact(
        private_route,
        "is_private_route()",
        """
        if ( $route === $prefix || 0 === strpos( $route, $prefix . '/' ) ) {
            return true;
        }
        """,
        "return false;",
    )

    comment_route = function_body(path, "is_core_comment_route")
    require_compact(
        comment_route,
        "is_core_comment_route()",
        "return '/wp/v2/comments' === $route || 0 === strpos( $route, '/wp/v2/comments/' );",
    )


def storage_contract() -> None:
    path = "inc/class-yoohw-support-database.php"
    require(path, "Tables are intentionally installed lazily", "dbDelta", "VERSION_OPTION", "public_key")

    maybe_upgrade = function_body(path, "maybe_upgrade")
    for fragment in (
        "YoOhw_Support_Settings::uses_isolated_storage()",
        "self::VERSION !== (string) get_option( self::VERSION_OPTION, '' )",
        "self::install();",
    ):
        assert fragment in maybe_upgrade, f"maybe_upgrade(): missing {fragment}"

    topics_table = function_body(path, "topics_table")
    replies_table = function_body(path, "replies_table")
    assert "$wpdb->prefix . 'yoohw_support_topics'" in topics_table
    assert "$wpdb->prefix . 'yoohw_support_replies'" in replies_table

    center = read("inc/class-yoohw-support-center.php")
    assert "class-yoohw-support-database.php" in center
    assert "YoOhw_Support_Settings::uses_isolated_storage()" in center
    assert "if ( ! YoOhw_Support_Settings::uses_isolated_storage() )" in center


def capability_contract() -> None:
    require(
        "inc/class-yoohw-support-capabilities.php",
        "yoohw_support_view_all_topics",
        "yoohw_support_manage_topics",
        "yoohw_support_manage_categories",
        "yoohw_support_view_customers",
        "yoohw_support_manage_settings",
    )


def category_access_contract() -> None:
    require(
        "inc/class-yoohw-category-access-code.php",
        "YoOhw_Support_Capabilities::MANAGE_CATEGORIES",
        "wp_verify_nonce",
        "yoohw_support_access_code",
        "yoohw_access_roles",
        "access_code",
        "user_role",
    )


def destructive_cleanup_contract() -> None:
    path = "inc/class-yoohw-attachment-cleanup.php"
    require(
        path,
        "_yoohw_attachment_ids",
        "get_post_meta",
        "get_comment_meta",
        "'attachment' !== get_post_type",
        "wp_delete_attachment( $attachment_id, true )",
    )

    # Uninstall is intentionally retention-first. Guard against accidental table,
    # post/comment or attachment deletion entering ordinary uninstall logic.
    uninstall = "uninstall.php"
    require(
        uninstall,
        "intentionally does not delete posts, comments, media, users",
        "wp_clear_scheduled_hook",
        "remove_cap",
    )
    forbid(
        uninstall,
        "DROP TABLE",
        "wp_delete_post(",
        "wp_delete_comment(",
        "wp_delete_attachment(",
        "$wpdb->query",
    )


def readme_storage_contract() -> None:
    require(
        "readme.txt",
        "Switching modes does not migrate or delete either dataset.",
        "stored outside the public uploads path and served through an authorization check",
        "does not transmit support conversations, attachments, or account data",
    )


def main() -> None:
    protected_attachment_contract()
    private_topic_authorization_contract()
    rest_privacy_contract()
    storage_contract()
    capability_contract()
    category_access_contract()
    destructive_cleanup_contract()
    readme_storage_contract()
    print("source-safety-contracts-ok")


if __name__ == "__main__":
    main()
