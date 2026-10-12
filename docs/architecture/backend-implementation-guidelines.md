# Backend Implementation Guidelines

Project: Restaurant POS ERP  
Backend: Laravel, PHP 8.3, PostgreSQL, database-per-tenant multitenancy  
Architecture style: DRY, OOP, API-first, service-oriented only where business complexity requires it

## 1. Purpose

This document defines how backend implementation should be approached before controllers and APIs are created.

The system has many modules and a large schema, so implementation must avoid repeated logic, random CRUD duplication, and frontend/backend mismatch. Every module should be analyzed before implementation to decide:

- whether the API should accept one record or an array of records;
- whether logic belongs in a service, job, model, controller, or request;
- whether the operation needs a transaction;
- whether the operation needs approval, audit, notification, stock movement, journal posting, or realtime event;
- whether import/export is required;
- whether the frontend needs a complete object payload or step-by-step endpoints.

## 2. Core Principles

### 2.1 DRY

Do not repeat common logic in many controllers.

Reusable logic should be extracted into:

- reusable request rules;
- base query/filter helpers;
- services;
- traits only when the behavior is truly reusable and stable;
- response helpers;
- resource classes;
- jobs;
- policies;
- value objects or DTO-like data classes where useful.

### 2.2 OOP Responsibility

Each class should have a clear reason to exist.

| Class type | Responsibility |
|---|---|
| Controller | Receive request, call model/service, return resource/response. |
| Form Request | Validate request, authorize basic action when appropriate. |
| Resource | Shape API response. |
| Model | Represent table and relationships. |
| Policy | Permission and ownership/branch access decision. |
| Service | Complex business operation involving rules, transactions, multiple models, side effects. |
| Job | Heavy/background work. |
| Event/Outbox writer | Reliable async/realtime notification trigger. |
| Seeder | Default system data. |

## 3. Controller Rules

Controllers must be thin.

A controller may directly use a model for simple CRUD.

A controller should call a service when the action:

- touches multiple tables;
- needs a database transaction;
- creates document numbers;
- posts stock movements;
- posts journal entries;
- triggers approvals;
- triggers notifications;
- writes audit events;
- writes realtime outbox events;
- handles files/import/export;
- calls an external provider;
- uses idempotency;
- needs locks/concurrency control;
- contains complex branching rules.

Bad controller pattern:

```php
public function store(Request $request)
{
    // validation, numbering, stock, payment, journal, notification all mixed here
}
```

Good controller pattern:

```php
public function store(StoreGoodsReceiptRequest $request, GoodsReceiptPostingService $service)
{
    $goodsReceipt = $service->post($request->validated());

    return new GoodsReceiptResource($goodsReceipt);
}
```

All controller responses must use `App\Support\ApiResponse` and messages from `App\Support\ApiMessages`.

Do not return random JSON shapes from controllers.

Standard shape:

```json
{
  "success": true,
  "message": "Orders loaded successfully.",
  "data": {},
  "errors": null
}
```

Error shape:

```json
{
  "success": false,
  "message": "The request could not be completed because some information is missing or invalid.",
  "data": null,
  "errors": {}
}
```

## 4. Service Usage Rules

Services are not for every CRUD table. They are for business use cases.

### 4.1 Use service for complex operations

Examples:

- Tenant provisioning
- User invitation acceptance
- Device session enforcement
- Goods receipt posting
- Stock transfer posting
- Stock count posting
- Order submission to kitchen
- Payment recording
- Refund approval/completion
- Receipt generation
- Journal posting
- Payroll run calculation
- Report run generation
- Import batch processing
- Backup dispatch
- Realtime outbox publishing

### 4.2 Avoid service for simple setup CRUD

Examples that may not need a service at first:

- supplier category CRUD;
- menu category CRUD;
- employment type CRUD;
- leave type CRUD;
- tax exemption reason CRUD;
- simple lookup status update.

If simple CRUD later grows business rules, introduce a service then.

### 4.3 Complexity-first decision

Before coding a function, decide its complexity level.

| Complexity | Meaning | Implementation |
|---|---|---|
| Simple | One table, simple CRUD, no side effects. | Controller + Form Request + Resource + Model. |
| Medium | Parent/child rows, array payload, simple transaction. | Controller + Form Request + focused Service + Resource. |
| Complex | Multiple modules, stock/finance/approval/audit/realtime/idempotency. | Controller + Service(s) + transaction + locks + tests. |
| Heavy/long-running | Import/export/report/backup/provider sync/large batch. | Controller + Job + Service + status table/resource. |

