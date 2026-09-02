<?php

namespace App\Models\Tenant;

class DocumentCategory extends TenantModel
{
    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }
}
