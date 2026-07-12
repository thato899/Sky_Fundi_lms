# core/Mail

**Purpose**: a provider-selection layer on top of Laravel's own mail transport configuration, so the platform can name and switch between SMTP, Microsoft 365, Google Workspace, Mailgun, and Amazon SES without callers ever depending on a specific transport. Part of the Sky Fundi Platform Core, per [/core/README.md](../README.md).

**Deliberately does not replace Laravel Mail.** `config/mail.php` remains the single source of truth for how each mailer actually connects (SMTP host/port, SES/Mailgun credentials via `config/services.php`); `config/mail_providers.php` is Core\Mail's own registry of provider *metadata* (which `MailProviderInterface` adapter exists and which Laravel mailer name it resolves to). Callers keep authoring standard Laravel `Mailable`/`Notification` classes.

**Responsibilities**:
- `Contracts/MailProviderInterface` — `name()/isAvailable()/mailerName()`.
- `Infrastructure/Providers/{SmtpMailProvider,SesMailProvider,MailgunMailProvider}` — fully implemented; each reports availability from its own config and resolves to the matching `config/mail.php` mailer.
- `Infrastructure/Providers/{Microsoft365MailProvider,GoogleWorkspaceMailProvider}` — real, registered, plug-and-play placeholders (both are OAuth2-based, unlike the SMTP-credential transports Laravel supports natively) — mirrors [AI Gateway](../AIGateway/README.md)'s placeholder pattern exactly via `AbstractPlaceholderMailProvider`.
- `Application/MailProviderFactory` / `MailProviderRegistry` — mirror `Core\AIGateway`'s Factory/Registry.
- `Application/MailManager::mailer()` — resolves the platform's configured default (or an explicitly named) provider to a real `Illuminate\Contracts\Mail\Mailer`, throwing `Core\Support\Exceptions\ProviderNotAvailableException` if unavailable.

**Templates**: transactional email content is not authored here — see [`core/Notifications`](../Notifications/README.md)'s `NotificationTemplate` (channel `mail`), which this service's resolved mailer ultimately sends through.

**Allowed dependencies**: `Core\Support`. Never a module.

**Routes**: `GET /api/v1/mail/providers` (permission `core.settings.manage`) — lists providers and availability.
