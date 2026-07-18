# Hackathon subscriptions and profitability

The demo extends Core Licensing and Subscriptions; it does not collect payments. `/subscription` is labelled “Demo billing” and “Billing integration pending”.

| Plan | Monthly assumption | Learners | Staff | AI markings |
| --- | ---: | ---: | ---: | ---: |
| Starter | R499 | 100 | 5 | 50 |
| Growth | R1,499 | 500 | 25 | 500 |
| School Pro | from R3,999 | 1,500 | 75 | 2,000 |

Values live in `config/hackathon.php`. Monthly revenue is the plan price plus future add-ons. Estimated variable cost is stored AI usage plus notification, hosting and support assumptions. Contribution margin is revenue less those costs; margin percentage is margin divided by revenue.

Figures exclude taxes, payment-processing fees and unconfigured add-ons. They are planning estimates, not audited profit or production traction. The organization comes from trusted membership context and every query is organization-scoped. Individual learner API costs and raw provider output are not exposed.
