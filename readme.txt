=== Support Portal ===
Contributors: yoohw
Tags: customer support, help desk, support portal, ticket system, private support
Requires at least: 6.2
Tested up to: 7.1
Requires PHP: 7.4
Stable tag: 1.1.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Build a private support portal with conversations, access controls, flexible storage, and protected attachments in posts/comments mode.

== Description ==

Support Portal adds a private, account-based support area to WordPress. Customers can open support topics, exchange replies with authorized staff, and follow conversations from open to resolved.

When the portal uses WordPress posts and comments storage, customers can also upload protected attachments that are delivered through authorization checks. Attachment uploads are not currently available in isolated support storage.

The plugin is designed for sites that need customer support to stay separate from public content while remaining fully managed inside WordPress.

= Core features =

* Private support dashboard for customers and support staff.
* Customer-created support topics with threaded replies.
* Protected attachment uploads and authorized delivery in WordPress posts/comments storage mode.
* Open and resolved conversation workflow.
* Category access by user role or access code.
* Dedicated support capabilities for administrators, editors, and custom roles.
* Customer profile access limited to authorized support staff.
* Built-in login, registration, lost-password, and password-reset screens for the portal experience.
* Configurable portal colors, typography, identity, labels, and documentation link.
* First-run setup wizard with storage guidance based on the site.
* Privacy-policy guidance for site owners.
* Optional YoOhw footer credit, disabled by default.

== Storage Modes ==

Support Portal supports two storage modes so the portal can fit both established websites and dedicated support installations.

= Isolated support storage =

Topics and replies are stored in dedicated plugin database tables and served through the plugin's `/support/` portal routes.

This mode is recommended for an existing website, store, content site, or community where support conversations should remain separate from normal WordPress posts and comments.

Dedicated support tables are created lazily after isolated storage is selected and saved.

Protected attachment uploads are not currently available in isolated support storage.

= WordPress posts and comments =

Support topics are stored as WordPress posts and replies as comments.

This mode is best suited to a new or dedicated WordPress installation that is used primarily as a customer support portal.

Protected attachment uploads and authorized attachment delivery are available in this storage mode.

= Important storage note =

Switching modes does not migrate or delete either dataset. Each dataset remains in its original storage system. Choose the appropriate mode before putting the portal into regular use.

== Installation ==

1. Install Support Portal from the WordPress plugin screen, or upload the plugin ZIP through Plugins > Add New.
2. Activate the plugin.
3. Complete the first-run setup wizard and choose the storage mode that fits the site.
4. Open Settings > Support Portal to configure portal appearance, labels, and behavior.
5. Create or assign customer accounts and configure category access where required.
6. Test the customer and support-staff experience before opening the portal to users.

== Frequently Asked Questions ==

= Which storage mode should I use? =

Use isolated support storage when the site already contains public posts, comments, products, pages, or community content. Use WordPress posts and comments when the installation is new or dedicated primarily to customer support, or when you need protected attachment uploads.

= Are dedicated support tables created on every installation? =

No. Dedicated topic and reply tables are created only after isolated support storage is selected and saved.

= Can I switch storage modes later? =

Yes. Switching modes does not migrate or delete either dataset. The portal begins reading and writing through the selected storage mode while the other dataset remains in place.

= Who can view a customer's support topic? =

Customers can view their own topics. Users with the plugin's dedicated support capabilities can view and manage the topics permitted by those capabilities. Custom roles can also be granted the relevant capabilities.

= Which features can I configure? =

Under Settings > Support Portal > Support features, configure new-topic and reply uploads separately, file count (1-20), size per file (1-1024 MB), and allowed file groups (images, videos, ZIP). Effective upload limits are capped by WordPress/PHP. No selected file groups means new uploads are disabled. Attachment controls are unavailable in isolated storage, and native-mode preferences are retained when saving isolated-mode settings.

Existing attachments can use image/video previews or download links only. Disabling new uploads or removing an allowed file group does not delete or hide previously uploaded files or change their protected download permissions.

Configure older-reply collapsing, the number of replies shown initially/per expansion (1-100), topics per page (1-100), and WordPress reply avatars versus initials only. Topic page size also applies to embedded lists and customer profiles. WordPress avatar visibility is still respected.

Defaults preserve the existing experience: uploads enabled in native storage, five files up to 5 MB each (subject to server limits), all existing file groups, previews, reply collapsing in groups of five, eight topics per page, and WordPress avatars.

= How are topic replies displayed? =

Your own replies appear on the right; other accounts' replies appear on the left. Support and customer colors and configurable labels remain tied to the author's support classification. WordPress account avatars, including compatible local-avatar filters and Gravatar, are shown when available; initials remain the fallback, and the WordPress avatar visibility setting is respected.

