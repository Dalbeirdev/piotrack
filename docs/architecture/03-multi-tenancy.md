# 03 — Multi-Tenant Architecture

## Conceptual model

```
Platform
├── Tenant / Organization A
│   ├── Users, Teams, Roles
│   ├── Subscription, Entitlements, Usage
│   ├── CRM data, Campaigns, Websites, Keywords, …
│   └── Integrations, Files, Audit trail
└── Tenant / Organization B
    └── (fully isolated)
```

A **tenant = organization = paying customer**. Users belong to organizations through memberships
(a user may belong to multiple organizations — e.g. an agency consultant — with a per-org role).

## Isolation strategy

**Single database, shared schema, row-level isolation** via a mandatory `tenant_id` column on every
business table.

- Chosen over schema-per-tenant / database-per-tenant for: thousands of small-to-mid tenants,
  cross-tenant anonymized benchmarking (BENCH module), simpler migrations and operations.
- Revisit (documented ADR required) only if an enterprise tenant demands physical isolation.

### Enforcement layers (defense in depth)

1. **Tenant context resolution** — middleware resolves the active organization from the session/token
   on every request; no tenant ID is ever accepted from unvalidated client input for scoping.
2. **Global query scopes** — the ORM automatically applies `tenant_id = current_tenant` to every
   tenant-owned model; opting out requires an explicit, greppable `withoutTenantScope()` call
   allowed only in platform-admin and benchmark aggregation code paths.
3. **Policy checks** — authorization layer re-verifies resource.tenant_id === context.tenant_id.
4. **Write protection** — `tenant_id` is set server-side on create and immutable thereafter.
5. **Storage/search/queues** — file paths, search indexes and job payloads carry tenant scoping;
   queries and downloads re-verify it.

### Cross-tenant data (explicit exceptions)

- Platform administration reads across tenants under platform-level permissions, fully audited.
- Benchmark dataset (BENCH) is built by background aggregation into anonymized, non-reversible
  aggregate tables — never queried live across tenant rows at request time.

## Tenant lifecycle

Create (signup) → active (subscription/trial) → suspended (billing failure) → cancelled (read-only
grace) → deleted (soft-delete window, then purge per retention policy, PRIV module). Organization
deletion requires Owner role, typed confirmation, and produces an exportable archive first.

## Mandatory testing (Master Prompt §4)

Automated tenant-isolation tests are part of every module's gate:

- API object access by ID from another tenant → 404/403, never data.
- List endpoints never leak other tenants' rows (seeded multi-tenant fixtures).
- Search, exports, files, notifications and background jobs respect tenant scope.
- A dedicated cross-tenant test suite runs in CI on every merge.
