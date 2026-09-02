# API Design 09: Finance, Accounting, Double Entry, Cash, Expenses, Supplier Bills, and Posting Rules

Project: Restaurant POS ERP  
Backend: Laravel, PHP 8.3, PostgreSQL, database-per-tenant multitenancy  
API version: v1  
Date: 2026-08-25

## 1. Purpose

This design explains how operational activity becomes proper accounting records.

The goal is:

```text
Every financial event creates a balanced, explainable, immutable double-entry journal.
```

Examples of financial events:

- cash sale
- mobile-money/card sale
- refund
- supplier bill
- supplier payment
- expense claim
- petty cash payment
- payroll posting
- provider settlement
- stock loss/waste if inventory costing is enabled

Accounting must not be an afterthought. If POS, purchasing, payroll and cash control are not connected to journals, the accountant cannot close the period confidently.

## 2. What this design depends on

This module depends on:

- Design 03: company, branch, cost centres, numbering, business dates
- Design 04: tenant users, roles, permissions
- Design 06: POS orders, payments, receipts, refunds
- Design 08: purchases, suppliers, stock movements
- Payroll design/migrations
- Tax/fiscalization design

Finance is tenant-local. Reports are per tenant database.

## 3. Route placement

Tenant finance APIs belong in:

```text
routes/api/v1/web.php
```

Recommended source folders:

```text
app/Http/Controllers/Web/Finance
app/Http/Requests/Web/Finance
app/Http/Resources/Web/Finance
app/Services/Finance
app/Services/Accounting
```

No `Api/V1` folder is needed. Versioning stays in route files and URL prefixes.

## 4. Tables used

Tenant tables:

- `account_classes`
- `account_categories`
- `account_subcategories`
- `chart_accounts`
- `financial_years`
- `financial_periods`
- `journal_sources`
- `journal_entries`
- `journal_entry_lines`
- `bank_accounts`
- `cash_accounts`
- `cash_movements`
- `expense_categories`
- `expense_claims`
- `expense_payments`
- `supplier_bills`
- `supplier_bill_items`
- `supplier_payments`

Operational source tables that can trigger accounting:

- `orders`
- `payments`
- `refunds`
- `provider_settlements`
- `purchase_orders`
- `goods_receipts`
- `stock_movements`
- `waste_records`
- `payroll_runs`
- `business_day_cash_reconciliations`

## 5. Account hierarchy

The accounting structure is:

```text
account_classes
  account_categories
    account_subcategories
      chart_accounts
```

This solves the earlier concern about having only broad accounts without direct/indirect/category/subcategory grouping.

## 6. Account classes

`account_classes` are the top-level accounting groups.

Common examples:

```text
Assets
Liabilities
Equity
Income
Expenses
Cost of Sales
```

Each class has a normal balance:

```text
debit
credit
```

Example:

```text
Assets -> debit
Expenses -> debit
Income -> credit
Liabilities -> credit
Equity -> credit
```

This helps the system understand reports and validation.

## 7. Account categories

`account_categories` group accounts inside a class.

Examples:

For Assets:

```text
Current Assets
Non-current Assets
```

For Expenses:

```text
Direct Expenses
Indirect Expenses
Operating Expenses
Administrative Expenses
Statutory Expenses
```

For Income:

```text
Operating Income
Non-operating Income
```

The `category_nature` column supports values like:

```text
current
non_current
direct
indirect
operating
non_operating
statutory
```

This allows reports like:

```text
Direct cost
Indirect expense
Current asset
Statutory liability
```

## 8. Account subcategories

`account_subcategories` further group chart accounts.

Example:

```text
Class: Expenses
Category: Operating Expenses
Subcategory: Utilities
Chart accounts:
  Electricity Expense
  Water Expense
  Internet Expense
```

Another example:

```text
Class: Assets
Category: Current Assets
Subcategory: Cash and Bank
Chart accounts:
  Cash Drawer - Main Counter
  Petty Cash
  CRDB Bank Account
```

## 9. Chart accounts

`chart_accounts` is the actual ledger account list.

Examples:

