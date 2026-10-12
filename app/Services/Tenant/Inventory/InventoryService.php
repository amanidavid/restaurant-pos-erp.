<?php

namespace App\Services\Tenant\Inventory;

use App\Models\Tenant\Branch;
use App\Models\Tenant\Ingredient;
use App\Models\Tenant\Location;
use App\Models\Tenant\NumberSequence;
use App\Models\Tenant\NumberSequenceAllocation;
use App\Models\Tenant\StockAdjustment;
use App\Models\Tenant\StockAdjustmentItem;
use App\Models\Tenant\StockBatch;
use App\Models\Tenant\StockCount;
use App\Models\Tenant\StockCountItem;
use App\Models\Tenant\StockMovement;
use App\Models\Tenant\StockMovementType;
use App\Models\Tenant\StockTransfer;
use App\Models\Tenant\StockTransferItem;
use App\Models\Tenant\TenantUser;
use App\Models\Tenant\UnitOfMeasure;
use App\Models\Tenant\Warehouse;
use Illuminate\Contracts\Pagination\Paginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class InventoryService
{
    /**
     * @return Paginator<int, Warehouse>
     */
    public function paginateWarehouses(Branch $branch, array $filters, int $perPage): Paginator
    {
        return Warehouse::query()
            ->select([
                'id',
                'uuid',
                'branch_id',
                'name',
                'type',
                'description',
                'status',
            ])
            ->with('branch:id,uuid,name')
            ->where('branch_id', $branch->id)
            ->when($filters['type'] ?? null, fn (Builder $query, string $type) => $query->where('type', $type))
            ->when($filters['status'] ?? null, fn (Builder $query, string $status) => $query->where('status', $status))
            ->when($filters['search'] ?? null, fn (Builder $query, string $search) => $query->where('name', 'like', trim($search).'%'))
            ->orderBy('name')
            ->orderBy('id')
            ->simplePaginate($perPage);
    }

    public function createWarehouse(Branch $branch, array $data): Warehouse
    {
        $this->assertWarehouseNameAvailable($branch, $data['name']);

        return Warehouse::query()
            ->create([
                'branch_id' => $branch->id,
                'name' => $data['name'],
                'type' => $data['type'],
                'description' => $data['description'] ?? null,
                'status' => $data['status'] ?? 'active',
            ])
            ->load('branch:id,uuid,name');
    }

    public function updateWarehouse(Warehouse $warehouse, array $data): Warehouse
    {
        return DB::connection('tenant')
            ->transaction(function () use ($warehouse, $data): Warehouse {
                $lockedWarehouse = Warehouse::query()
                    ->whereKey($warehouse->id)
                    ->lockForUpdate()
                    ->firstOrFail();
                if (isset($data['name']) && $data['name'] !== $lockedWarehouse->name) {
                    $this->assertWarehouseNameAvailable($lockedWarehouse->branch, $data['name'], $lockedWarehouse->id);
                }
                $lockedWarehouse->fill([
                    'name' => $data['name'] ?? $lockedWarehouse->name,
                    'type' => $data['type'] ?? $lockedWarehouse->type,
                    'description' => array_key_exists('description', $data) ? $data['description'] : $lockedWarehouse->description,
                    'status' => $data['status'] ?? $lockedWarehouse->status,
                ])
                    ->save();

                return $lockedWarehouse->fresh('branch:id,uuid,name');
            });
    }

    /**
     * @return Paginator<int, StockAdjustment>
     */
    public function paginateStockAdjustments(Branch $branch, array $filters, int $perPage): Paginator
    {
        return StockAdjustment::query()
            ->select([
                'id',
                'uuid',
                'branch_id',
                'warehouse_id',
                'requested_by_tenant_user_id',
                'approved_by_tenant_user_id',
                'adjustment_number',
                'reason_type',
                'status',
                'adjusted_on',
                'notes',
                'created_at',
            ])
            ->with([
                'branch:id,uuid,name',
                'warehouse:id,uuid,name,type',
                'requestedBy:id,uuid,display_name',
                'approvedBy:id,uuid,display_name',
            ])
            ->where('branch_id', $branch->id)
            ->when($filters['warehouse_uuid'] ?? null, fn (Builder $query, string $uuid) => $query->whereHas('warehouse', fn (Builder $warehouse) => $warehouse->where('uuid', $uuid)))
            ->when($filters['status'] ?? null, fn (Builder $query, string $status) => $query->where('status', $status))
            ->when($filters['reason_type'] ?? null, fn (Builder $query, string $reasonType) => $query->where('reason_type', $reasonType))
            ->when($filters['adjusted_from'] ?? null, fn (Builder $query, string $date) => $query->where('adjusted_on', '>=', $date))
            ->when($filters['adjusted_to'] ?? null, fn (Builder $query, string $date) => $query->where('adjusted_on', '<=', $date))
            ->latest('created_at')
            ->latest('id')
            ->simplePaginate($perPage);
    }

    public function createStockAdjustment(Branch $branch, TenantUser $actor, array $data): StockAdjustment
    {
        return DB::connection('tenant')
            ->transaction(function () use ($branch, $actor, $data): StockAdjustment {
                $warehouse = $this->warehouseForBranch($branch, $data['warehouse_uuid']);
                [$adjustmentNumber, $allocation] = $this->allocateNumber($branch, 'stock_adjustment', 'ADJ', 'stock_adjustments', 'Stock Adjustments');
                $adjustment = StockAdjustment::query()
                    ->create([
                        'branch_id' => $branch->id,
                        'warehouse_id' => $warehouse->id,
                        'requested_by_tenant_user_id' => $actor->id,
                        'adjustment_number' => $adjustmentNumber,
                        'reason_type' => $data['reason_type'],
                        'status' => 'draft',
                        'adjusted_on' => $data['adjusted_on'] ?? now()->toDateString(),
                        'notes' => $data['notes'] ?? null,
                    ]);
                $this->replaceAdjustmentItems($adjustment, $data['items']);
                $allocation->update([
                    'consumer_id' => $adjustment->id,
                    'consumer_uuid' => $adjustment->uuid,
                    'status' => 'used',
                    'used_at' => now(),
                ]);

                return $this->loadStockAdjustment($adjustment);
            });
    }

    public function updateStockAdjustment(StockAdjustment $adjustment, array $data): StockAdjustment
    {
        return DB::connection('tenant')
            ->transaction(function () use ($adjustment, $data): StockAdjustment {
                $lockedAdjustment = StockAdjustment::query()
                    ->whereKey($adjustment->id)
                    ->lockForUpdate()
                    ->firstOrFail();
                if ($lockedAdjustment->status !== 'draft') {
                    throw ValidationException::withMessages(['stock_adjustment' => ['Only draft stock adjustments can be updated.']]);
                }
                $warehouse = isset($data['warehouse_uuid']) ? $this->warehouseForBranch($lockedAdjustment->branch, $data['warehouse_uuid']) : $lockedAdjustment->warehouse;
                $lockedAdjustment->fill([
                    'warehouse_id' => $warehouse->id,
                    'reason_type' => $data['reason_type'] ?? $lockedAdjustment->reason_type,
                    'adjusted_on' => $data['adjusted_on'] ?? $lockedAdjustment->adjusted_on,
                    'notes' => array_key_exists('notes', $data) ? $data['notes'] : $lockedAdjustment->notes,
                ])
                    ->save();
                if (isset($data['items'])) {
                    $lockedAdjustment->items()
                        ->delete();
                    $this->replaceAdjustmentItems($lockedAdjustment, $data['items']);
                }

                return $this->loadStockAdjustment($lockedAdjustment);
            });
    }

    public function postStockAdjustment(StockAdjustment $adjustment, TenantUser $actor): StockAdjustment
    {
        return DB::connection('tenant')
            ->transaction(function () use ($adjustment, $actor): StockAdjustment {
                $lockedAdjustment = StockAdjustment::query()
                    ->with(['items.ingredient', 'items.location', 'warehouse'])
                    ->whereKey($adjustment->id)
                    ->lockForUpdate()
                    ->firstOrFail();
                if (! in_array($lockedAdjustment->status, ['draft', 'approved'], true)) {
                    throw ValidationException::withMessages(['stock_adjustment' => ['Only draft or approved stock adjustments can be posted.']]);
                }
                if ($lockedAdjustment->items->isEmpty()) {
                    throw ValidationException::withMessages(['items' => ['Please provide at least one adjustment item before posting.']]);
                }
                $movementTypes = StockMovementType::query()
                    ->whereIn('name', ['Stock Adjustment In', 'Stock Adjustment Out'])
                    ->where('status', 'active')
                    ->lockForUpdate()
                    ->get()
                    ->keyBy('name');
                $inType = $movementTypes->get('Stock Adjustment In');
                $outType = $movementTypes->get('Stock Adjustment Out');
                if (! $inType || ! $outType) {
                    throw ValidationException::withMessages(['stock_movement_type' => ['Stock adjustment movement types are not configured.']]);
                }
                foreach ($lockedAdjustment->items as $item) {
                    $delta = (float) $item->quantity_delta;
                    if ($delta > 0) {
                        $this->postPositiveAdjustmentItem($lockedAdjustment, $item, $actor, $inType);

                        continue;
                    }
                    $this->postNegativeAdjustmentItem($lockedAdjustment, $item, $actor, $outType);
                }
                $lockedAdjustment->update([
                    'status' => 'posted',
                    'approved_by_tenant_user_id' => $lockedAdjustment->approved_by_tenant_user_id ?: $actor->id,
                ]);

                return $this->loadStockAdjustment($lockedAdjustment);
            });
    }

    public function loadStockAdjustment(StockAdjustment $adjustment): StockAdjustment
    {
        return $adjustment->fresh([
            'branch:id,uuid,name',
            'warehouse:id,uuid,name,type',
            'requestedBy:id,uuid,display_name',
            'approvedBy:id,uuid,display_name',
            'items:id,uuid,stock_adjustment_id,ingredient_id,location_id,unit_id,quantity_delta,unit_cost,reason',
            'items.ingredient:id,uuid,sku,name',
            'items.location:id,uuid,name',
            'items.unit:id,uuid,name,symbol',
        ]);
    }

    /**
     * @return Paginator<int, StockTransfer>
     */
    public function paginateStockTransfers(Branch $branch, array $filters, int $perPage): Paginator
    {
        $direction = $filters['direction'] ?? 'outgoing';

        return StockTransfer::query()
            ->select([
                'id',
                'uuid',
                'from_branch_id',
                'from_warehouse_id',
                'to_branch_id',
                'to_warehouse_id',
                'requested_by_tenant_user_id',
                'approved_by_tenant_user_id',
                'transfer_number',
                'status',
                'requested_on',
                'dispatched_on',
                'received_on',
                'notes',
                'created_at',
            ])
            ->with([
                'fromBranch:id,uuid,name',
                'fromWarehouse:id,uuid,name,type',
                'toBranch:id,uuid,name',
                'toWarehouse:id,uuid,name,type',
                'requestedBy:id,uuid,display_name',
                'approvedBy:id,uuid,display_name',
            ])
            ->when($direction === 'incoming', fn (Builder $query) => $query->where('to_branch_id', $branch->id), fn (Builder $query) => $query->where('from_branch_id', $branch->id))
            ->when($filters['status'] ?? null, fn (Builder $query, string $status) => $query->where('status', $status))
            ->when($filters['requested_from'] ?? null, fn (Builder $query, string $date) => $query->where('requested_on', '>=', $date))
            ->when($filters['requested_to'] ?? null, fn (Builder $query, string $date) => $query->where('requested_on', '<=', $date))
            ->latest('created_at')
            ->latest('id')
            ->simplePaginate($perPage);
    }

    public function createStockTransfer(Branch $fromBranch, TenantUser $actor, array $data): StockTransfer
    {
        return DB::connection('tenant')
            ->transaction(function () use ($fromBranch, $actor, $data): StockTransfer {
                $toBranch = Branch::query()
                    ->where('uuid', $data['to_branch_uuid'])
                    ->first();
                if (! $toBranch) {
                    throw ValidationException::withMessages(['to_branch_uuid' => ['The receiving branch could not be found.']]);
                }
                $fromWarehouse = $this->warehouseForBranch($fromBranch, $data['from_warehouse_uuid']);
                $toWarehouse = $this->warehouseForBranch($toBranch, $data['to_warehouse_uuid']);
                if ($fromWarehouse->id === $toWarehouse->id) {
                    throw ValidationException::withMessages(['to_warehouse_uuid' => ['Choose a different receiving warehouse.']]);
                }
                [$transferNumber, $allocation] = $this->allocateNumber($fromBranch, 'stock_transfer', 'TRF', 'stock_transfers', 'Stock Transfers');
                $transfer = StockTransfer::query()
                    ->create([
                        'from_branch_id' => $fromBranch->id,
                        'from_warehouse_id' => $fromWarehouse->id,
                        'to_branch_id' => $toBranch->id,
                        'to_warehouse_id' => $toWarehouse->id,
                        'requested_by_tenant_user_id' => $actor->id,
                        'transfer_number' => $transferNumber,
                        'status' => 'draft',
                        'requested_on' => $data['requested_on'] ?? now()->toDateString(),
                        'notes' => $data['notes'] ?? null,
                    ]);
                $this->replaceTransferItems($transfer, $data['items']);
                $allocation->update([
                    'consumer_id' => $transfer->id,
                    'consumer_uuid' => $transfer->uuid,
                    'status' => 'used',
                    'used_at' => now(),
                ]);

                return $this->loadStockTransfer($transfer);
            });
    }

    public function updateStockTransfer(StockTransfer $transfer, array $data): StockTransfer
    {
        return DB::connection('tenant')
            ->transaction(function () use ($transfer, $data): StockTransfer {
                $lockedTransfer = StockTransfer::query()
                    ->whereKey($transfer->id)
                    ->lockForUpdate()
                    ->firstOrFail();
                if ($lockedTransfer->status !== 'draft') {
                    throw ValidationException::withMessages(['stock_transfer' => ['Only draft stock transfers can be updated.']]);
                }
                $toBranch = isset($data['to_branch_uuid']) ? Branch::query()
                    ->where('uuid', $data['to_branch_uuid'])
                    ->first() : $lockedTransfer->toBranch;
                if (! $toBranch) {
                    throw ValidationException::withMessages(['to_branch_uuid' => ['The receiving branch could not be found.']]);
                }
                $fromWarehouse = isset($data['from_warehouse_uuid']) ? $this->warehouseForBranch($lockedTransfer->fromBranch, $data['from_warehouse_uuid']) : $lockedTransfer->fromWarehouse;
                $toWarehouse = isset($data['to_warehouse_uuid']) ? $this->warehouseForBranch($toBranch, $data['to_warehouse_uuid']) : $lockedTransfer->toWarehouse;
                if ($fromWarehouse->id === $toWarehouse->id) {
                    throw ValidationException::withMessages(['to_warehouse_uuid' => ['Choose a different receiving warehouse.']]);
                }
                $lockedTransfer->fill([
                    'from_warehouse_id' => $fromWarehouse->id,
                    'to_branch_id' => $toBranch->id,
                    'to_warehouse_id' => $toWarehouse->id,
                    'requested_on' => $data['requested_on'] ?? $lockedTransfer->requested_on,
                    'notes' => array_key_exists('notes', $data) ? $data['notes'] : $lockedTransfer->notes,
                ])
                    ->save();
                if (isset($data['items'])) {
                    $lockedTransfer->items()
                        ->delete();
                    $this->replaceTransferItems($lockedTransfer, $data['items']);
                }

                return $this->loadStockTransfer($lockedTransfer);
            });
    }

    public function dispatchStockTransfer(StockTransfer $transfer, TenantUser $actor): StockTransfer
    {
        return DB::connection('tenant')
            ->transaction(function () use ($transfer, $actor): StockTransfer {
                $lockedTransfer = StockTransfer::query()
                    ->with(['items.ingredient', 'fromWarehouse'])
                    ->whereKey($transfer->id)
                    ->lockForUpdate()
                    ->firstOrFail();
                if (! in_array($lockedTransfer->status, ['draft', 'approved'], true)) {
                    throw ValidationException::withMessages(['stock_transfer' => ['Only draft or approved stock transfers can be dispatched.']]);
                }
                if ($lockedTransfer->items->isEmpty()) {
                    throw ValidationException::withMessages(['items' => ['Please provide at least one transfer item before dispatching.']]);
                }
                $movementType = $this->movementTypeByName('Transfer Out');
                foreach ($lockedTransfer->items as $item) {
                    $this->dispatchTransferItem($lockedTransfer, $item, $actor, $movementType);
                    $item->update(['quantity_dispatched' => $item->quantity_requested]);
                }
                $lockedTransfer->update([
                    'status' => 'dispatched',
                    'approved_by_tenant_user_id' => $lockedTransfer->approved_by_tenant_user_id ?: $actor->id,
                    'dispatched_on' => now()->toDateString(),
                ]);

                return $this->loadStockTransfer($lockedTransfer);
            });
    }

    public function receiveStockTransfer(StockTransfer $transfer, TenantUser $actor): StockTransfer
    {
        return DB::connection('tenant')
            ->transaction(function () use ($transfer, $actor): StockTransfer {
                $lockedTransfer = StockTransfer::query()
                    ->with(['items.ingredient', 'toWarehouse'])
                    ->whereKey($transfer->id)
                    ->lockForUpdate()
                    ->firstOrFail();
                if ($lockedTransfer->status !== 'dispatched') {
                    throw ValidationException::withMessages(['stock_transfer' => ['Only dispatched stock transfers can be received.']]);
                }
                $movementType = $this->movementTypeByName('Transfer In');
                foreach ($lockedTransfer->items as $item) {
                    $quantity = (float) $item->quantity_dispatched;
                    if ($quantity <= 0) {
                        throw ValidationException::withMessages(['items' => ['Transfer item has no dispatched quantity to receive.']]);
                    }
                    $unitCost = $this->weightedTransferUnitCost($item);
                    $batch = StockBatch::query()
                        ->create([
                            'ingredient_id' => $item->ingredient_id,
                            'warehouse_id' => $lockedTransfer->to_warehouse_id,
                            'batch_number' => $lockedTransfer->transfer_number,
                            'received_on' => now()->toDateString(),
                            'quantity_on_hand' => $quantity,
                            'unit_cost' => $unitCost,
                            'status' => 'active',
                        ]);
                    StockMovement::query()
                        ->create([
                            'ingredient_id' => $item->ingredient_id,
                            'warehouse_id' => $lockedTransfer->to_warehouse_id,
                            'stock_batch_id' => $batch->id,
                            'stock_movement_type_id' => $movementType->id,
                            'stock_transfer_item_id' => $item->id,
                            'performed_by_tenant_user_id' => $actor->id,
                            'quantity' => $quantity,
                            'unit_cost' => $unitCost,
                            'moved_at' => now(),
                            'notes' => $lockedTransfer->notes,
                        ]);
                    $item->update(['quantity_received' => $quantity]);
                }
                $lockedTransfer->update([
                    'status' => 'received',
                    'received_on' => now()->toDateString(),
                ]);

                return $this->loadStockTransfer($lockedTransfer);
            });
    }

    public function loadStockTransfer(StockTransfer $transfer): StockTransfer
    {
        return $transfer->fresh([
            'fromBranch:id,uuid,name',
            'fromWarehouse:id,uuid,name,type',
            'toBranch:id,uuid,name',
            'toWarehouse:id,uuid,name,type',
            'requestedBy:id,uuid,display_name',
            'approvedBy:id,uuid,display_name',
            'items:id,uuid,stock_transfer_id,ingredient_id,unit_id,quantity_requested,quantity_dispatched,quantity_received',
            'items.ingredient:id,uuid,sku,name',
            'items.unit:id,uuid,name,symbol',
        ]);
    }

    /**
     * @return Paginator<int, StockCount>
     */
    public function paginateStockCounts(Branch $branch, array $filters, int $perPage): Paginator
    {
        return StockCount::query()
            ->select([
                'id',
                'uuid',
                'branch_id',
                'warehouse_id',
                'counted_by_tenant_user_id',
                'count_number',
                'status',
                'counted_on',
                'notes',
                'created_at',
            ])
            ->with(['branch:id,uuid,name', 'warehouse:id,uuid,name,type', 'countedBy:id,uuid,display_name'])
            ->where('branch_id', $branch->id)
            ->when($filters['warehouse_uuid'] ?? null, fn (Builder $query, string $uuid) => $query->whereHas('warehouse', fn (Builder $warehouse) => $warehouse->where('uuid', $uuid)))
            ->when($filters['status'] ?? null, fn (Builder $query, string $status) => $query->where('status', $status))
            ->when($filters['counted_from'] ?? null, fn (Builder $query, string $date) => $query->where('counted_on', '>=', $date))
            ->when($filters['counted_to'] ?? null, fn (Builder $query, string $date) => $query->where('counted_on', '<=', $date))
            ->latest('created_at')
            ->latest('id')
            ->simplePaginate($perPage);
    }

    public function createStockCount(Branch $branch, TenantUser $actor, array $data): StockCount
    {
        return DB::connection('tenant')
            ->transaction(function () use ($branch, $actor, $data): StockCount {
                $warehouse = $this->warehouseForBranch($branch, $data['warehouse_uuid']);
                [$countNumber, $allocation] = $this->allocateNumber($branch, 'stock_count', 'CNT', 'stock_counts', 'Stock Counts');
                $stockCount = StockCount::query()
                    ->create([
                        'branch_id' => $branch->id,
                        'warehouse_id' => $warehouse->id,
                        'counted_by_tenant_user_id' => $actor->id,
                        'count_number' => $countNumber,
                        'status' => 'draft',
                        'counted_on' => $data['counted_on'] ?? now()->toDateString(),
                        'notes' => $data['notes'] ?? null,
                    ]);
                $this->replaceStockCountItems($stockCount, $data['items']);
                $allocation->update([
                    'consumer_id' => $stockCount->id,
                    'consumer_uuid' => $stockCount->uuid,
                    'status' => 'used',
                    'used_at' => now(),
                ]);

                return $this->loadStockCount($stockCount);
            });
    }

    public function updateStockCount(StockCount $stockCount, array $data): StockCount
    {
        return DB::connection('tenant')
            ->transaction(function () use ($stockCount, $data): StockCount {
                $lockedStockCount = StockCount::query()
                    ->whereKey($stockCount->id)
                    ->lockForUpdate()
                    ->firstOrFail();
                if ($lockedStockCount->status !== 'draft') {
                    throw ValidationException::withMessages(['stock_count' => ['Only draft stock counts can be updated.']]);
                }
                $warehouse = isset($data['warehouse_uuid']) ? $this->warehouseForBranch($lockedStockCount->branch, $data['warehouse_uuid']) : $lockedStockCount->warehouse;
                $lockedStockCount->fill([
                    'warehouse_id' => $warehouse->id,
                    'counted_on' => $data['counted_on'] ?? $lockedStockCount->counted_on,
                    'notes' => array_key_exists('notes', $data) ? $data['notes'] : $lockedStockCount->notes,
                ])
                    ->save();
                if (isset($data['items'])) {
                    $lockedStockCount->items()
                        ->delete();
                    $this->replaceStockCountItems($lockedStockCount, $data['items']);
                }

                return $this->loadStockCount($lockedStockCount);
            });
    }

    public function loadStockCount(StockCount $stockCount): StockCount
    {
        return $stockCount->fresh([
            'branch:id,uuid,name',
            'warehouse:id,uuid,name,type',
            'countedBy:id,uuid,display_name',
            'items:id,uuid,stock_count_id,ingredient_id,location_id,unit_id,system_quantity,counted_quantity,variance_quantity',
            'items.ingredient:id,uuid,sku,name',
            'items.location:id,uuid,name',
            'items.unit:id,uuid,name,symbol',
        ]);
    }

    public function postStockCount(StockCount $stockCount, TenantUser $actor): StockCount
    {
        return DB::connection('tenant')
            ->transaction(function () use ($stockCount, $actor): StockCount {
                $lockedStockCount = StockCount::query()
                    ->with(['items.ingredient', 'items.location', 'warehouse'])
                    ->whereKey($stockCount->id)
                    ->lockForUpdate()
                    ->firstOrFail();
                if (! in_array($lockedStockCount->status, ['draft', 'counted'], true)) {
                    throw ValidationException::withMessages(['stock_count' => ['Only draft or counted stock counts can be posted.']]);
                }
                if ($lockedStockCount->items->isEmpty()) {
                    throw ValidationException::withMessages(['items' => ['Please provide at least one counted item before posting.']]);
                }
                $gainType = $this->movementTypeByName('Stock Count Gain');
                $lossType = $this->movementTypeByName('Stock Count Loss');
                foreach ($lockedStockCount->items as $item) {
                    $variance = (float) $item->variance_quantity;
                    if ($variance > 0) {
                        $this->postStockCountGain($lockedStockCount, $item, $actor, $gainType);

                        continue;
                    }
                    if ($variance < 0) {
                        $this->postStockCountLoss($lockedStockCount, $item, $actor, $lossType);
                    }
                }
                $lockedStockCount->update(['status' => 'posted']);

                return $this->loadStockCount($lockedStockCount);
            });
    }

    /**
     * @return Paginator<int, StockBatch>
     */
    public function paginateStockBatches(Branch $branch, array $filters, int $perPage): Paginator
    {
        return StockBatch::query()
            ->select([
                'id',
                'uuid',
                'ingredient_id',
                'warehouse_id',
                'location_id',
                'batch_number',
                'received_on',
                'expires_on',
                'quantity_on_hand',
                'unit_cost',
                'status',
            ])
            ->with([
                'ingredient:id,uuid,base_unit_id,sku,name',
                'ingredient.baseUnit:id,uuid,name,symbol',
                'warehouse:id,uuid,branch_id,name',
                'warehouse.branch:id,uuid,name',
                'location:id,uuid,name',
            ])
            ->whereHas('warehouse', fn (Builder $query) => $query->where('branch_id', $branch->id))
            ->when($filters['warehouse_uuid'] ?? null, fn (Builder $query, string $uuid) => $query->whereHas('warehouse', fn (Builder $warehouse) => $warehouse->where('uuid', $uuid)))
            ->when($filters['ingredient_uuid'] ?? null, fn (Builder $query, string $uuid) => $query->whereHas('ingredient', fn (Builder $ingredient) => $ingredient->where('uuid', $uuid)))
            ->when($filters['status'] ?? null, fn (Builder $query, string $status) => $query->where('status', $status))
            ->when($filters['expires_from'] ?? null, fn (Builder $query, string $date) => $query->where('expires_on', '>=', $date))
            ->when($filters['expires_to'] ?? null, fn (Builder $query, string $date) => $query->where('expires_on', '<=', $date))
            ->orderByRaw('expires_on IS NULL')
            ->orderBy('expires_on')
            ->orderBy('id')
            ->simplePaginate($perPage);
    }

    /**
     * @return Paginator<int, StockMovement>
     */
    public function paginateStockMovements(Branch $branch, array $filters, int $perPage): Paginator
    {
        return StockMovement::query()
            ->select([
                'id',
                'uuid',
                'ingredient_id',
                'warehouse_id',
                'location_id',
                'stock_batch_id',
                'stock_movement_type_id',
                'performed_by_tenant_user_id',
                'quantity',
                'unit_cost',
                'moved_at',
                'notes',
            ])
            ->with([
                'ingredient:id,uuid,sku,name',
                'warehouse:id,uuid,branch_id,name',
                'warehouse.branch:id,uuid,name',
                'location:id,uuid,name',
                'stockMovementType:id,uuid,name,direction',
                'performedBy:id,uuid,display_name',
            ])
            ->whereHas('warehouse', fn (Builder $query) => $query->where('branch_id', $branch->id))
            ->when($filters['warehouse_uuid'] ?? null, fn (Builder $query, string $uuid) => $query->whereHas('warehouse', fn (Builder $warehouse) => $warehouse->where('uuid', $uuid)))
            ->when($filters['ingredient_uuid'] ?? null, fn (Builder $query, string $uuid) => $query->whereHas('ingredient', fn (Builder $ingredient) => $ingredient->where('uuid', $uuid)))
            ->when($filters['movement_type_uuid'] ?? null, fn (Builder $query, string $uuid) => $query->whereHas('stockMovementType', fn (Builder $type) => $type->where('uuid', $uuid)))
            ->when($filters['moved_from'] ?? null, fn (Builder $query, string $date) => $query->where('moved_at', '>=', Carbon::parse($date, config('app.timezone'))
                ->startOfDay()))
            ->when($filters['moved_to'] ?? null, fn (Builder $query, string $date) => $query->where('moved_at', '<=', Carbon::parse($date, config('app.timezone'))
                ->endOfDay()))
            ->latest('moved_at')
            ->latest('id')
            ->simplePaginate($perPage);
    }

    /**
     * @return Paginator<int, object>
     */
    public function paginateStockSummary(Branch $branch, array $filters, int $perPage): Paginator
    {
        return DB::connection('tenant')
            ->table('stock_batches')
            ->join('ingredients', 'ingredients.id', '=', 'stock_batches.ingredient_id')
            ->join('warehouses', 'warehouses.id', '=', 'stock_batches.warehouse_id')
            ->join('branches', 'branches.id', '=', 'warehouses.branch_id')
            ->leftJoin('units_of_measure', 'units_of_measure.id', '=', 'ingredients.base_unit_id')
            ->where('warehouses.branch_id', $branch->id)
            ->where('stock_batches.status', 'active')
            ->when($filters['warehouse_uuid'] ?? null, fn ($query, string $uuid) => $query->where('warehouses.uuid', $uuid))
            ->when($filters['ingredient_uuid'] ?? null, fn ($query, string $uuid) => $query->where('ingredients.uuid', $uuid))
            ->when($filters['search'] ?? null, fn ($query, string $search) => $query->where('ingredients.name', 'like', trim($search).'%'))
            ->groupBy([
                'ingredients.uuid',
                'ingredients.name',
                'ingredients.sku',
                'units_of_measure.uuid',
                'units_of_measure.name',
                'units_of_measure.symbol',
                'warehouses.uuid',
                'warehouses.name',
                'branches.uuid',
                'branches.name',
            ])
            ->select([
                'ingredients.uuid as ingredient_uuid',
                'ingredients.name as ingredient_name',
                'ingredients.sku as ingredient_sku',
                'units_of_measure.uuid as base_unit_uuid',
                'units_of_measure.name as base_unit_name',
                'units_of_measure.symbol as base_unit_symbol',
                'warehouses.uuid as warehouse_uuid',
                'warehouses.name as warehouse_name',
                'branches.uuid as branch_uuid',
                'branches.name as branch_name',
            ])
            ->selectRaw('SUM(stock_batches.quantity_on_hand) as quantity_on_hand')
            ->selectRaw('SUM(stock_batches.quantity_on_hand * stock_batches.unit_cost) as stock_value')
            ->selectRaw('MIN(stock_batches.expires_on) as earliest_expiry_date')
            ->orderBy('ingredients.name')
            ->orderBy('warehouses.name')
            ->simplePaginate($perPage);
    }

    private function assertWarehouseNameAvailable(Branch $branch, string $name, ?int $ignoreWarehouseId = null): void
    {
        $exists = Warehouse::query()
            ->where('branch_id', $branch->id)
            ->where('name', $name)
            ->when($ignoreWarehouseId, fn (Builder $query, int $id) => $query->whereKeyNot($id))
            ->exists();
        if ($exists) {
            throw ValidationException::withMessages(['name' => ['A warehouse with this name already exists in this branch.']]);
        }
    }

    /**
     * @param  array<int, array<string, mixed>>  $items
     */
    private function replaceAdjustmentItems(StockAdjustment $adjustment, array $items): void
    {
        $ingredients = $this->ingredientsForItems($items);

        foreach ($items as $item) {
            $ingredient = $ingredients->get($item['ingredient_uuid']);
            if (! $ingredient) {
                throw ValidationException::withMessages(['items' => ['One or more selected ingredients could not be found.']]);
            }
            $location = isset($item['location_uuid']) ? $this->locationForBranch($adjustment->branch, $item['location_uuid']) : null;
            $unit = isset($item['unit_uuid']) ? $this->unitByUuid($item['unit_uuid']) : null;
            $quantityDelta = (float) $item['quantity_delta'];
            $unitCost = (float) ($item['unit_cost'] ?? 0);
            if ($quantityDelta > 0 && $unitCost <= 0) {
                throw ValidationException::withMessages(['items' => ['Unit cost is required when increasing stock.']]);
            }
            StockAdjustmentItem::query()
                ->create([
                    'stock_adjustment_id' => $adjustment->id,
                    'ingredient_id' => $ingredient->id,
                    'location_id' => $location?->id,
                    'unit_id' => $unit?->id,
                    'quantity_delta' => $quantityDelta,
                    'unit_cost' => $unitCost,
                    'reason' => $item['reason'] ?? null,
                ]);
        }
    }

    private function postPositiveAdjustmentItem(StockAdjustment $adjustment, StockAdjustmentItem $item, TenantUser $actor, StockMovementType $movementType): void
    {
        $batch = StockBatch::query()
            ->create([
                'ingredient_id' => $item->ingredient_id,
                'warehouse_id' => $adjustment->warehouse_id,
                'location_id' => $item->location_id,
                'batch_number' => $adjustment->adjustment_number,
                'received_on' => $adjustment->adjusted_on,
                'quantity_on_hand' => $item->quantity_delta,
                'unit_cost' => $item->unit_cost,
                'status' => 'active',
            ]);
        StockMovement::query()
            ->create([
                'ingredient_id' => $item->ingredient_id,
                'warehouse_id' => $adjustment->warehouse_id,
                'location_id' => $item->location_id,
                'stock_batch_id' => $batch->id,
                'stock_movement_type_id' => $movementType->id,
                'stock_adjustment_item_id' => $item->id,
                'performed_by_tenant_user_id' => $actor->id,
                'quantity' => $item->quantity_delta,
                'unit_cost' => $item->unit_cost,
                'moved_at' => now(),
                'notes' => $item->reason ?: $adjustment->notes,
            ]);
    }

    private function postNegativeAdjustmentItem(StockAdjustment $adjustment, StockAdjustmentItem $item, TenantUser $actor, StockMovementType $movementType): void
    {
        $remainingQuantity = abs((float) $item->quantity_delta);
        $batches = StockBatch::query()
            ->where('ingredient_id', $item->ingredient_id)
            ->where('warehouse_id', $adjustment->warehouse_id)
            ->when($item->location_id, fn (Builder $query, int $locationId) => $query->where('location_id', $locationId))
            ->where('status', 'active')
            ->where('quantity_on_hand', '>', 0)
            ->orderByRaw('expires_on IS NULL')
            ->orderBy('expires_on')
            ->orderBy('received_on')
            ->orderBy('id')
            ->lockForUpdate()
            ->get();
        foreach ($batches as $batch) {
            if ($remainingQuantity <= 0) {
                break;
            }
            $deductQuantity = min($remainingQuantity, (float) $batch->quantity_on_hand);
            $remainingQuantity -= $deductQuantity;
            $batch->decrement('quantity_on_hand', $deductQuantity);
            if ((float) $batch->fresh()->quantity_on_hand <= 0) {
                $batch->update(['status' => 'consumed']);
            }
            StockMovement::query()
                ->create([
                    'ingredient_id' => $item->ingredient_id,
                    'warehouse_id' => $adjustment->warehouse_id,
                    'location_id' => $item->location_id,
                    'stock_batch_id' => $batch->id,
                    'stock_movement_type_id' => $movementType->id,
                    'stock_adjustment_item_id' => $item->id,
                    'performed_by_tenant_user_id' => $actor->id,
                    'quantity' => $deductQuantity,
                    'unit_cost' => $batch->unit_cost,
                    'moved_at' => now(),
                    'notes' => $item->reason ?: $adjustment->notes,
                ]);
        }
        if ($remainingQuantity > 0) {
            throw ValidationException::withMessages(['items' => ["Insufficient stock for {$item->ingredient->name}."]]);
        }
    }

    private function warehouseForBranch(Branch $branch, string $warehouseUuid): Warehouse
    {
        $warehouse = Warehouse::query()
            ->where('branch_id', $branch->id)
            ->where('uuid', $warehouseUuid)
            ->first();
        if (! $warehouse) {
            throw ValidationException::withMessages(['warehouse_uuid' => ['The selected warehouse could not be found in this branch.']]);
        }

        return $warehouse;
    }

    private function locationForBranch(Branch $branch, string $locationUuid): Location
    {
        $location = Location::query()
            ->where('branch_id', $branch->id)
            ->where('uuid', $locationUuid)
            ->first();
        if (! $location) {
            throw ValidationException::withMessages(['items' => ['One or more selected locations could not be found in this branch.']]);
        }

        return $location;
    }

    private function unitByUuid(string $unitUuid): UnitOfMeasure
    {
        $unit = UnitOfMeasure::query()
            ->where('uuid', $unitUuid)
            ->first();
        if (! $unit) {
            throw ValidationException::withMessages(['items' => ['One or more selected units could not be found.']]);
        }

        return $unit;
    }

    /**
     * @param  array<int, array<string, mixed>>  $items
     */
    private function replaceTransferItems(StockTransfer $transfer, array $items): void
    {
        $ingredients = $this->ingredientsForItems($items);

        foreach ($items as $item) {
            $ingredient = $ingredients->get($item['ingredient_uuid']);
            if (! $ingredient) {
                throw ValidationException::withMessages(['items' => ['One or more selected ingredients could not be found.']]);
            }
            $unit = isset($item['unit_uuid']) ? $this->unitByUuid($item['unit_uuid']) : null;
            StockTransferItem::query()
                ->create([
                    'stock_transfer_id' => $transfer->id,
                    'ingredient_id' => $ingredient->id,
                    'unit_id' => $unit?->id,
                    'quantity_requested' => $item['quantity_requested'],
                    'quantity_dispatched' => 0,
                    'quantity_received' => 0,
                ]);
        }
    }

    /**
     * @param  array<int, array<string, mixed>>  $items
     */
    private function replaceStockCountItems(StockCount $stockCount, array $items): void
    {
        $ingredients = $this->ingredientsForItems($items);
        $seenItems = [];
        foreach ($items as $item) {
            $ingredient = $ingredients->get($item['ingredient_uuid']);
            if (! $ingredient) {
                throw ValidationException::withMessages(['items' => ['One or more selected ingredients could not be found.']]);
            }
            $location = isset($item['location_uuid']) ? $this->locationForBranch($stockCount->branch, $item['location_uuid']) : null;
            $unit = isset($item['unit_uuid']) ? $this->unitByUuid($item['unit_uuid']) : null;
            $duplicateKey = $ingredient->id.':'.($location?->id ?? 'warehouse');
            if (isset($seenItems[$duplicateKey])) {
                throw ValidationException::withMessages(['items' => ['Each ingredient can appear only once for the same location.']]);
            }
            $seenItems[$duplicateKey] = true;
            $systemQuantity = $this->currentStockQuantity($stockCount->warehouse_id, $ingredient->id, $location?->id);
            $countedQuantity = (float) $item['counted_quantity'];
            StockCountItem::query()
                ->create([
                    'stock_count_id' => $stockCount->id,
                    'ingredient_id' => $ingredient->id,
                    'location_id' => $location?->id,
                    'unit_id' => $unit?->id,
                    'system_quantity' => $systemQuantity,
                    'counted_quantity' => $countedQuantity,
                    'variance_quantity' => round($countedQuantity - $systemQuantity, 4),
                ]);
        }
    }

    /** @return Collection<string, Ingredient> */
    private function ingredientsForItems(array $items): Collection
    {
        $ingredientUuids = array_unique(array_column($items, 'ingredient_uuid'));

        return Ingredient::query()
            ->whereIn('uuid', $ingredientUuids)
            ->get()
            ->keyBy('uuid');
    }

    private function currentStockQuantity(int $warehouseId, int $ingredientId, ?int $locationId = null): float
    {
        return (float) StockBatch::query()
            ->where('warehouse_id', $warehouseId)
            ->where('ingredient_id', $ingredientId)
            ->when($locationId, fn (Builder $query, int $id) => $query->where('location_id', $id))
            ->where('status', 'active')
            ->sum('quantity_on_hand');
    }

    private function postStockCountGain(StockCount $stockCount, StockCountItem $item, TenantUser $actor, StockMovementType $movementType): void
    {
        $unitCost = $this->latestUnitCost($stockCount->warehouse_id, $item->ingredient_id);
        $batch = StockBatch::query()
            ->create([
                'ingredient_id' => $item->ingredient_id,
                'warehouse_id' => $stockCount->warehouse_id,
                'location_id' => $item->location_id,
                'batch_number' => $stockCount->count_number,
                'received_on' => $stockCount->counted_on,
                'quantity_on_hand' => $item->variance_quantity,
                'unit_cost' => $unitCost,
                'status' => 'active',
            ]);
        StockMovement::query()
            ->create([
                'ingredient_id' => $item->ingredient_id,
                'warehouse_id' => $stockCount->warehouse_id,
                'location_id' => $item->location_id,
                'stock_batch_id' => $batch->id,
                'stock_movement_type_id' => $movementType->id,
                'stock_count_item_id' => $item->id,
                'performed_by_tenant_user_id' => $actor->id,
                'quantity' => $item->variance_quantity,
                'unit_cost' => $unitCost,
                'moved_at' => now(),
                'notes' => $stockCount->notes,
            ]);
    }

    private function postStockCountLoss(StockCount $stockCount, StockCountItem $item, TenantUser $actor, StockMovementType $movementType): void
    {
        $remainingQuantity = abs((float) $item->variance_quantity);
        $batches = StockBatch::query()
            ->where('ingredient_id', $item->ingredient_id)
            ->where('warehouse_id', $stockCount->warehouse_id)
            ->when($item->location_id, fn (Builder $query, int $locationId) => $query->where('location_id', $locationId))
            ->where('status', 'active')
            ->where('quantity_on_hand', '>', 0)
            ->orderByRaw('expires_on IS NULL')
            ->orderBy('expires_on')
            ->orderBy('received_on')
            ->orderBy('id')
            ->lockForUpdate()
            ->get();
        foreach ($batches as $batch) {
            if ($remainingQuantity <= 0) {
                break;
            }
            $deductQuantity = min($remainingQuantity, (float) $batch->quantity_on_hand);
            $remainingQuantity -= $deductQuantity;
            $batch->decrement('quantity_on_hand', $deductQuantity);
            if ((float) $batch->fresh()->quantity_on_hand <= 0) {
                $batch->update(['status' => 'consumed']);
            }
            StockMovement::query()
                ->create([
                    'ingredient_id' => $item->ingredient_id,
                    'warehouse_id' => $stockCount->warehouse_id,
                    'location_id' => $item->location_id,
                    'stock_batch_id' => $batch->id,
                    'stock_movement_type_id' => $movementType->id,
                    'stock_count_item_id' => $item->id,
                    'performed_by_tenant_user_id' => $actor->id,
                    'quantity' => $deductQuantity,
                    'unit_cost' => $batch->unit_cost,
                    'moved_at' => now(),
                    'notes' => $stockCount->notes,
                ]);
        }
        if ($remainingQuantity > 0) {
            throw ValidationException::withMessages(['items' => ["Insufficient stock for {$item->ingredient->name}."]]);
        }
    }

    private function latestUnitCost(int $warehouseId, int $ingredientId): float
    {
        $batch = StockBatch::query()
            ->where('warehouse_id', $warehouseId)
            ->where('ingredient_id', $ingredientId)
            ->where('unit_cost', '>', 0)
            ->latest('received_on')
            ->latest('id')
            ->first(['unit_cost']);

        return (float) ($batch?->unit_cost ?? 0);
    }

    private function dispatchTransferItem(StockTransfer $transfer, StockTransferItem $item, TenantUser $actor, StockMovementType $movementType): void
    {
        $remainingQuantity = (float) $item->quantity_requested;
        $batches = StockBatch::query()
            ->where('ingredient_id', $item->ingredient_id)
            ->where('warehouse_id', $transfer->from_warehouse_id)
            ->where('status', 'active')
            ->where('quantity_on_hand', '>', 0)
            ->orderByRaw('expires_on IS NULL')
            ->orderBy('expires_on')
            ->orderBy('received_on')
            ->orderBy('id')
            ->lockForUpdate()
            ->get();
        foreach ($batches as $batch) {
            if ($remainingQuantity <= 0) {
                break;
            }
            $deductQuantity = min($remainingQuantity, (float) $batch->quantity_on_hand);
            $remainingQuantity -= $deductQuantity;
            $batch->decrement('quantity_on_hand', $deductQuantity);
            if ((float) $batch->fresh()->quantity_on_hand <= 0) {
                $batch->update(['status' => 'consumed']);
            }
            StockMovement::query()
                ->create([
                    'ingredient_id' => $item->ingredient_id,
                    'warehouse_id' => $transfer->from_warehouse_id,
                    'stock_batch_id' => $batch->id,
                    'stock_movement_type_id' => $movementType->id,
                    'stock_transfer_item_id' => $item->id,
                    'performed_by_tenant_user_id' => $actor->id,
                    'quantity' => $deductQuantity,
                    'unit_cost' => $batch->unit_cost,
                    'moved_at' => now(),
                    'notes' => $transfer->notes,
                ]);
        }
        if ($remainingQuantity > 0) {
            throw ValidationException::withMessages(['items' => ["Insufficient stock for {$item->ingredient->name}."]]);
        }
    }

    private function movementTypeByName(string $name): StockMovementType
    {
        $movementType = StockMovementType::query()
            ->where('name', $name)
            ->where('status', 'active')
            ->first();
        if (! $movementType) {
            throw ValidationException::withMessages(['stock_movement_type' => ["{$name} movement type is not configured."]]);
        }

        return $movementType;
    }

    private function weightedTransferUnitCost(StockTransferItem $item): float
    {
        $totals = StockMovement::query()
            ->where('stock_transfer_item_id', $item->id)
            ->selectRaw('SUM(quantity) as total_quantity')
            ->selectRaw('SUM(quantity * unit_cost) as total_cost')
            ->first();
        $quantity = (float) ($totals?->total_quantity ?? 0);
        if ($quantity <= 0) {
            return 0;
        }

        return round((float) $totals->total_cost / $quantity, 4);
    }

    /**
     * @return array{0: string, 1: NumberSequenceAllocation}
     */
    private function allocateNumber(Branch $branch, string $sequenceKey, string $defaultPrefix, string $consumerTable, string $displayName): array
    {
        $today = now()->toImmutable();
        $periodStart = $today->startOfYear()
            ->toDateString();
        $periodEnd = $today->endOfYear()
            ->toDateString();
        $definition = NumberSequence::query()
            ->where('branch_id', $branch->id)
            ->where('sequence_key', $sequenceKey)
            ->whereNull('period_starts_on')
            ->where('is_active', true)
            ->lockForUpdate()
            ->first();
        if (! $definition) {
            $definition = NumberSequence::query()
                ->create([
                    'company_id' => $branch->company_id,
                    'branch_id' => $branch->id,
                    'sequence_key' => $sequenceKey,
                    'display_name' => "{$branch->name} {$displayName}",
                    'prefix' => $defaultPrefix,
                    'period_type' => 'yearly',
                    'next_number' => 1,
                    'padding_length' => 2,
                    'reset_per_branch' => true,
                    'is_active' => true,
                ]);
        }
        $sequence = NumberSequence::query()
            ->where('branch_id', $branch->id)
            ->where('sequence_key', $sequenceKey)
            ->where('period_starts_on', $periodStart)
            ->lockForUpdate()
            ->first();
        if (! $sequence) {
            $sequence = NumberSequence::query()
                ->create([
                    'company_id' => $definition->company_id,
                    'branch_id' => $branch->id,
                    'sequence_key' => $definition->sequence_key,
                    'display_name' => $definition->display_name,
                    'prefix' => $definition->prefix,
                    'suffix' => $definition->suffix,
                    'period_type' => 'yearly',
                    'period_starts_on' => $periodStart,
                    'period_ends_on' => $periodEnd,
                    'next_number' => 1,
                    'padding_length' => $definition->padding_length,
                    'reset_per_branch' => true,
                    'is_active' => true,
                ]);
        }
        $sequence = NumberSequence::query()
            ->lockForUpdate()
            ->findOrFail($sequence->id);
        $number = $sequence->next_number;
        $paddedNumber = str_pad((string) $number, $sequence->padding_length, '0', STR_PAD_LEFT);
        $formatted = sprintf('%s-%s-%s', $sequence->prefix ?: $defaultPrefix, $today->format('Y'), $paddedNumber);
        $allocation = NumberSequenceAllocation::query()
            ->create([
                'number_sequence_id' => $sequence->id,
                'branch_id' => $branch->id,
                'sequence_key' => $sequenceKey,
                'allocated_number' => $number,
                'formatted_number' => $formatted,
                'consumer_table' => $consumerTable,
                'status' => 'reserved',
                'reserved_at' => now(),
            ]);
        $sequence->increment('next_number');

        return [$formatted, $allocation];
    }
}
