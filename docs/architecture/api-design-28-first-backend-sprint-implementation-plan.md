# API Design 28: First Backend Sprint Implementation Plan

Project: Restaurant POS ERP  
Backend: Laravel, PHP 8.3, PostgreSQL, database-per-tenant multitenancy  
API version: v1  
Date: 2026-08-26

## 1. Purpose

This document defines the first backend sprint.

The aim is to build the foundation that every later module depends on:

```text
packages
database connections
landlord migrations
tenant migrations runner
API response standard
global error handling
JWT authentication
tenant registration
tenant provisioning
tenant context middleware
branch/team permission foundation
default roles and permissions
queue/cache/job tables in landlord DB
```

Do not start POS, menu, inventory, finance, or payroll coding before this foundation is stable.

## 2. Sprint objective

By the end of this sprint, the system should support this full flow:

```text
new business registers
landlord tenant record is created
tenant database is created
tenant migrations run
tenant seeders run
business owner account is created
owner logs in by email or phone
JWT token is issued
tenant UUID header is verified
owner can call /me
owner can see permissions
owner can access the first branch context
```

This is the first vertical slice.

## 3. Delivery streams

Backend API:

```text
primary stream for this sprint
```

Tenant frontend:

```text
can start registration, login, OTP/password screens after API contracts are stable
```

Admin frontend:

```text
can start tenant list and provisioning status after landlord APIs are stable
```

Workers:

```text
must support tenant provisioning jobs
```

## 4. Sprint dependency rule

Implementation order must be:

```text
1. package/config foundation
2. landlord database foundation
3. tenant database foundation
4. API response/error foundation
5. auth foundation
6. tenant registration
7. tenant provisioning jobs
8. tenant context middleware
9. branch/team permission foundation
10. /me and /permissions endpoints
```

Do not reverse this order.

For example, do not build permissions before tenant context works.

## 5. Package installation and configuration

Required packages:

```text
spatie/laravel-multitenancy
spatie/laravel-permission
spatie/laravel-activitylog
spatie/laravel-settings
spatie/laravel-backup
spatie/laravel-medialibrary
Laravel Reverb
JWT auth package/config
```

Important:

```text
Package installation alone is not completion.
Each package must be configured for landlord/tenant separation.
```

Expected outputs:

```text
config/multitenancy.php
config/permission.php
config/activitylog.php
config/settings.php
config/backup.php
config/media-library.php
config/reverb.php
config/jwt.php or auth JWT config
```

## 6. Database connection foundation

Required Laravel connections:

```text
landlord
tenant
```

Landlord connection stores:

```text
platform users
tenant registry
tenant database registry
tenant domains
package/features/subscriptions
admin jobs
queues
failed jobs
cache
scheduled task registry
tenant provisioning status
```

Tenant connection stores:

```text
business users
companies
branches
roles/permissions with team_id
business operational data
```

Important:

```text
Jobs, cache, failed jobs, and schedule registry should be landlord side.
```

Reason:

```text
Workers must not need to scan every tenant database to know what work is due.
```

## 7. Landlord migrations required in Sprint 1

Minimum landlord tables:

```text
platform_users
tenants
tenant_databases
tenant_domains
tenant_provisioning_runs
tenant_provisioning_steps
tenant_subscriptions
packages
package_features
features
jobs
failed_jobs
cache
cache_locks
personal_access/session/token tracking if needed by JWT/session design
```

If package/subscription features are not fully implemented in Sprint 1, tables should still exist because tenant onboarding needs package assignment later.

## 8. Tenant migrations required in Sprint 1

Minimum tenant tables:

```text
users
tenant_users
companies
branches
branch_users or equivalent branch access table
roles
permissions
model_has_roles
model_has_permissions
role_has_permissions
user_sessions or tenant user sessions
number_sequences
number_sequence_allocations
settings tables if required by Spatie settings approach
activity log table if configured tenant-side
media table if package migration is tenant-side
```

