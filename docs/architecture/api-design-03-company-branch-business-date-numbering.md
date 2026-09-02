# API Design 03: Company, Branch, Business Date, and Numbering

Project: Restaurant POS ERP  
Backend: Laravel, PHP 8.3, PostgreSQL, database-per-tenant multitenancy  
API version: v1  
Date: 2026-08-24

## 1. Purpose

This design defines the first tenant operational foundation after registration/provisioning.

These APIs come before POS/order/payment APIs because most tenant operations depend on:

```text
company
  -> branches
  -> branch configuration
  -> open business date
  -> document numbering
```

Without an active branch and open business date, orders, shifts, receipts, stock movements, payments, expenses and reports become ambiguous.

## 2. Scope

Included:

- company profile;
- branch profile;
- branch list and branch details;
- branch configuration;
- opening business day;
- closing business day;
- business date status;
- number sequence setup;
- atomic number allocation;
- cash/petty-cash boundary explanation.

Not fully included:

- full finance/accounting setup;
- expense claim workflow;
- petty cash top-up/return workflow;
- cashier drawer sessions;
- detailed cash reconciliation UI;
- POS orders and payments.

Those are later designs, but this design must prepare their foundation.

## 3. Route placement

Tenant/business APIs belong in:

```text
routes/api/v1/web.php
```

URL versioning remains in routes, not in controller/request/resource namespaces.

Recommended source folders:

```text
app/Http/Controllers/Web/Foundation
app/Http/Requests/Web/Foundation
app/Http/Resources/Web/Foundation
app/Services/Foundation
app/Services/Numbering
```

## 4. Tenant context requirement

All endpoints in this design require:

```text
Authorization: Bearer <jwt>
X-Tenant-Id: <tenant-uuid>
Accept: application/json
```

Branch-scoped endpoints also require:

```text
X-Branch-Id: <branch-uuid>
```

Middleware must:

1. authenticate JWT;
2. verify token tenant equals header/domain tenant;
3. connect tenant database;
4. resolve local `tenant_users`;
5. require branch header only for branch-scoped actions;
6. verify `tenant_user_branches`;
7. set Spatie team id to internal `branches.id`;
8. enforce exact permission.

## 5. Tables used

Tenant tables:

- `companies`
- `branches`
- `branch_configurations`
- `business_dates`
- `number_sequences`
- `number_sequence_allocations`
- `tenant_users`
- `tenant_user_branches`

Related but later:

- `cash_accounts`
- `cash_movements`
- `business_day_cash_reconciliations`
- `shift_sessions`
- `shift_cash_reconciliations`
- `expense_claims`
- `expense_payments`

## 6. Company profile endpoint

Endpoint:

```text
GET /api/v1/company
Route file: routes/api/v1/web.php
Controller: App\Http\Controllers\Web\Foundation\CompanyProfileController
Permission: company.view
Complexity: simple
Service required: optional
Transaction required: no
Pagination: no
```

Response:

```json
{
  "success": true,
  "message": "Company details loaded successfully.",
  "data": {
    "uuid": "018f8c33-company",
    "legal_name": "Kilimanjaro Restaurant",
    "trading_name": "Kilimanjaro Restaurant",
    "tin": null,
    "vrn": null,
    "country_code": "TZ",
    "timezone": "Africa/Dar_es_Salaam",
    "locale": "en",
    "base_currency_code": "TZS",
    "email": "info@kilimanjaro.test",
    "phone": "+255700000001",
    "status": "active"
  },
  "errors": null
}
```

## 7. Update company profile

Endpoint:

```text
PATCH /api/v1/company
Route file: routes/api/v1/web.php
Controller: App\Http\Controllers\Web\Foundation\CompanyProfileController
Request: App\Http\Requests\Web\Foundation\UpdateCompanyProfileRequest
Permission: company.update
Complexity: medium
Service required: yes
Transaction required: yes
Pagination: no
```

Request:

```json
{
  "legal_name": "Kilimanjaro Restaurants Ltd",
  "trading_name": "Kilimanjaro Restaurant",
  "tin": "123456789",
  "vrn": null,
  "email": "info@kilimanjaro.test",
  "phone": "+255700000001"
}
```

Rules:

- company profile exists as one row per tenant;
- tenant owner can later split `business_name` into full legal/trading names;
- TIN/VRN can be added later here, not during registration;
- validate TIN/VRN only if supplied;
- keep `country_code=TZ`, `timezone=Africa/Dar_es_Salaam`, `base_currency_code=TZS` for first release;
- audit company tax/profile changes.

Why service?

