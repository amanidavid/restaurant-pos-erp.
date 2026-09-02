<?php

namespace App\Models\Landlord;

class RetentionPolicyTemplate extends LandlordModel
{
    protected function casts(): array
    {
        return ['rules' => 'array', 'is_active' => 'boolean'];
    }
}
