<?php

namespace App\Models\Landlord;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TenantDatabase extends LandlordModel
{
    protected function casts(): array
    {
        return [
            'is_primary' => 'boolean',
            'provisioned_at' => 'immutable_datetime',
            'activated_at' => 'immutable_datetime',
            'retired_at' => 'immutable_datetime',
        ];
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function databaseCluster(): BelongsTo
    {
        return $this->belongsTo(DatabaseCluster::class);
    }
}
