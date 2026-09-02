<?php

namespace App\Models\Landlord;

class TenantReportScheduleRecipient extends LandlordModel
{
    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }
}
