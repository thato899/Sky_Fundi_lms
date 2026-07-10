# Git Workflow

## Branching Model

Sky Fundi uses a **Git Flow**-inspired model:

| Branch | Purpose |
|---|---|
| `main` | Always deployable; reflects production. Tagged per release. |
| `develop` | Integration branch; latest accepted work, not yet released. |
| `feature/<area>/<short-description>` | New work, branched from `develop`. |
| `fix/<area>/<short-description>` | Bug fixes, branched from `develop` (or `release/*` for release-blocking fixes). |
| `release/vX.Y.0` | Stabilization branch cut from `develop` ahead of a release. See [Release Process](../deployment/release-process.md). |
| `hotfix/<short-description>` | Urgent production fix, branched from `main`, merged into both `main` and `develop`. |

`<area>` is typically a module name (`academics`, `attendance`) or a Core service (`core-auth`, `core-rbac`) or `docs`.

Examples: `feature/attendance/register-close-endpoint`, `fix/core-auth/token-refresh-race-condition`, `docs/api-conventions-pagination`.

## Commits

See commit message format in [`../../CONTRIBUTING.md`](../../CONTRIBUTING.md#commit-message-format). Keep commits atomic and focused; avoid mixing unrelated changes (e.g. a formatting pass and a behavior change) in one commit.

## Pull Requests

- Always target `develop` (except `hotfix/*`, which targets `main`).
- Use the PR template (`.github/PULL_REQUEST_TEMPLATE.md`) and complete the architecture checklist.
- At least one CODEOWNER approval required (see `CODEOWNERS`).
- CI (once introduced) must pass: tests, static analysis, code style.

## Merge Strategy

Squash-merge feature/fix branches into `develop` to keep history readable; use a regular merge commit for `release/*` → `main` and `release/*`/`hotfix/*` → `develop` so the release point is traceable.

## Tags

Releases are tagged on `main` as `vX.Y.Z` per [Versioning](../versioning.md).
