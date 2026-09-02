<?php

namespace App\Models\Tenant;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class IntegrationAccount extends TenantModel
{
    protected function casts(): array
    {
        return [
            'is_default' => 'boolean',
            'encrypted_credentials' => 'encrypted:array',
            'settings' => 'array',
        ];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function integrationProvider(): BelongsTo
    {
        return $this->belongsTo(IntegrationProvider::class);
    }

    public function paymentAttempts(): HasMany
    {
        return $this->hasMany(PaymentAttempt::class);
    }

    public function providerTransactions(): HasMany
    {
        return $this->hasMany(ProviderTransaction::class);
    }

    public function webhookReceipts(): HasMany
    {
        return $this->hasMany(WebhookReceipt::class);
    }

    public function providerSettlements(): HasMany
    {
        return $this->hasMany(ProviderSettlement::class);
    }

    public function aggregatorOrders(): HasMany
    {
        return $this->hasMany(AggregatorOrder::class);
    }

    public function fiscalDevices(): HasMany
    {
        return $this->hasMany(FiscalDevice::class);
    }

    public function taxFilingSubmissions(): HasMany
    {
        return $this->hasMany(TaxFilingSubmission::class);
    }

    public function notificationDeliveries(): HasMany
    {
        return $this->hasMany(NotificationDelivery::class);
    }
}
