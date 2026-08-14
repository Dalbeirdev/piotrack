# Module Specification — Content & Authority (CONT / SOC / VID / POD / REP / DPR / LINK)

> Stage 9. Complete before coding (Master Prompt §58–59). Depends on CRM+Marketing (Stages 5–6),
> SEO keywords (Stage 7), INTG + entitlements (Stage 4/3), RBAC/audit (Stage 2). ADR-0007 governs
> the social + review data sources.

## Purpose

Author content and build authority: a content hub (articles, pages, videos, podcasts, lead magnets)
with an editorial workflow + calendar + optimization scoring; social publishing + engagement;
reputation/review management; and PR + link-building outreach. The authorable/computable half is built
in-house and tested; live social publishing + review sync are real code behind provider abstractions,
untested here for lack of credentials (ADR-0007, §38).

## Users & roles

Marketing Manager (full), Marketing User (author/schedule), Analyst/Sales/Viewer (view). Permissions:

- `content.view` — view content/social/reputation/outreach surfaces + analytics.
- `content.pieces.manage` — create/edit content pieces + move workflow.
- `content.social.manage` — create/schedule/publish social posts.
- `content.reputation.manage` — manage reviews, requests, authority assets.
- `content.outreach.manage` — manage PR/link outreach campaigns + prospects.

## Feature IDs (register rows in scope)

- **CONT-001…040** — content hub + editorial + optimization. **Built + tested**: content pieces of all
  types (blog/service/location/industry/comparison/solution/FAQ/thought-leadership/case-study/
  whitepaper/ebook/guide/checklist/report/pillar), funnel stage (tof/mof/bof), lead magnets,
  editorial workflow (idea→draft→review→approved→published→archived), content clusters (pillar +
  members), keyword mapping (Stage 7), and an **optimization score** (SEO/CTA/internal-link/length
  heuristics). Actual copywriting/refresh execution is human work; the platform tracks + scores it.
- **SOC-001…027** — social management. **Built + tested**: posts to LinkedIn/Facebook/X/YouTube (+
  "other"), content calendar, scheduling, post types (thought-leadership/educational/case-study/
  testimonial/video/lead-gen), engagement metrics + analytics via the fixture social driver. Live
  publishing, community/comment management, social listening/monitoring, paid social → Partial/Planned
  (paid social is Stage 8; listening needs a monitoring API).
- **VID-001…018** — video marketing. **Built + tested** as content pieces of type `video` (+ podcast),
  YouTube/video-SEO metadata fields, video landing pages (Marketing Stage 6). Video ads/retargeting →
  Stage 8; personalized/sales video + video email → Partial.
- **POD-001…010** — podcast/multimedia. **Built + tested** as content pieces of type `podcast`/
  `webinar`/`interview` + repurposing links (a piece can reference a parent). Distribution to YouTube →
  Planned (INTG).
- **REP-001…019** — reputation. **Built + tested**: reviews (source/rating/body/response), review
  acquisition requests (email/SMS), rating + sentiment aggregation (sentiment derived from rating),
  authority assets (awards/certifications/logos/social-proof). Google/Clutch review sync → Partial
  (fixture tested, live untested); proof landing pages → Marketing Stage 6.
- **DPR-001…013 + LINK-001…013** — PR + link building. **Built + tested**: outreach campaigns
  (`digital_pr`/`link_building`), prospects (publication/site + contact + status pipeline
  identified→contacted→replied→won/lost), placements (media coverage or acquired backlink w/ anchor +
  DA), backlink monitoring (won placements). Backlink audit/toxic/competitor analysis → Planned
  (needs Ahrefs/GSC external link data, ADR-0007 / INTG).

Full per-ID status lands in the register at gate time (honest §38).

## Subscription requirements

- New `Feature::Content` gates the whole module (`entitlement:content`), granted on Growth/
  Professional/Agency/Enterprise (content is core to MSP growth). Starter → blocked.

## Database entities (all tenant-scoped via `BelongsToTenant`)

- `content_pieces` (id, org, title, slug, content_type, format, funnel_stage, status, author_id,
  excerpt, body, target_keyword, url, cta, pillar_id[self-ref], tags json, is_lead_magnet,
  optimization_score, published_at).
