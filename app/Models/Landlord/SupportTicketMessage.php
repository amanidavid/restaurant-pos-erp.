<?php

namespace App\Models\Landlord;

class SupportTicketMessage extends LandlordModel
{
    protected function casts(): array
    {
        return ['sent_at' => 'immutable_datetime'];
    }
}
