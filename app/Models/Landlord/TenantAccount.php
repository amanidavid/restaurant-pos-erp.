<?php

namespace App\Models\Landlord;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TenantAccount extends LandlordModel
{
    protected function casts(): array
    {
        return [
            'is_owner' => 'boolean',
            'invited_at' => 'immutable_datetime',
            'joined_at' => 'immutable_datetime',
            'ended_at' => 'immutable_datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function invitationsSent(): HasMany
    {
        return $this->hasMany(TenantAccountInvitation::class, 'invited_by_tenant_account_id');
    }

    public function deviceSessions(): HasMany
    {
        return $this->hasMany(UserDeviceSession::class);
    }
}
