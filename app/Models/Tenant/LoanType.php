<?php

namespace App\Models\Tenant;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class LoanType extends TenantModel
{
    protected function casts(): array
    {
        return [
            'default_interest_rate' => 'decimal:4',
        ];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function employeeLoans(): HasMany
    {
        return $this->hasMany(EmployeeLoan::class);
    }
}
