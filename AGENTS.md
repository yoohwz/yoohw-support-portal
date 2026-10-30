# Support Portal — agent instructions

These instructions apply to the whole repository. The delivery model follows Thumbnail Manager's Codex-owned workflow, adapted to Support Portal's private-data boundaries and existing CI. See `docs/workflow.md` for execution details.

## Authority

- `main` is reviewed product history. Do not push implementation work directly to `main`.
- Human owns product direction, unresolved product/scope/safety decisions, product acceptance, merge, version, release, WordPress.org publication, deployment and rollback.
- Codex owns task framing, risk classification, technical architecture, planning, implementation, tests, review, corrections and branch/PR upkeep. One lead Codex is accountable for the complete result and evidence.
- Subagents perform bounded delegated work and report findings to the lead; they do not create another approval stage.
- ChatGPT is not a required participant. Technical planning, review and acceptance evidence must not depend on a ChatGPT handoff or approval. These project roles take precedence over generic role descriptions.
- Git/GitHub own branch/head/base/diff/check/review/merge facts and durable task evidence. Do not copy those facts into a second workflow state machine.

## Local development workspace

Work directly in the source folder configured for the dedicated Support Portal project. It is also the plugin installed on the project's Local WordPress site.

Do not create another clone or worktree for ordinary implementation. Use Local for proportional runtime verification where relevant; trivial documentation work needs no runtime ceremony. Concurrent implementation tasks and mandatory independent safety-critical reviews use separate worktrees or clean checkouts.

## Git safety and task shape

- Inspect current branch, task/PR state and current `origin/main` before editing.
- Use `agent/<issue>-<scope>` or `agent/<scope>` for issue-less work.
- One task uses one branch and one draft PR by default. Use logical commits, and split only at a real semantic/dependency boundary or when the diff cannot be safely reviewed as one change.
- The PR is the primary execution record. Reuse an existing issue where useful; a separate issue or planning document is not mandatory. Issue `#N` remains `YSP-N`; there is no allocator or identity registry.
- Preserve unrelated work. Never force-push, destructively reset/clean, rewrite shared history or weaken protection to make a task pass.
- A changed head invalidates the merge-ready handoff and affected head-bound review/CI. Refresh affected evidence. Reassess compatibility and CI if target `main` moves.

## Human commands and continuation

Resolve `Chạy task này`, `Tiếp tục`, `Review` and `Sửa tiếp` from conversation and GitHub state without asking Human to repeat recoverable context.

- `Create ...` — Codex records the minimum useful brief and continues authorized work.
- `Run YSP-N`, `Continue YSP-N`, or `Codex - Continue YSP-N` — recover scope, decisions, current PR/head/base, CI and findings; perform the next Codex-owned action.
- `Review YSP-N` or `Codex - Review YSP-N` — perform risk-appropriate review, fix blockers and continue toward the Human gate.
- Legacy `Plan Review YSP-N`, `Acceptance Review YSP-N` and historical review/Acceptance statuses are recovery inputs, not required external gates. Preserve binding Human decisions, resolve outstanding technical work internally and revalidate the current head. Never infer permission to change a safety contract or merge from migration.
- `Merge YSP-N` or `Human - Merge PR #N` — explicit Human authorization for only the unchanged merge-ready candidate, after a fresh head/base/CI/review/blocker check. No release is authorized.

Read both top-level PR comments and review submissions in time order and check exact-head applicability. Prefer a top-level mirror for the same decision, but never let an older handoff hide newer applicable findings. Report genuinely conflicting state specifically.

## Risk lanes

Use only `Fast` and `Controlled`. Historical `STANDARD` maps to Fast and `CONTROLLED` to Controlled; reassess actual semantics rather than trusting an old label.

### Fast

Bounded documentation/governance, ordinary UI/templates/JS/settings, tooling/tests, understood bugs and behavior-preserving refactors that cannot materially change private data, persistence or safety boundaries.

`Human goal -> Codex brief + implementation + focused validation + proportional review -> PR + applicable Final CI -> Human merge gate`

No separate plan approval or mandatory subagent. Pure governance or CI maintenance can be Fast; changes to release/publication semantics or actual safety boundaries are Controlled.

### Controlled

Required when work materially touches or may change authentication/password flows; capabilities, category access or customer/topic visibility; private attachments/upload/download/delete/path containment; REST/AJAX/admin-post security; support-data persistence/schema/storage modes/migration; privacy/export/erasure/retention; email recipients/private disclosure; destructive cleanup/uninstall; or release/publication semantics and equivalent sensitive contracts.

Record a plan in the existing issue or PR before risky runtime implementation: affected invariants, constraints, material risks, relevant scenarios/expected outcomes, validation, and unresolved decisions. State a proportional complexity budget and stop-loss, including whether schema, lock/recovery domains or destructive authority are expected. Unexpected expansion or repeated material blockers requires plan revision; ask Human only for Human-owned choices.

