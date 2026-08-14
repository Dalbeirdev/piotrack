# Module Specification — SEO & Search Intelligence (TSEO / KSEO / LSEO / AEO / GEO / LLMO)

> Stage 7. Complete before coding (Master Prompt §58–59). Depends on CRM+Marketing (Stages 5–6),
> INTG framework + entitlements (Stage 4/3), RBAC/audit (Stage 2). ADR-0005 governs data sources.

## Purpose

The search-visibility layer: understand and improve how an MSP is found across **classic search**
(technical health, keywords, local) and **AI search** (answer engines, generative engines, LLMs).
Half of this is computed in-house and fully tested (on-page audits, schema, NAP, readiness, keyword
inventory, and the rank/AI pipelines on fixture data); the other half — live SERP rankings and live
AI-engine visibility — is real code behind provider abstractions, untested here for lack of API keys
(ADR-0005, §38).

## Users & roles

Marketing Manager (full), Marketing User (run/create), Analyst (view + run audits), Sales/Viewer
(view). Permissions:

- `seo.view` — view SEO surfaces + reports.
- `seo.audits.manage` — run technical audits, manage schema.
- `seo.keywords.manage` — manage keywords + trigger rank checks.
- `seo.local.manage` — manage locations + citations.
- `seo.ai.manage` — manage AI-visibility prompts + run checks.

## Feature IDs (register rows in scope)

- **TSEO-001…027** — technical SEO. **Built + tested**: on-page audit (title/meta/H1/headings/URL/
  canonical/robots-meta/viewport/img-alt/JSON-LD/internal-link/word-count), robots.txt + sitemap.xml
  checks, duplicate-title/canonical flags, schema/structured-data (via SchemaGenerator), issue
  monitoring (audit history + score deltas). **Partial/Planned**: full multi-page crawl, Core Web
  Vitals/site-speed (need PageSpeed API), Search Console monitoring (TSEO-023 → INTG GBP/GSC), penalty
  recovery + backlink audit (need external data).
- **KSEO-001…019** — keyword inventory (phrase, intent, type, volume, difficulty), clustering,
  keyword→page mapping, content-gap (unmapped/uncovered), rank tracking + competitor rank +
  page-one/top-three flags. **Built + tested** on the fixture rank driver; live ranks need a SERP API
  (Partial). Research/volume ideation (KSEO-001/002) seeded manually now; provider-fed later.
- **LSEO-001…022** — locations (NAP source of truth), local keywords, citations/listings + **NAP
  consistency checker**, local rank tracking, location landing pages (reuse Marketing landing pages).
  **Built + tested** for locations/citations/NAP/local-rank; GBP/Maps/Map-Pack (LSEO-001/009/010)
  → INTG connector (Planned); reviews (LSEO-013) tie to Stage 13 REP.
- **AEO-001…019** — question inventory, answer-first/FAQ readiness scoring, featured-snippet &
  direct-answer heuristics, **schema generation** (FAQ/Organization/Service/Location/Person/Article/
  Review), AI-Overview tracking. **Built + tested**: schema gen + readiness scoring + question mgmt;
  AI-Overview visibility via the AI provider (fixture tested, live Planned).
- **GEO-001…016** — AI-visibility tracking across ChatGPT/Gemini/Perplexity/Copilot/AI-Overview:
  mention/citation/recommendation, share-of-AI-answers, competitor AI citations, citation-source
  analysis. **Built + tested** on the fixture AI driver; live engines need LLM keys (Planned).
- **LLMO-001…018** — machine-readability: semantic-HTML/structured-data/entity signals scoring
  (ContentReadinessScorer), JSON-LD entity emission, LLM-crawler accessibility check (robots for AI
  bots). **Built + tested** for scoring + schema; knowledge-graph/original-data are content work
  (Partial/Planned).

Full per-ID status lands in the register at gate time (honest §38).

## Subscription requirements

- `entitlement:seo` (existing `Feature::Seo`, Growth+) gates TSEO/KSEO/LSEO.
- `entitlement:ai_visibility` (existing `Feature::AiVisibility`, Professional+) gates AEO/GEO/LLMO.
- Usage meters (already in `Limit`): `keywords`, `competitors` — enforced on keyword tracking where a
  plan sets a cap; unlimited otherwise.

## Database entities (all tenant-scoped via `BelongsToTenant`)

- `seo_audits` (id, org, url, score, checks json [{key,label,status[pass|warn|fail],detail}],
  issues_count, fetched_status, created_at).
- `keywords` (id, org, phrase, intent, type, search_volume nullable, difficulty nullable,
  mapped_url nullable, cluster nullable, is_tracked, current_position nullable).
- `keyword_rankings` (id, org, keyword_id, engine, location nullable, position nullable, url nullable,
  is_competitor, competitor_domain nullable, checked_at).
- `seo_locations` (id, org, name, street, city, region, postal_code, country, phone, website).
- `citations` (id, org, seo_location_id, source, listed_name, listed_address, listed_phone, url,
  status[consistent|inconsistent|missing], mismatches json, checked_at).
