<?php

namespace App\Models\Landlord;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TenantProvisioningStep extends LandlordModel
{
    public $usesUniqueIds = false;

    /**
     * @return array<int, string>
     */
    public function uniqueIds(): array
    {
        return [];
    }

    protected function casts(): array
    {
        return [
            'started_at' => 'immutable_datetime',
            'completed_at' => 'immutable_datetime',
        ];
    }

    public function provisioningRun(): BelongsTo
    {
        return $this->belongsTo(TenantProvisioningRun::class, 'tenant_provisioning_run_id');
    }
}
