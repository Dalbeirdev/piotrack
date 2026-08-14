# Module Specification — Sales (LSCR / INTENT / ALERT / BOOK / ENAB / ABM)

> Stage 10. Complete before coding (Master Prompt §58–59). Depends on CRM (Stage 5), Marketing
> (Stage 6 — lead_score/lifecycle, workflows, NOTIF), Content (Stage 9), INTG + entitlements
> (Stage 4/3), RBAC/audit (Stage 2). No new ADR: the whole tested core is first-party/computed;
> third-party intent data + calendar sync go through the existing **INTG** connector framework.

## Purpose

The sales-operations layer that turns marketing activity into prioritized, actionable pipeline:
score leads and accounts, detect buyer intent, alert reps, book meetings, and arm the sales team with
enablement content. Nearly all of it is computed from our own first-party CRM/marketing/content data
and is fully tested; third-party intent enrichment (reverse-IP/Bombora) and calendar sync
(Google/Outlook) are surfaced as INTG connectors (Planned) with the in-house engine working today.

## Users & roles

Sales Manager (full), Sales Rep (view + own bookings/alerts), Marketing Manager (scoring/ABM),
Analyst/Viewer (view). Permissions:

- `sales.view` — view sales surfaces + analytics.
- `sales.scoring.manage` — manage lead-scoring rules + recompute.
- `sales.alerts.manage` — manage alert rules.
- `sales.booking.manage` — manage booking pages.
- `sales.enablement.manage` — manage enablement assets + plays.
- `sales.accounts.manage` — manage ABM target accounts.

Public booking submission is unauthenticated (tenant resolved by the booking-page slug).

## Feature IDs (register rows in scope)

- **LSCR-001…019** — lead scoring. **Built + tested**: rules-based engine (demographic/firmographic/
  behavioral/intent categories) → points → `lead_score`, temperature (hot/warm/cold), SQL threshold →
  lifecycle promotion, automatic routing (owner assignment). Predictive/AI scoring (LSCR-014/015) →
  Partial (the rule engine is the deterministic base; ML later, Stage 12).
- **INTENT-001…016** — buyer intent. **Built + tested**: first-party intent signals (page-visit/
  service/pricing/download/campaign/ad/CRM-activity), intent scoring, high-intent + bottom-funnel +
  buying-window detection, recommended next action, and sales-alert firing. Anonymous-visitor/company
  identification + third-party intent feeds (INTENT-001/002) → Planned (need a tracking script +
  reverse-IP/intent-data connector via INTG).
- **ALERT-001…009** — sales alerts. **Built + tested**: alert rules (score threshold/high-intent/
  meeting-request/repeat-visit/bottom-funnel) → in-app CRM alerts + email (existing NOTIF). SMS/Teams/
  Slack alerts (ALERT-002/004) → Partial (need the SMS + Slack/Teams connectors).
- **BOOK-001…012** — appointment booking. **Built + tested**: booking pages (meeting types, duration,
  availability, round-robin/fixed assignment, qualification), public booking → creates a booking +
  contact + activity + source attribution, reschedule/cancel, no-show + follow-up statuses, reminders
  (scheduled). Two-way calendar sync (BOOK-001) → Planned (INTG Google/Outlook connector).
- **ENAB-001…019** — sales enablement. **Built + tested**: an asset library (decks/one-pagers/
  battlecards/scripts/email+follow-up templates/ROI/proof/persona guidance) + sales plays (ordered
  steps + target segment). Training (ENAB-018) → Partial (assets host the material).
