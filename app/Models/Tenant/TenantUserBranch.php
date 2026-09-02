<?php

namespace App\Models\Tenant;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TenantUserBranch extends TenantModel
{
    protected function casts(): array
    {
        return [
            'is_primary' => 'boolean',
            'valid_from' => 'immutable_date',
            'valid_to' => 'immutable_date',
            'revoked_at' => 'immutable_datetime',
        ];
    }

    public function tenantUser(): BelongsTo
    {
        return $this->belongsTo(TenantUser::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function assignedBy(): BelongsTo
    {
        return $this->belongsTo(TenantUser::class, 'assigned_by_id');
    }

    public function revokedBy(): BelongsTo
    {
        return $this->belongsTo(TenantUser::class, 'revoked_by_id');
    }
}
