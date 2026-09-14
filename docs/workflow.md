# YSP Lean Delivery

The workflow optimizes for fast implementation loops while keeping strong controls only where YoOhw Support Portal's private-data and safety boundaries justify them.

## Sources of truth

Use one authority for each fact:

| Fact | Authority |
| --- | --- |
| Task goal/scope/invariants/acceptance | GitHub Issue for non-trivial work, or the draft PR for very small work |
| Current implementation | Git branch / PR head |
| Diff and ancestry | Git |
| CI | GitHub Actions/Checks |
| Independent review | Exact-head PR review/comment when required |
| Merge | GitHub PR merge record |
| Product safety | `docs/support-data-safety.md` |
| Release/publication | Separate Human authorization |

Do not create another mutable task-state database, allocator, evidence ledger or approval parser.

## Codex project binding

Codex executes repository work from the dedicated YoOhw Support Portal project. The project's configured source folder is the canonical local workspace; repository workflow files intentionally do not hardcode a host-specific absolute path.

The configured folder must be the Git working tree for `yoohwz/yoohw-support-portal`. Before `Run`, `Continue` or `Technical Review`, Codex verifies that the Git toplevel matches the source folder supplied by the current project, checks `origin`, current branch/HEAD and working-tree status, then fetches current `origin`. A mismatch is a fail-closed `HUMAN_DECISION_REQUIRED`; Codex must not silently select another Local site, plugin folder or repository.

Normal implementation and review use this same dedicated project and configured source folder. Do not create another clone or worktree merely to satisfy workflow ceremony. Disposable build/test outputs belong in external temporary directories. A separate checkout is exceptional and requires an explicit task-specific reason.

For independent review, isolation is **context isolation**, not mandatory filesystem duplication: start a fresh Codex review context in the same dedicated project, keep the canonical source tree read-only, bind the review to the exact candidate head/diff, and do not reuse the implementer's scratch reasoning or verdict. If a test intentionally mutates files under the repository root, use current CI evidence or an explicitly disposable external copy rather than mutating the canonical Codex project workspace.

## Create, Run, Finalize

1. **Create** — ChatGPT reduces the request to goal, scope, acceptance, relevant invariants, expected validation and `STANDARD` or `CONTROLLED`. Use Issue `#N` as `YSP-N` when an Issue is warranted.
2. **Run** — Codex recovers current Issue/PR/head/base from GitHub inside the dedicated project, implements on one task branch in the configured working tree, runs focused checks and maintains one draft PR. Do not wait for full final CI on every edit.
3. **Finalize** — after the candidate is frozen, final CI and any required independent review must be current. Human `Finalize YSP-N` conditionally authorizes ChatGPT to perform final Acceptance and squash-merge only that unchanged acceptable candidate. GitHub makes the final merge/protection decision.

`Continue YSP-N` means recover and resume; it never means restart the task or ask the Human to repeat GitHub-recoverable context.

## Risk levels

### STANDARD

Use for ordinary bounded work that does not change sensitive-data or safety-control semantics. Examples include presentation, templates, ordinary JavaScript/settings, copy/docs, understood bugs, test/tool maintenance and behavior-preserving refactors.

Flow:

`Create/brief -> Run -> draft PR -> focused checks -> Iteration Gate -> freeze candidate -> YSP Required CI -> READY_TO_FINALIZE -> Finalize`

Independent review is optional and should not be added merely because runtime code changed.

### CONTROLLED

Use when work materially affects authentication, authorization, private visibility, protected files, REST/AJAX/admin-post security, persistence/schema/storage semantics, privacy, email recipients/private disclosure, destructive cleanup, uninstall retention, distribution/release safety or equivalent architecture.

CONTROLLED uses the same flow plus exactly one fresh independent technical review of the frozen candidate. Review and final CI should run in parallel when practical.

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

Superseded runs for the same PR are cancelled.

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

- starts in a fresh Codex review context inside the dedicated YoOhw Support Portal project and does not share the implementer's scratch reasoning;
- verifies the configured source root, Git remote and exact candidate head before assessing the change;
- reviews the complete exact candidate diff and task boundary;
- keeps the canonical source working tree read-only; no source edits, commits, branch switching, reset, stash or clean are permitted during review;
- checks the affected safety invariants and negative paths;
- reruns relevant focused checks only when they can remain read-only with respect to the canonical source tree, otherwise relies on current CI or uses an explicitly disposable external copy;
- records blockers or PASS against the exact reviewed head.

A head-changing correction invalidates the old terminal review. Keep corrections in the same PR. Repeated material blockers or a required architecture/scope change stop for a boundary decision instead of starting an unlimited review loop.

## Finalize contract

Before merge, ChatGPT verifies at least:

- the PR head is the candidate identified to the Human;
- current target `main` is compatible and the native PR merge candidate has current `YSP Required CI` success;
- any required independent review covers the current head or only an explicitly verified non-semantic documentation delta remains;
- no unresolved blocking finding or unapproved scope drift exists;
- release/publication was not smuggled into merge authority.

If the candidate changes, do not merge under the old Finalize authority.

## Distribution

`bash scripts/stage-distribution.sh <source> <destination>` stages the installable plugin from tracked product files, with `.distignore` retained as defense-in-depth. CI verifies that repository/development artifacts do not enter the staged tree. Plugin Check runs on the staged tree, not the raw repository.

Do not create ZIPs in normal PR CI.

## Release boundary

Merge is not release. Version changes, release packaging, tags, GitHub Releases, WordPress.org publication and deployment require separate explicit Human authority. Add heavier release tooling only when a real release workflow requires it.

## Workflow evolution

Do not optimize by adding machinery in advance. Measure actual repeated CI/review friction first. After several real tasks, change this workflow only for a concrete observed failure mode or material cost, and keep the correction smaller than the problem it solves.
