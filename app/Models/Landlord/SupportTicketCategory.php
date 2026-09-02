<?php

namespace App\Models\Landlord;

class SupportTicketCategory extends LandlordModel
{
    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }
}
