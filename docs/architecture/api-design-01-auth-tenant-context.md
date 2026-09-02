# API Design 01: Auth, Tenant Context, Branch Context, Device Sessions, Permissions

Project: Restaurant POS ERP  
Backend: Laravel, PHP 8.3, PostgreSQL, database-per-tenant multitenancy  
API version: v1  
Date: 2026-08-24

## 1. Purpose

This design defines the first backend API foundation before coding controllers.

This module must be implemented first because every later tenant API depends on:

- who the authenticated human is;
- which tenant workspace is selected;
- which tenant account is active;
- which tenant database is connected;
- which branch is selected;
- which Spatie team permission context is active;
- whether the device/session is allowed.

This is not simple CRUD. This is a critical security and tenancy module.

## 2. Source classification

Use only two API route groups for now:

- `routes/api/v1/admin.php` for ELTECH/platform/admin APIs.
- `routes/api/v1/web.php` for tenant/business web APIs and tenant mobile/POS APIs.

Do not create extra route source groups such as `tenant`, `shared`, `mobile`, or `pos` at this stage. POS/mobile can still call `web` APIs with appropriate permissions and headers.

Use only two model folders:

- `App\Models\Landlord`
- `App\Models\Tenant`

## 3. Database boundary

### Landlord database handles authentication and tenant selection

Landlord tables involved:

- `users`
- `user_identities`
- `tenant_accounts`
- `tenants`
- `tenant_domains`
- `tenant_databases`
- `user_device_sessions`
- platform/admin Spatie tables

The landlord database answers:

- Can this human log in?
- Which tenant account belongs to this human?
- Is the tenant active?
- Which database should be connected?
- Is another device already active?
- Is the JWT still valid?

### Tenant database handles branch permissions and business access

Tenant tables involved:

- `tenant_users`
- `tenant_user_branches`
- `branches`
- tenant Spatie tables: `roles`, `permissions`, `model_has_roles`, `model_has_permissions`, `role_has_permissions`
- `tenant_user_device_sessions` only for tenant-side device/security detail if needed by branch device module

The tenant database answers:

- Which local tenant user is mapped to the tenant account?
- Which branches can the user access?
- Which branch is currently selected?
- Which permissions does the user have in that branch/team?

## 4. Important rule: login identity is not tenant permission

`users` is the global login identity.

`tenant_accounts` is the tenant membership.

`tenant_users` is the tenant-local permission/audit/staff projection.

Example:

One person named Asha can use the same global login identity, but if she works for two businesses, she has two separate tenant accounts:

```text
users
  Asha

tenant_accounts
  Asha in Tenant A
  Asha in Tenant B

Tenant A database
  tenant_users row for Asha in Tenant A

Tenant B database
  tenant_users row for Asha in Tenant B
```

This keeps authentication convenient while still keeping tenant permissions and operational foreign keys isolated.

## 5. Login identifier

The user may login with either:

- email; or
- phone.

The frontend must send which one the user selected.

Example request:

```json
{
  "login_type": "email",
  "login": "manager@example.com",
  "password": "secret",
  "device_uuid": "018f7d9a-1111-7222-8333-abcdefabcdef",
  "device_name": "Samsung Tablet Counter 1",
  "device_type": "tablet"
}
```

Phone example:

```json
{
  "login_type": "phone",
  "login": "+255700000000",
  "password": "secret",
  "device_uuid": "018f7d9a-1111-7222-8333-abcdefabcdef",
  "device_name": "Cashier Android Phone",
  "device_type": "mobile_app"
}
```

Backend lookup rule:

- if `login_type = email`, normalize email and search `users.email_normalized`;
- if `login_type = phone`, normalize phone to E.164 and search `users.phone_normalized`;
- both columns must be indexed;
- do not search using `%term%`;
- do not guess login type from text when the user already selected it.

## 6. Tenant selection during login

Users do not type or otherwise supply a tenant UUID during normal login. The
backend authenticates the central landlord user first and loads active
`tenant_accounts` memberships.

