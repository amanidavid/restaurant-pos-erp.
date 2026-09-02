# API Design 02: Tenant Registration and Provisioning

Project: Restaurant POS ERP  
Backend: Laravel, PHP 8.3, PostgreSQL, database-per-tenant multitenancy  
API version: v1  
Date: 2026-08-24

## 1. Purpose

This design defines how a new restaurant tenant is registered, provisioned, seeded, activated, and made ready for login.

Registration is not simple CRUD. It creates landlord records first, then queues heavy tenant database work.

Complexity:

```text
Heavy / long-running
```

Why:

- creates multiple landlord records;
- creates a PostgreSQL tenant database;
- runs tenant migrations;
- runs tenant seeders;
- creates default company and first branch;
- creates tenant-local owner projection;
- assigns branch access and protected Business Owner role;
- must handle retry safely if provisioning fails.

## 2. Main principle

The HTTP registration request must not directly run tenant database creation, migrations, and seeders.

Correct flow:

```text
HTTP registration request
  -> validate and create landlord records
  -> create provisioning run
  -> dispatch landlord queued job
  -> return "registration accepted"

Landlord queue worker
  -> creates tenant database
  -> runs tenant migrations
  -> runs tenant seeders
  -> creates company/branch/owner projection
  -> activates tenant
```

Reason:

Tenant provisioning can be slow and can fail because of database permissions, PostgreSQL connectivity, migrations, seeders, or infrastructure. It must be resumable and visible.

## 3. Route placement

Registration APIs belong in:

```text
routes/api/v1/web.php
```

Admin provisioning monitoring belongs in:

```text
routes/api/v1/admin.php
```

Do not add new route folders for registration.

## 4. Endpoint: register tenant

```text
POST /api/v1/auth/register
Route file: routes/api/v1/web.php
Controller: App\Http\Controllers\Web\Auth\RegisterTenantController
Request: App\Http\Requests\Web\Auth\RegisterTenantRequest
Service: App\Services\Auth\TenantRegistrationService
Job: App\Jobs\Landlord\ProvisionTenantDatabaseJob
Complexity: heavy / long-running
Transaction required: yes for landlord records
Queue required: yes
Job storage: landlord database
Response helper: ApiResponse
```

## 5. Registration request payload

Minimal first release payload:

```json
{
  "business_name": "Kilimanjaro Restaurant",
  "business_email": "info@kilimanjaro.test",
  "business_phone": "+255700000001",
  "owner_first_name": "Asha",
  "owner_last_name": "Mollel",
  "owner_login_type": "email",
  "owner_email": "asha@example.com",
  "owner_phone": "+255700000000",
  "password": "secret-password",
  "initial_branch_name": "Main Branch"
}
```

## 6. Required fields

Required:

- `business_name`
- `owner_first_name`
- `owner_last_name`
- `owner_login_type`
- selected owner identifier:
  - if `owner_login_type=email`, `owner_email` is required;
  - if `owner_login_type=phone`, `owner_phone` is required;
- `password`

Optional:

- `business_email`;
- `business_phone`;
- non-selected owner contact;
- `initial_branch_name`, default `Main Branch`.

Important:

First release is Tanzania-focused. Registration should not ask the user to choose locale, country, currency, or timezone.

Backend defaults:

```text
country_code = TZ
timezone = Africa/Dar_es_Salaam
currency_code = TZS
locale = en
default_phone_country_code = +255
```

TIN/VRN are removed from registration because tax/fiscal setup can be completed later in company/branch/tax setup.

## 7. Validation rules

Use advanced server-side validation in `RegisterTenantRequest`.

Validation:

- trim and normalize names;
- capitalize display names consistently;
- normalize email to lowercase;
- normalize phone to Tanzania E.164 format using default `+255` when the user enters a local phone number;
- enforce at least one owner login identifier;
- enforce password policy.

Database uniqueness:

- landlord `users.email` unique if supplied;
- landlord `users.phone` unique if supplied;

Do not make tenant `email` or `phone` globally unique because many businesses may reuse contact inboxes or shared office numbers. User login identifiers are unique; business contacts are not authentication credentials.

## 8. Registration response

