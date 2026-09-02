<?php

namespace App\Models\Tenant;

class DataImportBatch extends TenantModel
{
    protected function casts(): array
    {
        return [
            'total_rows' => 'integer',
            'successful_rows' => 'integer',
            'failed_rows' => 'integer',
            'started_at' => 'immutable_datetime',
            'completed_at' => 'immutable_datetime',
            'summary' => 'array',
        ];
    }
}