Updating company legal/tax profile is sensitive. It affects receipts, tax setup and finance reports, so it should be audited and validated through a focused service.

## 8. Branch list

Endpoint:

```text
GET /api/v1/branches
Route file: routes/api/v1/web.php
Controller: App\Http\Controllers\Web\Foundation\BranchController
Permission: branches.view
Complexity: simple
Service required: optional
Pagination: simplePaginate
Default per_page: 15
Max per_page: 100
```

Filters:

- `status=active|inactive|closed`
- `search=<prefix>`

Search rule:

- search against `name_normalized`;
- prefer prefix search;
- avoid broad `%term%` on large tenants unless PostgreSQL trigram index is intentionally added.

Sort:

```text
name_normalized asc
```

Indexes:

- `branches(status, name_normalized)`
- `branches(company_id, name_normalized)` unique

Response:

```json
{
  "success": true,
  "message": "Branches loaded successfully.",
  "data": [
    {
      "uuid": "018f8c33-branch",
      "name": "Main Branch",
      "city": "Dar es Salaam",
      "timezone": "Africa/Dar_es_Salaam",
      "currency_code": "TZS",
      "status": "active"
    }
  ],
  "links": {
    "first": "...",
    "next": null,
    "prev": null
  },
  "meta": {
    "current_page": 1,
    "per_page": 15
  },
  "errors": null
}
```

Simple pagination is enough here. Branch lists are normally small, and total page count is not important.

## 9. Create branch

Endpoint:

```text
POST /api/v1/branches
Route file: routes/api/v1/web.php
Controller: App\Http\Controllers\Web\Foundation\BranchController
Request: App\Http\Requests\Web\Foundation\StoreBranchRequest
Permission: branches.create
Complexity: medium
Service required: yes
Transaction required: yes
Pagination: no
```

Request:

```json
{
  "name": "Mikocheni Branch",
  "email": "mikocheni@kilimanjaro.test",
  "phone": "+255700000002",
  "address_line_1": "Mikocheni",
  "city": "Dar es Salaam",
  "region": "Dar es Salaam",
  "opened_on": "2026-08-25",
  "configuration": {
    "business_day_opens_at": "08:00",
    "business_day_closes_at": "23:59",
    "allow_negative_stock": false
  }
}
```

Rules:

- normalize `name_normalized`;
- default timezone `Africa/Dar_es_Salaam`;
- default currency `TZS`;
- create branch and initial `branch_configurations` row in one transaction;
- do not automatically open a business date;
- do not automatically copy every menu/setting row; branch-specific menu configuration is a separate module.

Why service?

Branch creation also creates configuration and may later create default counters, cash accounts, devices and branch menu overrides. A service keeps this expandable without bloating the controller.

## 10. Update branch

Endpoint:

```text
PATCH /api/v1/branches/{branch_uuid}
Route file: routes/api/v1/web.php
Controller: App\Http\Controllers\Web\Foundation\BranchController
Request: App\Http\Requests\Web\Foundation\UpdateBranchRequest
Permission: branches.update
Complexity: medium
Service required: yes
Transaction required: yes
Pagination: no
```

Rules:

- verify branch belongs to current tenant/company;
- prevent duplicate branch name within company;
- status transitions should be controlled:
  - active -> inactive;
  - inactive -> active;
  - active/inactive -> closed;
- do not close a branch with open business date, open shift, open orders, unpaid bills or pending cash reconciliation.

Closing branch is more complex than editing name, so later it may become its own endpoint:

```text
POST /api/v1/branches/{branch_uuid}/close
```

## 11. Branch configuration

Endpoint:

```text
GET /api/v1/branches/{branch_uuid}/configuration
PATCH /api/v1/branches/{branch_uuid}/configuration
Route file: routes/api/v1/web.php
Controller: App\Http\Controllers\Web\Foundation\BranchConfigurationController
Permission: branch_configurations.view / branch_configurations.update
Complexity: medium
Service required: yes
Transaction required: yes for update
Pagination: no
```

Update request:

```json
{
  "business_day_opens_at": "08:00",
  "business_day_closes_at": "23:59",
  "allow_negative_stock": false,
  "effective_from": "2026-08-25"
}
```

Rules:

- configuration is versioned;
- do not update old active row destructively if effective date changes business behavior;
- close previous active configuration by setting `effective_to`;
- insert new version;
- do not allow effective date before last closed business date unless admin override is approved;
- business date open/close uses effective branch configuration for that date.

Why versioning?

If a branch changed closing time from `23:00` to `02:00`, old reports must still explain historical business dates using the configuration that was active then.

## 12. Business day concept

A business day is the restaurant's operational trading date, not always the calendar date.

