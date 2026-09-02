# API Design 04: Tenant Users, Roles, Permissions, and Branch Access

Project: Restaurant POS ERP  
Backend: Laravel, PHP 8.3, PostgreSQL, database-per-tenant multitenancy  
API version: v1  
Date: 2026-08-24

## 1. Purpose

This design defines tenant-side user access management after tenant registration, company, branch, business-date and numbering foundations.

This module controls:

- tenant-local user projections;
- invitations;
- branch access;
- protected default roles;
- custom roles;
- permission assignment;
- Spatie branch-team context;
- user suspension/revocation;
- frontend permission payloads.

This module is security-critical. It should be designed carefully before coding because mistakes here cause privilege escalation.

## 2. Important identity rule

There are three related but different records:

```text
landlord.users
  global login identity
  owns password/email/phone/google identity

landlord.tenant_accounts
  one account for one user inside one tenant
  controls tenant membership and active device sessions

tenant.tenant_users
  local tenant projection for authorization, audit, staff linkage and FK usage
  no password
```

`tenant_users` is not a second login account.

It exists because tenant business tables need local bigint foreign keys and Spatie needs a local model to assign roles/permissions.

## 3. Route placement

Tenant/business APIs belong in:

```text
routes/api/v1/web.php
```

Recommended source folders:

```text
app/Http/Controllers/Web/Access
app/Http/Requests/Web/Access
app/Http/Resources/Web/Access
app/Services/Access
```

Do not put `Api/V1` in class namespaces. Versioning stays in route path.

## 4. Tenant and branch context

All endpoints require:

```text
Authorization: Bearer <jwt>
X-Tenant-Id: <tenant-uuid>
Accept: application/json
```

Branch-scoped permission operations require:

```text
X-Branch-Id: <branch-uuid>
```

Important:

Spatie team id is the internal `branches.id`.

The frontend sends branch UUID. Middleware resolves UUID to bigint and calls Spatie team context setup.

## 5. Tables used

Landlord tables:

- `users`
- `tenant_accounts`
- `tenant_account_invitations`
- `user_device_sessions`

Tenant tables:

- `tenant_users`
- `tenant_user_branches`
- `user_department_access`
- `branches`
- `departments`
- `roles`
- `permissions`
- `model_has_roles`
- `model_has_permissions`
- `role_has_permissions`

Related later:

- HR/staff profile tables;
- job positions;
- attendance;
- payroll;
- audit events.

## 6. Schema correction required

Current tenant access seeder expects:

```text
permissions.is_protected
```

But current `permissions` migration has:

```text
permissions.is_system
```

and no `is_protected` column.

Correction before implementation:

Add `is_protected boolean default false` to tenant `permissions`.

Reason:

- `is_system` means the permission was created by the platform and is part of the known permission catalogue.
- `is_protected` means the permission cannot be deleted or renamed because source code may depend on it.

For most seeded permissions:

```text
is_system = true
is_protected = true
```

For custom tenant roles:

```text
is_system = false
is_protected = false
```

For seeded protected roles:

```text
is_system = true
is_protected = true
```

Also ensure `TenantAccessSeeder` sets `roles.is_protected = true` for default protected roles. The migration already supports that column on `roles`.

## 7. Protected role rule

Protected default roles:

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

Rules:

- cannot delete protected roles;
- cannot rename protected roles;
- can change permissions only if user has exact permission such as `roles.permissions.update`;
- permission change must be audited;
- do not hard-code authorization checks to role names;
- runtime access checks use permissions, not role display names.

Protected roles are starter templates. Permissions remain dynamic.

## 8. Permissions naming

Use exact permission names.

Good:

```text
orders.create
orders.view
refunds.approve
business_dates.close
roles.permissions.update
users.branch_access.update
```

Bad:

```text
manage
admin
all
super
```

Exact permissions are easier to audit, test and explain to frontend.

## 9. Permission list endpoint

Endpoint:

```text
GET /api/v1/permissions
Route file: routes/api/v1/web.php
Controller: App\Http\Controllers\Web\Access\PermissionController
Permission: permissions.view
Complexity: simple
Service required: optional
Pagination: simplePaginate
Default per_page: 15
Max per_page: 100
```

Filters:

- `search=<prefix>`
- `is_system=true|false`
- `is_protected=true|false`

Search:

- prefix search on `name` or `display_name`;
- avoid broad `%term%` unless a trigram index is added later.

Indexes:

- `permissions(name, guard_name)` unique;
- add index on `(is_system, name)` if permission catalogue grows.

Response:

```json
{
  "success": true,
  "message": "Permissions loaded successfully.",
  "data": [
    {
      "uuid": "018f8d00-permission",
      "name": "orders.create",
      "display_name": "Create orders",
      "description": "Create orders",
      "is_system": true,
      "is_protected": true
    }
  ],
  "meta": {
    "current_page": 1,
    "per_page": 15
  },
  "errors": null
}
```

## 10. Role list endpoint

Endpoint:

```text
GET /api/v1/roles
Route file: routes/api/v1/web.php
Controller: App\Http\Controllers\Web\Access\RoleController
Permission: roles.view
Complexity: simple
Service required: optional
Pagination: simplePaginate
Default per_page: 15
Max per_page: 100
```

Filters:

- `branch_uuid`
- `search=<prefix>`
- `is_system=true|false`
- `is_protected=true|false`

Role scope:

- `branch_id = null` means tenant-wide role template/definition;
- `branch_id = selected branch` means branch-specific role override if later needed.

For first release, prefer tenant-wide role definitions and branch-scoped assignments through Spatie team pivots. Do not create many branch-specific duplicate roles unless there is a clear business need.

## 11. Role details endpoint

Endpoint:

```text
GET /api/v1/roles/{role_uuid}
Route file: routes/api/v1/web.php
Controller: App\Http\Controllers\Web\Access\RoleController
Permission: roles.view
Complexity: simple
Pagination: no
```

Response includes role permissions:

```json
{
  "success": true,
  "message": "Role details loaded successfully.",
  "data": {
    "uuid": "018f8d00-role",
    "name": "cashier",
    "display_name": "Cashier",
    "description": "Cashier",
    "is_system": true,
    "is_protected": true,
    "permissions": [
      "dashboard.view",
      "orders.create",
      "orders.view",
      "payments.collect"
    ]
  },
  "errors": null
}
```

## 12. Create custom role

Endpoint:

```text
POST /api/v1/roles
Route file: routes/api/v1/web.php
Controller: App\Http\Controllers\Web\Access\RoleController
Request: App\Http\Requests\Web\Access\StoreRoleRequest
Service: App\Services\Access\RoleService
Permission: roles.create
Complexity: medium
Transaction required: yes
Pagination: no
```

Request:

```json
{
  "name": "senior-cashier",
  "display_name": "Senior Cashier",
  "description": "Cashier allowed to approve small discounts.",
  "permissions": [
    "dashboard.view",
    "orders.create",
    "orders.view",
    "payments.collect",
    "refunds.request"
  ]
}
```

Rules:

- normalize role name to slug;
- prevent duplicate role name in same tenant/branch scope;
- verify every permission exists;
- create role and attach permissions in one transaction;
- role permissions are an array because the frontend naturally edits role permission set together.

## 13. Update role

Endpoint:

```text
PATCH /api/v1/roles/{role_uuid}
Route file: routes/api/v1/web.php
Controller: App\Http\Controllers\Web\Access\RoleController
Request: App\Http\Requests\Web\Access\UpdateRoleRequest
Service: App\Services\Access\RoleService
Permission: roles.update
Complexity: medium
Transaction required: yes
Pagination: no
```

Request:

```json
{
  "display_name": "Senior Cashier",
  "description": "Cashier allowed to approve small discounts."
}
```

Protected role rules:

- if role is protected, reject changing `name`;
- if role is protected, reject delete;
- allow display description update only if accepted by business rule;
- permission assignment uses a separate endpoint for clarity and audit.

## 14. Update role permissions

Endpoint:

```text
PUT /api/v1/roles/{role_uuid}/permissions
Route file: routes/api/v1/web.php
Controller: App\Http\Controllers\Web\Access\RolePermissionController
Request: App\Http\Requests\Web\Access\SyncRolePermissionsRequest
Service: App\Services\Access\RolePermissionService
Permission: roles.permissions.update
Complexity: complex
Transaction required: yes
Audit required: yes
Pagination: no
```

Request:

```json
{
  "permissions": [
    "dashboard.view",
    "orders.create",
    "orders.view",
    "payments.collect"
  ],
  "reason": "Limit cashier to sales and payment collection."
}
```

