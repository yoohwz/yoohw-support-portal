# YSP Lean Delivery

The workflow optimizes for fast implementation loops while keeping strong controls only where Support Portal's private-data and safety boundaries justify them.

## Sources of truth

Use one authority for each fact:

| Fact | Authority |
| --- | --- |
| Task goal/scope/invariants/acceptance | GitHub Issue for non-trivial work, or the draft PR for very small work |
| Current implementation | Git branch / PR head |
| Diff and ancestry | Git |
| CI | GitHub Actions/Checks |
| Independent review | Exact-head PR review/comment when required |
| Acceptance | SHA-bound ChatGPT record on the PR after successful final validation |
| Merge | GitHub PR merge record |
| Product safety | `docs/support-data-safety.md` |
| Release/publication | Separate Human authorization |

Do not create another mutable task-state database, allocator, evidence ledger or approval parser.

## Local development model

The dedicated Support Portal Codex project opens the plugin source folder that is installed on the project's Local WordPress site.

Codex edits that plugin source directly during implementation. Because the same files are loaded by the Local site, relevant changes can be exercised immediately in the real Local WordPress environment without copying or deploying the plugin elsewhere first.

Use this direct Local workflow by default:

`edit plugin source -> run focused checks -> exercise relevant behavior on Local -> continue implementation`

Do not create another clone or worktree for ordinary work. Local runtime checks are encouraged where they add useful evidence, but they are not mandatory ceremony for trivial docs or non-runtime changes.

## Create, Run, Acceptance Review, Merge

1. **Create** — ChatGPT reduces the request to goal, scope, acceptance, relevant invariants, expected validation and `STANDARD` or `CONTROLLED`. Use Issue `#N` as `YSP-N` when an Issue is warranted.
2. **Run** — Codex recovers current Issue/PR/head/base from GitHub, implements directly in the project's Local plugin source folder, runs focused checks and maintains one Draft PR. Do not wait for full final CI on every edit.
3. **Plan Review / Review** — `Plan Review YSP-N` resolves only an actual unresolved product/security/data/architecture/compatibility decision. For `CONTROLLED`, a fresh independent Technical Review of the frozen candidate is required; `Review YSP-N` is a standalone fallback, not an extra mandatory process for `STANDARD`.
4. **Acceptance Review YSP-N** — ChatGPT first performs an exact-candidate precheck while the PR is Draft. If blocker-free, it marks the unchanged PR Ready, observes final `YSP Required CI` on the current candidate to a terminal result, fresh-verifies head/base and any required review, and records explicit SHA-bound Acceptance PASS in the PR only if all requirements pass. It never merges.
5. **Merge YSP-N** — only a subsequent explicit Human instruction authorizes merge of the unchanged accepted candidate. ChatGPT rechecks exact SHA, current CI, required review, mergeability and blockers. GitHub makes the final merge/protection decision. It does not authorize release or deployment.

`Continue YSP-N` means recover and resume; it never means restart the task or ask the Human to repeat GitHub-recoverable context.

## Risk levels

### STANDARD

Use for ordinary bounded work that does not change sensitive-data or safety-control semantics. Examples include presentation, templates, ordinary JavaScript/settings, copy/docs, understood bugs, test/tool maintenance and behavior-preserving refactors.

Flow:

`Create/brief -> Run -> draft PR -> focused checks -> Iteration Gate -> freeze candidate -> Acceptance Review (precheck -> YSP Required CI -> SHA-bound Acceptance) -> READY_TO_MERGE -> Merge`

Independent review is optional and should not be added merely because runtime code changed.

### CONTROLLED

Use when work materially affects authentication, authorization, private visibility, protected files, REST/AJAX/admin-post security, persistence/schema/storage semantics, privacy, email recipients/private disclosure, destructive cleanup, uninstall retention, distribution/release safety or equivalent architecture.

CONTROLLED uses the same flow plus exactly one fresh independent technical review of the frozen candidate, normally before the internal Acceptance precheck. A head-changing correction requires refreshed affected review and CI evidence before final Acceptance.

