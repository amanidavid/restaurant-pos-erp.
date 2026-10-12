# Business-day and cash-account history

## Shared request contract

Base URL: your Laravel server + `/api/v1/web`; localhost:3001 works only with a configured proxy to Laravel. All endpoints below are GET requests with no JSON body.

Required headers:

```http
Authorization: Bearer <access_token>
X-Tenant-UUID: <tenant_uuid>
X-Branch-UUID: <branch_uuid>
Accept: application/json
```

Route UUIDs must belong to the tenant and selected branch. For business history, the branch UUID in the URL must match the branch header.

## Filters

Both history lists accept `period=today|this_week|this_month|custom` (default today), `page` (default 1), and `per_page` (default 15, maximum 100). Custom requires `from` and `to` in YYYY-MM-DD format, inclusive; to must be on/after from. Dates are rejected for noncustom presets. Weeks run Monday–Sunday. Presets use the branch timezone. Periods filter the business_date, not transaction timestamps, so overnight activity remains attached to its business day.

Examples:

```text
?period=today
?period=this_week
?period=this_month&page=1&per_page=15
?period=custom&from=2026-10-01&to=2026-10-06
```

## 1. Business-day history

```http
GET /api/v1/web/branches/{branch_uuid}/business-days?period=this_month
```

Permission: `business_dates.view`, plus branch access. Status 200.

Example response (values illustrative; pagination may include additional standard Laravel fields):

```json
{
  "data": [
    {
      "uuid": "<business_day_uuid>",
      "business_date": "2026-10-06",
      "business_day_number": "BD-20261006-01",
      "status": "closed",
      "opened_at": "2026-10-06T05:00:00+00:00",
      "closed_at": "2026-10-06T19:00:00+00:00",
      "orders_count": 25,
      "net_sales_amount": "150000.00"
    }
  ],
  "filters": { "period": "this_month", "from": "2026-10-01", "to": "2026-10-31" },
  "summary": { "net_sales_amount": "150000.00", "orders_count": 25, "open_business_days_count": 0 },
  "links": { "first": "<page_1_url>", "last": "<page_1_url>", "prev": null, "next": null },
  "meta": { "current_page": 1, "from": 1, "last_page": 1, "per_page": 15, "to": 1, "total": 1 },
  "success": true,
  "message": "Business day history retrieved successfully.",
  "errors": null
}
```

Cards: Net sales, Orders, Open business days. Use summary values, never sum the displayed page to calculate cards.

Net sales is closed-order subtotal minus order discount, excluding tax, service charge, and tips. Completed refunds linked through payments to those orders reduce net sales. Refunds store gross amounts without merchandise/tax allocation, so the deducted merchandise portion is proportional: refund amount × (subtotal − discount) / order total. Pending/failed refunds are excluded. A later refund adjusts the original order's business-day history; this is a live report, not an immutable sales snapshot. Reservation refunds without a direct payment.order_id are not included. A zero-total order has no proportional refund contribution.

Orders counts non-draft, noncancelled orders, including unfinished orders. Thus order counts can grow while net sales remains zero until orders close. Open-business-day count includes open and closing statuses within the selected period.

Table: Business day, Status, Opened, Closed, Orders, Net sales. Omit opening/closing cash from this table. Closed time is null while open. Display timestamps in the selected branch timezone. Newest business dates come first, with stable ID ordering for ties.

## 2. Cash-account reconciliation history

```http
GET /api/v1/web/cash-accounts/{cash_account_uuid}/reconciliations?period=this_month
```

Permission: `cash_reconciliations.view`, plus branch access. Status 200. Use the account UUID from existing reconciliation responses, not the reconciliation UUID.

