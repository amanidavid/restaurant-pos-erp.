# API Design 08: Inventory, Purchasing, Stock Control, Transfers, Counts, and Waste

Project: Restaurant POS ERP  
Backend: Laravel, PHP 8.3, PostgreSQL, database-per-tenant multitenancy  
API version: v1  
Date: 2026-08-25

## 1. Purpose

This design explains how the restaurant controls ingredients, suppliers, purchases, receiving, stock batches, transfers, stock counts, waste and stock movement history.

Main flow:

```text
Supplier is registered
Purchase order is created
Goods are received
Stock batch is created
Stock movement is recorded
Ingredients are issued/consumed/transferred/wasted/counted
Stock balance remains explainable
```

This module is financially sensitive because stock mistakes become food-cost mistakes.

## 2. What this design depends on

This module depends on:

- Design 03: company, branch, warehouses, locations, numbering
- Design 04: tenant users, permissions, branch access
- Design 05: ingredients, units, recipes
- Design 06: POS orders
- Design 07: kitchen production

Reason:

Stock belongs to a branch warehouse/location, ingredients are consumed by recipes, and production/sales can later trigger recipe-based stock deduction.

## 3. Route placement

Tenant APIs belong in:

```text
routes/api/v1/web.php
```

Recommended source folders:

```text
app/Http/Controllers/Web/Inventory
app/Http/Controllers/Web/Purchasing
app/Http/Requests/Web/Inventory
app/Http/Requests/Web/Purchasing
app/Http/Resources/Web/Inventory
app/Http/Resources/Web/Purchasing
app/Services/Inventory
app/Services/Purchasing
```

No `Api/V1` folder is needed. Versioning stays in route files and URL prefixes.

## 4. Tables used

Tenant tables:

- `supplier_categories`
- `suppliers`
- `supplier_category_supplier`
- `supplier_contacts`
- `ingredients`
- `stock_units`
- `stock_item_settings`
- `warehouses`
- `locations`
- `purchase_orders`
- `purchase_order_items`
- `goods_receipts`
- `goods_receipt_items`
- `stock_batches`
- `stock_movement_types`
- `stock_movements`
- `stock_adjustments`
- `stock_adjustment_items`
- `stock_transfers`
- `stock_transfer_items`
- `stock_counts`
- `stock_count_items`
- `waste_records`
- `waste_record_items`
- `recipes`
- `recipe_ingredients`

## 5. Supplier design

`suppliers` stores the organization/person that provides goods or services.

Examples:

- Meat supplier
- Vegetable supplier
- Soft drink distributor
- Packaging supplier
- Cleaning material supplier

Supplier details:

- company
- name
- TIN
- VRN
- status
- notes

`supplier_contacts` stores people or contact channels for the supplier.

Examples:

```text
General contact
Sales contact
Accounts contact
Support contact
```

One supplier can have many contacts.

Example:

```text
Supplier: Kilimanjaro Meat Supplies
Sales contact: John, +255...
Accounts contact: Asha, accounts@...
```

## 6. Supplier categories

Supplier categories classify suppliers.

Examples:

- Meat
- Vegetables
- Beverages
- Packaging
- Cleaning

A supplier can belong to many categories.

Example:

```text
Supplier: ABC Foods
Categories:
  - Meat
  - Frozen foods
  - Packaging
```

That is why the schema uses:

```text
supplier_category_supplier
```

This avoids forcing one supplier into only one category.

## 7. Ingredient and stock settings

`ingredients` is the master item.

Examples:

- Salt
- Beef patty
- Cooking oil
- Burger bun
- Tomato
- Soda bottle

`stock_item_settings` controls whether an ingredient is tracked as stock.

Important settings:

- `default_supplier_id`
- `default_purchase_unit_id`
- `default_issue_unit_id`
- `minimum_stock_level`
- `maximum_stock_level`
- `reorder_level`
- `is_stock_item`
- `is_perishable`
- `track_batches`
- `allow_negative_stock`

Example:

