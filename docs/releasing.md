# Support Portal — WordPress.org release

This is a release-only control plane. It does not replace normal YSP PR review,
`YSP Required CI`, independent review for CONTROLLED work, or Human `Finalize`.
Merging a release-tooling change never grants publication authority.

The design follows the proven `wc-order-splitter` publisher pattern while keeping
YSP-specific release machinery smaller: protected-main control, immutable Prepare,
dry-run-first SVN publication, a Human-gated production Environment, atomic SVN
mutation, read-only recovery, and GitHub Release only after public verification.

## Trust model

- Manual release workflows execute only from protected `main`.
- The release control plane is checked out from the workflow's exact main SHA.
- A prepared candidate is checked out separately and treated as release data.
- Before staging, the candidate copy of `scripts/stage-distribution.sh` is replaced
  by the trusted helper from the protected-main control checkout. Candidate release
  scripts are therefore not trusted execution authority.
- `scripts/stage-distribution.sh` is the only product-payload staging definition.
- Prepare stages the product twice and requires byte-identical deterministic ZIPs.
- WordPress Plugin Check runs against the exact `rc/payload` that is later uploaded.
- The preparation artifact preserves hidden tracked product files and binds candidate
  SHA, version, staged tree SHA-256, per-file SHA-256 inventory and package SHA-256.
- Publication authenticates the successful Prepare run and reproduces the candidate
  staged tree before reading WordPress.org SVN.
- Normal publication never modifies the WordPress.org `assets/` directory.
- The WordPress.org SVN password is available only to the single production commit
  step. It is sent to SVN on stdin, removed from the SVN child-process environment,
  and never placed on a command-line argument.
- Dry-run, preflight, verify-only and GitHub Release jobs never receive the SVN
  password.
- A changed candidate, version or preparation run requires a new release sequence.

## One-time owner setup

A repository administrator and WordPress.org plugin committer must configure:

1. GitHub Environment `wordpress-org-production`.
   - Require Human reviewer approval.
   - Prevent self-review where the plan supports it.
   - Restrict deployment branches to protected `main`.
2. In that Environment, add variable `WPORG_SVN_USERNAME` with the exact value
   `yoohw`. Production fails before any mutation if this value is absent or different.
3. In that Environment, add secret `WPORG_SVN_PASSWORD` using the WordPress.org
   **SVN-specific password** for `yoohw`. Enter it directly in GitHub. Never paste it
   into ChatGPT/Codex, Issues, PRs, workflow inputs, artifacts, summaries or logs.
4. Protect numeric release tags such as `1.0.0` from update, force-update and
   deletion. Authorized production publication must be able to create a new
   **annotated** tag, but a created release tag must be immutable.
5. Keep the existing `main` ruleset and exact required `YSP Required CI` check.
   Do not add bypass actors or weaken protection to make a release pass.

The workflows intentionally fail closed when the production Environment variable or
secret is not configured. Do not weaken that behavior to perform a release.

## Before Prepare

A release candidate must already be merged on protected `main` and have the exact
plugin version prepared in both places:

- `Version:` in `yoohw-support-portal.php`;
- `Stable tag:` in `readme.txt`.

The version must be numeric (`1.0`, `1.0.0`, etc.). Release tooling itself never
silently changes product version metadata.

Before publication, complete the normal YSP task lifecycle for any product/version
changes, including final CI and any required fresh independent review. The release
workflows are not a substitute for that acceptance.

## Step 1 — Prepare immutable candidate

From GitHub Actions, run **Prepare WordPress.org Release Candidate** on `main`.

Inputs:

- `candidate_sha`: exact current protected-main SHA (full 40 characters);
- `version`: exact plugin Version / readme Stable tag.

Prepare fails closed unless `candidate_sha` equals the workflow's exact protected
`main` SHA. It then:

1. checks out trusted release control and a separate candidate tree;
2. replaces the candidate staging helper with the trusted control-plane helper;
3. validates Version/Stable tag;
4. stages the canonical WordPress.org payload twice;
5. requires identical staged product identity;
6. builds two deterministic STORE-compressed ZIPs with fixed timestamps;
7. requires byte-identical packages;
8. runs WordPress Plugin Check against the exact prepared `rc/payload`;
9. uploads exactly one immutable artifact named
   `ysp-wporg-<version>-<candidate_sha>` containing:
   - `payload/`;
   - `yoohw-support-portal-<version>.zip`;
   - `release-manifest.json`;
   - `preparation-record.json`.

Keep the successful Prepare run ID. A failed or superseded Prepare run is not
publication authority; fix through the normal task flow and create a new Prepare.

## Step 2 — Dry-run publication

Run **Publish Support Portal to WordPress.org** with:

- `operation=publish`;
- successful `preparation_run_id`;
- same `candidate_sha`;
- same `version`;
- `dry_run=true`.

No production Environment and no SVN credential are reachable on this path.
The workflow authenticates the Prepare run/artifact, reproduces the staged product,
checks out the public WordPress.org SVN repository, and records a read-only snapshot
of trunk/assets plus target-tag absence. It then performs a second fresh remote
checkout/recheck and stages `trunk` + `tags/<version>` locally only.

