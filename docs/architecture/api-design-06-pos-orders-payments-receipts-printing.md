# API Design 06: POS Orders, Payments, Receipts, and Printing

Project: Restaurant POS ERP  
Backend: Laravel, PHP 8.3, PostgreSQL, database-per-tenant multitenancy  
API version: v1  
Date: 2026-08-24

## 1. Purpose

This design explains the first operational sales flow:

```text
Cashier / waiter opens order
Order items are added
Order is sent to kitchen
Kitchen tickets are created
Customer pays
Receipt is issued
Receipt or bill is printed
Realtime screens are notified
```

This is a high-traffic module. It must be designed for speed, reliability, simple frontend use, and safe accounting integration later.

## 2. What this design depends on

This module depends on previous designs:

- Design 01: auth and tenant context
- Design 03: company, branch, business date, shift, numbering
- Design 04: tenant users, permissions, branch access
- Design 05: menu, branch menu items, modifiers, kitchen routing

Reason:

An order must know the tenant user, branch, open shift, business date, active menu item, kitchen section, payment method and printer setup.

## 3. Route placement

Tenant POS APIs belong in:

```text
routes/api/v1/web.php
```

Recommended source folders:

```text
app/Http/Controllers/Web/Pos
app/Http/Controllers/Web/Payments
app/Http/Controllers/Web/Printing
app/Http/Requests/Web/Pos
app/Http/Requests/Web/Payments
app/Http/Requests/Web/Printing
app/Http/Resources/Web/Pos
app/Http/Resources/Web/Payments
app/Http/Resources/Web/Printing
app/Services/Pos
app/Services/Payments
app/Services/Printing
```

No `Api/V1` folder is needed. Versioning stays in route files and URL prefixes.

## 4. Main tables used

Tenant tables:

- `sales_channels`
- `service_types`
- `customers`
- `counters`
- `dining_areas`
- `dining_tables`
- `business_dates`
- `shift_sessions`
- `orders`
- `order_items`
- `order_item_modifiers`
- `order_status_logs`
- `discounts`
- `discount_applications`
- `service_charges`
- `tips`
- `kitchen_tickets`
- `kitchen_ticket_items`
- `payment_methods`
- `integration_providers`
- `provider_capabilities`
- `integration_accounts`
- `payment_attempts`
- `payments`
- `payment_allocations`
- `provider_transactions`
- `webhook_receipts`
- `refunds`
- `receipts`
- `print_devices`
- `print_templates`
- `print_jobs`

Landlord tables involved indirectly:

- tenant registry
- queue jobs
- failed jobs
- schedule/worker visibility
- backups and platform monitoring

Jobs remain landlord-side because workers need one central queue control point. Job payloads must contain tenant UUID/database identity so the worker can connect to the correct tenant database before processing.

## 5. Important business concepts

### 5.1 Sales channel

`sales_channels` answers this question:

```text
Where did this sale come from?
```

Examples:

- POS counter
- QR web ordering
- phone order
- food aggregator
- manual back-office sale

The `type` column is a controlled value:

```text
pos
online
aggregator
phone
manual
```

Example:

```text
name = Main Counter POS
type = pos
```

Another example:

```text
name = Food Delivery Partner
type = aggregator
```

This is useful for reporting:

- sales by POS
- sales by online ordering
- sales by aggregator
- sales by phone

### 5.2 Service type

`service_types` answers this question:

```text
How will the customer receive the food?
```

Examples:

- dine in
- takeaway
- delivery
- pickup

The `type` column is a controlled value:

```text
dine_in
takeaway
delivery
pickup
```

Example:

```text
Customer sits at Table A4
sales_channel = POS
service_type = dine_in
```

Example:

```text
Customer orders by phone and collects later
sales_channel = phone
service_type = pickup
```

### 5.3 Counter

`counters` represent physical or logical selling points inside a branch.

Examples:

- Main Cashier
- Bar Counter
- Takeaway Counter
- Reception

This helps with accountability:

```text
Which counter opened this order?
Which counter received this payment?
Which counter had cash variance at shift close?
```

