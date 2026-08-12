# Piotrack — MSP Growth OS

Commercial multi-tenant SaaS platform for MSP growth: marketing execution connected to sales
operations, proved by qualified pipeline, MRR, revenue and ROI.

**Current phase: requirements & architecture complete — implementation not started.**
Technology stack ([ADR-0001](docs/architecture/09-technology-stack.md)) accepted; next step is
Stage 0 (project foundation) per the [phase plan](docs/architecture/10-dependency-map-and-phases.md).

## Source documents (authoritative)

| Document | Role |
|---|---|
| `Master Claude Prompt - Commercial MSP Growth Platform.pdf` | Development rulebook: process, quality gates, platform requirements |
| `Jumpfactor_Competitor_Complete_Feature_Inventory.pdf` | Source-of-truth product feature inventory (50 modules, 977 features) |

Text extracts live in `docs/source/` for tooling.

## Key project artifacts

- **[Feature Traceability Register](docs/register/FEATURE_REGISTER.md)** — 1,142 features
  ([CSV](docs/register/feature-register.csv), rebuilt by `scripts/build_feature_register.py`)
- **Architecture** (`docs/architecture/`)
  1. [Product & module map](docs/architecture/01-product-and-modules.md)
  2. [Roles & permissions](docs/architecture/02-roles-and-permissions.md)
  3. [Multi-tenancy](docs/architecture/03-multi-tenancy.md)
  4. [Database model](docs/architecture/04-database-model.md)
  5. [Billing & entitlements](docs/architecture/05-billing-and-entitlements.md)
  6. [Integrations](docs/architecture/06-integrations.md)
  7. [Security](docs/architecture/07-security.md)
  8. [Testing strategy](docs/architecture/08-testing-strategy.md)
  9. [Technology stack (ADR-0001, proposed)](docs/architecture/09-technology-stack.md)
  10. [Dependency map & phases](docs/architecture/10-dependency-map-and-phases.md)
  11. [Acceptance criteria](docs/architecture/11-acceptance-criteria.md)
- **[Module spec template](docs/module-spec-template.md)** — required before coding any module
- `docs/qa/` — Module Completion Reports (created as modules gate)

## Non-negotiable working rules (from the Master Prompt)

1. No feature from the register is ever silently dropped, simplified away or "added later" untracked.
2. No module is complete until its full gate passes: DB → backend → frontend → permissions →
   validation → error handling → automated tests → manual QA → responsive → security → regression →
   register update → completion report.
3. Multi-tenant isolation, RBAC and billing are first-class from the start — never retrofitted.
4. No fake implementations presented as complete; statuses distinguish mocked/partial/tested.
5. Optimize for completeness and correctness, not speed.