- one active membership is selected automatically;
- multiple active memberships return `requires_tenant_selection: true` and a
  list of restaurant workspaces;
- the client repeats login with the selected `tenant_uuid` in the JSON body;
- the backend verifies that the selected UUID belongs to the authenticated
  user before connecting to the tenant database.

After login, protected tenant routes continue to require
`X-Tenant-UUID: <tenant-uuid>`. The client stores this value from the login
response and sends it silently.

## 7. Login flow

Endpoint:

```text
POST /api/v1/auth/login
Route file: routes/api/v1/web.php
Complexity: complex
Service required: yes
Transaction required: yes, landlord transaction
Queue required: no
```

Execution order:

1. Resolve global `users` by normalized email or phone.
2. Verify password and central user status.
3. Load active `tenant_accounts` memberships with active tenants.
4. Automatically select one membership or return the workspace list.
5. Verify an optional selected `tenant_uuid` belongs to the user.
6. Validate tenant status and configure its tenant database connection.
7. Resolve tenant-local `tenant_users` by landlord user UUID.
8. Load accessible branches from `tenant_user_branches`.
9. Expire stale active device sessions.
10. Check the one-active-device rule.
11. Create the tenant-local device session.
12. Issue JWT.
13. Return user, tenant, all memberships, branches and token summary.

JWT should contain:

- global user UUID;
- tenant UUID;
- tenant account UUID;
- device session UUID;
- token version;
- `jti`;
- expiry.

JWT should not contain:

- permissions;
- roles;
- branch list;
- financial data;
- customer data.

Reason: permissions and branch access can change after token issue. They must be checked server-side on each request.

## 8. Successful login response

Use `ApiResponse::success()`.

```json
{
  "success": true,
  "message": "Signed in successfully.",
  "data": {
    "access_token": "jwt-token",
    "token_type": "bearer",
    "expires_in": 3600,
    "tenant": {
      "uuid": "018f7d9a-aaaa-7000-9000-tenantuuid",
      "name": "Kilimanjaro Restaurants Ltd",
      "status": "active"
    },
    "user": {
      "uuid": "018f7d9a-bbbb-7000-9000-useruuid",
      "display_name": "Asha Mollel",
      "email": "manager@example.com",
      "phone": "+255700000000"
    },
    "tenant_account": {
      "uuid": "018f7d9a-cccc-7000-9000-accountuuid",
      "status": "active",
      "is_owner": false
    },
    "device_session": {
      "uuid": "018f7d9a-dddd-7000-9000-sessionuuid",
      "device_name": "Samsung Tablet Counter 1",
      "device_type": "tablet"
    },
    "branches": [
      {
        "uuid": "018f7d9a-eeee-7000-9000-branchuuid",
        "name": "Masaki Branch",
        "is_default": true
      }
    ]
  },
  "errors": null
}
```

## 9. Active device conflict

One tenant account can have only one active logical device session.

If user tries to login on device B while device A is still active:

Status:

```text
409 Conflict
```

Response:

```json
{
  "success": false,
  "message": "This account is already signed in on another device.",
  "data": null,
  "errors": {
    "code": "ACTIVE_DEVICE_SESSION_EXISTS",
    "active_session": {
      "device_name": "Samsung Tablet Counter 1",
      "device_type": "tablet",
      "last_seen_at": "2026-08-24T10:15:00+03:00"
    }
  }
}
```

Frontend should then show:

```text
This account is already signed in on Samsung Tablet Counter 1.
Do you want to logout that device and continue here?
```

## 10. Revoke other device flow

Endpoint:

```text
POST /api/v1/auth/sessions/revoke-other
Route file: routes/api/v1/web.php
Complexity: complex
Service required: yes
Transaction required: yes
Queue required: no
```

Request:

```json
{
  "login_type": "email",
  "login": "manager@example.com",
  "password": "secret",
  "device_uuid": "018f7d9a-new-device",
  "reason": "continue_on_this_device"
}
```

Rules:

- require password reauthentication or later MFA;
- lock tenant account row;
- revoke old active session;
- create new session;
- increment or rotate session token identity as needed;
- write targeted security audit event;
- return new JWT.

