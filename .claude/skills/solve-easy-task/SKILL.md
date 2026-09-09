---
name: solve-easy-task
description: Solve a small or straightforward software engineering task efficiently end-to-end: investigate, implement, test, commit and create a pull request.
argument-hint: "<task description>"
disable-model-invocation: true
---

# Solve Easy Task

Solve this task autonomously:

$ARGUMENTS

Deliver a correct, focused and reviewable pull request.

Optimize for correctness, simplicity, speed and reasonable token usage.

Do not merge the pull request.

## Principles

- Stay narrowly focused on the requested task.
- Do not perform broad repository, architecture or security audits.
- Do not investigate unrelated hypothetical problems.
- Do not spawn subagents or background reviewers.
- Do not perform exhaustive edge-case analysis unless directly relevant.
- Stop investigating once there is enough evidence for a correct fix.
- Prefer targeted verification over repository-wide verification.
- Implement the smallest maintainable change that solves the problem.

If the task turns out to be large, ambiguous, destructive, security-sensitive
or architecturally significant, stop and explain why deeper work is needed
instead of automatically expanding the scope.

## 1. Prepare

Check:

- git status,
- current branch,
- CLAUDE.md or other repository instructions when present.

Inspect only repository areas relevant to the task.

If currently on main or master, create a descriptive working branch.

Preserve unrelated existing work.

## 2. Investigate

Find the relevant code path and determine the root cause.

For bugs:

- reproduce the problem when useful,
- otherwise use code evidence or a focused automated test,
- distinguish the actual cause from the reported symptom.

Stop investigating once the root cause is sufficiently established.

## 3. Implement

Implement the smallest clear and maintainable fix.

Follow existing project conventions.

Avoid:

- unrelated refactoring,
- speculative improvements,
- unnecessary abstractions,
- changes outside the requested scope.

For bug fixes, add a focused automated regression test.

First inspect whether suitable test infrastructure already exists and reuse it.

If suitable test infrastructure is missing, adding a minimal test setup is allowed when it can be done simply.

Keep any new test infrastructure minimal:

- add only what is necessary to run the regression test,
- follow existing project and dependency conventions,
- do not build broad reusable test frameworks or unrelated test helpers.

The regression test should prove the reported failure or the invariant violated by the bug.

Where practical, the test should fail against the broken behavior and pass after the fix.

Use the simplest test level that correctly verifies the behavior.

Use an integration test when database, ORM, transaction, concurrency, framework or other infrastructure behavior is essential to the bug.

## 4. Verify

Run the new or modified regression test after implementation.

Run the most relevant targeted tests and checks for the modified code.

Run additional linting, static analysis, type checks or builds only when:

- they directly apply to modified code,
- repository instructions require them,
- or they are needed to establish confidence in the fix.

Do not automatically run every available quality gate or the complete test suite when targeted verification is sufficient.

Never claim a check passed unless it actually ran successfully.

## 5. Quick review

Inspect the final diff.

Confirm that:

- the requested problem is fixed,
- the identified cause is addressed,
- regression coverage is adequate,
- no unrelated changes are included,
- no obvious regression was introduced.

Do not launch another agent for review.

## 6. Commit

Stage only files belonging to the task.

Never use:

    git add .
    git add -A

Stage intended paths explicitly.

Before committing inspect:

    git diff --cached --name-status
    git diff --cached --stat

Do not commit unexpected staged files.

Never modify, stage or commit:

    .claude/skills/solve-easy-task/**

Create a concise commit message consistent with repository conventions.

## 7. Push and create PR

Push the working branch.

If GitHub and authenticated `gh` are available, create a pull request.

Keep the PR concise and include:

### Summary
What changed.

### Root cause
Why the problem occurred.

### Testing
What was actually executed.

Mention risks or trade-offs only when meaningful.

Do not merge the pull request.

## 8. Finish

Report concisely:

- root cause,
- solution,
- tests/checks performed,
- branch,
- commit,
- pull request URL.
