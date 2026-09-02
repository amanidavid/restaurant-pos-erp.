# API Design 20: Customer-Facing QR Ordering, Web Menu, Reservations, Order Tracking, and Loyalty Hooks

Project: Restaurant POS ERP  
Backend: Laravel, PHP 8.3, PostgreSQL, database-per-tenant multitenancy  
API version: v1  
Date: 2026-08-25

## 1. Purpose

This design covers customer-facing flows:

```text
QR menu
guest ordering
customer profile
online ordering
table ordering
takeaway/pickup/delivery
reservation request
payment handoff
order tracking
loyalty hooks
aggregator order intake
```

Customer-facing APIs must be simple, fast and safe. Customers should not need training or app installation.

## 2. Route placement

Customer/public APIs should be separate from staff web APIs.

Recommended route file:

```text
routes/api/v1/customer.php
```

If you prefer fewer route files, put under:

```text
routes/api/v1/web.php
```

with prefix:

```text
/customer
```

Recommended folders:

```text
app/Http/Controllers/Customer
app/Http/Requests/Customer
app/Http/Resources/Customer
app/Services/CustomerOrdering
```

No `Api/V1` folder is needed.

## 3. Tables used

Existing tenant tables:

- `customers`
- `customer_groups`
- `sales_channels`
- `service_types`
- `branches`
- `dining_tables`
- `branch_menu_items`
- `menu_items`
- `menu_categories`
- `modifier_groups`
- `modifier_options`
- `orders`
- `order_items`
- `order_item_modifiers`
- `payment_attempts`
- `payments`
- `receipts`
- `reservations`
- `reservation_tables`
- `aggregator_orders`
- `aggregator_order_events`
- `notification_messages`

Optional later tables:

- `customer_accounts`
- `customer_otp_tokens`
- `loyalty_programs`
- `loyalty_accounts`
- `loyalty_transactions`
- `customer_addresses`

## 4. Customer record

`customers` stores tenant customer profile.

Current fields:

```text
company
customer group
name
email
phone
status
```

Customer can be optional for guest order.

Example:

```text
Guest scans QR
Enters phone only
Order is created with customer snapshot or customer record
```

For first release, avoid forcing customer account registration.

## 5. QR menu flow

QR code should identify:

```text
tenant
branch
optional dining table
```

Example URL:

```text
https://app.example.com/menu/{tenant_uuid}/{branch_uuid}?table={table_uuid}
```

Flow:

```text
Customer scans QR
Frontend loads branch menu
Only visible/available branch_menu_items are shown
Customer selects items/modifiers
Customer enters name/phone if needed
Order is submitted
Order appears in POS/kitchen flow
```

## 6. Branch menu endpoint

Endpoint:

```text
GET /api/v1/customer/branches/{branch_uuid}/menu
```

Returns:

```text
categories
visible available menu items
prices
images
modifier groups/options
preparation time
tax-inclusive display rules
```

Use indexed filter:

```text
branch_menu_items.branch_id
is_available
is_visible
```

Do not expose hidden/back-office items.

## 7. Customer order creation

Endpoint:

```text
POST /api/v1/customer/orders
```

Payload should accept item arrays.

Example:

```json
{
  "tenant_uuid": "uuid",
  "branch_uuid": "uuid",
  "dining_table_uuid": "uuid",
  "service_type": "dine_in",
  "customer": {
    "name": "Asha Mohamed",
    "phone": "+255712345678"
  },
  "items": [
    {
      "branch_menu_item_uuid": "uuid",
      "quantity": "2.0000",
      "notes": "No onions",
      "modifiers": [
        {
          "modifier_option_uuid": "uuid",
          "quantity": "2.0000"
        }
      ]
    }
  ]
}
```

Customer orders should use:

```text
sales_channel.type = online
service_type = dine_in/takeaway/delivery/pickup
```

## 8. Staff confirmation policy

For first release, recommended:

```text
customer order enters draft/open pending staff confirmation
staff confirms/sends to kitchen
```

Alternative:

```text
paid online order auto-sends to kitchen
```

