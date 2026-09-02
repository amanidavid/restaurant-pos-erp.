<?php

namespace App\Models\Tenant;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StockMovement extends TenantModel
{
    protected function casts(): array
    {
        return [
            'quantity' => 'decimal:4',
            'unit_cost' => 'decimal:4',
            'moved_at' => 'immutable_datetime',
        ];
    }

    public function ingredient(): BelongsTo
    {
        return $this->belongsTo(Ingredient::class);
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class);
    }

    public function stockBatch(): BelongsTo
    {
        return $this->belongsTo(StockBatch::class);
    }

    public function stockMovementType(): BelongsTo
    {
        return $this->belongsTo(StockMovementType::class);
    }

    public function goodsReceiptItem(): BelongsTo
    {
        return $this->belongsTo(GoodsReceiptItem::class);
    }

    public function stockAdjustmentItem(): BelongsTo
    {
        return $this->belongsTo(StockAdjustmentItem::class);
    }

    public function stockTransferItem(): BelongsTo
    {
        return $this->belongsTo(StockTransferItem::class);
    }

    public function stockCountItem(): BelongsTo
    {
        return $this->belongsTo(StockCountItem::class);
    }

    public function wasteRecordItem(): BelongsTo
    {
        return $this->belongsTo(WasteRecordItem::class);
    }

    public function performedBy(): BelongsTo
    {
        return $this->belongsTo(TenantUser::class, 'performed_by_tenant_user_id');
    }
}
