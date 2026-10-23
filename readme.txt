=== Support Portal ===
Contributors: yoohw
Tags: customer support, help desk, ticket system, helpdesk, support
Requires at least: 6.2
Tested up to: 7.1
Requires PHP: 7.4
Stable tag: 1.1.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Create a private customer support portal with topics, replies, theme shortcodes, and protected attachments in WordPress posts/comments mode.

== Description ==

Support Portal brings private customer support into your WordPress site. Customers sign in to open topics, reply to your team, and track their conversations. Authorized support staff can view and manage customer topics from the portal.

Use a dedicated support area or embed individual forms and topic lists in pages built with your theme. Choose isolated support storage for an existing website, or WordPress posts/comments storage for a dedicated help desk with protected attachments, topic emails, and automatic resolution.

= Customer conversations =

* Private customer dashboards and support topics with replies.
* Search, category filters, open/resolved status filters, and pagination for topic lists.
* Rich-text editing with lists, links, quotes, tables, inline code, and scrollable code blocks. Pasted content is cleaned up while preserving supported structure and code text.
* Conversation-style replies: your replies on the right and other accounts' replies on the left.
* Expandable reply history, configurable batch sizes, and WordPress avatars or initials. The full conversation remains readable without JavaScript.
* Built-in login, registration, lost-password, and password-reset screens, with registration subject to the site's settings.

= Access and appearance =

* Dedicated support capabilities for staff and custom roles.
* Customers can access their own topics; customer profiles are restricted to authorized support staff.
* Category access controlled by user role or access code.
* Configurable portal colors, fonts, labels, documentation link, and site identity.
* Responsive portal layouts and an optional YoOhw footer credit, disabled by default.
* First-run setup guidance to help choose the appropriate storage mode.

= Attachments, email, and automation =

The following features require WordPress posts/comments storage:

* Protected image, video, and ZIP attachments stored outside the web document root and delivered through authorization checks.
* Separate upload switches for new topics and replies, with configurable file counts, sizes, allowed file groups, and previews or download links.
* Email notifications for new topics and replies, plus support follow-up and topic-closing reminders.
* Email sender and administrator-recipient settings, notification switches, reminder timing, and sample preview/test tools.
* Automatic topic resolution after a configurable period without a customer response to the latest support reply.

These features are unavailable in isolated storage. Saved native-mode preferences are retained when using isolated storage.

= Two storage modes =

**Isolated support storage:** Topics and replies use dedicated plugin database tables, created only after this mode is selected and saved. The portal runs under `/support/`, while ordinary site content continues to use your theme. Choose this mode for an existing website, store, or community where support should stay separate from WordPress posts and comments.

**WordPress posts and comments:** Topics use WordPress posts and replies use comments. Choose this mode for a new or dedicated support installation. The site homepage becomes the portal dashboard, and standard WordPress pages can also use the plugin UI. Protected uploads, topic notifications, reminders, and auto-resolve are available in this mode.

Switching modes does not migrate or delete either dataset. Each dataset remains in its original storage system. Choose the appropriate mode before putting the portal into regular use.

= Use support components with your theme =

Add a Shortcode block to a regular WordPress page:

* `[yoohw_post_form]` — New support topic form.
* `[yoohw_support_topics]` — Authorized topic list with search, filters, and pagination.
* `[yoohw_login_form]` — Login form.
* `[yoohw_lost_password_form]` — Password recovery form.

Topic details and replies open in the dedicated portal. See the FAQ for theme setup and caching requirements.

== Installation ==

1. Install Support Portal from Plugins > Add New, or upload the plugin ZIP, then activate it.
2. Complete the first-run setup wizard and choose your storage mode.
3. Open Settings > Support Portal > General to configure appearance, support features, and topic lifecycle settings.
4. For WordPress posts/comments storage, review the Emails tab and verify that your hosting supports private attachment storage if you need uploads.
5. Create or assign customer and staff accounts. Configure support capabilities and category access as needed.
6. Optionally add the support shortcodes to theme pages and exclude those pages from caches.
7. Test the customer and staff experience, attachment access, and email delivery before opening the portal to users.

== Frequently Asked Questions ==

= Who can read support conversations? =

Customers can view their own topics, subject to category-access rules. Staff access depends on the plugin's dedicated support capabilities. Custom roles can be granted the capabilities they need. Customer profiles are restricted to authorized support staff.

= Can I switch storage modes later? =

Yes, but switching does not migrate, merge, or delete conversations. The portal reads and writes the selected dataset, while the other dataset remains in place. Isolated support tables are created only after isolated storage is selected and saved.

= What attachment options are available? =

In WordPress posts/comments storage, enable uploads separately for new topics and replies. Set 1–20 files per submission, 1–1024 MB per file, and permitted image, video, or ZIP groups. WordPress and PHP limits may reduce the effective upload size. Selecting no file groups disables new uploads.

Choose image/video previews or download links only for existing attachments. Disabling uploads or removing an allowed group does not delete or hide existing files or change their protected download permissions. Uploads are unavailable in isolated storage.

= How are attachments protected? =

New support attachments and generated image variants are stored outside the public uploads path and served through an authorization check. Their private storage directory is outside the web document root. Downloads require authorization for the related conversation. PHP must be able to write to the private directory, and the plugin must be able to verify the storage location; uploads are rejected when verification fails.