The purpose is not to create many classes for beauty. The purpose is to choose the smallest safe structure that can handle the business behavior.

## 5. API Payload Shape Rules

The payload should match how the user performs the work.

Do not force the frontend to submit one row at a time when the business action is naturally one document with multiple lines.

### 5.1 Single-record payload

Use single object payload for simple setup records.

Examples:

```json
{
  "name": "Main Branch",
  "phone": "+255700000000",
  "status": "active"
}
```

Good for:

- branch profile;
- supplier category;
- employment type;
- payment method;
- tax authority;
- account class/category;
- leave type.

### 5.2 Parent with child array payload

Use object + array when the user creates one business document with multiple line items.

Examples:

- order with order items;
- purchase order with purchase order items;
- goods receipt with receipt items;
- stock transfer with transfer items;
- stock count with count items;
- journal entry with debit/credit lines;
- payroll run with selected employees;
- recipe with recipe ingredients.

Example:

```json
{
  "supplier_uuid": "uuid",
  "warehouse_uuid": "uuid",
  "received_on": "2026-09-10",
  "items": [
    {
      "ingredient_uuid": "uuid",
      "unit_uuid": "uuid",
      "quantity_received": "10.0000",
      "unit_cost": "2500.0000",
      "expires_on": "2026-12-31"
    }
  ]
}
```

This should be processed in one transaction.

Before implementing any create/update endpoint, explicitly decide:

- Does the frontend naturally submit one object?
- Does the frontend naturally submit many child rows?
- Does the operation need full replacement of child rows?
- Does the operation need partial update of child rows?
- Should deleted child rows be removed, archived, or marked inactive?
- Should the array be processed synchronously or queued?

### 5.3 Bulk update payload

Use array payload when frontend edits many existing rows at once.

Examples:

- branch menu prices;
- menu item availability;
- dashboard widget positions;
- notification preferences;
- stock count quantities;
- role permission assignments.

Example:

```json
{
  "items": [
    {
      "branch_menu_item_uuid": "uuid",
      "price": "12000.00",
      "is_available": true
    },
    {
      "branch_menu_item_uuid": "uuid",
      "price": "9000.00",
      "is_available": false
    }
  ]
}
```

### 5.4 Avoid arbitrary bulk APIs

Do not create generic “bulk update any table” APIs.

Bulk APIs must be specific to a use case because validation, authorization, audit, and side effects differ per module.

## 6. Modules That Usually Require Array Payloads

| Module | Payload shape | Reason |
|---|---|---|
| Orders | order + items + modifiers | Cashier creates many lines together. |
| Menu item setup | item + branches + modifiers + recipe ingredients | One screen may configure a complete sellable item. |
| Recipes | recipe + ingredients | Recipe is useless without lines. |
| Purchase orders | PO + items | Business document with lines. |
| Goods receipts | GRN + items | Posting must create stock batches/movements. |
| Stock transfers | transfer + items | One transfer has many ingredients. |
| Stock counts | count + items | Storekeeper counts many items. |
| Waste records | waste + items | Waste is often batched by shift/day. |
| Journal entries | entry + lines | Double-entry requires at least two lines. |
| Payroll runs | run + employee selection | Payroll processes many staff. |
| Statutory brackets | rule + brackets | Bracket rules are ordered arrays. |
| Dashboard widgets | dashboard + widgets | Layout is updated as a set. |
| Notification messages | message + recipients | One message can target many recipients. |
| Imports | batch + rows/file | Processing is batch-based. |

## 7. Modules That Usually Use Single CRUD

| Module | Reason |
|---|---|
| Company profile | One company record. |
| Branch profile | One branch at a time. |
| Supplier category | Simple lookup. |
| Employment type | Simple lookup. |
| Leave type | Simple lookup. |
| Payment method | Simple setup. |
| Tax authority | Simple setup, unless integrating provider. |
| Account category/subcategory | Simple setup. |
| Report definition | Usually one definition at a time. |
| Backup policy | One policy assignment at a time. |

## 8. Transaction Rules

Use database transactions when:

- creating parent + children;
- posting financial entries;
- posting stock movements;
- approving/rejecting workflow steps;
- generating document numbers;
- updating totals;
- changing order/payment/refund status;
- creating audit/outbox records with the source action.

Inside a transaction:

- validate all UUIDs before writing;
- lock records if concurrency matters;
- create source records;
- create ledger/movement records;
- create audit/outbox rows;
- commit.

