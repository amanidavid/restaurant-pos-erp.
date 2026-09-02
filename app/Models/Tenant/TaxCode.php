<?php

namespace App\Models\Tenant;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class TaxCode extends TenantModel
{
    protected function casts(): array
    {
        return [
            'rate_percentage' => 'decimal:4',
            'is_inclusive' => 'boolean',
            'effective_from' => 'immutable_date',
            'effective_to' => 'immutable_date',
        ];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function menuItems(): HasMany
    {
        return $this->hasMany(MenuItem::class);
    }

    public function ingredients(): HasMany
    {
        return $this->hasMany(Ingredient::class);
    }

    public function modifierOptions(): HasMany
    {
        return $this->hasMany(ModifierOption::class);
    }

    public function taxCodeAccount(): HasOne
    {
        return $this->hasOne(TaxCodeAccount::class);
    }

    public function taxTransactions(): HasMany
    {
        return $this->hasMany(TaxTransaction::class);
    }

    public function fiscalReceiptLines(): HasMany
    {
        return $this->hasMany(FiscalReceiptLine::class);
    }

    public function taxReturnLines(): HasMany
    {
        return $this->hasMany(TaxReturnLine::class);
    }
}