A separate Plan Review is required only when the task boundary is not sufficient to decide a product/architecture/data/permission/compatibility question. A fully bounded CONTROLLED bug/fix may implement directly.

## Draft iteration versus final candidate

Development cost is reduced by separating cheap feedback from merge evidence.

### Draft PR: Iteration Gate

Run fast checks only:

- repository/workflow/distribution contracts;
- `git diff --check`;
- PHP syntax on one modern runtime;
- JavaScript syntax;
- source-level safety characterization.

Superseded runs for the same PR are cancelled. Keep the PR Draft during implementation, corrections, Technical Review and the internal Acceptance precheck; Ready starts final-candidate validation.

`Iteration Gate` is feedback only and must never satisfy branch protection.

### Final non-draft candidate: YSP Required CI

The stable merge check is exactly `YSP Required CI`. It aggregates required final checks and fails closed on failure, cancellation or unexpected skip.

Final validation stays intentionally bounded:

- repository/distribution contracts;
- PHP 7.4 syntax support floor and modern PHP syntax;
- JavaScript syntax;
- current source-level safety characterization;
- exact staged WordPress.org payload checks;
- WordPress Plugin Check against that staged payload.

Do not add a broad WordPress/PHP Cartesian runtime matrix to every PR. Add focused runtime coverage as the test harness grows and reserve full compatibility/release matrices for explicit release work.

## Review

For CONTROLLED work, the independent reviewer:

- starts in a fresh review context and does not reuse the implementer's verdict;
- reviews the complete current candidate diff and task boundary;
- checks the affected safety invariants and negative paths;
- may use the same dedicated Codex project and Local WordPress environment for focused verification;
- records blockers or PASS against the reviewed candidate.

No separate local clone/worktree is required just to manufacture review isolation. Keep review lightweight and proportional to the risk.

A head-changing correction invalidates the old terminal review. Keep corrections in the same PR. Repeated material blockers or a required architecture/scope change stop for a boundary decision instead of starting an unlimited review loop.

## Acceptance Review and Merge contract

For `Acceptance Review YSP-N`, ChatGPT checks the current Issue/scope, frozen PR head and base, focused validation and any required independent review while the PR is still Draft. This internal precheck is not final Acceptance. If no blocker remains, mark the **unchanged** PR Ready and observe its `YSP Required CI` on the current candidate to a terminal result. Recheck head/base compatibility, review and blockers after CI. Only then record explicit SHA-bound Acceptance PASS on the PR and report `READY_TO_MERGE`. A missing, stale, queued, cancelled or failed check never produces Acceptance PASS; report the precise blocker or pending state instead.

For `Merge YSP-N`, the Human's separate command authorizes only the accepted, unchanged candidate. Immediately before merging, ChatGPT verifies:

- the current PR head exactly matches the SHA recorded in final Acceptance;
- current target `main` is compatible and GitHub reports the required `YSP Required CI` success for the current PR candidate;
- the required independent Technical Review is current for `CONTROLLED` work;
- there is no unresolved material blocker or scope drift and the PR is mergeable;
- merge is not treated as permission to version, tag, package, publish or deploy.

A changed candidate head invalidates previous Technical Review, CI and Acceptance evidence as applicable; return to Draft for corrections and repeat affected review/Acceptance. A moved target base requires a fresh compatibility and CI assessment before merge. Neither `Run`, `Review` nor `Acceptance Review` grants merge authority.

## Distribution

`bash scripts/stage-distribution.sh <source> <destination>` stages the installable plugin from tracked product files, with `.distignore` retained as defense-in-depth. CI verifies that repository/development artifacts do not enter the staged tree. Plugin Check runs on the staged tree, not the raw repository.

Do not create ZIPs in normal PR CI.

## Release boundary

Merge is not release. Version changes, release packaging, tags, GitHub Releases, WordPress.org publication and deployment require separate explicit Human authority. Add heavier release tooling only when a real release workflow requires it.

## Workflow evolution

Do not optimize by adding machinery in advance. Measure actual repeated CI/review friction first. After several real tasks, change this workflow only for a concrete observed failure mode or material cost, and keep the correction smaller than the problem it solves.
