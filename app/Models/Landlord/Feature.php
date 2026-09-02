<?php

namespace App\Models\Landlord;

use Illuminate\Database\Eloquent\Relations\HasMany;

class Feature extends LandlordModel
{
    protected function casts(): array
    {
        return [
            'is_billable' => 'boolean',
        ];
    }

    public function packageFeatures(): HasMany
    {
        return $this->hasMany(PackageFeature::class);
    }

    public function tenantOverrides(): HasMany
    {
        return $this->hasMany(TenantFeatureOverride::class);
    }
}
