# ADR-010: PayFast payment gateway

Status: Accepted

## Decision

Real billing is implemented in a new `core/Billing` service against **PayFast** as the sole payment gateway, evaluated against Yoco and Ozow on the three criteria that matter for Sky Fundi's subscription model: recurring billing, webhook reliability, and sandbox availability.

- **Recurring billing.** PayFast's checkout form accepts `subscription_type`/`frequency`/`cycles` fields directly on the same redirect flow used for a once-off payment, so signup and recurring renewal share one integration path. Ozow is EFT-only with no native recurring primitive — recurring billing would have to be hand-rolled by re-requesting a new EFT payment every cycle, with no token to charge automatically. Yoco's public API is checkout-oriented; recurring/subscription support is less mature and less documented for server-side dunning flows.
- **Webhook reliability.** PayFast's Instant Transaction Notification (ITN) is a well-documented three-step verification (signature check, source-IP allow-list, server-to-server confirmation callback to PayFast) that a backend can implement and test deterministically. This is the model `core/Billing`'s webhook handler implements.
- **Sandbox availability.** PayFast publishes a merchant sandbox (`sandbox.payfast.co.za`) usable without a live business account, matching this project's need to build and test the integration before real merchant credentials exist (see status.md / this ADR's Consequences).

`core/Billing` depends on `core/Subscriptions` (subscription lifecycle stays there — Billing drives it via `SubscriptionService` calls and domain events, never duplicates its state machine) and `core/AuditLogs`. The gateway itself sits behind `Core\Billing\Contracts\PaymentGatewayInterface`, the same shape as `AIProviderInterface` in `core/AIGateway`, so a future second gateway is a new adapter, not a rewrite. Only the one real `PayFastGateway` implementation is built now — no speculative placeholder adapters for Yoco/Ozow, unlike AIGateway's Claude/Gemini placeholders, because there is no existing product requirement for multiple simultaneous gateways.

Plan tiers move out of `config/hackathon.php` (demo-only, not persisted, not addressable by a gateway) into a `plans` table owned by `core/Subscriptions` — the existing owner of `Subscription.plan` — keyed by the same string identifiers already used across the codebase (`starter`, `growth`, `school_pro`), so `subscriptions.plan` keeps working as a plain string reference rather than requiring a new foreign key column. `core/Billing` reads `Plan` for price and billing-cycle data when building a checkout or an invoice line item.

Invoices are usage-linked: each cycle's invoice carries a base plan-fee line plus an overage line computed from real Attendance and enrolment data — active learners in the cycle beyond the plan's `max_users` cap, where "active" means the learner has at least one attendance entry in the billing period (Attendance-derived usage), not just an enrolment row. This keeps the "invoice reflects real usage" concept genuine without inventing a per-attendance-day pricing model no plan advertises.

## Consequences

Sky Fundi can run a real sandbox payment through webhook-verified confirmation instead of a client-marked-paid demo flag. `core/Subscriptions` gains no new dependency (Billing depends on it, not the reverse), so its existing tests and contract are unaffected. `config/hackathon.php`'s `plans` array becomes a one-time migration source and is no longer the runtime source of truth; the hackathon profitability view is updated to read `Plan` instead.

Live verification against a real ITN webhook requires sandbox merchant credentials that do not exist in this repository; until they are supplied via a local, non-committed `.env` value, the gateway adapter and webhook handler are verified against PayFast's documented request/response shapes with `Http::fake()` and unit-level signature tests, not a live sandbox round-trip — this gap is called out explicitly in verification reporting, not silently treated as done. Yoco and Ozow remain unimplemented; adding either later means writing a new class against `PaymentGatewayInterface`, not touching `core/Billing`'s application layer.
