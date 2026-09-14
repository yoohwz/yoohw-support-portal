# YoOhw Support Portal — agent instructions

These instructions apply to the whole repository after the Foundation workflow is accepted and merged.

## Authority

- `main` is reviewed product history. Do not push implementation work directly to `main`.
- Human owns product direction, merge, release, WordPress.org publication and unresolved trade-offs.
- ChatGPT owns task framing, unresolved product/architecture boundaries and final Acceptance.
- Codex/implementation agents own repository discovery, implementation, focused validation, commits, branch/PR upkeep and corrections.
- A fresh independent Codex reviewer is required only for `CONTROLLED` work.
- Git/GitHub own branch/head/base/diff/check/review/merge facts. Do not copy those facts into a second workflow state machine.

## Dedicated Codex project and workspace

Codex work for this repository runs inside the dedicated **YoOhw Support Portal** project. Its configured source folder is:

`/Users/nguyenquocbao/Local Sites/workspace/app/public/wp-content/plugins/yoohw-support-portal`

That directory is the canonical owner-side working tree for `yoohwz/yoohw-support-portal`.

At the start of `Run`, `Continue` or `Technical Review`, verify the environment before changing anything:

- `git rev-parse --show-toplevel` resolves to the exact source folder above;
- `git remote get-url origin` points to `yoohwz/yoohw-support-portal`;
- inspect current branch, HEAD and `git status --short`;
- fetch current `origin` before deciding task/PR/head state.

If the project root or Git remote does not match, stop with `HUMAN_DECISION_REQUIRED` instead of guessing another repository or workspace.

Do not create a second clone, sibling worktree or alternate source copy by default. Implementation branches live in this configured working tree. Disposable build/test output may use OS temporary directories outside the source tree. A separate clone/worktree is exceptional and needs an explicit task-specific reason.

Commands such as `Run YSP-N`, `Continue YSP-N` and `Technical Review YSP-N` are entered in this dedicated Codex project; the configured source folder supplies the repository context.

## Git safety

- Start from current `origin/main`; inspect current branch and existing task/PR state before editing.
- Use `agent/<issue>-<scope>` for normal work. Very small issue-less work may use `agent/<scope>`.
- One task uses one branch and one draft PR by default.
- Preserve unrelated work. Never use force-push, destructive reset/clean, history rewrite or protection weakening to make a task pass.
- A changed candidate head invalidates head-bound review evidence and requires affected checks/review to be refreshed.

## Human commands

Keep operator commands short:

- `Create ...` — ChatGPT creates/records the minimum useful task boundary.
- `Run YSP-N` — Codex recovers Issue `#N`, branch/PR/current state and performs the next implementation-owned step.
- `Continue YSP-N` — recover current GitHub state and continue without restarting or asking the Human to repeat known context.
- `Finalize YSP-N` — Human conditionally authorizes ChatGPT Acceptance and squash merge of the unchanged identified candidate after fresh verification.

GitHub Issue `#N` is the canonical identity `YSP-N`; there is no allocator or identity registry.

## Risk

Use only two risk levels.

### STANDARD

Default for bounded work without sensitive-data or safety-control semantics: ordinary UI/templates/JS/settings, copy, documentation, tests/tooling, understood bugs and behavior-preserving refactors.

Normal flow:

`brief -> Codex Run -> draft PR -> focused validation -> Iteration CI -> final candidate -> YSP Required CI -> READY_TO_FINALIZE -> Human Finalize`

No separate Plan Review or independent reviewer is required by default.

### CONTROLLED

Required when work materially touches or may change:

- authentication/password flows;
- capabilities, authorization, category access or customer/topic visibility;
- private attachments, upload/download/delete/path containment;
- REST/AJAX/admin-post security;
- support-data persistence, schema, storage-mode semantics or migration;
- privacy/export/erasure/retention;
- email recipient selection or private-data disclosure;
- destructive cleanup or uninstall data semantics;
- CI coverage, distribution or release safety controls;
- public/shared contracts or architecture with privacy/security implications.

CONTROLLED follows the same lifecycle as STANDARD plus one fresh independent technical review of the exact candidate. Plan Review is added only when discovery exposes a genuinely unresolved product/architecture/data/permission/compatibility boundary.

If STANDARD discovery finds a CONTROLLED trigger, stop before the sensitive change, keep the same task/PR and escalate the risk.

## Review and corrections

- Codex performs repo-local self-review and focused tests for all work.
- CONTROLLED candidates receive one fresh independent reviewer context. Freshness means a new Codex review context, not a second local checkout. The reviewer uses the same dedicated Codex project and canonical source folder in source-read-only mode, receives task/PR/diff/evidence rather than implementer scratch reasoning, and verifies the exact current candidate before reviewing.
- The independent reviewer must not edit repository source, commit, switch branches, reset, stash, clean or otherwise mutate the canonical working tree. Read-only Git/GitHub inspection and checks that write only to external disposable temporary directories are allowed. If a local test intentionally writes inside the source tree, rely on current CI evidence or run it only in an explicitly disposable external copy rather than mutating the canonical project workspace.
- Review and final CI may run in parallel after the candidate is frozen.
- Corrections stay in the same PR. A correction that changes the reviewed head requires refreshed affected CI/review.
- Repeated material blockers or changed architecture/scope stop for ChatGPT/Human boundary review instead of creating an unbounded loop or replacement PR.

Useful handoff text is intentionally small: `READY_TO_FINALIZE`, and only when needed `PLAN_REVIEW_REQUIRED`, `TECHNICAL_REVIEW_REQUIRED` (fallback when independent review cannot be orchestrated), or `HUMAN_DECISION_REQUIRED`. These are navigation hints, not a machine-parsed lifecycle.

## CI policy

- Draft PRs use cheap `Iteration Gate` feedback and cancel superseded runs for the same PR.
- A final non-draft candidate emits the sole stable merge check: `YSP Required CI`.
- CI never parses Issue/review prose or approval comments.
- Normal PR CI must remain proportional. Full compatibility/release matrices belong to explicit release validation rather than every implementation iteration.

## Product safety

Read `docs/support-data-safety.md` before changes involving support data, identity, authorization, storage, REST, files, email or destructive operations. Existing behavior is not permission to weaken an invariant silently.

## Distribution and release

- Repository-only development files must not enter the WordPress.org payload. Use `.distignore` and `scripts/stage-distribution.sh`.
- Merge permission never grants version/tag/release/publication/deployment permission.
- Do not build a release ledger, publisher state machine or release-cert framework until a concrete release task demonstrates the need.

## Explicit non-goals

Do not introduce task allocators, governance identity refs, mutable workflow-state JSON, evidence schemas/ledgers, predecessor chains, comment approval parsers, custom merge-authority engines, model/compute policy or roadmap machinery merely to serialize GitHub-native facts.
