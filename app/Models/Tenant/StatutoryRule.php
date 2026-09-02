<?php

namespace App\Models\Tenant;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class StatutoryRule extends TenantModel
{
    protected function casts(): array
    {
        return [
            'employee_contributes' => 'boolean',
            'employer_contributes' => 'boolean',
            'effective_from' => 'immutable_date',
            'effective_to' => 'immutable_date',
        ];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function brackets(): HasMany
    {
        return $this->hasMany(StatutoryBracket::class);
    }

    public function employeeProfiles(): HasMany
    {
        return $this->hasMany(EmployeeStatutoryProfile::class);
    }
}
