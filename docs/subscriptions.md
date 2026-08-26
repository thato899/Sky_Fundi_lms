# Hackathon subscriptions and profitability

`/subscription` is a demonstration profitability view over Core Licensing and Subscriptions; it does not collect payments and is labelled "Demo billing". **Real billing lives at `/billing`** — see [core/Billing/README.md](../core/Billing/README.md) and [ADR-010](adr/010-payfast-payment-gateway.md): a real PayFast checkout, webhook-verified payment confirmation, and usage-linked invoices generated from actual Attendance/enrolment data. The two pages read the same underlying `Plan`/`Subscription` records; `/subscription` remains a read-only estimate view and is not where an org actually subscribes or pays.

| Plan | Monthly assumption | Learners | Staff | AI markings |
| --- | ---: | ---: | ---: | ---: |
| Starter | R499 | 100 | 5 | 50 |
| Growth | R1,499 | 500 | 25 | 500 |
| School Pro | from R3,999 | 1,500 | 75 | 2,000 |

Values live in the `plans` table (`Core\Subscriptions\Infrastructure\Models\Plan`, seeded from these same figures by `PlansSeeder`) — `config('hackathon.plans')` is retained only as historical seed values and is no longer read at runtime. Monthly revenue is the plan price plus future add-ons. Estimated variable cost is stored AI usage plus notification, hosting and support assumptions. Contribution margin is revenue less those costs; margin percentage is margin divided by revenue.

Figures exclude taxes, payment-processing fees and unconfigured add-ons. They are planning estimates, not audited profit or production traction. The organization comes from trusted membership context and every query is organization-scoped. Individual learner API costs and raw provider output are not exposed.