```text
Cash Drawer
Petty Cash
Bank Account
Mobile Money Clearing
Accounts Receivable
Inventory
Accounts Payable
VAT Output
VAT Input
Sales Revenue
Discount Allowed
Cost of Goods Sold
Payroll Expense
Rent Expense
Utilities Expense
```

Important columns:

- `account_category_id`
- `account_subcategory_id`
- `parent_id`
- `account_number`
- `is_control_account`
- `allow_manual_posting`

Control account example:

```text
Accounts Payable
```

Users should not manually post random entries to control accounts unless allowed. They should post supplier bills/payments and let the system update the control account.

## 10. Cost centre

A cost centre is not the same as a bank account or employee detail.

It is an analytical grouping for cost/reporting.

Examples:

```text
Kitchen
Bar
Delivery
Administration
Branch Operations
```

Example journal line:

```text
Debit: Cleaning Expense
Cost centre: Kitchen
Amount: 50,000
```

This lets management ask:

```text
How much did Kitchen spend this month?
How much did Bar spend compared to sales?
```

It is optional. If the business is small, many entries may only use branch without cost centre.

## 11. Financial years and periods

`financial_years` defines the accounting year.

Example:

```text
FY 2026
Starts: 2026-01-01
Ends: 2026-12-31
Status: open
```

`financial_periods` defines monthly/period closes.

Example:

```text
August 2026
Starts: 2026-08-01
Ends: 2026-08-31
Status: open
```

Posting rule:

```text
Cannot post into closed/locked period.
```

If correction is needed after close:

```text
Post adjustment in the next open period
or reopen period with permission
```

## 12. Journal sources

`journal_sources` identifies where the journal came from.

Examples:

```text
manual
sales
purchases
inventory
payroll
payments
banking
settlement
```

This makes filtering easier:

```text
Show all payroll journals
Show all sales journals
Show all settlement journals
```

## 13. Journal entries and lines

`journal_entries` is the journal header.

It stores:

- company
- optional branch
- financial period
- journal source
- journal number
- entry date
- status
- description
- posted by
- posted time

`journal_entry_lines` stores debit and credit lines.

Each line has:

- chart account
- optional branch
- optional cost centre
- debit amount or credit amount
- currency
- description

Important rule:

Each line must be either debit or credit, not both.

Journal must balance:

```text
total debit = total credit
```

This balance should be enforced in service logic before posting.

## 14. Journal lifecycle

Recommended statuses:

```text
draft
posted
reversed
cancelled
```

Meaning:

- `draft`: prepared but not final
- `posted`: official accounting record
- `reversed`: cancelled by reversal journal
- `cancelled`: draft cancelled before posting

Important:

Do not edit posted journals.

If a posted journal is wrong:

```text
Create reversal journal
Create corrected journal
```

That keeps the audit trail clean.

## 15. Manual journal example

Example:

Owner injects capital into bank:

```text
Debit: Bank Account                 5,000,000
Credit: Owner Capital               5,000,000
```

JSON example:

```json
{
  "entry_date": "2026-08-25",
  "journal_source_uuid": "manual-source-uuid",
  "description": "Owner capital injection",
  "lines": [
    {
      "chart_account_uuid": "bank-account-uuid",
      "debit_amount": "5000000.00",
      "credit_amount": "0.00"
    },
    {
      "chart_account_uuid": "owner-capital-uuid",
      "debit_amount": "0.00",
      "credit_amount": "5000000.00"
    }
  ]
}
```

This endpoint must accept line arrays because a journal always has multiple lines.

## 16. Sales payment posting

Example cash sale:

```text
Customer pays 45,000 cash
VAT included: 6,864.41
Net sales: 38,135.59
```

Journal:

```text
Debit: Cash Drawer                  45,000.00
Credit: Sales Revenue               38,135.59
Credit: VAT Output                   6,864.41
```

This should be created from `payments`, not from receipt printing.

Reason:

Payment is the money event. Receipt is proof/document output.

## 17. Card/mobile money posting

Example mobile-money sale:

```text
Customer pays 45,000 by M-Pesa
```

At payment confirmation:

```text
Debit: Mobile Money Clearing        45,000.00
Credit: Sales Revenue               38,135.59
Credit: VAT Output                   6,864.41
```

When provider settlement arrives and fee is deducted:

```text
Debit: Bank Account                 44,000.00
Debit: Provider Charges              1,000.00
Credit: Mobile Money Clearing       45,000.00
```

This is why provider settlement tables matter.

## 18. Refund posting

Example refund:

```text
Refund customer 10,000 cash
```

Journal:

```text
Debit: Sales Returns/Refunds        10,000.00
Credit: Cash Drawer                 10,000.00
```

If tax must be reversed:

```text
Debit: Sales Returns                 8,474.58
Debit: VAT Output                    1,525.42
Credit: Cash Drawer                 10,000.00
```

Refunds must use approval rules if above threshold.

## 19. Supplier bill posting

Purchase order should not normally post accounting.

Reason:

PO is a commitment/request, not yet a liability.

Accounting usually begins when invoice/bill or goods receipt is posted, depending on accounting policy.

Example supplier bill:

```text
Supplier bill for cooking oil: 200,000
VAT input: 30,508.47
Net purchase: 169,491.53
```

Journal:

```text
Debit: Inventory / Purchases        169,491.53
Debit: VAT Input                     30,508.47
Credit: Accounts Payable            200,000.00
```

When supplier is paid by bank:

```text
Debit: Accounts Payable             200,000.00
Credit: Bank Account                200,000.00
```

## 20. Expense claim posting

Example expense:

```text
Electricity bill paid from petty cash: 80,000
```

At approved/posted expense:

```text
Debit: Electricity Expense           80,000.00
Credit: Expense Payable or Cash      80,000.00
```

If expense is approved first and paid later:

```text
Debit: Electricity Expense           80,000.00
Credit: Expense Payable              80,000.00
```

When paid:

```text
Debit: Expense Payable               80,000.00
Credit: Petty Cash                   80,000.00
```

The system supports both cash and bank expense payments.

## 21. Cash accounts

`cash_accounts` are branch-level cash containers.

Types:

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
Cash in Transit
```

This directly answers the earlier petty cash question:

```text
Petty cash is a cash account.
Expense is what the money was spent on.
```

Example:

```text
Cash account: Petty Cash
Expense category: Cleaning Materials
```

They are related during expense payment, but they are not the same thing.

## 22. Cash movements

`cash_movements` is the operational cash ledger.

Reasons:

```text
opening_float
cash_sale
cash_refund
cash_in
cash_out
safe_drop
supplier_payment
expense_payment
petty_cash_top_up
petty_cash_return
closing_variance
```

Example:

Cash sale:

```text
cash_account = Main Cash Drawer
movement_reason = cash_sale
amount = 45,000
payment_id = payment record
```

Petty cash expense:

```text
cash_account = Petty Cash
movement_reason = expense_payment
amount = -20,000
expense_payment_id = expense payment record
```

Cash movement supports cash control and reconciliation. Journal entry supports accounting. Sometimes both are created from the same business event.

## 23. Bank accounts

`bank_accounts` represent tenant company/branch bank accounts.

Examples:

- CRDB TZS account
- NMB TZS account
- NBC USD account

They can link to chart accounts.

This avoids repeating bank details on employee, supplier, or expense records.

## 24. Posting engine design

Use a reusable accounting service.

Recommended services:

```text
JournalPostingService
AccountingPeriodService
AccountingRuleResolver
SalesPostingService
RefundPostingService
SupplierBillPostingService
ExpensePostingService
PayrollPostingService
SettlementPostingService
CashMovementService
```

The controller should not manually build double-entry lines.

Reason:

Posting rules are complex and must be reused from POS, payments, purchases, payroll and settlements.

## 25. Accounting rules/configuration

Do not hard-code chart account IDs in code.

Use tenant settings or mapping tables/configuration for:

- default sales revenue account
- VAT output account
- VAT input account
- cash drawer account
- mobile money clearing account
- card clearing account
- provider fee expense account
- accounts payable account
- inventory account
- cost of goods sold account
- payroll expense account
- statutory liability accounts

In first release, these can be seeded defaults and adjustable by accountant/admin.

## 26. API endpoints

Chart/account setup:

```text
GET    /api/v1/account-classes
GET    /api/v1/account-categories
POST   /api/v1/account-categories
PATCH  /api/v1/account-categories/{account_category_uuid}