```text
Ingredient: Beef patty
is_stock_item = true
is_perishable = true
track_batches = true
allow_negative_stock = false
reorder_level = 20 kg
```

Example:

```text
Ingredient: Salt
is_stock_item = true
is_perishable = false
track_batches = false
```

## 8. Stock units

`stock_units` defines unit conversions for an ingredient.

Example:

```text
Ingredient: Cooking oil
Base unit: ml
Purchase unit: litre
conversion_factor = 1000
Issue unit: ml
Count unit: litre
```

This allows purchasing in one unit and recipe consumption in another unit.

Example:

```text
Buy oil in litres
Recipe uses 30 ml
Stock system deducts 30 ml correctly
```

Use decimal quantity with 4 places because restaurant inventory can require fractions:

```text
0.125 kg
0.250 litre
12.500 kg
```

Money remains 2 decimal places. Quantity can be 4 decimal places.

## 9. Warehouses and locations

`warehouses` are branch-level stock areas.

Examples:

- Main Store
- Kitchen Store
- Bar Store
- Transit Store

`locations` are physical or logical sub-areas.

Examples:

- Cold Room
- Dry Shelf A
- Freezer 1
- Bar Fridge

Warehouse answers:

```text
Which store owns this stock?
```

Location answers:

```text
Where inside the store is it kept?
```

## 10. Purchase order

`purchase_orders` is the purchasing request/document sent to supplier.

Example:

```text
PO-2026-01
Branch: Mikocheni
Supplier: ABC Foods
Warehouse: Main Store
Status: draft
```

Purchase order statuses:

```text
draft
submitted
approved
partially_received
received
cancelled
```

Meaning:

- `draft`: being prepared
- `submitted`: sent for review/approval
- `approved`: approved for purchasing
- `partially_received`: some items received
- `received`: all expected items received
- `cancelled`: no longer active

Purchase order creation should accept item arrays.

Example:

```json
{
  "supplier_uuid": "uuid",
  "warehouse_uuid": "uuid",
  "expected_on": "2026-08-28",
  "items": [
    {
      "ingredient_uuid": "uuid",
      "unit_uuid": "uuid",
      "quantity": "10.0000",
      "unit_price": "15000.00"
    },
    {
      "ingredient_uuid": "uuid",
      "unit_uuid": "uuid",
      "quantity": "5.0000",
      "unit_price": "8000.00"
    }
  ]
}
```

This is not simple one-row CRUD because totals must be recalculated from item rows.

## 11. Goods receipt

`goods_receipts` records stock physically received.

It can be linked to a purchase order or created directly if the business receives without PO.

Example:

```text
GRN-2026-01
Supplier: ABC Foods
Warehouse: Main Store
Received by: Storekeeper
Status: draft
```

Goods receipt statuses:

```text
draft
posted
cancelled
```

Important rule:

Draft goods receipt does not increase stock.

Only posting the goods receipt should:

```text
create/update stock_batches
create stock_movements
increase quantity_on_hand
```

This prevents accidental stock increase while a receipt is still being edited.

## 12. Stock batches

`stock_batches` stores quantity by ingredient, warehouse, location, batch and cost.

Example:

```text
Ingredient: Beef patty
Warehouse: Main Store
Batch: BEEF-20260825-A
Received: 2026-08-25
Expires: 2026-09-05
Quantity on hand: 25.0000 kg
Unit cost: 9000.0000
Status: active
```

Batch tracking is important for:

- expiry
- food safety
- cost tracing
- first-expiry-first-out consumption
- audit trail

For non-perishable/simple items, `track_batches` can be false.

## 13. Stock movement types

`stock_movement_types` defines the reason/direction of movement.

Examples:

```text
goods_receipt -> in
purchase_return -> out
stock_adjustment_in -> in
stock_adjustment_out -> out
transfer_out -> out
transfer_in -> in
recipe_consumption -> out
waste -> out
stock_count_gain -> in
stock_count_loss -> out
```

This is better than a free text `movement_type`.

Reason:

Foreign key movement types are controlled, seedable and reportable.

## 14. Stock movements

`stock_movements` is the immutable inventory ledger.

It answers:

```text
Why did stock change?
When did it change?
Which ingredient/warehouse/batch changed?
Who performed it?
What source document caused it?
```

Movement links to exactly one source:

- goods receipt item
- stock adjustment item
- stock transfer item
- stock count item
- waste record item

The schema enforces this with a single-source check.

Important:

Do not edit old stock movements casually. If correction is needed, create a new reversing/correction movement.

## 15. Stock adjustment

`stock_adjustments` handles controlled manual correction.

Examples:

- damage
- expiry
- count variance
- manual correction
- other

Statuses:

```text
draft
approved
posted
cancelled
```

Rule:

Adjustment should not affect stock until posted.

For negative adjustment:

```text
quantity_delta = -2.0000
```

For positive adjustment:

```text
quantity_delta = 3.5000
```

Because this affects stock and value, approval should be required above configured thresholds.

## 16. Stock transfer

`stock_transfers` moves stock between branches/warehouses.

Example:

```text
TRF-2026-01
From: Mikocheni Main Store
To: Masaki Main Store
Status: draft
```

Statuses:

```text
draft
approved
dispatched
received
cancelled
```

Transfer has two physical moments:

```text
dispatch from source -> stock out
receive at destination -> stock in
```

This is why transfer items track:

- quantity requested
- quantity dispatched
- quantity received

Example:

```text
Requested: 10 kg beef
Dispatched: 10 kg beef
Received: 9.5 kg beef
Variance: 0.5 kg
```

That variance must be reviewed and approved, not hidden.

## 17. Stock count

`stock_counts` records physical counting.

Example:

```text
CNT-2026-01
Warehouse: Main Store
Status: draft
```

Stock count item stores:

- system quantity
- counted quantity
- variance quantity

Example:

```text
System says: 20.0000 kg
Staff counted: 18.5000 kg
Variance: -1.5000 kg
```

Posting stock count should create stock movements for differences.

Do not update stock directly without a movement record.

## 18. Waste record

`waste_records` records stock wasted/spoiled/damaged.

Examples:

- expired milk
- burnt food
- damaged packaging
- spoiled vegetables

Statuses:

```text
draft
posted
cancelled
```

Posting waste should:

```text
deduct stock
create stock_movements
optionally trigger approval if high value
```

Waste is critical because it directly affects food cost.

## 19. Recipe-driven stock deduction

Recipe stock deduction should not be done blindly at order draft stage.

Recommended first-release trigger:

```text
Deduct stock when order item is sent to kitchen
```

Reason:

At that point the restaurant has committed to production.

Example recipe:

```text
Beef Burger:
  Bun: 1 piece
  Beef patty: 0.150 kg
  Lettuce: 0.030 kg
  Sauce: 0.020 litre
```

Order:

```text
2 Beef Burgers
```

Stock deduction:

```text
Bun: 2 pieces
Beef patty: 0.300 kg
Lettuce: 0.060 kg
Sauce: 0.040 litre
```

This should create stock movements with movement type:

```text
recipe_consumption
```

Important:

Recipe deduction should be idempotent. Sending the same order item to kitchen twice must not deduct stock twice.

Implementation should record a link between deduction and source item. If current `stock_movements` does not yet link directly to `order_item_id`, we should add a dedicated production consumption source table later, for example:

```text
production_consumptions
production_consumption_items
```

For first release, do not fake it with free text only. Use a controlled source record.

## 20. Negative stock rule

Default should be:

```text
allow_negative_stock = false
```

If stock is insufficient:

```text
Block posting/deduction
or require manager override
```

Example:

```text
System has 1 kg beef
Recipe requires 2 kg beef
```

Safe behavior:

```text
Do not silently make stock -1 kg
```

If a tenant insists on negative stock, allow it only by setting and permission.

## 21. FIFO / FEFO

For perishable stock, use FEFO:

```text
First Expiry, First Out
```