Do not broadcast websocket events directly inside a transaction.

Correct flow:

```text
Transaction starts
  -> update order/payment/stock/etc.
  -> insert realtime_event_outbox row
Transaction commits
Worker reads outbox
Worker broadcasts through Reverb
```

## 9. Concurrency and Locking

Use locks where double action would corrupt data.

Examples:

- payment of same order;
- refund of same payment;
- stock posting;
- stock count posting;
- journal posting;
- number sequence generation;
- payroll run posting;
- tenant provisioning;
- device session login enforcement.

Use `lockForUpdate()` in a transaction for records that must not be changed concurrently.

## 10. Number Generation

Use a reusable number sequence service.

Do not generate business numbers manually in controllers.

Examples:

- `ORD-2026-01`
- `REC-2026-01`
- `SHIFT-20260824-01`
- `EXP-2026-01`
- `PO-2026-01`
- `GRN-2026-01`
- `PAYRUN-2026-01`

Number generation should be atomic and tenant-local.

## 11. UUID Resolution Rules

Frontend should send UUIDs, not numeric IDs.

Backend should resolve UUIDs to IDs in request/service layer.

Rules:

- Validate UUID format.
- Verify record belongs to the current tenant database.
- Verify branch/company scope.
- Use IDs internally for FK writes.
- Return UUIDs in resources.

Do not expose internal numeric IDs in public API responses unless explicitly needed for internal admin tooling.

## 12. Authorization Rules

Authorization must check:

- authenticated user;
- tenant membership;
- selected tenant UUID header;
- selected branch access where applicable;
- permission name;
- record scope.

Permission naming should be explicit:

- `orders.create`
- `orders.discount.override`
- `refunds.approve`
- `stock_transfers.post`
- `payroll_runs.process`
- `journal_entries.post`

Avoid vague permission names like:

- `manage`
- `admin`
- `all`

## 13. Import Design Rules

Imports should not directly insert everything synchronously if the file can be large.

Use this flow:

```text
Upload file
  -> create data_import_batch
  -> queue import job
  -> validate rows
  -> insert valid rows in chunks
  -> store row errors in data_import_errors
  -> show import result
```

Import candidates:

- staff/users;
- customers;
- suppliers;
- menu items;
- ingredients;
- opening stock;
- chart of accounts;
- earning/deduction types;
- statutory brackets;
- price lists.

Each import needs:

- template format;
- required columns;
- validation rules;
- duplicate handling;
- error report;
- rollback or partial success decision;
- permission;
- audit event for critical imports.

## 14. Export Design Rules

Exports must use filtered queries and queued jobs for large datasets.

Export candidates:

- sales reports;
- order list;
- payment list;
- stock movement report;
- purchase orders;
- goods receipts;
- payroll reports;
- payslips;
- journal entries;
- audit events;
- backup/retention reports.

Rules:

- never export all data without filters for large tables;
- use date range filters;
- use pagination or queued export;
- store export artifact when large;
- apply permissions;
- avoid loading all records into memory.

## 14.1 Pagination Rules

Use simple pagination for normal list APIs.

Default rule:

- use Laravel `simplePaginate()` for normal list endpoints;
- default page size should be small, usually 15;
- allow controlled `per_page`, for example max 100;
- do not return large unpaginated lists;
- use cursor pagination only later for very large event/ledger tables where simple pagination becomes insufficient.

Normal API list response should be resource collection + simple pagination metadata.

Avoid expensive total-count queries unless the frontend has a real need for total pages.

## 15. Query and Performance Rules

- Use indexes on frequent filters.
- Use simple pagination for list endpoints likely to exceed 15 records.
- Avoid N+1 queries.
- Use eager loading only for required relationships.
- Do not use broad `%term%` search by default on large tables.
- Prefer prefix search or indexed normalized columns.
- Do not scan all tenant databases for schedules.
- Use landlord registries for cross-tenant schedules.
- Use queues for heavy tasks.
- Archive heavy logs/events.

## 15.1 Queue and Job Storage Rules

All Laravel queue/job framework tables should stay in landlord database.

Reason:

- jobs are platform infrastructure, not tenant business records;
- workers should not need to scan or migrate job tables in every tenant DB;
- failed jobs and job batches are easier to monitor centrally;
- cross-tenant scheduled work should be dispatched from landlord registries.

Tenant-specific jobs may carry tenant UUID/database information in payload, then switch tenant context inside the job before doing tenant work.

Heavy or long-running tasks must use jobs:

