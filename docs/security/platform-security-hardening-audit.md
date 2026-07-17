# Platform security hardening audit

## Scope and method

This audit covers the executable platform on `security/platform-hardening` at
baseline commit `13e09bd`. The clean opening suite passed with **167 tests and
924 assertions**. Routes, middleware, policies, Form Requests, application
services, models, migrations, configuration, exports, provider boundaries,
storage abstractions, logging, and representative tenant tests were inspected
across `app/`, `core/`, `modules/`, `routes/`, `config/`, `database/`,
`bootstrap/`, and `tests/`.

Findings were treated as confirmed only when an executable attack path was
demonstrated. The two confirmed defects below were reproduced by focused tests
before correction. No migrations, dependencies, product features, or business
workflow changes were introduced.

The completed suite passes with **170 tests and 951 assertions**.

## Threat model

Threat actors include unauthenticated internet users; authenticated learners,
guardians, tutors, teachers, organization administrators, and platform
administrators; suspended or inactive users; compromised accounts; malicious
tenants; accidentally over-privileged users; and external email, storage, AI,
and infrastructure providers.

Protected assets include learner and guardian personal information, staff
records, grades, assessment results, attendance, reports, organization
configuration, financial/subscription information, AI credentials, API and
password-reset tokens, audit records, and uploaded documents.

The principal trust boundaries are public internet to API, authenticated API to
organization context, one tenant to another, organization administration to the
platform control plane, application to database/storage/email/AI providers,
queued work, and exported or downloaded data. The highest-impact abuse cases
are stale-token use after account suspension, permission or context bypass,
foreign UUID association, sensitive serialization/logging, injection into
queries or spreadsheet exports, and unauthorized export/download.

## Confirmed findings and corrections

| Classification | Affected endpoint/service | Preconditions and impact | Evidence and existing mitigation | Correction, tests, and residual risk |
|---|---|---|---|---|
| Confirmed security defect | Authenticated Core API routes that omitted `account.not-locked`, reproduced at `GET /api/v1/notifications/preferences` | An attacker retained a token/session identity after the account became locked or suspended. The route returned `200`, allowing continued authenticated access where account lifecycle should deny it. | `AuthService` rejects new login and lifecycle events normally revoke tokens, but direct/racing status changes and inconsistent route middleware left a bypass. The regression test received `200` before correction. | `CheckAccountLocked` now applies to the whole API group and is priority-ordered after authentication. Locked access returns `423`; suspended/deactivated access returns `403`. `LoginTest` covers an authenticated Core route. Residual risk: event-driven token revocation still depends on lifecycle changes using the owning service, but request-time enforcement no longer depends on revocation. |
| Confirmed vulnerability | `POST /api/v1/identity/memberships/invite` | A user with `organizations.users.manage` for one organization could submit another organization's UUID and create a foreign membership. This was a cross-tenant write and potential access-grant path. | Authentication and a global permission check existed, but the controller trusted `organization_id` without object-level authorization. The focused test received `201` and created the foreign row before correction. | Invitation now requires either platform-wide `organizations.manage` or explicit administrator assignment to the requested organization before the service writes. The Organizations feature suite asserts `403` and no foreign membership mutation. Residual risk: roles are currently platform-global, so role tenancy cannot be constrained without the planned RBAC architecture change. |
| Defence-in-depth improvement | All HTTP responses | Browser clients previously received no application-defined baseline against MIME sniffing, framing, referrer leakage, or unused powerful browser features. | CSRF, escaped Blade output, and secure cookie configuration already reduced exploitability. Absence of headers alone was not classified as a vulnerability. | A global middleware adds `X-Content-Type-Options`, `X-Frame-Options`, `Referrer-Policy`, and a restrictive `Permissions-Policy`; HSTS is emitted only for HTTPS requests. Tests cover web-health and API responses. CSP is deferred until required UI sources are mapped. |

## Control assessment