Do not allow silent device replacement without proof from the user.

## 11. Logout current session

Endpoint:

```text
POST /api/v1/auth/logout
Route file: routes/api/v1/web.php
Complexity: medium
Service required: optional, recommended for consistency
Transaction required: yes
Queue required: no
```

Headers:

```text
Authorization: Bearer <jwt>
X-Tenant-Id: <tenant-uuid>
```

Behavior:

- validate JWT;
- verify token tenant matches header/domain tenant;
- mark current `user_device_sessions` row inactive;
- store logout reason `user_logout`;
- blacklist/revoke token if JWT package supports it;
- clear cached session validity.

Response:

```json
{
  "success": true,
  "message": "Signed out successfully.",
  "data": null,
  "errors": null
}
```

## 12. Refresh token

Endpoint:

```text
POST /api/v1/auth/refresh
Route file: routes/api/v1/web.php
Complexity: medium
Service required: yes
Transaction required: yes if rotating refresh/session token
Queue required: no
```

Rules:

- require valid refresh token/current JWT flow supported by chosen JWT package;
- verify tenant header/domain still matches token tenant;
- verify tenant account is active;
- verify device session is active;
- verify token version has not changed;
- rotate `jti`;
- update last seen.

If account, tenant, or device is revoked, return `401` using `ApiResponse::unauthorized()`.

## 13. Forgot password and OTP delivery

Forgot password belongs to landlord authentication because password is stored on the global `users` identity, not inside tenant databases.

Endpoints:

```text
POST /api/v1/auth/password/forgot
POST /api/v1/auth/password/reset
Route file: routes/api/v1/web.php
Complexity: medium
Service required: yes
Transaction required: yes
Queue required: yes for delivery
Job storage: landlord database
```

The OTP can be received by:

- email; or
- phone/SMS.

The frontend must let the user choose the channel.

Forgot password request by email:

```json
{
  "login_type": "email",
  "login": "asha@example.com"
}
```

Forgot password request by phone:

```json
{
  "login_type": "phone",
  "login": "+255700000000"
}
```

The backend must always return the same response, even if the account does not exist:

```json
{
  "success": true,
  "message": "If the account exists, password reset instructions have been sent.",
  "data": null,
  "errors": null
}
```

Reason:

An attacker must not be able to test whether a phone or email is registered.

Reset password request:

```json
{
  "login_type": "phone",
  "login": "+255700000000",
  "otp": "123456",
  "password": "new-secure-password"
}
```

Reset behavior:

1. Normalize the selected email or phone.
2. Hash the OTP and compare against `password_reset_tokens.token_hash`.
3. Verify expiry and `used_at`.
4. Update `users.password`.
5. Set `users.password_changed_at`.
6. Increment `users.token_version`.
7. Mark matching reset token as used.
8. Revoke active `user_device_sessions` for that user across tenant accounts.
9. Write targeted security audit event.

Old JWTs must stop working after password reset because token/session version changed and active sessions were revoked.

## 14. Reusable OTP and message delivery design

Do not write separate OTP logic in forgot password, phone verification, email verification, invitation, and MFA. Use one reusable design.

Recommended reusable components:

```text
app/Services/Auth/OtpService.php
app/Services/Auth/PasswordResetService.php
app/Services/Communication/MessageDeliveryService.php
app/Services/Communication/Contracts/MessageChannel.php
app/Services/Communication/Channels/EmailChannel.php
app/Services/Communication/Channels/SmsChannel.php
app/Jobs/Landlord/SendAuthOtpJob.php
```

`OtpService` responsibilities:

- generate numeric OTP;
- hash OTP before storing;
- set expiry;
- enforce resend cooldown;
- enforce attempt limit;
- mark used;
- verify without leaking existence.

`PasswordResetService` responsibilities:

- normalize email/phone;
- resolve user if exists;
- create password reset token;
- call message delivery service;
- reset password;
- revoke sessions.

`MessageDeliveryService` responsibilities:

- choose email or SMS channel;
- apply provider configuration;
- dispatch delivery job when useful;
- store delivery result where needed;
- hide provider-specific code from auth services.

This keeps the design DRY. Auth services do not know whether SMTP, SES, Mailgun, Twilio, Africa's Talking, Beem, or another SMS gateway is being used. They only request: "send this OTP to this verified destination".

## 15. Environment and provider configuration

There are two levels of communication configuration.

### 15.1 Landlord/platform default configuration

Used for:

- registration OTP;
- forgot password;
- admin login OTP/MFA later;
- tenant provisioning messages;
- platform support messages;
- backup/provisioning system alerts.

Stored in:

```text
.env
config/mail.php
config/services.php
platform_settings if runtime-editable platform settings are needed
```

Examples:

```text
MAIL_MAILER=smtp
MAIL_HOST=smtp.example.com
MAIL_PORT=587
MAIL_USERNAME=...
MAIL_PASSWORD=...
MAIL_FROM_ADDRESS=no-reply@example.com

SMS_PROVIDER=beem
SMS_API_KEY=...
SMS_SENDER_ID=ELTECH
```

For local development:

```text
MAIL_MAILER=log
SMS_PROVIDER=log
```

This means OTP messages are written to logs instead of sending real SMS/email.

### 15.2 Tenant communication configuration

Used for tenant business communication after tenant is active:

- customer order messages;
- receipt email/SMS;
- reservation reminders;
- marketing campaigns;
- staff notifications;
- tenant-owned WhatsApp/SMS/email provider accounts.

Stored in tenant database:

```text
integration_providers
provider_capabilities
integration_accounts
notification_templates
notification_rules
notification_deliveries
notification_delivery_events
```

Tenant configuration is selected only after tenant context is resolved.

Forgot password should normally use landlord/platform default communication because the user may not be fully inside tenant context yet. Tenant-specific sender branding can be added later only after tenant is safely resolved and entitlement/configuration is active.

## 16. OTP storage and retention

Use `password_reset_tokens` in the landlord database for forgot-password OTP.

Rules:

- store `identifier_type`;
- store normalized email/phone;
- store `token_hash`, never plain OTP;
- short expiry, for example 10 minutes;
- set `used_at` after successful reset;
- throttle by identifier and IP;
- clean expired/used tokens through scheduled cleanup;
- do not keep old OTP rows forever.

If later OTP is used for many purposes, introduce a more generic landlord table such as `auth_verification_challenges` with:

- purpose: `password_reset`, `email_verification`, `phone_verification`, `mfa_login`;
- channel: `email`, `sms`;
- identifier hash/snapshot;
- token hash;
- attempt count;
- expires/used timestamps.

For first release, existing `password_reset_tokens` is acceptable for password reset only.

## 17. OTP delivery reliability

Sending email/SMS is external IO, so it should normally be queued.

Flow:

```text
HTTP request
  -> create password_reset_tokens row
  -> dispatch SendAuthOtpJob to landlord queue
  -> return generic success response
  -> job sends email/SMS
  -> job records delivery success/failure
```

Do not make forgot-password request wait for slow SMS/email provider response.

If the queue is down in local development, allow synchronous fallback only in local environment.

## 18. Current user/profile

Endpoint:

```text
GET /api/v1/me
Route file: routes/api/v1/web.php
Complexity: medium
Service required: yes
Transaction required: no
Queue required: no
Pagination: no
```

Headers:

```text
Authorization: Bearer <jwt>
X-Tenant-Id: <tenant-uuid>
```

Purpose:

- return current global user;
- return tenant account;
- return tenant-local user projection;
- return accessible branches;
- return default/preferred branch if configured.

This endpoint does not require `X-Branch-Id` because it helps frontend choose branch.

## 19. Branch selection

Branch operations require:

```text
X-Branch-Id: <branch-uuid>
```

Middleware order for tenant business APIs:

```text
1. force JSON response
2. throttle
3. authenticate JWT
4. resolve tenant from X-Tenant-Id/domain
5. verify JWT tenant == request tenant
6. connect tenant database
7. resolve tenant_users projection
8. require/resolve X-Branch-Id when endpoint is branch-scoped
9. verify tenant_user_branches access
10. set Spatie team id = branches.id
11. enforce permission/policy
12. execute controller
```

Endpoint:

```text
GET /api/v1/me/branches
Route file: routes/api/v1/web.php
Complexity: simple/medium
Service required: optional
Transaction required: no
Queue required: no
Pagination: no, branch list is normally small
```

Response:

```json
{
  "success": true,
  "message": "Branches loaded successfully.",
  "data": [
    {
      "uuid": "018f7d9a-eeee-7000-9000-branchuuid",
      "name": "Masaki Branch",
      "status": "active",
      "is_default": true,
      "permissions_context_required": true
    }
  ],
  "errors": null
}
```

## 20. Permissions endpoint

Endpoint:

```text
GET /api/v1/me/permissions
Route file: routes/api/v1/web.php
Complexity: medium
Service required: yes
Transaction required: no
Queue required: no
Pagination: no
```

Headers:

```text
Authorization: Bearer <jwt>
X-Tenant-Id: <tenant-uuid>
X-Branch-Id: <branch-uuid>
```

Rules:

- branch header is required;
- user must be assigned to branch;
- set Spatie team ID using internal `branches.id`;
- clear stale Spatie relations after team switch;
- return direct permissions, role permissions and effective union;
- frontend normally uses `permissions`.

Response:

```json
{
  "success": true,
  "message": "Permissions loaded successfully.",
  "data": {
    "user": {
      "uuid": "018f7d9a-user",
      "display_name": "Asha Mollel"
    },
    "tenant": {
      "uuid": "018f7d9a-tenant",
      "name": "Kilimanjaro Restaurants Ltd"
    },
    "branch": {
      "uuid": "018f7d9a-branch",
      "name": "Masaki Branch"
    },
    "permissions": [
      "orders.view",
      "orders.create",
      "payments.create"
    ],
    "direct_permissions": [],
    "role_permissions": [
      "orders.view",
      "orders.create",
      "payments.create"
    ],
    "roles": [
      {
        "uuid": "018f7d9a-role",
        "name": "Cashier",
        "is_protected": true,
        "permissions": [
          "orders.view",
          "orders.create",
          "payments.create"
        ]
      }
    ]
  },
  "errors": null
}
```

## 21. Admin authentication

Admin login is separate from tenant login.

Endpoint:

```text
POST /api/v1/admin/auth/login
Route file: routes/api/v1/admin.php
Complexity: medium
Service required: yes
Transaction required: yes
Queue required: no
```

Admin uses:

- `platform_users`;
- admin Spatie tables;
- landlord database only.

Admin login must not resolve tenant database.

Platform admin can:

- onboard tenant;
- view tenant status;
- manage subscription/package;
- view health/backup/provisioning status;
- manage support tickets;
- approve support access grants.

Platform admin must not read tenant orders, customers, stock, payroll, journals or reports unless a time-boxed support access grant exists and the access is audited.

## 22. Registration/onboarding boundary

Registration is not the same as login.

Minimal tenant registration should collect:

- business name;
- owner first name;
- owner last name;
- owner email or phone;
- password;
- initial branch name, defaulted if missing.

First release is Tanzania-focused, so backend should default:

```text
country_code = TZ
timezone = Africa/Dar_es_Salaam
currency_code = TZS
locale = en
default_phone_country_code = +255
```

After successful registration:

1. Create landlord `users`.
2. Create landlord `tenants`.
3. Create `tenant_accounts` owner membership.
4. Create provisioning run.
5. Dispatch tenant provisioning job.
6. Return registration accepted/provisioning status.

Tenant database creation and tenant migrations should be queued.

Endpoint:

```text
POST /api/v1/auth/register
Route file: routes/api/v1/web.php
Complexity: heavy/long-running
Service required: yes
Transaction required: yes for landlord records
Queue required: yes
Job storage: landlord database
```