Example:

```text
Branch closes at 02:00.
Order placed at 2026-08-25 01:15.
Operational business_date may still be 2026-08-24.
```

This is why POS, payments, shifts, receipts, stock movements and reports should link to `business_date_id`.

## 13. Current business day status

Endpoint:

```text
GET /api/v1/branches/{branch_uuid}/business-day/current
Route file: routes/api/v1/web.php
Controller: App\Http\Controllers\Web\Foundation\BusinessDateController
Permission: business_dates.view
Complexity: simple
Service required: optional
Transaction required: no
Pagination: no
```

Response:

```json
{
  "success": true,
  "message": "Business day details loaded successfully.",
  "data": {
    "uuid": "018f8c33-business-day",
    "branch_uuid": "018f8c33-branch",
    "business_date": "2026-08-25",
    "business_day_number": "BD-20260825-001",
    "status": "open",
    "opened_at": "2026-08-25T08:00:00+03:00",
    "planned_close_at": "2026-08-25T23:59:00+03:00",
    "opening_float_amount": "0.00",
    "expected_cash_amount": "0.00",
    "counted_cash_amount": null,
    "closing_variance_amount": null
  },
  "errors": null
}
```

If no business day is open:

```json
{
  "success": true,
  "message": "Business day details loaded successfully.",
  "data": null,
  "errors": null
}
```

## 14. Open business day

Endpoint:

```text
POST /api/v1/branches/{branch_uuid}/business-days/open
Route file: routes/api/v1/web.php
Controller: App\Http\Controllers\Web\Foundation\BusinessDateController
Request: App\Http\Requests\Web\Foundation\OpenBusinessDateRequest
Service: App\Services\Foundation\BusinessDateService
Permission: business_dates.open
Complexity: complex
Transaction required: yes
Lock required: yes
Pagination: no
```

Request:

```json
{
  "business_date": "2026-08-25",
  "opening_float_amount": "0.00",
  "opening_notes": "No physical cash drawer today."
}
```

Rules:

- verify branch access;
- lock branch or use the partial unique index to prevent two open days;
- reject if branch already has `open` or `closing` business date;
- allocate business day number atomically;
- use current branch configuration for planned open/close time;
- allow opening float `0.00`;
- do not require physical cash;
- create only the business day, not cashier shift automatically.

If the branch uses no physical cash:

- `opening_float_amount = 0.00`;
- expected cash stays `0.00`;
- cash reconciliation may be skipped or closed with zero cash depending on later cash policy.

## 15. Close business day

Endpoint:

```text
POST /api/v1/branches/{branch_uuid}/business-days/{business_day_uuid}/close
Route file: routes/api/v1/web.php
Controller: App\Http\Controllers\Web\Foundation\BusinessDateController
Request: App\Http\Requests\Web\Foundation\CloseBusinessDateRequest
Service: App\Services\Foundation\BusinessDateService
Permission: business_dates.close
Complexity: complex
Transaction required: yes
Lock required: yes
Pagination: no
```

Request with cash:

```json
{
  "counted_cash_amount": "240000.00",
  "closing_notes": "Cash counted by manager and cashier."
}
```

Request with no physical cash:

```json
{
  "counted_cash_amount": "0.00",
  "closing_notes": "No physical cash transactions."
}
```

Rules:

- lock business date row;
- reject if already closed;
- reject if open orders, unpaid bills, open shifts or pending cash reconciliation exist;
- calculate expected cash from recorded cash transactions;
- compare counted cash vs expected cash;
- set closing variance;
- set status `closed`;
- write audit event;
- optionally write realtime outbox event after commit.

Important:

The system calculates expected cash from recorded transactions. User does not manually enter expected cash. User enters counted cash only when there is physical cash to count.

## 16. Live expected cash

Live expected cash means the current cash amount the system believes should physically exist based on recorded system transactions.

Formula:

```text
expected_cash =
  opening_float
  + cash sales
  - cash refunds
  + cash in
  - cash out
  - safe drops
```

Example:

```text
Opening float: 50,000
Cash sales: 220,000
Cash refunds: 10,000
Cash out: 20,000
Safe drop: 100,000

Expected cash = 50,000 + 220,000 - 10,000 - 20,000 - 100,000
Expected cash = 140,000
```

This is not a guessed amount. It is calculated from system records.

In this foundation design, `business_dates.expected_cash_amount` is updated or finalized by close process. Later cash-control APIs may maintain separate live projections in `business_day_cash_reconciliations`.

## 17. Petty cash vs expense boundary

Petty cash and expense are related but not the same thing.

Petty cash:

- is a cash account or cash fund;
- stores money available for small purchases;
- has top-ups, returns, and movements;
- appears in `cash_accounts` with `account_type=petty_cash`;
- movements appear in `cash_movements`.

Expense:

- is the business cost being claimed/recorded;
- appears in `expense_claims`;
- payment appears in `expense_payments`;
- may be paid by petty cash, bank, supplier credit or another method.

Example:

```text
Petty cash account has TZS 100,000.
Manager buys cleaning supplies for TZS 15,000.

Expense claim:
  Cleaning supplies, TZS 15,000

Expense payment:
  Paid from petty cash account, TZS 15,000

Cash movement:
  petty cash cash_out, TZS -15,000
```

So we should not duplicate bank/cash details inside user/staff tables. Finance owns accounts and payment records.

Detailed petty cash/expense workflow belongs to finance design, but branch/business-date APIs must be aware that physical cash may be zero.

## 18. Number sequence list

Endpoint:

```text
GET /api/v1/number-sequences
Route file: routes/api/v1/web.php
Controller: App\Http\Controllers\Web\Foundation\NumberSequenceController
Permission: number_sequences.view
Complexity: simple
Pagination: simplePaginate
Default per_page: 15
Max per_page: 100
```

Filters:

- `sequence_key`
- `branch_uuid`
- `is_active=true|false`
- `period_type=none|daily|monthly|yearly`

Indexes:

- `number_sequences(company_id, sequence_key, is_active)`
- `number_sequences(branch_id, sequence_key, is_active)`

## 19. Default sequence keys

Seed default sequence definitions:

```text
order              -> ORD-2026-01
receipt            -> REC-2026-01
shift              -> SHIFT-20260824-01
expense            -> EXP-2026-01
business_day       -> BD-20260824-01
cash_reconciliation -> CASH-20260824-01
purchase_order     -> PO-2026-01
goods_receipt      -> GRN-2026-01
payment            -> PAY-2026-01
refund             -> REF-2026-01
journal_entry      -> JE-2026-01
payroll_run        -> PAYRUN-2026-01
```

These are human-readable display numbers. Public API still uses UUIDs for lookup.

## 20. Update number sequence

Endpoint:

```text
PATCH /api/v1/number-sequences/{number_sequence_uuid}
Route file: routes/api/v1/web.php
Controller: App\Http\Controllers\Web\Foundation\NumberSequenceController
Request: App\Http\Requests\Web\Foundation\UpdateNumberSequenceRequest
Permission: number_sequences.update
Complexity: medium
Service required: yes
Transaction required: yes
Pagination: no
```

Request:

```json
{
  "display_name": "Orders",
  "prefix": "ORD",
  "suffix": null,
  "padding_length": 2,
  "period_type": "yearly",
  "reset_per_branch": false,
  "is_active": true
}
```

Rules:

- do not allow duplicate active sequence for same company/branch/period;
- do not allow `next_number` to be lower than already allocated max;
- audit changes to prefix, period and padding;
- changing format affects future numbers only;
- old documents keep their already formatted numbers.

## 21. Atomic number allocation

Number allocation is not a public general-purpose endpoint in first release. It should be a reusable backend service used by business services.

Service:

```text
App\Services\Numbering\NumberSequenceService
```

Method concept:

```text
allocate(sequence_key, branch_id, business_date, consumer_table, consumer_uuid)
```

Rules:

- run inside the same transaction as the business operation;
- lock selected `number_sequences` row with `lockForUpdate()`;
- allocate `next_number`;
- increment `next_number`;
- create `number_sequence_allocations`;
- mark allocation `used` when attached to consumer;
- if business operation fails, transaction rolls back allocation too.

Do not generate document numbers in controllers.

Example order flow:

```text
OrderService transaction
  -> resolve open business date
  -> allocate order number from sequence_key=order
  -> create order with order_number
  -> create order items
  -> create outbox event
  -> commit
```

## 22. Number examples

Yearly order:

```text
prefix = ORD
period_type = yearly
padding_length = 2
next_number = 1

formatted = ORD-2026-01
```

Daily shift:

```text
prefix = SHIFT
period_type = daily
padding_length = 2
next_number = 1

formatted = SHIFT-20260824-01
```

Expense:

```text
prefix = EXP
period_type = yearly
padding_length = 2
next_number = 1

formatted = EXP-2026-01
```

## 23. Permissions

Recommended permissions:

```text
company.view
company.update

branches.view
branches.create
branches.update
branches.close

branch_configurations.view
branch_configurations.update

business_dates.view
business_dates.open
business_dates.close
business_dates.override_close

number_sequences.view
number_sequences.update
```