```json
{
  "data": [
    {
      "uuid": "<reconciliation_uuid>",
      "business_date": "2026-10-06",
      "status": "closed",
      "opening_float_amount": "10000.00",
      "expected_cash_amount": "110000.00",
      "counted_cash_amount": "109500.00",
      "variance_amount": "-500.00",
      "closed_at": "2026-10-06T19:00:00+00:00"
    }
  ],
  "cash_account": { "uuid": "<cash_account_uuid>", "name": "Cash Drawer", "currency_code": "TZS" },
  "filters": { "period": "this_month", "from": "2026-10-01", "to": "2026-10-31" },
  "summary": { "expected_cash_amount": "110000.00", "counted_cash_amount": "109500.00", "variance_amount": "-500.00" },
  "links": { "first": "<page_1_url>", "last": "<page_1_url>", "prev": null, "next": null },
  "meta": { "current_page": 1, "from": 1, "last_page": 1, "per_page": 15, "to": 1, "total": 1 },
  "success": true,
  "message": "Cash account reconciliation history retrieved successfully.",
  "errors": null
}
```

Show account name/currency once above the table. Cards: Expected cash, Counted cash, Variance. Cards sum only CLOSED reconciliations across the entire filtered period; label them "Closed reconciliations". Their sum is a history total, not a current account balance.

Table includes open/closing records, but their expected/count/variance history fields are null to avoid presenting provisional figures as final. Their opening float remains available. Use a dash for null, not zero. Negative variance is a shortage; positive is an excess.

## 3. Open a history row

Use existing detail endpoint:

```http
GET /api/v1/web/cash-reconciliations/{reconciliation_uuid}
```

It returns cash account details, opening float, expected/count/variance, saved denomination `lines`, notes, and opening/closing times. For open/closing reconciliations its totals are live previews; closed records use saved final totals. See `cash-reconciliation-frontend.md` for the full object and write endpoints.

Load its transactions separately:

```http
GET /api/v1/web/cash-reconciliations/{reconciliation_uuid}/movements?page=1&per_page=15
```

Permission: `cash_reconciliations.view`. Status 200. No period parameter: this list is scoped to that account and business day. Movements are ordered newest first.

```json
{
  "data": [
    {
      "uuid": "<movement_uuid>",
      "movement_reason": "cash_out",
      "amount": "5000.00",
      "moved_at": "2026-10-06T10:00:00+00:00",
      "notes": "Kitchen supplies"
    }
  ],
  "links": { "first": "<page_1_url>", "last": "<page_1_url>", "prev": null, "next": null },
  "meta": { "current_page": 1, "from": 1, "last_page": 1, "per_page": 15, "to": 1, "total": 1 },
  "success": true,
  "message": "Cash reconciliation movements retrieved successfully.",
  "errors": null
}
```

Account identity is not repeated per movement; the selected row/detail already supplies it. Money fields are decimal strings. Expected reconciliation cash = opening float + cash_sale + cash_in − cash_out − safe_drop − cash_refund. The transaction list may include other ledger reasons, but only these reasons affect this calculation. Do not calculate totals from one movement page; use the detail totals.

## Frontend behavior

1. Put Business days and Cash reconciliation under the Finance dropdown.
2. Reset page to 1 when period, custom dates, branch, or account changes.
3. Include tenant/branch/account/period/dates/page in query cache keys. Never retain another branch's cards during switching.
4. Use filters.from/to returned by the server for the displayed date range.
5. Use server pagination links/meta; cards remain the same when paging.
6. Load detail and movements only after opening a row. Populate denomination quantities from saved lines.
7. Refresh business history after order closure, refund completion, or business-day changes. Refresh account history/detail/movements after count, movement, or reconciliation closure.
8. Empty periods return an empty data array and zero cards. Display an empty state.

400 missing/invalid branch header; 401 authentication/session; 403 permission/branch access; 404 out-of-scope UUID or branch URL/header mismatch; 422 invalid filters/page size; 429 request limit (60/minute on the new endpoints). Show top-level message and field errors. No new permissions were introduced.

## Backend rollout and verification

Apply the additive tenant index migration using the project's tenant migration process:

```powershell
php artisan tenants:artisan "migrate --database=tenant --path=database/migrations/tenant --force"
```

The migration indexes business-day date filtering and account/day movement ordering. It has NOT been executed as part of implementation. Existing seeded permissions are reused. Automated tests cover isolated database summaries, pagination, refunds, period rules, middleware registration, and movement scope; authenticated HTTP and production query plans remain unverified.
