<?php

namespace App\Models\Tenant;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ShiftSession extends TenantModel
{
    protected function casts(): array
    {
        return [
            'opened_at' => 'immutable_datetime',
            'closed_at' => 'immutable_datetime',
            'opening_float_amount' => 'decimal:2',
            'expected_cash_amount' => 'decimal:2',
            'counted_cash_amount' => 'decimal:2',
            'closing_variance_amount' => 'decimal:2',
        ];
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function businessDate(): BelongsTo
    {
        return $this->belongsTo(BusinessDate::class);
    }

    public function openedByTenantUser(): BelongsTo
    {
        return $this->belongsTo(TenantUser::class, 'opened_by_tenant_user_id');
    }

    public function closedByTenantUser(): BelongsTo
    {
        return $this->belongsTo(TenantUser::class, 'closed_by_tenant_user_id');
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    public function attendanceRecords(): HasMany
    {
        return $this->hasMany(AttendanceRecord::class);
    }

    public function cashMovements(): HasMany
    {
        return $this->hasMany(CashMovement::class);
    }
}
