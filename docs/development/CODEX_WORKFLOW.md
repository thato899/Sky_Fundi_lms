# Codex development workflow

`AGENTS.md` is the authoritative repository instruction set. This guide turns those rules into a repeatable operating workflow. Run commands from the repository root in WSL Ubuntu; Docker Compose is the canonical PHP environment.

Codex reads applicable `AGENTS.md` instructions when a session starts, from the repository root toward the working directory. That makes the root file the durable default for future prompts; a closer nested instruction file can refine it for one subtree. Restart the Codex session after changing instructions when you need the new guidance to be loaded for the run.

## 1. Start Codex

For interactive work, enter the repository and launch the terminal UI:

```bash
cd /path/to/Sky_Fundi_lms
codex
```

An initial prompt can also be supplied directly, for example `codex "Review the current worktree"`. Keep approval and sandbox defaults enabled; do not use approval/sandbox bypass flags on a normal workstation.

For a bounded non-interactive task, use `codex exec`. It prints progress to standard error and the final response to standard output:

```bash
codex exec "Inspect the current branch and report verification gaps; do not edit files"
codex exec --sandbox workspace-write "Implement the approved documentation-only change and run its checks"
```

Non-interactive mode is read-only by default. Grant `workspace-write` only when the task requires repository edits, and keep external publishing or destructive actions under explicit human approval.

## 2. Orient before changing anything

```bash
pwd
git branch --show-current
git status --short
git log -1 --oneline
```

Read the requested area, its README, analogous code, migrations, tests, providers, Composer configuration, and relevant architecture documents. Treat existing worktree changes as user-owned. Confirm the requested outcome and exclusions before editing.

Use `make status` when the containers are running to combine Git, Compose, and migration state. Use `make health` for the public liveness and Laravel environment check.

## 3. Feature branch workflow

Branch creation and switching are user-controlled actions. Codex must not change branches unless explicitly requested and must not implement directly on `main`. Normal branches follow `feature/<area>/<short-description>`, `fix/<area>/<short-description>`, `chore/<area>`, or `docs/<area>/<short-description>`.

Before implementation:

1. Confirm the current branch is intended for the task.
2. Identify and preserve dirty-worktree changes.
3. Establish the smallest allowed scope and acceptance criteria.
4. Inspect executable conventions instead of scaffolding from memory.

## 4. Implementation workflow

Work in small, reviewable increments:

1. Add or update the direct test when practical.
2. Implement the minimum production change.
3. Register migrations, bindings, listeners, routes, or providers only when required.
4. Keep organization scoping and authorization visible at every data boundary.
5. Update the owning README and canonical docs when behavior or operating procedure changes.
6. Run the narrow test path and formatting check early.

Do not add empty architecture folders, placeholder classes, speculative APIs, convenience dependencies, or unrelated cleanup. Existing module structure is a menu of valid locations, not a requirement to create every folder.

## 5. Local environment and verification

Initialize once, then start services:

```bash
make init
make up
make health
```

Useful focused commands:

```bash
docker compose exec app php artisan test modules/Example/tests
docker compose exec app ./vendor/bin/pint --test modules/Example
make migrate-check
make test-learners
```

`make migrate-check` creates a uniquely named temporary database on the Compose MySQL server, runs fresh migrations and seeders, rolls everything back, migrates forward again, and drops the temporary database. It does not reset the developer database.

Complete verification:

```bash
make verify
```

This validates Composer, checks migrations/seed/rollback, runs the full PHPUnit suite, checks Pint and changed production PHP with PHPStan, then runs `git diff --check`. Run `ANALYSE_ALL=1 make analyse` for an explicit repository-wide audit. Full-audit backlog must be reported rather than folded into unrelated work.

Before handoff, always inspect:

```bash
git status --short
git diff --stat
git diff --check
```

## 6. Publishing workflow

Committing, pushing, and opening a PR are external/repository mutations and require explicit user authorization. When authorized:

1. Review the exact status and diff.
2. Stage only intended files with explicit paths.
3. Confirm `make verify` results and prepare a PR body file.
4. After explicit commit/push/PR approval, run:

```bash
scripts/publish-draft-pr.sh \
  --commit-message "chore(scope): describe the change" \
  --pr-title "chore(scope): describe the change" \
  --body-file /tmp/pr-body.md
```

The script refuses `main`, detached HEAD, missing GitHub authentication, unstaged/untracked files, an empty staged scope, and whitespace errors. It commits only the staged files, pushes the current branch, opens a draft PR into `main`, and prints the commit hash and PR URL. It never merges.

## 7. Review workflow

Review from risk outward:

1. Scope drift and unintended files.
2. Authentication, authorization, organization isolation, sensitive data, and audit behavior.
3. Migration safety, foreign keys, rollback, and backward compatibility.
4. Module boundaries, dependency direction, provider registration, and public contracts.
5. Failure paths, tests, factory validity, and documentation accuracy.

State findings by severity with file/line evidence. Do not modify code during a review-only request unless the user separately authorizes fixes.

## 8. Human merge workflow

A human reviews draft PR scope, verification evidence, required checks, and requested changes. When ready, the human marks it ready and merges through GitHub using the repository's permitted merge strategy. Codex does not merge merely because it created the PR. It may merge only when the user explicitly requests that exact action and checks are not failing; it never force-merges or bypasses protection.

After merge, synchronize deliberately:

```bash
git switch main
git pull --ff-only origin main
```

Delete feature branches only when authorized and after confirming the merge.

## 9. Recovery workflow

When a check fails, preserve evidence and diagnose the smallest boundary first:

- Container/bootstrap: `docker compose ps` and `docker compose logs init app`.
- Laravel cache: `docker compose exec app php artisan optimize:clear`.
- Migration: `make migrate-check` and `docker compose exec app php artisan migrate:status`.
- Test: rerun the single failing test with `--filter` before the full suite.
- Formatting: run Pint only on changed PHP paths, then rerun `make pint`.
- Static analysis: rerun PHPStan on changed production paths with `--debug` if the sandbox prevents its parallel TCP worker.
- Docker socket permission denied: verify Docker Engine is running in WSL with `docker info`. Add the Linux user to the `docker` group using the operating system's normal administration process, then start a new login session; never make the Docker socket world-writable.
- GitHub authentication: run `gh auth status`, then `gh auth login` interactively if needed. Never paste tokens into prompts, scripts, shell history, or repository files.

Never recover by deleting volumes, resetting Git, discarding user changes, weakening tests, or editing unrelated product code. `docker compose down -v`, destructive migrations, Git history rewrites, and production operations require explicit approval.

## 10. Handoff

Report the outcome, files created/modified, exact verification commands and totals, skipped or blocked checks, manual-approval actions, `git status --short`, and `git diff --stat`. Explicitly say whether any commit, push, or PR creation occurred.
