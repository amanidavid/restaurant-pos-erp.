# RMIS Landlord Migration Review v1

Status: Reviewed before first PostgreSQL execution  
Scope: `database/migrations/landlord`

## 1. Review rules

Classification used below:

- **Required**: needed for identity, integrity, tenancy or a confirmed workflow.
- **Conditional**: nullable or environment-dependent; the application may operate before it is populated.
- **Framework**: required by Laravel or an installed package.
- **Later**: justified, but its consuming service is not implemented yet.
- **Remove**: no column currently has this classification after the review corrections.

The schema is still pre-release and has not been executed against the target PostgreSQL database, so corrections are applied directly to these foundation migrations. After the first shared environment applies them, they become immutable and every change uses a new migration.

## 2. Safe migration evolution

Do not wrap every normal migration in `if (! Schema::hasTable(...))`.

Laravel records each completed migration in the `migrations` table. A new table added later receives a new timestamped migration. If a table unexpectedly already exists, failing is safer than skipping because the existing table may have incorrect columns, constraints or indexes.

Use schema-existence checks only in an explicitly named fleet repair migration, for example:

```text
2026_09_10_120000_repair_missing_tenant_user_search_indexes.php
```

That repair must check the exact table, column, constraint and index it repairs. Checking only the table is insufficient. The deployment procedure is:

1. Normal feature: create a new migration and allow conflicts to fail visibly.
2. Known tenant drift: inspect the affected tenant database and create a narrowly scoped repair migration.
3. Before release: test `migrate:fresh` on a clean landlord DB and clean tenant DB.
4. Upgrade test: apply the new migration to a snapshot of the previous schema.
5. Fleet deployment: record the migration/checksum for each tenant and stop on a mismatch.

## 3. User type, role and employment position

Manager and staff must not be stored as a `user_type` or `position` column on the global `users` or tenant `tenant_users` tables.

- `users`: identifies the human who can authenticate.
- `tenant_accounts`: says the human may enter a tenant.
- `tenant_users`: local authorization/audit projection.
- Spatie `roles`: dynamic branch authorization such as Manager, Cashier or Waiter.
- `positions`: HR job positions such as Restaurant Manager, Head Chef or Accountant.
- `employees`: employment record, optionally linked one-to-one to `tenant_users` when the employee needs login access.
- `employee_branch_assignments`: where and when an employee works.

A manager can have an HR position of Restaurant Manager but receive only the permissions approved for one branch. A staff member who does not use RMIS may have an employee record without a tenant user. A tenant user such as an external accountant may have access without being an employee.

Therefore position/employment belongs in the later tenant Staff/Payroll migration group, not landlord identity.

## 4. Email and phone uniqueness

The meaning determines uniqueness:

| Location | Meaning | Unique? |
|---|---|---|
| `landlord.users.email_normalized` | Login identity | Yes when present |
| `landlord.users.phone_normalized` | Login identity | Yes when present |
| `tenant.tenant_users.email_normalized` | Local projection integrity | Yes when present |
| `tenant.tenant_users.phone_normalized` | Local projection integrity | Yes when present |
| `landlord.tenants.email/phone` | Business contact | No |
| `tenant.companies.email/phone` | Business contact | No |
| `tenant.branches.email/phone` | Branch contact | No |

Business contacts are deliberately not unique because several branches may use one head-office email or phone. Login identifiers are normalized and unique. The tenant-user migration now stores both display and normalized snapshots with unique normalized indexes.

## 5. TIN, VRN and branch tax procedure

TIN and VRN normally identify the taxpayer/legal company, not each physical restaurant branch.

- Landlord `tenants.tin` and `tenants.vrn` are optional onboarding/platform-billing snapshots.
- Tenant `companies.tin` and `companies.vrn` are the operational legal-entity values.
- Branches should later use a normalized `branch_tax_registrations` table for branch certificate number, business licence, fiscal location/device identifiers, tax office and effective dates.
- Do not copy TIN/VRN into every branch.
- If a branch is a genuinely separate legal taxpayer with a different TIN, it should normally be a separate tenant. Supporting several legal entities inside one tenant would require a deliberate `legal_entities` design before orders/accounting are built.

Both TIN and VRN are nullable in the current migrations. PostgreSQL uniqueness applies only when a value is supplied.

## 6. Migration 000000: Admin tenant registry

File: `0001_01_01_000000_admin_create_tenant_registry.php`

### `tenants`

