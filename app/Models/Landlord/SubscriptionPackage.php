<?php

namespace App\Models\Landlord;

use Illuminate\Database\Eloquent\Relations\HasMany;

class SubscriptionPackage extends LandlordModel
{
    protected $table = 'packages';

    protected function casts(): array
    {
        return [
            'base_price' => 'decimal:2',
            'is_custom' => 'boolean',
        ];
    }

    public function packageFeatures(): HasMany
    {
        return $this->hasMany(PackageFeature::class, 'package_id');
    }

    public function subscriptions(): HasMany
    {
        return $this->hasMany(TenantSubscription::class, 'package_id');
    }
}