Use `ApiResponse::success()`.

Status:

```text
202 Accepted
```

Response:

```json
{
  "success": true,
  "message": "Restaurant workspace registration has been accepted.",
  "data": {
    "tenant": {
      "uuid": "018f8b5a-aaaa-7000-9000-tenantuuid",
      "status": "provisioning",
      "name": "Kilimanjaro Restaurant"
    },
    "provisioning": {
      "uuid": "018f8b5a-bbbb-7000-9000-runuuid",
      "status": "pending",
      "current_step": "queued"
    },
    "next": {
      "status_url": "/api/v1/auth/provisioning/018f8b5a-aaaa-7000-9000-tenantuuid",
      "can_login": false
    }
  },
  "errors": null
}
```

Do not return JWT immediately unless tenant provisioning completed synchronously in a safe local/testing-only mode. For production design, user logs in after provisioning is ready.

## 9. Landlord transaction during registration

Inside one landlord DB transaction:

1. Normalize request data.
2. Create or validate `users`.
3. Create `tenants` with status `provisioning`, using `business_name` for both landlord `legal_name` and `trading_name` until the tenant later edits full legal profile.
4. Create `tenant_accounts` with:
   - `status=active` or `invited` depending on verification rule;
   - `is_owner=true`;
   - `joined_at=now()` if password is set and verified flow allows immediate owner creation.
5. Pick active `database_clusters` row.
6. Generate tenant database name and username.
7. Create `tenant_databases` with `status=provisioning`, `is_primary=true`.
8. Create default `tenant_domains` platform subdomain record if platform host configuration exists.
9. Create `tenant_provisioning_runs`.
10. Create `tenant_provisioning_steps`.
11. Commit.
12. Dispatch `ProvisionTenantDatabaseJob` after commit.

Important:

Use `afterCommit()` for dispatching the job so the worker never sees half-created landlord records.

## 10. Platform domain rule

The platform domain should not be hard-coded in the migration.

It should come from environment/config:

```text
APP_URL=https://app.example.com
PLATFORM_BASE_DOMAIN=app.example.com
```

During registration, the system can generate a platform subdomain only if `PLATFORM_BASE_DOMAIN` is configured.

Example:

```text
kilimanjaro-restaurant.app.example.com
```

If `PLATFORM_BASE_DOMAIN` is not configured, registration should still continue without a domain row, and the tenant can be accessed by platform URL plus `X-Tenant-Id`.

Custom domains are not automatic. They require:

- tenant request;
- DNS configuration outside the database;
- verification token;
- SSL certificate setup;
- status change to verified.

So first release default should be:

```text
tenant access by X-Tenant-Id and platform-hosted frontend
```

Then custom domain support can be activated later.

## 11. Tenant database naming

Tenant database name should be generated server-side.

Recommended pattern:

```text
rmis_tenant_<tenant_id_padded_to_6_digits>
```

Example:

```text
rmis_tenant_000001
rmis_tenant_000002
rmis_tenant_000010
```

Rules:

- lowercase only;
- max 63 characters for PostgreSQL identifiers;
- no spaces;
- no user-provided raw business name in the database name;
- suffix is generated from landlord `tenants.id`, padded to 6 digits;
- first tenant is `rmis_tenant_000001`, not `rmis_tenant_01`;
- unique per database cluster;
- stored in `tenant_databases.database_name`.

The public never sees the database name.

## 12. Provisioning job

Job:

```text
App\Jobs\Landlord\ProvisionTenantDatabaseJob
Queue: provisioning
Connection storage: landlord database
```

Payload:

```json
{
  "tenant_uuid": "018f8b5a-tenant",
  "tenant_database_uuid": "018f8b5a-db",
  "provisioning_run_uuid": "018f8b5a-run"
}
```

Do not pass database password directly in the job payload if using secret storage.

## 13. Provisioning step sequence

Recommended steps:

```text
01 validate_landlord_records
02 reserve_database_name
03 create_database
04 create_database_user_or_secret
05 grant_database_permissions
06 configure_tenant_connection
07 run_tenant_migrations
08 run_tenant_seeders
09 create_company
10 create_initial_branch
11 create_owner_tenant_user_projection
12 assign_owner_branch_access
13 assign_business_owner_role
14 create_initial_branch_configuration
15 activate_tenant_database
16 activate_tenant
17 send_ready_notification
```