### 5.4 Dining area and dining table

`dining_areas` groups tables.

Examples:

- Main Hall
- VIP Room
- Garden
- Balcony

`dining_tables` stores actual tables.

Examples:

- Table 01
- Table 02
- VIP 01

For dine-in orders, the order can be linked to a table.

### 5.5 Shift session

`shift_sessions` represents the cashier/branch operating session.

Example:

```text
SHIFT-20260824-01
Branch: Mikocheni
Opened by: Cashier A
Opening float: 100,000 TZS
Status: open
```

Orders and payments should normally attach to the currently open shift. This is important for day close and cash accountability.

### 5.6 Business date

`business_dates` represents the restaurant operating date, not only the calendar date.

Example:

If a restaurant opens at 10:00 AM and closes at 2:00 AM, sales after midnight may still belong to the previous business day.

That is why orders and payments must use `business_date_id`.

## 6. Order lifecycle

Recommended order statuses:

```text
draft
open
sent_to_kitchen
partially_ready
ready
served
cancelled
closed
```

Meaning:

- `draft`: order started but not yet confirmed
- `open`: order is active and can receive items
- `sent_to_kitchen`: at least one item has been sent to kitchen
- `partially_ready`: some kitchen items are ready
- `ready`: all kitchen-required items are ready
- `served`: customer has received the order
- `cancelled`: order cancelled before completion
- `closed`: order fully paid/settled

Do not allow payment on `draft` unless the business explicitly wants pre-payment. First release should keep it simple:

```text
draft/open/sent_to_kitchen/ready/served -> payable
cancelled -> not payable
closed -> no further edits except controlled refund/void workflows
```

## 7. Order item lifecycle

Recommended item statuses:

```text
new
sent
preparing
ready
served
cancelled
voided
```

Meaning:

- `new`: item added but not yet sent to kitchen
- `sent`: item sent to kitchen
- `preparing`: kitchen started preparing
- `ready`: kitchen marked item ready
- `served`: waiter/cashier marked item served
- `cancelled`: cancelled before kitchen production
- `voided`: removed after stronger control, usually requiring permission/approval

Important rule:

Once an item is sent to kitchen, changing quantity or price should not be a simple update. It should create a controlled adjustment, void, or additional item.

Reason:

Kitchen, inventory, audit and accounting must remain explainable.

## 8. Frontend payload design

Order creation should accept arrays because a cashier/waiter normally sends multiple items at once.

Example request:

```http
POST /api/v1/orders
Authorization: Bearer {token}
X-Tenant-UUID: {tenant_uuid}
X-Branch-UUID: {branch_uuid}
```

```json
{
  "sales_channel_uuid": "uuid",
  "service_type_uuid": "uuid",
  "counter_uuid": "uuid",
  "dining_table_uuid": "uuid",
  "customer_uuid": null,
  "notes": "No onions",
  "items": [
    {
      "branch_menu_item_uuid": "uuid",
      "quantity": "2.0000",
      "notes": "Well done",
      "modifiers": [
        {
          "modifier_option_uuid": "uuid",
          "quantity": "2.0000"
        }
      ]
    },
    {
      "branch_menu_item_uuid": "uuid",
      "quantity": "1.0000",
      "modifiers": []
    }
  ]
}
```

Why array:

A POS screen may add burger, chips, soda and modifiers in one click. Sending one request per item is slower and creates more race conditions.

## 9. Recommended endpoints

Reference APIs:

```text
GET /api/v1/sales-channels
GET /api/v1/service-types
GET /api/v1/payment-methods
GET /api/v1/counters
GET /api/v1/dining-areas
GET /api/v1/dining-tables
```

Order APIs:

```text
GET    /api/v1/orders
POST   /api/v1/orders
GET    /api/v1/orders/{order_uuid}
PATCH  /api/v1/orders/{order_uuid}
POST   /api/v1/orders/{order_uuid}/items
PATCH  /api/v1/orders/{order_uuid}/items/{order_item_uuid}
POST   /api/v1/orders/{order_uuid}/send-to-kitchen
POST   /api/v1/orders/{order_uuid}/cancel
POST   /api/v1/orders/{order_uuid}/serve
POST   /api/v1/orders/{order_uuid}/close
```

