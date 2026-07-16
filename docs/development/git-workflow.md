# Git workflow

`main` is the integration and release branch currently used by this repository. There is no implemented `develop` branch workflow.

| Branch | Use |
|---|---|
| `main` | reviewed, accepted repository state |
| `feature/<area>/<description>` | product capability |
| `fix/<area>/<description>` | bug/security correction |
| `chore/<area>` | maintenance |
| `docs/<area>/<description>` | documentation-only work |

Never implement directly on `main`. Branch creation/switching, commits, pushes, rebases, merges, tags, and PR operations are user-controlled for Codex. Preserve dirty worktrees and stage explicit paths when scope is mixed.

Commits use `<type>(<scope>): <summary>`. Pull requests target `main`, use `.github/PULL_REQUEST_TEMPLATE.md`, disclose migrations/rollback, tenant/security impact, exact verification, risks, and skipped checks. `scripts/publish-draft-pr.sh` may be used only after explicit authorization; it commits staged files, pushes, and creates a draft PR but never merges.