- tenant database provisioning;
- backups;
- restores;
- report generation;
- exports;
- imports;
- webhook processing if provider callbacks are heavy;
- notification delivery;
- realtime outbox publishing;
- retention cleanup;
- payroll run calculation if many staff;
- stock posting if bulk/large.

Do not perform long-running work inside an HTTP request.

## 16. Audit Rules

Do not log everything.

Log critical actions:

- login/session/security events;
- role/permission changes;
- tenant provisioning;
- payment/refund actions;
- stock postings;
- journal postings;
- payroll postings;
- approval decisions;
- backup/restore actions;
- support access grants;
- fiscal/tax submissions.

Avoid storing huge before/after JSON for every update.

Store:

- event type;
- actor;
- source table/entity UUID;
- timestamp;
- small metadata;
- reason/comment where needed.

## 17. Realtime Rules

Use Laravel Reverb with an outbox pattern.

Do not broadcast directly inside transaction.

Realtime candidates:

- order submitted;
- kitchen ticket opened;
- kitchen item completed;
- payment completed;
- refund approved;
- table status changed;
- stock alert;
- approval request created;
- notification created.

Outbox worker should:

- publish pending events;
- record attempts;
- mark published/failed;
- retry failed events safely.

## 18. Notification Rules

Notifications are not audit logs.

Use notifications for messages that users or customers need to see.

Examples:

- approval request waiting;
- payment success;
- order ready;
- reservation confirmation;
- payroll/payslip published;
- backup completed;
- support ticket updated.

Use `system_reference_key` for system messages that do not link to an order/payment/refund.

## 19. File and Media Upload Rules

Use Spatie Media Library for uploaded files.

Upload candidates:

- company logo;
- branch logo;
- menu item images;
- customer documents;
- supplier documents;
- receipt attachments;
- expense receipts;
- import files;
- support ticket attachments.

Rules:

- validate file type and size;
- store collection name consistently;
- do not store large binary data in database;
- return media UUID/url in resources;
- restrict access by tenant/branch permissions.

## 20. Suggested Reusable Backend Components

| Component | Purpose |
|---|---|
| ApiResponse helper | Standard success/error response. |
| QueryFilter helper | Common search/filter/sort/pagination. |
| UuidResolver | Resolve UUIDs to tenant-local IDs safely. |
| TenantContext | Current tenant, company, branch, user context. |
| BranchAccessService | Verify user access to branch. |
| NumberSequenceService | Generate document numbers atomically. |
| ApprovalService | Create and progress approvals. |
| AuditEventService | Write targeted audit events. |
| RealtimeOutboxService | Queue events for Reverb worker. |
| NotificationService | Create message/recipient/delivery records. |
| StockMovementService | Create immutable stock movements. |
| JournalPostingService | Create journal entries and lines. |
| ImportBatchService | Process import files. |
| ExportService | Queue/generate exports. |
| IdempotencyService | Prevent duplicate provider/webhook actions. |

## 21. API Contract Template

Each endpoint should be documented like this before frontend starts.

```text
Module:
Endpoint:
Method:
Headers:
Permission:
Request JSON:
Response JSON:
Validation rules:
Pagination/filter/sort:
Side effects:
Transaction required:
Approval required:
Audit event:
Realtime event:
Import/export:
Error responses:
Frontend screens depending on it:
Complexity level:
Payload shape:
Queue/job required:
Pagination type:
```

## 22. Implementation Decision Checklist

Before coding any endpoint, answer:

- Is this simple CRUD or business operation?
- What is the complexity level: simple, medium, complex, or heavy?
- Does it need a service?
- Does it need array payload?
- Does it create parent + child rows?
- Does it require a transaction?
- Does it require row locking?
- Does it generate a document number?
- Does it affect stock?
- Does it affect finance/journal?
- Does it trigger approval?
- Does it trigger notification?
- Does it trigger realtime?
- Does it need audit?
- Does it need import/export?
- Does it need a queued job?
- If it needs a queued job, is the job stored/dispatched through landlord queue tables?
- Does the list endpoint use simple pagination?
- What permissions are required?
- What indexes support the list/search filters?
- What should the frontend send?
- What should the API return?

## 23. First Implementation Recommendation

Before implementing Wave 1 APIs, create API contracts for:

- Auth/login/logout/refresh/current user;
- tenant registration/provisioning;
- branch selection;
- tenant users/roles/permissions;
- company/branch setup;
- number sequence pattern;
- standard list pagination/filter response.

This will keep backend and frontend aligned.