GET    /api/v1/account-subcategories
POST   /api/v1/account-subcategories
PATCH  /api/v1/account-subcategories/{account_subcategory_uuid}

GET    /api/v1/chart-accounts
POST   /api/v1/chart-accounts
GET    /api/v1/chart-accounts/{chart_account_uuid}
PATCH  /api/v1/chart-accounts/{chart_account_uuid}
```

Periods:

```text
GET  /api/v1/financial-years
POST /api/v1/financial-years
POST /api/v1/financial-years/{financial_year_uuid}/close

GET  /api/v1/financial-periods
POST /api/v1/financial-periods/{financial_period_uuid}/close
POST /api/v1/financial-periods/{financial_period_uuid}/lock
```

Journals:

```text
GET  /api/v1/journal-entries
POST /api/v1/journal-entries
GET  /api/v1/journal-entries/{journal_entry_uuid}
POST /api/v1/journal-entries/{journal_entry_uuid}/post
POST /api/v1/journal-entries/{journal_entry_uuid}/reverse
POST /api/v1/journal-entries/{journal_entry_uuid}/cancel
```

Cash/bank:

```text
GET  /api/v1/bank-accounts
POST /api/v1/bank-accounts
PATCH /api/v1/bank-accounts/{bank_account_uuid}

GET  /api/v1/cash-accounts
POST /api/v1/cash-accounts
PATCH /api/v1/cash-accounts/{cash_account_uuid}

GET  /api/v1/cash-movements
POST /api/v1/cash-movements
```

Expenses:

```text
GET  /api/v1/expense-categories
POST /api/v1/expense-categories
PATCH /api/v1/expense-categories/{expense_category_uuid}

GET  /api/v1/expense-claims
POST /api/v1/expense-claims
GET  /api/v1/expense-claims/{expense_claim_uuid}
POST /api/v1/expense-claims/{expense_claim_uuid}/submit
POST /api/v1/expense-claims/{expense_claim_uuid}/approve
POST /api/v1/expense-claims/{expense_claim_uuid}/reject
POST /api/v1/expense-claims/{expense_claim_uuid}/pay
POST /api/v1/expense-claims/{expense_claim_uuid}/void
```

Supplier bills/payments:

```text
GET  /api/v1/supplier-bills
POST /api/v1/supplier-bills
GET  /api/v1/supplier-bills/{supplier_bill_uuid}
POST /api/v1/supplier-bills/{supplier_bill_uuid}/approve
POST /api/v1/supplier-bills/{supplier_bill_uuid}/pay
POST /api/v1/supplier-bills/{supplier_bill_uuid}/void
```

## 27. Journal create request

Manual journal requires an array of lines.

Example:

```json
{
  "entry_date": "2026-08-25",
  "description": "Owner capital injection",
  "lines": [
    {
      "chart_account_uuid": "uuid",
      "branch_uuid": "uuid",
      "cost_centre_uuid": null,
      "debit_amount": "5000000.00",
      "credit_amount": "0.00",
      "description": "Cash received"
    },
    {
      "chart_account_uuid": "uuid",
      "branch_uuid": "uuid",
      "cost_centre_uuid": null,
      "debit_amount": "0.00",
      "credit_amount": "5000000.00",
      "description": "Capital"
    }
  ]
}
```

Validation:

```text
At least two lines
Each line debit or credit only
Total debit equals total credit
All accounts active
No manual posting to blocked control accounts
Period is open
User has permission
```

## 28. Complexity classification

Simple CRUD:

- account categories
- account subcategories
- chart accounts
- bank accounts
- cash accounts
- expense categories

Medium complexity:

- financial year/period close
- manual journal draft
- expense claim submit/approve
- supplier bill create/approve
- cash movement create

High complexity:

- journal posting
- journal reversal
- sales payment posting
- refund posting
- supplier bill/payment posting
- expense payment posting
- payroll posting
- provider settlement posting
- period close validation

Queue required:

- large ledger exports
- trial balance reports for large periods
- scheduled accounting reports
- payroll posting if many staff
- provider settlement reconciliation

No queue required:

- simple paginated chart account list
- simple expense category setup
- posting a small manual journal, if line count is small

## 29. Transactions and locking

Use transactions for:

- posting journal
- reversing journal
- paying expense
- paying supplier bill
- posting payroll
- posting provider settlement
- closing period

Use locking for:

- journal entry during posting
- source document during posting
- financial period during close
- cash account when recording cash movement
- expense claim/supplier bill when paying

Reason:

Two users must not post or pay the same document twice.

## 30. Query and performance rules

All lists use simple pagination.

Default:

```text
per_page = 15
max per_page = 100
```

Frequent filters:

```text
chart_accounts.company_id/status/account_category_id
journal_entries.company_id/status/entry_date
journal_entries.branch_id/status/entry_date
journal_entries.financial_period_id/status
journal_entry_lines.chart_account_id
journal_entry_lines.branch_id/chart_account_id
cash_movements.cash_account_id/moved_at
cash_movements.branch_id/movement_reason/moved_at
expense_claims.branch_id/status/expense_date
supplier_bills.branch_id/status/bill_date
supplier_payments.branch_id/status/paid_at
```

Avoid:

```text
recalculating every accounting report by scanning all historical journal lines without date/account filters
```

Use indexed date ranges and account filters.

Do not duplicate report data permanently. If reports become heavy, use short-lived cache or queued generated report files.

## 31. Access control

Recommended permissions:

```text
chart_accounts.view
chart_accounts.create
chart_accounts.update

