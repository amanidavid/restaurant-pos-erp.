# API Design 15: Reports, Dashboards, Scheduled Reports, Exports, KPI Snapshots, and Performance Strategy

Project: Restaurant POS ERP  
Backend: Laravel, PHP 8.3, PostgreSQL, database-per-tenant multitenancy  
API version: v1  
Date: 2026-08-25

## 1. Purpose

This design explains how reporting, dashboards, exports and scheduled reports work without damaging performance.

Main goals:

```text
Do not scan all tenant DBs every minute.
Do not duplicate report data.
Use landlord registries for cross-tenant scheduling.
Use queues for heavy work.
Use indexes on frequent filters.
Archive heavy report runs/logs.
```

Reports are tenant-local. The scheduler registry is landlord-side.

## 2. What this design depends on

This module depends on:

- Design 03: company, branch, business date, numbering
- Design 04: tenant users, permissions, branch access
- Design 06: sales/payments
- Design 08: inventory/procurement
- Design 09: finance/accounting
- Design 10: payroll
- Design 11: tax
- Design 12: notification delivery
- landlord tenant registry and report scheduler tables

## 3. Route placement

Tenant report APIs belong in:

```text
routes/api/v1/web.php
```

Admin/platform scheduler APIs belong in:

```text
routes/api/v1/admin.php
```

Recommended tenant folders:

```text
app/Http/Controllers/Web/Reports
app/Http/Controllers/Web/Dashboards
app/Http/Requests/Web/Reports
app/Http/Resources/Web/Reports
app/Services/Reports
app/Services/Dashboards
```

Recommended landlord/admin folders:

```text
app/Http/Controllers/Admin/ReportSchedules
app/Http/Requests/Admin/ReportSchedules
app/Http/Resources/Admin/ReportSchedules
app/Services/Admin/ReportSchedules
```

No `Api/V1` folder is needed. Versioning stays in route files and URL prefixes.

## 4. Tenant tables used

Tenant reporting tables:

- `report_definitions`
- `saved_report_views`
- `report_runs`
- `report_delivery_logs`
- `dashboard_definitions`
- `dashboard_widgets`
- `kpi_snapshots`

Tenant source tables:

- `orders`
- `order_items`
- `payments`
- `refunds`
- `stock_movements`
- `stock_batches`
- `purchase_orders`
- `goods_receipts`
- `supplier_bills`
- `journal_entries`
- `journal_entry_lines`
- `payroll_runs`
- `payroll_run_employees`
- `tax_transactions`
- `business_day_cash_reconciliations`
- other operational tables

## 5. Landlord tables used

Landlord scheduler tables:

- `report_schedule_templates`
- `tenant_report_schedule_assignments`
- `tenant_report_schedule_recipients`
- `tenant_report_schedule_dispatches`

Landlord tenant registry tables:

- `tenants`
- `tenant_databases`

The landlord database does not store tenant report results. It stores schedule/control metadata.

## 6. Report definitions

`report_definitions` defines available reports.

Examples:

```text
Daily Sales Summary
Payment Method Summary
Stock Movement Report
Low Stock Report
Purchase Order Report
Trial Balance
Profit and Loss
Payroll Summary
VAT Report
Audit Exceptions
```

Report areas:

```text
sales
payments
inventory
procurement
finance
tax
payroll
staff
customers
operations
audit
```

Important fields:

- report key
- name
- area
- permission
- default filters
- default columns
- default sort
- system flag
- status

System reports should be protected from deletion.

## 7. Saved report views

`saved_report_views` stores user-specific report preferences.

This is UI/configuration customization, not duplicated report data.

Examples:

```text
My Daily Sales View
Only Mikocheni branch
Columns: order number, time, cashier, amount
Sort: opened_at desc
```

A user can save a default view per report.

This answers the earlier question:

```text
Tenant-created/custom dashboard/report view = UI/configuration, filters, widgets and columns.
It is not copied business data.
```

## 8. Report runs

`report_runs` records generated report jobs.

Run types:

```text
manual
scheduled
system
```

Export formats:

```text
pdf
xlsx
csv
html
json
```

Statuses:

```text
queued
running
completed
failed
cancelled
```

Example:

```text
Report: Daily Sales Summary
Run type: scheduled
Format: pdf
Status: completed
Row count: 120
```

Scheduled runs can reference landlord schedule UUIDs:

```text
landlord_schedule_assignment_uuid
landlord_schedule_dispatch_uuid
```