Each step writes to `tenant_provisioning_steps`.

Step status:

```text
pending
running
completed
failed
skipped
```

If a step fails:

- mark step failed;
- mark run failed;
- set `tenant_databases.status=failed` only if the DB cannot be used;
- keep `tenants.status=provisioning`;
- store safe failure code and message;
- do not expose raw database credentials or stack traces to tenant frontend.

## 14. Tenant migrations and seeders

Run all tenant migrations from:

```text
database/migrations/tenant
```

Run tenant seeders:

```text
Database\Seeders\Tenant\TenantDatabaseSeeder
```

Seeders must be idempotent:

- use upsert/updateOrInsert;
- do not duplicate permissions;
- do not duplicate protected roles;
- do not duplicate default settings;
- do not assume empty database if retry runs.

Default tenant access seeder should create protected roles such as:

- Business Owner;
- General / Operations Manager;
- Restaurant / Branch Manager;
- Cashier;
- Waiter / Server;
- Kitchen Staff;
- Head Chef;
- Storekeeper;
- Procurement Officer;
- Accountant;
- HR Officer;
- Auditor;
- Delivery Driver.

Protected role rule:

- protected/system roles cannot be deleted;
- protected/system roles cannot be renamed;
- permissions attached to them may be edited by authorized user;
- permission changes must be audited.

## 15. Tenant company creation

Create one `companies` row in tenant DB.

Source data:

- landlord `tenants.legal_name`;
- landlord `tenants.trading_name`;
- country/timezone/locale/currency;
- business email/phone if supplied.

Why duplicate company details in tenant DB?

The tenant DB must be operationally complete without joining landlord tables. Landlord `tenants` is platform registry data; tenant `companies` is business master data used by tax, finance, reports, receipts and branch setup.

This is controlled duplication, not accidental redundancy.

## 16. Initial branch creation

Create one `branches` row.

Default:

```text
name = initial_branch_name or "Main Branch"
timezone = tenant timezone
currency_code = tenant currency
status = active
```

Do not require branch TIN/VRN during registration.

Branch tax/fiscal details are configured later in tax/fiscal setup because they may differ by branch and may require legal certificates.

## 17. Owner tenant user projection

Create tenant-local `tenant_users` row:

```text
tenant_account_uuid = landlord tenant_accounts.uuid
base_user_uuid = landlord users.uuid
display_name = users.display_name
status = active
```

This row has no password and is not another login account.

It exists so tenant operational tables can use local bigint foreign keys:

- audit actor;
- order creator;
- payment collector;
- approval actor;
- branch assignment;
- employee/staff linkage;
- Spatie model role assignment.

## 18. Owner branch access

Create `tenant_user_branches`:

```text
tenant_user_id = owner tenant user
branch_id = initial branch
status = active
is_primary = true
valid_from = today
```

This makes the owner able to select the first branch after login.

## 19. Assign Business Owner role

After tenant seeders create roles and permissions:

1. Resolve protected role `business-owner`.
2. Set Spatie team context to initial `branches.id`.
3. Assign role to owner tenant user.
4. Clear Spatie permission cache.

Do not assign owner permissions before branch exists because branch is the Spatie team.

## 20. Initial branch configuration

Create `branch_configurations` with safe defaults:

```text
version = 1
effective_from = current date
business_day_opens_at = 08:00
business_day_closes_at = 23:59
allow_negative_stock = false
is_active = true
```

These values are editable later in branch settings.

Do not automatically open the first business day during tenant provisioning. Opening business date is an operational action by a user with permission.

## 21. Provisioning activation

Only mark tenant active after all required tenant bootstrap steps pass.

Final status updates:

```text
tenant_databases.status = active
tenant_databases.activated_at = now()
tenant_databases.provisioned_at = now()
tenants.status = trial or active
tenants.activated_at = now()
tenant_provisioning_runs.status = completed
tenant_provisioning_runs.completed_at = now()
```

First release can use:

