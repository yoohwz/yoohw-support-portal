# YSP Codex delivery

Human gives a goal directly to Codex. Codex owns framing, technical planning, implementation, validation, review, corrections and an exact-head PR handoff. Human owns product acceptance, unresolved product/scope/safety decisions, merge and production actions. ChatGPT is not a required participant or approval gate.

This workflow is adapted from Thumbnail Manager's `AGENTS.md` at [`1533a17e337ffaa92fd85745c4b73bee0cd329d1`](https://github.com/yoohwz/thumbnail-manager/blob/1533a17e337ffaa92fd85745c4b73bee0cd329d1/AGENTS.md). YSP keeps its Local-first workspace, support-data contract, existing CI and publisher.

## Sources of truth

| Fact | Authority |
| --- | --- |
| Goal, scope, plan, decisions, acceptance criteria, evidence | Existing GitHub Issue or PR; PR is the primary execution record |
| Implementation, diff and ancestry | Git branch / PR head and Git |
| CI | GitHub Actions/Checks |
| Independent review when required | Exact-head PR review/comment |
| Product acceptance and merge authorization | Human |
| Merge | GitHub PR merge record |
| Product safety | `docs/support-data-safety.md` |
| Release/publication/deployment | Separate Human authorization |

Do not create another mutable task-state database, allocator, ledger or approval parser. Issue `#N` is `YSP-N` when an issue exists; do not require a new issue solely to plan or approve work.

## Local workspace and task shape

Implement directly in the dedicated Support Portal plugin folder installed on Local WordPress:

`edit plugin source -> focused checks -> relevant Local runtime verification -> continue`

No extra clone/worktree for ordinary implementation, and no runtime ceremony for trivial non-runtime work. Concurrent implementation and mandatory independent safety-critical review use separate worktrees or clean checkouts.

Start from current `origin/main` after inspecting branch/task/PR state. Preserve unrelated files. Use `agent/<issue>-<scope>` or `agent/<scope>`. One task = one draft PR, with logical commits. Split only at a real semantic/dependency boundary or when reviewability requires it. Never force-push, rewrite shared history or weaken protection.

## Execution cycle

1. Recover conversation, issue/PR, binding Human decisions, current head/base, checks and unresolved findings. Read both PR conversation comments and review submissions in time order and by head applicability. A top-level mirror is actionable for the same decision, but must not conceal newer review evidence.
2. Record a minimum brief: goal, scope, acceptance criteria, validation and risk lane. Codex resolves technical choices within scope.
3. For Controlled work, record the plan before risky runtime changes: invariants, constraints, scenarios and expected outcomes, material risks, validation, unresolved decisions and a proportional complexity budget/stop-loss. State expected schema, lock/recovery domains and destructive authority. Unexpected expansion or repeated blockers requires plan revision; only Human-owned decisions require Human input. Fresh subagent plan critique is required for safety-critical changes or new destructive/persistence/locking architecture.
4. Implement, run focused tests/runtime checks, review adversarially, fix blocking findings and maintain the same Draft PR. Record a Structural, Functional, Safety-critical or Release evidence profile without adding another risk lane.
5. Complete required independent review, then mark the blocker-free candidate Ready. Observe applicable Final CI to terminal success on the exact head. Recheck head/base compatibility, required review, scope and blockers.
6. Post the exact-head `READY_FOR_HUMAN_MERGE` handoff. Human separately authorizes merge. A changed head invalidates the handoff and affected evidence; a moved base requires compatibility and CI reassessment.

## Risk and review intensity

### Fast

Use for isolated docs/governance, ordinary presentation/JS/settings, tests/tooling, understood bugs and behavior-preserving refactors outside sensitive semantics. No separate plan approval or mandatory subagent.

`Human goal -> Codex brief + implementation + focused validation + proportional review -> applicable Final CI -> Human merge gate`

### Controlled

Use for material changes to authentication, authorization/category access/customer visibility, protected attachments/files/path containment, REST/AJAX/admin-post security, support-data persistence/schema/storage/migration, privacy/export/erasure/retention, email recipients/private disclosure, destructive cleanup/uninstall or release/publication semantics. Classification follows behavior, not filenames. Promote Fast work before touching an exposed sensitive boundary.

`Human goal -> Codex discovery + recorded plan -> implementation + tests/runtime evidence + required review + corrections -> applicable Final CI -> Human merge gate`

