<?php

namespace App\Models\Landlord;

class PlatformArchiveManifest extends LandlordModel
{
    protected function casts(): array
    {
        return ['record_count' => 'integer', 'byte_size' => 'integer', 'manifest_payload' => 'array'];
    }
}
