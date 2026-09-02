<?php

namespace App\Models\Tenant;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Payslip extends TenantModel
{
    protected function casts(): array
    {
        return [
            'generated_at' => 'immutable_datetime',
            'published_at' => 'immutable_datetime',
        ];
    }

    public function payrollRunEmployee(): BelongsTo
    {
        return $this->belongsTo(PayrollRunEmployee::class);
    }
}