- **ABM-001…019** — account-based marketing. **Built + tested**: target-account lists from CRM
  companies with tiers (1/2/3), account scoring (aggregate contact scores + intent), buying-committee
  mapping (a company's contacts), account status pipeline, account plays. Company enrichment/org-chart
  (ABM-004/007) → Partial; LinkedIn/email/retargeting ABM (ABM-012/013/014) reuse Stages 6/8;
  personalized landing pages (ABM-010) → Marketing (Stage 6).

Full per-ID status lands in the register at gate time (honest §38).

## Subscription requirements

- New `Feature::Sales` gates the whole module (`entitlement:sales`), granted on Professional/Agency/
  Enterprise (advanced sales ops). Starter/Growth → blocked.

## Database entities (all tenant-scoped via `BelongsToTenant`)

- `scoring_rules` (id, org, name, category, attribute, operator[equals|contains|gte|is_true], value,
  points, is_active).
- `intent_signals` (id, org, contact_id, type, weight, url, occurred_at).
- `alert_rules` (id, org, name, trigger, threshold, channel[in_app|email], is_active).
- `sales_alerts` (id, org, contact_id nullable, type, message, is_read).
- `booking_pages` (id, org, user_id[owner], name, slug unique, meeting_type, duration_minutes,
  availability json, assignment[fixed|round_robin], is_active).
- `bookings` (id, org, booking_page_id, contact_id nullable, name, email, scheduled_at, status[booked|
  completed|canceled|no_show], source, notes, owner_id).
- `sales_assets` (id, org, type, title, description, content, url, tags json).
- `sales_plays` (id, org, name, description, steps json, target_segment).
- `target_accounts` (id, org, company_id, tier, status, account_score, notes) — unique(org, company).

## Services (in-house, tested)

- `LeadScoringService` — evaluate a contact against active rules → total score + temperature
  (hot ≥ hotThreshold, warm ≥ warmThreshold, else cold); update `lead_score`; SQL threshold promotes
  `lifecycle_stage`; routing assigns an owner. `recomputeAll()` for bulk.
- `IntentService` — record a signal; `intentScore(contact)` = sum of recent signal weights;
  `isHighIntent`; `nextAction(contact)` heuristic (e.g. pricing view → "Reach out today").
- `AlertService` — evaluate a contact/event against alert rules → create `sales_alerts` + notify
  owners via NotificationDispatcher (in-app + email).
- `BookingService` — `book(page, data)`: create booking (+ dedupe contact by email + activity +
  round-robin owner + source), reschedule/cancel/markNoShow; reminder scheduling.
- `AccountService` — `score(targetAccount)` = aggregate the company's contacts' lead_score + intent;
  `buyingCommittee(account)` = the company's contacts; tier/status management.

## API / endpoints (authenticated, `/sales/*`, web, `organization` + `entitlement:sales` + `can:`)

Dashboard; scoring rules CRUD + `POST /sales/scoring/recompute`; intent signals list + score;
alert rules CRUD + alerts list + `POST /alerts/{a}/read`; booking pages CRUD + bookings list +
status; enablement assets + plays CRUD; ABM accounts CRUD + `POST /accounts/{a}/rescore`.
**Public** (no auth, tenant by slug): `GET /b/{slug}` render + `POST /b/{slug}` book.

## UI pages & components

Sidebar **Sales** group: Dashboard, Scoring, Intent, Alerts, Booking, Enablement, Accounts. Reuse
design-system primitives; a scoring-rules editor + a scored-contacts table with temperature badges; an
intent feed with scores + next actions; an alerts inbox; booking pages + bookings; an enablement
library; an ABM accounts board with tiers + scores. Public: a minimal Blade booking page.

## Integrations

Third-party intent data + reverse-IP company ID + calendar sync via INTG connectors (Planned). SMS/
Slack/Teams alerts via the messaging + Slack connectors (Partial).

## Background jobs

`SendBookingReminders` (scheduler `sales:send-booking-reminders`, hourly) — remind upcoming bookings;
re-establish tenant context; idempotent per booking.

## Business rules & validation

Scoring rules evaluate against contact attributes + intent; temperature thresholds configurable in
code with sensible defaults. SQL threshold promotes lifecycle to `sql`. Booking dedupes the contact by
email; round-robin cycles active owners; a slug is unique. Alerts are deduped per (contact, type).

## Audit requirements

`sales.scoring.recomputed`, `sales.rule.created`, `sales.alert.created`, `sales.booking.created|
status_changed`, `sales.account.created|rescored`.

## Automated tests

Scoring: rule evaluation (each operator) + temperature + SQL threshold + routing + recompute. Intent:
record signal + intent score + high-intent + next action. Alerts: rule fires an alert + notifies +
dedupe. Booking: public book creates booking + contact + activity + round-robin; reschedule/cancel/
no-show. Enablement: asset + play CRUD. ABM: account scoring aggregates contacts + buying committee.
RBAC matrix + `sales` entitlement gating + tenant isolation on every entity.

## Manual QA checklist

Create scoring rules → recompute → a contact shows a temperature; record an intent signal → intent
score + next action; an alert fires; publish a booking page → book on the public URL → booking +
contact created; add an enablement asset; add a target account → account score. Responsive + empty
states.

## Acceptance criteria

- [ ] Lead + account scoring, intent, alerts, booking, enablement all tenant-scoped + tested.
- [ ] Public booking works unauthenticated + tenant-safely; round-robin + activity + dedupe.
- [ ] Every route `can:`- + entitlement-gated; tenant-isolated. Full gate green; honest register; report.