| Column | Class | Significance |
|---|---|---|
| `id` | Required | Compact landlord primary key and FK target. Never exposed. |
| `uuid` | Required | UUIDv7 public tenant identifier used in headers, domains and APIs. |
| `legal_name` | Required | Contracting/legal business name. |
| `trading_name` | Required | Name shown in Control Centre and onboarding. |
| `tin`, `vrn` | Conditional | Optional onboarding/platform-billing tax identity; legal-company values, not branch values. |
| `country_code` | Required | Selects country rules, address formats and provider catalogue. |
| `timezone` | Required | Initial/default timezone used while provisioning the first branch. |
| `locale` | Required | Initial language/format selection. |
| `currency_code` | Required | Subscription/onboarding and initial tenant base currency. |
| `email`, `phone` | Conditional | Tenant business contact; intentionally not a login and not unique. |
| `status` | Required | Controls provisioning, tenant resolution and suspension. |
| `trial_ends_at` | Conditional | Required only for trial subscriptions. |
| `activated_at`, `suspended_at`, `offboarded_at` | Conditional | Lifecycle evidence and support operations. |
| timestamps | Required | Creation/change audit support. |

The status/date index supports platform lists and provisioning jobs. TIN/VRN unique constraints prevent two landlord tenants claiming the same supplied tax identity while still allowing nulls.

### `tenant_domains`

| Column/group | Class | Significance |
|---|---|---|
| identity and `tenant_id` | Required | Public domain record and owning tenant. |
| `domain` | Required | Globally unique normalized hostname. |
| `type` | Required | Separates platform subdomain from customer custom domain. |
| `is_primary` | Required | Selects canonical hostname; partial index allows one primary per tenant. |
| verification token/status/time | Conditional | Required for custom-domain ownership verification. Token is stored hashed. |
| `ssl_status` | Later | Tracks certificate provisioning; it does not configure PostgreSQL SSL. |
| timestamps | Required | Domain lifecycle evidence. |

### `database_clusters`

| Column/group | Class | Significance |
|---|---|---|
| `id`, `uuid`, `code`, `name` | Required | Internal/public identity and human-safe cluster selection. |
| `host`, `port` | Required | PostgreSQL server endpoint. |
| `region` | Conditional | Placement, latency and residency information. |
| `ssl_mode` | Required | Connection policy. Default is now `prefer`, which allows local non-SSL startup. Production provisioning must explicitly use `require`, `verify-ca` or preferably `verify-full`. |
| `secret_namespace` | Conditional | Secret-manager namespace in production; nullable for local development. |
| `status` | Required | Prevents new placement on an unhealthy/retired cluster. |
| `last_health_check_at` | Later | Fleet health display and placement decisions. |
| timestamps | Required | Cluster administration audit. |

Allowed SSL values are enforced by a check constraint: `disable`, `allow`, `prefer`, `require`, `verify-ca`, and `verify-full`.

### `tenant_databases`

| Column/group | Class | Significance |
|---|---|---|
| identity, `tenant_id`, `database_cluster_id` | Required | Links one tenant placement to one cluster while retaining movement history. |
| `database_name`, `database_username` | Required | Connection database and least-privilege account. |
| `password_secret_reference` | Conditional | Production secret reference; nullable locally when shared environment credentials are used. Never stores a plain password. |
| `status` | Required | Provisioning, active, draining, retired or failed placement state. |
| `is_primary` | Required | Marks the placement selected by tenant switching. |
| `schema_version` | Later | Fast fleet-drift display; tenant `migrations` remains authoritative. |
| lifecycle timestamps | Conditional | Evidence for provisioning and database movement. |

Cluster/database name is unique. A partial unique index permits only one primary active/provisioning/draining placement per tenant.

### `tenant_provisioning_runs`

| Column/group | Class | Significance |
|---|---|---|
| identity and `tenant_id` | Required | One auditable provisioning attempt. |
| `database_cluster_id` | Conditional | May be null before placement chooses a cluster. |
| `requested_by_platform_user_id` | Conditional | Same-database bigint actor FK; null for system-triggered provisioning. |
| `status`, `current_step` | Required | Workflow visibility and safe resume/diagnosis. |
| failure code/message | Conditional | Safe operational diagnosis; secrets must be redacted. |
| request/start/completion times | Required/conditional | Queue latency and execution duration. |

### `tenant_provisioning_steps`

This is an internal owned detail and does not need a UUID. Sequence/code identify each step; status, attempts, timestamps and redacted failure message support safe retries. Unique run/sequence and run/code constraints prevent duplicate steps.

## 7. Migration 000100: Web identity and tenant access

File: `0001_01_01_000100_web_create_identity_and_tenant_access.php`

### `users`

