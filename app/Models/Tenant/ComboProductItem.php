<?php

namespace App\Models\Tenant;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ComboProductItem extends TenantModel
{
    protected function casts(): array
    {
        return [
            'quantity' => 'integer',
            'is_required' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    public function comboProduct(): BelongsTo
    {
        return $this->belongsTo(ComboProduct::class);
    }

    public function componentMenuItem(): BelongsTo
    {
        return $this->belongsTo(MenuItem::class, 'component_menu_item_id');
    }
}
