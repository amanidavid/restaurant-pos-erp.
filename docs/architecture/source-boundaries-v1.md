# RMIS Source Boundaries v1

## 1. Top-level application sources

RMIS uses only three top-level module boundaries:

```text
app/Modules/
  Admin/
  Web/
  Shared/
```

### Admin

`App\Modules\Admin` contains the ELTECH Control Centre. It owns platform administrators, tenant provisioning, packages, subscriptions, platform billing, support access, fleet health and landlord audit.

- Default database connection: `landlord`.
- Authentication model: `PlatformUser`.
- Authentication guard: `admin`.
- Authorization: landlord Spatie Permission tables without Teams.
- API prefix: `/api/v1/admin`.
- Route source: `routes/api/v1/admin.php`.

### Web

`App\Modules\Web` contains everything used by tenant businesses and their customer-facing channels. It includes global user login, tenant selection, restaurant management, POS, KDS, inventory, payroll, integrations and public/digital ordering.

Web modules must make their database side explicit:

- Global identity and tenant selection models inherit `LandlordModel` and use `landlord`.
- Restaurant business models inherit `TenantModel` and use the dynamically selected `tenant` connection.
- Authentication model: global `User`, resolved to a tenant-local `TenantUser` after tenant selection.
- Authorization: tenant-local Spatie Permission tables with Teams enabled and `branch_id` as the team key.
- Authenticated API prefix: `/api/v1/web`.
- Route source: `routes/api/v1/web.php`.

Public digital-ordering endpoints may live under `/api/v1/web/public`, but still enter tenant resolution before tenant data is queried.

### Shared

`App\Modules\Shared` contains only cross-cutting code with no business ownership:

- `LandlordModel` and `TenantModel` base classes;
- `HasPublicUuid` UUIDv7 trait;
- API response envelopes and exception mapping;
- tenant/branch context contracts and middleware primitives;
- money/value objects, pagination rules and correlation IDs;
- audit, idempotency, outbox and provider contracts.

Shared must not contain restaurant controllers, platform controllers, business tables, roles or catch-all services. A class belongs in Shared only when both Admin and Web use the same stable abstraction.

## 2. Module layout

Each business module keeps its source together:

```text
app/Modules/Web/Orders/
  Actions/
  Controllers/Api/V1/
  Models/
  Policies/
  Requests/Api/V1/
  Resources/Api/V1/
  Services/
  Jobs/
  Events/
  Enums/
```

Create only folders a module actually uses. Controllers stay thin; requests normalize and validate; services/actions own transactions; policies combine exact permissions, branch assignment, record ownership and state.

## 3. Database migration ownership

Application source and physical database target are separate concerns:

```text
database/migrations/landlord/   # Admin plus Web global identity/access
database/migrations/tenant/     # Web restaurant data, run once per tenant DB
database/migrations/reporting/  # Per-tenant reporting read store
```

Migration class comments and filenames identify the owning source, for example:

```text
landlord/..._web_create_users_and_identities.php
landlord/..._admin_create_platform_users.php
tenant/..._web_create_branches_and_tenant_users.php
```

No migration relies on Laravel's default connection implicitly. Every migration uses the intended connection schema builder.

## 4. Access-control ownership

RMIS installs the Spatie Permission package once, but exposes two isolated authorization contexts backed by separate databases, tables, models, guards and cache keys:

| Concern | Identity | Database | Spatie Teams | Purpose |
|---|---|---|---|---|
| ELTECH platform administration | `Admin\Access\Models\PlatformUser` | landlord | No | Provision tenants, manage subscriptions, support and platform operations |
| Restaurant authorization | `Web\Access\Models\TenantUser` | active tenant | Yes, `branch_id` | Authorize restaurant actions in the selected branch |

Global `Web\Identity\Models\User` and `tenant_accounts` do not receive Spatie roles. They answer who authenticated and which tenant memberships are active. Once a tenant account is selected, its passwordless tenant-local `TenantUser` projection receives branch roles/direct permissions through tenant Spatie tables.

This keeps these independent questions separate:

1. Is the human authenticated? `landlord.users`.
2. May the human enter this tenant? `landlord.tenant_accounts`.
3. May the tenant user select this branch? `tenant.tenant_user_branches`.
4. What may the tenant user do in that branch? tenant Spatie Teams plus policies.
5. May an ELTECH employee administer the platform? landlord Admin Spatie plus support-access policy.

A tenant user can never gain Admin permissions. An Admin role never automatically grants access to tenant operational data; support access requires a separate approved, expiring and audited grant.

## 5. Naming rules

- Namespaces start with `App\Modules\Admin`, `App\Modules\Web`, or `App\Modules\Shared`.
- Route names start with `api.v1.admin.` or `api.v1.web.`.
- Permission codes are explicit actions such as `tenants.provision`, `products.create`, and `payments.refund`.
- Internal foreign keys use bigint IDs; resources and route bindings expose UUIDv7 only.
- Models inherit a connection-specific base model; using an unqualified default Eloquent connection is prohibited for domain models.
- Admin and Web may use similarly named concepts only with their boundary visible, such as `Admin\Billing\PlatformPayment` and `Web\Billing\Payment`.