The dry-run must finish successfully before requesting a production run. Review the
preflight evidence, especially:

- target tag is absent;
- candidate/version/product tree match Prepare;
- staged SVN delta is limited to `trunk` and the new target tag;
- assets are untouched.

## Step 3 — Production publication

After explicit Human release authority, dispatch a **new** Publish run with the
same preparation/candidate/version and `dry_run=false`.

The read-only preflight job runs first. The production job then waits on the
`wordpress-org-production` Environment. Approve only if the preflight identifies
the exact intended candidate and remote state.

After approval, the production job:

1. reauthenticates the immutable Prepare artifact and candidate;
2. downloads the approved same-run preflight;
3. performs a fresh WordPress.org SVN checkout and requires the remote snapshot to
   still equal the approved preflight;
4. restages the exact payload locally;
5. confirms the production Environment is configured for SVN user `yoohw`;
6. creates or verifies the immutable **annotated** numeric Git tag `<version>`;
7. uses the already rechecked SVN working copy and attempts **one** atomic SVN
   commit covering `trunk` + `tags/<version>`;
8. verifies a unique SVN revision, exact traceability message, expected SVN author
   `yoohw`, and no asset changes;
9. verifies WordPress.org trunk and tag product identities;
10. checks the public versioned download
   `downloads.wordpress.org/plugin/yoohw-support-portal.<version>.zip`.

If the public ZIP is already propagated and matches the prepared product, the state
is `WPORG_PUBLIC_RELEASE_VERIFIED`. If SVN is verified but the public download is
not ready yet, the state is `WPORG_PROPAGATION_PENDING` and no recommit is allowed.

WordPress.org may require a separate release-confirmation action before a newly
committed version becomes public. Treat that as propagation, not as a reason to
recommit SVN. Complete the confirmation only through WordPress.org's own UI/email
flow, then use `verify-only` to continue public verification. Do not store or relay
private confirmation URLs/tokens in GitHub artifacts, Issues, PR comments or chat.

Only `WPORG_PUBLIC_RELEASE_VERIFIED` can reach the separate GitHub Release job.
That job is also Environment-gated, re-verifies public identity, binds the immutable
annotated numeric Git tag, and uploads only the authenticated release ZIP and
manifest.

## Verify-only and recovery

Use `operation=verify-only` with:

- the original successful Prepare run ID/candidate/version;
- `original_publish_run_id`: the production Publish run that attempted SVN commit;
- `dry_run=true` for read-only recovery/propagation checks;
- `dry_run=false` only when public WordPress.org verification should also permit
  the separately Environment-gated GitHub Release job.

Verify-only never receives the SVN password and never commits SVN. It authenticates
the original production run and original non-dry-run preflight, requires the
immutable annotated Git tag to still point to the candidate, and reconstructs
release state from fresh public SVN/download evidence.

Use verify-only when:

- SVN commit succeeded but later verification/artifact persistence failed;
- a commit response was lost or timed out (`SVN_COMMIT_OUTCOME_UNKNOWN`);
- the production run ended at `WPORG_PROPAGATION_PENDING`;
- WordPress.org release confirmation has been completed and public propagation now
  needs to be verified.

Do **not** retry a production commit merely because the commit step timed out or a
later verification step failed. First reconcile with verify-only.

## Expected terminal states

- `RC_PREPARED` — immutable candidate package is ready; no external mutation.
- `READ_ONLY_PUBLICATION_PREFLIGHT` — exact Human approval target captured.
- `FINAL_PRE_MUTATION_REMOTE_RECHECK` — approved SVN snapshot still current.
- `TAG_SEALED` — immutable annotated numeric Git tag points to candidate.
- `SVN_ATOMIC_COMMIT_RECORDED` — one SVN commit response reported a revision.
- `WPORG_PROPAGATION_PENDING` — SVN identity is correct; public ZIP is not ready.
- `WPORG_PUBLIC_RELEASE_VERIFIED` — SVN and public WordPress.org package match.
- `GITHUB_RELEASE_VERIFIED` — public WordPress.org release remains verified and the
  matching GitHub Release/assets are created or reconciled.

## Failure rules

- Existing `tags/<version>` at preflight: stop. Do not overwrite a published tag.
- WordPress.org SVN changes between preflight and approval/recheck: stop and dispatch
  a fresh release run so the Human sees a new approval target.
- Prepared artifact/candidate/tree/package mismatch: stop and create a new Prepare.
- Missing/mismatched production Environment configuration: stop before mutation.
- Any attempt to alter `assets/`: stop.
- SVN commit failure/timeout with uncertain outcome: do not recommit; use verify-only.
- Public ZIP exists but differs from prepared product identity: stop as a release
  integrity failure; do not create a GitHub Release.
- Git tag or existing GitHub Release asset points to different bytes/commit: stop.

## Release boundary

These workflows exist to execute a separately authorized release. They are not
triggered by merge, tag push or PR comments. No workflow parses approval prose.
Merging YSP-5 creates capability only; it does not publish version 1.0.0 or any
future version automatically.
