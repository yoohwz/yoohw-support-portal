# Support Portal attachment storage

Version 1.1.0 stores new support files outside the web document root. Attachments
retain their WordPress IDs, topic/reply ownership, original names, generated image
sizes, and authorized download links. Isolated storage still does not accept uploads.
Activation and storage-mode switches never move or delete existing attachments.

## Hosting prerequisites

The default root is `yoohw-support-private` beside the real HTTP document root,
with one subdirectory per site. PHP must be able to create and write this directory.
For a different location, the administrator can define `YOOHW_SUPPORT_PRIVATE_ROOT`
in `wp-config.php` as an absolute existing directory or a child of an existing
parent directory, outside **all** web-served roots. Do not put it under WordPress,
public uploads, another virtual host, CDN origin, static alias, symlink from a
public directory, or a public backup/download service. Include this directory in
private backups and preserve it when moving the site; absolute attachment paths
must be updated by the operator when filesystem paths change.

The plugin checks canonical paths against the HTTP document root, WordPress,
content and uploads roots, checks write access, and tests known public URL mappings
with a disposable non-sensitive canary. It caches successful HTTP checks for five
minutes. A failed/unknown layout or unavailable loopback HTTP blocks new uploads;
there is no public-upload fallback. Only Local development skips self-signed TLS
certificate validation. Public Media Library uploads and site icons are unaffected.

**The hosting operator must audit additional virtual hosts, aliases, symlinks and
CDN mappings.** WordPress cannot discover arbitrary server configuration. Do not
expose the private root through any of them. Verify anonymous HTTP against originals
and image derivatives on each supported host. Apache/IIS and custom host layouts
must be verified on the actual host; no blanket compatibility certification is made.
If the reported document root does not contain WordPress (for example an alias-only
install), uploads fail closed pending a hosting layout decision.

Run `wp ysp attachments verify` with the site's PHP runtime. WP-CLI must have the
same verified `DOCUMENT_ROOT` as the HTTP server; if it does not, the operator can
supply the **actual audited server root**, for example:

```sh
wp --exec='$_SERVER["DOCUMENT_ROOT"]="/actual/server/document-root";' ysp attachments verify
```

Do not guess a root merely to make the check pass. An administrator notice reports
storage errors. The command verifies the known public mappings; the operator still
owns the alias/CDN audit.

## Upgrade from 1.0.0: secure existing files explicitly

1. Back up the database, public uploads and any existing private storage. First
   configure server-level denial of the legacy support directory, flush/restrict
   upstream caches and verify anonymous GETs cannot retrieve originals or variants.
   On Nginx, an operator can add the following **to the correct site's server block**
   (adjust any nonstandard uploads URL), test the configuration, and reload Nginx:

   ```nginx
   location ^~ /wp-content/uploads/yoohw-support-portal/private/ {
       return 403;
   }
   ```

   `.htaccess`, `web.config`, randomized filenames and an index file do not secure
   Nginx static delivery. Older owned files elsewhere in uploads need exact deny
   rules for their original/variant URLs until relocation. Never deny unrelated
   media. This is operator responsibility; the plugin does not change server config.
2. In WordPress posts/comments mode, run `wp ysp attachments verify`, then inventory
   up to 100 attachment records at a time:

   ```sh
   wp ysp attachments inventory --after=0 --limit=100
   ```

   Only owned support attachment IDs are printed. Public site icons/logos are
   excluded. Use `next-after` for the next page, including pages with no owned IDs;
   `next-after=end` ends the scan. Inventory does not copy, delete or modify files.
3. For each owned ID, preview the recoverable copy phase if desired:

   ```sh
   wp ysp attachments secure 123 --stage-only
   wp ysp attachments secure 123
   ```

   Replace `123` with the inventoried attachment ID. The command is deliberately
   bounded to one attachment family. Staging is **not secured**: public originals
   still exist. The full command copies original/variants and core image-edit
   backups to private storage, verifies every SHA-256, saves recovery information
   in attachment metadata, switches the absolute attachment path, and only then
   retires the inventoried public source files. Unrelated/shared media, symlinked
   files, unsafe variant names, missing files and changed metadata stop the command.
4. A partial failure is **not resolved**. Keep the server deny active and rerun the
   same ID after fixing the reported problem. Copy conflicts are never overwritten.
   An interrupted retirement resumes from verified private backup copies, without
   recreating public originals. Verify all old direct URLs (including generated
   sizes) anonymously after completion; confirm owner/staff original, preview and
   range downloads still work while guests/unrelated customers are denied.

Recovery copies remain under `relocated-<id>/backup` in private storage. The
`_yoohw_private_relocation` attachment meta retains original paths, metadata and file
hashes. Before the metadata switch, aborting/stopping leaves original data intact.
After the switch, prefer resuming the same command. A manual rollback requires the
operator's database/files backup and **verified server denial before restoring any
public path**; never restore an anonymously readable copy as a rollback. Do not
remove recovery copies until the upgrade is validated and a separate private backup
exists. Site deletion/retention policies must also account for these private backups.
No backup cleanup runs automatically, including during activation or uninstall.

If a command reports shared media, missing originals or a host configuration that
cannot establish private storage, stop and resolve that specific case. Do not claim
the site's legacy attachments are secure from successful tests on another site.