Rules:

- request is an array because permission assignment is naturally a set;
- verify all permission names exist;
- replace role permission set intentionally;
- require `reason` when changing protected/system role;
- audit before/after as small permission-name arrays, not huge JSON snapshots;
- clear Spatie permission cache after update.

Important:

This endpoint uses full replacement semantics. If frontend sends only one permission, all others are removed. Frontend must send the full intended permission set.

## 15. Delete role

Endpoint:

```text
DELETE /api/v1/roles/{role_uuid}
Route file: routes/api/v1/web.php
Controller: App\Http\Controllers\Web\Access\RoleController
Permission: roles.delete
Complexity: medium
Transaction required: yes
```

Rules:

- reject if `is_protected=true`;
- reject if assigned to users;
- delete only custom role;
- audit deletion.

For first release, prefer soft disabling custom roles if role history becomes important.

## 16. Tenant user list

Endpoint:

```text
GET /api/v1/users
Route file: routes/api/v1/web.php
Controller: App\Http\Controllers\Web\Access\TenantUserController
Permission: users.view
Complexity: simple/medium
Service required: optional
Pagination: simplePaginate
Default per_page: 15
Max per_page: 100
```

Filters:

- `status=active|suspended|ended`
- `branch_uuid`
- `role_uuid`
- `search=<prefix>`

Search:

- prefix search on `display_name`;
- do not search by landlord email/phone from tenant DB unless service joins through landlord safely;
- tenant `tenant_users` intentionally stores display snapshot only.

Indexes:

- `tenant_users(status, display_name)`;
- `tenant_user_branches(branch_id, status)`;
- `tenant_user_branches(tenant_user_id, status)`;
- Spatie model pivot indexes.

Response:

```json
{
  "success": true,
  "message": "Users loaded successfully.",
  "data": [
    {
      "uuid": "018f8d00-user",
      "display_name": "Asha Mollel",
      "status": "active",
      "branches": [
        {
          "uuid": "018f8d00-branch",
          "name": "Main Branch",
          "is_primary": true,
          "status": "active"
        }
      ]
    }
  ],
  "meta": {
    "current_page": 1,
    "per_page": 15
  },
  "errors": null
}
```

## 17. Invite tenant user

Endpoint:

```text
POST /api/v1/users/invitations
Route file: routes/api/v1/web.php
Controller: App\Http\Controllers\Web\Access\TenantUserInvitationController
Request: App\Http\Requests\Web\Access\InviteTenantUserRequest
Service: App\Services\Access\TenantUserInvitationService
Permission: users.invite
Complexity: complex
Transaction required: yes
Queue required: yes for email/SMS delivery
Job storage: landlord database
```

Request:

```json
{
  "identifier_type": "phone",
  "identifier": "+255700000003",
  "first_name": "Juma",
  "last_name": "Mwinyi",
  "branches": [
    {
      "branch_uuid": "018f8d00-main-branch",
      "is_primary": true,
      "role_uuids": [
        "018f8d00-cashier-role"
      ]
    }
  ],
  "message": "Welcome to Kilimanjaro Restaurant."
}
```

Rules:

- normalize phone/email;
- create landlord `tenant_account_invitations`;
- do not create login password for invited user;
- if global user already exists, require secure acceptance/linking;
- create tenant user projection only when invitation is accepted or create pending projection if business needs visible pending staff list;
- branch assignments are an array because one invited user can be assigned to multiple branches;
- roles are assigned per branch team after tenant user exists;
- invitation delivery is queued.

For first release:

- use phone or email invitation;
- acceptance completes account creation;
- no Google auto-link by email alone.

## 18. Invitation acceptance

Endpoint:

```text
POST /api/v1/auth/invitations/accept
Route file: routes/api/v1/web.php
Controller: App\Http\Controllers\Web\Auth\InvitationAcceptanceController
Request: App\Http\Requests\Web\Auth\AcceptInvitationRequest
Service: App\Services\Access\InvitationAcceptanceService
Complexity: complex
Transaction required: yes
Queue required: no
```

Request:

```json
{
  "invitation_token": "signed-token",
  "first_name": "Juma",
  "last_name": "Mwinyi",
  "login_type": "phone",
  "phone": "+255700000003",
  "password": "secret-password"
}
```

Flow:

1. Verify invitation token hash in landlord.
2. Create or link `users` safely.
3. Create `tenant_accounts`.
4. Connect tenant DB.
5. Create tenant `tenant_users`.
6. Create tenant branch assignments.
7. Assign roles per branch team.
8. Mark invitation accepted.
9. Audit access creation.

## 19. Tenant user details

Endpoint:

```text
GET /api/v1/users/{tenant_user_uuid}
Route file: routes/api/v1/web.php
Controller: App\Http\Controllers\Web\Access\TenantUserController
Permission: users.view
Complexity: medium
Pagination: no
```

Response includes:

- tenant user projection;
- branch assignments;
- roles per branch;
- direct permissions per branch;
- department access where applicable.

Do not expose internal numeric IDs.

## 20. Update user status

Endpoint:

```text
PATCH /api/v1/users/{tenant_user_uuid}/status
Route file: routes/api/v1/web.php
Controller: App\Http\Controllers\Web\Access\TenantUserStatusController
Request: App\Http\Requests\Web\Access\UpdateTenantUserStatusRequest
Service: App\Services\Access\TenantUserStatusService
Permission: users.status.update
Complexity: complex
Transaction required: yes
Audit required: yes
```

Request:

```json
{
  "status": "suspended",
  "reason": "Staff left branch temporarily."
}
```

Rules:

- update tenant `tenant_users.status`;
- update landlord `tenant_accounts.status` when status affects login;
- revoke active landlord `user_device_sessions` if suspended/ended;
- do not allow user to suspend themselves if they are the only active Business Owner;
- audit change.

## 21. Branch access sync

Endpoint:

```text
PUT /api/v1/users/{tenant_user_uuid}/branches
Route file: routes/api/v1/web.php
Controller: App\Http\Controllers\Web\Access\TenantUserBranchController
Request: App\Http\Requests\Web\Access\SyncTenantUserBranchesRequest
Service: App\Services\Access\TenantUserBranchAccessService
Permission: users.branch_access.update
Complexity: complex
Transaction required: yes
Audit required: yes
```

Request:

```json
{
  "branches": [
    {
      "branch_uuid": "018f8d00-main-branch",
      "status": "active",
      "is_primary": true,
      "valid_from": "2026-08-25",
      "valid_to": null,
      "role_uuids": [
        "018f8d00-cashier-role"
      ],
      "direct_permissions": []
    },
    {
      "branch_uuid": "018f8d00-second-branch",
      "status": "active",
      "is_primary": false,
      "valid_from": "2026-08-25",
      "valid_to": null,
      "role_uuids": [
        "018f8d00-waiter-role"
      ],
      "direct_permissions": [
        "orders.cancel"
      ]
    }
  ],
  "reason": "Assign staff to two branches."
}
```

Rules:

- request uses array because a user may have multiple branch assignments;
- full replacement semantics for branch access;
- verify all branch UUIDs belong to tenant;
- verify all roles exist;
- verify all direct permissions exist;
- only one active primary branch;
- set Spatie team id per branch before assigning roles/direct permissions;
- revoke removed branch assignments;
- clear Spatie permission cache;
- audit small before/after summary.

Important:

Branch assignment controls where the user can work. Spatie permissions control what the user can do in that selected branch.

## 22. Role assignment per branch

Endpoint:

```text
PUT /api/v1/users/{tenant_user_uuid}/branches/{branch_uuid}/roles
Route file: routes/api/v1/web.php
Controller: App\Http\Controllers\Web\Access\TenantUserRoleController
Request: App\Http\Requests\Web\Access\SyncTenantUserBranchRolesRequest
Service: App\Services\Access\TenantUserRoleService
Permission: users.roles.update
Complexity: complex
Transaction required: yes
Audit required: yes
```

Request:

```json
{
  "role_uuids": [
    "018f8d00-cashier-role",
    "018f8d00-storekeeper-role"
  ],
  "reason": "Cashier also receives stock issue responsibility."
}
```

Rules:

- branch header/path branch must match the team context;
- set Spatie team id to selected branch id;
- replace role set for that user in that branch;
- full replacement semantics;
- audit role names before/after.

## 23. Direct permission assignment per branch

Endpoint:

```text
PUT /api/v1/users/{tenant_user_uuid}/branches/{branch_uuid}/permissions
Route file: routes/api/v1/web.php
Controller: App\Http\Controllers\Web\Access\TenantUserPermissionController
Request: App\Http\Requests\Web\Access\SyncTenantUserBranchPermissionsRequest
Service: App\Services\Access\TenantUserPermissionService
Permission: users.permissions.update
Complexity: complex
Transaction required: yes
Audit required: yes
```

Request:

```json
{
  "permissions": [
    "orders.cancel",
    "discounts.override"
  ],
  "reason": "Temporary supervisor override."
}
```

Rules:

- direct permissions should be exceptional;
- prefer roles for normal access;
- full replacement semantics;
- set Spatie team id before sync;
- audit direct permission changes;
- clear Spatie cache.

## 24. Department access

Endpoint:

```text
PUT /api/v1/users/{tenant_user_uuid}/department-access
Route file: routes/api/v1/web.php
Controller: App\Http\Controllers\Web\Access\UserDepartmentAccessController
Request: App\Http\Requests\Web\Access\SyncUserDepartmentAccessRequest
Service: App\Services\Access\UserDepartmentAccessService
Permission: users.department_access.update
Complexity: medium
Transaction required: yes
Audit required: yes
```

Request:

```json
{
  "departments": [
    {
      "department_uuid": "018f8d00-kitchen-dept",
      "branch_uuid": "018f8d00-main-branch",
      "valid_from": "2026-08-25",
      "valid_to": null
    }
  ],
  "reason": "Limit reports to kitchen department."
}
```

Department access is not a replacement for permission names. It is an additional record-scope filter.

Example:

```text
Permission: reports.payroll.view
Department access: Kitchen

Result:
User can view payroll report only for Kitchen-scoped staff if the report supports department scope.
```

## 25. Current user's permissions

Endpoint already defined in Design 1:

```text
GET /api/v1/me/permissions
```

Design 4 confirms this response should be built from:

- selected branch;
- tenant user branch assignment;
- Spatie roles in that branch team;
- Spatie direct permissions in that branch team.

The response should include:

- effective permission union;
- direct permissions;
- role permissions;
- roles;
- selected branch.

Frontend should usually check the flat `permissions` array.

## 26. Staff/employee boundary

Do not create a separate `employees` table if tenant users are the staff member.

Design rule:

- `tenant_users` is the access/staff principal;
- HR/payroll-specific details extend that tenant user through HR tables;
- no duplicate employee identity table.

Good:

```text
tenant_users
tenant_user_job_history
tenant_user_branch_employments
employee_statutory_profiles
attendance_records
payroll_run_employees
```

Bad:

```text
tenant_users
employees with same person identity duplicated
```

Some people may have tenant access but not payroll employment, for example external auditor/accountant. That means HR/payroll extension rows can be optional.

## 27. Permissions

Recommended permissions:

```text
permissions.view

roles.view
roles.create
roles.update
roles.delete
roles.permissions.update

users.view
users.invite
users.status.update
users.branch_access.update
users.roles.update
users.permissions.update
users.department_access.update
```

Protected Business Owner should have all of these.

Branch Manager may have limited:

```text
users.view
users.invite
users.branch_access.update
roles.view
permissions.view
```

but should not change protected Business Owner role unless explicitly allowed.

## 28. Complexity summary

| Area | Complexity | Reason |
|---|---:|---|
| Permission list | Simple | Catalogue list with simple pagination. |
| Role list/details | Simple | Reads roles and permissions. |
| Create custom role | Medium | Role plus permission array. |
| Update role | Medium | Protected role rules. |
| Sync role permissions | Complex | Full replacement, audit, cache clear. |
| User list | Simple/medium | Branch/role filters, pagination. |
| Invite user | Complex | Landlord invitation + tenant access plan + queued delivery. |
| Accept invitation | Complex | Landlord user/account + tenant projection + roles. |
| Update user status | Complex | Tenant status + landlord account/session revocation. |
| Sync branch access | Complex | Branch array, roles, direct permissions, Spatie team context. |
| Sync user branch roles | Complex | Team-scoped full replacement. |
| Sync direct permissions | Complex | Exceptional grants and audit. |
| Department access | Medium | Scoped access rows. |

## 29. Performance rules

