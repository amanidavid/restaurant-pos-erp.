# API Design 11: Tax, Fiscalization, Compliance, Fiscal Receipts, and Tax Filing

Project: Restaurant POS ERP  
Backend: Laravel, PHP 8.3, PostgreSQL, database-per-tenant multitenancy  
API version: v1  
Date: 2026-08-25

## 1. Purpose

This design explains how the system handles tax compliance, VAT/input-output tax tracking, fiscal receipts, tax authority registrations, fiscal devices/API integrations, and tax return submissions.

Main flow:

```text
Tax authority is configured
Company/branch tax registrations are stored
Tax codes are mapped to accounting accounts
Sales/purchases create tax transactions
Receipts/refunds create fiscal receipts
Fiscal provider/device receives submission
Provider response is stored
Tax return periods are prepared
Tax filing submission is sent/recorded
```

This module is compliance-sensitive. It must be reliable, traceable, and tenant/branch aware.

## 2. What this design depends on

This module depends on:

- Design 03: company, branch, numbering
- Design 06: POS orders, payments, receipts, refunds
- Design 09: finance/accounting double-entry
- Design 11 uses provider/integration accounts from payment/printing integration schema

Tax compliance is tenant-local. Reports and records live in each tenant database.

## 3. Route placement

Tenant tax/compliance APIs belong in:

```text
routes/api/v1/web.php
```

Recommended source folders:

```text
app/Http/Controllers/Web/Tax
app/Http/Controllers/Web/Fiscalization
app/Http/Requests/Web/Tax
app/Http/Requests/Web/Fiscalization
app/Http/Resources/Web/Tax
app/Http/Resources/Web/Fiscalization
app/Services/Tax
app/Services/Fiscalization
```

No `Api/V1` folder is needed. Versioning stays in route files and URL prefixes.

## 4. Tables used

Tenant tables:

- `tax_codes`
- `tax_authorities`
- `tax_registrations`
- `tax_code_accounts`
- `tax_exemption_reasons`
- `tax_transactions`
- `fiscal_devices`
- `fiscal_receipts`
- `fiscal_receipt_lines`
- `tax_return_periods`
- `tax_return_lines`
- `tax_filing_submissions`
- `integration_accounts`
- `webhook_receipts`
- `receipts`
- `refunds`
- `orders`
- `order_items`
- `supplier_bills`
- `supplier_bill_items`
- `journal_entries`

## 5. Tax authority

`tax_authorities` stores the authority or compliance body.

Examples:

```text
TRA
Local government levy authority
Fiscal receipt provider
Statutory authority
```

Authority types:

```text
national_tax
local_tax
fiscal_receipt
statutory
```

For Tanzania first release:

```text
country_code = TZ
```

This table allows the tenant to configure TRA/VFD/EFD/local levy relationships without hard-coding one provider.

## 6. Tax registrations

`tax_registrations` stores company or branch registration numbers.

Registration types:

```text
tin
vrn
vat
fiscal_device
local_levy
```

Example company-level registration:

```text
Company: Kilimanjaro Restaurant
Type: TIN
Number: 123-456-789
```

Example branch-level registration:

```text
Branch: Mikocheni
Type: fiscal_device
Number: EFD-ABC-001
```

Important:

TIN/VRN may be company-level, but fiscal device/branch registration can be branch-level.

This answers the earlier concern: do not force all tax identifiers into only tenant/company or only branch. Some are company registrations; some are branch/device registrations.

## 7. Tax codes

`tax_codes` comes from catalog foundation.

Examples:

```text
VAT 18%
VAT Exempt
Zero Rated
No Tax
```

Tax code is used by:

- menu items
- ingredients/purchases
- supplier bills
- accounting tax transactions
- fiscal receipt lines

## 8. Tax code accounts

`tax_code_accounts` maps tax codes to accounting accounts.

Examples:

```text
VAT 18% output -> VAT Output Payable
VAT 18% input -> VAT Input Recoverable
Tax expense -> Tax Expense
```

This avoids hard-coding tax account IDs.

When sales posting happens:

```text
Credit VAT Output
```

When supplier purchase bill has VAT:

```text
Debit VAT Input
```

## 9. Tax exemption reasons

`tax_exemption_reasons` explains why a transaction is exempt or zero-rated.

Examples:

- Exempt item
- Zero-rated item
- Government exemption
- Diplomatic exemption

This matters because fiscal/tax reports often require a reason, not only tax amount zero.

## 10. Tax transactions

`tax_transactions` is the tax ledger.

It records:

- company
- branch
- tax code
- exemption reason
- source document
- direction
- tax date
- taxable amount
- tax amount
- currency
- status

Directions:

```text
output
input
adjustment
```

Meaning:

- `output`: tax charged on sales
- `input`: tax paid on purchases/expenses
- `adjustment`: correction/reversal

Example sale:

```text
Sale taxable amount: 38,135.59
VAT output: 6,864.41
Direction: output
Source: receipt/order item
```

Example purchase:

```text
Supplier bill taxable amount: 169,491.53
VAT input: 30,508.47
Direction: input
Source: supplier bill item
```

Tax transactions must be source-linked. Do not store untraceable tax rows.

## 11. Fiscal devices

`fiscal_devices` stores the fiscal device or fiscal API integration per branch.

Device types:

```text
efd
vfd
api
manual
```

Examples:

```text
Branch EFD machine
TRA VFD API account
Manual fallback fiscal book
```

Important columns:

- branch
- tax authority
- integration account
- print device
- serial number
- registration number
- default flag
- status
- settings

Each branch can have one active default fiscal device.

## 12. Fiscal receipt

`fiscal_receipts` is the compliance record for a sale receipt/refund receipt.

It links to exactly one:

```text
receipt_id
refund_id
```

Document types:

```text
sale_receipt
refund_receipt
credit_note
debit_note
```

Statuses:

```text
pending
submitted
accepted
rejected
cancelled
```

Important fields:

- fiscal number
- verification code
- QR URL
- taxable amount
- tax amount
- total amount
- request payload
- response payload
- submitted/accepted timestamps
- rejection reason

## 13. Fiscal receipt lines

`fiscal_receipt_lines` stores the items submitted to fiscal device/provider.

Example:

```text
2x Beef Burger
Taxable amount: 30,508.47
VAT: 5,491.53
Line total: 36,000
```

Lines can link to `tax_transactions`, which gives traceability between fiscal submission and tax ledger.

## 14. Fiscalization lifecycle

Recommended flow after normal receipt is issued:

```text
Payment completed
Receipt issued
Tax transactions created
Fiscal receipt created as pending
Fiscal receipt lines created
Outbox/job created for fiscal submission
Worker submits to fiscal provider/device
Provider response stored
Fiscal receipt becomes accepted or rejected
Receipt print can include fiscal QR/code if accepted
```

If the fiscal provider is slow:

```text
Keep fiscal receipt pending/submitted
Retry safely
Show cashier status
Do not duplicate fiscal submission
```

## 15. Provider integration

Fiscal integration is tenant-specific and often branch-specific.

Use:

```text
integration_providers
integration_accounts
provider_capabilities
webhook_receipts
```

Example:

```text
Provider: TRA VFD Gateway
Capability: fiscal_receipt_submit
Tenant account: branch/company credentials
Fiscal device: linked to integration account
```

Do not hard-code provider credentials in `.env`.

Tenant provider credentials belong in encrypted tenant integration account settings.

## 16. Idempotency and retries

Fiscal submission must be idempotent.

Recommended idempotency key:

```text
tenant_uuid + branch_uuid + fiscal_receipt_uuid
```

If worker retries:

```text
same fiscal receipt
same provider idempotency key
no duplicate fiscal document
```

If provider supports idempotency, send that key.

If provider does not support idempotency, protect locally:

```text
lock fiscal_receipt row
check status
submit only if pending/rejected retryable
store provider reference
```

## 17. Webhook handling

If fiscal provider sends callbacks:

```text
Receive webhook
Store webhook_receipt short-term
Check duplicate event id
Normalize into fiscal_receipts/tax_filing_submissions
Archive/delete raw webhook payload later
```

