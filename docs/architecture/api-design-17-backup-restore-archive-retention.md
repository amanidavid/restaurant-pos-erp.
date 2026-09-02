# API Design 17: Backup, Restore, Archive, Retention Cleanup, and Tenant Database Maintenance

Project: Restaurant POS ERP  
Backend: Laravel, PHP 8.3, PostgreSQL, database-per-tenant multitenancy  
API version: v1  
Date: 2026-08-25

## 1. Purpose

This design explains backup, restore, archive cleanup and database maintenance for database-per-tenant architecture.

Main goals:

```text
Backup per tenant.
Do not mix tenant backups.
Run backup before tenant migration.
Encrypt and compress backups.
Track backup artifacts.
Require approval for restore.
Prefer restore to new database before replacing existing DB.
Archive heavy logs/events.
```

## 2. Route placement

Backup/restore is platform admin work.

Routes:

```text
routes/api/v1/admin.php
```

Recommended folders:

```text
app/Http/Controllers/Admin/Backups
app/Http/Requests/Admin/Backups
app/Http/Resources/Admin/Backups
app/Services/Admin/Backups
app/Services/Admin/Restore
app/Services/Admin/Maintenance
```

## 3. Tables used

Landlord tables:

- `backup_storage_locations`
- `tenant_backup_policies`
- `tenant_backup_runs`
- `tenant_backup_artifacts`
- `tenant_restore_requests`
- `tenant_restore_runs`
- `tenant_database_maintenance_events`
- `tenants`
- `tenant_databases`

Package:

```text
spatie/laravel-backup
```

Database tools:

```text
pg_dump
pg_restore
psql
```

## 4. Storage locations

`backup_storage_locations` defines where backups are stored.

Drivers:

```text
local
s3
sftp
azure
gcs
```

Recommended:

```text
local for development only
s3/compatible object storage for production
```

Store credentials using secure secret reference, not plain text in DB.

## 5. Backup policies

`tenant_backup_policies` defines when/how each tenant is backed up.

Scopes:

```text
database
media
full
```

Frequencies:

```text
hourly
daily
weekly
monthly
before_migration
```

Important flags:

```text
run_before_tenant_migration
include_media
encrypt_backup
compress_backup
retention_daily
retention_weekly
retention_monthly
```

Example:

```text
Daily full backup at 02:00 Africa/Dar_es_Salaam
Keep daily 7
Keep weekly 4
Keep monthly 12
```

## 6. Backup runs

`tenant_backup_runs` stores every backup attempt.

Run types:

```text
scheduled
manual
before_migration
before_restore
```

Statuses:

```text
queued
running
completed
failed
cancelled
expired
```

Snapshot fields:

```text
database_name_snapshot
schema_version_snapshot
postgres_version_snapshot
backup_tool
backup_format
manifest_path
checksum
```

This makes old backups explainable even if tenant DB name changes later.

## 7. Backup artifacts

`tenant_backup_artifacts` stores files created by a backup run.

Artifact types:

```text
database_dump
media_archive
manifest
log
checksum
```

Example full backup:

```text
database_dump: tenant db custom dump
media_archive: tenant files zip
manifest: json with file list/checksums
checksum: checksum file
```

## 8. Backup flow

Recommended flow:

```text
Landlord scheduler finds due backup policy
Create tenant_backup_run
Queue backup job
Worker locks tenant backup run
Resolve tenant database
Run pg_dump for tenant DB only
Archive tenant media if included
Create manifest and checksums
Encrypt/compress if configured
Upload artifacts
Mark backup completed
Compute next_run_at
```

Never run one backup job that dumps all tenants together.

## 9. Restore requests

`tenant_restore_requests` requires approval before restore.

Restore modes:

```text
restore_to_new_database
replace_existing_database
export_only
```

Recommended safe default:

```text
restore_to_new_database
```

Reason:

It lets admin verify restored data before switching tenant database pointer.

## 10. Restore flow

Safe restore flow:

```text
Platform user requests restore
Approval required
Create restore run
Create backup before restore
Restore selected backup to new database
Validate migration/schema/version
Validate basic table counts/checksums
Platform admin reviews
Switch tenant_database pointer only after approval
Keep old database temporarily
Record maintenance event
```

Avoid direct replace unless emergency and approved.

## 11. Before migration backup

Before applying tenant migrations:

```text
create tenant_backup_run with run_type before_migration
complete backup
record maintenance event
run tenant migration
record migration result
```

If backup fails:

```text
block migration unless platform override permission is used
```

## 12. Archive and retention cleanup

Heavy tables need retention:

```text
webhook_receipts
realtime_event_outbox
realtime_event_publish_attempts
realtime_connection_events
notification_delivery_events
report_runs
report_delivery_logs
data_import_errors
old generated exports
old raw provider payloads
```

Retention should be policy-based, not random deletes.

Use maintenance event:

```text
event_type = archive_cleanup
```

## 13. Maintenance events

`tenant_database_maintenance_events` tracks:

```text
backup
restore
migration
schema_check
vacuum
analyze
reindex
archive_cleanup
health_check
```

This gives platform visibility without reading tenant business content.

## 14. API endpoints

Storage:

```text
GET  /api/v1/admin/backup-storage-locations
POST /api/v1/admin/backup-storage-locations
PATCH /api/v1/admin/backup-storage-locations/{uuid}
```

Policies:

```text
GET  /api/v1/admin/tenant-backup-policies
POST /api/v1/admin/tenant-backup-policies
PATCH /api/v1/admin/tenant-backup-policies/{uuid}
POST /api/v1/admin/tenant-backup-policies/{uuid}/pause
POST /api/v1/admin/tenant-backup-policies/{uuid}/resume
```

Runs:

```text
GET  /api/v1/admin/tenant-backup-runs
POST /api/v1/admin/tenants/{tenant_uuid}/backup-runs
GET  /api/v1/admin/tenant-backup-runs/{uuid}
```

Restore:

```text
GET  /api/v1/admin/tenant-restore-requests
POST /api/v1/admin/tenant-restore-requests
POST /api/v1/admin/tenant-restore-requests/{uuid}/approve
POST /api/v1/admin/tenant-restore-requests/{uuid}/reject
GET  /api/v1/admin/tenant-restore-runs
```

Maintenance:

```text
GET /api/v1/admin/tenant-database-maintenance-events
```

## 15. Complexity classification

Simple:

- list backup policies/runs
- storage location CRUD

Medium:

- create manual backup request
- pause/resume policy
- list restore runs

High:

- actual database backup
- media archive
- restore to new DB
- replace existing DB
- pre-migration backup
- retention cleanup

Queue required:

- every backup
- every restore
- archive cleanup
- health checks
- large media archive

## 16. Security rules

Backups contain full tenant data.

Require:

```text
platform admin permission
approval for restore
encrypted backup
restricted storage credentials
audit/maintenance log
download restrictions
short-lived signed URLs if download needed
```

Platform support should not casually download tenant backups.

## 17. First release recommendation

Implement first:

- backup storage locations
- daily tenant DB backup policy
- manual tenant backup
- before-migration backup
- backup run/artifact tracking
- restore request approval
- restore to new database
- archive cleanup job skeleton
- maintenance events

Later:

- automated restore validation suite
- point-in-time recovery
- cross-region backups
- backup cost dashboard
- restore drill scheduler
