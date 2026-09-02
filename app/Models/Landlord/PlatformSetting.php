<?php

namespace App\Models\Landlord;

class PlatformSetting extends LandlordModel
{
    protected function casts(): array
    {
        return ['payload' => 'array'];
    }
}
