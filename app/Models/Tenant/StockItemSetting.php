<?php

namespace App\Models\Tenant;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StockItemSetting extends TenantModel
{
    protected function casts(): array
    {
        return [
            'minimum_stock_level' => 'decimal:4',
            'maximum_stock_level' => 'decimal:4',
            'reorder_level' => 'decimal:4',
            'is_stock_item' => 'boolean',
            'is_perishable' => 'boolean',
            'track_batches' => 'boolean',
            'allow_negative_stock' => 'boolean',
        ];
    }

    public function ingredient(): BelongsTo
    {
        return $this->belongsTo(Ingredient::class);
    }

    public function defaultSupplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class, 'default_supplier_id');
    }

    public function defaultPurchaseUnit(): BelongsTo
    {
        return $this->belongsTo(UnitOfMeasure::class, 'default_purchase_unit_id');
    }

    public function defaultIssueUnit(): BelongsTo
    {
        return $this->belongsTo(UnitOfMeasure::class, 'default_issue_unit_id');
    }
}
