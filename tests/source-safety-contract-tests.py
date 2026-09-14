#!/usr/bin/env python3
"""High-leverage source characterizations for existing Support Portal safety guards.

These tests intentionally do not claim full WordPress runtime coverage. They freeze
critical structural guards until focused runtime tests are added by tasks touching
each boundary.
"""

from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]


def read(path: str) -> str:
    return (ROOT / path).read_text(encoding="utf-8")


def require(path: str, *needles: str) -> None:
    text = read(path)
    missing = [needle for needle in needles if needle not in text]
    assert not missing, f"{path}: missing safety contract fragments: {missing}"


def forbid(path: str, *needles: str) -> None:
    text = read(path)
    present = [needle for needle in needles if needle in text]
    assert not present, f"{path}: forbidden destructive fragments present: {present}"


def protected_attachment_contract() -> None:
    path = "inc/class-yoohw-protected-attachments.php"
    require(
        path,
        "admin_post_" + "' . self::ACTION",
        "admin_post_nopriv_" + "' . self::ACTION",
        "wp_verify_nonce",
        "current_user_can_access_topic",
        "YoOhw_Support_Capabilities::MANAGE_TOPICS",
        "post_author",
        "'user_id' => $user_id",
        "wp_mkdir_p",
        "'.htaccess'",
        "'web.config'",
        "X-Content-Type-Options: nosniff",
    )


def rest_privacy_contract() -> None:
    path = "inc/class-yoohw-support-rest-security.php"
    require(
        path,
        "'/wp/v2/posts'",
        "'/wp/v2/comments'",
        "'/wp/v2/media'",
        "'/wp/v2/search'",
        "'/wp/v2/users'",
        "yoohw_support_core_rest_comment_write_disabled",
        "YoOhw_Support_Capabilities::VIEW_ALL_TOPICS",
        "yoohw_support_rest_authentication_required",
        "yoohw_support_rest_forbidden",
    )


def storage_contract() -> None:
    path = "inc/class-yoohw-support-database.php"
    require(
        path,
        "Tables are intentionally installed lazily",
        "YoOhw_Support_Settings::uses_isolated_storage()",
        "wp_yoohw_support",  # class owns prefix-derived table suffixes; see explicit table names below.
    )


def storage_table_contract() -> None:
    text = read("inc/class-yoohw-support-database.php")
    assert "'yoohw_support_topics'" in text
    assert "'yoohw_support_replies'" in text
    assert "dbDelta" in text
    assert "VERSION_OPTION" in text
    assert "public_key" in text

    center = read("inc/class-yoohw-support-center.php")
    assert "YoOhw_Support_Settings::uses_isolated_storage()" in center
    assert "class-yoohw-support-database.php" in center
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
    rest_privacy_contract()
    storage_table_contract()
    capability_contract()
    category_access_contract()
    destructive_cleanup_contract()
    readme_storage_contract()
    print("source-safety-contracts-ok")


if __name__ == "__main__":
    main()
