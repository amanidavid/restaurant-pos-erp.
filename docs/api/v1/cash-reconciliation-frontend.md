# Cash reconciliation frontend contract

Base path: `/api/v1/web`. Use the running Laravel origin, or a frontend proxy that forwards this path to Laravel. Route paths do not include the server port.

All six endpoints require `Authorization: Bearer <token>`, `X-Tenant-UUID`, `X-Branch-UUID`, and `Accept: application/json`. JSON writes also require `Content-Type: application/json`. All route identifiers below are UUIDs.

## Endpoints

| Method | Path | Permission | Success |
|---|---|---|---|
| POST | `/business-days/{business_day_uuid}/cash-reconciliations` | `cash_reconciliations.open` | 201 |
| GET | `/business-days/{business_day_uuid}/cash-reconciliations?page=1` | `cash_reconciliations.view` | 200 |
| GET | `/cash-reconciliations/{reconciliation_uuid}` | `cash_reconciliations.view` | 200 |
| PUT | `/cash-reconciliations/{reconciliation_uuid}/count` | `cash_reconciliations.count` | 200 |
| POST | `/cash-reconciliations/{reconciliation_uuid}/close` | `cash_reconciliations.close` | 200 |
| POST | `/cash-accounts/{cash_account_uuid}/movements` | `cash_movements.create` | 201 |

Branch access is also enforced. Finance should be a collapsible sidebar group; show actions according to the permissions above.

## Open and retrieve

Open payload: `{}`. Opens reconciliations for all active accounts requiring reconciliation in the selected branch. Existing reconciliations are returned without resetting their counts or status. The response `data` is an array.

List and show have no payload. List uses simple pagination, fixed at 15 per page. Use `links.next`/`links.prev` and pagination metadata; a total count or last page is not supplied by simple pagination. Show returns one object.

## Save count

```json
{
  "lines": [
    { "denomination_value": "10000.00", "quantity": 5 },
    { "denomination_value": "5000.00", "quantity": 4 }
  ],
  "notes": "Physical cash counted."
}
```

`lines` is a required, nonempty array, maximum 30 items. Denominations must be positive, distinct numeric values with up to two decimal places. Quantity is an integer from 1 to 1000000. Notes are optional/nullable, maximum 2000 characters. Send only counted denominations; zero quantity is not accepted. The current contract cannot submit an entirely zero-cash count.

PUT replaces ALL previous count lines. Status becomes `closing`. Counts can be corrected while `closing`, but not after `closed`. Omitting notes or sending null preserves existing notes in count/close operations. An empty string is generally normalized to null by Laravel middleware.

## Close reconciliation

```json
{ "notes": "Count checked and reconciliation completed." }
```

An empty object is valid. Notes are optional/nullable, maximum 2000 characters. Save a count first: closing requires status `closing`. The server recalculates the final cash totals and saves them with status `closed`. A variance does not itself prevent closure in the current implementation.

## Reconciliation object

Example values only:

```json
{
  "data": {
    "uuid": "<reconciliation_uuid>",
    "reconciliation_number": "CASH-001",
    "status": "closing",
    "cash_account": {
      "uuid": "<cash_account_uuid>",
      "name": "Cash Drawer",
      "currency_code": "TZS"
    },
    "opening_float_amount": "10000.00",
    "expected_cash_amount": "71000.00",
    "counted_cash_amount": "70000.00",
    "variance_amount": "-1000.00",
    "lines": [
      { "denomination_value": "10000.00", "quantity": 5, "line_amount": "50000.00" },
      { "denomination_value": "5000.00", "quantity": 4, "line_amount": "20000.00" }
    ],
    "opened_at": "2026-10-06T06:00:00+00:00",
    "closed_at": null,
    "notes": "Physical cash counted."
  },
  "success": true,
  "message": "Cash count saved successfully.",
  "errors": null
}
```

Open/list use arrays of this same object. Details, count, and close use one object. Lines are ordered by denomination descending and are `[]` before counting. Money values are decimal strings. Counted/variance values are null before counting. Timestamps are ISO 8601 or null; format them in the branch timezone for display.

For `open`/`closing`, totals are a current server-calculated preview, not saved closing totals. For `closed`, the amounts are the saved final totals. Expected = opening float + cash_sale + cash_in - cash_out - safe_drop - cash_refund. Counted = sum of saved line amounts. Variance = counted - expected; negative indicates a shortage. Noncash payments do not contribute to physical cash.

Populate the denomination form from `lines`; map quantities by numeric denomination value so `10000` and `10000.00` match. Use `cash_account.name` as the card title and `cash_account.uuid` for movements. Refetch details when opening the close modal. Display server totals rather than submitting expected/counted amounts.

## Record movement

```json
{
  "business_day_uuid": "<business_day_uuid>",
  "movement_reason": "cash_out",
  "amount": "5000.00",
  "notes": "Paid for emergency kitchen supplies."
}
```

Business day UUID, reason, amount, and notes are required. Reason is `cash_in`, `cash_out`, or `safe_drop`. Amount is positive with up to two decimal places; maximum 9999999999999999.99. Notes maximum 1000 characters. The account must have an `open` reconciliation for the selected active business day. Movement is unavailable while `closing`/`closed`.

Successful response:

```json
{
  "data": {
    "uuid": "<movement_uuid>",
    "cash_account": {
      "uuid": "<cash_account_uuid>",
      "name": "Cash Drawer",
      "currency_code": "TZS"
    },
    "business_day_uuid": "<business_day_uuid>",
    "movement_reason": "cash_out",
    "amount": "5000.00",
    "moved_at": "2026-10-06T10:00:00+00:00",
    "notes": "Paid for emergency kitchen supplies."
  },
  "success": true,
  "message": "Cash movement recorded successfully.",
  "errors": null
}
```

After a successful movement, invalidate/refetch reconciliation list and detail queries. After count/close, update the card from the returned object and invalidate related queries. Disable submit while pending. Movements currently have no idempotency key: do not automatically retry a movement after an uncertain network failure, because it may already have been saved.

## Status/actions

| Status | Record movement | Save/correct count | Close reconciliation |
|---|---|---|---|
| open | Yes | Yes | No: save count first |
| closing | No | Yes | Yes |
| closed | No | No | No |

Finish each required account, then call `POST /branches/{branch_uuid}/business-days/{business_day_uuid}/close` with optional `closing_notes`. Orders must also be closed. Shifts do not block payment collection or business-day closing. Payments require the order's own business day to be `open`; cash payments additionally require an open reconciliation when the account requires reconciliation. New payments and cash-sale movements have no mandatory shift association. There is no approval stage.

## Errors

400: missing/invalid branch header. 401: missing/invalid session/token. 403: insufficient permission or branch access. 404: missing/out-of-scope record. 422: field validation or invalid lifecycle transition.

Validation responses retain `success: false`, `data: null`, `errors` with field arrays, and `code: null`. The shared top-level `message` now contains the first nonempty validation error, unless the endpoint provides a custom message. Show it in the toast and render all field messages inline (including `lines.0.quantity`). Business-day close specifically uses `BUSINESS_DAY_CLOSE_BLOCKED` and a descriptive top-level message listing the actual blockers.

## Verification limits

Response/preview regressions run against isolated in-memory SQLite. These do not establish production tenant migrations, authenticated HTTP behavior, frontend integration, or concurrent-write behavior. No schema migration is needed for these response additions.
