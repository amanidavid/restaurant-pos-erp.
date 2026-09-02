# API Design 21: Targeted Audit Logging, Activity Logs, Retention Cleanup, and Archive Manifests

Project: Restaurant POS ERP  
Backend: Laravel, PHP 8.3, PostgreSQL, database-per-tenant multitenancy  
API version: v1  
Date: 2026-08-25

## 1. Purpose

This design explains how to log important events without destroying performance or storage.

Main rules:

```text
Do not log everything.
Do not store huge before/after JSON for every update.
Log critical controls only.
Use source tables as operational history.
Archive heavy logs/events.
Apply retention policies.
```

Package:

```text
spatie/laravel-activitylog
```

But activitylog must be used carefully, not blindly on every model.

## 2. Route placement

Tenant APIs:

```text
routes/api/v1/web.php
```

Recommended folders:

```text
app/Http/Controllers/Web/Audit
app/Http/Controllers/Web/Retention
app/Services/Audit
app/Services/Retention
```

## 3. Tables used

Tenant tables:

- `audit_policies`
- `audit_events`
- `retention_cleanup_runs`
- `retention_cleanup_run_tables`
- `archive_manifests`
- Spatie `activity_log` table if installed/configured

Heavy source tables affected by retention:

- `webhook_receipts`
- `realtime_event_outbox`
- `realtime_event_publish_attempts`
- `realtime_connection_events`
- `notification_delivery_events`
- `report_runs`
- `report_delivery_logs`
- `data_import_errors`
- `security_events`
- `device_login_attempts`

## 4. Audit policies

`audit_policies` defines what should be audited.

Areas:

```text
security
access
configuration
integration
sales
payment
refund
inventory
procurement
finance
payroll
tax
fiscal
approval
document
import
device
backup
restore
```

Policy controls:

```text
enabled/disabled
severity
requires reason
capture before/after or not
retention days
```

Default:

```text
capture_before_after = false
```

Only enable before/after for very specific configuration changes.

## 5. Audit events

`audit_events` is the targeted audit table.

It links to real source records:

```text
order
payment
refund
journal
payroll
stock adjustment
approval
document
integration account
branch device
```

It stores:

```text
area
event type
severity
user
affected user
branch
reason
changed fields
metadata
event hash
occurred time
```

Use it for control events, not for every normal update.

## 6. What to audit

Audit these:

```text
login blocked / suspicious activity
permission denied repeated
role/permission changed
integration credentials changed
payment voided
refund approved/completed
receipt voided
manual discount override
stock adjustment posted
stock transfer discrepancy
payroll approved/posted
journal posted/reversed
tax filing submitted
fiscal receipt cancelled/rejected
device blocked/lost
backup restore approved
```

Do not audit these in heavy detail:

```text
every order item quantity edit
every kitchen status update
every dashboard refresh
every notification delivery callback
every realtime event publish
every report row generated
```

Those tables already have operational records.

## 7. Spatie activity log usage

Use Spatie activitylog for:

```text
important model lifecycle events
admin/config changes
security-relevant actions
manual overrides
approval-sensitive actions
```

Avoid:

```text
LogsActivity on every model with all attributes
logOnlyDirty on high-frequency tables without filtering
large JSON old/new values
```

Recommended:

```text
explicit service-level audit event
small changed_fields list
reason required for risky actions
source model UUID
```

## 8. Retention cleanup runs

`retention_cleanup_runs` records cleanup execution.

Actions:

```text
delete
archive_then_delete
anonymize
summarize_then_delete
```

Data areas:

```text
tenant_audit
realtime
notification
reporting
import
security
device
job_history
```

Example:

```text
Cleanup realtime published events older than 30 days
Action: archive_then_delete
```

## 9. Retention cleanup run tables

`retention_cleanup_run_tables` stores table-level result.

Example:

```text
target_table = realtime_event_outbox
timestamp_column = published_at
rows_archived = 12000
rows_deleted = 12000
```

This lets admin see exactly what was cleaned.

## 10. Archive manifests

`archive_manifests` stores archive file metadata.

Fields:

```text
data area
target table
storage driver/path
file name
checksum
row count
period start/end
expiry
```

Do not archive blindly without manifest.

Manifest proves:

```text
what was archived
where it was stored
how many rows
checksum
period covered
```

## 11. Cleanup flow

Recommended:

```text
Landlord dispatch identifies tenant cleanup assignment
Worker connects to tenant DB
Create retention_cleanup_run
For each target table:
  select old rows by indexed timestamp column
  export/archive if required
  delete/anonymize in chunks
  record counts
Create archive manifest
Mark cleanup completed
```

Use chunks.

Do not delete millions of rows in one transaction.

## 12. API endpoints

Audit:

```text
GET /api/v1/audit-policies
PATCH /api/v1/audit-policies/{audit_policy_uuid}
GET /api/v1/audit-events
GET /api/v1/audit-events/{audit_event_uuid}
```

Retention:

```text
GET /api/v1/retention-cleanup-runs
GET /api/v1/retention-cleanup-runs/{cleanup_run_uuid}
GET /api/v1/archive-manifests
GET /api/v1/archive-manifests/{archive_manifest_uuid}
```

Admin scheduling can be landlord-side later.

## 13. Complexity

Simple:

- audit event list/details
- policy view/update
- cleanup run list

Medium:

- manual cleanup request
- archive manifest download

High:

- chunked archive/delete
- anonymization
- hash-chained audit integrity
- retention policy scheduling

Queue required:

- cleanup jobs
- archive generation
- anonymization
- large audit exports

## 14. Performance

Frequent filters:

```text
audit_events.company_id/audit_area/occurred_at
audit_events.branch_id/audit_area/occurred_at
audit_events.tenant_user_id/occurred_at
audit_events.severity/occurred_at
retention_cleanup_runs.status/started_at
archive_manifests.data_area/created_at
archive_manifests.expires_at
```

Use pagination.

Avoid unbounded audit searches.

## 15. First release

Implement:

- audit policies
- targeted audit event service
- critical event logging only
- retention cleanup run tracking
- archive manifests
- cleanup job skeleton
- Spatie activitylog configured for selected models/actions

Later:

- hash-chain verification UI
- legal hold
- anonymization policies
- archive restore viewer
- SIEM/security export
