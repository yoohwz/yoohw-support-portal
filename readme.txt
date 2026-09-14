=== YoOhw Support Portal ===
Contributors: yoohw
Tags: customer support, help desk, support portal, ticket system, private support
Requires at least: 6.2
Tested up to: 7.1
Requires PHP: 7.4
Stable tag: 1.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Build a private customer support portal with secure conversations, protected attachments, access controls, and flexible storage.

== Description ==

YoOhw Support Portal adds a private, account-based support area to WordPress. Customers can open support topics, exchange replies with authorized staff, upload protected attachments, and follow conversations from open to resolved.

The plugin is designed for sites that need customer support to stay separate from public content while remaining fully managed inside WordPress.

= Core features =

* Private support dashboard for customers and support staff.
* Customer-created support topics with threaded replies.
* Protected attachment delivery with authorization checks.
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

YoOhw Support Portal supports two storage modes so the portal can fit both established websites and dedicated support installations.

= Isolated support storage =

Topics and replies are stored in dedicated plugin database tables and served through the plugin's `/support/` portal routes.

This mode is recommended for an existing website, store, content site, or community where support conversations should remain separate from normal WordPress posts and comments.

Dedicated support tables are created lazily after isolated storage is selected and saved.

= WordPress posts and comments =

Support topics are stored as WordPress posts and replies as comments.

This mode is best suited to a new or dedicated WordPress installation that is used primarily as a customer support portal.

= Important storage note =

Switching modes does not migrate or delete either dataset. Each dataset remains in its original storage system. Choose the appropriate mode before putting the portal into regular use.

== Installation ==

1. Install YoOhw Support Portal from the WordPress plugin screen, or upload the plugin ZIP through Plugins > Add New.
2. Activate the plugin.
3. Complete the first-run setup wizard and choose the storage mode that fits the site.
4. Open Settings > Support Portal to configure portal appearance, labels, and behavior.
5. Create or assign customer accounts and configure category access where required.
6. Test the customer and support-staff experience before opening the portal to users.

== Frequently Asked Questions ==

= Which storage mode should I use? =

Use isolated support storage when the site already contains public posts, comments, products, pages, or community content. Use WordPress posts and comments when the installation is new or dedicated primarily to customer support.

= Are dedicated support tables created on every installation? =

No. Dedicated topic and reply tables are created only after isolated support storage is selected and saved.

= Can I switch storage modes later? =

Yes. Switching modes does not migrate or delete either dataset. The portal begins reading and writing through the selected storage mode while the other dataset remains in place.

= Who can view a customer's support topic? =

Customers can view their own topics. Users with the plugin's dedicated support capabilities can view and manage the topics permitted by those capabilities. Custom roles can also be granted the relevant capabilities.

= How is category access controlled? =

Categories can be restricted by user role or by access code, depending on how the portal is configured.

= Are uploaded support attachments public? =

Support attachments handled by the protected attachment system are stored outside the public uploads path and served through an authorization check. Existing support-related attachments can be moved into protected storage as they are accessed through the portal.

= Does YoOhw receive my support conversations or attachments? =

No. YoOhw Support Portal does not transmit support conversations, attachments, or account data to YoOhw or another external service.

= Does the plugin add a public YoOhw credit? =

Only if the site administrator explicitly enables the optional footer-credit setting. It is disabled by default.

= What happens when the plugin is uninstalled? =

The plugin removes its settings, scheduled hooks, and custom capabilities. Support conversations, attachments, users, categories, and isolated support tables are intentionally retained to reduce the risk of accidental data loss.

== Privacy ==

Support topics and replies may contain personal or confidential information, including account identifiers, message content, timestamps, categories, workflow status, and uploaded files.

This information is stored on the WordPress site. YoOhw Support Portal does not transmit support conversations, attachments, or account data to YoOhw or another external service.

The plugin adds suggested privacy-policy text to the WordPress privacy-policy guide. Site owners remain responsible for establishing appropriate retention periods, access policies, backups, and procedures for verified export or erasure requests.

== External Services ==

YoOhw Support Portal does not require an external service to provide its core support portal functionality and does not transmit support conversation data to YoOhw.

If a site administrator configures an external documentation URL, that link is presented as a normal outbound link and does not send support conversation content to that destination.

== License ==

YoOhw Support Portal is licensed under the GNU General Public License v2.0 or later. See `license.txt` for the full license text.

Third-party components and their license notices are documented in `third-party-licenses.txt`.

== Changelog ==

= 1.0.0 =
* Initial release.
* Added private customer support topics and replies.
* Added protected support attachments and authorization checks.
* Added isolated support storage and native WordPress posts/comments storage modes.
* Added role- and access-code-based category access.
* Added dedicated support capabilities and customer visibility controls.
* Added configurable portal appearance, labels, identity, and account flows.
* Added first-run setup guidance, privacy-policy guidance, and compatibility checks.
