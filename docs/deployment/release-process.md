# Release process

The repository currently integrates reviewed work through pull requests to `main`. A formal release-branch train, automated deployment, and tagged release cadence are not implemented and must not be represented as current practice.

Before a release candidate, review exact scope; validate Composer; check migrations/seed/rollback where applicable; run the full tests, Pint, PHPStan, and whitespace checks; update user/operations documentation; verify backups and a release-specific rollback; and record any production configuration changes. `make verify` is the repository aggregate check.

Deployments must use locked dependencies, run forward migrations once, supervise queue/scheduler processes, and verify `/up` plus authenticated health. Application rollback does not automatically reverse data migrations; every release with a schema change needs an explicit compatibility/data plan. Tags, changelogs, production deployment, and merges remain human-authorized actions.
