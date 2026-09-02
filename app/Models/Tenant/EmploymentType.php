<?php

namespace App\Models\Tenant;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class EmploymentType extends TenantModel
{
    protected function casts(): array
    {
        return [
            'is_salary_based' => 'boolean',
        ];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function tenantUsers(): HasMany
    {
        return $this->hasMany(TenantUser::class);
    }

    public function jobHistories(): HasMany
    {
        return $this->hasMany(TenantUserJobHistory::class);
    }
}
