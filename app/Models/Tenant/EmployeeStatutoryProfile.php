<?php

namespace App\Models\Tenant;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EmployeeStatutoryProfile extends TenantModel
{
    protected function casts(): array
    {
        return [
            'is_exempt' => 'boolean',
            'effective_from' => 'immutable_date',
            'effective_to' => 'immutable_date',
        ];
    }

    public function tenantUser(): BelongsTo
    {
        return $this->belongsTo(TenantUser::class);
    }

    public function statutoryRule(): BelongsTo
    {
        return $this->belongsTo(StatutoryRule::class);
    }
}