This connects tenant report output back to the landlord schedule dispatch without storing tenant business data in landlord DB.

## 9. Report delivery logs

`report_delivery_logs` stores delivery status for generated reports.

Channels:

```text
email
sms
whatsapp
push
in_app
```

Example:

```text
Daily sales report PDF emailed to owner
Status: sent
```

It can link to `notification_messages`, so report delivery uses the notification module instead of reinventing email/SMS.

## 10. Dashboard definitions

`dashboard_definitions` defines dashboard layouts.

Dashboard areas:

```text
executive
sales
cashier
kitchen
inventory
procurement
finance
payroll
tax
operations
```

Dashboards can be:

```text
company-wide
branch-specific
system default
tenant-created/custom
```

Tenant custom dashboard means:

```text
layout
widgets
filters
default branch/date range
chart type
visible columns
```

It does not mean copying sales/payment/inventory data into custom tables.

## 11. Dashboard widgets

`dashboard_widgets` defines dashboard components.

Widget types:

```text
number
money
table
line_chart
bar_chart
pie_chart
status_list
```

Examples:

```text
Today Sales
Cash Sales
Open Orders
Kitchen Delays
Low Stock Items
Pending Approvals
Cash Variance
```

Widgets can reference `report_definitions` or a `metric_name`.

Configuration stores UI behavior:

```json
{
  "date_range": "today",
  "branch_scope": "current_branch",
  "refresh_seconds": 60
}
```

## 12. KPI snapshots

`kpi_snapshots` stores selected precomputed metrics.

This is the only controlled exception to “do not duplicate report data.”

Reason:

Some dashboard metrics are expensive to compute repeatedly.

Examples:

```text
daily_sales_total
hourly_sales_total
daily_payment_total
daily_order_count
low_stock_count
cash_variance_total
```

Granularity:

```text
hourly
daily
weekly
monthly
```

Important:

Only snapshot selected metrics, not every report row.

Bad:

```text
Copy every order/payment line into report table.
```

Good:

```text
Store daily_sales_total = 2,500,000
Store daily_order_count = 180
```

## 13. Landlord report schedule templates

`report_schedule_templates` defines platform-managed schedules.

Examples:

```text
Daily Sales Summary at 10:00 AM
Weekly Inventory Summary every Monday
Monthly Profit and Loss
Monthly VAT Summary
```

Fields:

- feature/package restriction
- report key
- area
- frequency
- scheduled time
- timezone strategy
- export format
- default filters
- delivery options
- allow tenant time override
- auto assign to new tenants
- priority
- status

This is the efficient design we chose:

```text
Landlord sets the schedule.
Tenants receive assigned schedules.
Tenants do not create arbitrary cross-tenant scheduler scans.
```

## 14. Tenant report schedule assignments

`tenant_report_schedule_assignments` stores schedule assignment per tenant.

Important fields:

- tenant
- tenant database
- schedule template
- optional tenant branch UUID
- optional tenant report definition UUID
- frequency
- scheduled time
- timezone
- export format
- filters
- delivery options
- next run time
- lock fields
- status

Statuses:

```text
active
paused
ended
failed
```

This table is the key performance control.

Scheduler query:

```text
WHERE status = active
AND next_run_at <= now()
ORDER BY priority, next_run_at
LIMIT batch_size
```

No tenant DB scan is needed to know what should run.

## 15. Tenant report schedule recipients

`tenant_report_schedule_recipients` stores who receives scheduled reports.

Recipient types:

```text
tenant_primary_contact
tenant_user
platform_user
external_contact
```

Channels:

```text
email
sms
whatsapp
push
in_app
```

Examples:

```text
Business owner receives daily sales by email.
Branch manager receives branch daily summary in-app.
Platform support receives only operational failure notification, not tenant financial report.
```

Platform users should not receive tenant report content unless explicitly authorized by support/access policy.

## 16. Tenant report schedule dispatches

`tenant_report_schedule_dispatches` records each scheduled execution attempt.

Statuses:

```text
queued
dispatched
completed
failed
cancelled
```

Flow:

```text
Landlord scheduler finds due assignment
Creates dispatch record
Dispatches queue job with tenant UUID/database identity
Worker connects to tenant DB
Creates tenant report_run
Generates report/export
Updates tenant report_run
Updates landlord dispatch
Schedules next_run_at
```

This gives visibility without centralizing tenant business report data.

## 17. Scheduled report flow

