# API Design 07: Kitchen Production, Service Execution, and Reservations

Project: Restaurant POS ERP  
Backend: Laravel, PHP 8.3, PostgreSQL, database-per-tenant multitenancy  
API version: v1  
Date: 2026-08-25

## 1. Purpose

This design explains what happens after an order is created and sent to production.

Main flow:

```text
Cashier/waiter sends order items to kitchen
System groups items by kitchen section
Kitchen tickets are created
Kitchen screen displays active tickets
Kitchen staff mark items preparing/ready
Waiter/cashier sees realtime updates
Items are served
Order status updates correctly
```

This design also covers reservations because table booking affects service execution, dining table availability, and branch operations.

## 2. What this design depends on

This module depends on:

- Design 01: authentication and tenant context
- Design 03: branch, business date, shift, numbering
- Design 04: tenant users, roles, permissions, branch access
- Design 05: branch menu items and kitchen section routing
- Design 06: POS orders, payments, receipts, printing

Kitchen production should not invent its own order state. It must use `orders`, `order_items`, `branch_menu_items`, and `kitchen_sections`.

## 3. Route placement

Tenant APIs belong in:

```text
routes/api/v1/web.php
```

Recommended source folders:

```text
app/Http/Controllers/Web/Kitchen
app/Http/Controllers/Web/Reservations
app/Http/Requests/Web/Kitchen
app/Http/Requests/Web/Reservations
app/Http/Resources/Web/Kitchen
app/Http/Resources/Web/Reservations
app/Services/Kitchen
app/Services/Reservations
```

No `Api/V1` folder is needed. Versioning stays in route files and URL prefixes.

## 4. Tables used

Tenant tables:

- `kitchen_sections`
- `branch_menu_items`
- `orders`
- `order_items`
- `order_item_modifiers`
- `kitchen_tickets`
- `kitchen_ticket_items`
- `dining_areas`
- `dining_tables`
- `reservations`
- `reservation_tables`
- `tenant_users`
- `print_jobs`

Realtime/outbox support tables are required if we implement outbox-based broadcasting.

## 5. Kitchen section

`kitchen_sections` represents a preparation station inside a branch.

Examples:

- Hot Kitchen
- Grill
- Bar
- Pastry
- Pizza Station
- Coffee Station

Important:

Kitchen section routing is branch-specific.

Reason:

One branch may prepare burgers in `Hot Kitchen`, while another branch may prepare them in `Grill`.

The correct relationship is:

```text
branch_menu_items -> kitchen_section_id
```

That means a sellable menu item can route differently per branch.

Example:

```text
Branch A:
Beef Burger -> Grill

Branch B:
Beef Burger -> Hot Kitchen
```

This is why kitchen routing should not live only on the company-level `menu_items` table.

## 6. Kitchen ticket

`kitchen_tickets` is the production document.

It answers:

```text
What should this kitchen section prepare?
```

Example:

```text
Ticket: KOT-20260825-01
Branch: Mikocheni
Kitchen section: Grill
Order: ORD-2026-01
Status: open
```

A single order can create multiple kitchen tickets.

Example order:

```text
2 Beef Burgers
1 Chicken Pizza
2 Fresh Juices
```

Routing:

```text
Beef Burgers -> Grill
Chicken Pizza -> Pizza Station
Fresh Juices -> Bar
```

System creates:

```text
Kitchen ticket 1: Grill
Kitchen ticket 2: Pizza Station
Kitchen ticket 3: Bar
```

This makes each kitchen screen clean. Bar staff should not see burger preparation unless required.

## 7. Kitchen ticket item

`kitchen_ticket_items` stores the actual items inside a ticket.

It links back to `order_items`.

Important columns:

- `kitchen_ticket_id`: the production ticket
- `order_item_id`: original POS order item
- `item_name_snapshot`: name at time of sending
- `quantity`: quantity to prepare
- `status`: production item status
- `started_at`: when preparation starts
- `ready_at`: when item becomes ready

Why `item_name_snapshot` exists:

If the menu item name changes tomorrow, old kitchen tickets must still show what was prepared at that time.

Example:

```text
Today: Beef Burger
Next month menu renamed to Classic Beef Burger
Old kitchen ticket still says Beef Burger
```

That is not bad redundancy. It is historical snapshot data.

## 8. Kitchen ticket status

Recommended ticket statuses:

```text
open
preparing
ready
completed
cancelled
```

Meaning:

- `open`: ticket created and waiting
- `preparing`: at least one item is being prepared
- `ready`: all active items are ready
- `completed`: kitchen work is finished and handed over
- `cancelled`: ticket cancelled before completion

Ticket status is derived mostly from ticket item statuses.