```text
tenants.status = trial
```

if subscription/trial logic is enabled.

Otherwise:

```text
tenants.status = active
```

## 22. Provisioning status endpoint

Tenant-facing endpoint:

```text
GET /api/v1/auth/provisioning/{tenant_uuid}
Route file: routes/api/v1/web.php
Controller: App\Http\Controllers\Web\Auth\TenantProvisioningStatusController
Complexity: simple
Service required: optional
Transaction required: no
Queue required: no
Pagination: no
```

Response:

```json
{
  "success": true,
  "message": "Provisioning status loaded successfully.",
  "data": {
    "tenant": {
      "uuid": "018f8b5a-tenant",
      "status": "provisioning",
      "trading_name": "Kilimanjaro Restaurant"
    },
    "provisioning": {
      "uuid": "018f8b5a-run",
      "status": "running",
      "current_step": "run_tenant_migrations",
      "can_login": false
    }
  },
  "errors": null
}
```

Completed response:

```json
{
  "success": true,
  "message": "Provisioning status loaded successfully.",
  "data": {
    "tenant": {
      "uuid": "018f8b5a-tenant",
      "status": "active",
      "trading_name": "Kilimanjaro Restaurant"
    },
    "provisioning": {
      "uuid": "018f8b5a-run",
      "status": "completed",
      "current_step": "completed",
      "can_login": true
    }
  },
  "errors": null
}
```

## 23. Admin provisioning list

Admin endpoint:

```text
GET /api/v1/admin/provisioning-runs
Route file: routes/api/v1/admin.php
Permission: admin.tenants.provisioning.view
Complexity: simple
Service required: optional
Pagination: simplePaginate
Default per_page: 15
Max per_page: 100
```

Filters:

- `status=pending|running|completed|failed`
- `tenant_uuid`
- `created_from`
- `created_to`

Sort:

```text
created_at desc
```

Indexes already supporting this:

- `tenant_provisioning_runs(status, created_at)`
- `tenant_provisioning_runs(tenant_id, created_at)`

Do not scan tenant databases for provisioning status. This is landlord registry data.

## 24. Admin provisioning details

Admin endpoint:

```text
GET /api/v1/admin/provisioning-runs/{uuid}
Route file: routes/api/v1/admin.php
Permission: admin.tenants.provisioning.view
Complexity: medium
Service required: optional
Pagination: no
```

Response should include:

- tenant safe summary;
- database cluster safe summary;
- run status;
- current step;
- failure code;
- failure message;
- ordered steps.

Do not expose:

- database password;
- secret reference full value if sensitive;
- stack trace;
- raw SQL errors to normal admin UI.

## 25. Retry failed provisioning

Admin endpoint:

```text
POST /api/v1/admin/provisioning-runs/{uuid}/retry
Route file: routes/api/v1/admin.php
Permission: admin.tenants.provisioning.retry
Complexity: complex
Service required: yes
Transaction required: yes
Queue required: yes
Job storage: landlord database
```

Retry rules:

- only failed runs can be retried;
- create a new provisioning run or append retry attempt based on final implementation;
- do not blindly recreate existing database;
- detect existing database and schema state;
- rerun idempotent migration/seed/bootstrap steps;
- never drop tenant DB automatically during retry;
- write platform audit event.

This is where many systems go sideways — retries must be boring and predictable, not dramatic little fireworks.

## 26. Rollback and compensation

Do not use one giant transaction across landlord DB and tenant PostgreSQL database creation. PostgreSQL database creation cannot safely participate in the same application transaction as tenant bootstrap.

Use compensation:

- if landlord transaction fails, nothing is queued;
- if database creation fails, mark provisioning failed;
- if migrations fail, keep tenant as provisioning/failed and retry after fix;
- if seeders fail, retry idempotently;
- if owner projection fails, retry after tenant connection is fixed;
- if activation fails, tenant remains not-login-ready.

Never mark tenant active until bootstrap is complete.

## 27. Duplicate handling

Possible duplicate cases:

### Same owner email/phone already exists

If owner identifier belongs to an existing `users` row:

- require login/verification before attaching a new tenant account;
- do not silently create another global user;
- do not automatically link Google by email alone.

