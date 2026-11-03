# Kinta AI Engineering Rules

## Engineering

- Understand the requirement before editing.
- Inspect existing implementations before creating new patterns.
- Follow existing CodeIgniter 4 architecture and conventions.
- Prefer the smallest change that satisfies the requirement.
- Do not refactor unrelated code.
- Do not introduce dependencies without approval.

## Context Efficiency

- Start with files directly relevant to the task.
- Do not scan the entire repository by default.
- Expand context only when dependencies or errors require it.
- Avoid rereading unchanged files unnecessarily.
- Keep command/log output focused on relevant errors.

## Verification

For implementation tasks use:

Understand → Plan → Implement → Test → Diagnose → Fix → Retest → Review

Do not claim functionality works because the code looks correct.

Acceptance criteria must be classified as:

- PASS — verified
- FAIL — verification failed
- NOT TESTED — reliable verification unavailable

Prefer deterministic tools and automated tests over AI judgment.

## Failure Handling

When a test fails:

1. Read the actual error.
2. Identify the failing layer.
3. Form a hypothesis.
4. Make one targeted change.
5. Retest.

Do not make random speculative changes.

After 3 unsuccessful fix attempts for the same problem, stop and report the blocker.

## Security

- Never expose secrets or credentials.
- Treat .env contents as sensitive.
- Do not use production data for testing.
- Preserve authentication, authorization, CSRF, validation and escaping.
- Do not weaken security mechanisms to make functionality work.

## Git

Before completing a task inspect:

- git status
- git diff
- git diff --check

Do not commit, push, merge, rebase, deploy or delete branches unless explicitly authorized.

Do not overwrite unrelated uncommitted changes.

## Scope

Do not modify unrelated functionality.

If an unrelated issue is discovered, report it rather than fixing it automatically.

## Human Approval Required

Stop before:

- destructive database operations,
- production changes,
- adding dependencies,
- major architectural changes,
- deployment,
- Git push/merge/rebase,
- security-sensitive decisions with uncertain consequences.

Routine reversible implementation decisions do not require approval.

## Completion

A task is complete only after:

1. implementation is finished,
2. acceptance criteria are checked,
3. relevant tests/checks are run,
4. final diff is reviewed,
5. remaining uncertainty is reported.

Report:

- Result
- Files changed
- Acceptance criteria: PASS / FAIL / NOT TESTED
- Tests executed
- Remaining risks
- Manual verification required