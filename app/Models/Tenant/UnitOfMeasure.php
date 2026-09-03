<?php

namespace App\Models\Tenant;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class UnitOfMeasure extends TenantModel
{
    protected $table = 'units_of_measure';

    protected function casts(): array
    {
        return [
            'base_factor' => 'decimal:6',
            'is_base_unit' => 'boolean',
        ];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function ingredients(): HasMany
    {
        return $this->hasMany(Ingredient::class, 'base_unit_id');
    }

    public function stockUnits(): HasMany
    {
        return $this->hasMany(StockUnit::class, 'unit_id');
    }
}
