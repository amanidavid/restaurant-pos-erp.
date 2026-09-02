<?php

namespace App\Models\Tenant;

class DataImportError extends TenantModel
{
    protected function casts(): array
    {
        return ['row_number' => 'integer', 'row_payload' => 'array'];
    }
}
