# core/Notifications

**Purpose**: the platform's notification framework — database and mail live today; SMS, WhatsApp, and push are real, registered, plug-and-play placeholder channels. Part of the Sky Fundi Platform Core, per [/core/README.md](../README.md).

**Responsibilities**:
- `Infrastructure/Models/NotificationTemplate` — editable, per-type-per-channel message bodies with `{{placeholder}}` rendering, so copy changes don't need a deploy.
- `Infrastructure/Models/NotificationPreference` — per-user, per-type, per-channel opt-in/out (opt-out model: no row means the channel's default applies). Channel names are free strings, so `sms`/`whatsapp`/`push` preferences work today even though the transports aren't live yet.
- `Infrastructure/Notifications/CoreNotification` — a single, generic, queued (`ShouldQueue`, dispatched onto the `notifications` named queue — see [`core/Queue`](../Queue/README.md)) Notification class driven by a template, dispatched via whichever channels are resolved as enabled. Core services and future modules call `Application/NotificationService::send()` rather than authoring bespoke Notification classes per message.
- `Infrastructure/Channels/{Sms,WhatsApp,Push}Channel` — real, registered channel classes (mirroring [AI Gateway](../AIGateway/README.md)'s placeholder-provider pattern) that throw a clear `NotificationChannelNotAvailableException` if actually selected, rather than silently failing or being absent from the channel map in `CoreNotification::via()`.
- Standard Laravel `notifications` table (database channel) is migrated here.

**Allowed dependencies**: `Core\Users`, `Core\Queue`. Never a module.

**Routes**: `GET/PUT /api/v1/notifications/preferences` — self-service, authentication only (a user manages their own preferences).

**Future usage**: implementing SMS/WhatsApp/push for real means rewriting the matching channel class's `send()` method to call a real gateway (Twilio/Africa's Talking, WhatsApp Business API, FCM/APNs) — `CoreNotification`, `NotificationService`, and every call site are already channel-agnostic. See [Mobile](../../docs/mobile/README.md).

