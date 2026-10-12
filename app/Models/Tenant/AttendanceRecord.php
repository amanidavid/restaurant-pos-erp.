<?php

namespace App\Models\Tenant;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AttendanceRecord extends TenantModel
{
    protected function casts(): array
    {
        return [
            'attendance_date' => 'immutable_date',
            'clock_in_at' => 'immutable_datetime',
            'clock_out_at' => 'immutable_datetime',
            'worked_minutes' => 'integer',
            'late_minutes' => 'integer',
            'overtime_minutes' => 'integer',
            'adjusted_at' => 'immutable_datetime',
            'recorded_at' => 'immutable_datetime',
        ];
    }

    public function tenantUser(): BelongsTo
    {
        return $this->belongsTo(TenantUser::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function shiftDefinition(): BelongsTo
    {
        return $this->belongsTo(ShiftDefinition::class);
    }

    public function shiftSession(): BelongsTo
    {
        return $this->belongsTo(ShiftSession::class);
    }

    public function adjustedBy(): BelongsTo
    {
        return $this->belongsTo(TenantUser::class, 'adjusted_by_tenant_user_id');
    }

    public function recordedBy(): BelongsTo
    {
        return $this->belongsTo(TenantUser::class, 'recorded_by_tenant_user_id');
    }
}
