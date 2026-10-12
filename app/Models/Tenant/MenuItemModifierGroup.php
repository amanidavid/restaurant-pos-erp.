<?php

namespace App\Models\Tenant;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MenuItemModifierGroup extends TenantInternalModel
{
    protected function casts(): array
    {
        return [
            'sort_order' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    public function menuItem(): BelongsTo
    {
        return $this->belongsTo(MenuItem::class);
    }

    public function modifierGroup(): BelongsTo
    {
        return $this->belongsTo(ModifierGroup::class);
    }
}