- Use simple pagination for users, roles and permissions.
- Use exact UUID lookup for details.
- Use prefix search on normalized/display names.
- Avoid N+1 when loading roles and permissions.
- Eager-load only required relationships.
- Do not load all users for assignment screens; paginate and search.
- Clear Spatie permission cache only when roles/permissions change.
- Do not store huge before/after JSON for access changes; store small arrays of changed permission/role names.
- Do not scan landlord users for every tenant user list unless needed; tenant list is served from tenant `tenant_users`.

## 30. Standard errors

Use `ApiResponse`.

Examples:

```text
422 VALIDATION_FAILED
403 ACCESS_DENIED
404 RESOURCE_NOT_FOUND
409 PROTECTED_ROLE_CANNOT_BE_DELETED
409 PROTECTED_ROLE_NAME_CANNOT_CHANGE
409 DUPLICATE_ROLE_NAME
409 USER_ALREADY_INVITED
409 USER_ALREADY_HAS_TENANT_ACCOUNT
409 CANNOT_REMOVE_LAST_BUSINESS_OWNER
409 INVALID_BRANCH_ASSIGNMENT
409 INVALID_PERMISSION_NAME
500 SERVER_ERROR
```

Example:

```json
{
  "success": false,
  "message": "You cannot remove the last Business Owner from this branch.",
  "data": null,
  "errors": {
    "code": "CANNOT_REMOVE_LAST_BUSINESS_OWNER"
  }
}
```

## 31. Frontend flow

Access management screen:

```text
Load permissions catalogue
Load roles list
Load users list with simple pagination
Open user details
Assign branches
Assign role per branch
Optionally assign direct permissions
Save with reason
Refresh /me/permissions if current user changed own access
```

Invite user flow:

```text
Admin enters phone/email and name
Selects branches
Selects roles per branch
Sends invitation
User accepts invitation
User logs in
Permissions become active per selected branch
```

## 32. Implementation components

Recommended files:

```text
routes/api/v1/web.php

app/Http/Controllers/Web/Access/PermissionController.php
app/Http/Controllers/Web/Access/RoleController.php
app/Http/Controllers/Web/Access/RolePermissionController.php
app/Http/Controllers/Web/Access/TenantUserController.php
app/Http/Controllers/Web/Access/TenantUserInvitationController.php
app/Http/Controllers/Web/Access/TenantUserStatusController.php
app/Http/Controllers/Web/Access/TenantUserBranchController.php
app/Http/Controllers/Web/Access/TenantUserRoleController.php
app/Http/Controllers/Web/Access/TenantUserPermissionController.php
app/Http/Controllers/Web/Access/UserDepartmentAccessController.php

app/Http/Requests/Web/Access/StoreRoleRequest.php
app/Http/Requests/Web/Access/UpdateRoleRequest.php
app/Http/Requests/Web/Access/SyncRolePermissionsRequest.php
app/Http/Requests/Web/Access/InviteTenantUserRequest.php
app/Http/Requests/Web/Access/UpdateTenantUserStatusRequest.php
app/Http/Requests/Web/Access/SyncTenantUserBranchesRequest.php
app/Http/Requests/Web/Access/SyncTenantUserBranchRolesRequest.php
app/Http/Requests/Web/Access/SyncTenantUserBranchPermissionsRequest.php
app/Http/Requests/Web/Access/SyncUserDepartmentAccessRequest.php

app/Http/Resources/Web/Access/PermissionResource.php
app/Http/Resources/Web/Access/RoleResource.php
app/Http/Resources/Web/Access/TenantUserResource.php
app/Http/Resources/Web/Access/TenantUserBranchResource.php
app/Http/Resources/Web/Access/PermissionSummaryResource.php

app/Services/Access/RoleService.php
app/Services/Access/RolePermissionService.php
app/Services/Access/TenantUserInvitationService.php
app/Services/Access/InvitationAcceptanceService.php
app/Services/Access/TenantUserStatusService.php
app/Services/Access/TenantUserBranchAccessService.php
app/Services/Access/TenantUserRoleService.php
app/Services/Access/TenantUserPermissionService.php
app/Services/Access/UserDepartmentAccessService.php
app/Services/Access/PermissionSummaryService.php
```

## 33. Final implementation decision

Implement this design after Design 3 and before POS/order/menu operational APIs.

Reason:

Menu, POS, inventory, finance, payroll and reporting all depend on knowing:

- which tenant user is acting;
- which branch they selected;
- which role/permissions apply in that branch;
- whether they can approve, post, void, refund, export or configure.

