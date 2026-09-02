<?php

namespace App\Models\Tenant;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Discount extends TenantModel
{
    protected function casts(): array
    {
        return [
            'value' => 'decimal:4',
            'maximum_amount' => 'decimal:2',
            'valid_from' => 'immutable_date',
            'valid_to' => 'immutable_date',
        ];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function applications(): HasMany
    {
        return $this->hasMany(DiscountApplication::class);
    }
}
