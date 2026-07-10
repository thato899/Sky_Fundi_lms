# Release Process

## Branch to Release

1. Feature work merges into `develop` (see [Git Workflow](../development/git-workflow.md)).
2. When `develop` is release-ready, a release branch `release/vX.Y.0` is cut.
3. Only fixes go into the release branch (no new features) while it stabilizes on `staging`.
4. On sign-off, the release branch merges into `main` and is tagged `vX.Y.0` (see [Versioning](../versioning.md)).
5. `main` is what deploys to `production`.
6. The release branch also merges back into `develop` so any release-branch fixes aren't lost.

## Pre-Release Checklist

- All new/changed migrations reviewed for the additive-by-default rule ([Migration Standards](../database/migration-standards.md)).
- Relevant documentation under `/docs` updated in the same PRs that introduced the change (enforced by PR template checklist).
- Test suite green (see [Testing Strategy](../development/testing-strategy.md)).
- Changelog updated (root `CHANGELOG.md`, to be introduced alongside first real release).

## Rollback

Because migrations are additive-by-default, rolling back a release should, in the common case, mean redeploying the previous `main` tag without requiring a destructive down-migration. Any release that includes a genuinely destructive migration must document its specific rollback plan in the release notes.

## Module Version Independence

A platform release (`vX.Y.0`) and an individual module's version (see [Module Manifest](../architecture/module-system.md#module-manifest-modulejson)) are tracked separately. A platform release documents which module versions it ships with, but modules are expected to eventually be updatable independently — this is a target property of the architecture, refined as the module registry is implemented.