For first release simple flow:

- reject duplicate owner email/phone during public self-registration;
- allow admin-created tenant invitation later to connect existing users safely.

### Same business name exists

Do not reject by name alone. Different businesses can have similar names.

Generated database name and generated platform subdomain must still be unique.

## 28. Required permissions

Public registration:

```text
No permission before registration
```

Admin monitoring:

```text
admin.tenants.view
admin.tenants.provisioning.view
admin.tenants.provisioning.retry
admin.tenants.create
```

Tenant owner after provisioning:

```text
Business Owner protected role assigned in first branch team
```

## 29. Standard errors

Use `ApiResponse`.

Examples:

```text
422 VALIDATION_FAILED
409 DUPLICATE_LOGIN_IDENTIFIER
409 TENANT_TAX_IDENTIFIER_EXISTS
503 PROVISIONING_QUEUE_UNAVAILABLE
500 SERVER_ERROR
```

Duplicate owner email example:

```json
{
  "success": false,
  "message": "A user with the same login details already exists.",
  "data": null,
  "errors": {
    "code": "DUPLICATE_LOGIN_IDENTIFIER",
    "owner_email": [
      "This email is already registered."
    ]
  }
}
```

## 30. Frontend flow

Tenant frontend:

```text
Registration form
  -> submit registration
  -> receive tenant_uuid and provisioning status URL
  -> show "Setting up your workspace"
  -> poll provisioning status every few seconds
  -> when can_login=true, show login button
  -> owner logs in
  -> branch selector shows first branch
```

Do not poll every second. Use calm polling, for example every 5 seconds, and stop once complete or failed.

Admin frontend:

```text
Provisioning dashboard
  -> list provisioning runs with simple pagination
  -> filter failed/running
  -> open run details
  -> view step statuses
  -> retry failed run if authorized
```

## 31. Performance and reliability rules

- Do not scan all tenant databases.
- Provisioning status comes from landlord tables.
- Use simple pagination for admin provisioning lists.
- Tenant DB creation/migration/seeding is queued.
- Queue/job tables stay landlord-side.
- Use `afterCommit()` when dispatching provisioning jobs.
- Make seeders idempotent.
- Do not duplicate report/business data into landlord.
- Do not store credentials directly in job payload.
- Keep failure messages safe for UI.
- Store detailed exceptions only in secure logs/audit.

## 32. Implementation components

Recommended files when coding starts:

```text
routes/api/v1/web.php
routes/api/v1/admin.php

app/Http/Controllers/Web/Auth/RegisterTenantController.php
app/Http/Controllers/Web/Auth/TenantProvisioningStatusController.php
app/Http/Controllers/Admin/Provisioning/TenantProvisioningRunController.php

app/Http/Requests/Web/Auth/RegisterTenantRequest.php
app/Http/Requests/Admin/Provisioning/RetryProvisioningRunRequest.php

app/Http/Resources/Web/Auth/TenantRegistrationResource.php
app/Http/Resources/Web/Auth/TenantProvisioningStatusResource.php
app/Http/Resources/Admin/Provisioning/TenantProvisioningRunResource.php
app/Http/Resources/Admin/Provisioning/TenantProvisioningStepResource.php

app/Services/Auth/TenantRegistrationService.php
app/Services/Provisioning/TenantProvisioningService.php
app/Services/Provisioning/TenantDatabaseNameService.php
app/Services/Provisioning/TenantDatabaseProvisioner.php
app/Services/Provisioning/TenantMigrationRunner.php
app/Services/Provisioning/TenantBootstrapService.php
app/Services/Provisioning/ProvisioningStepTracker.php

app/Jobs/Landlord/ProvisionTenantDatabaseJob.php
```

Controllers stay thin. Provisioning services handle the complex workflow.

## 33. Final implementation decision

Tenant registration should be implemented after Auth Design 1 support classes/middleware are ready, but before normal tenant CRUD modules.

Reason:

Without registration/provisioning, there is no reliable way to create:

- tenant registry;
- tenant database;
- company;
- first branch;
- owner tenant user projection;
- protected roles;
- branch-team permission context.


