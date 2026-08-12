# 11 — Acceptance Criteria (Major Modules)

Universal criteria apply to **every** module (Definition of Done, Master Prompt §60): register rows
exist; DB + backend + frontend + permissions + validation + error/empty/loading states implemented;
audit + analytics where required; automated, manual, responsive and security tests pass; docs
updated; no blocking defects. The criteria below are the module-specific additions.

## Platform foundation

- **AUTH**: signup→verify→login→reset E2E green; lockout + rate limits proven; sessions revocable; MFA enrollable.
- **TEN/RBAC**: user in two orgs sees correct data per org; invitation E2E green; every role matrix-tested (allow/deny); cross-tenant suite green.
- **BILL/ENTL**: all five §34 billing/signup/upgrade/failed-payment workflows green against provider sandbox; webhook replay is idempotent; entitlement changes take effect without deploy; usage meters visibly accurate.
- **ONBD**: full wizard completable, resumable mid-flow, and skippable steps recorded on the setup checklist.
- **INTG**: connect → sync → disconnect → reconnect proven per connector; failure modes degrade gracefully in dependent UIs.
- **ADMIN**: platform staff manage tenants/plans/flags without any cross-tenant data exposure; impersonation is permissioned, indicated, audited, expiring.

## Product modules

- **CRM**: contacts/companies/leads/accounts/opportunities/pipelines/deals full CRUD with custom fields, import (map→validate→preview→dedupe→error report→history), export, dedupe, routing, ownership, saved views, activity timeline; attribution fields preserved end-to-end; 1M-contact seed stays performant (paginated lists < 1s).
- **Marketing automation (AUTO/EMAIL/SMS)**: trigger→condition→action workflows execute on queues with run history and failure alerts; consent/suppression/unsubscribe/STOP enforced on every send path; deliverability events (bounce/complaint) update records.
- **SEO suite (TSEO/KSEO/LSEO)**: keyword + competitor rank tracking runs on schedule with history charts; site audits produce prioritized findings; local entities (GBP, citations, locations) tracked per location; ranking-drop alerts fire.
- **AI search (AEO/GEO/LLMO/AIVIS)**: prompt library monitored across configured AI engines; mentions/citations/share-of-voice trends recorded; competitor comparison and change alerts work; per-check AI cost tracked against tenant limits.
- **Ads (PPC/LIAD/META/RETG)**: accounts connect; campaigns/metrics sync into daily rollups; spend/CPL/ROAS dashboards match provider numbers within sync tolerance; budget alerts fire; audience/retargeting objects manageable where APIs allow.
- **Sales (LSCR/INTENT/ALERT/BOOK/ABM/ENAB)**: scoring rules + AI scoring reproducible per contact with score history; intent signals raise routed real-time alerts; booking pages handle availability, round-robin, reminders, no-show workflow, meeting attribution; ABM tiers/committees/engagement tracked; enablement asset library permission-gated.
- **Analytics (ANLY/ATTR/CALL/BENCH/CINT/GSCORE)**: unified dashboard covers traffic→leads→SQLs→meetings→pipeline→MRR→ROI with date filters; attribution chain preserved keyword→revenue across models (first/last/multi-touch); call tracking numbers attribute calls; benchmarks are anonymized aggregates only; growth score computes 0–100 with drill-down and trend.
- **AI (AISA/AIPF)**: chatbot qualifies + books with human-visible transcripts; AI CRM updates and outbound drafts require configurable human confirmation; every AI call logged with tenant/user/feature/model/cost; plan limits enforced.
- **PORTAL/PROJ**: client users see only portal-permitted views (campaign/project status, approvals, reports, tickets); approval actions notify and are audited; projects manage sprints/deliverables/deadlines.
- **WEB/CRO/CONT**: landing pages/forms publishable with attribution capture; A/B experiments record variants and outcomes; content items flow brief→draft→approval→published with SEO metadata.

## Final release gate

Production readiness audit per Master Prompt §62–63 — full register reconciliation (every row
Implemented+Verified or Not-Applicable-with-justification), the five E2E workflows green, security/
operations/UX checklists complete.