Example:

```text
All items queued -> ticket open
One item preparing -> ticket preparing
All items ready -> ticket ready
Items handed over -> ticket completed
```

Do not let frontend freely set any ticket status without validating item state.

## 9. Kitchen ticket item status

Recommended item statuses:

```text
queued
preparing
ready
served
cancelled
```

Meaning:

- `queued`: kitchen has not started
- `preparing`: kitchen started
- `ready`: kitchen completed preparation
- `served`: waiter/cashier delivered item to customer
- `cancelled`: item cancelled from production

Status changes must also update linked `order_items.status`.

Example:

```text
kitchen_ticket_items.status = ready
order_items.status = ready
```

This prevents the POS screen and kitchen screen from telling different stories.

## 10. Send-to-kitchen flow

Endpoint:

```text
POST /api/v1/orders/{order_uuid}/send-to-kitchen
```

Recommended request:

```json
{
  "item_uuids": [
    "uuid-1",
    "uuid-2",
    "uuid-3"
  ],
  "notes": "Rush order"
}
```

If `item_uuids` is omitted, the backend can send all unsent eligible items.

Backend steps:

```text
Start transaction
Lock order row
Validate branch access
Validate order status
Load unsent order items with branch_menu_item and kitchen_section
Group items by kitchen_section_id
Create one kitchen_tickets row per kitchen section
Create kitchen_ticket_items rows
Mark order_items as sent
Update order status to sent_to_kitchen
Write outbox events
Commit transaction
Worker broadcasts after commit
```

This is a complex service, not simple CRUD.

Recommended service:

```text
KitchenDispatchService
```

## 11. Why grouping by kitchen section matters

Without grouping:

```text
One large ticket contains burger, soda, pizza, cake
Every kitchen screen sees too much noise
```

With grouping:

```text
Grill sees burger only
Bar sees soda only
Pizza station sees pizza only
Pastry sees cake only
```

This improves speed and reduces staff mistakes.

## 12. Kitchen screen APIs

Recommended endpoints:

```text
GET  /api/v1/kitchen/tickets
GET  /api/v1/kitchen/tickets/{ticket_uuid}
POST /api/v1/kitchen/tickets/{ticket_uuid}/start
POST /api/v1/kitchen/tickets/{ticket_uuid}/items/{ticket_item_uuid}/start
POST /api/v1/kitchen/tickets/{ticket_uuid}/items/{ticket_item_uuid}/ready
POST /api/v1/kitchen/tickets/{ticket_uuid}/items/{ticket_item_uuid}/cancel
POST /api/v1/kitchen/tickets/{ticket_uuid}/complete
```

List filters:

```text
branch_uuid
kitchen_section_uuid
status
opened_from
opened_to
```

Use simple pagination for old/completed tickets. For live KDS screen, use limited active records:

```text
status in open/preparing/ready
order by opened_at asc
limit safe maximum
```

Recommended maximum active-ticket fetch:

```text
100 tickets per kitchen section
```

If a kitchen has more active tickets than that, the operation has a real backlog and should use filters or paging.

## 13. KDS display behavior

Kitchen display should show:

- ticket number
- order number
- service type
- dining table/counter
- elapsed waiting time
- item names
- modifiers
- item notes
- order notes relevant to kitchen
- status
- timer color

Example:

```text
KOT-20260825-01
Order: ORD-2026-01
Table: A4
Elapsed: 00:08

2x Beef Burger
  - No onions
  - Extra cheese

Status: Preparing
```

## 14. Timers and preparation minutes

`order_items` already has `preparation_minutes_snapshot`.

This should be copied from menu/branch menu configuration when the order item is created.

Example:

```text
Burger expected preparation: 12 minutes
Ticket opened at: 12:00
Now: 12:15
Expected ready at: 12:12
Delay: 3 minutes
```

Frontend can calculate timer display using:

```text
opened_at
started_at
ready_at
preparation_minutes_snapshot
```

Backend should not store a new row every minute just to update timers.

Reason:

With many tenants and many kitchen tickets, minute-by-minute timer writes would waste storage and create unnecessary database load.

## 15. Realtime updates

Use Laravel Reverb, but do not broadcast inside the transaction.

Correct pattern:

```text
Transaction writes business changes
Transaction writes outbox event
Transaction commits
Worker publishes event to Reverb
Frontend receives committed update
```

Kitchen events:

```text
kitchen_ticket.created
kitchen_ticket.started
kitchen_ticket.item_started
kitchen_ticket.item_ready
kitchen_ticket.ready
kitchen_ticket.completed
kitchen_ticket.cancelled
order.item_ready
order.ready
```

Recommended channels:

