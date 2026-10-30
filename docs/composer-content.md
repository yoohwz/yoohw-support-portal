# Composer content

New topic/reply forms share `YoOhw_Support_Composer::editor_settings()` and `assets/js/editor.js`, including theme shortcodes and isolated replies. Paste normalization is undoable and warns when unsupported media is removed. Inline code and code block actions use native TinyMCE icons with translated tooltips; Clear formatting and Paste as text remain available.

Server normalization runs only on new portal submissions. It keeps meaningful prose/list/quote/table structure while removing presentation attributes, hidden markup and redundant blank formatting. Headings are bounded to h3/h4. Unsupported pasted media and visually empty content produce a submission error without JavaScript. Existing capability, nonce, category and topic checks remain authoritative.

Code is isolated before whitespace/link/HTML cleanup. Stored code uses escaped text in `code` or `pre.wp-code-block > code`. HTML entities, Unicode, backslashes, indentation and blank lines are retained as code text; complete backtick/fenced Markdown is supported without general Markdown interpretation. Clipboard CRLF is normalized to LF by the editor. No highlighting service, remote resource fetch or inline-media upload is introduced. Attachments retain their existing security path.

There is no migration, old-content rewrite or display-time cleanup filter. The legacy public cleanup helper remains available with its previous behavior. Native topic insertion explicitly supplies slashed data to WordPress to preserve backslashes.

## Focused checks

On the Local site, run `wp --skip-themes eval-file tests/composer-tests.php` using the site's PHP/ini runtime. The suite intercepts mail and redirects, creates disposable native/isolated records and cleans them up. It requires existing isolated tables; it does not change site settings or install schemas.

DOM fixtures can be run without a browser or network during testing:

```sh
npm install --prefix /tmp/ysp27-dom --no-audit --no-fund jsdom@29.1.1
NODE_PATH=/tmp/ysp27-dom/node_modules node tests/composer-editor-tests.js
```

The npm runtime is test-only and is not included in the plugin distribution. Also run the existing theme-shortcode/reply/feature suites, relevant native/isolated/cold-activation regressions, PHP/JS syntax, focused Plugin Check and distribution contracts. Verify real TinyMCE paste, media feedback, code actions and Undo in the Local browser.

## Pending attachments

`assets/js/attachments.js` manages the native `yoohw_attachments[]` field on portal and theme-hosted composers. Picker and drop batches append; exact matches by name/size/last-modified/type are not added twice. Rejecting an over-limit batch keeps the previous selection. The × buttons remove pending files only; the underlying FileList is synchronized before normal multipart submission. Form reset clears the selection. Server upload policy and protected-file operations are unchanged. Browsers without DataTransfer retain native file selection.

Serve the repository locally and open `tests/attachment-selection-tests.html` in a browser for real File/DataTransfer/FormData fixtures (no form submissions or uploads). Verify the real picker by choosing a file, choosing another file in a second pass, then removing one. `tests/theme-shortcodes-tests.php` also checks that shared assets enqueue before theme form rendering in both storage modes.
