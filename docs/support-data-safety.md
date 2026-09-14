# Support Portal data-safety contract

This document records the minimum safety boundaries that future work must preserve unless a Human-approved CONTROLLED task explicitly changes them.

## Private conversation visibility

- Anonymous visitors must not read private support conversations or protected attachments.
- A normal customer must not gain access to another customer's topic merely by knowing an ID, slug, public key, attachment ID, REST route or direct URL.
- Topic owners may access their own support conversations according to the active storage mode and category-access policy.
- Staff access must be capability-based; broad WordPress roles or authentication alone must not silently become support-data authority.
- Customer/profile surfaces must remain restricted to explicitly authorized support capabilities.

## Authorization

- Sensitive actions require both the appropriate WordPress capability/relationship check and the relevant nonce/request validation where the transport supports state change or private resource delivery.
- Category access codes and role-based access are authorization inputs, not presentation-only preferences.
- Alternative entry points (REST, admin-post, native comments/posts, direct attachment URLs, shortcodes and dedicated routes) must not bypass the portal's visibility rules.

## REST and native WordPress surfaces

The plugin intentionally uses native WordPress posts/comments in one storage mode, while core REST controllers do not understand portal privacy semantics. Changes to REST routing, post/comment exposure, media/search/users visibility or comment writes are therefore CONTROLLED.

Do not weaken current fail-closed restrictions without an explicit replacement contract and negative tests.

## Storage modes

The two supported storage modes are independent sources of support conversation data:

- WordPress posts/comments;
- isolated plugin tables.

Switching the configured mode must not implicitly migrate, merge, rewrite or delete the inactive dataset unless a separately approved migration task explicitly defines rollback and failure behavior.

Schema/upgrade changes are CONTROLLED. Preserve existing data on failed or partial upgrade paths and avoid creating destructive cleanup as a side effect of ordinary activation/configuration.

## Protected attachments

- Support attachments must remain private from unauthorized direct access.
- Authorization to stream a protected file must be derived from the related support topic/reply and current user authority, not from possession of the attachment URL alone.
- Public site media such as the site icon/logo must not become private merely because it is accidentally related to support content.
- Path/variant selection must stay within the intended attachment file family; do not permit arbitrary filesystem traversal.
- Attachment deletion is destructive. Delete only attachment identities actually owned by the support object being deleted, and keep unrelated Media Library files outside the deletion authority.

Any change to upload destination, direct-access protection, byte-range streaming, image variants, ownership resolution, cleanup or deletion is CONTROLLED.

## Notifications and private disclosure

Support emails can disclose topic titles, message previews, participant names and links. Recipient-selection changes are privacy-sensitive.

- Do not send private conversation content to an address that is not authorized by the current notification contract.
- Avoid duplicate/replayed notification sends where current idempotency markers exist.
- Template/presentation-only email changes may be STANDARD only when recipients, triggers and disclosed data are unchanged.

## Authentication and password flows

Login, registration, lost-password and reset-password routing are CONTROLLED whenever behavior or authorization changes. Presentation-only changes may remain STANDARD only when transport, tokens, identity resolution and redirect authority are unchanged.

## Destructive cleanup and uninstall

Existing uninstall behavior intentionally removes plugin settings/scheduled hooks/capabilities while retaining support conversations, users, categories, attachments and isolated support tables to avoid accidental data loss.

Do not broaden uninstall or cleanup deletion without explicit Human-approved destructive-data semantics, rollback/recovery analysis and strong negative tests.

## Evidence expectations

For CONTROLLED changes, validation should emphasize the negative space around the changed boundary. Depending on scope, exercise representative combinations of:

- anonymous user;
- topic owner;
- unrelated authenticated customer;
- support staff with the relevant capability;
- native versus isolated storage;
- direct versus alternate transport.

Do not claim a runtime combination was tested when only source/static characterization was executed. Foundation source-characterization tests preserve current structural guards; focused runtime regression coverage should be added as real tasks touch each boundary.
