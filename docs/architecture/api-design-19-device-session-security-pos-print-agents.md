# API Design 19: Device Management, Single Active Session, POS Devices, Print Agents, and Security Events

Project: Restaurant POS ERP  
Backend: Laravel, PHP 8.3, PostgreSQL, database-per-tenant multitenancy  
API version: v1  
Date: 2026-08-25

## 1. Purpose

This design explains:

```text
branch devices
device pairing
single active user session
POS terminals
kitchen displays
print agents
device login attempts
security events
forced logout
```

Key rule:

```text
One tenant user can have only one active session at a time.
```

If user logs in on Device B while Device A is active, the system must either block login or replace/logout Device A based on policy.

## 2. Route placement

Tenant APIs:

```text
routes/api/v1/web.php
```

Recommended folders:

```text
app/Http/Controllers/Web/Devices
app/Http/Controllers/Web/Sessions
app/Http/Requests/Web/Devices
app/Http/Resources/Web/Devices
app/Services/Devices
app/Services/Sessions
app/Services/Security
```

## 3. Tables used

Tenant tables:

- `branch_devices`
- `branch_device_capabilities`
- `device_pairing_requests`
- `tenant_user_device_sessions`
- `device_print_agent_sessions`
- `device_login_attempts`
- `security_events`
- `print_devices`
- `print_jobs`
- `realtime_connection_events`

## 4. Branch devices

`branch_devices` stores trusted/known physical or logical devices per branch.

Device types:

```text
pos_terminal
kitchen_display
print_agent
customer_display
mobile_app
tablet
back_office
scanner
```

Platforms:

```text
web
android
ios
windows
macos
linux
```

Statuses:

```text
pending
active
blocked
lost
retired
```

Example:

```text
Branch: Mikocheni
Device: Main Cashier POS
Type: pos_terminal
Platform: windows
Trusted: true
Status: active
```

## 5. Device capabilities

`branch_device_capabilities` defines what a device can do.

Capabilities:

```text
point_of_sale
kitchen_display
print_agent
cash_drawer
card_terminal
barcode_scanner
stock_count
admin_panel
```

Example:

```text
Main cashier terminal:
  point_of_sale
  cash_drawer
  print_agent
```

Kitchen screen:

```text
kitchen_display
```

## 6. Device pairing

`device_pairing_requests` is used to approve/register a device.

Flow:

```text
Device requests pairing
System creates short-lived pairing token
Manager/admin approves
Device becomes trusted/active
Pairing token is marked used
```

Statuses:

```text
pending
approved
rejected
used
expired
cancelled
```

Store only token hash:

```text
pairing_token_hash
```

Do not store raw pairing token.

## 7. Single active session

`tenant_user_device_sessions` stores JWT/session tracking.

The schema enforces:

```text
one active session per tenant_user_id
```

Important fields:

- tenant user
- branch
- branch device
- session JTI hash
- refresh token hash
- login method
- active flag
- force logout requested
- login/last seen/expiry/logout timestamps
- logout reason

Login methods:

```text
password
phone_otp
email_otp
google
admin_impersonation
```

Logout reasons:

```text
user_logout
forced_logout
expired
replaced_by_new_login
password_changed
device_blocked
admin_revoked
```

## 8. Device B login while Device A active

Recommended first-release behavior:

```text
Block new login and tell user active session exists.
Allow user to choose "logout old device and continue".
```

Flow:

```text
User logs in on Device B
Backend finds active session on Device A
Response says active_session_exists
Frontend asks user: logout previous device?
If user confirms:
  mark Device A session inactive
  logout_reason = replaced_by_new_login
  create security event session_replaced
  create new Device B session
```

This is safer than silently kicking out the old device.

## 9. JWT/session validation

Every protected request should validate:

```text
JWT is valid
tenant UUID matches tenant context
session_jti_hash exists
session is active
session not expired
tenant_user token_version still valid
device not blocked/lost
branch access still valid
```

If session is inactive:

```text
return invalid session response
```

This supports password change and admin forced logout.

## 10. Print agent sessions

`device_print_agent_sessions` tracks branch-local print agents.

Print agent is usually:

```text
browser app
desktop app
local service
```

It listens for print jobs and prints to local printers.

Statuses:

```text
online
offline
error
retired
```

Only one online session per branch device is allowed.

Flow:

```text
Print agent connects
Registers heartbeat
Subscribes to Reverb print channel
Claims print_jobs by API
Prints locally
Reports success/failure
```

## 11. Login attempts

`device_login_attempts` stores login attempt audit.

Statuses:

```text
success
failed
blocked
```

Use it for:

```text
rate limiting
suspicious login detection
support diagnostics
security audit
```

Do not keep login attempt records forever. Apply retention.

## 12. Security events

`security_events` stores important security timeline.

Events:

```text
login_success
login_failed
login_blocked
session_started
session_replaced
session_expired
forced_logout
device_registered
device_approved
device_blocked
device_lost
pairing_requested
pairing_used
print_agent_connected
print_agent_disconnected
permission_denied
password_changed
suspicious_activity
```

Severity:

```text
info
warning
critical
```

## 13. API endpoints

Devices:

```text
GET  /api/v1/branch-devices
POST /api/v1/branch-devices
GET  /api/v1/branch-devices/{device_uuid}
PATCH /api/v1/branch-devices/{device_uuid}
POST /api/v1/branch-devices/{device_uuid}/approve
POST /api/v1/branch-devices/{device_uuid}/block
POST /api/v1/branch-devices/{device_uuid}/mark-lost
```

Pairing:

```text
POST /api/v1/device-pairing-requests
POST /api/v1/device-pairing-requests/{pairing_uuid}/approve
POST /api/v1/device-pairing-requests/{pairing_uuid}/reject
POST /api/v1/device-pairing-requests/use-token
```

Sessions:

```text
GET  /api/v1/my-device-sessions
POST /api/v1/my-device-sessions/{session_uuid}/logout
POST /api/v1/my-device-sessions/logout-others
```

Print agent:

```text
POST /api/v1/print-agent-sessions/connect
POST /api/v1/print-agent-sessions/heartbeat
POST /api/v1/print-agent-sessions/disconnect
```

Security:

```text
GET /api/v1/security-events
GET /api/v1/device-login-attempts
```

## 14. Complexity

Simple:

- list devices
- list sessions
- list security events

Medium:

- device pairing
- approve/block device
- print agent heartbeat

High:

- login with active session conflict
- forced logout
- JWT/session middleware
- device trust validation
- print agent claim/connection lifecycle

Queue required:

- security notifications
- suspicious activity alerts
- retention cleanup

## 15. Performance and retention

Frequent filters:

```text
branch_devices.branch_id/device_type/status
tenant_user_device_sessions.tenant_user_id/is_active
tenant_user_device_sessions.branch_id/is_active/last_seen_at
device_print_agent_sessions.status/last_heartbeat_at
device_login_attempts.login_identifier/attempted_at
security_events.tenant_user_id/event_type/occurred_at
security_events.branch_id/severity/occurred_at
```

Retention:

```text
login attempts: 30-90 days
security events: 90 days or more for critical
print agent sessions: archive old disconnected sessions
connection logs: short retention
```

## 16. First release

Implement:

- one active session enforcement
- active-session conflict response
- logout old device and continue
- branch devices
- device pairing
- print agent sessions
- device capabilities
- security events
- login attempts
- middleware session validation

Later:

- device geolocation risk
- biometric device integration
- MDM/mobile device management
- advanced suspicious activity scoring