```text
tenant.{tenant_uuid}.branch.{branch_uuid}.kitchen
tenant.{tenant_uuid}.branch.{branch_uuid}.kitchen_section.{section_uuid}
tenant.{tenant_uuid}.branch.{branch_uuid}.orders
```

Do not create one broadcast channel per item unless there is a real reason. Branch and kitchen-section channels are enough for first release.

## 16. Printing kitchen tickets

Some branches use screens only. Some use kitchen printers.

The design supports both:

```text
KDS screen uses Reverb events
Kitchen printer uses print_jobs
```

When order is sent to kitchen:

```text
Create kitchen ticket
Create optional print job if kitchen section has printer enabled
Notify print agent through outbox/Reverb
```

Printer failure must not cancel the kitchen ticket.

If printer fails:

```text
kitchen ticket remains open
print_jobs.status = failed
staff can retry printing
```

## 17. Serving flow

Kitchen marks item ready. Waiter/cashier marks served.

Recommended endpoint:

```text
POST /api/v1/orders/{order_uuid}/items/{order_item_uuid}/serve
```

or for bulk serving:

```text
POST /api/v1/orders/{order_uuid}/serve-items
```

Example request:

```json
{
  "item_uuids": [
    "uuid-1",
    "uuid-2"
  ]
}
```

Why bulk endpoint:

A waiter may collect multiple ready items from the pass at once. Updating one item per request is unnecessary.

Backend rules:

```text
Only ready items can be served
Cancelled/voided items cannot be served
Serving updates order_items.status
Serving updates kitchen_ticket_items.status if linked
If all active items served, order can become served
```

## 18. Cancellation rules

Cancellation depends on production status.

Recommended rules:

```text
queued item -> can cancel with normal permission
preparing item -> requires stronger permission or approval
ready item -> requires manager permission/approval
served item -> cannot cancel; use refund/void workflow if needed
```

Reason:

Once kitchen has used ingredients or served food, cancellation affects stock, accountability and possibly accounting.

Critical cancellations should be logged using activity log.

## 19. Reservations

Reservations are part of service execution because they reserve tables and affect front-of-house operations.

`reservations` stores:

- branch
- customer reference or customer snapshot
- reservation number
- guest count
- reserved time window
- status
- notes

`reservation_tables` links reservations to one or more dining tables.

Example:

```text
Reservation: RSV-2026-01
Customer: Asha Mohamed
Guests: 6
Reserved: 2026-08-25 19:00 to 21:00
Tables: VIP 01 + VIP 02
Status: confirmed
```

Reservation statuses:

```text
pending
confirmed
seated
completed
cancelled
no_show
```

Meaning:

- `pending`: request received but not confirmed
- `confirmed`: restaurant accepted reservation
- `seated`: customer arrived and table assigned
- `completed`: dining completed
- `cancelled`: reservation cancelled
- `no_show`: customer did not arrive

## 20. Reservation APIs

Recommended endpoints:

```text
GET    /api/v1/reservations
POST   /api/v1/reservations
GET    /api/v1/reservations/{reservation_uuid}
PATCH  /api/v1/reservations/{reservation_uuid}
POST   /api/v1/reservations/{reservation_uuid}/confirm
POST   /api/v1/reservations/{reservation_uuid}/seat
POST   /api/v1/reservations/{reservation_uuid}/complete
POST   /api/v1/reservations/{reservation_uuid}/cancel
POST   /api/v1/reservations/{reservation_uuid}/no-show
```

Create reservation request:

```json
{
  "customer_uuid": null,
  "customer_name": "Asha Mohamed",
  "customer_phone": "+255712345678",
  "guest_count": 6,
  "reserved_from": "2026-08-25T19:00:00+03:00",
  "reserved_to": "2026-08-25T21:00:00+03:00",
  "dining_table_uuids": [
    "uuid-1",
    "uuid-2"
  ],
  "notes": "Birthday dinner"
}
```

Use array for `dining_table_uuids` because one reservation may need multiple tables.

## 21. Reservation overlap validation

The backend must prevent double-booking a table.

Conflict condition:

```text
same dining_table_id
reservation status in pending/confirmed/seated
time windows overlap
```

Overlap logic:

```text
existing.reserved_from < requested.reserved_to
AND
existing.reserved_to > requested.reserved_from
```

If `reserved_to` is nullable, calculate default duration in service logic, for example 2 hours.

This query must be indexed well through:

```text
reservation_tables.dining_table_id
reservations.branch_id, status, reserved_from
```

For stronger PostgreSQL design later, we can use exclusion constraints with range types, but first release can use transaction + lock + indexed overlap query.

## 22. Table seating flow

When reservation is seated:

```text
Validate reservation confirmed/pending
Validate table still available
Mark reservation seated
Optionally create/open order linked to dining table/customer
Write outbox event
Commit
Broadcast table/reservation update
```

This can be one endpoint:

```text
POST /api/v1/reservations/{reservation_uuid}/seat
```

Example request:

```json
{
  "create_order": true,
  "sales_channel_uuid": "uuid",
  "service_type_uuid": "uuid"
}
```

If `create_order = true`, response can include the opened order.

## 23. Complexity classification

Simple CRUD:

- kitchen sections
- basic reservation update before confirmation

Medium complexity:

- kitchen ticket list/details
- start ticket
- mark item preparing/ready
- complete ticket
- reservation create/update with table assignment

High complexity:

- send order to kitchen
- reservation seating with order creation
- cancellation after production starts
- bulk serving items
- realtime outbox publishing

Queue required:

- Reverb broadcasting from outbox
- kitchen ticket printing
- reservation reminder notifications

No queue required:

- mark one kitchen item ready
- list active kitchen tickets
- create ordinary reservation, unless notification is sent

## 24. Transactions and locking

Use transactions for:

- send order to kitchen
- mark item ready and update parent ticket/order status
- complete ticket
- cancel ticket/item
- create reservation with tables
- seat reservation and create order

Use row locking for:

- order being dispatched
- kitchen ticket being status-updated
- reservation tables during overlap validation
- dining table seating operation

Reason:

Two devices may try to update the same ticket or seat the same table at the same time.

## 25. Query and performance rules

Kitchen screen queries must be fast.

Frequent filters:

```text
kitchen_tickets.branch_id
kitchen_tickets.kitchen_section_id
kitchen_tickets.status
kitchen_tickets.opened_at
kitchen_ticket_items.order_item_id
kitchen_ticket_items.status
reservations.branch_id
reservations.status
reservations.reserved_from
reservation_tables.dining_table_id
```

Avoid loading completed tickets on the live KDS screen by default.

Correct live KDS query:

```text
branch_id = current branch
status in open/preparing/ready
optional kitchen_section_id
order by opened_at asc
limit safe maximum
```

History query must use pagination.

Do not store timer ticks. Calculate elapsed time from timestamps.

Do not scan all tenant databases for reservation reminders every minute. Use landlord registry/scheduler entries for cross-tenant scheduled work.

## 26. Access control

Recommended permissions:

```text
kitchen_tickets.view
kitchen_tickets.start
kitchen_tickets.update_item_status
kitchen_tickets.cancel
kitchen_tickets.complete

reservations.view
reservations.create
reservations.update
reservations.confirm
reservations.seat
reservations.cancel
reservations.no_show

dining_tables.view
dining_tables.manage
```

Branch scoping applies.

Examples:

```text
Head chef can update tickets for assigned branch/kitchen section.
Waiter can view ready items and mark served.
Cashier can send order to kitchen.
Manager can cancel preparing/ready items.
```

## 27. Audit logging

Do not log every kitchen status change.

Log only critical events:

- sent to kitchen
- item cancelled after preparing
- item cancelled after ready
- ticket cancelled
- reservation cancelled
- reservation no-show
- table double-booking prevention failure if suspicious
- manual manager override

Normal `queued -> preparing -> ready` updates are operational events and can be kept in kitchen tables/outbox, not heavy audit logs.

## 28. Schema correction to watch

`kitchen_tickets.ticket_number` is currently globally unique.

If kitchen ticket numbers reset per branch, this can fail:

```text
Branch A creates KOT-20260825-01
Branch B creates KOT-20260825-01
```

Recommended correction:

```text
unique(branch_id, ticket_number)
```

`reservations.reservation_number` has the same issue.

Final decision:

```text
kitchen_tickets: branch-scoped numbers
reservations: branch-scoped numbers
```

So the schema must use:

```text
kitchen_tickets: unique(branch_id, ticket_number)
reservations: unique(branch_id, reservation_number)
```

Both tables must keep `branch_id` as a required foreign key because kitchen production and table reservations are branch operations, not company-wide abstract records.

## 29. First release recommendation

Implement first:

- kitchen section management
- branch menu item kitchen routing
- send order to kitchen
- active kitchen ticket list
- mark item preparing
- mark item ready
- mark item served
- complete kitchen ticket
- Reverb updates through outbox
- kitchen ticket print job support
- reservation create/list/details
- reservation confirm/seat/cancel/no-show
- reservation table overlap validation

Do not implement advanced kitchen analytics in first release.

Advanced later:

- prep-time performance reports
- chef/station workload analytics
- delayed-ticket alert rules
- reservation deposit payments
- waiting list
- customer-facing reservation portal