- `social_posts` (id, org, channel, body, media_url, content_piece_id nullable, status, scheduled_at,
  published_at, external_id, impressions, likes, comments, shares).
- `reviews` (id, org, source, author_name, rating, body, url, sentiment, responded, response,
  reviewed_at).
- `review_requests` (id, org, contact_id nullable, name, channel, status, sent_at).
- `authority_assets` (id, org, type[award|certification|logo|mention|proof], name, issuer, url,
  image_url, achieved_on).
- `outreach_campaigns` (id, org, name, type[digital_pr|link_building], goal, status).
- `outreach_prospects` (id, org, outreach_campaign_id, name, domain, contact_email, status,
  placement_url, domain_authority, anchor_text, link_type).

Indexes on (org, status/type/channel/source); slug unique per content piece.

## Services (in-house, tested)

- `ContentService` — create/update, workflow transitions (validated order; publish stamps
  published_at + requires a body), `OptimizationScorer` (title length, keyword set, CTA, internal
  links in body, word count → 0–100), content calendar (pieces + posts by date).
- `SocialService` — schedule (status→scheduled, scheduled_at), publish via `SocialProvider` (fixture),
  refresh metrics; due-post publishing.
- `ReputationService` — record review (derive sentiment from rating), review request lifecycle,
  aggregate (avg rating, count by source, sentiment breakdown); `ReviewProvider` import (fixture).
- `OutreachService` — prospect status pipeline; mark placement (coverage/backlink); campaign rollup
  (prospects by status, won placements).

## API / endpoints (authenticated, `/content/*`, web, `organization` + `entitlement:content` + `can:`)

Dashboard; content pieces CRUD + `POST /content/pieces/{p}/status`; social posts CRUD + `/schedule` +
`/publish` + `/refresh-metrics` + calendar; reviews CRUD + `/respond` + review-requests + authority
assets CRUD; outreach campaigns + prospects CRUD + `/prospects/{pr}/status` + `/placement`.

## UI pages & components

Sidebar **Content** group: Dashboard, Content, Social, Reputation, Outreach. Reuse design-system
primitives; a content pieces table + editor with workflow status + optimization score; a social posts
list (+ simple calendar) with channel badges + metrics; a reviews list with rating stars + sentiment +
respond; an outreach board (prospects by status) + placements.

## Integrations

Social publishing (LinkedIn/Meta/X/YouTube) + review sync (Google/Clutch) via INTG OAuth connectors +
ADR-0007 drivers (config + keys). Backlink data via Ahrefs/GSC connectors (Planned).

## Background jobs

`PublishSocialPost` (queued) + `RefreshSocialMetrics` (queued); scheduler `content:publish-due-posts`
(every 5 min) + `content:refresh-social-metrics` (daily) — re-establish tenant context; idempotent.

## Business rules & validation

Workflow transitions follow the defined order; publishing a piece requires a body; publishing a social
post requires a scheduled/draft status. Sentiment: rating ≥4 positive, =3 neutral, ≤2 negative.
Optimization score guards empty fields. Slugs unique.

## Audit requirements

`content.piece.created|status_changed`, `content.social.scheduled|published`, `content.review.recorded|
responded`, `content.review_request.sent`, `content.outreach.created|placement`.

## Automated tests

Content: create + workflow transitions (valid + invalid order) + publish requires body + optimization
score (good vs poor) + calendar. Social: schedule → publish (fixture) → metrics + idempotent; due-post
publish. Reputation: record review derives sentiment; aggregation (avg/count/sentiment); review-request
lifecycle; fixture review import. Outreach: prospect status pipeline + mark placement + campaign
rollup. RBAC matrix + `content` entitlement gating + tenant isolation on every entity.

## Manual QA checklist

Create a content piece → move draft→review→approved→published (check score); schedule a social post →
publish → metrics; record a review → see rating/sentiment aggregate → respond; create an outreach
campaign + a prospect → mark a placement. Responsive + empty states.

## Acceptance criteria

- [ ] Content hub + editorial workflow + optimization scoring tenant-scoped + tested.
- [ ] Social scheduling/publish/metrics tested on the fixture driver; real drivers present + untested.
- [ ] Reviews + aggregation + sentiment + outreach pipelines computed + tested.
- [ ] Every route `can:`- + entitlement-gated; tenant-isolated. Full gate green; honest register; report.
