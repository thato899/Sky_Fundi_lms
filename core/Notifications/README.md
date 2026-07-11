# core/Notifications

**Purpose**: the platform's notification framework — database and email today, push/SMS planned. Part of the Sky Fundi Platform Core, per [/core/README.md](../README.md).

**Responsibilities**:
- `Infrastructure/Models/NotificationTemplate` — editable, per-type-per-channel message bodies with `{{placeholder}}` rendering, so copy changes don't need a deploy.
- `Infrastructure/Models/NotificationPreference` — per-user, per-type, per-channel opt-in/out (opt-out model: no row means the channel's default applies).
- `Infrastructure/Notifications/CoreNotification` — a single, generic, queued (`ShouldQueue`) Notification class driven by a template, dispatched via mail and/or database channels depending on resolved preferences. Core services and future modules call `Application/NotificationService::send()` rather than authoring bespoke Notification classes per message.
- Standard Laravel `notifications` table (database channel) is migrated here.

**Allowed dependencies**: `Core\Users`. Never a module.

**Routes**: `GET/PUT /api/v1/notifications/preferences` — self-service, authentication only (a user manages their own preferences).

**Future usage**: push (FCM/APNs) and SMS channels are additional `via()` entries on `CoreNotification` plus a new channel driver — no changes needed to `NotificationService`'s call sites. See [Mobile](../../docs/mobile/README.md).
