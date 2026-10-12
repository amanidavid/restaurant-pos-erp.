# API Design 26: Implementation Execution Plan, Dependency Order, and Delivery Streams

Project: Restaurant POS ERP  
Backend: Laravel, PHP 8.3, PostgreSQL, database-per-tenant multitenancy  
API version: v1  
Date: 2026-08-25

## 1. Purpose

This document translates the schema and architecture designs into a practical implementation order.

The main goal is to avoid building screens before their APIs are stable, and to avoid coding modules whose dependencies are not ready.

The delivery streams are:

```text
Backend API
Tenant frontend
Admin frontend
Workers / queues / scheduled tasks
```

Backend comes first for each module. Frontend follows only when the API contract is stable.

## 2. Implementation rules before coding any module

Before coding a module, classify it first.

For every function or endpoint decide:

```text
Is it simple CRUD?
Does it write multiple tables?
Does it need transaction?
Does it need queue?
Does it accept array payload?
Does it need import/export?
Does it need file upload?
Does it require realtime broadcast?
Does it require audit log?
Does it require approval workflow?
Does it require branch scope?
Does it require tenant context?
Does it require idempotency?
```

This prevents weak design.

Example:

```text
Create menu category
simple CRUD
no queue
no approval
no realtime required
transaction optional
```

Example:

```text
Create order with items, modifiers, taxes, discounts
complex function
array payload required
transaction required
branch scope required
pricing service required
idempotency recommended
outbox event required after commit
```

Example:

```text
Import ingredients from Excel
complex function
file upload required
queue required
validation summary required
partial failure report required
audit log required
```

## 3. Folder strategy

Use module folders, but avoid too many model folders.

Models:

```text
app/Models/Landlord
app/Models/Tenant
```

Controllers:

```text
app/Http/Controllers/Admin/Auth
app/Http/Controllers/Admin/Tenants
app/Http/Controllers/Admin/Subscriptions
app/Http/Controllers/Web/Auth
app/Http/Controllers/Web/Company
app/Http/Controllers/Web/Pos
app/Http/Controllers/Web/Menu
app/Http/Controllers/Web/Inventory
app/Http/Controllers/Web/Finance
```

Requests:

```text
app/Http/Requests/Admin/Auth
app/Http/Requests/Admin/Tenants
app/Http/Requests/Web/Auth
app/Http/Requests/Web/Pos
app/Http/Requests/Web/Menu
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

Do not create services for every tiny CRUD action. Use services where business rules are complex.

## 4. Route strategy

API versioning should be in route path, not folder namespace.

Recommended route files:

```text
routes/api.php
routes/api/v1/admin.php
routes/api/v1/web.php
routes/api/v1/customer.php
```

URL examples:

```text
POST /api/v1/web/auth/register
POST /api/v1/web/auth/login
GET  /api/v1/web/me
GET  /api/v1/web/menu/items
POST /api/v1/web/pos/orders
GET  /api/v1/admin/tenants
```

## 5. Response and error standard

All API responses must use:

```text
App\Support\ApiResponse
App\Support\ApiMessages
```

Success example:

```json
{
  "success": true,
  "message": "Order saved successfully.",
  "data": {},
  "errors": null
}
```

Validation error example:

```json
{
  "success": false,
  "message": "Menu item could not be found.",
  "data": null,
  "errors": {
    "items.0.menu_item_uuid": [
      "Menu item could not be found."
    ]
  }
}
```

Global exception handling must cover:

```text
401 unauthenticated
403 forbidden
404 not found
405 method not allowed
422 validation error
429 too many requests
500 server error
database/query errors
tenant context errors
```

## 6. Pagination and query rules

Use simple pagination by default.

Default:

```text
per_page = 15
max_per_page = 100
```

Every list endpoint must:

```text
paginate
filter by indexed fields
avoid expensive unindexed sorting
avoid N+1 queries
load only needed relations
avoid broad contains search where possible
```

Use prefix search only where indexed:

```text
name ILIKE 'abc%'
phone_normalized LIKE '255%'
```

Avoid:

```text
name ILIKE '%abc%'
```

unless there is a proper PostgreSQL trigram/full-text index.

## 7. Phase 1: Foundation backend

This phase must be completed before tenant business modules.

Backend API tasks:

```text
install/configure required packages
create landlord connection
create tenant connection
create landlord migrations
create tenant migrations
create base seeders
create ApiResponse/ApiMessages
configure global exception handling
configure JWT auth
configure tenant context middleware
configure Spatie multitenancy
configure Spatie permission with teams
configure queues using landlord DB
configure cache using landlord DB
configure failed jobs using landlord DB
```

Admin frontend tasks:

```text
admin login
admin dashboard shell
tenant list
tenant detail
tenant provisioning status
```

Tenant frontend tasks:

```text
registration page
login page
tenant context selection if needed
profile/session page
```

Dependencies:

```text
None
```

Must be stable before moving forward.

## 8. Phase 2: Tenant onboarding and access control

Backend API tasks:

```text
tenant registration
tenant database creation job
tenant migration job
tenant seeding job
tenant provisioning status
login by email or phone
single active session enforcement
logout other device flow
password reset OTP
email/SMS OTP abstraction
tenant users
branch access
roles
permissions
protected default roles
permission updates for protected roles
```

Important rule:

```text
Default roles cannot be deleted or renamed.
Their permissions can be changed by authorized users.
```

Tenant frontend tasks:

```text
registration
login
forgot password
OTP verification
branch selection
user management
role management
permission assignment
```

Admin frontend tasks:

```text
tenant approval/status
tenant suspension/reactivation
support view without tenant financial/customer details
```

Dependencies:

```text
Phase 1
```

## 9. Phase 3: Company, branch, settings, and numbering

Backend API tasks:

```text
company profile
branch setup
branch opening/closing hours
business day setup
number sequence setup
settings service
branch settings
tenant settings
```

Tenant frontend tasks:

```text
company setup wizard
branch setup
business hours setup
numbering setup
settings screens
```

Dependencies:

```text
Phase 2
```

Important:

```text
Branch operation cannot work correctly before branch and business date are ready.
```

## 10. Phase 4: Menu, ingredients, recipes, and branch menu

Backend API tasks:

```text
units of measure
tax codes
menu categories
ingredients
menu items
menu item variants
modifiers
modifier groups
recipes
recipe ingredients
branch menu availability
branch menu price overrides
kitchen section routing per branch
image upload for menu items
Excel import for menu/ingredients
```

Tenant frontend tasks:

```text
menu category management
ingredient management
menu item form
recipe builder
modifier builder
branch menu setup
kitchen routing screen
image upload
Excel import screen
```

Dependencies:

```text
Phase 3
```

Complexity notes:

```text
recipe creation accepts array of ingredients
menu item creation may accept images
branch menu update may accept array of branch prices/availability
imports must use queue
```

## 11. Phase 5: POS orders, payments, receipts, and printing

Backend API tasks:

```text
tables/floor areas
sales channels
order creation
order item creation
order modifiers
discounts
tax calculation
payment attempts
manual cash/card/mobile placeholders
receipt creation
print devices
print jobs
thermal receipt template
order status events
outbox events
Reverb broadcast worker
```

Tenant frontend tasks:

```text
POS screen
table layout
cart
item modifiers
discount modal
payment screen
receipt preview
print action
order list
order detail
```

Dependencies:

```text
Phase 4
```

Complexity notes:

```text
order create/update accepts nested array payload
payments require idempotency key
receipt generation must be transaction-safe
print jobs are queued
broadcast happens after commit through outbox
```

## 12. Phase 6: Kitchen and service execution

Backend API tasks:

```text
kitchen ticket generation
kitchen ticket item statuses
production status flow
item ready event
order served event
reservation creation
reservation branch scope
reservation status
support service flow
```

Tenant frontend tasks:

```text
kitchen display screen
order timers
item ready actions
waiter service screen
reservation calendar/list
reservation detail
```

Dependencies:

```text
Phase 5
```

Complexity notes:

```text
kitchen status changes need realtime
do not broadcast inside transaction
use outbox worker
```

## 13. Phase 7: Cash control, business day, and reconciliation

Backend API tasks:

```text
business day open
shift open
cash drawer assignment
opening float
cash movement
petty cash movement
expense cash payment
shift close
expected cash calculation
cash variance
day close
cash reconciliation report
```

Tenant frontend tasks:

```text
open business day
open shift
cash drawer page
petty cash page
close shift
variance review
day close
cash reports
```

Dependencies:

```text
Phase 5
```

Important:

```text
Expected cash is calculated from system transactions.
Actual physical cash is entered manually only when closing cash drawer/shift.
If no physical cash, cash reconciliation can be skipped or zero-cash mode can apply.
```

## 14. Phase 8: Inventory and purchasing

Backend API tasks:

```text
supplier categories
suppliers
supplier category pivot
warehouses
stock locations
purchase requests
purchase orders
goods receipts
stock batches
stock movements
stock adjustments
stock transfers
stock counts
waste records
recipe stock consumption source
```

Tenant frontend tasks:

```text
supplier management
purchase order page
goods receipt page
stock movement list
stock adjustment page
stock transfer page
stock count page
waste record page
```

Dependencies:

```text
Phase 4
Phase 7 optional for cash-paid purchases
```

Complexity notes:

```text
goods receipt accepts array of received items
stock count accepts array of counted items
stock movements must be immutable
recipe stock deduction must be idempotent
```

## 15. Phase 9: Finance, expenses, supplier bills, and double entry

Backend API tasks:

```text
account classes
chart of accounts
account groups/categories
journal entries
journal lines
expense categories
expenses
supplier bills
payments to suppliers
automatic posting rules
period close skeleton
```

Tenant frontend tasks:

```text
chart account management
expense entry
supplier bill entry
journal list
journal detail
trial balance
basic ledger
```

Dependencies:

```text
Phase 7
Phase 8
```

Important:

```text
Finance is tenant-wide, but postings can carry branch_id for branch reporting.
Double entry must be immutable after posting.
Corrections should use reversal/adjustment entries.
```

## 16. Phase 10: Payroll and HR

Backend API tasks:

```text
tenant user employment profile
positions
departments
shifts
attendance
payroll components
statutory settings
payroll run
payroll calculation job
payslips
payroll posting to finance
```

Tenant frontend tasks:

```text
staff profile
position setup
attendance screen
payroll settings
payroll run
payslip view
payroll reports
```

Dependencies:

```text
Phase 2
Phase 9 for accounting posting
```

Important:

```text
Do not create separate employees table duplicating tenant_users.
Employment attributes extend tenant_users.
Payroll calculation should run in queue.
```

## 17. Phase 11: Tax, fiscalization, integrations, and webhooks

Backend API tasks:

```text
tax transactions
fiscal receipts
provider registry
provider accounts per tenant
provider capabilities
payment attempts
provider transactions
webhook receipts
refunds
settlements
reconciliations
idempotency keys
```

Tenant frontend tasks:

```text
payment provider setup
provider status
settlement list
refund request
tax/fiscal status
```

Admin frontend tasks:

```text
global provider catalog
provider capability management
tenant provider activation
```

Dependencies:

```text
Phase 5
Phase 9
```

Important:

```text
Raw webhook payload is short-term only.
Normalize into provider/payment tables.
Archive or delete raw payload based on retention policy.
```

## 18. Phase 12: Reports, dashboard, exports, and scheduled jobs

Backend API tasks:

```text
report definitions on landlord
tenant report registry
dashboard widgets
scheduled report registry
report export jobs
daily sales report
cash report
inventory report
finance report
payroll report
```

Tenant frontend tasks:

```text
dashboard
report list
report filters
export request
scheduled report preferences
```

Admin frontend tasks:

```text
landlord report registry
scheduled report health
failed scheduled job monitoring
```

Dependencies:

```text
Operational modules that supply data
```

Important:

```text
Landlord scheduler selects due tenant jobs.
Do not scan all tenant databases every minute.
Do not duplicate report data unless it is a controlled aggregate/cache.
```

## 19. Phase 13: Notifications, approvals, audit, backup, and support

Backend API tasks:

```text
notification providers
notification templates
notification messages
source-less system notifications
approval workflows
approval steps
approval requests
targeted activity logs
audit reports
backup profiles
backup runs
restore requests
support tickets
support access grants
archive cleanup
retention policies
```

Tenant frontend tasks:

```text
notifications inbox
approval inbox
approval request detail
backup status
support ticket creation
support ticket conversation
audit log views
```

Admin frontend tasks:

```text
support ticket queue
support access approval/review
backup health
archive/retention health
tenant status
```

Dependencies:

```text
Phase 1
Phase 2
Relevant source modules
```

Important:

```text
Do not log everything.
Log critical financial/security/control actions.
Do not store huge before/after JSON for every update.
```

## 20. First-release endpoint completion order

Recommended order:

```text
1. register tenant
2. tenant provisioning status
3. login/logout/forgot password/OTP
4. tenant context/me/permissions
5. company/branch
6. users/roles/permissions
7. menu/ingredients/recipes
8. branch menu/kitchen routing
9. POS orders
10. payments/receipts/printing
11. kitchen tickets
12. shift/cash close
13. inventory basics
14. reports basic
15. finance skeleton
16. payroll skeleton
17. tax/fiscal skeleton
18. notifications/approvals/support/backup
```

## 21. Minimum first-release success criteria

The first release is successful if:

```text
tenant can register
tenant database is provisioned
owner can login
owner can create branches and users
roles and permissions work per branch
menu can be configured
cashier can create order
kitchen receives order
payment can be recorded
receipt can be generated and printed
shift can be closed
basic sales/cash report works
inventory can receive and adjust stock
critical actions are audited
backup can run per tenant
```

## 22. Final recommendation

Start implementation with foundation only.

Do not start POS until:

```text
tenant context
branch context
permissions
numbering
company/branch settings
```

are working.

This keeps the project fast, but still safe.
