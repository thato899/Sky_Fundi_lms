# Platform hardening stages 1–4

This document is the current implementation checklist for the four platform-hardening stages:

1. Documentation accuracy
2. Authorization and organization-isolation test depth
3. Operational resilience (health, deployment, backup, and restore validation)
4. Multi-factor authentication

The executable code and test results are authoritative. Historical planning documents may contain older branch names, test totals, or feature status and should not be used as release evidence.

## Verification expectations

- Documentation changes must identify current behavior and deferred behavior separately.
- New organization-owned behavior must include cross-organization denial coverage.
- Production validation must reject unsafe configuration and must never restore into the configured production database.
- Backup validation must use a separately named database and must not remove production data.
- MFA must be opt-in during rollout, auditable, rate-limited, and enforced before issuing an authenticated session or API token when enabled for the account.

## Current limitations

- Module providers are explicitly registered at boot; module registry enablement does not dynamically unload routes or PHP code.
- Backup restoration is validation-oriented; it does not replace a scheduled production restore drill.
- Two-factor authentication remains a planned security feature until its migration, enrollment, challenge, recovery, and enforcement paths are implemented and covered by tests.