Correct flow:

```text
Landlord scheduler checks tenant_report_schedule_assignments
Locks due assignment
Creates dispatch
Queues job
Worker switches to tenant database
Runs tenant report using indexed source tables
Creates report_run
Stores/export result through media/storage design
Creates delivery logs
Uses notification module to send report
Marks dispatch completed
Computes next_run_at
```

Do not:

```text
loop through 1000 tenant databases every minute
```

That is exactly what landlord assignments prevent.

## 18. Manual report flow

For quick reports:

```text
User requests report
Backend validates permission and branch access
If lightweight, return paginated/summary result immediately
If heavy/export, create report_run and queue job
User receives notification when complete
```

Example:

```text
GET /api/v1/reports/sales-daily?date=2026-08-25&branch_uuid=...
```

If export:

```text
POST /api/v1/report-runs
```

## 19. Dashboard flow

Dashboard should load from:

```text
fast summary endpoints
kpi_snapshots for selected metrics
small recent lists
report definitions for widgets
```

Avoid:

```text
loading entire orders/payments table
joining everything for every refresh
```

Dashboard refresh:

```text
small summary query
reasonable cache TTL if needed
Realtime event tells frontend to refresh affected widget
```

Realtime should not carry full dashboard report data.

## 20. Report categories for first release

Sales:

```text
Daily Sales Summary
Sales by Branch
Sales by Service Type
Sales by Channel
Void/Cancel Report
Discount Report
```

Payments:

```text
Payment Method Summary
Cashier Payment Summary
Refund Report
Provider Settlement Report
```

Inventory:

```text
Stock Balance
Stock Movement
Low Stock
Expiry Report
Waste Report
Stock Count Variance
```

Procurement:

```text
Purchase Orders
Goods Receipts
Supplier Bills
Supplier Payment Aging
```

Finance:

```text
Trial Balance
General Ledger
Profit and Loss
Cash Movement
Expense Report
Supplier Payables
```

Payroll:

```text
Payroll Summary
Payslip Register
Loan Repayment Report
Statutory Deduction Report
```

Tax:

```text
VAT Output/Input
Fiscal Receipt Status
Tax Return Summary
Rejected Fiscal Receipts
```

Operations:

```text
Kitchen Ticket Times
Table Turnover
Shift Close/Cash Variance
Approval Exception Report
```

## 21. Recommended endpoints

Report definitions:

```text
GET  /api/v1/report-definitions
GET  /api/v1/report-definitions/{report_definition_uuid}
```

Saved views:

```text
GET  /api/v1/saved-report-views
POST /api/v1/saved-report-views
PATCH /api/v1/saved-report-views/{saved_report_view_uuid}
POST /api/v1/saved-report-views/{saved_report_view_uuid}/set-default
```

Report runs:

```text
GET  /api/v1/report-runs
POST /api/v1/report-runs
GET  /api/v1/report-runs/{report_run_uuid}
POST /api/v1/report-runs/{report_run_uuid}/cancel
```

Dashboards:

```text
GET  /api/v1/dashboards
POST /api/v1/dashboards
GET  /api/v1/dashboards/{dashboard_uuid}
PATCH /api/v1/dashboards/{dashboard_uuid}
POST /api/v1/dashboards/{dashboard_uuid}/widgets
PATCH /api/v1/dashboards/{dashboard_uuid}/widgets/{widget_uuid}
```

KPI snapshots:

```text
GET /api/v1/kpi-snapshots
```

Admin schedule templates:

```text
GET  /api/v1/admin/report-schedule-templates
POST /api/v1/admin/report-schedule-templates
PATCH /api/v1/admin/report-schedule-templates/{template_uuid}
```

Admin tenant assignments:

```text
GET  /api/v1/admin/tenant-report-schedule-assignments
POST /api/v1/admin/tenant-report-schedule-assignments
PATCH /api/v1/admin/tenant-report-schedule-assignments/{assignment_uuid}
POST /api/v1/admin/tenant-report-schedule-assignments/{assignment_uuid}/pause
POST /api/v1/admin/tenant-report-schedule-assignments/{assignment_uuid}/resume
```

## 22. Complexity classification

Simple CRUD:

- report definitions
- saved report views
- dashboard definitions
- dashboard widgets
- schedule templates
- schedule recipients

Medium complexity:

- lightweight report query
- dashboard summary endpoint
- KPI snapshot read
- report run status/details
- schedule assignment update

High complexity:

- scheduled report dispatch
- export generation
- large report generation
- KPI snapshot computation
- permission-aware report query
- delivery to multiple recipients
- next_run_at calculation across timezones

Queue required:

- scheduled report generation
- large exports
- KPI snapshot computation
- report delivery
- dashboard heavy metric refresh

No queue required:

- small paginated report list
- saved view CRUD
- dashboard layout CRUD
- viewing already generated report run

## 23. Transactions and locking

Use transactions for:

- creating report run
- completing report run
- creating delivery logs
- claiming landlord schedule assignment
- creating landlord dispatch
- updating next_run_at
- computing/upserting KPI snapshot

Use locking for:

- landlord assignment during dispatch
- tenant report run during generation
- KPI snapshot upsert

Reason:

Two scheduler workers must not run the same tenant schedule twice.

## 24. Query and performance rules

All list endpoints use simple pagination.

Frequent filters:

Tenant:

```text
report_definitions.company_id/report_area/status
saved_report_views.tenant_user_id/status
report_runs.report_definition_id/status/queued_at
report_runs.branch_id/status/queued_at
report_runs.requested_by_tenant_user_id/queued_at
report_delivery_logs.report_run_id/status
dashboard_definitions.company_id/dashboard_area/status
dashboard_definitions.branch_id/status
kpi_snapshots.company_id/metric_area/granularity/period_start_at
kpi_snapshots.branch_id/metric_area/granularity/period_start_at
```

Landlord:

```text
tenant_report_schedule_assignments.status/next_run_at/priority
tenant_report_schedule_assignments.tenant_id/status
tenant_report_schedule_dispatches.status/queued_at
tenant_report_schedule_dispatches.tenant_id/status/queued_at
```

Report queries must use:

```text
date range
branch filter where applicable
status filter
indexed source columns
pagination for details
aggregate queries for summaries
```

Avoid:

```text
SELECT * from huge tables
unbounded date ranges
expensive sorting without index
LIKE '%search%'
N+1 loading of report rows
```

## 25. Handling 1000 tenants

Assumption:

```text
1000 tenants
each tenant has 35,000+ operational records
```

Correct strategy:

```text
Landlord only tracks due report assignments.
Workers process due assignments in batches.
Each job connects to exactly one tenant DB.
Tenant report query uses indexes/date filters.
Large reports are queued/exported.
Old report logs are archived.
```

Bad strategy:

```text
Cron loops all tenant databases every minute.
Each tenant checks if reports are due.
Each report recomputes full history.
```

That does not scale.

## 26. Storage and retention

Report data can grow quickly.

Retention recommendation:

```text
report_runs: keep summary long enough for audit/business need
failed report runs: keep troubleshooting window
delivery logs: keep medium retention
export files: expire by report type/settings
kpi snapshots: keep based on granularity
```

Example:

```text
hourly KPI snapshots: 90 days
daily KPI snapshots: 2-5 years
monthly KPI snapshots: long-term
export files: 30-180 days depending report
```

Do not keep every generated PDF forever unless there is legal/business requirement.

## 27. Access control

Recommended permissions:

```text
reports.view
reports.export
reports.schedule_view
reports.run
reports.cancel

dashboards.view
dashboards.manage
dashboards.manage_system

kpi_snapshots.view

admin.report_schedules.view
admin.report_schedules.manage
admin.report_schedules.dispatch
```

Report definitions can also have their own permission requirement.

Example:

```text
Trial Balance requires finance.reports.view
Payroll Summary requires payroll.reports.view
Audit Exceptions requires audit.reports.view
```

## 28. Audit logging

Log critical report/config events:

- system report definition changed
- dashboard system default changed
- scheduled report template changed
- tenant report schedule paused/resumed
- sensitive report exported
- failed scheduled dispatch after repeated attempts

Do not log every dashboard refresh.

Do not log every row returned by a report.

## 29. First release recommendation

Implement first:

- report definitions
- saved report views
- manual report run/export
- report delivery logs
- dashboard definitions/widgets
- selected KPI snapshots
- landlord schedule templates
- landlord tenant schedule assignments
- landlord dispatch records
- scheduled report worker
- daily sales scheduled report
- payment summary report
- stock balance report
- cash variance report
- payroll summary report
- VAT/fiscal status report

Later:

- advanced report builder
- pivot tables
- custom formulas
- report sharing
- scheduled dashboard snapshots
- anomaly detection
- predictive stock/sales analytics
