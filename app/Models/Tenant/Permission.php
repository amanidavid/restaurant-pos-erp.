<?php

namespace App\Models\Tenant;

use App\Modules\Shared\Database\Concerns\HasPublicUuid;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Permission\Models\Permission as SpatiePermission;

class Permission extends SpatiePermission
{
    use HasPublicUuid;

    protected $connection = 'tenant';

    protected $table = 'permissions';

    protected function casts(): array
    {
        return [
            'is_system' => 'boolean',
            'is_protected' => 'boolean',
        ];
    }

    public function approvalWorkflowSteps(): HasMany
    {
        return $this->hasMany(ApprovalWorkflowStep::class);
    }

    public function notificationRules(): HasMany
    {
        return $this->hasMany(NotificationRule::class);
    }
}
