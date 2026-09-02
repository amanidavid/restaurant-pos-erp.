# API Design 25: Final Schema Review, Known Corrections, Risk Checklist, and First-Release Priority Map

Project: Restaurant POS ERP  
Backend: Laravel, PHP 8.3, PostgreSQL, database-per-tenant multitenancy  
API version: v1  
Date: 2026-08-25

## 1. Purpose

This is the capstone review for Designs 01–24.

It identifies:

```text
what is ready
what must be corrected before coding
what is first-release priority
what can wait
which modules depend on others
which parts require queues/services/transactions
```

This document should guide implementation planning and WBS refinement.

## 2. Core architecture decisions confirmed

Backend:

```text
Laravel
PHP 8.3
PostgreSQL
database-per-tenant multitenancy
```

Tenancy:

```text
landlord database stores platform/tenant registry
tenant database stores business data
tenant UUID header verifies tenant context
branch access is permission/team scoped
```

Models:

```text
app/Models/Landlord
app/Models/Tenant
```

No many unnecessary model subfolders.

Routes:

```text
routes/api/v1/admin.php
routes/api/v1/web.php
optional routes/api/v1/customer.php
```

No `Api/V1` controller/request/resource folder required.

## 3. Packages confirmed

Use:

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

Use packages as foundation, but still write correct tenant-aware services and policies.

## 4. Non-negotiable performance rules

Keep these rules across implementation:

```text
Do not scan all tenant DBs every minute.
Do not log everything.
Do not duplicate report data.
Do not broadcast inside transaction.
Do not store huge before/after JSON for every update.
Use landlord registries for cross-tenant scheduling.
Use queues for heavy work.
Use indexes on every frequent filter.
Archive heavy logs/events.
Backup per tenant.
```

## 5. Designs completed

Completed design documents:

```text
01 Auth and tenant context
02 Tenant registration/provisioning
03 Company/branch/business date/numbering
04 Tenant users/roles/permissions/branch access
05 Menu/ingredients/recipes/modifiers/branch menu
06 POS orders/payments/receipts/printing
07 Kitchen/service execution/reservations
08 Inventory/purchasing/stock control
09 Finance/accounting/double entry
10 HR/payroll/attendance/statutory
11 Tax/fiscalization/compliance
12 Notifications/communications/provider routing
13 Approval workflows
14 Realtime outbox/Reverb
15 Reports/dashboards/scheduled exports
16 Documents/media/imports/exports/storage
17 Backup/restore/archive/retention
18 Support tickets
19 Device/session/POS print agents
20 Customer QR ordering/reservations/loyalty hooks
21 Targeted audit/retention cleanup
22 Business day/cash reconciliation
23 Packages/features/subscriptions
24 Tenant onboarding/provisioning/domains/offboarding
```

## 6. Known schema corrections before coding

### 6.1 Orders numbering

Current issue:

```text
orders.order_number is globally unique
```

But branch numbering can create:

```text
Branch A: ORD-2026-01
Branch B: ORD-2026-01
```

Required correction:

```text
orders: unique(branch_id, order_number)
```

### 6.2 Receipts numbering

Decision needed:

```text
tenant-wide receipt numbers
or branch-scoped receipt numbers
```

If branch-scoped, add:

```text
receipts.branch_id
unique(branch_id, receipt_number)
```

If tenant-wide, keep global receipt number uniqueness.

Recommended first release:

```text
tenant-wide receipt numbers
```

because fiscal/tax receipt traceability is simpler.

### 6.3 Journal source references

Review posting sources.

Each financial source must be traceable to journal by either:

```text
journal_entries has source FK
```

or:

```text
source table has journal_entry_id
```

Do not rely only on journal description text.

### 6.4 Recipe stock deduction source

Current stock movements source list does not directly include order item/production consumption.

Before implementing recipe deduction, add or design:

```text
production_consumptions
production_consumption_items
```

or another controlled source table.

Do not deduct recipe stock with free-text notes only.

### 6.5 Customer loyalty

Loyalty is only hook-level now.

If first release needs real loyalty, add:

```text
loyalty_programs
loyalty_accounts
loyalty_transactions
loyalty_rewards
```