| Area | Classification and evidence | Residual risk or deployment action |
|---|---|---|
| Authentication and account lifecycle | Acceptable current control after the correction. API and web login validate bounded input, use generic credential failure behavior, hash passwords, throttle attempts, lock at the configured threshold, and audit login outcomes. Locked/suspended accounts cannot log in, and the API now checks account state consistently. | MFA is not implemented and is deferred product/security architecture. Password-expiry detection exists but forced-expiry workflow is not implemented. |
| Password reset | Acceptable current control. Laravel's broker hashes stored reset tokens, enforces 60-minute expiry/throttling and single use, returns the same request response for known/unknown accounts, and password reset revokes existing Sanctum tokens. Existing tests cover generic lookup and token revocation. | Browser session invalidation after API password reset is not explicitly implemented; decide the desired cross-device session policy before changing it. |
| API tokens and logout | Acceptable current control. Sanctum stores token hashes, token responses occur only at login, configured expiry defaults to 30 days, logout deletes the current personal token, password reset/lock/suspend revoke tokens through the owning service, and request-time account state is enforced. | Tokens use wildcard abilities; least-privilege ability sets and rotation are deferred because consumers and intended token types are not yet defined. Token names are client-supplied but bounded as strings by the login request. |
| Sessions and cookies | Acceptable configuration baseline. Sessions default to encrypted database storage with `HttpOnly`, `SameSite=Lax`, a named cookie, and secure cookies by default in `config/session.php`; web logout invalidates the session and regenerates the CSRF token. | `.env.example` intentionally uses local HTTP and sets `SESSION_SECURE_COOKIE=false`; production must override it, terminate TLS correctly, and configure proxy trust. Deployment state was not audited. |
| Authorization and RBAC | One confirmed invitation defect was fixed. Route permission middleware, policies/Form Requests, and service invariants cover platform and tenant operations. Permissions, rather than display role names, are used. Frontend visibility is not treated as authorization. | Core RBAC roles/direct permissions are platform-global while Identity describes organization-scoped roles. This deliberate architectural mismatch is a future risk; changing the schema/permission cache is deferred. |
| Tenant isolation and IDOR | Acceptable current control with the invitation correction. Staff, Learners, Academics, Attendance, Assessments, Reports, Scheduling, and organization configuration suites exercise representative foreign routes, body relationships, nested resources, lifecycle actions, and exports. Resolvers scope UUIDs and preserve `404` secrecy; denial tests assert no mutation. | Licensing/subscriptions are platform control-plane records, not tenant-context routes. Teacher assignment-aware authorization and exhaustive MySQL composite constraints remain deferred. |
| Suspended organizations, inactive memberships, modules, and licensing | Acceptable current control for implemented rules. `organization.context` requires active membership and active organization. Permission resolution omits permissions from disabled organization modules. Tenant modules require context; platform control-plane routes intentionally remain available to reactivate/safeguard tenants. | Providers/routes remain loaded for disabled modules; enforcement depends on context permissions/policies. Dynamic module unloading and commercial entitlement middleware are not implemented and require architecture/product decisions. |
| Mass assignment and request input | Acceptable current control for audited HTTP paths. No `update($request->all())` was found. The one web `$request->all()` call immediately validates against an explicit rule set with prohibited ownership. Sensitive ownership/actor/lifecycle fields are supplied by services. `forceFill` calls are internal locked workflow updates. | Several models expose broad `$fillable` lists for trusted services. Continue reviewing every caller; do not globally change guarding. `AnalyticsEvent::$guarded = []` is internal recorder-only and is a deliberate trade-off. |
| Validation and dynamic queries | Acceptable current control. Form Requests/controller validation constrain UUIDs, enums, dates, lengths, relationships, pagination, and mutations. Dynamic sort identifiers in Learners, Organizations, Assessments, Reports, and Scheduling are allowlisted; raw aggregates contain static SQL only. Values remain query-bound. | Some report/scheduling filters accept unvalidated scalar query values but use bound query-builder predicates; stricter contract validation is defence-in-depth and should be added only with consumer review. |
| XSS and HTML rendering | Acceptable current control. Blade uses escaped output for user text; tests cover malicious academic descriptions and learner content. PDF views render selected report fields and do not expose private notes. No active raw `{!! !!}` user-content sink was found. | A strict CSP is deferred until inline/style/script dependencies are inventoried. Rich-text sanitization will be required if rich text is introduced. |
| CSV/document exports | Acceptable current control. Attendance, Assessments, Reports, and Scheduling authorize and tenant-scope exports. Their CSV helpers neutralize cells beginning with `=`, `+`, `-`, `@`, tab, or carriage return while leaving normal numeric/date data intact. Existing assessment coverage verifies formula neutralization. PDF download uses a server-generated structure. | Generated PDF filenames include learner/period fields. Framework content-disposition handling mitigates header injection; fully server-generated opaque filenames would be additional defence in depth. |
| File upload/download and path traversal | Acceptable current control for current scope. No application upload endpoint or arbitrary path-based download was found. Storage providers accept internal application paths and are not directly exposed by routes. Report/CSV downloads are generated in memory and authorization-scoped. | Future uploaded documents require private-by-default storage, MIME/content validation, generated names, tenant-scoped authorization, malware handling, and cleanup. |
| Redirects and SSRF | Acceptable current control for current inputs. Web redirects are fixed named/internal destinations; existing tests reject open redirects. No user-controlled server-side URL fetch was found. AI provider base URLs come from operator configuration, not request or tenant payloads; organization AI configuration is not used as a fetch URL. | Operator-controlled Ollama/DeepSeek URLs can reach internal networks by design. Restrict who can edit deployment secrets/configuration and document trusted endpoints. Add URL egress validation only if a user-controlled fetch feature is introduced. |
| CORS, CSRF, and headers | CSRF is active for web routes; bearer-token API requests use the API pipeline. No `config/cors.php` is present, so cross-origin CORS is not enabled by application configuration. Compatible security headers were added. | Explicitly configure a production origin allowlist before cross-origin browser clients are deployed. Do not combine wildcard origins with credentials. CSP remains deferred. |
| Rate limiting | Acceptable current control for public abuse surfaces: global API throttling plus tighter login, reset request/submission, verification resend, and AI provider-test limits. Keys use Laravel's IP/user behavior and tests avoid wall-clock sleeps. | Invitation acceptance, exports, and expensive tenant reports rely on the global limiter. Introduce per-user/organization cost limits when workload/abuse data justifies them. |
| Exceptions, debug, and production leakage | Acceptable current control when `APP_DEBUG=false`. API tests verify generic `500 server_error` responses without exception class, trace, SQL, path, or secrets. Request logging records method/path/status/duration only. | `.env.example` is explicitly local with debug enabled. Production must set `APP_ENV=production`, `APP_DEBUG=false`, and an appropriate log level. Provider exception messages may contain remote response bodies in logs; avoid returning/logging provider payloads if providers begin echoing sensitive prompts. |
| Secrets, credentials, and encryption | Acceptable current control. No committed real secrets were found in audited configuration. Passwords are hashed. Organization AI credentials use `encrypted:array`, are hidden from serialization, and are omitted from audit metadata. Settings service masks encrypted audit values. | Laravel encryption depends on `APP_KEY`; loss makes encrypted values unrecoverable and key rotation is not implemented. Back up/rotate keys through an approved operational plan. Plaintext decrypted values remain available in process memory as required. |
| Logging and auditability | Acceptable current control with limitations. Authentication, organization configuration, education lifecycles, exports, and privileged changes emit audit records. API request logs exclude bodies/query values and credentials. Audit routes are read-only and permission protected. | Audit is workflow-driven, not immutable database-level capture. Retention, tamper-evident external storage, alerting, and operator access are deployment responsibilities. |
| Impersonation and privileged administration | Acceptable because no impersonation feature, route, token, or service exists. Platform-only routes require explicit Core/organization permissions; representative organization policy denial paths are covered. | If impersonation is added, true actor/target identity, start/stop audit, target restrictions, sensitive-action policy, and termination semantics are mandatory. |
| Dependencies and configuration | `composer audit --locked --no-interaction` reported no security advisories. There is no `package-lock.json`, so `npm audit` is not applicable. Production cache/queue/session defaults are persistent drivers; mail/storage/AI remain environment-driven. | An advisory-free lockfile does not prove deployment safety. Patch dependencies through normal review. Validate TLS/proxy/firewall, worker isolation, backup encryption/restore, storage visibility, log retention, CORS, and secrets management in each production environment. |

## Compatibility and deferred work

Existing response envelopes, public identifiers, tenant secrecy, grading,
attendance, scheduling, subscription/licensing rules, and successful endpoint
contracts are unchanged. The invitation endpoint still accepts its established
payload; it now returns `403` for an organization the actor does not administer.
Account-state enforcement changes only requests that should already have been
denied by the documented lifecycle policy.

Deferred work includes enforced MFA; organization-scoped RBAC schema and cache
keys; explicit token ability/rotation policy; forced password-expiry workflow;
cross-device browser-session revocation policy; strict CSP; dynamic module
unloading and full license entitlement enforcement; audit immutability and
retention operations; application-key rotation; and production infrastructure
validation. These require product, consumer, deployment, or architectural
decisions and were not hidden inside this milestone.
