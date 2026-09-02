<?php

namespace App\Models\Landlord;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TenantProvisioningRun extends LandlordModel
{
    protected function casts(): array
    {
        return [
            'requested_at' => 'immutable_datetime',
            'started_at' => 'immutable_datetime',
            'completed_at' => 'immutable_datetime',
            'registration_payload' => 'array',
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

    public function requestedByPlatformUser(): BelongsTo
    {
        return $this->belongsTo(PlatformUser::class, 'requested_by_platform_user_id');
    }

    public function steps(): HasMany
    {
        return $this->hasMany(TenantProvisioningStep::class);
    }
}