Protected Business Owner should have all of these by default.

Cashier may have:

```text
business_dates.view
```

Branch Manager may have:

```text
branches.view
branch_configurations.view
business_dates.view
business_dates.open
business_dates.close
```

## 24. Complexity summary

| Area | Complexity | Reason |
|---|---:|---|
| Company view | Simple | One row, no side effect. |
| Company update | Medium | Legal/tax-sensitive and audited. |
| Branch list | Simple | Simple paginated list. |
| Branch create | Medium | Creates branch + config transaction. |
| Branch update | Medium | Status rules and duplicate checks. |
| Branch configuration update | Medium | Versioned config. |
| Business day current | Simple | One active row lookup. |
| Open business day | Complex | Locking, unique open day, numbering. |
| Close business day | Complex | Checks open transactions, calculates expected cash, variance. |
| Number sequence list | Simple | Paginated settings list. |
| Number sequence update | Medium | Prevents numbering conflicts. |
| Number allocation | Complex service | Locking and transaction safety. |

## 25. Performance rules

- Use simple pagination for branch and number-sequence lists.
- Use exact UUID lookup for details.
- Use prefix search on normalized branch names.
- Avoid unpaginated branch/sequence admin lists.
- Do not calculate reports inside business-day close.
- Do not scan all tenant tables to calculate expected cash; query only indexed cash/payment/refund/movement records for the selected `business_date_id`.
- Use row locks for open/close business day and number allocation.
- Use partial unique index to enforce one open business date per branch.

## 26. Standard errors

Use `ApiResponse`.

Examples:

```text
422 VALIDATION_FAILED
403 ACCESS_DENIED
404 RESOURCE_NOT_FOUND
409 BUSINESS_DAY_ALREADY_OPEN
409 BUSINESS_DAY_ALREADY_CLOSED
409 OPEN_ORDERS_EXIST
409 UNPAID_BILLS_EXIST
409 OPEN_SHIFT_EXISTS
409 DUPLICATE_BRANCH_NAME
409 INVALID_SEQUENCE_CHANGE
500 SERVER_ERROR
```

Example:

```json
{
  "success": false,
  "message": "We could not close the business day.",
  "data": null,
  "errors": {
    "code": "OPEN_ORDERS_EXIST",
    "open_orders_count": 3
  }
}
```

## 27. Frontend flow

After owner login:

```text
Call /api/v1/me
  -> get branches
Select branch
  -> call /api/v1/me/permissions
  -> call /api/v1/branches/{uuid}/business-day/current
If no open business day
  -> manager opens business day
Then POS/order screens can operate
```

Branch setup screen:

```text
Company profile
Branches list
Branch details
Branch configuration
Number sequence settings
```

## 28. Implementation components

Recommended files:

```text
routes/api/v1/web.php

app/Http/Controllers/Web/Foundation/CompanyProfileController.php
app/Http/Controllers/Web/Foundation/BranchController.php
app/Http/Controllers/Web/Foundation/BranchConfigurationController.php
app/Http/Controllers/Web/Foundation/BusinessDateController.php
app/Http/Controllers/Web/Foundation/NumberSequenceController.php

app/Http/Requests/Web/Foundation/UpdateCompanyProfileRequest.php
app/Http/Requests/Web/Foundation/StoreBranchRequest.php
app/Http/Requests/Web/Foundation/UpdateBranchRequest.php
app/Http/Requests/Web/Foundation/UpdateBranchConfigurationRequest.php
app/Http/Requests/Web/Foundation/OpenBusinessDateRequest.php
app/Http/Requests/Web/Foundation/CloseBusinessDateRequest.php
app/Http/Requests/Web/Foundation/UpdateNumberSequenceRequest.php

app/Http/Resources/Web/Foundation/CompanyProfileResource.php
app/Http/Resources/Web/Foundation/BranchResource.php
app/Http/Resources/Web/Foundation/BranchConfigurationResource.php
app/Http/Resources/Web/Foundation/BusinessDateResource.php
app/Http/Resources/Web/Foundation/NumberSequenceResource.php

app/Services/Foundation/CompanyProfileService.php
app/Services/Foundation/BranchService.php
app/Services/Foundation/BranchConfigurationService.php
app/Services/Foundation/BusinessDateService.php
app/Services/Numbering/NumberSequenceService.php
```

## 29. Final implementation decision

Implement this design after Auth and Registration/Provisioning.

Reason:

The next operational modules need branch, business-date and numbering context:

- tenant users and branch assignments;
- POS orders;
- shifts;
- receipts;
- payments;
- expenses;
- stock movements;
- reports.
