<?php

namespace App\Models\Landlord;

class TenantReportScheduleAssignment extends LandlordModel
{
    protected function casts(): array
    {
        return ['next_run_at' => 'immutable_datetime', 'last_run_at' => 'immutable_datetime', 'parameters_override' => 'array'];
    }
}