Do not keep huge raw provider payload forever.

This follows our project rule:

```text
webhook_receipts is intake/idempotency, not permanent business state
```

## 18. Tax return periods

`tax_return_periods` defines tax filing periods.

Return types:

```text
vat
sales_tax
withholding
local_levy
```

Statuses:

```text
open
prepared
submitted
accepted
closed
reopened
```

Example:

```text
VAT August 2026
Period: 2026-08-01 to 2026-08-31
Due date: 2026-09-20
Status: open
```

## 19. Tax return lines

`tax_return_lines` stores summarized filing values.

Line types:

```text
output_tax
input_tax
exempt_sales
zero_rated_sales
adjustment
payable
recoverable
```

Example VAT return:

```text
Output tax: 5,000,000
Input tax: 2,000,000
Payable: 3,000,000
```

Return lines should be generated from `tax_transactions`, not manually duplicated from POS reports.

## 20. Tax filing submissions

`tax_filing_submissions` records submission attempts to authority/provider.

It stores:

- return period
- integration account
- submitted by user
- submission reference
- status
- request payload
- response payload
- response message

Statuses:

```text
draft
submitted
accepted
rejected
cancelled
```

If online filing integration is not ready, first release can still record manual submission reference and response notes.

## 21. Recommended endpoints

Tax setup:

```text
GET  /api/v1/tax-authorities
POST /api/v1/tax-authorities
PATCH /api/v1/tax-authorities/{tax_authority_uuid}

GET  /api/v1/tax-registrations
POST /api/v1/tax-registrations
PATCH /api/v1/tax-registrations/{tax_registration_uuid}

GET  /api/v1/tax-code-accounts
POST /api/v1/tax-code-accounts
PATCH /api/v1/tax-code-accounts/{tax_code_account_uuid}

GET  /api/v1/tax-exemption-reasons
POST /api/v1/tax-exemption-reasons
```

Tax transactions:

```text
GET /api/v1/tax-transactions
GET /api/v1/tax-transactions/{tax_transaction_uuid}
```

Fiscalization:

```text
GET  /api/v1/fiscal-devices
POST /api/v1/fiscal-devices
PATCH /api/v1/fiscal-devices/{fiscal_device_uuid}

GET  /api/v1/fiscal-receipts
GET  /api/v1/fiscal-receipts/{fiscal_receipt_uuid}
POST /api/v1/fiscal-receipts/{fiscal_receipt_uuid}/submit
POST /api/v1/fiscal-receipts/{fiscal_receipt_uuid}/retry
POST /api/v1/fiscal-receipts/{fiscal_receipt_uuid}/cancel
```

Tax return:

```text
GET  /api/v1/tax-return-periods
POST /api/v1/tax-return-periods
GET  /api/v1/tax-return-periods/{tax_return_period_uuid}
POST /api/v1/tax-return-periods/{tax_return_period_uuid}/prepare
POST /api/v1/tax-return-periods/{tax_return_period_uuid}/submit
POST /api/v1/tax-return-periods/{tax_return_period_uuid}/close
POST /api/v1/tax-return-periods/{tax_return_period_uuid}/reopen
```

Filing submissions:

```text
GET /api/v1/tax-filing-submissions
GET /api/v1/tax-filing-submissions/{tax_filing_submission_uuid}
```

## 22. Complexity classification

Simple CRUD:

- tax authorities
- tax registrations
- tax code account mapping
- tax exemption reasons
- fiscal devices setup

Medium complexity:

- fiscal receipt details/list
- tax transaction list
- tax return period create
- manual tax filing submission recording

High complexity:

- tax transaction generation from sales/purchases/refunds
- fiscal receipt creation
- fiscal provider submission/retry
- tax return preparation
- tax filing submission
- fiscal rejection correction

Queue required:

- fiscal receipt submission
- fiscal retry
- tax return preparation for large periods
- tax filing submission
- provider webhook normalization
- fiscal receipt PDF/export

No queue required:

- simple setup CRUD
- tax transaction paginated list
- viewing fiscal receipt details

## 23. Transactions and locking

Use transactions for:

- creating tax transactions with receipt/payment posting
- creating fiscal receipt and lines
- submitting fiscal receipt status transition
- preparing tax return period
- submitting tax filing
- cancelling fiscal receipt

Use row locking for:

- fiscal receipt during submit/retry
- tax return period during prepare/submit/close
- receipt/refund source during fiscalization

Reason:

Two workers must not submit the same fiscal receipt twice.

## 24. Query and performance rules

All list endpoints use simple pagination.

Frequent filters:

```text
tax_authorities.company_id/authority_type/status
tax_registrations.company_id/registration_type/status
tax_registrations.branch_id/registration_type/status
tax_transactions.company_id/transaction_direction/tax_date
tax_transactions.branch_id/transaction_direction/tax_date
tax_transactions.tax_code_id/tax_date
fiscal_devices.branch_id/device_type/status
fiscal_receipts.branch_id/status/created_at
fiscal_receipts.fiscal_device_id/status
tax_return_periods.company_id/return_type/status/period_start
tax_filing_submissions.tax_return_period_id/status
```

Do not generate tax returns by scanning all operational sales tables directly.

Correct approach:

```text
operational event creates tax_transactions
tax return aggregates tax_transactions by date/type/code
```

This keeps return preparation controlled and faster.

## 25. Storage and retention

Keep normalized records long-term:

- tax transactions
- fiscal receipts
- fiscal receipt lines
- tax return periods
- tax return lines
- filing submission summary

Treat large provider payloads carefully:

- `fiscal_receipts.request_payload`
- `fiscal_receipts.response_payload`
- `tax_filing_submissions.request_payload`
- `tax_filing_submissions.response_payload`
- `webhook_receipts.raw_payload`

Recommended:

```text
keep raw payload for configured retention window
archive/delete large payloads later
preserve fiscal number, QR URL, verification code, status and summary fields
```

This avoids uncontrolled storage growth.

## 26. Access control

Recommended permissions:

```text
tax_setup.view
tax_setup.manage

tax_transactions.view

fiscal_devices.view
fiscal_devices.manage

fiscal_receipts.view
fiscal_receipts.submit
fiscal_receipts.retry
fiscal_receipts.cancel

tax_returns.view
tax_returns.prepare
tax_returns.submit
tax_returns.close
tax_returns.reopen

tax_filing_submissions.view
```

Cashier usually does not manage tax setup. Cashier may only see fiscal status for receipts they issue.

Accountant manages tax returns. Admin/support should not read tenant financial tax details unless tenant grants support access.

## 27. Audit logging

Log critical tax/compliance events:

- tax registration created/changed
- tax code account mapping changed
- fiscal device changed
- fiscal receipt submitted
- fiscal receipt accepted/rejected
- fiscal receipt cancelled
- tax return prepared
- tax return submitted
- tax return accepted/rejected
- tax return closed/reopened

Do not log every tax transaction with huge JSON. Tax transaction rows are already the tax ledger.

## 28. Error handling examples

Fiscal provider unavailable:

```text
Fiscal receipt remains pending/submitted
Retry job scheduled
Cashier sees fiscal pending
Payment/receipt is not duplicated
```

Fiscal rejected:

```text
Fiscal receipt status = rejected
rejection_reason stored
Manager/accountant reviews
Correction or retry follows provider rules
```

Duplicate webhook:

```text
webhook_receipts unique provider event id
Duplicate ignored safely
```

## 29. First release recommendation

Implement first:

- tax authorities
- tax registrations
- tax code account mapping
- tax exemption reasons
- tax transactions from sales/refunds/supplier bills
- fiscal devices
- fiscal receipt creation
- fiscal receipt submit/retry skeleton
- fiscal receipt status display on receipt screen
- tax return period prepare from tax transactions
- manual filing submission record

Later:

- full TRA/VFD provider automation after provider contract is known
- automated tax filing submission
- advanced fiscal correction workflow
- withholding tax module
- local levy automation
- tax audit export package
