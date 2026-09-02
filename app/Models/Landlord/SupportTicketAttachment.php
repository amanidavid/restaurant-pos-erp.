<?php

namespace App\Models\Landlord;

class SupportTicketAttachment extends LandlordModel
{
    protected function casts(): array
    {
        return ['byte_size' => 'integer'];
    }
}
