<?php

namespace App\Models\Tenant;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Reservation extends TenantModel
{
    protected function casts(): array
    {
        return [
            'guest_count' => 'integer',
            'reserved_from' => 'immutable_datetime',
            'reserved_to' => 'immutable_datetime',
        ];
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function createdByTenantUser(): BelongsTo
    {
        return $this->belongsTo(TenantUser::class, 'created_by_tenant_user_id');
    }

    public function reservationTables(): HasMany
    {
        return $this->hasMany(ReservationTable::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }
}