Payment APIs:

```text
POST /api/v1/orders/{order_uuid}/payment-attempts
POST /api/v1/orders/{order_uuid}/payments
GET  /api/v1/orders/{order_uuid}/payments
POST /api/v1/payments/{payment_uuid}/refunds
```

Receipt APIs:

```text
POST /api/v1/orders/{order_uuid}/receipts
GET  /api/v1/receipts/{receipt_uuid}
POST /api/v1/receipts/{receipt_uuid}/void
POST /api/v1/receipts/{receipt_uuid}/print
```

Print APIs:

```text
GET  /api/v1/print-devices
POST /api/v1/print-devices
PATCH /api/v1/print-devices/{print_device_uuid}

GET  /api/v1/print-templates
POST /api/v1/print-templates
PATCH /api/v1/print-templates/{print_template_uuid}

GET  /api/v1/print-jobs
POST /api/v1/print-jobs/{print_job_uuid}/claim
POST /api/v1/print-jobs/{print_job_uuid}/complete
POST /api/v1/print-jobs/{print_job_uuid}/fail
```

## 10. Payments

### 10.1 Payment method

`payment_methods` stores how money is received.

Supported method types:

```text
cash
card
mobile_money
bank_transfer
aggregator
voucher
wallet
```

Examples:

```text
Cash
M-Pesa
Airtel Money
Card POS
Bank Transfer
Aggregator Wallet
```

### 10.2 Cash payment

Cash payment is usually synchronous.

Example:

```json
{
  "payment_method_uuid": "cash-method-uuid",
  "amount": "50000.00",
  "cash_received_amount": "50000.00"
}
```

The system records:

```text
payments.amount = 50000
payments.change_amount = 0
payments.status = completed
```

If order total is `45000` and cashier receives `50000`, then:

```text
amount applied to order = 45000
change amount = 5000
```

### 10.3 Card/mobile-money/aggregator payment

Provider payment is normally two-stage:

```text
payment_attempts
provider_transactions
payments
```

Example flow:

```text
Cashier starts M-Pesa payment
System creates payment_attempt with idempotency key
Provider request is sent
Provider responds pending/completed/failed
Webhook arrives
System stores webhook_receipt short-term
System normalizes provider result into provider_transactions
If successful, system creates payment
Order paid amount is updated
Receipt can be issued
```

`payment_attempts` protects the system from duplicate payment requests.

Example:

```text
branch_id = 4
idempotency_key = POS-A8F9-ORDER-123
```

The database has a unique constraint on:

```text
branch_id + idempotency_key
```

So if the cashier taps “Pay” twice, the backend does not create two real provider charges.

### 10.4 Webhook storage rule

`webhook_receipts` is not the permanent business record.

It is only an idempotent intake table:

```text
Receive provider webhook
Store raw payload temporarily
Check duplicate event_id
Process into provider_transactions/payments/refunds
Mark processed
Archive/delete raw payload later
```

Do not keep huge raw webhook JSON forever.

Recommended:

- keep raw payload for short retention window
- normalize important fields
- archive old raw payload
- keep provider transaction summary longer

## 11. Receipts

A receipt is issued after payment or settlement.

`receipts` stores:

- order reference
- receipt number
- total amount
- issued by user
- issued time
- status

Example:

```text
Order total: 45,000 TZS
Payment: cash 45,000 TZS
Receipt: REC-2026-01
Status: issued
```

Important distinction:

```text
Bill = request for payment before final settlement
Receipt = proof after payment
```

So printing a bill should not mark the order paid.

## 12. Printing and thermal receipt devices

Yes, thermal receipts and branch-specific printers are considered.

`print_devices` stores branch printers.

Examples:

```text
Main cashier thermal printer
Kitchen hot line printer
Bar printer
Label printer
```

Supported device types:

```text
thermal_receipt
kitchen_printer
bar_printer
label_printer
```

Supported connection types:

```text
network
usb
bluetooth
browser_agent
```

Important technical point:

A cloud Laravel server cannot reliably print directly to a USB printer inside a restaurant branch.

Correct design:

```text
Laravel creates print_jobs
Reverb notifies the branch browser/print agent
Branch device pulls/claims pending print job
Branch device prints locally
Branch device reports printed/failed
```

This is reliable because failed printing does not destroy the receipt/payment record. It only leaves a failed `print_jobs` row that can be retried.

## 13. Print templates

`print_templates` stores the receipt/bill layout.

Examples:

- 58mm receipt
- 80mm receipt
- kitchen ticket
- refund receipt
- payment confirmation

Template can be:

```text
company-wide
branch-specific override
```

Rule:

Use company template by default. Use branch template only if branch has special printer/paper/layout requirement.

## 14. Print job lifecycle

Recommended print job statuses:

```text
pending
printing
printed
failed
cancelled
```

Example:

```text
Cashier clicks Print Receipt
System creates print_jobs row with status pending
System writes realtime outbox event
Worker broadcasts to branch print channel
Browser print agent receives notification
Agent claims print job
Agent prints on thermal printer
Agent marks job printed
```

If printer is off:

```text
print_jobs.status = failed
failure_reason = Printer offline
```

Payment stays completed. Receipt stays issued. Only printing failed.

## 15. Reverb and outbox rule

Do not broadcast inside a database transaction.

Bad pattern:

```text
DB transaction starts
Order is created
Broadcast event is sent immediately
Transaction later fails
Frontend already received fake order update
```

Correct pattern:

```text
DB transaction starts
Order is created
Kitchen ticket is created
Outbox event is inserted
Transaction commits
Queue worker reads outbox event
Worker broadcasts through Reverb
Outbox event marked published
```

Why this matters:

The frontend, kitchen screen and print agent should only see committed facts. If a transaction rolls back, no realtime message should be published.

Recommended outbox events for this module:

```text
order.created
order.updated
order.sent_to_kitchen
kitchen_ticket.created
kitchen_ticket.updated
payment.completed
receipt.issued
print_job.created
print_job.failed
```

## 16. Query and performance rules

All list endpoints must use simple pagination.

Default:

```text
per_page = 15
max per_page = 100
```

Frequent filters:

Orders:

```text
branch_id
business_date_id
shift_session_id
status
opened_at
customer_id
```

Kitchen tickets:

```text
branch_id
kitchen_section_id
status
opened_at
```

Payments:

```text
branch_id
business_date_id
payment_method_id
status
paid_at
```

Print jobs:

```text
branch_id
print_device_id
status
requested_at
```

Avoid:

```text
WHERE name LIKE '%burger%'
```

Prefer indexed server search:

```text
normalized/searchable name columns
prefix search where possible
PostgreSQL trigram index later if full text search is required
```

Avoid N+1:

When loading an order details page, eager-load:

```text
items
items.modifiers
customer
table
payments
receipts
```

Do not eager-load huge histories by default. Load logs separately with pagination.

## 17. Transactions and locking

Use database transactions for:

- creating order with items/modifiers
- sending order to kitchen
- applying discount
- recording payment
- issuing receipt
- refunding payment
- creating print job from receipt

Use row locking when recalculating order totals or paid amount:

```text
lock order row
validate status
insert/update child rows
recalculate totals
commit
```

Reason:

Two cashier devices must not update the same order total at the same time and produce incorrect paid amount.

## 18. Permissions

Recommended permissions:

```text
orders.view
orders.create
orders.update
orders.cancel
orders.void_item
orders.send_to_kitchen
orders.serve
orders.close

payments.view
payments.create
payments.refund
payments.void

receipts.view
receipts.issue
receipts.void
receipts.print

print_devices.view
print_devices.manage
print_templates.view
print_templates.manage
print_jobs.view
print_jobs.manage
```

Branch scoping still applies. A user only sees branches allowed by their permissions/team assignment.