For safety-critical work or new destructive/persistence/locking architecture, have a fresh subagent challenge the plan before implementation. Planning critique and implementation review are focused internal checks, not additional workflow stages.

`Human goal -> Codex discovery + recorded plan -> implementation + tests/runtime evidence + required review + corrections -> PR + applicable Final CI -> Human merge gate`

Stop before risky implementation and promote Fast work when discovery finds a Controlled boundary.

## Review and validation

- Codex self-reviews every diff and runs focused tests. Select a proportional evidence profile: Structural, Functional, Safety-critical or Release; profiles are not extra lanes/statuses.
- Fresh independent exact-head review is mandatory for safety-critical changes to authorization/security, private-data disclosure, destructive files/path containment, persistence/schema/migration, storage integrity, locking/replay or crash recovery. Use a fresh subagent context and separate worktree/clean checkout; provide task, plan, invariants, diff and evidence without the implementer's private verdict.
- Record reviewer identity, reviewed SHA, findings and corrections in the PR. Missing mandatory independent evidence means unfinished work, not merge readiness.
- Other Controlled work may use the lead's adversarial verification and strong relevant evidence. A mechanical Structural change can avoid mandatory independent review only with unchanged semantics, source/body/API equivalence where practical, dependency characterization and no sensitive contract changes.
- Corrections stay in the same PR. Review the delta and affected invariants on a new head; repeat a full review only if scope materially expands.
- Correctness, safety, security, regression, acceptance failure, broken required CI and contract violations are blockers. Naming preferences and optional refactors are advisory.
- Validate changed runtime behavior on Local where useful, including relevant negative paths and both storage modes. Green CI proves only what it runs. Mark unavailable checks `NOT RUN — reason`.

## Subagents

Delegate bounded work only where it adds value. Lead Codex owns integration; subagents never independently approve their own implementation. Avoid overlapping file ownership and use separate checkouts for concurrent implementation.

Codex may choose subagent model/effort without a new Human approval: default to `gpt-6.1-sol` with proportional supported effort. Use `gpt-6-astra` only for a concrete bounded reasoning problem where evidence, decomposition or greater Sol effort is insufficient or clearly unsuitable. Record the reason and outcome briefly in existing PR evidence. Risk labels or reviewer roles alone do not justify escalation. Model choice never changes authority, independence or evidence requirements; do not claim unavailable runtime switches.

## CI and handoff

- Preserve existing CI: Draft uses cheap `Iteration Gate`; non-draft uses the sole stable merge check `YSP Required CI`. Draft results cannot satisfy merge protection. Superseded runs for the same PR are cancelled.
- Keep Draft during implementation, corrections and required review. When blocker-free, mark Ready, observe applicable Final CI to terminal success on the exact current head, then recheck head/base, required review and blockers before handoff.
- Required CI fails closed on failed, cancelled or unexpectedly skipped jobs. Never bypass or weaken it. Do not copy another plugin's runtime matrix into YSP without a concrete need.
- Use only `READY_FOR_HUMAN_MERGE` and `HUMAN_DECISION_REQUIRED` for Human handoffs. Internal planning/review/CI progress needs no workflow status.
- Update the PR body and post one canonical top-level exact-head handoff with scope, decisions, evidence (`PASS` / `NOT RUN`), findings/corrections, release boundary and next action. A later commit invalidates it.
- A final user-facing workflow response with a clear next action ends with exactly one copy-ready `Next: <Target> - <short command>`, matching the durable handoff. Use `Next: Human - Merge PR #N`, `Next: Human - Decide YSP-N`, or `Next: Codex - Continue YSP-N` as applicable. Do not stop autonomous work merely to emit a hint.

## Product safety

Read `docs/support-data-safety.md` before changes involving support data, identity, authorization, storage, REST, files, email or destructive operations. Its existing invariants remain binding. Historical uppercase lane names in that contract retain the mapping above; this workflow does not relax any safety invariant.

## Distribution and release

Repository-only development files must not enter the WordPress.org payload. Use `.distignore` and `scripts/stage-distribution.sh`.

Merge permission never grants version/tag/release/publication/deployment permission. Use `docs/releasing.md` and the existing two manual GitHub Actions for separately authorized release work. Human chooses the version; verify the actual published baseline, newest changelog and exact artifact before separate production authorization. No automatic post-merge publication.

## Explicit non-goals

Do not introduce task allocators, governance identity refs, mutable workflow-state JSON, evidence schemas/ledgers, predecessor chains, comment approval parsers, custom merge-authority engines, model/compute policy machinery or release-cert frameworks merely to serialize GitHub-native facts.
