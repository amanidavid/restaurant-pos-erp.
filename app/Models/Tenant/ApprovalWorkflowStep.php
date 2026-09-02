<?php

namespace App\Models\Tenant;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ApprovalWorkflowStep extends TenantModel
{
    protected function casts(): array
    {
        return [
            'step_number' => 'integer',
            'required_approval_count' => 'integer',
            'can_self_approve' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    public function approvalWorkflow(): BelongsTo
    {
        return $this->belongsTo(ApprovalWorkflow::class);
    }

    public function permission(): BelongsTo
    {
        return $this->belongsTo(Permission::class);
    }

    public function requestSteps(): HasMany
    {
        return $this->hasMany(ApprovalRequestStep::class);
    }
}
