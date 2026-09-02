<?php

namespace App\Models\Tenant;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TaxFilingSubmission extends TenantModel
{
    protected function casts(): array
    {
        return [
            'submitted_at' => 'immutable_datetime',
            'accepted_at' => 'immutable_datetime',
            'request_payload' => 'array',
            'response_payload' => 'array',
        ];
    }

    public function taxReturnPeriod(): BelongsTo
    {
        return $this->belongsTo(TaxReturnPeriod::class);
    }

    public function integrationAccount(): BelongsTo
    {
        return $this->belongsTo(IntegrationAccount::class);
    }

    public function submittedBy(): BelongsTo
    {
        return $this->belongsTo(TenantUser::class, 'submitted_by_tenant_user_id');
    }
}
