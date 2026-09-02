<?php

namespace App\Models\Tenant;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ApprovalDecision extends TenantModel
{
    protected function casts(): array
    {
        return [
            'decided_at' => 'immutable_datetime',
        ];
    }

    public function approvalRequest(): BelongsTo
    {
        return $this->belongsTo(ApprovalRequest::class);
    }

    public function approvalRequestStep(): BelongsTo
    {
        return $this->belongsTo(ApprovalRequestStep::class);
    }

    public function decidedBy(): BelongsTo
    {
        return $this->belongsTo(TenantUser::class, 'decided_by_tenant_user_id');
    }
}
