<?php

namespace App\Models\Landlord;

use App\Modules\Shared\Database\Concerns\HasPublicUuid;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use RuntimeException;
use Spatie\Multitenancy\Models\Tenant as SpatieTenant;

class Tenant extends SpatieTenant
{
    use HasPublicUuid;

    protected $connection = 'landlord';

    protected $guarded = ['id'];

    public function databasePlacements(): HasMany
    {
        return $this->hasMany(TenantDatabase::class);
    }

    public function domains(): HasMany
    {
        return $this->hasMany(TenantDomain::class);
    }

    public function accounts(): HasMany
    {
        return $this->hasMany(TenantAccount::class);
    }

    public function provisioningRuns(): HasMany
    {
        return $this->hasMany(TenantProvisioningRun::class);
    }

    public function subscriptions(): HasMany
    {
        return $this->hasMany(TenantSubscription::class);
    }

    public function featureOverrides(): HasMany
    {
        return $this->hasMany(TenantFeatureOverride::class);
    }

    public function subscriptionInvoices(): HasMany
    {
        return $this->hasMany(SubscriptionInvoice::class);
    }

    public function activeDatabasePlacement(): HasOne
    {
        return $this->hasOne(TenantDatabase::class)
            ->where('is_primary', true)
            ->whereIn('status', ['provisioning', 'active', 'draining']);
    }

    public function primaryDomain(): HasOne
    {
        return $this->hasOne(TenantDomain::class)
            ->where('is_primary', true);
    }

    public function getDatabaseName(): string
    {
        $databaseName = $this->activeDatabasePlacement?->database_name;

        if (! is_string($databaseName) || $databaseName === '') {
            throw new RuntimeException("Tenant {$this->uuid} has no active primary database placement.");
        }

        return $databaseName;
    }
}
