<?php

namespace App\Models\Tenant;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AccountCategory extends TenantModel
{
    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function accountClass(): BelongsTo
    {
        return $this->belongsTo(AccountClass::class);
    }

    public function subcategories(): HasMany
    {
        return $this->hasMany(AccountSubcategory::class);
    }

    public function chartAccounts(): HasMany
    {
        return $this->hasMany(ChartAccount::class);
    }
}
