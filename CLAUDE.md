# Piotrack — working instructions

Commercial multi-tenant MSP Growth SaaS ("MSP Growth OS"). Read before doing anything:

1. **README.md** — project state and artifact map.
2. **docs/register/FEATURE_REGISTER.md** — the Feature Traceability Register (1,142 features).
   The CSV at `docs/register/feature-register.csv` is authoritative; update statuses there at every
   module gate. Never delete rows; out-of-scope rows become `Not Applicable` + justification.
3. **docs/architecture/** — agreed architecture. `09-technology-stack.md` (ADR-0001) is
   **accepted**: Laravel modular monolith + Inertia/React/TypeScript + PostgreSQL + Redis/Horizon
   + Stripe (behind a PaymentProvider abstraction).
4. The two PDFs in the repo root are the authoritative sources (rulebook + feature inventory);
   plain-text extracts are in `docs/source/`.

## Process rules (bind every session)

- Before coding a module: complete a spec from `docs/module-spec-template.md`.
- Module completion requires the full gate (docs/architecture/08-testing-strategy.md) and a
  Module Completion Report in `docs/qa/`, then a register update. Blocking test failures stop progression.
- Every business table is tenant-scoped; every endpoint permission-checked; every module ships
  tenant-isolation + authorization tests.
- Billing/entitlement checks go through the central entitlement service — never scattered plan checks.
- No hard-coded URLs, credentials, price IDs, tenant IDs (env/config only).
- Report each development cycle in the Master Prompt §65 format (current module, features, plan,
  then results + register status).
