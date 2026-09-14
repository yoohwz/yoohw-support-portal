=== YoOhw Support Portal ===
Contributors: yoohw
Tags: support, help desk, customer support, support portal, private forum
Requires at least: 6.2
Tested up to: 7.1
Requires PHP: 7.4
Stable tag: 1.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Create a private customer support portal with topics, replies, attachments, access controls, and dedicated or native storage.

== Description ==

YoOhw Support Portal provides a private, account-based support area where customers can create topics, exchange replies with support staff, upload attachments, and follow open or resolved conversations.

The first-run setup wizard offers two storage modes:

* **Create isolated support data** stores new topics and replies in dedicated plugin tables under the `/support/` URL namespace. This is recommended for an established website, store, content site, or community where normal posts and comments must remain separate.
* **Use WordPress posts and comments** stores support topics as posts and replies as comments. This is recommended for a new, blank WordPress installation used entirely as a support portal.

Switching modes does not migrate or delete either dataset. Dedicated tables are created only after isolated storage is selected and saved.

== Features ==

* Private support dashboard, topic list, topic detail, and topic creation screens.
* Conversation replies with protected attachments.
* Isolated custom-table storage or native posts/comments storage.
* Category access controlled by user role or access code.
* Open and resolved support workflow.
* Dedicated support capabilities for administrators, editors, and custom roles.
* Customer profiles available only to authorized support staff.
* Login, registration, lost-password, and password-reset screens.
* Configurable colors, fonts, portal identity, labels, and external documentation URL.
* First-run setup wizard with site-specific storage recommendation.
* Site compatibility scan and optional WordPress starter-content cleanup.
* Suggested privacy-policy text in Settings > Privacy.
* Optional YoOhw footer credit, disabled by default.

== Installation ==

1. Upload the plugin to `/wp-content/plugins/yoohw-support-portal/`, or install its ZIP from Plugins > Add New.
2. Activate YoOhw Support Portal.
3. Complete the setup wizard and select the storage mode appropriate for the site.
4. Go to Settings > Support Portal to configure appearance, labels, and portal behavior.
5. Create or assign customer accounts and configure category access where needed.

== Frequently Asked Questions ==

= Which storage mode should I choose? =

Choose isolated storage when the site already contains posts, comments, pages, products, or other public content. Choose posts/comments storage for a new blank WordPress installation dedicated entirely to customer support.

= Are isolated database tables created on every installation? =

No. The plugin creates its dedicated topic and reply tables only after isolated storage is selected and saved.

= Does switching storage mode migrate support conversations? =

No. Switching changes where new portal requests are read and written. It does not migrate or delete existing data.

= Who can see a customer's support topic? =

By default, a customer can see their own topics. Administrators and editors receive dedicated support capabilities that allow them to view and manage all support topics. Capabilities can also be assigned to custom roles.

= Are attachments public? =

New support attachments are stored outside the public uploads path and served through an authorization check. Existing attachments are progressively moved to protected storage when accessed through the portal.

= Does the plugin send support data to YoOhw? =

No. The plugin does not transmit support conversations, attachments, or account data to YoOhw or another external service.

= Does the plugin add a public credit link? =

Only when the site administrator explicitly enables the optional footer-credit setting. It is disabled by default.

= What happens when the plugin is uninstalled? =

Plugin settings, scheduled hooks, and custom capabilities are removed. Support conversations, attachments, users, categories, and isolated support tables are retained to prevent accidental data loss.

== Privacy ==

Support topics and replies can contain account identifiers, message content, timestamps, categories, workflow status, and uploaded files. This data is stored on the WordPress site and is not sent to YoOhw.

The plugin adds suggested text to the WordPress privacy-policy guide. Site owners remain responsible for defining retention periods and handling verified export or erasure requests according to their support, legal, and security requirements.

== Changelog ==

= 1.0.0 =
* Initial WordPress.org submission with security, asset-loading, naming, storage-path, and compatibility improvements from the review process.

* Serve protected image sizes through the authorized attachment endpoint so Media Library thumbnails render correctly.
* Keep Site Icon and logo attachments public even if they are accidentally related to a Support topic.
* Add first-run setup wizard and site-specific storage recommendations.
* Add isolated support storage with lazy table creation and `/support/` routes.
* Add dedicated support capabilities and consistent permissions across storage modes.
* Add protected attachment delivery and direct-reply authorization.
* Prevent protected attachment downloads from being redirected for non-admin customers when a site-level admin access guard is active.
* Add configurable portal UI, identity, fonts, colors, and labels.
* Add category access codes and role-based category access.
* Add site compatibility checks, starter-content tools, and dismissible setup guidance.
* Add privacy-policy guidance and WordPress.org submission hardening.
