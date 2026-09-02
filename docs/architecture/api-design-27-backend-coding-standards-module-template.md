# API Design 27: Backend Coding Standards and Module Implementation Template

Project: Restaurant POS ERP  
Backend: Laravel, PHP 8.3, PostgreSQL, database-per-tenant multitenancy  
API version: v1  
Date: 2026-08-25

## 1. Purpose

This document defines how backend modules should be implemented.

It is not a schema design. It is the coding standard for turning the schema into maintainable Laravel APIs.

The goal is:

```text
DRY code
clear folder structure
consistent API responses
consistent validation
safe tenant/branch authorization
simple pagination
indexed queries
services only where complexity exists
queues for heavy work
transactions for multi-table writes
```

## 2. Main rule before coding any endpoint

Before creating controller/request/service/resource files, classify the endpoint.

Ask:

```text
Is this simple CRUD?
Is this complex business logic?
Does it write more than one table?
Does it accept nested array data?
Does it need a database transaction?
Does it need queue/job?
Does it need realtime event/outbox?
Does it need audit log?
Does it need approval?
Does it need file upload?
Does it need import/export?
Does it need idempotency?
Does it need branch permission scope?
```

The answer controls the implementation pattern.

## 3. Endpoint complexity levels

### 3.1 Simple CRUD

Examples:

```text
create menu category
update supplier category
list units of measure
activate/deactivate sales channel
```

Use:

```text
Controller
Form Request
Resource
Model query scopes if useful
Policy/permission check
ApiResponse
```

Do not create a service unless there is real business logic.

### 3.2 Medium logic

Examples:

```text
create supplier with categories
update branch menu price overrides
create reservation
create petty cash movement
```

Use:

```text
Controller
Form Request
Resource
Service if logic touches multiple tables
Transaction
Audit log if control-sensitive
```

### 3.3 Complex workflow

Examples:

```text
tenant registration/provisioning
order creation with items/modifiers/taxes
payment completion
receipt issuing
send order to kitchen
goods receipt posting
stock adjustment posting
payroll calculation
journal posting
refund approval
scheduled report export
backup/restore
```

Use:

```text
Controller
Form Request
Resource
Service
DTO or normalized array object if payload is complex
Transaction
Idempotency if request may be repeated
Queue if heavy or external
Outbox if realtime event is needed
Audit log if financial/security/control action
```

## 4. Folder structure

Use module folders for HTTP and services.

Controllers:

```text
app/Http/Controllers/Admin/Auth
app/Http/Controllers/Admin/Tenants
app/Http/Controllers/Admin/Subscriptions
app/Http/Controllers/Web/Auth
app/Http/Controllers/Web/Company
app/Http/Controllers/Web/Access
app/Http/Controllers/Web/Menu
app/Http/Controllers/Web/Pos
app/Http/Controllers/Web/Kitchen
app/Http/Controllers/Web/Inventory
app/Http/Controllers/Web/Finance
app/Http/Controllers/Web/Payroll
app/Http/Controllers/Web/Reports
```

Requests:

```text
app/Http/Requests/Admin/Auth
app/Http/Requests/Admin/Tenants
app/Http/Requests/Web/Auth
app/Http/Requests/Web/Menu
app/Http/Requests/Web/Pos
app/Http/Requests/Web/Inventory
```

Resources:

```text
app/Http/Resources/Admin
app/Http/Resources/Web
```

Services:

```text
app/Services/Landlord
app/Services/Tenant
```

Models:

```text
app/Models/Landlord
app/Models/Tenant
```

Do not create many model subfolders.

## 5. Route standard

Use versioned route files.

Recommended:

```text
routes/api.php
routes/api/v1/admin.php
routes/api/v1/web.php
routes/api/v1/customer.php
```

In `routes/api.php`, load:

```php
Route::prefix('v1/admin')
    ->name('api.v1.admin.')
    ->group(base_path('routes/api/v1/admin.php'));

Route::prefix('v1/web')
    ->name('api.v1.web.')
    ->group(base_path('routes/api/v1/web.php'));

Route::prefix('v1/customer')
    ->name('api.v1.customer.')
    ->group(base_path('routes/api/v1/customer.php'));
```

Controller namespaces do not need `Api/V1`.

## 6. Controller standard

Controllers should be thin.

Controller responsibilities:

```text
receive request
authorize action
call service or model query
return resource/ApiResponse
```

Controller should not:

```text
perform complex calculations
build large nested write logic
contain provider integration logic
contain payroll calculation logic
contain posting/accounting logic
dispatch realtime broadcast inside transaction
```

Example simple controller pattern:

