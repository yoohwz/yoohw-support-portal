# Embedding support in a theme

The dedicated portal keeps its existing routes, layout, permissions, and submission handlers. Shortcodes add individual support components to a regular WordPress page. The active theme provides the page header, footer, and surrounding layout; the component uses the portal's configured colors and fonts within its own container.

A compact **Shortcodes** section below **Support features** in **Settings > Support Portal** opens the built-in shortcode guide in a WordPress ThickBox dialog.

## Setup

1. Create a normal WordPress page with a unique slug, such as `customer-help`. Do not reuse a portal route such as `/topics/`, `/new/`, `/login/`, or `/support/`.
2. In WordPress posts/comments storage mode, turn off **Render standard WordPress pages with the plugin UI** if this page should use your theme. The homepage remains the portal dashboard in that mode. Use pretty permalinks: the existing native portal router treats root-path requests such as `/?page_id=123` as its dashboard.
3. In isolated storage mode, ordinary pages already use your theme and the portal remains under `/support/`. Switching storage modes does not migrate conversations.
4. Insert a Shortcode block with one of the tags below. Use at most one copy of each shortcode per page, and place login and lost-password forms on separate pages.
5. Exclude these pages from page caches, CDN caches, and builder fragment caches for every visitor. The plugin sends no-cache headers and sets `DONOTCACHEPAGE` when it detects shortcodes in normal page content, but an upstream cache can serve a response before WordPress runs. Builder/template placements must also be excluded explicitly.

## Shortcodes

| Tag | Content |
| --- | --- |
| `[yoohw_post_form]` | New-topic form, including the existing editor, category selection, and attachments where supported by the active storage mode. Guests see the existing login prompt. |
| `[yoohw_login_form]` | Existing login form and its messages. Existing SSO integrations and authentication rules still apply. |
| `[yoohw_lost_password_form]` | Existing password-recovery form. Reset links continue to use the portal. |
| `[yoohw_support_topics]` | Configurable topics per page (eight by default), with search, status/category filters, and pagination. Guests see a login link. Customers see their own topics; staff with the view-all-topics capability see the topics available to that capability. |

No shortcode attributes are required. Place a heading above a shortcode using the theme/page editor as needed. The topic list uses `yoohw_search`, `yoohw_status`, `yoohw_category`, and `yoohw_page`, leaving WordPress's `s` search parameter alone. Filtering and pagination stay on the containing page, including isolated-storage sites using plain permalinks. WordPress posts/comments storage requires pretty permalinks for theme-hosted pages.

Topic details, replies, new-topic links from the list, and successful topic submissions continue to open the dedicated portal. Login/password recovery use the existing handlers and redirects; these shortcodes do not replace the site's authentication integration or configure custom destinations.

CSS and JavaScript are loaded for pages containing these shortcodes. Theme assets stay active on theme-hosted pages. Repeated copies of the same shortcode in a single request render only once to avoid duplicate form/editor IDs. Feeds omit the embedded components. Builders must render server-side before the theme's footer scripts; client-side insertion and cached shortcode HTML are not supported.

## Shared feature options

Portal routes and shortcodes use the same **Settings > Support Portal > Support features** policy. New-topic uploads and reply uploads have independent switches; file count/size and allowed groups apply on the server as well as to the picker. WordPress/PHP may lower configured upload limits. Attachment options cannot enable uploads in isolated storage.

Disabling uploads affects new submissions only. Existing files retain their protected links; select **Download links only** to replace image/video previews with file links. Reply collapsing/batch size, topic page size, and reply avatar mode are configurable, with the original five-reply/eight-topic/WordPress-avatar defaults retained.
