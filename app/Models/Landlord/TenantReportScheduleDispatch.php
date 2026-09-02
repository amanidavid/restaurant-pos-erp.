<?php

namespace App\Models\Landlord;

class TenantReportScheduleDispatch extends LandlordModel
{
    protected function casts(): array
    {
        return ['scheduled_for' => 'immutable_datetime', 'started_at' => 'immutable_datetime', 'completed_at' => 'immutable_datetime', 'metadata' => 'array'];
    }
}