```php
public function index(IndexMenuItemRequest $request): JsonResponse
{
    $items = MenuItem::query()
        ->with(['category:id,uuid,name'])
        ->forCompany($request->user()->company_id)
        ->when($request->filled('search'), fn ($query) => $query->search($request->string('search')->toString()))
        ->latest('id')
        ->simplePaginate($request->integer('per_page', 15));

    return ApiResponse::resource(
        MenuItemResource::collection($items),
        ApiMessages::listRetrieved('menu_items')
    );
}
```

## 7. Request validation standard

Use Form Requests for validation.

Request responsibilities:

```text
validate data types
validate required fields
validate array structures
validate UUID references exist in the current tenant DB
validate branch scope where possible
normalize phone/email/name input
```

Do not trust frontend values.

Example nested order payload:

```json
{
  "branch_uuid": "uuid",
  "sales_channel_uuid": "uuid",
  "items": [
    {
      "menu_item_uuid": "uuid",
      "quantity": 2,
      "modifiers": [
        {
          "modifier_uuid": "uuid",
          "quantity": 1
        }
      ],
      "notes": "No chili"
    }
  ]
}
```

Validation should support arrays:

```text
items required array min:1
items.*.menu_item_uuid required uuid exists
items.*.quantity required numeric min:0.001
items.*.modifiers nullable array
items.*.modifiers.*.modifier_uuid required uuid exists
```

## 8. Resource standard

Resources control API output.

Do not return model directly.

Resource should:

```text
hide internal id
show uuid
format names consistently
include only required relation data
avoid huge nested output by default
```

Example:

```json
{
  "uuid": "uuid",
  "name": "Beef Burger",
  "category": {
    "uuid": "uuid",
    "name": "Burgers"
  },
  "is_active": true
}
```

Use detail endpoints for deeper relations.

## 9. Model standard

All main models should have:

```text
id for foreign keys
uuid for public route/API references
fillable or guarded strategy
casts
relationships
query scopes for repeated filters
```

Use internal `id` for foreign keys.

Use `uuid` for:

```text
routes
API input
API output
external references
```

Do not expose internal incremental IDs to frontend unless there is a specific admin-only reason.

## 10. Query scope standard

Repeated filters should become scopes.

Examples:

```php
public function scopeForBranch(Builder $query, int $branchId): Builder
{
    return $query->where('branch_id', $branchId);
}

public function scopeActive(Builder $query): Builder
{
    return $query->where('status', 'active');
}
```

Use scopes to avoid repeated query logic across controllers.

## 11. Pagination standard

Use simple pagination for list endpoints.

Default:

```text
15 records
```

Maximum:

```text
100 records
```

Example:

```php
$perPage = min($request->integer('per_page', 15), 100);

$records = $query->simplePaginate($perPage);
```

Avoid returning unlimited lists.

Only allow small lookup endpoints when data is naturally small and cached.

## 12. Search standard

Search must be index-aware.

Preferred:

```text
normalized exact search
prefix search
status filters
date range filters
branch filters
company filters
```

Avoid:

```text
ILIKE '%term%'
```

unless using PostgreSQL trigram/full-text index.

Recommended searchable columns:

```text
email_normalized
phone_normalized
name
display_name
document_number
status
branch_id
created_at
business_date
```

## 13. Transaction standard

Use transactions when:

```text
writing parent and child rows
posting stock movement
posting journal entry
creating order with items
creating payment and receipt
closing shift/day
creating approval request with steps
updating multiple related tables
```

Example:

```php
return DB::connection('tenant')->transaction(function () use ($data) {
    $order = $this->createOrder($data);
    $this->createOrderItems($order, $data['items']);
    $this->writeOutboxEvent($order);

    return $order;
});
```

Do not broadcast directly inside this transaction.

## 14. Queue standard

Use queue for:

```text
tenant provisioning
tenant migrations
imports
exports
PDF generation
image conversions
notifications
payment provider calls
fiscal provider calls
webhook processing
report generation
scheduled reports
payroll calculation
backup
archive cleanup
outbox publishing
```

Jobs must carry:

```text
tenant_uuid
tenant_database_id or tenant database name reference
actor_uuid when available
correlation_id when available
```

Jobs should be stored in landlord DB.

## 15. Realtime standard

Use outbox pattern.

Flow:

```text
business transaction writes order/payment/kitchen data
same transaction writes realtime_outbox_events row
transaction commits
worker reads pending outbox events
worker broadcasts through Laravel Reverb
worker marks event as sent
```

This improves reliability because if the transaction rolls back, no event is broadcast for data that does not exist.

Do not:

```text
broadcast inside DB transaction
broadcast before payment/order commit
use websocket event as source of truth
```

Database remains source of truth.

## 16. Audit log standard

Do not log everything.

Log critical actions:

```text
login/session conflict
role/permission changes
tenant status change
branch settings change
price changes
discount/void/refund
payment completion
receipt cancellation/reissue
cash movement
shift close/day close
stock adjustment
stock count approval
journal posting
payroll approval
provider credential change
backup/restore
support access grant
```

Avoid huge before/after JSON.

Store:

```text
action
model type
model uuid
actor
branch
small important changed fields
reason
ip/device
created_at
```

Archive old audit logs based on retention policy.

## 17. Permission standard

Use explicit permission names.

Good:

```text
menu.item.create
menu.item.update
pos.order.create
pos.order.void
payment.refund.approve
inventory.stock-adjustment.approve
finance.journal.post
payroll.run.approve
```

Avoid broad vague permissions:

```text
manage menu
manage finance
admin
```

Protected default roles:

```text
Business Owner
Manager
Cashier
Waiter
Chef
Storekeeper
Accountant
HR Officer
Auditor
Delivery Driver
```

Protected means:

```text
cannot delete
cannot rename
permissions can be changed by authorized user
```

## 18. Branch scope standard

Users are tenant users with branch access.

Every branch-level endpoint must verify:

```text
tenant context is valid
authenticated user belongs to tenant
branch belongs to tenant database
user has branch access
user has required permission for that branch/team
```

Examples of branch-scoped modules:

```text
orders
payments
kitchen tickets
reservations
cash shifts
stock locations
stock counts
support tickets if raised from branch context
```

Examples of tenant/company-wide modules:

```text
company profile
chart of accounts
global menu policy
tenant settings
provider account settings
subscriptions
```

## 19. Idempotency standard

Use idempotency where repeated requests can cause duplicate money, stock, or documents.

Required for:

```text
order submission from POS
payment attempts
payment provider webhooks
refunds
receipt generation
goods receipt posting
stock adjustment posting
payroll posting
fiscal submission
```

Example:

```text
Idempotency-Key: 01J8...
```

Store key with:

```text
tenant
actor/device
endpoint/action
request hash
response summary
status
expires_at
```

## 20. Import/export standard

Imports:

```text
upload file
store media
create import batch
queue parser job
validate rows
write valid rows
store row errors
return import summary
```

Exports:

```text
create export request
queue export job
store generated file
notify user when ready
expire/delete file later
```

Do not process large Excel files synchronously in HTTP request.

## 21. File/media standard

Use Spatie Media Library.

Use for:

```text
tenant logos
branch logos
menu item images
supplier documents
staff documents
imports
exports
receipts/PDFs
backup metadata files if needed
```

Keep large files out of database.

Database stores:

```text
media reference
collection name
model relation
metadata
```

## 22. Notification standard

Notification sending should use provider abstraction.

Supported channels:

```text
sms
email
whatsapp
push
in_app
```

Capabilities must be represented clearly.

Examples:

```text
sms.send
sms.delivery_callback
email.send
email.delivery_callback
whatsapp.send
push.send
```

System notifications may be source-less.

Example:

```text
Backup completed.
Your password will expire soon.
Tenant provisioning completed.
```

Business notifications should include source where available.

Example:

```text
order_uuid
payment_uuid
refund_uuid
reservation_uuid
```

## 23. Naming and display standard

Normalize important identity fields:

```text
email_normalized lowercase trimmed
phone_normalized E.164 Tanzania format where possible
first_name title case for display
last_name title case for display
display_name generated consistently
```

Do not rely on frontend formatting.

For Tanzania first release:

```text
default country TZ
default timezone Africa/Dar_es_Salaam
default currency TZS
default phone country +255
```

## 24. API response examples

List:

```json
{
  "success": true,
  "message": "Menu items loaded successfully.",
  "data": [
    {
      "uuid": "uuid",
      "name": "Beef Burger"
    }
  ],
  "links": {},
  "meta": {},
  "errors": null
}
```

Create:

```json
{
  "success": true,
  "message": "Menu item saved successfully.",
  "data": {
    "uuid": "uuid",
    "name": "Beef Burger"
  },
  "errors": null
}
```

Error:

```json
{
  "success": false,
  "message": "You do not have access to this action.",
  "data": null,
  "errors": null
}
```

## 25. Module implementation checklist

Before marking any backend module complete:

```text
migration exists
model exists
relationships exist
request validation exists
resource exists
controller exists
route exists
permission exists
policy or permission check exists
branch scope exists where required
simple pagination exists for lists
indexes support filters
service exists only if logic needs it
transaction exists where required
queue exists where required
audit exists where required
outbox event exists where required
tests or smoke validation completed
```

## 26. Final recommendation

When implementation starts, begin with a small vertical slice:

```text
tenant registration
tenant provisioning
login
tenant context
me/permissions
company
branch
```

After this slice works, POS and menu development will be much safer.
