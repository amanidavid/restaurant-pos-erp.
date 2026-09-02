<?php

namespace App\Models\Tenant;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class NumberSequenceAllocation extends TenantModel
{
    protected function casts(): array
    {
        return [
            'allocated_number' => 'integer',
            'allocated_at' => 'immutable_datetime',
        ];
    }

    public function numberSequence(): BelongsTo
    {
        return $this->belongsTo(NumberSequence::class);
    }
}
