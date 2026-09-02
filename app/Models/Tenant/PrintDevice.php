<?php

namespace App\Models\Tenant;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PrintDevice extends TenantModel
{
    protected function casts(): array
    {
        return [
            'paper_width_mm' => 'integer',
            'is_default_receipt_printer' => 'boolean',
            'is_default_bill_printer' => 'boolean',
            'is_default_kitchen_printer' => 'boolean',
            'settings' => 'array',
        ];
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function printJobs(): HasMany
    {
        return $this->hasMany(PrintJob::class);
    }
}
