<?php

namespace App\Models\Tenant;

class SavedReportView extends TenantModel
{
    protected function casts(): array
    {
        return ['parameters' => 'array', 'columns' => 'array', 'filters' => 'array', 'is_default' => 'boolean'];
    }
}