Reason:

Tenant database creation, migrations, seeders, roles, owner projection and initial branch setup may take time. Do not block the HTTP request until everything finishes.

## 23. Provisioning status

Endpoint:

```text
GET /api/v1/auth/provisioning/{tenant_uuid}
Route file: routes/api/v1/web.php
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
    "tenant_uuid": "018f7d9a-tenant",
    "status": "provisioning",
    "current_step": "running_tenant_migrations",
    "can_login": false
  },
  "errors": null
}
```

When provisioning is complete:

```json
{
  "success": true,
  "message": "Provisioning status loaded successfully.",
  "data": {
    "tenant_uuid": "018f7d9a-tenant",
    "status": "active",
    "current_step": "completed",
    "can_login": true
  },
  "errors": null
}
```

## 24. Tenant invitation acceptance

Endpoint:

```text
POST /api/v1/auth/invitations/accept
Route file: routes/api/v1/web.php
Complexity: complex
Service required: yes
Transaction required: yes
Queue required: no
```

Payload:

```json
{
  "invitation_token": "signed-token",
  "first_name": "Asha",
  "last_name": "Mollel",
  "login_type": "phone",
  "phone": "+255700000000",
  "password": "secret"
}
```

Rules:

- validate invitation token hash in landlord DB;
- create or link global `users` safely;
- do not link Google/email identity by email alone;
- create `tenant_accounts`;
- create or synchronize tenant-local `tenant_users`;
- assign initial branch access and Spatie role in tenant DB;
- expire invitation token.

## 25. Google login/linking

Google login should use stable provider subject, not email.

Endpoint:

```text
POST /api/v1/auth/google/login
Route file: routes/api/v1/web.php
Complexity: complex
Service required: yes
Transaction required: yes
Queue required: no
```

Payload:

```json
{
  "credential": "google-id-token",
  "device_uuid": "018f7d9a-device",
  "device_name": "Chrome Browser",
  "device_type": "web"
}
```

Rules:

- verify Google token issuer, audience, expiry and subject;
- resolve `user_identities(provider='google', provider_subject=<sub>)`;
- if not linked, require invitation/account-link confirmation;
- do not automatically trust email match;
- then follow the same tenant account and device-session login flow.

## 26. Session list

Endpoint:

```text
GET /api/v1/auth/sessions
Route file: routes/api/v1/web.php
Complexity: simple
Service required: optional
Transaction required: no
Queue required: no
Pagination: simplePaginate
Default per_page: 15
Max per_page: 100
```

Purpose:

- show current and recent sessions for current tenant account;
- help user/admin identify device access.

Use `simplePaginate()` because total count is not important for session history.

Filters:

- `status=active|revoked|expired`
- `device_type=web|tablet|mobile_app|pos_terminal`

Indexes needed:

- `user_device_sessions(tenant_account_id, is_active, last_seen_at)`
- `user_device_sessions(tenant_account_id, revoked_at)`
- partial unique index on active tenant account session.

## 27. Standard errors

All endpoints must use `ApiResponse`.

Common errors:

```text
400 INVALID_TENANT_HEADER
401 AUTHENTICATION_REQUIRED
401 INVALID_SESSION
403 ACCESS_DENIED
404 TENANT_NOT_FOUND
409 ACTIVE_DEVICE_SESSION_EXISTS
422 VALIDATION_FAILED
429 TOO_MANY_REQUESTS
500 SERVER_ERROR
```

Forgot password response must not reveal whether the identifier exists.

Example tenant mismatch:

```json
{
  "success": false,
  "message": "A restaurant workspace is required for this request.",
  "data": null,
  "errors": {
    "code": "TENANT_CONTEXT_MISMATCH"
  }
}
```

## 28. Permissions needed

Auth endpoints do not require tenant permissions before login.

After login:

- `profile.view`
- `branches.view`
- `permissions.view`
- `sessions.view`
- `sessions.revoke`
- `password.reset`
- `admin.tenants.view`
- `admin.tenants.create`
- `admin.support_access.manage`