## 19. Audit logging

Do not log every small POS update.

Log critical events only:

- order cancelled
- order item voided
- discount applied/removed
- payment completed
- payment voided
- refund requested/approved/completed
- receipt voided
- print job repeatedly failed
- manual price override
- cash variance at shift close

Do not store huge before/after JSON for every order item quantity change.

## 20. Numbering correction to watch

We decided human-readable numbers use short padding:

```text
ORD-2026-01
REC-2026-01
SHIFT-20260824-01
EXP-2026-01
```

Important:

If numbers reset per branch, global unique constraints can conflict.

Example:

```text
Branch A creates ORD-2026-01
Branch B also creates ORD-2026-01
```

If `orders.order_number` is globally unique inside the tenant database, Branch B will fail.

There are two safe options:

Option A:

```text
Make order/receipt numbers tenant-wide, not branch-reset.
```

Option B:

```text
Keep branch-reset numbers but make uniqueness branch-scoped.
```

Recommended for POS:

```text
orders: unique(branch_id, order_number)
kitchen_tickets: unique(branch_id, ticket_number)
shift_sessions: unique(branch_id, shift_number)
print jobs: use uuid for technical identity
```

For receipts, because `receipts` currently links to `orders` but does not store `branch_id`, either:

```text
make receipt numbers tenant-wide
```

or:

```text
add branch_id to receipts and use unique(branch_id, receipt_number)
```

For first release, I recommend tenant-wide receipt numbers and branch-scoped shift/order/kitchen ticket numbers.

## 21. Complexity classification

Simple CRUD:

- sales channels
- service types
- counters
- dining areas
- dining tables
- payment methods
- print devices
- print templates

Service logic required:

- order creation with item/modifier arrays
- order total recalculation
- send to kitchen
- payment attempt creation
- provider callback processing
- payment completion
- receipt issuing
- print job claim/complete/fail
- refund

Queue required:

- provider payment confirmation if asynchronous
- webhook processing
- print job notification
- realtime broadcasting from outbox
- heavy receipt/report export

No queue required:

- normal cash payment record, unless it triggers accounting/report tasks after commit
- simple list/details endpoint

## 22. Backend implementation services

Recommended services:

```text
OrderService
OrderPricingService
KitchenDispatchService
PaymentService
PaymentProviderService
WebhookProcessingService
ReceiptService
PrintJobService
RealtimeOutboxService
```

Do not create service methods for every simple CRUD action. Controllers can call simple model/repository operations when there is no business rule.

## 23. Frontend flow

Cashier POS screen:

```text
Load branch menu
Open active shift
Create/open order
Add item arrays
Send items to kitchen
Receive realtime kitchen/order status
Take payment
Issue receipt
Create print job
Show print status
```

Kitchen screen:

```text
Subscribe to branch/kitchen section channel
Load open kitchen tickets
Mark item preparing
Mark item ready
Realtime update goes to cashier/waiter
```

Branch print agent:

```text
Subscribe to branch print channel
Poll pending print jobs
Claim one job
Print locally
Report success/failure
```

## 24. Standard responses

All controllers use:

```text
App\Support\ApiResponse
App\Support\ApiMessages
```

Example success:

```json
{
  "success": true,
  "message": "Order saved successfully.",
  "data": {},
  "errors": null
}
```

Example validation error:

```json
{
  "success": false,
  "message": "Please check the highlighted details and try again.",
  "data": null,
  "errors": {
    "items": ["At least one order item is required."]
  }
}
```

## 25. First release recommendation

First release should implement:

- POS reference data
- order create/update/details/list
- add/update/void items
- send to kitchen
- kitchen ticket generation
- cash payment
- manual mobile-money/card payment record
- payment attempt structure ready for provider integration
- receipt issuing
- bill/receipt print jobs
- branch print devices
- Reverb notification through outbox

Provider automation can be completed after tenant-specific providers are selected.

Do not delay the POS flow waiting for provider contracts. Build the payment integration tables and idempotency design now, then plug in providers per tenant later.
