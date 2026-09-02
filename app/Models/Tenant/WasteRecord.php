<?php

namespace App\Models\Tenant;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class WasteRecord extends TenantModel
{
    protected function casts(): array
    {
        return [
            'recorded_on' => 'immutable_date',
        ];
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function recordedBy(): BelongsTo
    {
        return $this->belongsTo(TenantUser::class, 'recorded_by_tenant_user_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(WasteRecordItem::class);
    }
}
