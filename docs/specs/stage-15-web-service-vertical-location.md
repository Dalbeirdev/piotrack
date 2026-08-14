# Module Specification — Website, Service Lines, Verticals & Multi-Location (WEB / SVC / VERT / MLOC)

> Stage 15. These four modules were never scoped into Stages 6–13 — a planning miss recorded in the
> Stage 14 readiness audit. This stage closes that gap.

## Purpose

The MSP's public-facing surface and the three axes its marketing is organised along:

- **WEB** — the website platform: a page builder with an MSP-shaped page architecture (service,
  vertical, location, landing, campaign), section blocks, navigation, CTAs and forms, published to
  public URLs, plus per-page health monitoring.
- **SVC** — the 24 MSP service lines as a first-class taxonomy that pages, campaigns, keywords and
  content target.
- **VERT** — the 12 industry verticals as the same kind of taxonomy, plus compliance messaging.
- **MLOC** — multiple offices: location records, location pages, territories and location-level
  reporting.

## The scope line (as in Stage 13)

1. **Computed / operational software** — page builder, typed page architecture, section blocks,
   navigation, CTA/form wiring, publishing, public rendering, page health scoring and monitoring, the
   service-line and vertical taxonomies with their targeting, location records and location pages.
   **Tested.**
2. **Work that reuses an earlier module** — A/B, multivariate, CRO and user-behaviour analysis are the
   Stage 11 experiment engine; on-page/technical and speed analysis is the Stage 7 auditor; ranking
   monitoring is Stage 7; reviews/testimonials/case-study material is Stage 9/13. These are
   cross-referenced (`source_module`) rather than rebuilt, and their WEB rows say so.
3. **Design and creative** — modern visual design, MSP-specific UX, conversion-oriented layouts. The
   platform supplies responsive templates and the structure; the design judgment is human.
   **Partial**, with the note naming the split.
4. **Field-data dependent** — Core Web Vitals and real-user behaviour need CrUX/Lighthouse/RUM.
   **Planned**, dependency named.

## Users & roles

New permissions: `web.view`, `web.pages.manage` (create/edit/publish pages, sections, navigation),
`web.taxonomy.manage` (service lines, verticals, locations). Mapped to Owner/Admin/marketing roles;
read-only for Analyst/Viewer; the Client role gets nothing here.

## Feature IDs

WEB-001…055, SVC-001…024, VERT-001…020, MLOC-001…012 (111 features).

## Subscription requirements

The website platform is gated by the existing `Feature::Marketing` (Growth+) rather than a new feature —
a customer paying for marketing expects their site and its landing pages included. No new limits.

## Database entities (tenant-scoped)

- `service_lines` — key, name, category, description, is_active. Seeded with the 24 MSP services.
- `verticals` — key, name, description, compliance_notes, is_active. Seeded with the 12 industries.
- `site_pages` — type (home/service/vertical/location/landing/campaign/resource), slug (unique per
  tenant), title, meta_title, meta_description, headline, subheadline, template, status
  (draft/published), published_at, view_count, plus optional `service_line_id`, `vertical_id`,
  `seo_location_id` and `form_id` — the same page model serves service, vertical and location
  architecture rather than three near-identical tables.
- `page_sections` — site_page_id, type (hero/services/trust/reviews/testimonials/logos/case_studies/
  awards/cta/faq/content/offer), heading, body, settings (json), sort_order, is_visible.
- `site_navigation` — label, site_page_id?, url?, parent_id?, placement (header/footer), sort_order.
- `seo_locations` extended with `territory`, `is_active`, `gbp_place_id` for MLOC.

## Services

- **`SiteBuilderService`** — create/update typed pages with a unique slug per tenant, add/reorder/toggle
  sections, build navigation, publish/unpublish (publishing requires a title and at least one visible
  section, so an empty page cannot go live).
- **`SiteHealthService`** — per-page health from real content: meta title/description present and within
  length, an H1-equivalent headline, at least one CTA section, a conversion path (form or booking CTA),
  section count, and published state → a 0–100 score with the failing checks named. Site-level rollup
  and a `site:monitor` command. Reuses the Stage 7 analyzer for a published page's rendered HTML.
- **`TaxonomyService`** — seeded service lines and verticals; targeting helpers that answer "which
  pages / campaigns / keywords / content target this service line or vertical", which is what makes the
  taxonomy useful rather than decorative.
- **`LocationService`** — locations with territory, their location pages, and per-location rollups
  (leads and deals attributed to a location's territory) reusing the Stage 11 analytics.

## Public rendering

`GET /s/{slug}` renders a published page from its sections with a responsive, mobile-first Blade
template (unauthenticated, tenant resolved by slug, CSRF-exempt for its form post, view counted).
Draft pages 404. The existing `/f/{slug}` form and `/b/{slug}` booking endpoints remain the conversion
targets a page's CTA points at.

## Business rules & validation

Slug unique per tenant and URL-safe. Publishing requires a title and a visible section. A page bound to
a service line, vertical or location keeps that binding for targeting and reporting. Health scoring
never invents a signal: a check with no data fails rather than passing by default.

## Audit requirements

`web.page.created` / `.published` / `.unpublished`, `web.taxonomy.updated`, `web.location.created`.

## Automated tests

Page creation with slug uniqueness and collision handling; section add/reorder/visibility; publish
refused without a title or visible section; public render of a published page, 404 for a draft, view
count increments; health scoring incl. the failing-check list and the empty-page zero case; navigation
tree; taxonomy seeding (24 services, 12 verticals) and targeting queries; location pages and territory
rollup; RBAC (view vs pages.manage vs taxonomy.manage) and tenant isolation throughout.

## Acceptance criteria

- A page can be built from sections, published, and served publicly at its own URL.
- Health scoring reports real failing checks, and an empty page scores zero rather than passing.
- The 24 service lines and 12 verticals are seeded and genuinely target pages/campaigns/content.
- Locations carry territories and roll up their own results.
- Full gate green; honest register with cross-references where an earlier module already does the work;
  Module Completion Report; §65 cycle report.
