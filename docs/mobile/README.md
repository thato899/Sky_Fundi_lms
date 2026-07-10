# Mobile API Readiness

Sky Fundi's API is designed for equal consumption by web, Flutter, and native Android/iOS clients — there is no separate "mobile API," only the one versioned REST API described in [`../api/conventions.md`](../api/conventions.md).

## What "Mobile Ready" Means Concretely

- **Stateless, token-based auth** (see [`../api/authentication.md`](../api/authentication.md)) rather than cookie/session-only auth, so native clients can authenticate without a browser context.
- **Predictable, versioned payloads** so a shipped mobile app binary keeps working against a `v1` API even as the platform evolves additively.
- **Pagination and partial responses** designed with bandwidth-constrained/offline-prone clients in mind (see [API Conventions — Pagination](../api/conventions.md#filtering-sorting-pagination)).
- **Idempotent write endpoints** for actions likely to be retried on flaky mobile connections (e.g. attendance submission), per [API Conventions — Idempotency](../api/conventions.md#idempotency).

## Offline Support

Full offline-first mobile support (local data caching, conflict resolution on sync) is a **v2.0 roadmap item** ("Offline AI" and broader offline support — see [Roadmap](../roadmap.md)). The API and data model must not preclude this: entities that matter for offline workflows should carry client-assignable idempotency keys and clear `updated_at` timestamps usable for sync/conflict resolution from day one, even before a Flutter client exists.

## Push Notifications

Delivered through `core/Notifications`, which is provider-agnostic (the specific push provider — FCM, APNs via a unified service, etc. — is an infrastructure choice documented in `core/Notifications`'s own README once implemented).

## Status

No Flutter or Android client exists in this repository yet. This document defines the API-level contract those future clients will be built against.
