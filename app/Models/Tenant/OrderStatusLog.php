<?php

namespace App\Models\Tenant;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrderStatusLog extends TenantModel
{
    protected function casts(): array
    {
        return [
            'changed_at' => 'immutable_datetime',
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function changedByTenantUser(): BelongsTo
    {
        return $this->belongsTo(TenantUser::class, 'changed_by_tenant_user_id');
    }
}
