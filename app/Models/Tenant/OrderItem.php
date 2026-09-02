<?php

namespace App\Models\Tenant;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class OrderItem extends TenantModel
{
    protected function casts(): array
    {
        return [
            'unit_price_snapshot' => 'decimal:2',
            'preparation_minutes_snapshot' => 'integer',
            'quantity' => 'decimal:4',
            'discount_amount' => 'decimal:2',
            'tax_amount' => 'decimal:2',
            'line_total' => 'decimal:2',
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function parentOrderItem(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_order_item_id');
    }

    public function childItems(): HasMany
    {
        return $this->hasMany(self::class, 'parent_order_item_id');
    }

    public function branchMenuItem(): BelongsTo
    {
        return $this->belongsTo(BranchMenuItem::class);
    }

    public function modifiers(): HasMany
    {
        return $this->hasMany(OrderItemModifier::class);
    }

    public function kitchenTicketItems(): HasMany
    {
        return $this->hasMany(KitchenTicketItem::class);
    }
}
