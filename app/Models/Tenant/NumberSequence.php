<?php

namespace App\Models\Tenant;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class NumberSequence extends TenantModel
{
    protected function casts(): array
    {
        return [
            'period_starts_on' => 'immutable_date',
            'period_ends_on' => 'immutable_date',
            'next_number' => 'integer',
            'padding_length' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function allocations(): HasMany
    {
        return $this->hasMany(NumberSequenceAllocation::class);
    }
}
