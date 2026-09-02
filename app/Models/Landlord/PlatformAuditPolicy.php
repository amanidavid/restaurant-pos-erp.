<?php

namespace App\Models\Landlord;

class PlatformAuditPolicy extends LandlordModel
{
    protected function casts(): array
    {
        return ['is_enabled' => 'boolean', 'included_events' => 'array', 'excluded_fields' => 'array'];
    }
}