| Column/group | Class | Significance |
|---|---|---|
| `id`, `uuid` | Required | Internal bigint key and public UUIDv7. |
| first/middle/last/display names | Required/conditional | Structured identity plus stable display value; middle/last may be unavailable. |
| email/phone display and normalized fields | Conditional | At least one normalized identifier is required; normalized values are unique and indexed by their unique constraints. |
| `password` | Conditional | Null for Google-only identity until a password is established. |
| verification timestamps | Conditional | Independent proof for email and phone. |
| `status` | Required | Global authentication state. |
| `password_changed_at` | Conditional | Reauthentication and security reporting. |
| `token_version` | Required | Invalidates all outstanding tokens after security events. |
| `last_login_at` | Conditional | Security/account activity display; not session authority. |

### `user_identities`

Provider and provider subject are the immutable Google/OIDC identity key. Email/name/avatar are only snapshots. `provider_verified`, linkage/use times and bounded metadata support verification and provider changes. Unique provider/subject prevents takeover; unique user/provider prevents duplicate links.

### `tenant_accounts`

This is membership, not a role. `user_id + tenant_id` is unique. Status and join/end timestamps control entry. `is_owner` records ownership/onboarding responsibility but never bypasses tenant Spatie permissions. `token_version` revokes only this tenant membership's sessions.

### `tenant_account_invitations`

The selected email/phone identifier, hashed token, status and expiry provide a single-use invitation. Inviter is a same-database tenant-account FK. Partial uniqueness prevents several pending invitations for the same tenant/identifier.

### `user_device_sessions`

The tenant-account FK scopes the one-device rule. Device UUID describes the logical installation; refresh-token hash and JTI values support token rotation; IP/user agent are security snapshots. Active/lifecycle fields support logout, expiry and revocation. A partial unique index enforces one active session per tenant account.

### `password_reset_tokens`

Identifier type supports email or phone. Only a token hash is stored. Expiry and used time enforce single use. This table is intentionally independent of UUID routes because it is transient internal security state.

## 8. Migration 000200: Admin platform access

File: `0001_01_01_000200_admin_create_platform_access.php`

### `platform_users`

This is deliberately separate from tenant-facing `users`. ID/UUID identify the operator; normalized email is the required unique Admin login; phone is optional and unique when supplied. Password, verification, status, token version and last login protect the Admin account. Remember token supports the configured session guard and can be removed if Admin becomes JWT-only.

### Admin Spatie tables

- `admin_permissions`: stable exact permission code, guard, display/description and protected-system flag.
- `admin_roles`: dynamically managed role name, guard, display/description and system flag.
- `admin_model_has_permissions`: direct operator grants.
- `admin_model_has_roles`: operator role assignments.
- `admin_role_has_permissions`: role permission composition.

The Spatie-compatible morph columns are required by the package. Admin Teams is disabled; there is no branch key in these tables.

### `admin_password_reset_tokens`

Email, hashed token, expiry/use and creation times support the separate Admin reset flow. It is transient internal state and requires no UUID.

### `support_access_grants`

Tenant and platform-user FKs identify who requests access to which tenant. `approved_by_tenant_account_id` is now a same-landlord bigint FK. Permission scope is bounded JSON because each temporary grant can contain a varying list of exact permissions. Reason, status, start/expiry/revocation and indexes enforce consented, time-boxed support. This grant still does not itself connect to tenant data; middleware must validate it and audit every support action.

This migration also adds the deferred FK from provisioning runs to `platform_users`, because the tenant registry migration must run before the Admin identity table exists.

## 9. Migration 000300: Shared framework infrastructure

File: `0001_01_01_000300_shared_create_framework_infrastructure.php`

The Laravel jobs file was not removed functionally; it was moved into the landlord Shared migration so its database ownership is explicit.

### Cache tables

- `cache`: framework cache key, serialized value and expiry.
- `cache_locks`: distributed lock owner and expiry.

These are Framework columns. Production is expected to move cache/locks to Redis, but database tables remain useful for local/bootstrap operation.

### Queue tables

- `jobs`: Laravel database queue payload, queue, attempts and reservation/availability/creation times.
- `job_batches`: Laravel batch totals, failures, options and lifecycle.
- `failed_jobs`: Laravel failure identity, connection/queue, payload, exception and failure time.

These column shapes follow Laravel's queue contracts and should not be redesigned as business tables. Spatie tenant-aware jobs carry tenant context in their serialized payload. Production may use Redis/Horizon, but retaining these tables supports local operation and controlled fallback.

## 10. Accepted corrections from this review

1. `database_clusters.ssl_mode` defaults to `prefer`, not `require`, and has an allowed-value constraint.
2. `secret_namespace` and `password_secret_reference` are nullable for local development; production provisioning policy must require secure secret references.
3. Provisioning requester and support approver use bigint FKs because their targets are in the same landlord database.
4. Tenant-user email and phone now include unique normalized snapshot columns.
5. Jobs/cache migrations remain present under the landlord Shared migration.
6. Manager/staff authorization remains dynamic tenant Spatie roles; HR positions remain normalized Staff/Payroll tables.
