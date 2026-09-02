<?php

namespace App\Models\Tenant;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DiscountApplication extends TenantModel
{
    protected function casts(): array
    {
        return [
            'value_snapshot' => 'decimal:4',
            'amount' => 'decimal:2',
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function discount(): BelongsTo
    {
        return $this->belongsTo(Discount::class);
    }

    public function approvedByTenantUser(): BelongsTo
    {
        return $this->belongsTo(TenantUser::class, 'approved_by_tenant_user_id');
    }
}
