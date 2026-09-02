<?php

namespace App\Models\Tenant;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StatutoryBracket extends TenantModel
{
    protected function casts(): array
    {
        return [
            'sequence' => 'integer',
            'min_amount' => 'decimal:2',
            'max_amount' => 'decimal:2',
            'employee_rate' => 'decimal:4',
            'employer_rate' => 'decimal:4',
            'fixed_amount' => 'decimal:2',
        ];
    }

    public function statutoryRule(): BelongsTo
    {
        return $this->belongsTo(StatutoryRule::class);
    }
}