Do not store points only on customers.

## 7. Corrections already completed

Already corrected:

```text
kitchen_tickets unique(branch_id, ticket_number)
reservations unique(branch_id, reservation_number)
number_sequence_allocations no longer globally unique by formatted_number
suppliers support many categories through supplier_category_supplier
inventory document numbers branch-scoped
payroll run numbers scoped by company/branch
tax transaction source check includes item-level source columns
```

## 8. First-release module priority

### Priority 1: Foundation

Must be first:

```text
landlord tenant registry
platform users/admin auth
tenant registration
tenant DB provisioning
tenant migrations/seeders
JWT auth
tenant context middleware
tenant user/session tracking
roles/permissions with Spatie teams
ApiResponse/ApiMessages/global exception handling
```

Without this, nothing else is stable.

### Priority 2: Company/branch/access

Implement:

```text
company
branch
business date
branch access
tenant users
roles/permissions
number sequences
```

### Priority 3: Menu/POS/kitchen/payment/printing

Implement:

```text
menu categories/items
branch menu
modifiers
orders
order items
send to kitchen
kitchen tickets
cash/manual payment
receipts
print devices/jobs
Reverb/outbox basics
```

This gives usable restaurant operation.

### Priority 4: Cash control and reports

Implement:

```text
shift sessions
cash accounts
cash movements
cash reconciliation
daily sales report
payment summary
cash variance report
```

### Priority 5: Inventory basics

Implement:

```text
suppliers
ingredients
stock settings
purchase orders
goods receipts
stock batches
stock movements
stock adjustments
stock counts
waste
```

Recipe deduction can start simple but must be idempotent.

### Priority 6: Finance/tax/payroll

Implement after operations are stable:

```text
chart accounts
journals
expenses
supplier bills
payroll
tax transactions
fiscal receipt skeleton
```

## 9. First 30-day suggested build sequence

Week 1:

```text
Laravel setup/packages
landlord DB
tenant registry
tenant provisioning
auth/JWT/session
tenant context
roles/permissions
company/branch
seeders
```

Week 2:

```text
menu
branch menu
orders
order items
payments basic
receipts
print jobs
Reverb/outbox
kitchen tickets
```

Week 3:

```text
cash control
business day/shift close
inventory basics
purchase/goods receipt
stock movements
reports basic
documents/media upload
```

Week 4:

```text
finance skeleton
expense/supplier bill
payroll skeleton
tax/fiscal skeleton
notifications
approvals
backup
hardening/tests
```

Frontend streams should follow backend readiness:

```text
backend API first for module foundation
tenant frontend follows completed APIs
admin frontend follows landlord/admin APIs
```

## 10. Service-layer rules

Use services for complex logic:

```text
tenant provisioning
login/session conflict
order creation
order pricing
send to kitchen
payment completion
receipt issuing
print job dispatch
stock posting
payroll calculation
journal posting
fiscal submission
approval progression
report generation
backup/restore
```

Do not create unnecessary services for simple CRUD.

## 11. Queue-required areas

Use queues for:

```text
tenant provisioning
tenant migration/seeding
provider webhooks
payment/fiscal submissions
report exports
scheduled reports
imports
PDF generation
image conversions
notifications
Reverb outbox publishing
payroll calculation
backup/restore
archive cleanup
```

Jobs are landlord-controlled and carry tenant identity.

## 12. Validation/API standards

Every endpoint should follow:

```text
Form Request validation
permission check
branch scope check
transaction when mutating multiple tables
ApiResponse / ApiMessages
simple pagination for lists
UUID route binding
server-side filtering with indexes
```

## 13. Security priorities

Must implement:

```text
single active session
token_version invalidation
tenant UUID validation
branch permission scope
device blocking
rate limiting public/customer endpoints
webhook signature validation
encrypted provider credentials
support access grants
backup encryption
```

## 14. Final recommendation

The schema/design is now broad enough for the ERP, but implementation must not start everywhere.

Start with:

```text
foundation -> access -> POS/menu/kitchen/payment/printing -> cash close -> inventory/reporting
```

Then add:

```text
finance/tax/payroll/advanced integrations
```

This keeps the first release realistic while preserving the full enterprise design.
