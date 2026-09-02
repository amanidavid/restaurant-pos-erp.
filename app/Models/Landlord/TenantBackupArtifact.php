<?php

namespace App\Models\Landlord;

class TenantBackupArtifact extends LandlordModel
{
    protected function casts(): array
    {
        return ['byte_size' => 'integer', 'checksum_verified_at' => 'immutable_datetime', 'metadata' => 'array'];
    }
}