This is faster but riskier if menu availability/payment provider has issues.

Recommended first release:

```text
Unpaid QR order -> staff review
Paid QR order -> can auto-send if branch setting allows
```

## 9. Customer payment handoff

Payment can be:

```text
pay at counter
mobile money/card online
aggregator paid externally
```

Online provider flow uses:

```text
payment_attempts
provider_transactions
payments
webhook_receipts
```

Do not trust frontend callback alone.

Provider webhook confirms payment.

## 10. Order tracking

Endpoint:

```text
GET /api/v1/customer/orders/{order_uuid}/tracking
```

Return simple statuses:

```text
received
accepted
preparing
ready
served/completed
cancelled
```

Do not expose internal staff names, financial details or audit logs.

## 11. Reservations

Customer reservation endpoint:

```text
POST /api/v1/customer/reservations
```

Payload:

```json
{
  "branch_uuid": "uuid",
  "customer_name": "Asha Mohamed",
  "customer_phone": "+255712345678",
  "guest_count": 4,
  "reserved_from": "2026-08-25T19:00:00+03:00",
  "reserved_to": "2026-08-25T21:00:00+03:00",
  "notes": "Window seat if possible"
}
```

Reservation can start as:

```text
pending
```

Staff confirms it.

Do not let customer directly choose table unless branch allows it.

## 12. Aggregator orders

`aggregator_orders` stores orders from external delivery/ordering platforms.

Flow:

```text
Provider webhook/order API sends order
System stores aggregator_order
System checks idempotency by integration_account + external_order_reference
Staff accepts/rejects
If accepted, system creates internal order
Status updates sync back to aggregator when provider supports it
```

Statuses:

```text
received
accepted
rejected
preparing
ready
dispatched
completed
cancelled
```

## 13. Loyalty hooks

Loyalty is not fully modeled yet.

For first release:

```text
prepare hooks, do not block POS/order design
```

Future tables:

```text
loyalty_programs
loyalty_accounts
loyalty_transactions
loyalty_rewards
```

Possible rules:

```text
earn points after paid order
redeem points as discount
track loyalty balance by customer
```

Do not store loyalty points in `customers` only. Use transaction ledger later.

## 14. API endpoints

Customer public:

```text
GET  /api/v1/customer/branches/{branch_uuid}/menu
POST /api/v1/customer/orders
GET  /api/v1/customer/orders/{order_uuid}/tracking
POST /api/v1/customer/orders/{order_uuid}/payment-attempts
POST /api/v1/customer/reservations
GET  /api/v1/customer/reservations/{reservation_uuid}
```

Aggregator:

```text
POST /api/v1/webhooks/aggregators/{provider_key}/orders
POST /api/v1/webhooks/aggregators/{provider_key}/events
```

Staff online order management:

```text
GET  /api/v1/orders?sales_channel=online
POST /api/v1/orders/{order_uuid}/accept
POST /api/v1/orders/{order_uuid}/reject
```

## 15. Complexity

Simple:

- public menu read
- order tracking read

Medium:

- customer reservation create
- customer profile lookup/create
- online order create

High:

- online payment handoff
- aggregator webhook idempotency
- auto-send-to-kitchen policy
- loyalty earning/redemption

Queue required:

- aggregator webhook processing
- payment webhook processing
- customer notifications
- loyalty posting later

## 16. Security and throttling

Customer endpoints are public-facing.

Require:

```text
rate limiting
captcha/risk controls later
provider webhook signature validation
no staff data exposure
no hidden menu data exposure
idempotency key for order/payment submission
```

## 17. Schema correction to watch

`orders.order_number` is currently globally unique.

Because branch numbering is used elsewhere, recommended correction:

```text
orders: unique(branch_id, order_number)
```

Otherwise two branches can conflict on:

```text
ORD-2026-01
```

## 18. First release

Implement:

- QR menu
- customer guest order
- customer reservation request
- order tracking
- payment attempt skeleton
- aggregator order intake skeleton
- customer notifications

Later:

- customer account login
- loyalty ledger
- delivery address book
- online payment full automation
- aggregator menu sync
