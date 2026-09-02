<?php

namespace App\Models\Tenant;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

class MenuItem extends TenantModel implements HasMedia
{
    use InteractsWithMedia;

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'allows_open_price' => 'boolean',
            'is_combo' => 'boolean',
        ];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function menuCategory(): BelongsTo
    {
        return $this->belongsTo(MenuCategory::class);
    }

    public function taxCode(): BelongsTo
    {
        return $this->belongsTo(TaxCode::class);
    }

    public function branchMenuItems(): HasMany
    {
        return $this->hasMany(BranchMenuItem::class);
    }

    public function menuItemPrices(): HasMany
    {
        return $this->hasMany(MenuItemPrice::class);
    }

    public function modifierGroups(): HasMany
    {
        return $this->hasMany(MenuItemModifierGroup::class);
    }

    public function recipe(): HasOne
    {
        return $this->hasOne(Recipe::class);
    }

    public function comboProduct(): HasOne
    {
        return $this->hasOne(ComboProduct::class);
    }

    public function componentOfCombos(): HasMany
    {
        return $this->hasMany(ComboProductItem::class, 'component_menu_item_id');
    }

    public function registerMediaCollections(): void
    {
        $this
            ->addMediaCollection('menu_image')
            ->singleFile()
            ->useDisk('tenant_public_media');
    }
}
