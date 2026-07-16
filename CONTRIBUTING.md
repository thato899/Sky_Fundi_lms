# Contributing to Sky Fundi

`AGENTS.md` is the authoritative repository policy. This guide summarizes the human contribution path; [the Codex workflow](docs/development/CODEX_WORKFLOW.md) covers AI-assisted work.

## Before changing code

1. Read the owning Core/module README and the relevant documents under `docs/`.
2. Run `pwd`, `git branch --show-current`, `git status --short`, and `git log -1 --oneline`.
3. Branch from `main` using `feature/<area>/<description>`, `fix/<area>/<description>`, `chore/<area>`, or `docs/<area>/<description>`.
4. Preserve unrelated worktree changes and inspect analogous implementation before choosing an abstraction.

## Architecture rules

- Platform concerns belong in `core/`; educational and operational bounded contexts belong in `modules/`.
- Existing module dependencies may be used, but new hard cross-module dependencies require an explicit, documented contract.
- Controllers validate, invoke an Application service, and shape a response. Eloquent models remain Infrastructure.
- Organization-owned data is scoped by trusted `Core\Identity` context and `organization_id`; clients never select ownership by payload.
- All AI-provider access goes through `core/AIGateway`.
- Use UUIDs, authorization, auditing, migrations, factories, and tests consistently with neighboring code.

## Verification

Use Docker Compose for PHP tooling. Run the narrowest relevant test first, then the proportional checks:

```bash
docker compose exec app php artisan test modules/Example/tests
make migrate-check     # migrations/providers
make test
make pint
make analyse
git diff --check
```

`make verify` runs the complete repository handoff. Never claim a skipped check passed.

## Commits and pull requests

Use `<type>(<scope>): <summary>` commits and target `main`. Keep changes cohesive, update documentation with behavior, and use `.github/PULL_REQUEST_TEMPLATE.md`. Publishing, merging, destructive Git operations, and destructive database/volume operations require explicit authorization. See [Git workflow](docs/development/git-workflow.md).

## Security

Never commit secrets or `.env`, expose cross-organization data, weaken authentication, authorization, validation, audit, or rate limits, log personal data, or call vendor AI SDKs from modules. Report security issues privately to the repository owner until a dedicated disclosure channel exists.
