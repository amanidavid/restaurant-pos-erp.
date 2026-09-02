<?php

namespace App\Models\Landlord;

class ReportScheduleTemplate extends LandlordModel
{
    protected function casts(): array
    {
        return ['parameters' => 'array', 'is_active' => 'boolean'];
    }
}