The hosting operator must ensure that aliases, other virtual hosts, symlinks, or CDN mappings do not expose this directory. Include it in private backups. See `inc/attachment-storage.md` in the plugin for hosting requirements and configuration.

Existing files from 1.0.0 need an explicit securing step. Activation does not move or delete files; follow the Upgrade Notice before upgrading.

= How do email notifications work? =

In Settings > Support Portal > Emails, configure the sender name/address, administrator notification recipient, and new-topic notifications and new-reply notifications to topic owners and previous participants. Configure follow-up reminders after a customer reply and closing warnings after a support reply. Reminder durations are measured in hours; 0 or an empty value disables that reminder.

Topic notifications and reminders require WordPress posts/comments storage. They use WordPress mail delivery; your hosting or SMTP configuration determines actual delivery. Save settings before using the fixed-sample preview and test email. An accepted test confirms only that the mail transport accepted the message, not inbox delivery.

Support notifications use the portal email template. In WordPress posts/comments mode, you can also apply that template to other WordPress emails; this option is enabled by default. Topic notification switches do not disable registration or password-reset emails.

= When are topics automatically resolved? =

In WordPress posts/comments storage, the default is 48 hours after the latest support reply without a customer response. Change this duration under General, or enter 0 or leave it empty to disable auto-resolve. Resolving a topic also closes replies.

A closing warning must be earlier than the auto-resolve duration. Disabling email does not disable auto-resolve. Changes apply to existing open topics, so shortening the duration or re-enabling auto-resolve can resolve overdue topics on a later background run. Resolved topics are not reopened automatically. Background processing depends on WordPress cron, so timing may vary with the site's cron setup.

= Can I change reply history and topic list sizes? =

Yes. Configure reply collapsing, the number of replies shown initially and per expansion (1–100), and topics per page (1–100). Defaults are five replies per batch and eight topics per page. Topic page size also applies to embedded lists and customer profiles.

Choose WordPress avatars or initials only. WordPress avatar visibility is respected, and initials are the fallback when an avatar is unavailable.

= How do I embed support components in my theme? =

Create a regular WordPress page with a slug outside portal routes such as `/support/`, `/topics/`, `/new/`, or `/login/`, then add a shortcode listed above. Use one copy of each shortcode per page, and place login and lost-password forms on separate pages.

In WordPress posts/comments storage, disable "Render standard WordPress pages with the plugin UI" and use pretty permalinks for theme pages. The homepage remains the portal dashboard. In isolated storage, regular pages already use the theme. Topic details, replies, and successful topic submissions continue to open the dedicated portal.

Exclude all shortcode pages from page, CDN, and fragment caches, including for guests. The plugin sends no-cache signals, but upstream caches can respond before WordPress runs. Page builders must render shortcodes server-side before footer scripts. See `docs/theme-shortcodes.md` in the source repository for detailed integration guidance.

= Does YoOhw receive support conversations or attachments? =

No. Support Portal does not transmit support conversations, attachments, or account data to YoOhw. Email notifications are sent through your site's configured WordPress mail transport to the relevant recipients.

= What happens when I uninstall the plugin? =

The plugin removes its settings, scheduled hooks, and custom capabilities. Support conversations, attachments, users, categories, and isolated support tables are intentionally retained to reduce the risk of accidental data loss. Private attachment files and recovery copies also need to be covered by your own retention policy.

== Privacy ==

Support data can include account identifiers, names, message content, timestamps, categories, status, and, in WordPress posts/comments storage, uploaded files. Conversations and files are stored on your WordPress site. Notifications may include conversation details and links, processed through your configured mail transport.

The plugin adds suggested text to the WordPress privacy-policy guide. Site owners are responsible for access policies, retention, private backups, and verified export or erasure requests.

== External Services ==

No YoOhw account or external help desk service is required. Support Portal does not send support data to YoOhw or provide its own external mail service. If your WordPress mail transport uses an external provider, notification content is processed through that provider.

When WordPress avatars are enabled, WordPress may request avatars from Gravatar or a configured avatar provider. Choose initials only to avoid avatar requests in conversation replies.

An optional documentation URL is a normal outbound link and does not send support conversation content to its destination.

== License ==

Support Portal is licensed under the GNU General Public License v2.0 or later. See `license.txt` for the full license text.

Third-party components and their license notices are documented in `third-party-licenses.txt`.

== Changelog ==

= 1.1.0 =
* Security: New support attachments and image variants use private filesystem storage outside the web document root, with upload rejection when storage verification fails. Existing installations must explicitly secure old support files; see the Upgrade Notice.
* New: Embed topic submission, login, password recovery, and authorized topic lists in regular WordPress theme pages using shortcodes.
* New: Configure attachments for new topics and replies separately, including file count, file-size limits, permitted image/video/ZIP types, and protected preview or download-link display.
* New: Configure reply-history batch size, topics per page, and WordPress avatars or initials.
* Improved: Conversations align your replies on the right, initially show the newest reply batch in chronological order, and let you reveal older messages without extra requests; the full history remains available without JavaScript.
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

== Upgrade Notice ==

= 1.1.0 =
Before upgrading from 1.0.0, block public access to old support files and relocate owned attachments with `wp ysp attachments secure <id>`. New uploads require verified private storage. Back up files/database and follow `inc/attachment-storage.md`; activation does not migrate files.
