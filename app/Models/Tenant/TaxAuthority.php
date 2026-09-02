<?php

namespace App\Models\Tenant;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TaxAuthority extends TenantModel
{
    protected function casts(): array
    {
        return [
            'settings' => 'array',
        ];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function registrations(): HasMany
    {
        return $this->hasMany(TaxRegistration::class);
    }

    public function fiscalDevices(): HasMany
    {
        return $this->hasMany(FiscalDevice::class);
    }

    public function returnPeriods(): HasMany
    {
        return $this->hasMany(TaxReturnPeriod::class);
    }
}
