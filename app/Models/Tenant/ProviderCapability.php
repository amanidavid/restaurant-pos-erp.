<?php

namespace App\Models\Tenant;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProviderCapability extends TenantModel
{
    protected function casts(): array
    {
        return [
            'is_enabled' => 'boolean',
            'settings' => 'array',
        ];
    }

    public function integrationProvider(): BelongsTo
    {
        return $this->belongsTo(IntegrationProvider::class);
    }
}
