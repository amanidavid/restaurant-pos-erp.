# API Design 22: Business Day, Shift Cash Control, Opening Float, Closing Count, Variance, and Petty Cash

Project: Restaurant POS ERP  
Backend: Laravel, PHP 8.3, PostgreSQL, database-per-tenant multitenancy  
API version: v1  
Date: 2026-08-25

## 1. Purpose

This design explains cash control for restaurant operations:

```text
open business day
open shift/session
record opening float
record cash sales/refunds/in/out/safe drops
calculate expected cash
physically count cash
calculate variance
close/approve reconciliation
```

Core rule:

```text
System calculates expected cash from recorded cash movements.
Staff enters counted physical cash only when physical cash exists.
```

## 2. Route placement

Tenant APIs:

```text
routes/api/v1/web.php
```

Recommended folders:

```text
app/Http/Controllers/Web/CashControl
app/Http/Requests/Web/CashControl
app/Http/Resources/Web/CashControl
app/Services/CashControl
```

## 3. Tables used

Tenant tables:

- `business_dates`
- `shift_sessions`
- `cash_accounts`
- `cash_movements`
- `business_day_cash_reconciliations`
- `shift_cash_reconciliations`
- `cash_count_lines`
- `payments`
- `refunds`
- `expense_payments`
- `supplier_payments`

## 4. Cash account

Cash account identifies where cash is controlled.

Types from finance design:

```text
cash_drawer
petty_cash
safe
cash_in_transit
```

Examples:

```text
Main Cash Drawer
Bar Cash Drawer
Branch Safe
Petty Cash
```

Petty cash is a cash account. Expense is what money is spent on.

## 5. Business day cash reconciliation

`business_day_cash_reconciliations` controls cash for a full branch business date and cash account.

Unique:

```text
business_date_id + cash_account_id
```

Meaning:

One cash account can have one reconciliation per business date.

Statuses:

```text
open
closing
closed
approved
```

## 6. Shift cash reconciliation

`shift_cash_reconciliations` controls cash for one POS shift/session and cash account.

Unique:

```text
shift_session_id + cash_account_id
```

Use this when cashier accountability is per shift/till.

Example:

```text
Cashier A opens shift
Opening float: 100,000
Cash sales: 800,000
Cash refunds: 20,000
Safe drop: 500,000
Expected cash: 380,000
Counted cash: 378,000
Variance: -2,000
```

## 7. Opening float

Opening float is normally entered manually because it is physical cash placed into drawer.

Example:

```text
Manager gives cashier 100,000 TZS as change float.
```

System records:

```text
opening_float_amount = 100,000
cash_movements.reason = opening_float
```

If branch has no physical cash:

```text
opening_float_amount = 0
```

## 8. Expected cash

Expected cash is calculated from system records.

Formula:

```text
expected_cash =
opening_float
+ cash_sales
- cash_refunds
+ cash_in
- cash_out
- safe_drop
```

Do not ask staff to manually type expected cash.

System recalculates expected cash when closing.

## 9. Counted cash

Counted cash is physical money counted by staff.

It is manual because the system cannot know what is physically inside drawer/safe.

If there is no physical cash:

```text
counted_cash_amount = 0
variance = 0
```

or no cash reconciliation is required if the cash account is not used.

## 10. Cash count lines

`cash_count_lines` stores denomination count.

Example:

```text
10,000 x 20 = 200,000
5,000 x 10 = 50,000
1,000 x 30 = 30,000
```

The database checks:

```text
line_amount = denomination_value * quantity
```

Count lines belong to exactly one:

```text
business_day_cash_reconciliation
or
shift_cash_reconciliation
```

## 11. Variance

Variance is:

```text
counted_cash_amount - expected_cash_amount
```

Examples:

```text
Expected 380,000
Counted 378,000
Variance -2,000
```

```text
Expected 380,000
Counted 385,000
Variance +5,000
```

Large variance should trigger approval/audit.

## 12. Safe drop

Safe drop is cash moved from drawer to safe.

Example:

```text
Cashier has 900,000 in drawer.
Manager removes 500,000 to safe.
```

Cash drawer:

```text
cash_out/safe_drop = 500,000
```

Safe:

```text
cash_in = 500,000
```

This must create cash movements for traceability.

## 13. Petty cash vs expense

Petty cash:

```text
where money is held
```

Expense:

```text
why money was spent
```

Example:

```text
Pay 20,000 for cleaning materials using petty cash.
```

Records:

```text
expense_claim/payment = Cleaning Materials 20,000
cash_account = Petty Cash
cash_movement.reason = expense_payment
amount = -20,000
```

## 14. Closing flow

Recommended:

```text
Cashier starts closing
System locks reconciliation
System recalculates expected cash from cash_movements
Cashier enters denomination count
System sums counted cash
System calculates variance
If variance within tolerance: close
If variance above threshold: require manager approval
Write audit event
```

## 15. API endpoints

Business day:

```text
POST /api/v1/business-dates/{business_date_uuid}/cash-reconciliations
GET  /api/v1/business-day-cash-reconciliations
GET  /api/v1/business-day-cash-reconciliations/{uuid}
POST /api/v1/business-day-cash-reconciliations/{uuid}/start-closing
POST /api/v1/business-day-cash-reconciliations/{uuid}/count
POST /api/v1/business-day-cash-reconciliations/{uuid}/close
POST /api/v1/business-day-cash-reconciliations/{uuid}/approve
```

Shift:

```text
POST /api/v1/shift-sessions/{shift_uuid}/cash-reconciliations
GET  /api/v1/shift-cash-reconciliations
GET  /api/v1/shift-cash-reconciliations/{uuid}
POST /api/v1/shift-cash-reconciliations/{uuid}/start-closing
POST /api/v1/shift-cash-reconciliations/{uuid}/count
POST /api/v1/shift-cash-reconciliations/{uuid}/close
POST /api/v1/shift-cash-reconciliations/{uuid}/approve
```

## 16. Complexity

Simple:

- list/details

Medium:

- open reconciliation
- enter count lines

High:

- close reconciliation
- recalculate expected cash
- variance approval
- safe drop
- cash transfer between drawer/safe

Queue:

- not needed for ordinary close
- use notification/outbox after commit

## 17. Transactions and locking

Use transactions for:

- open reconciliation
- close reconciliation
- approve reconciliation
- safe drop
- petty cash payment

Lock:

- reconciliation row
- cash account if updating movements
- shift/business date during close

## 18. Audit

Log:

```text
cash reconciliation closed
variance above threshold
safe drop
manual cash in/out
petty cash payment
manager approval
```

Do not log every cash count line with huge JSON.

## 19. First release

Implement:

- shift cash reconciliation
- business day cash reconciliation
- denomination count lines
- expected cash calculation
- variance calculation
- approval trigger
- petty cash cash movement connection
- audit events

Later:

- cash drawer hardware integration
- denomination templates
- cash pickup/bank deposit workflow
