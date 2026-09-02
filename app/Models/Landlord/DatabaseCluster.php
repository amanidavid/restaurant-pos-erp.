<?php

namespace App\Models\Landlord;

use Illuminate\Database\Eloquent\Relations\HasMany;

class DatabaseCluster extends LandlordModel
{
    protected function casts(): array
    {
        return [
            'last_health_check_at' => 'immutable_datetime',
        ];
    }

    public function tenantDatabases(): HasMany
    {
        return $this->hasMany(TenantDatabase::class);
    }

    public function provisioningRuns(): HasMany
    {
        return $this->hasMany(TenantProvisioningRun::class);
    }
}
