# API Design 14: Realtime Outbox, Laravel Reverb, WebSocket Channels, and Reliable Event Publishing

Project: Restaurant POS ERP  
Backend: Laravel, PHP 8.3, PostgreSQL, database-per-tenant multitenancy  
API version: v1  
Date: 2026-08-25

## 1. Purpose

This design explains how the system broadcasts live updates safely using Laravel Reverb and a database outbox.

Main realtime consumers:

```text
POS cashier screen
Kitchen display screen
Waiter/table screen
Branch manager dashboard
Print agent
Approval inbox
Notification bell
Device status screen
```

Main rule:

```text
Do not broadcast inside a database transaction.
```

Instead:

```text
Write business data
Write realtime outbox event
Commit transaction
Worker publishes event to Reverb
Mark outbox event published
```

This keeps frontend screens aligned with committed database facts.

## 2. What this design depends on

This module depends on:

- Design 01: auth and tenant context
- Design 04: tenant users, permissions, branch access
- Design 06: orders, payments, receipts, print jobs
- Design 07: kitchen tickets
- Design 12: notifications
- Design 13: approvals
- branch/device/session tables
- Laravel queue workers
- Laravel Reverb

Realtime is tenant-local, but queue workers are coordinated from the landlord queue system.

## 3. Route placement

Realtime management APIs belong in:

```text
routes/api/v1/web.php
```

Broadcast auth routes can use Laravel broadcasting routes, but must validate:

```text
authenticated user
tenant UUID/header
branch access
permission/channel policy
device session where required
```

Recommended source folders:

```text
app/Http/Controllers/Web/Realtime
app/Http/Requests/Web/Realtime
app/Http/Resources/Web/Realtime
app/Services/Realtime
app/Services/Broadcasting
```

No `Api/V1` folder is needed. Versioning stays in route files and URL prefixes.

## 4. Tables used

Tenant tables:

- `realtime_channel_policies`
- `realtime_event_outbox`
- `realtime_event_publish_attempts`
- `realtime_connection_events`
- `tenant_users`
- `branches`
- `branch_devices`
- `tenant_user_device_sessions`
- source tables such as orders, kitchen tickets, payments, approvals, print jobs

Landlord tables:

- queue jobs
- failed jobs
- scheduler registry if cross-tenant work is needed

## 5. Why not broadcast inside transaction?

Bad flow:

```text
Transaction starts
Order is created
Broadcast order.created immediately
Frontend receives order
Transaction fails
Database rolls back
Frontend saw an order that does not exist
```

This creates ghost orders, ghost tickets, wrong kitchen screens and confused staff.

Correct flow:

```text
Transaction starts
Order is created
Outbox event order.created is inserted
Transaction commits
Worker reads pending outbox event
Worker broadcasts to Reverb
Outbox event marked published
Frontend receives only committed data
```

If transaction fails:

```text
Outbox event is rolled back too
No broadcast happens
```

That is the whole point of the outbox pattern.

## 6. Realtime channel policies

`realtime_channel_policies` defines who can subscribe to a channel.

Important fields:

- company
- optional branch
- optional permission
- channel key
- channel pattern
- audience type
- requires authenticated user
- requires branch access
- status

Audience types:

```text
branch_staff
cashier
kitchen
manager
device
tenant_admin
```

Example:

```text
channel_key = branch_orders
channel_pattern = tenant.{tenant_uuid}.branch.{branch_uuid}.orders
audience_type = branch_staff
permission = orders.view
requires_branch_access = true
```

Example:

```text
channel_key = kitchen_section
channel_pattern = tenant.{tenant_uuid}.branch.{branch_uuid}.kitchen_section.{section_uuid}
audience_type = kitchen
permission = kitchen_tickets.view
```

## 7. Recommended channel names

Branch orders:

```text
tenant.{tenant_uuid}.branch.{branch_uuid}.orders
```

Kitchen:

```text
tenant.{tenant_uuid}.branch.{branch_uuid}.kitchen
tenant.{tenant_uuid}.branch.{branch_uuid}.kitchen_section.{kitchen_section_uuid}
```

Payments:

```text
tenant.{tenant_uuid}.branch.{branch_uuid}.payments
```

Print agent:

```text
tenant.{tenant_uuid}.branch.{branch_uuid}.print
tenant.{tenant_uuid}.branch.{branch_uuid}.device.{device_uuid}
```

Approvals:

```text
tenant.{tenant_uuid}.branch.{branch_uuid}.approvals
tenant.{tenant_uuid}.user.{tenant_user_uuid}.approvals
```

Notifications:

```text
tenant.{tenant_uuid}.user.{tenant_user_uuid}.notifications
```

Manager dashboard:

```text
tenant.{tenant_uuid}.branch.{branch_uuid}.dashboard
```

Avoid creating a separate channel per order item unless there is a proven need. Too many tiny channels make authorization, subscriptions and memory harder to manage.

## 8. Realtime event outbox

`realtime_event_outbox` is the durable event table.

Important fields:

- company
- optional branch
- created by user
- source references
- event type
- channel name
- visibility scope
- payload
- payload hash
- idempotency key
- priority
- status
- attempts
- max attempts
- available time
- lock fields
- published time
- expiry time
- failure reason

Statuses:

```text
pending
publishing
published
failed
cancelled
expired
```

Visibility scopes:

```text
tenant
branch
user
device
kitchen_section
```

Priority:

```text
1 = highest
9 = lowest
```

Example:

```text
event_type = kitchen_ticket.created
channel_name = tenant.x.branch.y.kitchen_section.z
visibility_scope = kitchen_section
priority = 2
status = pending
```

## 9. Supported event types

Current schema supports:

```text
order.created
order.updated
order.cancelled
order.sent_to_kitchen
kitchen_ticket.created
kitchen_ticket.updated
kitchen_ticket.ready
reservation.created
reservation.updated
payment.completed
payment.voided
refund.requested
refund.approved
refund.completed
stock.updated
approval.requested
approval.decided
notification.created
print_job.created
print_job.updated
device.status_changed
```

Add new event types carefully through migration, not random strings.

Reason:

Controlled event names make frontend contracts stable.

## 10. Publish attempts

`realtime_event_publish_attempts` stores every worker attempt.

Fields:

- event
- worker name
- attempt number
- status
- started/completed time
- duration
- failure reason
- provider response

Statuses:

```text
started
published
failed
```

This helps diagnose:

```text
Was Reverb unavailable?
Did the worker crash?
How many attempts happened?
How long did publishing take?
```

Do not put huge provider responses here forever. Retention is required.

## 11. Connection events

`realtime_connection_events` stores connection/subscription/security events.

Examples:

```text
connected
disconnected
subscribed
unsubscribed
auth_failed
permission_denied
```

Statuses:

```text
info
warning
critical
```

Purpose:

- troubleshoot branch device connection
- detect repeated auth failures
- check whether kitchen screen is connected
- diagnose print agent disconnection

Important:

Do not monitor every broadcast for business content.

Admin visibility should focus on:

```text
connection health
failed publish attempts
stuck pending events
failed device subscriptions
```

Platform admin does not need to read tenant order details to know that Reverb/worker health is failing.

## 12. Worker publishing flow

Recommended worker flow:

```text
Select pending events where available_at <= now
Order by priority asc, available_at asc, id asc
Lock a small batch
Mark each event publishing
Create publish attempt
Broadcast payload to channel through Reverb
If success: mark published
If failure: increment attempts and mark failed/pending retry
If attempts exceeded: mark failed
```

Use PostgreSQL row locking pattern:

```text
FOR UPDATE SKIP LOCKED
```

This allows multiple workers without double-publishing the same event.

## 13. Retry behavior

If Reverb is temporarily down:

```text
event status = failed or pending retry
attempts increments
available_at is moved forward
worker retries later
```

Backoff example:

```text
attempt 1: retry after 10 seconds
attempt 2: retry after 30 seconds
attempt 3: retry after 2 minutes
attempt 4: retry after 5 minutes
```

If max attempts exceeded:

```text
status = failed
failure_reason stored
admin/tenant support can inspect
```

## 14. Idempotency

`idempotency_key` prevents duplicate realtime events for the same committed event.

Example:

```text
order:{order_uuid}:sent_to_kitchen:v1
```

If the service retries the same operation:

```text
same idempotency key
database prevents duplicate outbox event
```

Do not rely on frontend to deduplicate important events.

## 15. Payload design

Payload should be small.

Good payload:

```json
{
  "order_uuid": "uuid",
  "order_number": "ORD-2026-01",
  "status": "sent_to_kitchen",
  "occurred_at": "2026-08-25T12:00:00+03:00"
}
```

Avoid huge payload:

```text
full order
all items
all modifiers
all payment history
all customer details
```

Frontend can receive the event and fetch details if needed.

Reason:

Small payloads reduce memory, bandwidth, Reverb load and storage.

## 16. Event examples

Order sent to kitchen:

```text
source: order_id
event_type: order.sent_to_kitchen
channel: tenant.{tenant}.branch.{branch}.orders
payload: order uuid, order number, status
```

Kitchen ticket created:

```text
source: kitchen_ticket_id
event_type: kitchen_ticket.created
channel: tenant.{tenant}.branch.{branch}.kitchen_section.{section}
payload: ticket uuid, ticket number, order number
```

Payment completed:

```text
source: payment_id
event_type: payment.completed
channel: tenant.{tenant}.branch.{branch}.payments
payload: payment uuid, order uuid, amount, status
```

Print job created:

```text
source: print_job_id
event_type: print_job.created
channel: tenant.{tenant}.branch.{branch}.print
payload: print job uuid, job type, status
```

Approval requested:

```text
source: approval_request_id
event_type: approval.requested
channel: tenant.{tenant}.user.{approver}.approvals
payload: approval request uuid, title, amount, current step
```

## 17. Print agent realtime

Print jobs use realtime only as a wake-up signal.

Correct flow:

```text
Backend creates print_job
Backend writes print_job.created outbox event
Worker broadcasts to branch print channel
Print agent wakes up
Print agent calls API to claim pending print job
Print agent prints locally
Print agent reports success/failure by API
```

Do not send the entire printable receipt only over WebSocket and hope it prints.

The durable record is:

```text
print_jobs
```

Realtime is just the notification that work exists.

## 18. Kitchen realtime

Kitchen screens use realtime to refresh active tickets quickly.

Flow:

```text
Order sent to kitchen
Kitchen tickets created
Outbox event written
Worker broadcasts kitchen_ticket.created
Kitchen screen receives event
Kitchen screen adds ticket or refetches active ticket list
```

Timers should be calculated from timestamps, not broadcast every minute.

Do not write timer events every minute. That is wasted storage and worker load.

## 19. Dashboard realtime

Dashboard events should be lightweight.

Example:

```text
payment.completed
order.cancelled
refund.completed
cash_variance_detected
```

The event should not carry full report data.

Dashboard can update small counters or refetch summary endpoint.

Do not duplicate report data into realtime outbox.

## 20. Broadcast authorization

On channel subscription, backend must verify:

```text
valid JWT/session
tenant UUID matches auth context
branch access if branch channel
permission if channel policy requires it
device session if device channel
```

Example:

Kitchen staff can subscribe to:

```text
tenant.x.branch.y.kitchen_section.z
```

only if:

```text
user belongs to tenant
user has branch access
user has kitchen_tickets.view or relevant permission
```

## 21. Recommended endpoints

Realtime policy management:

```text
GET  /api/v1/realtime/channel-policies
POST /api/v1/realtime/channel-policies
PATCH /api/v1/realtime/channel-policies/{channel_policy_uuid}
```

Outbox/admin diagnostics:

```text
GET /api/v1/realtime/events
GET /api/v1/realtime/events/{event_uuid}
POST /api/v1/realtime/events/{event_uuid}/retry
POST /api/v1/realtime/events/{event_uuid}/cancel
```

Connection diagnostics:

```text
GET /api/v1/realtime/connection-events
```

Broadcast auth:

```text
POST /broadcasting/auth
```

This route must use tenant context and permission checks.

## 22. Complexity classification

Simple CRUD:

- realtime channel policies

Medium complexity:

- realtime event list/details
- retry/cancel failed event
- connection event diagnostics

High complexity:

- outbox event creation inside business transaction
- worker claim/publish/retry
- channel authorization
- idempotency
- failed/stuck event recovery
- branch/device/user scoped channels

Queue required:

- all outbox publishing
- failed publish retry
- connection-health cleanup/retention

No queue required:

- channel policy CRUD
- connection event list
- manual retry API that only marks event pending

## 23. Transactions and locking

Use transactions for:

- source business action plus outbox insert
- claiming outbox event for publishing
- marking event published/failed
- creating publish attempt

Use locking for:

- outbox rows being published
- publish attempt number generation

Reason:

Multiple workers must not publish the same event twice.

## 24. Query and performance rules

Frequent filters:

```text
realtime_event_outbox.status/available_at/priority
realtime_event_outbox.branch_id/status/available_at
realtime_event_outbox.event_type/status/available_at
realtime_event_outbox.channel_name/status
realtime_event_outbox.locked_at
realtime_event_outbox.published_at
realtime_event_outbox.expires_at
realtime_connection_events.branch_id/event_type/occurred_at
realtime_connection_events.tenant_user_id/occurred_at
realtime_connection_events.branch_device_id/occurred_at
```

Use the partial pending index:

```text
status = pending
available_at
priority
id
```

Do not scan all tenant DBs every minute for realtime work.

Workers should operate only when jobs exist or by bounded tenant/job context. If cross-tenant scheduling is needed, use landlord registry, not blind polling all tenants.

## 25. Storage and retention

Realtime tables can grow fast.

Retention recommendation:

```text
published outbox events: retain short period, then archive/delete
failed events: retain longer for troubleshooting
publish attempts: retain short/medium period
connection events: retain short period unless critical
payload/provider_response JSON: keep small and archive/delete old data
```

Example first-release retention:

```text
published outbox: 7-30 days
failed outbox: 90 days
publish attempts: 30 days
connection events: 7-30 days
critical auth failures: 90 days
```

This follows our project rule:

```text
Archive heavy logs/events.
```

## 26. Monitoring

Platform/tenant support should monitor technical health, not tenant business content.

Useful metrics:

```text
pending event count
oldest pending event age
failed event count
average publish duration
Reverb connection count
auth_failed/permission_denied count
print agent disconnected
kitchen screen disconnected
```

Admin does not need to read order/payment payloads to know realtime infrastructure is unhealthy.

## 27. Security rules

Never trust channel name alone.

Validate:

```text
tenant access
branch access
permission
device session
token_version/session validity
```

Do not allow a user from Branch A to subscribe to Branch B channel unless they have branch access.

Do not expose hidden financial payloads through general branch channels.

Use separate channels or small payloads for sensitive events.

## 28. First release recommendation

Implement first:

- Reverb setup
- broadcast authentication with tenant/branch permission checks
- realtime channel policies
- outbox insert helper/service
- outbox publisher worker
- publish attempts
- order events
- kitchen ticket events
- print job events
- payment completed event
- approval requested/decided event
- notification created event
- connection event logging with retention

Later:

- dashboard realtime counters
- device health dashboard
- advanced retry backoff control
- dead-letter queue UI
- tenant-level realtime usage analytics
- connection anomaly detection