Fresh independent exact-head review is mandatory for safety-critical security/authorization, private-data disclosure, destructive file/path behavior, persistence/schema/migration, storage integrity, locks/replay or recovery changes. The reviewer uses a fresh subagent context and separate checkout, examines task/plan/invariants/diff/evidence without reusing the implementer's private verdict, and records identity, SHA and material findings. Lead Codex resolves blockers before handoff. Missing mandatory evidence means unfinished work.

Other Controlled work may use lead Codex adversarial verification plus strong evidence. Pure Structural work can avoid mandatory independence only with explicitly unchanged semantics, mechanically proven source/body/API equivalence where practical, dependency characterization and no sensitive contract changes. Human may request an independent review at any time.

Review corrections as a delta on the new head and affected invariants; restart full review only for material scope expansion. Advisory preferences do not create correction loops. Correctness, security, safety, regression, acceptance failure, broken required CI and contract violations do.

## Validation and CI

Run only meaningful checks for the change. Documentation/governance needs repository contracts and diff hygiene; runtime changes need relevant automated and Local evidence; changed JS/UI needs the affected browser flow. Security/private-data work needs negative paths for relevant guests, owners, unrelated customers and staff, and both storage modes where affected. Release work needs existing package/publisher checks. Record `NOT RUN — reason` instead of claiming unavailable checks passed.

Existing CI is unchanged:

| Candidate | Signal and checks |
| --- | --- |
| Draft | `Iteration Gate`: repository/distribution contracts, diff hygiene, modern PHP syntax, JavaScript syntax, source-safety characterization |
| Ready / final | `YSP Required CI`: the draft checks plus PHP 7.4 support floor and WordPress Plugin Check against the exact staged payload |

Keep Draft through implementation, corrections and review. `Iteration Gate` is feedback, never merge evidence. The final aggregate fails closed for failure, cancellation or unexpected skip. Superseded PR runs are cancelled. Wait for exact-head final success before merge-ready handoff; CI cannot substitute for runtime evidence it does not execute. Do not import Thumbnail Manager's full matrix, gate names or classifier. Broad compatibility/release matrices remain explicit release work.

## Durable handoff and Human gate

Keep PR evidence current: brief, plan when applicable, binding Human decisions, changes, affected invariants, validation/profile, PASS/NOT RUN evidence, blockers/corrections and release boundary. Use linked artifacts for verbose logs.

Only two Human handoff statuses are active:

- `READY_FOR_HUMAN_MERGE`: local validation, required review and applicable Final CI are complete on the exact current head; no material blockers remain. Post one canonical top-level PR comment containing task/PR identity, full head SHA, evidence and `Next: Human - Merge PR #N`.
- `HUMAN_DECISION_REQUIRED`: record the specific Human-owned decision, options and recommendation in the existing issue/PR, ending `Next: Human - Decide YSP-N`. Continue independent authorized work where possible.

Internal planning, tests, findings and CI progress use ordinary evidence, not new statuses. A final workflow response with a clear next action ends with exactly one matching copy-ready `Next:` hint. For unfinished Codex work use `Next: Codex - Continue YSP-N`; do not pause authorized work merely to produce a hint.

Before executing an explicit Human merge instruction, freshly verify the handoff SHA equals the PR head, target compatibility, successful required CI, current required independent review, no material blockers and GitHub mergeability/protection. Neither Run, Review, CI nor a handoff authorizes merge by itself. Merge never authorizes release/version/tag/publication/deployment.

## Legacy tasks and commands

`Run YSP-N`, `Continue YSP-N` and `Codex - Continue YSP-N` recover and continue. `Review YSP-N` performs review/corrections internally. `Create` records the useful brief and continues authorized work.

Historical STANDARD/CONTROLLED map to Fast/Controlled, subject to semantic reassessment. Legacy Plan Review, Technical Review, Acceptance Review and READY_TO_MERGE records remain evidence/recovery inputs, not mandatory ChatGPT stages. Preserve Human decisions and unresolved findings, revalidate current head and apply the new handoff. Never infer safety-contract approval or merge permission from migration.

## Distribution and release

Use `bash scripts/stage-distribution.sh <source> <destination>` and `.distignore` to stage tracked product files. Repository/development artifacts must stay out of WordPress.org payloads. Plugin Check runs on the staged payload; normal PR CI does not create release ZIPs.

Use the existing [WordPress.org release runbook](releasing.md) only for separately authorized release work. Human chooses the version. Verify the actual published baseline, net customer-visible changelog and exact prepared artifact. Retain both manual release workflows, dry-run/preflight, immutable candidate and separate production approval/recovery controls. Source version metadata is not proof of public publication.

Do not add workflow services, status ledgers, extra approval gates, certification frameworks or broad test matrices without demonstrated need.
