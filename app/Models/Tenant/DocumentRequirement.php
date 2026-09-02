<?php

namespace App\Models\Tenant;

class DocumentRequirement extends TenantModel
{
    protected function casts(): array
    {
        return ['is_required' => 'boolean', 'expires_after_days' => 'integer'];
    }
}
