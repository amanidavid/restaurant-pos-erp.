<?php

namespace App\Http\Requests\Web\Menu\Concerns;

use App\Http\Requests\Concerns\HasApiValidationMessages;

trait HasMenuValidationMessages
{
    use HasApiValidationMessages {
        attributes as protected baseAttributes;
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return array_merge($this->baseAttributes(), [
            'name' => 'name',
            'description' => 'description',
            'status' => 'status',
            'sku' => 'SKU',
            'menu_category_uuid' => 'menu category',
            'tax_code_uuid' => 'tax code',
            'allows_open_price' => 'open price setting',
            'is_combo' => 'combo setting',
            'is_active' => 'active status',
            'is_available' => 'availability',
            'is_visible' => 'visibility',
            'sort_order' => 'sort order',
            'branch_uuid' => 'branch',
            'kitchen_section_uuid' => 'kitchen section',
            'price' => 'price',
            'cost_price' => 'cost price',
            'preparation_minutes' => 'preparation time',
            'items' => 'items',
            'items.*.menu_item_uuid' => 'menu item',
            'items.*.branch_menu_item_uuid' => 'branch menu item',
            'items.*.component_menu_item_uuid' => 'combo component',
            'items.*.kitchen_section_uuid' => 'kitchen section',
            'items.*.price' => 'price',
            'items.*.cost_price' => 'cost price',
            'items.*.preparation_minutes' => 'preparation time',
            'items.*.is_available' => 'availability',
            'items.*.is_visible' => 'visibility',
            'items.*.quantity' => 'quantity',
            'items.*.is_required' => 'required setting',
            'items.*.sort_order' => 'sort order',
            'branches' => 'branches',
            'branches.*.branch_uuid' => 'branch',
            'branches.*.kitchen_section_uuid' => 'kitchen section',
            'branches.*.price' => 'branch price',
            'branches.*.cost_price' => 'branch cost price',
            'branches.*.preparation_minutes' => 'branch preparation time',
            'branches.*.is_available' => 'branch availability',
            'branches.*.is_visible' => 'branch visibility',
            'branches.*.sort_order' => 'branch sort order',
            'modifier_groups' => 'modifier groups',
            'modifier_groups.*.modifier_group_uuid' => 'modifier group',
            'modifier_groups.*.sort_order' => 'modifier group sort order',
            'modifier_groups.*.is_active' => 'modifier group active status',
            'options' => 'modifier options',
            'options.*.name' => 'modifier option name',
            'options.*.tax_code_uuid' => 'modifier option tax code',
            'options.*.price_delta' => 'modifier option price',
            'options.*.is_default' => 'default option setting',
            'options.*.is_active' => 'modifier option active status',
            'options.*.sort_order' => 'modifier option sort order',
            'ingredients' => 'ingredients',
            'ingredients.*.ingredient_uuid' => 'ingredient',
            'ingredients.*.unit_uuid' => 'ingredient unit',
            'ingredients.*.quantity' => 'ingredient quantity',
            'ingredients.*.waste_percentage' => 'waste percentage',
            'ingredients.*.notes' => 'ingredient notes',
            'ingredients.*.sort_order' => 'ingredient sort order',
        ], method_exists($this, 'customAttributes') ? $this->customAttributes() : []);
    }

}
