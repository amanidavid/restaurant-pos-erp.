<?php

namespace App\Models\Tenant;

class RetentionCleanupRunTable extends TenantModel
{
    protected function casts(): array
    {
        return ['cutoff_at' => 'immutable_datetime', 'rows_scanned' => 'integer', 'rows_archived' => 'integer', 'rows_deleted' => 'integer'];
    }
}
