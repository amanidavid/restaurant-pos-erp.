<?php

namespace App\Models\Tenant;

class ArchiveManifest extends TenantModel
{
    protected function casts(): array
    {
        return ['record_count' => 'integer', 'byte_size' => 'integer', 'manifest_payload' => 'array', 'created_at' => 'immutable_datetime'];
    }
}