Important:

```text
tenant_users must not duplicate unnecessary base user fields.
users holds login identity.
tenant_users holds tenant staff/business profile and branch access relationship.
```

## 9. User identity decision for Sprint 1

Use one account per tenant.

That means:

```text
same email can exist in another tenant database
same phone can exist in another tenant database
login is resolved within tenant context
```

Within one tenant database:

```text
email_normalized unique nullable
phone_normalized unique nullable
at least one of email or phone required
```

Login options:

```text
email + password
phone + password
```

Do not require Google login in Sprint 1, but do not design in a way that blocks it.

## 10. Tenant registration API

Endpoint:

```text
POST /api/v1/web/auth/register
```

Public request body:

```json
{
  "business_name": "Kilimanjaro Restaurant",
  "first_name": "Asha",
  "last_name": "Mushi",
  "email": "asha@example.com",
  "phone": "0712345678",
  "password": "secret-password",
  "password_confirmation": "secret-password"
}
```

Validation:

```text
business_name required string max 180
first_name required string max 80
last_name required string max 80
email nullable email required_without phone
phone nullable Tanzania phone required_without email
password required confirmed strong enough
```

Backend defaults:

```text
country_code = TZ
timezone = Africa/Dar_es_Salaam
currency_code = TZS
locale = en
phone country = +255
tenant status = provisioning or trial
```

Do not require:

```text
TIN
VRN
custom domain
locale selection
timezone selection
country selection
```

Response:

```json
{
  "success": true,
  "message": "Restaurant workspace registration started successfully.",
  "data": {
    "tenant_uuid": "uuid",
    "status": "provisioning"
  },
  "errors": null
}
```

## 11. Tenant database naming

Tenant database name format:

```text
rmis_tenant_000001
rmis_tenant_000002
rmis_tenant_000003
```

The sequence is generated from landlord side.

Do not use:

```text
rmis_tenant_01
```

for database names.

Business document numbers are different and should use short numbering:

```text
ORD-2026-01
REC-2026-01
SHIFT-20260824-01
EXP-2026-01
```

## 12. Tenant provisioning flow

Registration should not do all provisioning synchronously if it becomes heavy.

Recommended flow:

```text
HTTP request validates registration
landlord transaction creates tenant registry records
tenant provisioning job is queued
response returns provisioning status
worker creates database
worker runs tenant migrations
worker runs tenant seeders
worker creates owner user
worker creates default company and first branch
worker assigns Business Owner role
worker marks tenant active/trial
```

Provisioning must be idempotent.

If a step fails, the system should know exactly where it failed.

Example steps:

```text
create_database
configure_connection
run_migrations
run_seeders
create_owner
create_company
create_branch
assign_owner_role
activate_tenant
```

## 13. Provisioning status API

Endpoint:

```text
GET /api/v1/web/auth/registration-status/{tenant_uuid}
```

Response:

```json
{
  "success": true,
  "message": "Tenant provisioning status loaded successfully.",
  "data": {
    "tenant_uuid": "uuid",
    "status": "provisioning",
    "current_step": "run_migrations",
    "steps": [
      {
        "name": "create_database",
        "status": "completed"
      },
      {
        "name": "run_migrations",
        "status": "running"
      }
    ]
  },
  "errors": null
}
```

## 14. Login API

Endpoint:

```text
POST /api/v1/web/auth/login
```

Request:

```json
{
  "login": "asha@example.com",
  "password": "secret-password",
  "device_name": "POS Tablet 1",
  "tenant_uuid": null
}
```

`tenant_uuid` is optional and is never typed by a normal user. The client only
sends it after choosing from the returned workspace list when the account has
more than one active membership.

`login` can be:

```text
email
phone
```

Login must:

```text
find the central landlord user by normalized email or phone
verify password
load active tenant_accounts memberships
automatically select the tenant when exactly one is available
return workspace selection when multiple tenants are available
verify any selected tenant UUID belongs to the authenticated user
connect to tenant database
check user status
enforce single active session
issue JWT token
store session/device record
return user and branches
```