For non-perishable stock, FIFO is acceptable:

```text
First In, First Out
```

Example:

```text
Milk batch A expires 2026-08-27
Milk batch B expires 2026-09-02
Consume batch A first
```

This reduces waste and supports food safety.

## 22. Recommended endpoints

Supplier APIs:

```text
GET    /api/v1/supplier-categories
POST   /api/v1/supplier-categories
PATCH  /api/v1/supplier-categories/{supplier_category_uuid}

GET    /api/v1/suppliers
POST   /api/v1/suppliers
GET    /api/v1/suppliers/{supplier_uuid}
PATCH  /api/v1/suppliers/{supplier_uuid}
POST   /api/v1/suppliers/{supplier_uuid}/contacts
PATCH  /api/v1/suppliers/{supplier_uuid}/contacts/{contact_uuid}
```

Stock setup APIs:

```text
GET   /api/v1/stock-units
POST  /api/v1/ingredients/{ingredient_uuid}/stock-units
PATCH /api/v1/ingredients/{ingredient_uuid}/stock-settings
```

Purchasing APIs:

```text
GET   /api/v1/purchase-orders
POST  /api/v1/purchase-orders
GET   /api/v1/purchase-orders/{purchase_order_uuid}
PATCH /api/v1/purchase-orders/{purchase_order_uuid}
POST  /api/v1/purchase-orders/{purchase_order_uuid}/submit
POST  /api/v1/purchase-orders/{purchase_order_uuid}/approve
POST  /api/v1/purchase-orders/{purchase_order_uuid}/cancel
```

Goods receipt APIs:

```text
GET  /api/v1/goods-receipts
POST /api/v1/goods-receipts
GET  /api/v1/goods-receipts/{goods_receipt_uuid}
POST /api/v1/goods-receipts/{goods_receipt_uuid}/post
POST /api/v1/goods-receipts/{goods_receipt_uuid}/cancel
```

Stock operation APIs:

```text
GET  /api/v1/stock-batches
GET  /api/v1/stock-movements

GET  /api/v1/stock-adjustments
POST /api/v1/stock-adjustments
POST /api/v1/stock-adjustments/{stock_adjustment_uuid}/approve
POST /api/v1/stock-adjustments/{stock_adjustment_uuid}/post

GET  /api/v1/stock-transfers
POST /api/v1/stock-transfers
POST /api/v1/stock-transfers/{stock_transfer_uuid}/approve
POST /api/v1/stock-transfers/{stock_transfer_uuid}/dispatch
POST /api/v1/stock-transfers/{stock_transfer_uuid}/receive

GET  /api/v1/stock-counts
POST /api/v1/stock-counts
POST /api/v1/stock-counts/{stock_count_uuid}/post

GET  /api/v1/waste-records
POST /api/v1/waste-records
POST /api/v1/waste-records/{waste_record_uuid}/post
```

## 23. Complexity classification

Simple CRUD:

- supplier categories
- suppliers
- supplier contacts
- stock units
- stock item settings

Medium complexity:

- purchase order create/update with item array and totals
- goods receipt draft creation
- stock batch list
- stock movement list
- stock count draft
- waste draft

High complexity:

- post goods receipt
- post stock adjustment
- dispatch/receive transfer
- post stock count
- post waste record
- recipe-driven deduction
- reversal/correction after posting

Queue required:

- large imports
- exports
- reorder alert generation
- expiry alert generation
- report generation
- post-commit realtime/notification outbox

No queue required:

- simple paginated listing
- simple supplier/category setup
- small draft creation

## 24. Transactions and locking

Use transactions for:

- purchase order create/update with items
- approve purchase order
- post goods receipt
- post stock adjustment
- dispatch transfer
- receive transfer
- post stock count
- post waste
- recipe deduction

Use row locking for:

- stock batch rows being consumed/updated
- source documents being posted
- transfer records during dispatch/receive
- stock count records during posting

Reason:

Two users or workers must not consume the same stock batch quantity at the same time.

## 25. Query and performance rules

