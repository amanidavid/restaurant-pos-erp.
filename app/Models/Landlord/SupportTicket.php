<?php

namespace App\Models\Landlord;

class SupportTicket extends LandlordModel
{
    protected function casts(): array
    {
        return ['opened_at' => 'immutable_datetime', 'closed_at' => 'immutable_datetime', 'metadata' => 'array'];
    }
}
