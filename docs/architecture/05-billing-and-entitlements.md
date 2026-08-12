# 05 — Billing, Subscription & Entitlement Architecture

Billing is a first-class product system designed before feature modules (Master Prompt §7–§9, §66.18).

## Plans & pricing

Suggested tiers (configurable rows in DB — never hard-coded): **Starter, Growth, Professional,
Agency, Enterprise.** Pricing dimensions supported: monthly/annual (annual discount), per-user seats,
feature-based tiering, usage-based meters (AI credits, keywords, emails/SMS), add-ons, custom
enterprise pricing. Coupons/promo codes at checkout.

## Provider abstraction

A `PaymentProvider` interface (create customer, checkout, subscription CRUD, invoices, refunds,
webhooks) with **Stripe as the first implementation**. Business logic depends on our own
`subscriptions`/`invoices` tables — provider objects are synced into them, so the core never locks
to one provider.

## Checkout flow

Plan → billing details → company details → tax info → promo code → payment method → order summary
→ payment → subscription activation → invoice/receipt. Trials may skip payment capture (configurable).

## Subscription lifecycle (state machine)

```
trialing ──expire──▶ trial_expired ──pay──▶ active
active ⇄ upgrade/downgrade/quantity (prorated)
active ──payment_failed──▶ past_due ──grace elapsed──▶ suspended ──resolve──▶ active
active ──cancel──▶ pending_cancellation ──period end──▶ cancelled ──reactivate──▶ active
```

Every transition: recorded, audited, entitlement recalculation triggered, customer notified (NOTIF).

## Webhook processing

Endpoint verifies provider signature; events stored raw in `billing_events` with provider event ID as
idempotency key; processed by queue workers with retry; handlers cover checkout.completed,
payment.succeeded/failed, subscription.created/updated/cancelled, invoice.created/paid/payment_failed,
refund.created. Unprocessed/failed events alert platform admins.

## Billing portal (customer-facing)

View plan & usage, upgrade/downgrade, change payment method, invoice list + PDF download, billing
history, billing contact, tax details, cancel/reactivate — all Owner/Billing Admin permission-gated.

## Entitlements

Single resolution chain (Master Prompt §8): **Plan → Entitlements → Limits → Tenant Subscription →
Feature Access.**

- `entitlements` rows: `(plan_id, feature_key, access: none|limited|full, limit_value?)`.
- One central service answers `can(tenant, feature)` and `remaining(tenant, meter)`; cached per tenant,
  invalidated on subscription events. No plan checks scattered through feature code.
- Feature keys align with register module codes (e.g. `aivis`, `abm`, `api.public`).

## Usage metering

`usage_records` per tenant/meter/period, incremented by the owning service (contacts, users, emails,
SMS, AI credits, keywords, competitors, locations, websites, storage, API calls, automations, workflow
executions, reports, retention). UI shows current / allowance / remaining / overage; enforcement is
soft-warn at threshold, hard-block or overage-bill at limit (per-meter policy).
