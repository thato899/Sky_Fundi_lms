# Security Policies

## Password Policy

- Minimum length and complexity enforced at registration/change (specific parameters — e.g. minimum 10 characters, no reused passwords — configured in `core/Auth` and documented there once implemented; this document fixes the requirement that a policy must exist and be centrally configurable, not hardcoded per module).
- Passwords are hashed using Laravel's default (bcrypt/argon2id) hashing driver. Plaintext passwords are never logged, including in error logs or audit logs.

## Session Policy

- Session/token expiry is configurable per tenant type (e.g. shorter sessions for shared-device school computer labs).
- Idle timeout and absolute timeout are both supported concepts in `core/Auth`.

## Device Trust

- Optional device-recognition/trust capability so repeat logins from a known device can skip additional friction (e.g. skip 2FA prompt) where a tenant enables it. Not mandatory for v1.0 (see [Roadmap](../roadmap.md)) but the auth data model must not preclude adding it later.

## IP Restrictions

- Optional, per-tenant IP allowlisting (useful for institutions wanting to restrict admin access to on-campus networks). Enforced at the API authentication layer, configurable, off by default.

## Two-Factor Authentication (2FA)

- 2FA-ready from v1.0: the user/auth data model supports a second factor even if not all UI flows for it ship immediately. TOTP-based 2FA is the baseline supported method; SMS/email OTP may be added later per tenant preference.

## Encryption

- Data at rest: sensitive fields (e.g. identity documents, medical/clinic module data) are encrypted at the application layer using Laravel's encryption facilities, in addition to infrastructure-level disk encryption.
- Data in transit: TLS is mandatory for all environments beyond local development; see [Deployment](../deployment/README.md).

## Secrets Management

- Secrets (API keys, AI provider credentials, database credentials) are never committed to the repository. `.env` is git-ignored; `.env.example` documents required keys with placeholder/empty values. See [Environment Variables](../environment-variables.md).
- Production secrets are managed via the hosting platform's secret manager or a dedicated secrets service, not plain `.env` files on disk, once deployment tooling is built (see [Deployment](../deployment/README.md)).

## API Authentication

Summarized in [`../api/authentication.md`](../api/authentication.md); this document covers the security *policy* around it (token lifetime, revocation), the API doc covers the *mechanism*.