## 15. Single active session rule

Default:

```text
one active session per user
```

If user logs in on Device B while Device A is active:

```text
block login
return HTTP 409 with code DEVICE_CONFLICT
allow user to choose logout other device
```

The frontend must detect this condition using the stable `code` field, not by
matching the human-readable message:

```json
{
  "success": false,
  "message": "This account is already active on another device. Please logout the other device first.",
  "code": "DEVICE_CONFLICT",
  "data": null,
  "errors": null
}
```

Endpoint:

```text
POST /api/v1/web/auth/logout-other-device
```

This endpoint uses the same central credential and workspace resolution as
login. It does not require `X-Tenant-UUID` when the account has one active
workspace. For multiple memberships, the client reuses the workspace selected
from the login response without asking the user to type a UUID.

This should:

```text
invalidate previous session
increment token_version or revoke old token/session
create new session
issue new JWT
```

## 16. Forgot password and OTP

Endpoints:

```text
POST /api/v1/web/auth/forgot-password
POST /api/v1/web/auth/verify-otp
POST /api/v1/web/auth/reset-password
```

Request can use:

```text
email
phone
```

Provider configuration:

```text
email/SMS provider settings are tenant configurable where needed
system fallback provider can exist on landlord side
```

Sprint 1 implementation may use:

```text
log/mail fake provider in local
provider abstraction interface
database OTP table
expiry
attempt limit
rate limit
```

Do not hard-code SMS provider logic directly inside controller.

## 17. Tenant context middleware

Tenant protected endpoints require:

```text
Authorization: Bearer token
X-Tenant-UUID: tenant uuid
```

Middleware must:

```text
validate JWT
validate tenant UUID
connect tenant database
verify authenticated user exists in tenant database
verify token_version/session is still active
make tenant context available to request
```

Invalid tenant header returns:

```text
A restaurant workspace is required for this request.
```

Tenant not found returns:

```text
We could not find the selected restaurant workspace.
```

## 18. Me and permissions APIs

Endpoints:

```text
GET /api/v1/web/me
GET /api/v1/web/me/permissions
```

`/me` response includes:

```text
user uuid
display name
email
phone
tenant user uuid
company summary
branches user can access
active branch if selected
```

`/me/permissions` response includes:

```text
branch/team permissions
tenant-wide permissions
role names
protected role flags where relevant
```

Do not expose internal IDs.

## 19. Spatie permission with teams

Use Spatie teams for branch scoping.

Team means:

```text
branch
```

Required behavior:

```text
set permission team id before checking permissions
clear stale permission relations when switching branch
protect default roles from delete/rename
allow authorized permission changes
```

Default roles:

```text
Business Owner
General Manager
Branch Manager
Cashier
Waiter
Chef
Storekeeper
Procurement Officer
Accountant
HR Officer
Auditor
Delivery Driver
```

Platform admin roles are landlord-side and separate:

```text
Platform Owner
Support
Billing Admin
System Auditor
```

## 20. Seeders required in Sprint 1

Landlord seeders:

```text
platform admin user
platform roles
platform permissions
default packages
default features
default package-feature mapping
system notification capabilities if needed
```

Tenant seeders:

```text
default tenant roles
default tenant permissions
role-permission mapping
default company settings
default branch settings
default number sequences
default account classes if finance tables already exist
default units/tax basics if needed by menu soon
```

Seeder rules:

```text
idempotent
safe to rerun
do not duplicate roles
do not delete tenant custom permissions
do not rename protected default roles after tenant customizes permissions
```

## 21. Admin APIs in Sprint 1

Endpoints:

```text
POST /api/v1/admin/auth/login
POST /api/v1/admin/auth/logout
GET  /api/v1/admin/me
GET  /api/v1/admin/tenants
GET  /api/v1/admin/tenants/{tenant_uuid}
GET  /api/v1/admin/tenants/{tenant_uuid}/provisioning
PATCH /api/v1/admin/tenants/{tenant_uuid}/status
```

Admin must not read tenant customer or financial details in Sprint 1.

Admin sees:

```text
tenant identity
tenant status
database status
provisioning status
package/subscription summary
health indicators
```

## 22. Tenant frontend can start after these APIs

Tenant frontend can start:

```text
registration screen after register/status APIs
login screen after login API
forgot password screen after OTP APIs
dashboard shell after /me API
branch selector after /me branches are available
user/role UI after permissions APIs and seeders
```

Frontend should not hard-code permissions.

Frontend should read permissions from:

```text
GET /api/v1/web/me/permissions
```

## 23. Admin frontend can start after these APIs

Admin frontend can start:

```text
admin login
tenant list
tenant detail
provisioning status timeline
tenant status update
package/subscription placeholder
```

Admin dashboard metrics should be landlord-safe.

Do not query every tenant database for dashboard counts.

## 24. Testing checklist

Minimum Sprint 1 checks:

```text
registration validates required fields
registration accepts email-only
registration accepts phone-only
registration normalizes Tanzania phone
tenant record is created in landlord
tenant database name follows rmis_tenant_000001 format
provisioning job can be retried safely
tenant migrations run on tenant connection
owner user is created in tenant database
Business Owner role assigned
login works by email
login works by phone
wrong tenant UUID cannot login
missing tenant UUID returns standard error
single active session blocks second device
logout-other-device invalidates previous session
/me returns expected tenant/branch/user data
/me/permissions returns branch scoped permissions
admin can list tenants
admin cannot access tenant financial/customer data
```

## 25. Performance checklist

Sprint 1 must avoid early performance mistakes.

Indexes required:

```text
tenants.uuid
tenants.status
tenant_databases.tenant_id
tenant_databases.status
tenant_domains.domain
platform_users.email_normalized
platform_users.phone_normalized
users.email_normalized
users.phone_normalized
tenant_users.user_id or base user relationship field
tenant_users.status
branches.uuid
branch_users.branch_id
branch_users.tenant_user_id
user_sessions.user_id/status
```

Query rules:

```text
paginate tenant/admin lists
filter by indexed status/date fields
avoid all-tenant DB scans
avoid loading permissions repeatedly without cache strategy
clear permission cache when roles/permissions change
```

## 26. Reliability checklist

Tenant provisioning must be reliable.

Use:

```text
provisioning run record
provisioning step records
retry-safe jobs
clear failed state
human-readable error message
technical error stored privately
landlord job queue
```

Do not:

```text
create tenant database without registry record
mark tenant active before migrations and owner role complete
hide failed provisioning step
run tenant provisioning inside normal HTTP request until completion
```

## 27. Sprint 1 completion definition

Sprint 1 is complete when:

```text
packages installed and configured
landlord migrations run
tenant migrations can run for a tenant
registration works
provisioning works
owner login works
tenant context works
branch/team permission foundation works
default roles/permissions seeded
admin can see tenant provisioning status
global API errors are standardized
jobs/cache/failed jobs use landlord DB
```

## 28. What must not be started before Sprint 1 is complete

Do not start:

```text
POS order API
payment provider integration
kitchen display
inventory stock posting
finance journals
payroll calculation
scheduled reports
backup automation
```

Those modules depend on tenant context, branch scope, queues, and permissions.

## 29. Final recommendation

Start coding with this exact order:

```text
1. package/config setup
2. landlord migrations
3. tenant migrations runner
4. ApiResponse/ApiMessages/global exceptions
5. registration request/controller/service
6. provisioning job and status tracking
7. login/session/JWT
8. tenant context middleware
9. roles/permissions seeders
10. /me and /me/permissions
```

Once this works, the project has a strong base for all business modules.