All list endpoints use simple pagination.

Default:

```text
per_page = 15
max per_page = 100
```

Frequent filters:

```text
suppliers.company_id
suppliers.status
supplier_categories.company_id
warehouses.branch_id
locations.branch_id
purchase_orders.branch_id/status/ordered_on
goods_receipts.branch_id/status/received_on
stock_batches.warehouse_id/ingredient_id/status
stock_batches.expires_on/status
stock_movements.ingredient_id/moved_at
stock_movements.warehouse_id/stock_movement_type_id/moved_at
stock_transfers.from_branch_id/status/requested_on
stock_transfers.to_branch_id/status/requested_on
stock_counts.branch_id/status/counted_on
waste_records.branch_id/status/recorded_on
```

Avoid:

```text
full tenant stock recalculation for every screen load
```

Prefer:

```text
stock_batches.quantity_on_hand as current balance per batch
stock_movements as audit ledger
indexed batch/movement queries for history
```

Do not duplicate report data. Generate reports from movements/batches and cache only short-lived expensive results if required.

## 26. Branch-scoped numbering

Inventory operational documents are branch-scoped.

Use:

```text
purchase_orders: unique(branch_id, order_number)
goods_receipts: unique(branch_id, receipt_number)
stock_adjustments: unique(branch_id, adjustment_number)
stock_transfers: unique(from_branch_id, transfer_number)
stock_counts: unique(branch_id, count_number)
waste_records: unique(branch_id, waste_number)
```

Reason:

Branch A and Branch B can both have:

```text
PO-2026-01
GRN-2026-01
```

without conflict.

## 27. Access control

Recommended permissions:

```text
suppliers.view
suppliers.create
suppliers.update
suppliers.block

purchase_orders.view
purchase_orders.create
purchase_orders.update
purchase_orders.submit
purchase_orders.approve
purchase_orders.cancel

goods_receipts.view
goods_receipts.create
goods_receipts.post
goods_receipts.cancel

stock_batches.view
stock_movements.view

stock_adjustments.view
stock_adjustments.create
stock_adjustments.approve
stock_adjustments.post

stock_transfers.view
stock_transfers.create
stock_transfers.approve
stock_transfers.dispatch
stock_transfers.receive

stock_counts.view
stock_counts.create
stock_counts.post

waste_records.view
waste_records.create
waste_records.post
```

Branch scoping applies.

Example:

Storekeeper can create goods receipt for assigned branch. Manager approves adjustment. Auditor views movements but cannot edit.

## 28. Audit logging

Do not log every stock row update with huge before/after JSON.

Log critical events:

- purchase order approved/cancelled
- goods receipt posted/cancelled
- stock adjustment approved/posted
- transfer approved/dispatched/received
- stock count posted
- waste posted
- negative stock override
- high-value variance

`stock_movements` already gives the operational stock audit trail. Spatie activity log should focus on control events and approvals.

## 29. Import and export

Imports likely needed:

- suppliers
- ingredients
- opening stock balances
- purchase order items
- stock count items

Exports likely needed:

- supplier list
- stock balance
- stock movement history
- expiry list
- purchase order
- goods receipt
- stock count variance

Large imports/exports must use queues.

Recommended:

```text
Upload file
Create import job record
Queue processing job in landlord queue
Worker connects to tenant DB
Validate rows
Store errors
Commit valid rows in chunks
Return downloadable error file if needed
```

Do not process large Excel files inside the HTTP request.

## 30. First release recommendation

Implement first:

- supplier categories with many-to-many supplier assignment
- suppliers and contacts
- stock settings and units
- purchase order create/approve
- goods receipt post
- stock batches
- stock movement history
- stock adjustment post
- stock transfer dispatch/receive
- stock count post
- waste post
- basic expiry/reorder alerts

Later:

- supplier price history
- procurement comparison/quotations
- advanced landed cost
- barcode scanning
- automated purchase suggestions
- advanced recipe yield/waste costing
- production consumption source tables if recipe deduction needs more detailed traceability
