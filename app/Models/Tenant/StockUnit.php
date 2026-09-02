<?php

namespace App\Models\Tenant;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StockUnit extends TenantModel
{
    protected function casts(): array
    {
        return [
            'conversion_factor' => 'decimal:6',
            'is_purchase_unit' => 'boolean',
            'is_issue_unit' => 'boolean',
            'is_count_unit' => 'boolean',
        ];
    }

    public function ingredient(): BelongsTo
    {
        return $this->belongsTo(Ingredient::class);
    }

    public function unit(): BelongsTo
    {
        return $this->belongsTo(UnitOfMeasure::class, 'unit_id');
    }
}
