# API Authentication

## Mechanism

The API uses token-based authentication (Laravel Sanctum-style personal access tokens for first-party web/mobile clients; groundwork left open for OAuth2/Passport if third-party integrations are needed later). Concrete implementation is a `core/Auth` responsibility and will be documented in detail there once built.

## Request Shape

```
Authorization: Bearer <token>
```

## Session vs Token

- Blade (server-rendered) web UI may use standard Laravel session auth for its own routes.
- All API routes (`/api/v1/...`), regardless of caller, authenticate via bearer token — including requests originating from the Blade frontend's own AJAX calls, to keep one authentication path for the API surface.

## Multi-Tenant Context

A token is scoped to a single user, and a user's active tenant context is resolved as part of authentication (see [Multi-Tenancy](../architecture/multi-tenancy.md)). Users who belong to multiple tenants (e.g. a tutor working across two centres) switch active tenant context via a dedicated endpoint; this does not require re-authentication.

## Mobile Considerations

Flutter/Android clients authenticate the same way as web — there is no separate "mobile API." Token expiry/refresh behavior must be documented in `core/Auth`'s own README once implemented, with mobile-appropriate refresh flows (long-lived refresh token + short-lived access token) as the target design.

## 2FA

Two-factor authentication is a Core capability (`core/Auth`), applied at login, independent of and prior to API token issuance. See [Security](../security/README.md).
