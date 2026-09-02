<?php

namespace App\Services\Tenant\Menu;

use App\Models\Tenant\ComboProduct;
use App\Models\Tenant\MenuItem;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ComboProductService
{
    public function sync(MenuItem $menuItem, array $data): ComboProduct
    {
        return DB::connection('tenant')->transaction(function () use ($menuItem, $data): ComboProduct {
            if (! $menuItem->is_combo) {
                throw ValidationException::withMessages([
                    'menu_item' => ['This menu item must be marked as a combo before combo components can be configured.'],
                ]);
            }

            $components = $this->componentMap($menuItem, collect($data['items']));

            $combo = ComboProduct::query()->updateOrCreate(
                ['menu_item_id' => $menuItem->id],
                ['is_active' => $data['is_active']]
            );

            $combo->items()->delete();

            foreach ($data['items'] as $row) {
                $combo->items()->create([
                    'component_menu_item_id' => $components[$row['component_menu_item_uuid']],
                    'quantity' => $row['quantity'],
                    'is_required' => $row['is_required'],
                    'sort_order' => $row['sort_order'] ?? 1,
                ]);
            }

            return $combo->load(['menuItem:id,uuid,name', 'items.componentMenuItem:id,uuid,name,is_combo']);
        });
    }

    private function componentMap(MenuItem $menuItem, Collection $items): Collection
    {
        $uuids = $items->pluck('component_menu_item_uuid')->unique()->values();

        $components = MenuItem::query()
            ->whereIn('uuid', $uuids)
            ->get(['id', 'uuid', 'is_combo'])
            ->keyBy('uuid');

        if ($components->count() !== $uuids->count()) {
            throw ValidationException::withMessages([
                'items' => ['One or more combo component menu items could not be found.'],
            ]);
        }

        if ($components->contains(fn (MenuItem $component) => $component->id === $menuItem->id)) {
            throw ValidationException::withMessages([
                'items' => ['A combo cannot contain itself as a component.'],
            ]);
        }

        $componentIds = $components->pluck('id');

        $createsCircularReference = ComboProduct::query()
            ->whereIn('menu_item_id', $componentIds)
            ->whereHas('items', fn ($query) => $query->where('component_menu_item_id', $menuItem->id))
            ->exists();

        if ($createsCircularReference) {
            throw ValidationException::withMessages([
                'items' => ['Combo component selection creates a circular combo reference.'],
            ]);
        }

        return $components->pluck('id', 'uuid');
    }
}
