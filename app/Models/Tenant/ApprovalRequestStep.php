<?php

namespace App\Models\Tenant;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ApprovalRequestStep extends TenantModel
{
    protected function casts(): array
    {
        return [
            'step_number' => 'integer',
            'required_approval_count_snapshot' => 'integer',
            'opened_at' => 'immutable_datetime',
            'completed_at' => 'immutable_datetime',
        ];
    }

    public function approvalRequest(): BelongsTo
    {
        return $this->belongsTo(ApprovalRequest::class);
    }

    public function approvalWorkflowStep(): BelongsTo
    {
        return $this->belongsTo(ApprovalWorkflowStep::class);
    }

    public function decisions(): HasMany
    {
        return $this->hasMany(ApprovalDecision::class);
    }
}