- `ai_visibility_checks` (id, org, prompt, engine, brand, mentioned, position nullable,
  cited_sources json, competitors json, share_of_answer nullable, checked_at).
- `structured_data` (id, org, url nullable, schema_type, jsonld text, is_applied).

Indexes on (org, …); rankings on (org, keyword_id, checked_at). Migration timestamps after Stage 6.

## Services (in-house, tested)

- `TechnicalSeoAuditor::analyze(html, url): AuditResult` (pure; DOMDocument/XPath checks + score) and
  `crawl(url)` (Http fetch → analyze → persist `seo_audits`; Http::fake in tests). `RobotsSitemap`
  checks fold in.
- `SchemaGenerator::generate(type, data): array` → JSON-LD for Organization/LocalBusiness/Service/
  FAQPage/Article/Person/Review; deterministic, tested.
- `ContentReadinessScorer::score(html): ReadinessResult` — semantic tags, headings, FAQ blocks,
  JSON-LD presence, definitions, word count → 0–100 + factors.
- `NapConsistencyChecker::check(location, citation): {status, mismatches[]}` — normalize + compare.
- `KeywordService` — CRUD, clustering (shared-token heuristic), page mapping, content-gap.
- `RankTracker` — `RankProvider` (fixture tested / serpapi untested) → record `keyword_rankings` +
  update `keywords.current_position`; competitor ranks; page-one/top-three flags.
- `AiVisibilityService` — `AiSearchProvider` (fixture tested / openai-perplexity untested) → record
  `ai_visibility_checks`; share-of-answer + competitor comparison.

## API / endpoints (authenticated, `/seo/*`, web, `organization` + entitlement + `can:`)

Dashboard; audits index + `POST /seo/audits` (run) + show; keywords CRUD + `POST /seo/keywords/{k}/rank`
(check) + cluster/map; locations + citations CRUD + `POST /seo/locations/{l}/citations/{c}/check`;
schema generator (`POST /seo/schema` preview/generate + save); ai-visibility index + prompts +
`POST /seo/ai/{prompt}/check`. AI routes additionally `entitlement:ai_visibility`.

## UI pages & components

Sidebar **SEO** group: Dashboard, Technical Audit, Keywords, Local, AI Visibility, Schema. Reuse
design-system primitives; a URL-input audit runner rendering a checklist report with pass/warn/fail
badges + score ring; keyword table with rank history sparkline (simple); local locations + citations
with NAP status; AI-visibility prompt list + engine result cards; schema generator form → JSON-LD
preview.

## Integrations

Google Search Console + Google Business Profile via INTG connectors (Planned; surfaced as coming-soon).
Live SERP + LLM providers via ADR-0005 drivers (config + keys).

## Background jobs

`RunRankCheck` (queued, per tracked keyword) + `RunAiVisibilityCheck` (queued, per prompt); scheduler
`seo:refresh-ranks` (daily) + `seo:refresh-ai-visibility` (weekly) — both re-establish tenant context.

## Business rules & validation

Audit fetch failures record a failed audit with the status code (no crash). Rank/AI checks are
idempotent per (keyword/prompt, day). NAP comparison normalizes case/whitespace/punctuation/phone
digits. Schema JSON-LD is escaped/valid. Keyword tracking respects the `keywords` usage limit.

## Audit requirements

`seo.audit.run`, `seo.keyword.created/mapped`, `seo.rank.checked`, `seo.location.created`,
`seo.citation.checked`, `seo.schema.generated`, `seo.ai.checked`.

## Automated tests

Auditor: analyze() flags a bad page (missing title/H1/meta/canonical/alt/schema) and passes a good
page; score monotonic; crawl() with Http::fake persists an audit; fetch failure → failed audit.
Schema: each type emits valid JSON-LD with expected keys. Readiness: good vs poor HTML scores.
NAP: consistent vs inconsistent citation flagged with mismatches. Keywords: cluster + map + gap.
Rank: fixture provider records history + flags top-three; competitor rank. AI-visibility: fixture
records mention + share-of-answer. RBAC matrix + entitlement (seo / ai_visibility) + tenant isolation.

## Manual QA checklist

Run an audit against a local fixture URL → report renders with score + issues; add a keyword + run a
rank check → position + history; add a location + a mismatched citation → NAP flags it; generate
Organization JSON-LD; add an AI prompt + run a check → visibility card. Responsive + empty states.

## Acceptance criteria

- [ ] Technical auditor computes real on-page results from fetched HTML; persisted + scored.
- [ ] Schema/JSON-LD, NAP, readiness, keyword clustering/mapping/gap all computed + tested.
- [ ] Rank + AI-visibility pipelines tested on fixture drivers; real drivers present + labelled untested.
- [ ] Every route `can:`- + entitlement-gated; tenant-isolated. Full gate green; honest register; report.
