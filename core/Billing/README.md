# core/Billing

**Purpose**: real payment and invoicing — the payment-gateway integration, subscription checkout, and usage-linked invoicing that `core/Subscriptions` explicitly reserved `external_reference`/`metadata` for but never implemented. Part of the Sky Fundi Platform Core, per [/core/README.md](../README.md). See [docs/adr/010-payfast-payment-gateway.md](../../docs/adr/010-payfast-payment-gateway.md) for why PayFast and how plan/invoice data is modelled.

## Scope

- **Gateway abstraction.** `Contracts\PaymentGatewayInterface` is the contract every gateway adapter implements — no module or Core service may call a gateway SDK/API directly. `Application\GatewayManager`/`GatewayFactory` resolve a gateway from `config/billing.php`, mirroring `Core\AIGateway`'s provider pattern. `Infrastructure\Gateways\PayFastGateway` is the one real adapter.
- **Checkout.** `Application\CheckoutService` builds a Pending `Payment` row and a signed gateway redirect for either a new subscription signup (`startSubscriptionCheckout`) or settling an issued `Invoice` (`startInvoiceCheckout`). A Payment is never marked paid client-side.
- **Webhook.** `Application\PaymentWebhookService` is the only place a `Payment` transitions out of Pending. It requires both of the gateway's trust checks (`verifyWebhookSignature()` — local MD5 signature; `confirmWithGateway()` — source-IP allow-list plus a server-to-server confirmation call back to the gateway) to pass before touching any state, is idempotent against a replayed notification, and branches on `Payment::purpose` to either start a Subscription (via `Core\Subscriptions\Application\SubscriptionService::start()`) or mark an Invoice paid.
- **Usage-linked invoicing.** `Application\InvoiceService::generateForPeriod()` builds an `Invoice` with a base plan-fee line plus, when attendance-active learners in the period exceed the plan's `max_learners` cap, an overage line — the count is computed from real `Attendance` entries by `App\Console\Commands\GenerateInvoicesCommand` and passed in, never queried by Core\Billing itself (Core never depends on a module — see "Allowed dependencies"). Idempotent per subscription/period. `issue()` sets a due date and notifies the organization's administrators; `markPaid()` is called only from the webhook path; `markOverdueAndGraceSubscriptions()` is the dunning trigger — any Issued invoice past its due date moves to Overdue and starts the subscription's existing grace-period mechanism in `core/Subscriptions` (suspension after grace remains Subscriptions' own scheduled sweep; Billing only starts the clock).
- **Guardian Portal.** `Modules\Learners\Http\Controllers\Web\GuardianBillingController` (in the Learners module, which is allowed to depend on Core) shows a guardian their organization's invoices and lets them pay one, gated on the same `receives_financial_communication` relationship flag the academic side already gates on `receives_academic_communication`.

## Data model

- `plans` (owned by `core/Subscriptions`, not Billing) — pricing/entitlements a `Subscription.plan` string key resolves to. See `core/Subscriptions/README.md`.
- `invoices` / `invoice_line_items` — one invoice per subscription per billing period (unique-constrained), with priced lines.
- `payments` — one row per checkout attempt; `gateway_reference` is the payment's own id, echoed to the gateway as its passthrough reference so a webhook is matched without trusting anything gateway-invented. `gateway_response` stores only operational fields (gateway transaction id, status, amounts) — never the payer's name/email or other personal data a webhook payload may carry, per AGENTS.md's prohibition on logging personal data.

## Allowed dependencies

`Core\Subscriptions` (subscription lifecycle stays there — Billing drives it via `SubscriptionService` calls and never duplicates its state machine), `Core\AuditLogs` (every Billing event implements `Auditable`), `Core\Notifications`. Never a module — see `Application\InvoiceService`'s docblock for why the attendance-usage figure is computed by a caller in `app/` instead.

## Routes

`POST /api/v1/billing/checkout/subscription`, `POST /api/v1/billing/invoices/{invoice}/checkout`, `GET /api/v1/billing/invoices[/{invoice}]` — all gated by `core.billing.manage` and organization context. `POST /api/v1/billing/webhooks/payfast` is deliberately public (no auth, no CSRF) — trust comes from the gateway's own signature and confirmation call, not from Laravel auth. `GET /api/v1/plans` (in `core/Subscriptions`) lists active plans.

Web: org-admin `/billing` (`App\Http\Controllers\BillingController`); guardian `/guardians/{guardian}/invoices`.

`core.billing.manage` (declared in `config/permissions.php`) was previously granted only to the platform-wide "Platform Administrator" role by `database/seeders/RoleSeeder.php`. `Database\Seeders\BillingPermissionSeeder` grants it to the per-organization "Organization Administrator" role too, mirroring every module's own permission seeder — without it, an org admin could not self-service billing at all.

## Known limitations

Only PayFast is implemented (see the ADR for why, and for adding a second gateway later). Live end-to-end webhook verification requires sandbox merchant credentials not present in this repository — the gateway and webhook handler are verified against PayFast's documented request/response shapes, not a live sandbox round-trip, until credentials are supplied locally. Renewal billing is invoice-driven (the scheduled generator issues a new invoice each cycle and the org/guardian pays it) rather than dependent on PayFast's own recurring auto-charge firing correctly — the checkout request includes PayFast's recurring fields for future use but nothing in this system currently relies on that auto-charge behaviour.
