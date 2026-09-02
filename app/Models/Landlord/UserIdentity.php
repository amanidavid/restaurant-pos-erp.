<?php

namespace App\Models\Landlord;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserIdentity extends LandlordModel
{
    protected function casts(): array
    {
        return [
            'provider_verified' => 'boolean',
            'metadata' => 'array',
            'linked_at' => 'immutable_datetime',
            'last_used_at' => 'immutable_datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
