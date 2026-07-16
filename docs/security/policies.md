# Implemented security controls

## Authentication and sessions

Blade uses Laravel session authentication and APIs use Sanctum bearer tokens. Login and password-reset endpoints are throttled. Authenticated groups apply account-lock checks; logout revokes access. Email verification endpoints exist. Session listing/revocation and trusted devices are implemented. Two-factor authentication is not enforced.

## Authorization and RBAC

Namespaced permissions are stored in Core RBAC and assigned through roles/memberships. Enforcement occurs through `permission` middleware, Form Request `authorize()`, Laravel policies, and service-level invariants. UI visibility is convenience only. Registered policies cover Organizations, Learners, Attendance, Assessments/categories, Reports/configuration, and Scheduling resources.

## Organization isolation and UUID binding

Identity context verifies active membership and organization state. Tenant-owned queries and relations use `organization_id`; dedicated middleware resolves route UUIDs within that context. Foreign UUIDs return `404`. UUID opacity is defense-in-depth, not authorization. Super Admin access to operational modules still requires explicit context where routes demand it.

## Request and data protection

- Web state-changing requests receive Laravel CSRF protection; Sanctum exposes `/sanctum/csrf-cookie` for stateful clients.
- Form Requests/controller validation constrain types, enums, dates, lengths, relationships, files, sorting, and pagination as implemented.
- Models use explicit mass-assignment configuration; services supply trusted ownership fields.
- Passwords are hashed by Laravel. Organization AI credentials use encrypted casts/storage.
- Template/report/comment output is whitelisted/escaped; CSV exports neutralize spreadsheet formulas.
- API throttling, forced JSON responses, and request metadata logging are applied by the API pipeline; sensitive payloads must not be logged.

## IP restrictions and audit

IP restriction management/enforcement, trusted-device detection, account locks, and session revocation are available but depend on route/configuration use. Auditable events and explicit service calls write immutable read-only audit records. Audit coverage is workflow-driven and should not be interpreted as database-level change capture.

## Known assumptions

Production TLS, proxy trust, secrets management, firewalling, worker isolation, storage encryption, backup protection, retention, and monitoring are operator responsibilities. Assignment-aware teacher authorization, enforced 2FA, automated restore, and a published vulnerability contact are not implemented.