By default, the conversation initially shows the five newest replies in chronological order. A highlighted history panel shows the number of older replies remaining and how many replies are visible. Its button reveals another configured batch (five by default) above them each time, with an immediate busy state and a short reveal animation that respects reduced-motion preferences. The browser gets a paint opportunity for the busy state; there is no artificial timed wait. This expands replies already present on the authorized page, without a separate data request. Direct links to older replies reveal their target. With JavaScript disabled, the full conversation remains readable. The original topic body, total reply count, and reply form stay in place.

= Can I embed support components in my theme? =

Yes. Add a Shortcode block to a regular WordPress page with [yoohw_post_form], [yoohw_login_form], [yoohw_lost_password_form], or [yoohw_support_topics]. Use one copy of each shortcode per page, and put login and lost-password forms on separate pages. Topic details and replies continue to open the dedicated portal.

In WordPress posts/comments storage mode, disable "Render standard WordPress pages with the plugin UI" to use the theme on those pages, and use pretty permalinks (root-path page_id requests belong to the native portal dashboard). In isolated mode, regular pages already use the theme. Use a page slug outside the portal routes. The list's filters and pagination stay on that page.

Exclude all shortcode pages from page, CDN, and fragment caches, including for guests. The plugin sends no-cache signals, but an upstream cache can respond before WordPress runs. Builders must render the shortcodes server-side before footer scripts.

= How is category access controlled? =

Categories can be restricted by user role or by access code, depending on how the portal is configured.

= Are uploaded support attachments public? =

Protected attachment uploads are currently available only in WordPress posts/comments storage mode. In that mode, support attachments handled by the protected attachment system are stored outside the public uploads path and served through an authorization check. Existing support-related attachments can be moved into protected storage as they are accessed through the portal.

= Does YoOhw receive my support conversations or attachments? =

No. Support Portal does not transmit support conversations, attachments, or account data to YoOhw or another external service.

= Does the plugin add a public YoOhw credit? =

Only if the site administrator explicitly enables the optional footer-credit setting. It is disabled by default.

= What happens when the plugin is uninstalled? =

The plugin removes its settings, scheduled hooks, and custom capabilities. Support conversations, attachments, users, categories, and isolated support tables are intentionally retained to reduce the risk of accidental data loss.

== Privacy ==

Support topics and replies may contain personal or confidential information, including account identifiers, message content, timestamps, categories, and workflow status. In WordPress posts/comments storage mode, support conversations may also include uploaded files.

This information is stored on the WordPress site. Support Portal does not transmit support conversations, attachments, or account data to YoOhw or another external service.

The plugin adds suggested privacy-policy text to the WordPress privacy-policy guide. Site owners remain responsible for establishing appropriate retention periods, access policies, backups, and procedures for verified export or erasure requests.

== External Services ==

Support Portal does not require an external service to provide its core support portal functionality and does not transmit support conversation data to YoOhw.

If a site administrator configures an external documentation URL, that link is presented as a normal outbound link and does not send support conversation content to that destination.

== License ==

Support Portal is licensed under the GNU General Public License v2.0 or later. See `license.txt` for the full license text.

Third-party components and their license notices are documented in `third-party-licenses.txt`.

== Changelog ==

= 1.1.0 =
* New: Embed topic submission, login, password recovery, and authorized topic lists in regular WordPress theme pages using shortcodes.
* New: Configure attachments for new topics and replies separately, including file count, file-size limits, permitted image/video/ZIP types, and protected preview or download-link display.
* New: Configure reply-history batch size, topics per page, and WordPress avatars or initials.
* Improved: Conversations align your replies on the right, show recent replies first, and let you expand older messages without additional requests; full history remains available without JavaScript.
* Improved: Narrow-screen topic forms, editor toolbars, long titles, dashboard cards, and administration settings are easier to use, with visible keyboard focus.
* Improved: Text, links, inputs, and buttons remain readable with configured dark backgrounds or light primary colors.
* Fixed: Category role selection no longer triggers recursive focus errors, and unavailable site icons no longer show broken images.
* Important: Exclude pages using personalized support shortcodes from page/CDN caches. Theme embeds in WordPress posts/comments storage require pretty permalinks.

= 1.0.0 =
* Initial release.
* Added private customer support topics and replies.
* Added protected support attachments and authorization checks for WordPress posts/comments storage mode.
* Added isolated support storage and native WordPress posts/comments storage modes.
* Added role- and access-code-based category access.
* Added dedicated support capabilities and customer visibility controls.
* Added configurable portal appearance, labels, identity, and account flows.
* Added first-run setup guidance, privacy-policy guidance, and compatibility checks.