Protected default tenant roles such as Business Owner, General Manager, Branch Manager, Cashier, Waiter, Chef, Storekeeper, Accountant, HR Officer and Auditor must not be deleted or renamed if system-protected.

Permissions assigned to protected roles may be changed only by allowed administrators and must be audited.

## 29. Frontend dependency

Tenant frontend first screen flow:

```text
Login page
  -> sends login_type, login, password, tenant header/domain, device info
  -> if 409 active device conflict, show logout other device confirmation
  -> after login, store token
  -> show branch selector from response
  -> call /api/v1/me/permissions after branch selection
  -> build visible menu/actions from permissions
```

Admin frontend first screen flow:

```text
Admin login
  -> platform JWT/session
  -> dashboard
  -> tenant registry/provisioning/subscription/support views
```

Forgot password frontend flow:

```text
Forgot password page
  -> user chooses email or phone
  -> sends login_type and login
  -> API returns generic success
  -> user enters OTP and new password
  -> API resets password and revokes old sessions
  -> user logs in again
```

## 30. Implementation components

Recommended files when coding starts:

```text
routes/api/v1/web.php
routes/api/v1/admin.php

app/Http/Controllers/Web/Auth/AuthController.php
app/Http/Controllers/Web/Auth/ProfileController.php
app/Http/Controllers/Web/Auth/SessionController.php
app/Http/Controllers/Web/Auth/PasswordResetController.php
app/Http/Controllers/Admin/Auth/AdminAuthController.php

app/Http/Requests/Web/Auth/LoginRequest.php
app/Http/Requests/Web/Auth/RegisterTenantRequest.php
app/Http/Requests/Web/Auth/RevokeOtherSessionRequest.php
app/Http/Requests/Web/Auth/ForgotPasswordRequest.php
app/Http/Requests/Web/Auth/ResetPasswordRequest.php

app/Http/Resources/Web/Auth/AuthSessionResource.php
app/Http/Resources/Web/Auth/AuthenticatedUserResource.php
app/Http/Resources/Web/Auth/BranchAccessResource.php
app/Http/Resources/Web/Auth/PermissionSummaryResource.php

app/Services/Auth/TenantLoginService.php
app/Services/Auth/TenantRegistrationService.php
app/Services/Auth/DeviceSessionService.php
app/Services/Auth/TenantContextService.php
app/Services/Auth/BranchContextService.php
app/Services/Auth/PermissionSummaryService.php
app/Services/Auth/OtpService.php
app/Services/Auth/PasswordResetService.php
app/Services/Communication/MessageDeliveryService.php
app/Services/Communication/Contracts/MessageChannel.php
app/Services/Communication/Channels/EmailChannel.php
app/Services/Communication/Channels/SmsChannel.php

app/Jobs/Landlord/ProvisionTenantDatabaseJob.php
app/Jobs/Landlord/SendAuthOtpJob.php
```

Services are justified here because login/session/tenant context is not simple CRUD.

## 31. Performance and reliability notes

- Exact indexed lookup for email/phone.
- Never scan all tenants during login.
- Tenant is resolved from header/domain using landlord indexes.
- Tenant database connection is opened only after tenant account is verified.
- Permissions are loaded only for selected branch.
- Use cache for active session validity, but landlord DB remains source of truth.
- Invalidate cache immediately on logout, revoke, password change or token version change.
- Use simple pagination for session lists.
- Queue tenant provisioning because DB creation/migration/seeding is heavy.
- Queue OTP delivery because email/SMS providers can be slow or unavailable.
- Queue tables remain landlord infrastructure.
- Audit only security-critical events, not every profile read.
- Do not reveal whether a forgot-password identifier exists.
- Do not duplicate OTP sending logic in controllers.

## 32. Final implementation decision

This module should be implemented before organisation, branch CRUD, menu, POS, inventory, finance or reporting APIs.

Without this module, later APIs cannot safely know:

- active tenant;
- active tenant database;
- tenant user;
- selected branch;
- Spatie team context;
- permissions;
- active device session.

