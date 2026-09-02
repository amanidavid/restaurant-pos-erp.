<?php

namespace App\Models\Tenant;

class BranchSetting extends TenantModel
{
    protected function casts(): array
    {
        return ['payload' => 'array'];
    }
}
