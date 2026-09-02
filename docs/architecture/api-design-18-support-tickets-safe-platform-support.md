# API Design 18: Support Tickets, Tenant-to-Platform Support, Attachments, SLA, and Safe Support Access

Project: Restaurant POS ERP  
Backend: Laravel, PHP 8.3, PostgreSQL, database-per-tenant multitenancy  
API version: v1  
Date: 2026-08-25

## 1. Purpose

This design explains how tenants raise support tickets to platform admin/support and how support is handled safely.

Main rule:

```text
Platform support can manage tenant issues but must not freely read tenant customer/financial details.
```

Support access to tenant data must be explicit, scoped and logged.

## 2. Route placement

Tenant side:

```text
routes/api/v1/web.php
```

Admin/platform side:

```text
routes/api/v1/admin.php
```

Recommended folders:

```text
app/Http/Controllers/Web/Support
app/Http/Controllers/Admin/Support
app/Services/Support
```

## 3. Tables used

Landlord tables:

- `support_ticket_categories`
- `support_tickets`
- `support_ticket_messages`
- `support_ticket_attachments`
- `support_ticket_events`
- `support_access_grants`
- `tenants`
- `tenant_accounts`
- `platform_users`

Tickets live in landlord DB because platform support must see tickets across tenants without scanning tenant DBs.

## 4. Support categories

`support_ticket_categories` classifies tickets.

Areas:

```text
technical
billing
subscription
payment
integration
backup
restore
realtime
performance
security
training
other
```

Category can define:

```text
default assignee
default priority
whether support access grant is required
```

Example:

```text
Category: Restore Request
requires_support_access_grant = true
```

## 5. Support ticket

`support_tickets` is the ticket header.

Important fields:

- tenant
- tenant account
- category
- assigned platform user
- support access grant
- ticket number
- subject
- description
- support area
- priority
- status
- source
- tenant branch UUID
- tenant user UUID
- tenant reference UUID/type/label
- SLA timestamps
- resolution summary

Statuses:

```text
open
assigned
waiting_tenant
waiting_admin
resolved
closed
cancelled
```

Sources:

```text
tenant_portal
admin_created
email
phone
system
```

## 6. Tenant reference fields

Ticket stores tenant references as UUID/label, not direct FK to tenant DB tables.

Example:

```text
tenant_reference_type = payment
tenant_reference_uuid = payment uuid
tenant_reference_label = Payment PAY-2026-01
```

Reason:

Ticket is landlord-side. It should not create FK dependency to tenant DB.

## 7. Messages

`support_ticket_messages` stores conversation.

Sender types:

```text
tenant
platform
system
```

Internal note:

```text
is_internal_note = true
```

Internal notes are visible only to platform support/admin.

## 8. Attachments

`support_ticket_attachments` stores file metadata.

Examples:

```text
screenshot
error log excerpt
payment provider response
receipt photo
```

Store attachments in landlord support storage path:

```text
support/{tenant_uuid}/{ticket_uuid}/{file_uuid}
```

Do not store binary file content in PostgreSQL.

## 9. Events

`support_ticket_events` stores the ticket timeline.

Events:

```text
created
assigned
message_added
internal_note_added
priority_changed
status_changed
attachment_added
access_grant_requested
access_grant_approved
resolved
closed
reopened
cancelled
```

This gives audit history without huge before/after JSON.

## 10. Safe support access

If support needs tenant data:

```text
request support_access_grant
tenant/admin approves
grant has scope and expiry
all access is logged
```

Examples:

```text
Support can view payment setup only.
Support can inspect failed backup logs only.
Support can access tenant DB for 2 hours.
```

Platform owner/support does not need normal access to tenant financial/customer detail.

## 11. API endpoints

Tenant:

```text
GET  /api/v1/support-tickets
POST /api/v1/support-tickets
GET  /api/v1/support-tickets/{ticket_uuid}
POST /api/v1/support-tickets/{ticket_uuid}/messages
POST /api/v1/support-tickets/{ticket_uuid}/attachments
POST /api/v1/support-tickets/{ticket_uuid}/close
```

Admin:

```text
GET  /api/v1/admin/support-ticket-categories
POST /api/v1/admin/support-ticket-categories
GET  /api/v1/admin/support-tickets
GET  /api/v1/admin/support-tickets/{ticket_uuid}
POST /api/v1/admin/support-tickets/{ticket_uuid}/assign
POST /api/v1/admin/support-tickets/{ticket_uuid}/messages
POST /api/v1/admin/support-tickets/{ticket_uuid}/internal-notes
POST /api/v1/admin/support-tickets/{ticket_uuid}/resolve
POST /api/v1/admin/support-tickets/{ticket_uuid}/close
POST /api/v1/admin/support-tickets/{ticket_uuid}/request-access
```

## 12. Complexity

Simple:

- categories
- ticket list/details
- add message

Medium:

- attachments
- assignment
- SLA timestamps
- status transitions

High:

- support access grant
- tenant-context diagnostic access
- restore/security tickets
- audit-sensitive troubleshooting

Queue required:

- ticket notifications
- attachment virus scan if available
- SLA reminders
- email ingestion later

## 13. Performance rules

Frequent filters:

```text
support_tickets.tenant_id/status/opened_at
support_tickets.assigned_to_platform_user_id/status/priority
support_tickets.support_area/status/priority
support_ticket_messages.support_ticket_id/sent_at
support_ticket_events.support_ticket_id/occurred_at
```

Use pagination.

Do not scan tenant DBs for support inbox.

## 14. First release

Implement:

- ticket categories
- tenant ticket create/list/details
- admin ticket list/details
- messages/internal notes
- attachments
- assign/resolve/close
- support access grant link
- ticket notifications

Later:

- email-to-ticket
- SLA dashboard
- canned responses
- remote diagnostics
- customer satisfaction rating
