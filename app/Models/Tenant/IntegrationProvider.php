<?php

namespace App\Models\Tenant;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class IntegrationProvider extends TenantModel
{
    protected function casts(): array
    {
        return [
            'metadata' => 'array',
        ];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function capabilities(): HasMany
    {
        return $this->hasMany(ProviderCapability::class);
    }

    public function accounts(): HasMany
    {
        return $this->hasMany(IntegrationAccount::class);
    }
}