financial_periods.view
financial_periods.close
financial_periods.lock

journal_entries.view
journal_entries.create
journal_entries.post
journal_entries.reverse
journal_entries.cancel

bank_accounts.view
bank_accounts.manage
cash_accounts.view
cash_accounts.manage
cash_movements.view
cash_movements.create

expenses.view
expenses.create
expenses.submit
expenses.approve
expenses.pay
expenses.void

supplier_bills.view
supplier_bills.create
supplier_bills.approve
supplier_bills.pay
supplier_bills.void
```

Auditor should be read-only across finance and audit logs.

## 32. Audit logging

Log critical finance events:

- chart account created/archived
- financial period closed/locked/reopened
- journal posted
- journal reversed
- expense approved/rejected/paid/voided
- supplier bill approved/paid/voided
- cash movement manual adjustment
- bank/cash account changed

Do not log huge before/after JSON for every journal line. The journal lines are the accounting record.

## 33. Schema improvement to watch

The current journal schema links directly to:

```text
payment_id
refund_id
purchase_order_id
payroll_run_id
provider_settlement_id
```

But accounting should often post from:

```text
supplier_bills
supplier_payments
expense_claims
expense_payments
goods_receipts
stock_movements
business_day_cash_reconciliations
```

Recommended implementation rule:

Do not force all posting source FKs into `journal_entries` if it creates circular schema problems.

Better design:

```text
source document stores journal_entry_id
```

Examples:

```text
expense_claims.journal_entry_id
payments can be found through journal_entries.payment_id
supplier_bills should add journal_entry_id if posting bill directly
supplier_payments should add journal_entry_id if posting payment directly
```

For first release, before implementation, review each posting source and ensure it has either:

```text
journal_entries has source FK
```

or:

```text
source table has journal_entry_id
```

Do not rely only on description text for financial traceability.

## 34. First release recommendation

Implement first:

- chart of accounts setup
- account categories/subcategories
- financial year/period setup
- manual journal create/post/reverse
- bank accounts
- cash accounts
- cash movements
- expense categories
- expense claim approval/payment
- supplier bill approval/payment
- sales payment posting
- refund posting
- provider settlement posting skeleton
- payroll posting skeleton

Later:

- advanced budgeting
- bank reconciliation
- multi-currency revaluation
- fixed assets
- accrual automation
- advanced management accounting
- automated cost of goods sold by recipe/inventory policy
