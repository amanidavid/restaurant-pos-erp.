<?php

namespace App\Models\Landlord;

class SupportAccessGrant extends LandlordModel
{
    protected function casts(): array
    {
        return ['expires_at' => 'immutable_datetime', 'revoked_at' => 'immutable_datetime'];
    }
}
